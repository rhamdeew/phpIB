phpIB
=====

Simple incremental backup script written on php.
Works as a replacement for the standard backups functional ISPmanager.
The advantage of this script is to support incremental backups.

**REQUIREMENTS:**
- ISPmanger
- php-cli
- rsync
- curl

**USAGE:**

1. Edit config in head of the script phpIB.php


*Example:*

<pre>
$mysql_user = "backuper";
$mysql_password = "password";
$user_path = "/var/www/";
$date = date("Y-m-d");
$backupName = 'backup-'.$date;
$backupsStorage = '/var/backups/local/';
$ftpUser = 'login';
$ftpPassword = 'password';
$ftpHost = 'ftp.example.ru';
$ftpPath = 'test';
</pre>

2. Add in your crontab task

``
30 6 * * * /usr/bin/php /root/phpIB/phpIB.php
``

**ToDO:**
1. Add limit of daily backups

2. Add support to exclude files and directories

3. Add mail report with stats and status
