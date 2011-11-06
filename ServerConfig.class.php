<?php

class ServerConfig
{
	private $config;

	public function __construct()
	{
		if (!file_exists('serverconfig.php')) {
			throw new Exception('serverconfig.php not readable');
		}

		if (!$this->config = include('serverconfig.php')) {
			throw new Exception('serverconfig.php not readable');
		}
	}

	public function getBaseDir()
	{
		if (!isset($this->config['baseDir'])) {
			throw new Exception('invalid config: baseDir not set');
		}

		if (!is_dir($this->config['baseDir'])) {
			throw new Exception('invalid config: baseDir not found');
		}

		$baseDir = rtrim($this->config['baseDir'], '/') . '/';

		return $baseDir;
	}

}

