<?php

namespace DbVcs;

class ChangeManager {
	protected $changesetPath;

	/** @var Processor */
	protected $processor;

	/** @var \PDO */
	protected $db;

	/** @var Output */
	protected $output;

	/**
	 * @param string $changesetPath Path to directory containing version numbers.
	 * @param \PDO $db
	 * @param \DbVcs\Output $log
	 */
	public function __construct($changesetPath, \PDO $db, Processor $processor, Output $log) {
		$this->changesetPath = $changesetPath;
		$this->db = $db;
		$this->processor = $processor;
		$this->output = $log;

		if (!is_dir($this->changesetPath)) {
			throw new \InvalidArgumentException('Invalid changeset path provided.');
		}
	}

	public function init() {
		$this->processor->createMeta(__DIR__ . '/../init.sql');
	}

	public function showState() {
		try {
			$version = $this->getCurrentVersion();
		} catch (\Exception $e) {
			$version = null;
		}

		$this->output->notice('Version: ' . $version ?: 'Uninitialized');

		return $this;
	}

	public function upgradeTo($targetVersion) {
		try {
			$currentVersion = $this->getCurrentVersion();
		} catch (\Exception $ex) {
			$this->init();
			$currentVersion = 0;
		}
		$lastVersion = 0;

		if ($targetVersion === null) {
			$targetVersion = $currentVersion;
			$this->output->notice('Checking for unapplied changes up to the current version [' . $currentVersion . ']');
		} else {
			$this->output->notice('Checking for unapplied changes between current version [' . $currentVersion . '] and requested target version [' . $targetVersion . ']');
		}

		$available = $this->getAvailableSets();
		$applied = $this->getAppliedSets();

		foreach ($available as $version => $changes) {
			if (version_compare($version, $targetVersion) > 0) {
				break;
			}

			$unapplied = array_diff($changes, $applied);

			if (!empty($unapplied)) {
				$this->output->notice('Found unapplied changes in v' . $version);
				$this->applyChanges($version, $unapplied);
			}

			$lastVersion = $version;
		}

		if (version_compare($currentVersion, $lastVersion) < 0) {
			$this->output->notice('Updating current version to [' . $lastVersion . ']');
			$this->processor->metaVersion('UPDATE _metaVersion SET currentVersion = ?', $lastVersion);
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
			$this->output->notice('Applying changeset: v' . $version . '/' . $file);
			$this->processor->applyFile($this->changesetPath . '/v' . $version . '/' . $file);
			$this->processor->metaChange('INSERT INTO _metaChange (name) VALUES (?)', $file);
		}
	}
}
