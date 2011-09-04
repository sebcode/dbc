<?php

$dir = '../data/';

$it = new RecursiveDirectoryIterator($dir);

$files = array();

function dohash($filename)
{
	$size = filesize($filename);
	$chunkSize = 1024 * 1024;
	$numChunks = ceil($size / $chunkSize);
	$numFullChunks = floor($size / $chunkSize);

	if ($numChunks == $numFullChunks) {
		$lastChunkSize = 0;
	} else {
		$lastChunkSize = $size - ($numFullChunks * $chunkSize);
	}

	$result = array();
	$result['filename'] = $filename;
	$result['size'] = $size;
	$result['numChunks'] = $numChunks;
	$result['numFullChunks'] = $numFullChunks;
	$result['lastChunkSize'] = $lastChunkSize;
	$result['chunkHashes'] = array();
	$result['hash'] = false;

	$finalHash = false;

	$f = fopen($filename, 'r');

	for ($i = 0; $i < $numChunks; $i++) {
		fseek($f, $i * $chunkSize);
		$data = fread($f, $chunkSize);
		$hash = hash('md4', $data, true);
		$hashStr = gethashstr($hash);
		$result['chunkHashes'][$i] = $hashStr;

		if ($finalHash === false) {
			$finalHash = $hash;
		} else {
			$finalHash = xorHash($finalHash, $hash);
		}
	}

	fclose($f);
	
	$result['hash'] = gethashstr($finalHash);

	return $result;
}

foreach (new RecursiveIteratorIterator($it) as $file) {
	$r = dohash($file->getPathname());

	var_export($r);
}

function gethashstr($hash)
{
	$result = '';

	for ($i = 0; $i < 16; $i++) {
		$result .= sprintf('%02x', ord($hash[$i]));
	}

	return $result;
}

function xorHash($h1, $h2)
{
	if (strlen($h1) !== strlen($h2)) {
		return false;
	}

	$hash = '';

	for ($i = 0; $i < strlen($h1); $i++) {
		$hash .= (int)$h1[$i] xor (int)$h2[$i];
	}

	return $hash;
}

