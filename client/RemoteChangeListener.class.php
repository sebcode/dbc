<?php

class RemoteChangeListener
{
	protected $serverUrl;
	protected $user;
	protected $pwHash;
	protected $parentPid;
	protected $lastChange = 0;
	
	public function __construct($serverUrl, $user, $pwHash)
	{
		$this->serverUrl = $serverUrl;
		$this->user = $user;
		$this->pwHash = $pwHash;
		$this->parentPid = posix_getpid();
	}

	public function start()
	{
		$pid = pcntl_fork();

		if ($pid == -1) {
			throw new Exception('Could not fork process');
		} else if ($pid) {
			/* parent process */
			return true;
		} else {
			/* child process */
			$this->childLoop();
		}
	}

	public function getLastChange()
	{
		return $this->lastChange;
	}

	public function setLastChange()
	{
		$this->lastChange = time();
	}

	protected function childLoop()
	{
		while (true) {
			$this->sendIdle();

			sleep(5);
		}

		exit();
	}

	protected function sendIdle()
	{
		$ch = curl_init($this->serverUrl . '?user=' . $this->user . '&pass=' . $this->pwHash . '&cmd=idle');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 300);
		curl_exec($ch);

		posix_kill($this->parentPid, SIGUSR1);
	}

}

