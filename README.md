# Db Changeset Manager

Automate the process of applying SQL files (changesets) to databases of different environments or users.

```
php ./bin/db.php [-s] [-n | -i] [-t [version]]
  -n           Run as normal but perform no modifying operations against the database.
  -i           Provide interactive confirmation prompts before applying changes.
  -t version   Specify new version to target upgrade to
  -s           Display the current version of the database
```


Note: Options to set the database DSN/user/pass and changeset path outside of the repository are TBD.
