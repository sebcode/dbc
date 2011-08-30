<?php

class Config
{
	protected static $instance;

	protected $workDir;

	public static function getInstance()
	{
		if (!self::$instance) {
			self::$instance = new Config;
		}

		return self::$instance;
	}

	private function __construct()
	{
		$this->workDir = dirname(__FILE__) . '/';

		if (!is_dir($d = $this->getDataDir())) {
			if (!mkdir($d)) {
				throw new Exception('Could not create dir: ' . $d);
			}
		}
		
		if (!is_dir($d = $this->getTempDir())) {
			if (!mkdir($d)) {
				throw new Exception('Could not create dir: ' . $d);
			}
		}
	}
	
	public function getWorkDir()
	{
		return $this->workDir;
	}
	
	public function getDataDir()
	{
		return $this->workDir . 'data/';
	}
	
	public function getTempDir()
	{
		return $this->workDir . 'tmp/';
	}
	
}

