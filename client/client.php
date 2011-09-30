#!/usr/bin/env php
<?php

if (empty($argv[1])) {
	echo "Syntax: dbc [data-directory]\n";
	exit(1);
}

$dir = rtrim($argv[1], '/') . '/';

if (!is_dir($dir)) {
	echo "Directory $dir does not exists. Initialize? ";
	if (trim(fread(STDIN, 1024)) != 'y') {
		echo "Aborted.\n";
		exit(1);
	}

	if (!mkdir($dir)) {
		echo "Could not create directory: $dir\n";
		exit(1);
	}
	
	if (!mkdir($dir . '.dbc')) {
		echo "Could not create directory: $dir.dbc\n";
		exit(1);
	}

	$server = askServer();

	echo "Using server: $server\n";

	list($user, $pass) = askAuth($server);

	if (!file_put_contents($f = "$dir.dbc/server", $server)) {
		echo "Could not write: $f\n";
		exit(1);
	}

	if (!file_put_contents($f = "$dir.dbc/user", $user)) {
		echo "Could not write: $f\n";
		exit(1);
	}

	if (!file_put_contents($f = "$dir.dbc/pw", md5($pass))) {
		echo "Could not write: $f\n";
		exit(1);
	}
}

$dir = realpath($dir) . '/';

if (!is_dir($dir)) {
	echo "Could not find directory: $dir\n";
	exit(1);
}

if (!is_dir("$dir.dbc")
	|| !file_exists("$dir.dbc/user")
	|| !file_exists("$dir.dbc/pw")
	|| !file_exists("$dir.dbc/server")) {

	echo "Not a valid dbc directory: $dir\n";
	exit(1);
}

echo "Using data directory: $dir\n";

if (!$server = file_get_contents($f = "$dir.dbc/server")) {
	echo "Could not read: $f\n";
}

if (!$user = file_get_contents($f = "$dir.dbc/user")) {
	echo "Could not read: $f\n";
}

if (!$pass = file_get_contents($f = "$dir.dbc/pw")) {
	echo "Could not read: $f\n";
}

declare(ticks = 1);

require_once('Client.class.php');

pcntl_signal(SIGTERM, "signalHandlerAbort");
pcntl_signal(SIGINT, "signalHandlerAbort");
pcntl_signal(SIGUSR1, "signalHandlerUsr1");

try {
	$client = new Client($dir, $server, $user, $pass);
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

function askServer()
{
	while (true) {
		echo "Enter server URL: ";
		$serverUrl = trim(fread(STDIN, 8129));

		if ($serverUrl == 'q') {
			echo "Abort.\n";
			exit(1);
		}

		$ch = curl_init($serverUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		$res = curl_exec($ch);
		curl_close($ch);

		if (trim($res) == 'SERVER_OK') {
			return $serverUrl;
		}
		
		echo "Not a valid server, try again.\n";
	}
}

function askAuth($serverUrl)
{
	echo "Username: ";
	$user = trim(fread(STDIN, 8129));

	while (true) {
		echo "Password for user $user: ";
		$pass = trim(fread(STDIN, 8129));

		$url = $serverUrl
			. '?user=' . $user
			. '&pass=' . md5($pass)
			. "&cmd=ping";

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		$res = curl_exec($ch);
		curl_close($ch);

		if (trim($res) == 'PONG') {
			return array($user, $pass);
		}
		
		echo "Auth invalid, try again.\n";
	}
}


