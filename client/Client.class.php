#!/usr/bin/env php
<?php

require_once('FileList.class.php');
require_once('RemoteChangeListener.class.php');

class Client
{
	protected $filelist = array();

	protected $serverUrl;
	protected $user;
	protected $pwHash;
	protected $watchDir;
	protected $tmpDir;
	protected $metaDir;

	protected $rcl;
	protected $lastRemoteChange = 0;

	public function __construct($watchDir, $serverUrl, $user, $pwHash)
	{
		$this->watchDir = rtrim($watchDir, '/') . '/';
		$this->serverUrl = $serverUrl;
		$this->user = $user;
		$this->pwHash = $pwHash;

		@mkdir($this->metaDir . '.dbc');
		$this->metaDir = $this->watchDir . '.dbc/';

		$this->tmpDir = $this->metaDir . 'tmp/';
		@mkdir($this->tmpDir);
	}

	public function start()
	{
		$this->handleStartupLock();

		$this->rcl = new RemoteChangeListener($this->serverUrl, $this->user, $this->pwHash);
		$this->rcl->start();

		$this->filelist = $this->findfiles($this->watchDir);
		$forceSync = true;

		while (true) {
			if ($forceSync || $this->localFilelistHasChanged() || $this->remoteFilelistHasChanged()) {
				echo "dosync\n";

				try {
					$this->doSync();
				} catch (Exception $e) {
					echo "Exception during sync: " . $e->getMessage() . ". Wait 5 sec...\n";
					$forceSync = true;
					sleep(5);
					continue;
				}

				$forceSync = false;
			}
			
			sleep(1);
		}
	}

	public function stop()
	{
		if (file_exists($this->metaDir . 'lock')) {
			unlink($this->metaDir . 'lock');
		}
	}

	public function remoteChangedCallback()
	{
		echo "remoteChangedCallback\n";
		$this->rcl->setLastChange();
	}

	protected function handleStartupLock()
	{
		$lockFile = $this->metaDir . 'lock';

		if (file_exists($lockFile)) {
			if (($c = file_get_contents($lockFile)) === false) {
				throw new Exception('Could not read lockfile ' . $lockFile);
			}

			$oldPid = (int) trim($c);

			if ($oldPid) {
				if (posix_getsid($oldPid) === false) {
					echo "remove stale lockfile $lockFile\n";
					unlink($lockFile);
				} else {
					throw new Exception("already running with pid $oldPid");
				}
			}
		}

		if (file_put_contents($lockFile, posix_getpid() . "\n") === false) {
			throw new Exception('Could not write lockfile ' . $lockFile);
		}
	}

	protected function localFilelistHasChanged()
	{
		try {
			$newFilelist = $this->findFiles($this->watchDir);
		} catch (RuntimeException $e) {
			return false;
		}

		if ($this->filelist === $newFilelist) {
			return false;
		}

		$this->filelist = $newFilelist;

		echo "local filelist has changed.\n";
		return true;
	}

	protected function remoteFilelistHasChanged()
	{
		$lc = $this->rcl->getLastChange();

		if ($lc != $this->lastRemoteChange) {
			$this->lastRemoteChange = $lc;
			return true;
		}

		return false;
	}

	protected function getRemoteFilelist()
	{
		$res = $this->sendRequest('getfilelist');

		echo "received remote filelist: $res\n";

		return FileList::createFromData($res);
	}

	protected function findFiles($dir)
	{
		$it = new RecursiveDirectoryIterator($dir);

		$files = array();

		foreach (new RecursiveIteratorIterator($it) as $file) {
			$relFilename = substr($file, strlen($dir));
			$files[$relFilename] = array(
				'mtime' => $file->getMtime()
				,'size' => $file->getSize()
			);
		}

		ksort($files);

		return $files;
	}

	protected function download($hash, $size, $name)
	{
		$tmpFile = $this->tmpDir . $hash . '.' . $size . '.tmp';
		
		$fp = fopen($tmpFile, 'w+');

		if (!$fp) {
			throw new Exception("could not open $tmpFile for writing");
		}

		$ch = $this->getCurlHandle('downloadfile', array(
			'filehash' => $hash,
			'filesize' => $size
		));

		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_exec($ch);
		curl_close($ch);
		fclose($fp);
		
		$actualSize = filesize($tmpFile);

		if ($actualSize != $size) {
			throw new Exception("downloaded file $tmpFile has unexpected size $actualSize (exp: $size)");
		}

		$actualHash = md5_file($tmpFile);

		if ($actualHash != $hash) {
			throw new Exception("downloaded file $tmpFile has unexpected hash $actualHash");
		}

		$dest = $this->watchDir . $name;

		if (file_exists($dest)) {
			unlink($dest);
		}

		if (!is_dir(dirname($dest))) {
			mkdir(dirname($dest));
		}

		if (!rename($tmpFile, $dest)) {
			throw new Exception("failed to move $tmpFile to $dest");
		}

		return true;
	}

	protected function upload($file, $mtime, $hash, $size)
	{
		$localFile = $this->watchDir . $file;

		if (filemtime($localFile) !== $mtime) {
			throw new Exception("upload of $file failed, mtime changed.");
		}

		$tmpFile = $this->tmpDir . $hash .'.'. $size . '.upload.tmp';

		if (!copy($localFile, $tmpFile)) {
			throw new Exception("could not copy $localFile to $tmpFile");
		}

		$ch = $this->getCurlHandle('uploadfile', array(
			'filehash' => $hash,
			'filesize' => $size,
			'filename' => $file
		));

		$postFields = array(
			'file' => "@$tmpFile"
		);

		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
		$res = curl_exec($ch);
		
		unlink($tmpFile);
		
		if (trim($res) != 'OK') {
			throw new Exception('upload failed, server response: ' . $res . "\n");
		}

		return true;
	}

	protected function remoteDelete($hash, $size)
	{
		$res = $this->sendRequest('deletefile', array(
			'filehash' => $hash,
			'filesize' => $size
		));

		if (trim($res) != 'OK') {
			throw new Exception('upload failed, server response: ' . $res . "\n");
		}

		return true;
	}

	protected function getChangeList($diffRemote, $diffLocal, $localFilelistNew, $localFilelistOld, $remoteFilelist)
	{
		$changeList = array();

		foreach ($diffRemote as $file => $state) {
			/* datei wurde remote geloescht, oder lokal neu hinzugekommen */
			if ($state === 'D'
				&& ($localEntry = $localFilelistNew->getEntry($file))) {

				/* wenn lokal nicht neu oder modifiziert, dann wurde datei remote
				 * geloescht. => auch lokal loeschen */
				if (!isset($diffLocal[$file])) {
					$changeList[] = array(
						'file' => $file
						,'action' => 'local_delete'
						,'entry' => $localEntry
						,'desc' => "local delete: $file"
					);
				}
				/* wenn lokal neu oder modifiziert, dann uploaden */
				else {
					$changeList[] = array(
						'file' => $file
						,'action' => 'upload'
						,'desc' => "upload: $file"
						,'entry' => $localEntry
					);
				}
			}

			/* datei ist remote modifiziert/neu */
			if ($state === 'M' || $state === 'N') {
				/* wenn lokal nicht neu oder modifiziert, dann datei herunterladen */
				if (!isset($diffLocal[$file])) {
					$changeList[] = array(
						'file' => $file
						,'action' => 'download'
						,'desc' => "download: $file"
						,'entry' => $remoteFilelist->getEntry($file)
					);
				}
				/* lokal geloescht */
				else if ($state === 'N' && $diffLocal[$file] === 'D') {
					$changeList[] = array(
						'file' => $file
						,'action' => 'remote_delete'
						,'desc' => "remote delete: $file"
						,'entry' => $localFilelistOld->getEntry($file)
					);
				}
				/* ansonsten uploaden */
				else {
					$changeList[] = array(
						'file' => $file
						,'action' => 'upload'
						,'desc' => "upload: $file"
						,'entry' => $localFilelistNew->getEntry($file)
					);
				}
			}
		}
		
		return $changeList;
	}

	protected function commitChange($change)
	{
		if ($change['action'] === 'remote_delete') {
			echo "remote delete " . $change['file'] . "...\n";

			$this->remoteDelete($change['entry']['hash'], $change['entry']['size']);

			return true;
		}

		if ($change['action'] === 'local_delete') {
			echo "local delete " . $change['file'] . "...\n";
			$f = $this->watchDir . $change['file'];
			
			unlink($f);
			return true;
		}
			
		if ($change['action'] === 'download') {
			echo "download " . $change['file'] . "...\n";
			
			if (!$this->download($change['entry']['hash'], $change['entry']['size'], $change['file'])) {
				return false;
			}

			return true;
		}
		
		if ($change['action'] === 'upload') {
			echo "upload " . $change['file'] . "...\n";
			
			if (!$this->upload($change['file'], $change['entry']['mtime'], $change['entry']['hash'], $change['entry']['size'])) {
				return false;
			}

			return true;
		}

		return false;
	}

	protected function doSync()
	{
		echo "-------------------------------------------------------------------\n";

		if (file_exists($f = $this->metaDir . 'filelist.txt')) {
			$localFilelistOld = FileList::createFromFile($f);
		} else {
			$localFilelistOld = new FileList;
		}

		$localFilelistNew = FileList::createFromDir($this->watchDir);
		$diffLocal = $localFilelistOld->diff($localFilelistNew);

		$remoteFilelist = $this->getRemoteFilelist();
		$diffRemote = $localFilelistNew->diff($remoteFilelist);

		#echo 'localFilelistOld: ' . var_export($localFilelistOld->toString(), true) . "\n";
		#echo 'localFilelistNew: ' . var_export($localFilelistNew->toString(), true) . "\n";
		#echo 'remoteFilelist: ' . var_export($remoteFilelist->toString(), true) . "\n";
		echo 'diffLocal: ' . var_export($diffLocal, true) . "\n";
		echo 'diffRemote: ' . var_export($diffRemote, true) . "\n";

		/* changelist berechnen */

		$changeList = $this->getChangeList($diffRemote, $diffLocal, $localFilelistNew, $localFilelistOld, $remoteFilelist);

		if (empty($changeList)) {
			echo "changelist empty.\n";
			return;
		}

		echo 'commit changeList: ' . var_export($changeList, true) . "\n";

		/* remote deletes */
		foreach ($changeList as $change) {
			if ($change['action'] !== 'remote_delete') {
				continue;
			}

			if (!$this->commitChange($change)) {
				echo "commit change failed\n";
				return;
			}
			
			echo "patching filelist for remote_delete: del ". $change['file'] ."\n";
			$localFilelistOld->deleteEntry($change['file']);
			$localFilelistOld->toFile($this->metaDir . 'filelist.txt');
		}

		/* upload */
		foreach ($changeList as $change) {
			if ($change['action'] !== 'upload') {
				continue;
			}

			if (!$this->commitChange($change)) {
				echo "commit change failed\n";
				return;
			}
			
			echo "patching filelist for upload: adding ". $change['file'] ."\n";
			$localFilelistOld->setEntry($change['file'], $change['entry']['hash'], $change['entry']['size'], $change['entry']['mtime']);
			$localFilelistOld->toFile($this->metaDir . 'filelist.txt');
		}

		/* local delete */
		foreach ($changeList as $change) {
			if ($change['action'] !== 'local_delete') {
				continue;
			}

			if (!$this->commitChange($change)) {
				echo "commit change failed\n";
				return;
			}

			echo "patching filelist for local_delete: del ". $change['file'] ."\n";
			$localFilelistOld->deleteEntry($change['file']);
			$localFilelistOld->toFile($this->metaDir . 'filelist.txt');
		}

		/* downloads */
		foreach ($changeList as $change) {
			if ($change['action'] !== 'download') {
				continue;
			}

			if (!$this->commitChange($change)) {
				echo "commit change failed\n";
				return;
			}
			
			echo "patching filelist for download: adding ". $change['file'] ."\n";
			$localFilelistOld->setEntry($change['file'], $change['entry']['hash'], $change['entry']['size'], $change['entry']['mtime']);
			$localFilelistOld->toFile($this->metaDir . 'filelist.txt');
		}
	}

	protected function getCurlHandle($cmd, $args = array())
	{
		$url = $this->serverUrl
			. '?user=' . $this->user
			. '&pass=' . $this->pwHash
			. "&cmd=$cmd";

		foreach ($args as $argName => $argVal) {
			$url .= '&' . rawurlencode($argName) . '=' . rawurlencode($argVal);
		}

		echo "$url\n";

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);

		return $ch;
	}

	protected function sendRequest($cmd, $args = array())
	{
		$ch = $this->getCurlHandle($cmd, $args);
		
		return curl_exec($ch);
	}

}

