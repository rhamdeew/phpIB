<?php

require "config.php";
require "phpIB.php";

$ib = new phpIB($backupsStorage,$backupsArchiveTempDir);
$users = $ib->getISPUsersData();

if(is_array($users)) {
    foreach($users as $user => $databases) {
        $ib->printDelimiter();
        $ib->toLog("Backup started for $user:\n");
        $now = time();
        $ib->cleanOldBackups($user,$backupsStorage,$days);
        $ib->backupISPUser($user,$userPath,$databases,$backupsStorage,$backupsArchiveTempDir,$backupName,$excludeFile,$mysqlUser,$mysqlPassword);
        $result = $ib->archiveForUpload($user,$backupsStorage,$archiver,$maxArchiveSize);
        if(is_array($result)) {
            foreach($result as $archiveItem)
                $ib->toLog($archiveItem."\n");
        }
        $ib->archiveTasksUpload($tasks);
        $ib->cleanForUpload();
        
        $timeDiff = time()-$now;
        $ib->toLog("Ended in $timeDiff seconds\n");
        $ib->printDelimiter();
    }
}

$ib->diskFree();
$ib->report(true,$mailAddress,$mailSubject);

?>