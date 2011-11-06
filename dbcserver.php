#!/usr/bin/env php
<?php

require_once('ServerConfig.class.php');

try {
	$c = new ServerConfig();
	$baseDir = $c->getBaseDir();

	if (empty($argv[1])) {
		echo "Usage: \n";
		echo "  dbcserver adduser [username] [password]\n";
		echo "  dbcserver rmuser [username]\n";
		echo "  dbcserver setpassword [username] [new password]\n";
		echo "\n";
		echo "existing users:\n";
		
		foreach (glob($baseDir . '*', GLOB_ONLYDIR) as $dir) {
			echo " * " . basename($dir) . "\n";
		}

		echo "\n";
		exit(0);
	}

	$command = $argv[1];

	switch ($command) {
		case 'adduser': cmd_adduser(); break;
		case 'rmuser': cmd_rmuser(); break;
		case 'setpassword': cmd_setpassword(); break;
		default: throw new Exception('unknown command');
	}

	exit(0);
} catch (Exception $e) {
	echo "err: " . $e->getMessage() . "\n";
	exit(1);
}

function cmd_adduser()
{
	global $argv, $baseDir;

	if (empty($argv[2])) {
		throw new Exception('missing 2nd argument: username');
	}
	
	if (empty($argv[3])) {
		throw new Exception('missing 3nd argument: password');
	}

	$user = $argv[2];
	$pass = md5($argv[3]);

	if (file_exists($baseDir . $user)) {
		throw new Exception("user $user already exists");
	}

	if (!mkdir($dir = $baseDir . $user)) {
		throw new Exception("could not create directory $dir");
	}

	if (!file_put_contents($file = $baseDir . $user . '/pw', $pass)) {
		throw new Exception("could not write file $file");
	}

	echo "user $user created.\n";
}

function cmd_rmuser()
{
	global $argv, $baseDir;

	if (empty($argv[2])) {
		throw new Exception('missing 2nd argument: username');
	}
	
	$user = $argv[2];

	if (!file_exists($baseDir . $user)) {
		throw new Exception("user $user does not exist");
	}

	echo "to delete user $user, delete the following directory manually:\n" . $baseDir . $user . "\n";
}

function cmd_setpassword()
{
	global $argv, $baseDir;

	if (empty($argv[2])) {
		throw new Exception('missing 2nd argument: username');
	}
	
	if (empty($argv[3])) {
		throw new Exception('missing 3nd argument: password');
	}

	$user = $argv[2];
	$pass = md5($argv[3]);

	if (!file_exists($baseDir . $user)) {
		throw new Exception("user $user does not exist");
	}

	if (!file_put_contents($file = $baseDir . $user . '/pw', $pass)) {
		throw new Exception("could not write file $file");
	}

	echo "password for user $user changed.\n";
}

