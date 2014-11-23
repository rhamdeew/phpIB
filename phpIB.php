<?php

$cmd = "";
if(isset($argv[1])) {
	$cmd = $argv[1];
}
$log = '';

echo "Starting backup process\n";
$log .= "Starting backup process\n";

require "config.php";
exec('mkdir -p '.$backupsArchiveTempDir);
/*
$now = time();
$alltimeNow = $now;


///////////////////////////////////////////////////////////
//														 //
//						Getting info 					 //
//														 //
///////////////////////////////////////////////////////////

echo "Getting ISPmanager data\n";
$log .= "Getting ISPmanager data\n";
//Getting list of databases
exec('/usr/local/ispmgr/sbin/mgrctl -m ispmgr db',$result);
$s = '';
$databases = array();
if(!empty($result)) {
	foreach($result as $dbItem) {
		$s = explode(" ", $dbItem);
		$db = array();
		foreach ($s as $param) {
			$param = explode("=",$param);
			if(isset($param[1])) {
				if($param[0]=="name")
					$db["name"] = $param[1];
				if($param[0]=="owner")
					$db["owner"] = $param[1];
			}
		}
		if(isset($db["name"]) && isset($db['owner']))
			$databases[$db["owner"]][] = $db["name"];
	}
}
//Getting list of users
$users = array();
unset($result);
exec("/usr/local/ispmgr/sbin/mgrctl -m ispmgr user | cut -d' ' -f1 | sed s/name=//",$result);
if(!empty($result)) {
	foreach ($result as $user) {
		$users[$user] = array();
		if(isset($databases[$user])) {
			foreach ($databases[$user] as $dbName) {
				$users[$user][] = $dbName;
			}
		}
	}
}
$sec = time()-$now;
echo "Done in ".$sec." sec.\n";
$log .= "Done in ".$sec." sec.\n";

///////////////////////////////////////////////////////////
//														 //
//	  				  Create backups 					 //
//														 //
///////////////////////////////////////////////////////////

if(empty($cmd) || $cmd=='--delete-old') {

	if(!empty($users)) {

	echo "Starting rsync process\n";
	$log .= "Starting rsync process\n";

		foreach ($users as $user => $databases) {

			if($cmd!='--delete-old') {
				$now = time();
				echo "User ".$user."\n";
				$log .= "User ".$user."\n";

				//Databases backups
				$path = $user_path.$user.'/';
				$mysqlPath = $path.'data/mysql';

				exec('mkdir -p '.$mysqlPath);
				exec('rm -rf '.$mysqlPath.'/*');

				foreach ($databases as $dbName) {
					exec('mysqldump -u '.$mysql_user.' -p'.$mysql_password.' '.$dbName.' | gzip > '.$mysqlPath.'/'.$dbName.'.sql.gz');
				}

				exec('chown -R '.$user.':'.$user.' '.$mysqlPath);


				//Rsync backup
				$backupPath = $backupsStorage.$user;
				$backupArchiveName = $backupsArchiveTempDir.$user;

				if(!file_exists($backupPath.'/current')) {
					
					if(!is_dir($backupPath)) {
						exec('mkdir -p '.$backupPath); // /var/backups/local/user1
						exec('ln -s '.$path.' '.$backupPath.'/current');	
					}
					else {
						unset($result);
						exec('ls -td '.$backupPath.'/backup-* | xargs -n 1 basename', $result);
						if(!empty($result[0]))
							exec('ln -s '.$result[0].' '.$backupPath.'/current');
						else
							exec('ln -s '.$path.' '.$backupPath.'/current');								
					}
					
				}

		        if(file_exists(__DIR__.'/'.$exclude_file)) {
		        	echo "Use exclude file ".$exclude_file."\n";
		        	$log .= "Use exclude file ".$exclude_file."\n";
					exec('rsync -a --del --delete-excluded --exclude-from \''.__DIR__.'/'.$exclude_file.'\' --link-dest='.$backupPath.'/current '.$path.' '.$backupPath.'/'.$backupName);
				}
				else {
					exec('rsync -a --del --delete-excluded --link-dest='.$backupPath.'/current '.$path.' '.$backupPath.'/'.$backupName);
				}

				exec('rm '.$backupPath.'/current && ln -s '.$backupName.' '.$backupPath.'/current');

				//Delete mysql dir from user directory
				exec('rm -rf '.$mysqlPath);
			}


			//Remove old backups
			//TODO: Fix to use linux utils
			unset($result);
			exec('ls '.$backupPath.'/',$result);

			if(!empty($result)) {

				foreach($result as $dir) {
					if(mb_substr($dir, 0,7)=="backup-") {

						$date = mb_substr($dir, 7);
						$time = strtotime($date);
						$datediff = floor(($now - $time)/(60*60*24));
						if($datediff>$days) {
							exec('rm -rf '.$backupPath.'/'.$dir);
							echo "Removed: ".$backupPath.'/'.$dir."\n";
							$log .= "Removed: ".$backupPath.'/'.$dir."\n";
						}
					}
				}

			}

			$sec = time()-$now;
			echo "Done in ".$sec." sec.\n";
			$log .= "Done in ".$sec." sec.\n";

			if($cmd!=='--delete-old') {
				$now = time();
				echo "Starting archieving process\n";
				$log .= "Starting archieving process\n";
				unset($result);
				exec('ls -td '.$backupPath.'/backup-* | xargs -n 1 basename', $result);
				
				if(!empty($result))
					$backupNames = array_slice($result, 0, 3); //3 last backups to upload

				if(!empty($backupNames)) {

					foreach ($backupNames as $key => $value) {
						$backupNames[$key] = $backupPath.'/'.$value;
					}

					$backupNames = implode(' ', $backupNames);

					// Archiving backups
					if($archiver=='gzip') {
						echo "Use gzip\n";
						$log .= "Use gzip\n";
						$arch_bin = "gzip";
					}
					elseif($archiver=='pigz') {
						echo "Use pigz\n";
						$log .= "Use pigz\n";
						$arch_bin = "pigz -9 -p 32";
					}

					if(!empty($max_archive_size)) {
						echo 'tar cf - '.$backupNames.' | '.$arch_bin.' | split -b '.$max_archive_size.' -d - '.$backupArchiveName.'.tar.gz'."\n";
						exec('tar cf - '.$backupNames.' | '.$arch_bin.' | split -b '.$max_archive_size.' -d - '.$backupArchiveName.'.tar.gz');
					}
					else {
						echo 'tar cf - '.$backupNames.' | '.$arch_bin.' > '.$backupArchiveName.'.tar.gz'."\n";
						exec('tar cf - '.$backupNames.' | '.$arch_bin.' > '.$backupArchiveName.'.tar.gz');
					}

					$sec = time()-$now;
					echo "Done in ".$sec." sec.\n";
					$log .= "Done in ".$sec." sec.\n";

					$now = time();
					echo "Starting upload process\n";

					if($s3BackupFlag && !empty($s3BucketPath)) {
						echo 'Backup to Amazon S3'."\n";
						$log .= 'Backup to Amazon S3'."\n";
						//Uploading to Amazon S3
						unset($result);
						exec('ls '.$backupArchiveName.'.tar.gz*',$result);
						foreach($result as $file) {
							exec('s3cmd put '.$file.' s3://'.$s3BucketPath);
						}
					}
					
					if($ftpBackupFlag && !empty($ftpHost)) {
						//Uploading to ftp
						echo 'Backup to remote ftp'."\n";
						$log .= 'Backup to remote ftp'."\n";
						unset($result);
						exec('ls '.$backupArchiveName.'.tar.gz*',$result);
						foreach($result as $file) {
							exec('curl -T '.$file.' ftp://'.$ftpHost.'/'.$ftpPath.'/ --user '.$ftpUser.':'.$ftpPassword);
						}
					}

					//Remove archive
					exec('rm '.$backupArchiveName.'.tar.gz*');
					
					$sec = time()-$now;
					echo "Done in ".$sec." sec.\n";		
					$log .= "Done in ".$sec." sec.\n";
							 
					echo "-----------------------\n";
					$log .= "-----------------------\n";
				}
		
			}
		}

	}

}

$sec = time()-$alltimeNow;
echo "All time ".$sec." sec.\n";
$log .= "All time ".$sec." sec.\n";

unset($result);
exec('df -h',$result);

if(isset($result[1])) {
        echo "Available space on device: ".$result[1]."\n";
        $log .= "Available space on device: ".$result[1]."\n";
}

file_put_contents(__DIR__.'/backup.log',$log,FILE_APPEND);

if(!empty($mailAddress))
	mail($mailAddress,$mailSubject,$log);