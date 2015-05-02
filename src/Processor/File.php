<?php

namespace DbVcs\Processor;

class File implements \DbVcs\Processor {

	public function __construct($output) {
		$this->output = $output;
	}

	public function applyFile($file) {
		$this->fwrite('# Applying changeset file: ' . $file . "\n" . file_get_contents($file) . "\n;");
	}

	public function createMeta($file) {
		$this->fwrite('# Creating meta tracking tables' . "\n" . file_get_contents($file));
	}

	public function metaChange($sql, $changeset) {
		$this->fwrite('# Adding changeset to meta' . "\n" . str_replace('?', "'" . addslashes($changeset) . "'", $sql));
	}

	public function metaVersion($sql, $lastVersion) {
		$this->fwrite('# Updating meta version' . "\n" . str_replace('?', "'" . addslashes($lastVersion) . "'", $sql));
	}

	protected function fwrite($content) {
		file_put_contents($this->output, "\n" . $content . "\n", FILE_APPEND);
	}
}
