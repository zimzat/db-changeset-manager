#!/usr/bin/env php
<?php

namespace DbVcs;

function showUsage() {
	return <<<USAGE
{$GLOBALS['argv'][0]} -h
{$GLOBALS['argv'][0]} -s [-c db.ini]
{$GLOBALS['argv'][0]} -t <version> [-a | -n | -i | -o <file>] [-c db.ini]

Specify an action:
  -h           Display this help message.
  -s           Display the current version of the database
  -t version   Specify new version to target upgrade to

Set a run mode:
  -a           Apply: Execute all changesets and tracking against the database immediately
  -n           No Op: Run as normal but perform no modifying operations against the database.
  -i           Interactive: Provide interactive confirmation prompts before applying changes.
  -o file      File: Output all SQL commands to a file which can be run separately.

Other options:
  -c file      Specify the db config file to use, defaults to db.ini


USAGE;
}

foreach (['/../../autoload.php', '/../vendor/autoload.php', '/vendor/autoload.php'] as $file) {
    if (file_exists(__DIR__ . '/..' . $file)) {
		require __DIR__ . '/..' . $file;
        break;
    }
}

$opts = getopt('hsanio:t:c:');
if (isset($opts['h']) || empty($opts)) {
	echo \DbVcs\showUsage();
	exit;
}

if (!isset($opts['c'])) {
	$opts['c'] = 'db.ini';
}

if (!file_exists($opts['c'])) {
	echo \DbVcs\showUsage();
	echo 'ERROR: Configuration file not found! Please check that [', $opts['c'], '] exists or create it', "\n";
	exit(1);
}

$errors = [];
$config = parse_ini_file($opts['c']);
foreach (['host', 'dbname', 'username', 'changesetPath'] as $param) {
	if (empty($config[$param])) {
		$errors[] = 'Required configuration paramter not set: ' . $param;
		$hasConfigFailure = true;
	} elseif ($param === 'changesetPath' && !is_dir(realpath($config['changesetPath']))) {
		$errors[] = 'Changeset path invalid';
		$hasConfigFailure = true;
	}
}
if ($errors) {
	echo \DbVcs\showUsage();
	echo "ERROR: ", implode("\nERROR: ", $errors);
	exit(1);
}

$db = new \PDO(
	'mysql:host=' . $config['host'] . ';dbname=' . $config['dbname'],
	$config['username'],
	isset($config['password']) ? $config['password'] : null, [
		\PDO::ATTR_EMULATE_PREPARES => false,
		\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
	]
);
$output = new \DbVcs\Output();

if (isset($opts['n']) || isset($opts['s'])) {
	$processor = new \DbVcs\Processor\NoOp();
} elseif (isset($opts['i'])) {
	$processor = new \DbVcs\Processor\Interactive($db, $output);
} elseif (isset($opts['o'])) {
	$processor = new \DbVcs\Processor\File($opts['o']);
} elseif (isset($opts['a'])) {
	$processor = new \DbVcs\Processor\Apply($db);
} else {
	echo \DbVcs\showUsage();
	echo 'ERROR: No processor specified', "\n";
	exit(1);
}

$changeManager = new \DbVcs\ChangeManager(realpath($config['changesetPath']), $db, $processor, $output);
if (array_key_exists('versionPrefix', $config)) {
	$changeManager->setVersionPrefix($config['versionPrefix']);
}

if (isset($opts['s'])) {
	$changeManager->showState();
} elseif (isset($opts['t'])) {
	$targetVersion = ($opts['t'] !== false) ? $opts['t'] : null;
	$changeManager->upgradeTo($targetVersion);
} else {
	echo \DbVcs\showUsage();
	echo 'ERROR: No action specified', "\n";
}
