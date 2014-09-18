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

	// var_dump($databases);
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

//Create backups

if(!empty($users)) {

	foreach ($users as $user => $databases) {

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

		exec('mkdir -p '.$backupPath);
		exec('ln -s backup-'.$date.' '.$backupPath.'/current');

		exec('rsync -az --link-dest='.$backupPath.'/current '.$path.' '.$backupPath.'/'.$backupName);
		exec('rm '.$backupPath.'/current && ln -s '.$backupName.' '.$backupPath.'/current');

		//Delete mysql dir from user directory
		exec('rm -rf '.$mysqlPath);

		//Archiving backups
		exec('tar zcf '.$backupPath.'.tar.gz '.$backupPath);
		//Uploading to ftp
		exec('curl -T '.$backupPath.'.tar.gz ftp://'.$ftpHost.'/'.$ftpPath.'/ --user '.$ftpUser.':'.$ftpPassword);
		//Remove archive
		exec('rm '.$backupPath.'.tar.gz');
	}

}