#!/usr/bin/env php
<?php

try {
	read(); // HI

	send("login seb invalid\n");
	read();

	send("PING\n");
	read();

	send("login seb test\n");
	read();

	send("getfilelist\n");
	read();

	send("PING\n");
	read();

} catch (Exception $e) {
	echo $e->getMessage() . "\n";
	exit(1);
}

echo "(done)\n";
exit(0);

function send($msg)
{
	fwrite(STDERR, "SEND : ". trim($msg) . "\n");
	echo "$msg";
}

function read()
{
	$data = trim(fread(STDIN, 8192));

	fwrite(STDERR, "GOT  : $data\n");
}

