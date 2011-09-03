#!/usr/bin/env php
<?php

require_once('Server.class.php');

$s = new Server;

echo "HI\n";

while ($data = fread(STDIN, 8192)) {
	$data = trim($data);

	if ($data == 'q' || $data == 'quit') {
		echo "BYE\n";
		exit(0);
	}

	try {
		$ret = $s->processRequest($data);

		if ($ret === true) {
			echo "OK\n";
		} else {
			echo "$ret\n";
		}
	} catch (Exception $e) {
		echo 'ERR: ' . $e->getMessage() . "\n";
	}
}

