<?php

$config = include('config.php');

$it = new RecursiveDirectoryIterator($config['dir']);

$files = array();

foreach (new RecursiveIteratorIterator($it) as $file) {
	$relFilename = substr($file, strlen($config['dir']));

	$size = $file->getSize();
	$chunkSize = 1024 * 1024;
	$numChunks = ceil($size / $chunkSize);
	$numFullChunks = floor($size / $chunkSize);

	if ($numChunks == $numFullChunks) {
		$lastChunkSize = 0;
	} else {
		$lastChunkSize = $size - ($numFullChunks * $chunkSize);
	}

	echo $relFilename . "\n";
	echo "  has size of $size B\n";
	echo "  has $numChunks chunks of 1 MB ($numFullChunks full)\n";
	echo "  last chunk size is $lastChunkSize B\n";

	$f = fopen($file->getPathname(), 'r');

	for ($i = 0; $i < $numChunks; $i++) {
		 fseek($f, $i * $chunkSize);
		 $data = fread($f, $chunkSize);
		 $hash = hash('md4', $data, true);
		 $hashStr = gethashstr($hash);
		 echo "    $i : $hashStr\n";
	}

	fclose($f);
}

function gethashstr($hash)
{
	$result = '';

	for ($i = 0; $i < 16; $i++) {
		$result .= sprintf('%02x', ord($hash[$i]));
	}

	return $result;
}

