<?php

class Server
{
	protected $workDir;
	protected $tempDir;
	protected $pdo;
	protected $userID = 0;
	
	public function __construct()
	{
		$this->workDir = dirname(__FILE__) . '/';

		if (!is_dir($this->tempDir = $this->workDir . 'tmp')
			&& !mkdir($this->tempDir)) {

			throw new Exception('INTERNAL_ERROR');
		}

		$this->initDB();
	}

	public function setUserID($userID)
	{
		$this->userID = $userID;
	}

	private function initDB()
	{
		$file = $this->workDir . 'db.sqlite';

		$new = ! file_exists($file);

		$this->pdo = new \PDO('sqlite:' . $file);
		$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

		if ($new) {
			if (!$sql = file_get_contents($sqlFile = $this->workDir . 'db.sql')) {
				throw new Exception("Could not load $sqlFile");
			}

			$statements = explode(';', $sql);

			foreach ($statements as $statement) {
				$this->pdo->prepare($statement)->execute();
			}
		}
	}

	public function processRequest($data)
	{
		if (!$data) {
			throw new Exception('INVALID_REQUEST');
		}

		@list($command, $args) = explode(' ', $data, 2);

		$command = strtolower($command);

		switch ($command) {
			case 'ping':
				return "PONG\n";

			case 'login':
				return $this->commandLogin($args);

			case 'getfilelist':
				return $this->commandGetfilelist($args);
			
			case 'setfile':
				return $this->commandGetfilelist($args);
		}

		throw new Exception('INVALID_REQUEST');
	}

	/* req: LOGIN user password
	 * res: OK filelistmtime
	 */
	protected function commandLogin($args)
	{
		@list($user, $pass) = explode(' ', $args, 2);
		
		$st = $this->pdo->prepare('
			SELECT id, filelist_mtime FROM users
				WHERE name = :name
				AND password_hash = :password_hash
		');
		
		$st->execute(array(
			':name' => $user
			,':password_hash' => md5($pass)
		));

		if (!$ret = $st->fetch(\PDO::FETCH_ASSOC)) {
			throw new Exception('ACCESS_DENIED');
		}

		if (empty($ret['id'])) {
			throw new Exception('INTERNAL_ERROR');
		}

		$this->userID = $ret['id'];

		return "OK " . $ret['filelist_mtime'];
	}

	/* req: GETFILELIST
	 * res: OK
	 * <fileliste>
	 * EOF
	 */
	protected function commandGetfilelist($args)
	{
		$this->checkLogin();
		
		$st = $this->pdo->prepare('
			SELECT filename, mtime, size FROM filelist
				WHERE user_id = :user_id
		');
		
		$st->execute(array(
			'user_id' => $this->userID
		));

		$ret = $st->fetchAll(\PDO::FETCH_ASSOC);

		$filelist = var_export($ret, true); // FIXME

		return 'OK ' . $filelist;
	}

	/* req: SETFILE <hash> <size> <name>
	 * res: OK || ALREADY_EXISTS || NEED_CHUNKHASHES
	 * bei NEED_CHUNKHASHES muss noch liste der chunk_hashes geschickt werden
	 */
	protected function commandSetfile($args)
	{
		@list($hash, $size, $name) = explode(' ', $args, 3);
		
//		$st = $this->pdo->prepare('
//			SELECT id FROM files
//				WHERE filename = :filename
//		')->execute(array(
//			':name' => $user
//			,':password_hash' => md5($pass)
//		));
//		
//		if (!$ret = $st->fetch(\PDO::FETCH_ASSOC)) {
//			throw new Exception('ACCESS_DENIED');
//		}
	}

	protected function checkLogin()
	{
		if (!$this->userID) {
			throw new Exception('LOGIN_REQUIRED');
		}
	}

	protected function getTempFile()
	{
		if (!$file = tempnam($this->tempDir, 'tmp')) {
			throw new Exception('INTERNAL_ERROR');
		}

		return $file;
	}

	public function getFilelist()
	{
		$this->checkLogin();
		
		$st = $this->pdo->prepare('
			SELECT hash, size, mtime, filename FROM files
				WHERE user_id = :user_id
		');
		
		$st->execute(array(
			'user_id' => $this->userID
		));

		$ret = $st->fetchAll(\PDO::FETCH_ASSOC);

		$result = '';

		foreach ($ret as $record) {
			$result .= $record['hash'] .' '. $record['size'] .' '. $record['mtime'] .' ' . $record['filename'] . "\n";
		}

		return $result;
	}

}

