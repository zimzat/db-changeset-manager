# Db Changeset Manager

Automate the process of applying SQL files (changesets) to databases of different environments or users.

Create versioned directories where developers can drop SQL files to be applied to other environments through the release
process and back to other developers to ensure that everyone stays synced with database changes.

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

Files must be suffixed with `.sql` but otherwise can be named anything. A natural sort (1 before 10) is done to the list of files before being applied to the database.

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
changesetPath = changesets
```

The available commands, which can also be seen by running the command without any options, are:
```
php ./bin/db.php [-s] [-n | -i] [-t [version]] [-c db.ini]
  -n           Run as normal but perform no modifying operations against the database.
  -i           Provide interactive confirmation prompts before applying changes.
  -t version   Specify new version to target upgrade to
  -s           Display the current version of the database
  -c file      Specify the db config file to use
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
	new \DbVcs\Output()
);
```

Note: The \DbVcs\Output class should be extended to send results where desired (log file, browser, database, etc).

Note: Interactive mode should not be enabled unless the host script is also run from the command line.
