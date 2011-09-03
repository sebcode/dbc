#!/usr/bin/env php
<?php

require_once('Server.class.php');

$s = new Server;
$s->setUserID(1);

//echo var_export($s->getFilelist(), true) . "\n";

$filename = '../data/resistance.mp3';
$hash = md5_file($filename);
$size = filesize($filename);
$name = basename($filename);

echo "setFile ";
$ret = $s->setFile($hash, $size, $name);
echo var_export($ret, true) . "\n";

