<?php

class FileList
{
	private $entries = array();
	
	public static function createFromData($data)
	{
		$f = new FileList;
		$f->parseList($data);
	}

	public static function createFromDir($dir)
	{
		$f = new FileList;
	
		$it = new RecursiveDirectoryIterator($dir);

		$files = array();

		foreach (new RecursiveIteratorIterator($it) as $file) {
			$relName = substr($file, strlen($dir));

			$f->entries[$relName] = array(
				'hash' => md5_file($file)
				,'mtime' => filemtime($file)
				,'size' => filesize($file)
			);
		}

		ksort($f->entries);

		return $f;
	}

	public static function createFromFile($file)
	{
		$f = new FileList;
		$f->fromFile($file);

		return $f;
	}

	public function fromFile($file)
	{
		$this->entries = array();

		if (!$c = file_get_contents($file)) {
			throw new Exception("Read failed: $file");
		}

		$this->parseList($c);

		return true;
	}

	public function toFile($file)
	{
		if (!file_put_contents($file, $this->toString())) {
			throw new Exception("Write failed: $file");
		}

		return true;
	}

	public function getEntries()
	{
		return $this->entries;
	}
	
	protected function parseList($data)
	{
		$lines = explode("\n", $data);

		foreach ($lines as $line) {
			if (empty($line)) {
				continue;
			}
			
			@list($hash, $mtime, $size, $name) = explode(' ', $line, 4);

			$this->entries[$name] = array(
				'hash' => $hash
				,'mtime' => $mtime
				,'size' => $size
			);
		}
	}

	public function diff(FileList $newlist)
	{
		$entries = $this->getEntries();
		$newentries = $newlist->getEntries();

		$diff = array();

		foreach ($entries as $name => $meta) {
			if (!isset($newentries[$name])) {
				$diff[$name] = 'D';
				continue;
			}
			
			if (isset($newentries[$name]) && $newentries[$name]['hash'] !== $entries[$name]['hash']) {
				$diff[$name] = 'M';
				continue;
			}
		}
		
		foreach ($newentries as $name => $meta) {
			if (!isset($entries[$name])) {
				$diff[$name] = 'N';
				continue;
			}
		}

		ksort($diff);

		return $diff;
	}

	public function toString()
	{
		$result = '';

		foreach ($this->entries as $name => $meta) {
			$result .= $meta['hash'] . ' ' . $meta['mtime'] . ' ' . $meta['size'] . ' ' . $name . "\n";
		}

		return $result;
	}

	public function getEntry($name)
	{
		if (isset($this->entries[$name])) {
			return $this->entries[$name];
		}

		return false;
	}

	public function getNameByHash($hash, $size)
	{
		foreach ($this->entries as $name => $meta) {
			if ($meta['hash'] === $hash && $meta['size'] === $size) {
				return $name;
			}
		}

		return false;
	}

	public function setEntry($name, $hash, $size, $mtime)
	{
		$this->entries[$name] = array(
			'hash' => $hash
			,'mtime' => $mtime
			,'size' => $size
		);

		ksort($f->entries);

		return true;
	}

	public function deleteEntry($name)
	{
		if (!isset($this->entries[$name])) {
			return false;
		}

		unset($this->entries[$name]);
		return true;
	}

}

