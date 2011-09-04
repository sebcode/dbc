<?php

require_once('../client/FileList.class.php');

class Server
{
	protected $baseDir = '/tmp/server/';
	protected $params = false;
	protected $userDir = false;
	protected $dataDir = false;
	
	public function handleRequest()
	{
		$this->params = $_REQUEST;

		$this->checkAuth();

		if (empty($this->params['cmd'])) {
			throw new Exception('INVALID_REQUEST');
		}

		switch ($this->params['cmd']) {
			case 'getfilelist': return $this->cmdGetfilelist();
			case 'uploadfile': return $this->cmdUploadfile();
		}

		throw new Exception('INVALID_REQUEST');
	}

	protected function cmdUploadfile()
	{
		// TODO zieldatei locken

		if (empty($this->params['filehash']) || empty($this->params['filesize'])) {
			throw new Exception('INVALID_REQUEST');
		}

		$expectHash = $this->params['filehash'];
		$expectSize = $this->params['filesize'];

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

		$filename = $this->dataDir . $_FILES['file']['name'];
		
		if (!is_dir($d = dirname($filename)) && !mkdir($d)) {
			throw new Exception('INTERNAL_ERROR');
		}

		if (!move_uploaded_file($tmpFile, $filename)) {
			throw new Exception('INVALID_REQUEST');
		}

		echo "OK\n";
		return true;
	}

	protected function cmdGetfilelist()
	{
		$f = FileList::createFromDir($this->dataDir);

		echo $f->toString();

		return true;
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

