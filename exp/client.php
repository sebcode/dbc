<?php

require_once('FileList.php');

$tmpDir = '/tmp/tmp/';
$metaDir = '/tmp/meta/';
$watchDir = '/tmp/data/';
$filelist = findfiles($watchDir);

$rtmpDir = '/tmp/rtmp/';
$rwatchDir = '/tmp/rdata/';
$rfilelist = findfiles($rwatchDir);

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

function commit($name, $hash, $size)
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

function dosync()
{
	global $watchDir, $metaDir, $rwatchDir;

	if (file_exists($f = $metaDir . 'filelist.txt')) {
		$localFilelistOld = FileList::createFromFile($f);
	} else {
		$localFilelistOld = new FileList;
	}

	$localFilelistNew = FileList::createFromDir($watchDir);

	$diffLocal = $localFilelistOld->diff($localFilelistNew);

	echo "local localold/localnew: " . var_export($diffLocal, true) . "\n";

	$remoteFilelist = getRemoteFilelist();

	$diffRemote = $localFilelistNew->diff($remoteFilelist);

	echo "diff localnew/remote: " . var_export($diffRemote, true) . "\n";



//	$copyList = array();
//	$commitList = array();
//
//	foreach ($diffLocal as $file => $state) {
//		/* datei wurde lokal geloescht */
//		/* wir loeschen die datei remote, wenn exakt die selbe datei auch remote existiert. */
//		if ($state === 'D'
//			&& ($remoteEntry = $remoteFilelist->getEntry($file))) {
//		
//			$localEntry = $localFilelistOld->getEntry($file);
//
//			/* von remote loeschen, wenn dort die selbe datei existiert */
//			if ($remoteEntry['hash'] == $localEntry['hash']
//				&& $remoteEntry['size'] == $localEntry['size']) {
//
//				echo "deleting remote file: $file\n";
//				unlink($rwatchDir . $file);
//			}
//		}
//
//	}
//
//	foreach ($diffRemote as $file => $state) {
//		/* datei wurde remote geloescht */
//		/* wir loeschen die datei lokal, wenn exakt die selbe datei auch lokal existiert. */
//		if ($state === 'D'
//			&& ($localEntry = $localFilelistNew->getEntry($file))) {
//		
//			$remoteEntry = $remoteFilelist->getEntry($file);
//
//			/* lokal loeschen, wenn dort die selbe datei existiert */
//			if ($remoteEntry['hash'] == $localEntry['hash']
//				&& $remoteEntry['size'] == $localEntry['size']) {
//
//				echo "deleting local file: $file\n";
//				unlink($watchDir . $file);
//				unset($localFilelistNew[$file]);
//			}
//		}
//	}
		
	//	/* datei wurde lokal modifiziert, uploaden */
	//	if ($state === 'M') {
	//		$localEntry = $localFilelistOld->getEntry($file);
	//	
	//		if (upload($file, $localEntry['mtime'], $localEntry['hash'], $localEntry['size'])) {
	//			echo "uploaded $file to remote\n";

	//			commit($file, $localEntry['hash'], $localEntry['size']);
	//		}
	//	}

//		/* new file or file has been modified on remote */
//		if ($state === 'N' || $state === 'M') {
//			/* download from remote */
//
//			$meta = $remoteFilelist->getEntry($file);
//
//			if ($downloadFile = download($meta['hash'], $meta['size'])) {
//				echo "downloaded $file from remote\n";
//
//				$copyList[] = array(
//					'src' => $downloadFile
//					,'dest' => $watchDir . $file
//					,'destRel' => $file
//				);
//			}
//		}
//
//		/* file does not exist on remote => upload to remote */
//		if ($state === 'D') {
//			$meta = $localFilelistNew->getEntry($file);
//
//			if (upload($file, $meta['mtime'], $meta['hash'], $meta['size'])) {
//				echo "uploaded $file to remote\n";
//
//				$info = $meta;
//				$info['file'] = $file;
//
//				$commitList[] = $info;
//			}
//		}
	
//	foreach ($copyList as $info) {
//		echo "copying " . $info['destRel'] . "\n";
//		copy($info['src'], $info['dest']);
//	}
//
//	foreach ($commitList as $info) {
//		echo "commit upload " . $info['file'] . "\n";
//		commit($info['file'], $info['hash'], $info['size']);
//	}

	//$localFilelistNew->toFile($metaDir . 'filelist.txt');
}

/*
	$remoteFilelist = fetchRemoteFilelist()
	$localFilelist = fetchLocalFilelist()
	$diff = diff($remoteFilelist, $localFilelist)
	
	neue datei auf server
		client <-A server
	neue datei auf client
		client A-> server

	datei auf client geloescht
		client D-> server
	datei auf server geloescht
		client <-D server

	datei auf client modifiziert
		client M-> server
	datei auf server modifiziert
		client <-M server
	
	konfliktfaelle:
		datei wurde auf client und server modifiziert
			=> neueste gewinnt, wenn gleich dann die vom server
		datei wurde auf client modifiziert und auf server geloescht
			=> die des clients gewinnt
		datei wurde auf server modifiziert und lokal geloescht
			=> die des clients gewinnt

*/
