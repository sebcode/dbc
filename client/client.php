#!/usr/bin/env php
<?php

declare(ticks = 1);

require_once('Client.class.php');

pcntl_signal(SIGTERM, "signalHandlerAbort");
pcntl_signal(SIGINT, "signalHandlerAbort");
pcntl_signal(SIGUSR1, "signalHandlerUsr1");

try {
	$client = new Client;
	$client->start();
	exit(0);
} catch (Exception $e) {
	echo "caught exception: " . $e->getMessage() . "\n";
	exit(1);
}

function signalHandlerUsr1()
{
	if (isset($GLOBALS['client'])) {
		$GLOBALS['client']->remoteChangedCallback();
	}
}

function signalHandlerAbort()
{
	if (isset($GLOBALS['client'])) {
		$GLOBALS['client']->stop();
	}

	exit();
}

