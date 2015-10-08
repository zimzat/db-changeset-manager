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

	protected $versionPrefix = 'v';

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

	public function setVersionPrefix($versionPrefix) {
		$this->versionPrefix = $versionPrefix;
		return $this;
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

		try {
			if (!$this->lockDatabase()) {
				return $this;
			}
		} catch (\Exception $ex) {
			// If the lock fails with an exception then the database /probably/ needs the lock meta upgrade;
			$this->upgradeMeta();
			if (!$this->lockDatabase()) {
				return $this;
			}
		}
		$this->upgradeMeta();
		$this->doUpgrades($targetVersion, $currentVersion);
		$this->unlockDatabase();

		return $this;
	}

	protected function doUpgrades($targetVersion, $currentVersion) {
		if ($targetVersion === null) {
			$targetVersion = $currentVersion;
			$this->output->notice('Checking for unapplied changes up to the current version [' . $currentVersion . ']');
		} else {
			$this->output->notice('Checking for unapplied changes between current version [' . $currentVersion . '] and requested target version [' . $targetVersion . ']');
		}

		$available = $this->getAvailableSets();
		$applied = $this->getAppliedSets();

		$lastVersion = 0;
		foreach ($available as $version => $changes) {
			if (version_compare($version, $targetVersion) > 0) {
				break;
			}

			$unapplied = array_diff($changes, $applied);

			if (!empty($unapplied)) {
				$this->output->notice('Found unapplied changes in ' . $this->versionPrefix . $version);
				$this->applyChanges($version, $unapplied);
			}

			$lastVersion = $version;
		}

		if (version_compare($currentVersion, $lastVersion) < 0) {
			$this->output->notice('Updating current version to [' . $lastVersion . ']');
			$this->processor->metaVersion('UPDATE _metaVersion SET currentVersion = ?', $lastVersion);
		}
	}

	protected function upgradeMeta() {
		$applied = $this->getAppliedSets();
		if (array_search('_metaVersion.isLocked', $applied) === false) {
			$this->processor->upgradeMeta('ALTER TABLE _metaVersion ADD COLUMN isLocked BOOLEAN NOT NULL DEFAULT FALSE', 'Add lock capability');
			$this->processor->metaChange('INSERT INTO _metaChange (name) VALUES (?)', '_metaVersion.isLocked');
		}
		if (array_search('_metaChange.nameLength', $applied) === false) {
			$this->processor->upgradeMeta('ALTER TABLE _metaChange CHANGE COLUMN name name VARCHAR(128) NOT NULL', 'Increase changeset file length maximum from 64 to 128');
			$this->processor->metaChange('INSERT INTO _metaChange (name) VALUES (?)', '_metaChange.nameLength');
		}
	}

	protected function lockDatabase() {
		$i = 0;
		while (!$this->db->query('UPDATE _metaVersion SET isLocked = 1 WHERE isLocked = 0')->rowCount()) {
			if ($i++ > 3) {
				if ($this->output->prompt('Database still locked; Force database unlock and retry? [yN] ') === 'y') {
					$this->unlockDatabase();
					continue;
				} else {
					$this->output->notice('Lock wait timeout: canceling upgrade.');
					return false;
				}
			}

			$this->output->notice('Database is already locked; waiting for external process to finish. [' . pow(2, $i) * 0.25 . 's]');
			usleep(250000 * pow(2, $i));
		}

		return true;
	}

	protected function unlockDatabase() {
		return (bool)$this->db->query('UPDATE _metaVersion SET isLocked = 0')->rowCount();
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
		foreach (glob($this->changesetPath . '/' . $this->versionPrefix . '*/*.sql') as $path) {
			list($tmp, $version, $file) = explode(DIRECTORY_SEPARATOR, substr($path, $pathPrefixLength));
			$changes[substr_replace($version, '', 0, strlen($this->versionPrefix))][] = $file;
		}

		foreach ($changes as &$files) {
			asort($files, SORT_NATURAL);
		}
		ksort($changes, SORT_NATURAL);

		return $changes;
	}

	protected function applyChanges($version, $changes) {
		foreach ($changes as $file) {
			$this->output->notice('Applying changeset: ' . $this->versionPrefix . $version . '/' . $file);
			$this->processor->applyFile($this->changesetPath . '/' . $this->versionPrefix . $version . '/' . $file);
			$this->processor->metaChange('INSERT INTO _metaChange (name) VALUES (?)', $file);
		}
	}
}
