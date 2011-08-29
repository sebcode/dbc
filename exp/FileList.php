<?php

class FileList
{
	private $entries;
	
	public function __construct($data)
	{
		$this->parseList($data);
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
			
			@list($hash, $size, $mtime, $name) = explode(' ', $line, 4);

			$this->entries[$name] = array(
				'hash' => $hash
				,'size' => $size
				,'mtime' => $mtime
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
				$diff[$name] = 'N';
				continue;
			}
		}

		return $diff;
	}

}
