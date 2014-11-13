<?php

$cmd = "";
if(isset($argv[1])) {
	$cmd = $argv[1];
}
$log = '';

echo "Starting backup process\n";
$log .= "Starting backup process\n";

require "config.php";
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
$result = array();
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
			$result = "";
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
						echo 'tar zcf '.$backupPath.'.tar.gz '.$backupNames."\n";
						exec('tar zcf '.$backupPath.'.tar.gz '.$backupNames);
					}
					elseif($archiver=='pigz') {
						echo "Use pigz\n";
						$log .= "Use pigz\n";
						if(!empty($max_archive_size)) {
							echo 'tar cf - '.$backupNames.' | pigz -9 -p 32 | split -b '.$max_archive_size.' -d - '.$backupPath.'.tar.gz'."\n";
							exec('tar cf - '.$backupNames.' | pigz -9 -p 32 | split -b '.$max_archive_size.' -d - '.$backupPath.'.tar.gz');
						}
						else {
							echo 'tar cf - '.$backupNames.' | pigz -9 -p 32 > '.$backupPath.'.tar.gz'."\n";
							exec('tar cf - '.$backupNames.' | pigz -9 -p 32 > '.$backupPath.'.tar.gz');
						}
					}

					$sec = time()-$now;
					echo "Done in ".$sec." sec.\n";
					$log .= "Done in ".$sec." sec.\n";

					$now = time();
					echo "Starting upload process\n";

					//Uploading to ftp
					if(!empty($max_archive_size)) {
						exec('ls '.$backupPath.'.tar.gz*',$result);
						foreach($result as $file) {
							exec('curl -T '.$file.' ftp://'.$ftpHost.'/'.$ftpPath.'/ --user '.$ftpUser.':'.$ftpPassword);
						}
					}
					else {
						exec('curl -T '.$backupPath.'.tar.gz ftp://'.$ftpHost.'/'.$ftpPath.'/ --user '.$ftpUser.':'.$ftpPassword);			
					}		

					//Remove archive
					if(!empty($max_archive_size)) {
						exec('rm '.$backupPath.'.tar.gz*');
					}
					else {
						exec('rm '.$backupPath.'.tar.gz');
					}
					
					$sec = time()-$now;
					echo "Done in ".$sec." sec.\n";		
							 
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

file_put_contents(__DIR__.'/backup.log',$log,FILE_APPEND);