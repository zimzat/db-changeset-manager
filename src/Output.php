<?php

namespace DbVcs;

class Output {
	public function notice($message) {
		echo $message, "\n";
	}

	public function prompt($message) {
		return readline($message);
	}
}
