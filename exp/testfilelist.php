#!/usr/bin/env php
<?php

require_once('FileList.php');

/* fileliste des servers (oldlist) */
$d1 = 'AAAA 10 123 test.txt
BBBB 11 234 test2.txt
CCCC 11 345 test3.txt
EEEE 11 345 test5.txt
';

$f1 = new FileList($d1);

#echo var_export($f1->getEntries(), true) . "\n";

/* fileliste des clients (newlist) */
$d2 = 'AAAA 10 123 test.txt
BBBB 11 234 test2.txt
DDDD 11 345 test4.txt
';

$f2 = new FileList($d2);

#echo var_export($f2->getEntries(), true) . "\n";

$diff = $f1->diff($f2);
echo var_export($diff, true) . "\n";

