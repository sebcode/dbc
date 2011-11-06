<?php

require_once('ServerConfig.class.php');
require_once('Server.class.php');

try {
	$c = new ServerConfig();
	$baseDir = $c->getBaseDir();

	$s = new Server($baseDir);
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

