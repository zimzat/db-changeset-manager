#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (!file_exists('db.ini')) {
	echo 'Configuration file not found! Please create db.ini', "\n";
	exit(1);
}

$config = parse_ini_file('db.ini');
foreach (['host', 'dbname', 'username', 'changesetPath'] as $param) {
	if (empty($config[$param])) {
		echo 'Required configuration paramter not set: ', $param, "\n";
	} elseif ($param === 'changesetPath' && !is_dir(realpath($config['changesetPath']))) {
		echo 'Changeset path invalid';
	}
}
if ($hasConfigFailure) {
	exit(1);
}

$changeManager = new \DbVcs\ChangeManager(
	realpath($config['changesetPath']),
	new \PDO(
		'mysql:host=' . $config['host'] . ';dbname=' . $config['dbname'],
		$config['username'],
		isset($config['password']) ? $config['password'] : null, [
			\PDO::ATTR_EMULATE_PREPARES => false,
			\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
		]
	),
	new \DbVcs\Output()
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
