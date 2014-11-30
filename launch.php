<?php

require "config.php";
require "phpIB.php";

$ib = new phpIB();
$users = $ib->getISPUsersData();

if(is_array($users)) {
    foreach($users as $user => $databases) {
        $ib->cleanOldBackups($user,$backupsStorage,$days);
        $ib->backupISPUser($user,$userPath,$databases,$backupsStorage,$backupsArchiveTempDir,$backupName);
        $ib->archiveForUpload($user,$backupsStorage,$archiver,$maxArchiveSize);
        $ib->archiveTasksUpload($tasks);
        $ib->cleanForUpload($backupArchiveName);
    }
}

$ib->diskFree();

?>