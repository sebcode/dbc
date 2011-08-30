<?php

require_once('DB.class.php');

class Server
{
	protected $userID = 0;
	protected $pdo = 0;

	public function __construct()
	{
		$this->pdo = DB::getInstance()->getPDO();
	}
	
	public function setUserID($userID)
	{
		$this->userID = $userID;
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
		
	}

	/* c: PUTCHUNK
	 * s: NO
	 *
	 * c: PUTCHUNK
	 * s: OK <filehash> <chunkhash>
	 *
	 * c: PUTCHUNK <filehash> <chunkhash>
	 * s: OK
	 * c: DATA <len>
	 * c: <data>
	 * s: OK
	 */
	protected function commandPutchunk($args)
	{
		/*

		if (!$args) {
			$ret = findNeededChunk()

			if (!$ret) {
				throw new Exception('NO')
			}

			list($fileHash, $chunkHash) = findNeededChunk()

			return "OK $filehash $chunkhash\n"
		}

		@list($fileHash, $chunkHash) = explode(' ', $args, 2);

		if (!isNeededChunk($fileHash, $chunkHash)) {
			throw new Exception('NO')
		}

		lockChunk($fileHash, $chunkHash)

		echo "OK\n";

		$c = fread(STDIN, 8192)
		@list($resp, $len) = explode(' ', $args, 3);

		if (!$resp != 'DATA') {
			throw new Exception('EXPECTED_DATA')
		}

		if ($len < 0 || $len > (1024 * 1024)) {
			throw new Exception('INVALID_CHUNK_LEN')
		}

		$tmpFile = getTmpFile()
		$f = fopen($tmpFile, 'w+')

		$bytesReceived = 0;

		do {
			$data = fread(STDIN, $len);
			$bytesReceived += strlen($data);
			fwrite($f, $data);
		} while ($bytesReceived < $len);

		writeChunk($fileHash, $chunkHash, $tmpFile)

		unlink($tmpFile)

		unlockChunk($fileHash, $chunkHash)

		*/

	}

	protected function checkLogin()
	{
		if (!$this->userID) {
			throw new Exception('LOGIN_REQUIRED');
		}
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

	public function fileExistsByName($name)
	{
		$st = $this->pdo->prepare('
			SELECT id FROM files
				WHERE filename = :filename
				AND user_id = :user_id
		');
		
		$st->execute(array(
			':filename' => $name
			,':user_id' => $this->userID
		));
		
		return (bool) $st->fetch(\PDO::FETCH_ASSOC);
	}

	public function fileExistsByHash($hash)
	{
		$st = $this->pdo->prepare('
			SELECT id FROM files
				WHERE hash = :hash
				AND user_id = :user_id
		');
		
		$st->execute(array(
			':hash' => $hash
			,':user_id' => $this->userID
		));
		
		return (bool) $st->fetch(\PDO::FETCH_ASSOC);
	}

	public function setFile($hash, $name, $size, $mtime, $chunks = false)
	{
	/*
		$fileID = getFileID($hash)

		if (!$fileID) {
			if (!$chunks) {
				throw new Exception('NEED_CHUNKS');
			} else {
				$fileID = setChunks($hash, $size, $chunks)
			}
		}

		$userFileID = getUserFileID($name)

		if ($userFileID) {
			deleteUserFile($userFileID)
		}

		$userFileID = setUserFile($this->userID, $name, $mtime, $fileID)
		
	*/

		return false;
	}

}

