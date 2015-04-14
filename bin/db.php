#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

$changeManager = new \DbVcs\ChangeManager(
	new \PDO('mysql:host=localhost;dbname=test', 'root', null, [
		\PDO::ATTR_EMULATE_PREPARES => false,
		\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
	]),
	new \DbVcs\Log()
);

$opts = getopt('nit:');
if (isset($opts['s'])) {
	$changeManager->showState();
} elseif (isset($opts['t'])) {
	if (isset($opts['n'])) {
		$changeManager->setNoOperation();
	} elseif (isset($opts['i'])) {
		$changeManager->setInteractive();
	}
	$targetVersion = ($opts['t'] !== false) ? $opts['t'] : null;
	$changeManager->upgradeTo($targetVersion);
} else {
		echo <<<USAGE
{$argv[0]} [-s] [-n | -i] [-t [version]]
  -n           Run as normal but perform no modifying operations against the database.
  -i           Provide interactive confirmation prompts before applying changes.
  -t version   Specify new version to target upgrade to
  -s           Display the current version of the database


USAGE;
	$changeManager->showState();
}
