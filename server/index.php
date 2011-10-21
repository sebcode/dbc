<?php

require_once('Server.class.php');

if (!file_exists('config.php')) {
	send500('config.php not readable');
}

if (!$config = include('config.php')) {
	send500('config.php not readable');
}

if (!isset($config['baseDir'])) {
	send500('invalid config: baseDir not set');
}

if (!is_dir($config['baseDir'])) {
	send500('invalid config: baseDir not found');
}

$s = new Server($config['baseDir']);

try {
	$s->handleRequest();
} catch (Exception $e) {
	send500($e->getMessage());
}

function send500($msg)
{
	header('HTTP/1.1 500 Internal Server Error');
	echo $msg . "\n";
	exit();
}

