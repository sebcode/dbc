#!/usr/bin/env php
<?php

require_once('Server.class.php');

$s = new Server;
$s->setUserID(1);

echo var_export($s->getFilelist(), true) . "\n";

