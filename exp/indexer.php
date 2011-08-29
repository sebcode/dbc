<?php

$config = include('config.php');

$files = getfilelist();

var_export($files);

function getfilelist()
{
	global $config;

	$it = new RecursiveDirectoryIterator($config['dir']);

	$files = array();

	foreach (new RecursiveIteratorIterator($it) as $file) {
		$relFilename = substr($file, strlen($config['dir']));
		$files[$relFilename] = array(
			'mtime' => $file->getMtime()
			,'getsize' => $file->getSize()
		);
	}

	ksort($files);

	return $files;
}

