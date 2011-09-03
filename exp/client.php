<?php

require_once('FileList.php');

$tmpDir = '/tmp/tmp/';
$metaDir = '/tmp/meta/';
$watchDir = '/tmp/data/';
$filelist = findfiles($watchDir);

$rtmpDir = '/tmp/rtmp/';
$rwatchDir = '/tmp/rdata/';
$rfilelist = findfiles($rwatchDir);

dosync();

while (true) {
	sleep(1);

	if (localFilelistHasChanged() || remoteFilelistHasChanged()) {
		echo "dosync\n";
		dosync();
	}
}

function localFilelistHasChanged()
{
	global $filelist, $watchDir;

	try {
		$newfilelist = findfiles($watchDir);
	} catch (RuntimeException $e) {
		return false;
	}

	if ($filelist === $newfilelist) {
		return false;
	}

	$filelist = $newfilelist;

	echo "local filelist has changed.\n";
	return true;
}

function remoteFilelistHasChanged()
{
	global $rfilelist, $rwatchDir;

	try {
		$newfilelist = findfiles($rwatchDir);
	} catch (RuntimeException $e) {
		return false;
	}

	if ($rfilelist === $newfilelist) {
		return false;
	}

	$rfilelist = $newfilelist;

	echo "remote filelist has changed.\n";
	return true;
}

function getRemoteFilelist()
{
	global $rwatchDir;

	return FileList::createFromDir($rwatchDir);
}

function findfiles($dir)
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

function download($hash, $size)
{
	global $rwatchDir, $tmpDir;

	$f = FileList::createFromDir($rwatchDir);

	if (!$name = $f->getNameByHash($hash, $size)) {
		return false;
	}

	$tmpFile = $tmpDir . $hash . '.' . $size . '.tmp';

	if (file_exists($tmpFile)) {
		unlink($tmpFile);
	}

	if (!copy($rwatchDir . $name, $tmpFile)) {
		echo "failed to download $name from remote\n";
		return false;
	}

	return $tmpFile;
}

function upload($file, $mtime, $hash, $size)
{
	global $watchDir, $rtmpDir;

	$localFile = $watchDir . $file;

	if (filemtime($localFile) !== $mtime) {
		echo "upload of $file failed, mtime changed.\n";
		return false;
	}

	$dest = $rtmpDir . $hash .'.'. $size . '.tmp';

	if (!copy($localFile, $dest)) {
		echo "upload of $file failed.\n";
		return false;
	}
	
	return true;
}

function commitUpload($name, $hash, $size)
{
	global $rwatchDir, $rtmpDir;

	$tmpFile = $rtmpDir . "$hash.$size.tmp";

	if (!file_exists($tmpFile)) {
		echo "commit of $name failed, uploaded file $tmpFile not found\n";
		return false;
	}

	$dest = $rwatchDir . $name;

	if (!is_dir(dirname($dest))) {
		mkdir(dirname($dest));
	}

	if (!copy($tmpFile, $dest)) {
		echo "commit of $name failed.\n";
		return false;
	}

	return true;
}

function commitDownload($name, $hash, $size)
{
	global $watchDir, $tmpDir;

	$tmpFile = $tmpDir . "$hash.$size.tmp";

	if (!file_exists($tmpFile)) {
		echo "commit of $name failed, uploaded file $tmpFile not found\n";
		return false;
	}

	$dest = $watchDir . $name;

	if (!is_dir(dirname($dest))) {
		mkdir(dirname($dest));
	}

	if (!copy($tmpFile, $dest)) {
		echo "commit of $name failed.\n";
		return false;
	}

	return true;
}

function getChangeList($diffRemote, $diffLocal, $localFilelistNew, $localFilelistOld, $remoteFilelist)
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

function dosync()
{
	global $watchDir, $metaDir, $rwatchDir;
	
	echo "-------------------------------------------------------------------\n";

	if (file_exists($f = $metaDir . 'filelist.txt')) {
		$localFilelistOld = FileList::createFromFile($f);
	} else {
		$localFilelistOld = new FileList;
	}

	$localFilelistNew = FileList::createFromDir($watchDir);

	$diffLocal = $localFilelistOld->diff($localFilelistNew);

	//echo "local localold/localnew: " . var_export($diffLocal, true) . "\n";

	$remoteFilelist = getRemoteFilelist();

	$diffRemote = $localFilelistNew->diff($remoteFilelist);

	//echo "diff localnew/remote: " . var_export($diffRemote, true) . "\n";

	/* changelist berechnen */

	$changeList = getChangeList($diffRemote, $diffLocal, $localFilelistNew, $localFilelistOld, $remoteFilelist);

	if (empty($changeList)) {
		echo "changelist empty.\n";
		return;
	}

	echo 'commit changeList: ' . var_export($changeList, true) . "\n";

	/* commit remote deletes */

	foreach ($changeList as $i) {
		if ($i['action'] === 'remote_delete') {
			echo "remote delete " . $i['file'] . "...\n";
			$f = $rwatchDir . $i['file'];
			if (filesize($f) == $i['entry']['size']) {
				unlink($f);
			} else {
				echo "change detected, CANCEL commit.\n";
			}
		}
	}

	/* commit local deletes */
	foreach ($changeList as $i) {
		if ($i['action'] === 'local_delete') {
			echo "local delete " . $i['file'] . "...\n";
			$f = $watchDir . $i['file'];
			if (filesize($f) == $i['entry']['size']) {
				unlink($f);
			} else {
				echo "change detected, CANCEL commit.\n";
			}
		}
	}

	/* downloaden/uploaden */
	foreach ($changeList as $i) {
		if ($i['action'] === 'download') {
			echo "download " . $i['file'] . "...\n";
			download($i['entry']['hash'], $i['entry']['size']);
		} else if ($i['action'] === 'upload') {
			echo "upload " . $i['file'] . "...\n";
			if (!upload($i['file'], $i['entry']['mtime'], $i['entry']['hash'], $i['entry']['size'])) {
				return;
			}
		}
	}

	/* commit downloads */
	foreach ($changeList as $i) {
		if ($i['action'] === 'download') {
			echo "commit download " . $i['file'] . "...\n";
			if (!commitDownload($i['file'], $i['entry']['hash'], $i['entry']['size'])) {
				return;
			}
		}
	}

	/* commit uploads */
	foreach ($changeList as $i) {
		if ($i['action'] === 'upload') {
			echo "commit upload " . $i['file'] . "...\n";
			if (!commitUpload($i['file'], $i['entry']['hash'], $i['entry']['size'])) {
				return;
			}
		}
	}

	$localFilelistNew = FileList::createFromDir($watchDir);
	$localFilelistNew->toFile($metaDir . 'filelist.txt');
}

