<?php

require_once('Config.class.php');

class DB
{
	protected static $instance;
	
	protected $pdo;
	
	public static function getInstance()
	{
		if (!self::$instance) {
			self::$instance = new DB;
		}

		return self::$instance;
	}

	private function __construct()
	{
		$this->initDB();
	}
	
	private function initDB()
	{
		$workDir = Config::getInstance()->getWorkDir();
		$dataDir = Config::getInstance()->getDataDir();

		$file = $dataDir . 'db.sqlite';

		$new = ! file_exists($file);

		$this->pdo = new \PDO('sqlite:' . $file);
		$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

		if ($new) {
			if (!$sql = file_get_contents($sqlFile = $workDir . 'db.sql')) {
				throw new Exception("Could not load $sqlFile");
			}

			$statements = explode(';', $sql);

			foreach ($statements as $statement) {
				$this->pdo->prepare($statement)->execute();
			}
		}
	}

	public function getPDO()
	{
		return $this->pdo;
	}

}
