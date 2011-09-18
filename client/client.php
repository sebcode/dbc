#!/usr/bin/env php
<?php

declare(ticks = 1);

require_once('Client.class.php');

pcntl_signal(SIGTERM, "signalHandler");
pcntl_signal(SIGINT, "signalHandler");

try {
	$client = new Client;
	$client->start();
	exit(0);
} catch (Exception $e) {
	echo "caught exception: " . $e->getMessage() . "\n";
	exit(1);
}

function signalHandler()
{
	if (isset($GLOBALS['client'])) {
		$GLOBALS['client']->stop();
	}

	exit();
}

