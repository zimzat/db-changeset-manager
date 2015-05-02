<?php

namespace DbVcs\Processor;

class Apply implements \DbVcs\Processor {
	/** @var \PDO */
	protected $db;

	public function __construct(\PDO $db) {
		$this->db = $db;
	}

	public function applyFile($file) {
		$this->db->exec(file_get_contents($file));
	}

	public function createMeta($file) {
		$this->db->exec(file_get_contents($file));
	}

	public function metaChange($sql, $changeset) {
		$this->db->prepare($sql)->execute([$changeset]);
	}

	public function metaVersion($sql, $lastVersion) {
		$this->db->prepare($sql)->execute([$lastVersion]);
	}
}
