<?php

$mysql_user = "backuper";
$mysql_password = "";
$user_path = "/var/www/";
$date = date("Y-m-d");
$backupName = 'backup-'.$date;
$backupsStorage = '/var/backups/local/';
$backupsArchiveTempDir = '/var/backups/archive/';
$s3BackupFlag = true;
$s3BucketPath = '';
$ftpBackupFlag = true;
$ftpUser = '';
$ftpPassword = '';
$ftpHost = 'ftp.ru';
$ftpPath = 'test';
$days = 14;
$archiver = 'gzip';
// $archiver = 'pigz';
$exclude_file = 'exclude.txt';
$max_archive_size = '4500M';
$mailAddress = "mail@example.ru";
$mailSubject = "site backup";