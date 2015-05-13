<?php

namespace DbVcs\Processor;

class NoOp implements \DbVcs\Processor {
	public function applyFile($file) { }
	public function createMeta($file) { }
	public function upgradeMeta($sql, $description) { }
	public function metaChange($sql, $changeset) { }
	public function metaVersion($sql, $lastVersion) { }
}
