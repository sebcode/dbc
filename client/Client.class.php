#!/usr/bin/env php
<?php

require_once('FileList.class.php');

class Client
{
	protected $tmpDir = '/tmp/tmp/';
	protected $metaDir = '/tmp/meta/';
	protected $watchDir = '/tmp/data/';
	protected $filelist = array();

	protected $rtmpDir = '/tmp/rtmp/';
	protected $rwatchDir = '/tmp/rdata/';
	protected $rfilelist = array();

	public function start()
	{
		$this->filelist = $this->findfiles($this->watchDir);
		$this->rfilelist = $this->findfiles($this->rwatchDir);
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
		try {
			$newFilelist = $this->findFiles($this->rwatchDir);
		} catch (RuntimeException $e) {
			return false;
		}

		if ($this->rfilelist === $newFilelist) {
			return false;
		}

		$this->rfilelist = $newFilelist;

		echo "remote filelist has changed.\n";
		return true;
	}

	protected function getRemoteFilelist()
	{
		return FileList::createFromDir($this->rwatchDir);
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

	protected function download($hash, $size)
	{
		$f = FileList::createFromDir($this->rwatchDir);

		if (!$name = $f->getNameByHash($hash, $size)) {
			return false;
		}

		$tmpFile = $this->tmpDir . $hash . '.' . $size . '.tmp';

		if (file_exists($tmpFile)) {
			unlink($tmpFile);
		}

		if (!copy($this->rwatchDir . $name, $tmpFile)) {
			echo "failed to download $name from remote\n";
			return false;
		}

		return $tmpFile;
	}

	protected function upload($file, $mtime, $hash, $size)
	{
		$localFile = $this->watchDir . $file;

		if (filemtime($localFile) !== $mtime) {
			throw new Exception("upload of $file failed, mtime changed.");
		}

		$dest = $this->rtmpDir . $hash .'.'. $size . '.tmp';

		if (!copy($localFile, $dest)) {
			throw new Exception("upload of $file failed.");
		}
		
		return true;
	}

	protected function commitUpload($name, $hash, $size)
	{
		$tmpFile = $this->rtmpDir . "$hash.$size.tmp";

		if (!file_exists($tmpFile)) {
			echo "commit of $name failed, uploaded file $tmpFile not found\n";
			return false;
		}

		$dest = $this->rwatchDir . $name;

		if (!is_dir(dirname($dest))) {
			mkdir(dirname($dest));
		}

		if (!copy($tmpFile, $dest)) {
			echo "commit of $name failed.\n";
			return false;
		}

		return true;
	}

	protected function commitDownload($name, $hash, $size)
	{
		$tmpFile = $this->tmpDir . "$hash.$size.tmp";

		if (!file_exists($tmpFile)) {
			echo "commit of $name failed, uploaded file $tmpFile not found\n";
			return false;
		}

		$dest = $this->watchDir . $name;

		if (!is_dir(dirname($dest))) {
			mkdir(dirname($dest));
		}

		if (!copy($tmpFile, $dest)) {
			echo "commit of $name failed.\n";
			return false;
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
			$f = $this->rwatchDir . $change['file'];
			
			unlink($f);
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
			
			if (!$this->download($change['entry']['hash'], $change['entry']['size'])) {
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

		// TODO
		// erst remote actions ausfuehren (upload, remote delete)
		// dann local actions einzeln (download, local del)
		//   => nach jeder action fileliste schreiben

		foreach ($changeList as $change) {
			if (!$this->commitChange($change)) {
				echo "commit change failed\n";
				return;
			}
		}

		$localFilelistNew = FileList::createFromDir($this->watchDir);
		$localFilelistNew->toFile($this->metaDir . 'filelist.txt');
	}

}

