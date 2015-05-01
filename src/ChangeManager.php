<?php

namespace DbVcs;

class ChangeManager {
	protected $noOp = false;
	protected $interactive = false;

	protected $changesetPath;

	/** @var \PDO */
	protected $db;

	/** @var Output */
	protected $log;

	/**
	 * @param string $changesetPath Path to directory containing version numbers.
	 * @param \PDO $db
	 * @param \DbVcs\Output $log
	 */
	public function __construct($changesetPath, \PDO $db, Output $log) {
		$this->changesetPath = $changesetPath;
		$this->db = $db;
		$this->log = $log;

		if (!is_dir($this->changesetPath)) {
			throw new \InvalidArgumentException('Invalid changeset path provided.');
		}
	}

	public function setNoOperation($noOp = true) {
		$this->log->notice('Write Operations ' . ($noOp ? 'Disabled' : 'Enabled'));
		$this->noOp = (bool)$noOp;
	}

	public function setInteractive($interactive = true) {
		$this->log->notice('Interactive Mode ' . ($interactive ? 'Enabled' : 'Disabled'));
		$this->interactive = $interactive;
	}

	public function checkInteractive($prompt) {
		if ($this->interactive) {
			return in_array(readline("\t" . $prompt . ' [yN] '), ['y', 'Y'], true);
		}
		return true;
	}

	public function init() {
		$this->db->query(file_get_contents(__DIR__ . '/../init.sql'));
	}

	public function showState() {
		try {
			$version = $this->getCurrentVersion();
		} catch (\Exception $e) {
			$version = null;
		}

		$this->log->notice('Version: ' . $version ?: 'Uninitialized');

		return $this;
	}

	public function upgradeTo($targetVersion) {
		try {
			$currentVersion = $this->getCurrentVersion();
		} catch (\Exception $ex) {
			$this->noOp || !$this->checkInteractive('Create database meta tables for tracking?') || $this->init();
		}
		$lastVersion = 0;

		if ($targetVersion === null) {
			$targetVersion = $currentVersion;
			$this->log->notice('Checking for unapplied changes up to the current version [' . $currentVersion . ']');
		} else {
			$this->log->notice('Checking for unapplied changes between current version [' . $currentVersion . '] and requested target version [' . $targetVersion . ']');
		}

		$available = $this->getAvailableSets();
		$applied = $this->getAppliedSets();

		foreach ($available as $version => $changes) {
			if (version_compare($version, $targetVersion) > 0) {
				break;
			}

			$unapplied = array_diff($changes, $applied);

			if (!empty($unapplied)) {
				$this->log->notice('Found unapplied changes in v' . $version);
				$this->applyChanges($version, $unapplied);
			}

			$lastVersion = $version;
		}

		if (version_compare($currentVersion, $lastVersion) < 0) {
			$this->log->notice('Updating current version to [' . $lastVersion . ']');
			$this->noOp || !$this->checkInteractive('Upgrade current version?') || $this->db->prepare('UPDATE _metaVersion SET currentVersion = ?')->execute([$lastVersion]);
		}

		return $this;
	}

	protected function getCurrentVersion() {
		return $this->db->query('SELECT * FROM _metaVersion LIMIT 1')->fetchColumn();
	}

	protected function getAppliedSets() {
		return $this->db->query('SELECT name FROM _metaChange')->fetchAll(\PDO::FETCH_COLUMN);
	}

	protected function getAvailableSets() {
		$pathPrefixLength = strlen($this->changesetPath);

		$changes = [];
		foreach (glob($this->changesetPath . '/*/*.sql') as $path) {
			list($tmp, $version, $file) = explode(DIRECTORY_SEPARATOR, substr($path, $pathPrefixLength));
			$changes[ltrim($version, 'v')][] = $file;
		}

		foreach ($changes as &$files) {
			asort($files, SORT_NATURAL);
		}
		ksort($changes, SORT_NATURAL);

		return $changes;
	}

	protected function applyChanges($version, $changes) {
		foreach ($changes as $file) {
			$this->log->notice('Applying changeset: v' . $version . '/' . $file);
			if (!$this->noOp) {
				!$this->checkInteractive('Apply changeset to database?') || $this->db->exec(file_get_contents($this->changesetPath . '/v' . $version . '/' . $file));
				!$this->checkInteractive('Mark changeset as applied?') || $this->db->prepare('INSERT INTO _metaChange (name) VALUES (?)')->execute([$file]);
			}
		}
	}
}
