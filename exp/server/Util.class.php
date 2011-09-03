<?php

require_once('Config.class.php');

class Util
{
	
	public static function getTempFile()
	{
		$tempDir = Config::getInstance()->getTempDir();

		if (!$file = tempnam($tempDir, 'tmp')) {
			throw new Exception('INTERNAL_ERROR');
		}

		return $file;
	}

	
}
