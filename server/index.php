<?php

require_once('Server.class.php');

$s = new Server;

try {
	$s->handleRequest();
} catch (Exception $e) {
	header('HTTP/1.1 500 err');
	echo $e->getMessage() . "\n";
}

