# Db Changeset Manager

Automate the process of applying SQL files (changesets) to databases of different environments or users.

Create versioned directories where developers can drop SQL files to be applied to other environments through the release
process and back to other developers to ensure that everyone stays synced with database changes. This project doesn't try
to force specific syntax, verbose declarations, or provide automated conflict resolution or detection; just a simple
process of applying changes between environments.

A typical changeset directory would look something like this:
```
changesets
├── v1.0
│   ├── 20150102_create-user.sql
│   └── 20150103_track-user-actions.sql
├── v1.1
│   └── 20150103_track-user-action-date.sql
├── v1.3
│   ├── 2015-01-15_create-user-profile.sql
│   ├── 2015-01-16_blogs.sql
│   └── 2015-01-17_user-roles.sql
├── v1.3.1
│   └── 2015-01-20_add-user-roles.sql
└── v1.5
    ├── 2015-01-15_long-awaited-feature.sql
    ├── 2015-02-01_index-blogs.sql
    ├── 2015-02-03_add-reporting.sql
    └── 2015-02-10_blog-tags.sql
```

Changes are applied to the database in order of version number and then sorted numerically by file name, thus upgrading from 1.0 to 1.3 would apply 'track-user-actions' before 'track-user-action-date' even though they're the same date prefix.

Directories must be prefixed with 'v' but otherwise follows the standard set out by [version_compare()](http://php.net/version_compare).

Files must be suffixed with `.sql` and must be uniquely named between all versions but otherwise can be named anything. A natural sort (2 before 10) is done to the list of files before being applied to the database.

Restrictions
====

* No SELECT statements.
* No CREATE DATABASE, DROP DATABASE, or USE DATABASE statements.

Requirements
====

* PDO
* MySQL

Install
====
The supported method to install the utility is through Composer.

```
composer install zimzat/db-changeset-manager
```

Command Line Usage
=====

Once installed a php shell file can be used to apply changes from the command line.

```
ln -s ./vendor/bin/dbChangeManager.php .
./dbChangeManager.php
```

The command line tool expects a configuration file to be defined specifying the access name and permissions.

```
host = localhost
dbname = test
username = root
; password = secret
changesetPath = changesets
```

The available commands, which can also be seen by running the command without any options, are:
```
./bin/dbChangeManager.php -h
./bin/dbChangeManager.php -s [-c db.ini]
./bin/dbChangeManager.php -t <version> [-a | -n | -i | -o <file>] [-c db.ini]

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

```

PHP Usage
=====

The utility can also be invoked directly from a PHP script by instantiating the \DbVcs\ChangeManager object with its dependencies.

```
$changeManager = new \DbVcs\ChangeManager(
	'changesets',
	new \PDO(
		'mysql:host=localhost;dbname=test',
		'root',
		null, [
			\PDO::ATTR_EMULATE_PREPARES => false,
			\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
		]
	),
	new \DbVcs\Processor\NoOp(),
	new \DbVcs\Output()
);
```

Note: The \DbVcs\Output class should be extended to send results where and how desired (log file, browser, database, etc).
