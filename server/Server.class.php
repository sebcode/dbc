<?php

require_once('../client/FileList.class.php');

class Server
{
	protected $baseDir;
	protected $params = false;
	protected $userDir = false;
	protected $dataDir = false;

	public function __construct($baseDir)
	{
		$this->baseDir = rtrim($baseDir, '/') . '/';
	}

	public function handleRequest()
	{
		$this->params = $_REQUEST;

		if (empty($this->params['cmd'])) {
			echo "SERVER_OK\n";
			return true;
		}

		$this->checkAuth();

		if (empty($this->params['cmd'])) {
			throw new Exception('INVALID_REQUEST');
		}

		switch ($this->params['cmd']) {
			case 'getfilelist': return $this->cmdGetfilelist();
			case 'uploadfile': return $this->cmdUploadfile();
			case 'downloadfile': return $this->cmdDownloadfile();
			case 'deletefile': return $this->cmdDeletefile();
			case 'idle': return $this->cmdIdle();
			case 'ping': return $this->cmdPing();
		}

		throw new Exception('INVALID_REQUEST');
	}

	protected function cmdUploadfile()
	{
		// TODO zieldatei locken

		if (!isset($this->params['filehash'])
			|| !isset($this->params['filesize'])
			|| !isset($this->params['filename'])) {

			throw new Exception('INVALID_REQUEST');
		}

		$expectHash = $this->params['filehash'];
		$expectSize = $this->params['filesize'];
		$name = $this->params['filename'];

		if (!isset($_FILES['file']['tmp_name'])) {
			throw new Exception('INVALID_REQUEST');
		}

		$tmpFile = $_FILES['file']['tmp_name'];

		if (!is_uploaded_file($tmpFile)) {
			throw new Exception('INVALID_REQUEST');
		}

		$hash = md5_file($tmpFile);
		$size = filesize($tmpFile);

		if ($hash != $expectHash) {
			throw new Exception('UNEXPECTED_HASH');
		}

		if ($size != $expectSize) {
			throw new Exception('UNEXPECTED_SIZE');
		}

		$filename = $this->dataDir . $name;
		
		if (!is_dir($d = dirname($filename)) && !mkdir($d)) {
			throw new Exception('INTERNAL_ERROR');
		}

		if (!move_uploaded_file($tmpFile, $filename)) {
			throw new Exception('INVALID_REQUEST');
		}

		echo "OK\n";
		return true;
	}

	protected function cmdDownloadfile()
	{
		if (!isset($this->params['filehash']) || !isset($this->params['filesize'])) {
			throw new Exception('INVALID_REQUEST');
		}

		$hash = $this->params['filehash'];
		$size = $this->params['filesize'];

		$filelist = $this->getFilelist();

		if (!$name = $filelist->getNameByHash($hash, $size)) {
			throw new Exception('FILE_NOT_FOUND');
		}

		if (!$f = fopen($this->dataDir . $name, 'r')) {
			throw new Exception('FILE_NOT_FOUND');
		}

		header('Content-Length: ' . $size);

		fpassthru($f);
		fclose($f);

		return true;
	}

	protected function cmdDeletefile()
	{
		if (!isset($this->params['filehash'])
			|| !isset($this->params['filesize'])) {

			throw new Exception('INVALID_REQUEST');
		}

		$hash = $this->params['filehash'];
		$size = $this->params['filesize'];

		$filelist = $this->getFilelist();

		if (!$name = $filelist->getNameByHash($hash, $size)) {
			throw new Exception('FILE_NOT_FOUND');
		}

		unlink($this->dataDir . $name);

		echo "OK\n";
		return true;
	}

	protected function cmdIdle()
	{
		ignore_user_abort(false);

		$f = $this->getFilelist();
		$originalHash = md5($f->toString());

		echo "IDLE\n";
		flush();
		ob_flush();

		while (true) {
			sleep(1);

			$f = $this->getFilelist();
			$hash = md5($f->toString());

			if ($hash != $originalHash) {
				break;
			}
			
			if (connection_status() != CONNECTION_NORMAL) {
				break;
			}
		}

		return true;
	}

	protected function cmdPing()
	{
		echo "PONG\n";

		return true;
	}

	protected function cmdGetfilelist()
	{
		$f = $this->getFilelist();

		echo $f->toString();

		return true;
	}

	protected function getFilelist()
	{
		return FileList::createFromDir($this->dataDir);
	}

	protected function checkAuth()
	{
		if (empty($this->params['user']) || empty($this->params['pass'])) {
			throw new Exception('AUTH_FAILED');
		}

		$user = $this->params['user'];
		$pass = $this->params['pass'];

		if (!preg_match('@[a-z]+@i', $user)) {
			throw new Exception('AUTH_FAILED');
		}

		if (file_exists($f = $this->baseDir . $user . '/pw')
			&& (($actualPwHash = file_get_contents($f)) !== false)
			&& (trim($actualPwHash) === $pass)) {

			$this->userDir = dirname($f);
			$this->dataDir = dirname($f) . '/data/';

			if (!is_dir($this->dataDir)) {
				if (!mkdir($this->dataDir)) {
					throw new Exception('INTERNAL_ERROR');
				}
			}

			return true;
		}

		throw new Exception('AUTH_FAILED');
	}

}

