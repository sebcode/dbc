<?php

$watchDir = '../data/';

$filelist = findfiles($watchDir);

while (true) {
	sleep(1);

	if (localFilelistHasChanged() || remoteFilelistHasChanged()) {
		dosync();
	}
}

function localFilelistHasChanged()
{
	global $filelist;

	$newfilelist = findfiles($watchDir);

	if ($filelist === $newfilelist) {
		return false;
	}

	$filelist = $newfilelist;

	return true;
}

function remoteFilelistHasChanged()
{
	/* poll remote for changes */

	return false;
}

function findfiles($dir)
{
	$it = new RecursiveDirectoryIterator($dir);

	$files = array();

	foreach (new RecursiveIteratorIterator($it) as $file) {
		$relFilename = substr($file, strlen($dir));
		$files[$relFilename] = array(
			'mtime' => $file->getMtime()
			,'getsize' => $file->getSize()
		);
	}

	ksort($files);

	return $files;
}

function dosync()
{
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


	server muss loeschliste pflegen
	diese enthaelt filename, md5
	datei wird beim sync lokal nur geloescht, wenn md5 aus loeschliste
	uebereinstimmt

*/
}

