<?php

$config = include('config.php');

$filelist = array();

while (true) {
	sleep(5);

	$newfilelist = findfiles($config['dir']);

	if ($filelist === $newfilelist) {
		continue;
	}

	diff($newfilelist, $filelist);

	$filelist = $newfilelist;
}

function diff($fl1, $fl2)
{
	// print changes here
}

