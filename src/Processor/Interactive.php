<?php

namespace DbVcs\Processor;

class Interactive extends Apply implements \DbVcs\Processor {
	/** @var \DbVcs\Output */
	protected $output;

	public function __construct(\PDO $db, \DbVcs\Output $output) {
		parent::__construct($db);
		$this->output = $output;
	}

	public function applyFile($file) {
		if ($this->checkInteractive('Apply changeset to database?')) {
			parent::applyFile($file);
		}
	}

	public function createMeta($file) {
		if ($this->checkInteractive('Create database meta tables for tracking?')) {
			parent::createMeta($file);
		}
	}

	public function upgradeMeta($sql, $description) {
		if ($this->checkInteractive('Upgrade meta tracking with: ' . $description . '?')) {
			parent::upgradeMeta($sql, $description);
		}
	}

	public function metaChange($sql, $changeset) {
		if ($this->checkInteractive('Mark changeset as applied?')) {
			parent::metaChange($sql, $changeset);
		}
	}

	public function metaVersion($sql, $lastVersion) {
		if ($this->checkInteractive('Upgrade current version?')) {
			parent::metaVersion($sql, $lastVersion);
		}
	}

	protected function checkInteractive($prompt) {
		return in_array($this->output->prompt("\t" . $prompt . ' [yN] '), ['y', 'Y'], true);
	}
}
