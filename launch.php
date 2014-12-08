<?php

require "config.php";
require "phpIB.php";

$ib = new phpIB($backupsStorage,$backupsArchiveTempDir);
$users = $ib->getISPUsersData();

if(is_array($users)) {
    foreach($users as $user => $databases) {
        echo "Backup started for $user:\n";
        $now = time();
        $ib->cleanOldBackups($user,$backupsStorage,$days);
        $ib->backupISPUser($user,$userPath,$databases,$backupsStorage,$backupsArchiveTempDir,$backupName,$excludeFile,$mysqlUser,$mysqlPassword);
        $ib->archiveForUpload($user,$backupsStorage,$archiver,$maxArchiveSize);
        $ib->archiveTasksUpload($tasks);
        $ib->cleanForUpload();
        $timeDiff = time()-$now;
        echo "Ended in $timeDiff seconds";
    }
}

$ib->diskFree();

?>