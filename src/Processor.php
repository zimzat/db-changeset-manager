<?php

namespace DbVcs;

interface Processor {
	public function createMeta($file);

	public function metaVersion($sql, $lastVersion);

	public function metaChange($sql, $changeset);

	public function applyFile($file);
}
