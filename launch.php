<?php

require "config.php";
require "phpIB.php";

$shortopts = "";
$longopts = array(
	"users::",
	"onlyupload::",
	"log::",
	"dryrun::",
	);

$options = getopt($shortopts,$longopts);

$onlyUpload = false;
if(isset($options['onlyupload'])) {
	$onlyUpload = true;
}

$log = true;
if(isset($options['log'])) {
	if($options['log']=='false') {
		$log = false;
	}
}

$dryRun = false;
if(isset($options['dryrun'])) {
	if($options['dryrun']=='true') {
		$dryRun = true;
	}
}

$ib = new phpIB($backupsStorage,$backupsArchiveTempDir);
$ib->log = $log;
$ib->onlyUpload = $onlyUpload;
$ib->dryRun = $dryRun;

if(isset($options['users'])) {
	$users = $options['users'];
	$users = explode(',',$users);
}
else {
	$users = $ib->getISPUsersData();
}

if(is_array($users)) {
	foreach($users as $user => $databases) {
		$ib->printDelimiter();
		$ib->toLog("Backup started for $user:\n");
		$now = time();

		//Если нужно не только залить, но и сделать свежий бэкап
		if(!$onlyUpload) {
			$ib->cleanOldBackups($user,$backupsStorage,$days);
			$ib->backupISPUser($user,$userPath,$databases,$backupsStorage,$backupsArchiveTempDir,$backupName,$excludeFile,$mysqlUser,$mysqlPassword);
		}

		$result = $ib->archiveForUpload($user,$backupsStorage,$archiver,$maxArchiveSize);
		if(is_array($result)) {
			foreach($result as $archiveItem)
				$ib->toLog($archiveItem."\n");
		}
		$ib->archiveTasksUpload($tasks,$user);
		$ib->cleanForUpload();

		$timeDiff = time()-$now;
		$ib->toLog("Ended in $timeDiff seconds\n");
		$ib->printDelimiter();
	}
}

$ib->diskFree();
$ib->report(true,$mailAddress,$mailSubject);
?>
