#!/usr/bin/env php
<?php

foreach (['/../../autoload.php', '/../vendor/autoload.php', '/vendor/autoload.php'] as $file) {
    if (file_exists(__DIR__ . '/..' . $file)) {
		require __DIR__ . '/..' . $file;
        break;
    }
}

$opts = getopt('nit:c:');
if (!isset($opts['c'])) {
	$opts['c'] = 'db.ini';
}

if (!file_exists($opts['c'])) {
	echo 'Configuration file not found! Please create db.ini', "\n";
	exit(1);
}

$hasConfigFailure = false;
$config = parse_ini_file($opts['c']);
foreach (['host', 'dbname', 'username', 'changesetPath'] as $param) {
	if (empty($config[$param])) {
		echo 'Required configuration paramter not set: ', $param, "\n";
		$hasConfigFailure = true;
	} elseif ($param === 'changesetPath' && !is_dir(realpath($config['changesetPath']))) {
		echo 'Changeset path invalid';
		$hasConfigFailure = true;
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

// Force interactive mode on always
if (!empty($config['interactive'])) {
	$opts['i'] = true;
}

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
{$argv[0]} [-s] [-n | -i] [-t [version]] [-c db.ini]
  -n           Run as normal but perform no modifying operations against the database.
  -i           Provide interactive confirmation prompts before applying changes.
  -t version   Specify new version to target upgrade to
  -s           Display the current version of the database
  -c file      Specify the db config file to use


USAGE;
	$changeManager->showState();
}
