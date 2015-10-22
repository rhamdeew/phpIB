<?php


$mysqlUser = "backuper";
$mysqlPassword = "";

$userPath = "/var/www/";
$backupName = 'backup-'.date("Y-m-d");
$backupsStorage = '/var/backups/local/';
$backupsArchiveTempDir = '/var/backups/archive/';
$days = 14;
$archiver = 'gzip'; //|pigz
$excludeFile = 'exclude.txt';
$maxArchiveSize = '4500M';
$mailAddress = "mail@example.ru";
$mailSubject = "site backup";

$tasks = array(
	"FirstFTP" => array(
		"type" => "ftp",
		"user" => "username",
		"password" => "password",
		"host" => "examplehost.ru",
		"path" => "test",
		),
	"SecondFTP" => array(
		"type" => "ftp",
		"user" => "username",
		"password" => "password",
		"host" => "examplehost.ru",
		"path" => "test",
		),
	"AmazonS3" => array(
		"type" => "s3",
		"path" => "test",
		),
	"Rsync" => array(
		"type" => "rsync",
		"hostname" => "examplehost",
		"args" => "-av --delete",
		"localpath" => "/var/backups/local/",
		"remotepath" => "/var/backups/extbackup/",
		),
	"Local" => array(
		"type" => "local",
		"localpath" => "/var/backups/tmp/",
		"owner" => "backuper",
		),
	);
