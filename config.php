<?php

$mysql_user = "backuper";
$mysql_password = "";
$user_path = "/var/www/";
$date = date("Y-m-d");
$backupName = 'backup-'.$date;
$backupsStorage = '/var/backups/local/';
$ftpUser = '';
$ftpPassword = '';
$ftpHost = 'ftp.selcdn.ru';
$ftpPath = 'test';
$days = 14;
$archiver = 'gzip';
// $archiver = 'pigz';
$exclude_file = 'exclude.txt';
$max_archive_size = '4500M';