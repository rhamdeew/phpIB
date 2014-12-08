<?php

class phpIB {

	private $log = '';	
	private $apps = array();
	private $backupArchiveName = '';
	private $startTime = '';
	
	function __construct($backupsStorage,$backupsArchiveTempDir,$message = 'Starting backup process') {
		echo $message."\n";
		$this->startTime = time();
		$this->log .= $message."\n";
		$this->myExec('mkdir','-p '.$backupsStorage);
		$this->myExec('mkdir','-p '.$backupsArchiveTempDir);
	}
	
	function __destruct() {
		$now = time();
		$timeDiff = $now - $this->startTime;
		echo "All backups complete in $timeDiff sec.\n";
	}
	
	public function getISPUsersData() {
		exec('/usr/local/ispmgr/sbin/mgrctl -m ispmgr db',$result);
		$s = '';
		$users = array();
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
		
		return $users;
	}
	
	public function backupISPUser($user,$userPath,$userDatabases,$backupsStorage,$backupsArchiveTempDir,$backupName,$excludeFile,$mysqlUser,$mysqlPassword) {
		//Databases backups
		$path = $userPath.$user.'/';
		$mysqlPath = $path.'data/mysql';

		exec('mkdir -p '.$mysqlPath);
		exec('rm -rf '.$mysqlPath.'/*');

		foreach ($userDatabases as $dbName) {
			$this->myExec('mysqldump','-u '.$mysqlUser.' -p'.$mysqlPassword.' '.$dbName.' | gzip > '.$mysqlPath.'/'.$dbName.'.sql.gz');
		}

		$this->myExec('chown','-R '.$user.':'.$user.' '.$mysqlPath);
		
		//Rsync backup
		$backupPath = $backupsStorage.$user;
		$this->backupArchiveName = $backupsArchiveTempDir.$user;

		if(!file_exists($backupPath.'/current')) {
			if(!is_dir($backupPath)) {
				$this->myExec('mkdir','-p '.$backupPath);
				$this->myExec('ln','-s '.$path.' '.$backupPath.'/current');
			}
			else {
				$result = $this->myExec('ls','-td '.$backupPath.'/backup-* | xargs -n 1 basename');
				if(!empty($result[0]))
					$this->myExec('ln','-s '.$result[0].' '.$backupPath.'/current');
				else
					$this->myExec('ln','-s '.$path.' '.$backupPath.'/current');							
			}
		}

		if(file_exists(__DIR__.'/'.$excludeFile)) {
			$this->myExec('rsync','-a --del --delete-excluded --exclude-from \''.__DIR__.'/'.$excludeFile.'\' --link-dest='.$backupPath.'/current '.$path.' '.$backupPath.'/'.$backupName);
		}
		else {
			$this->myExec('rsync','-a --del --delete-excluded --link-dest='.$backupPath.'/current '.$path.' '.$backupPath.'/'.$backupName);
		}

		$this->myExec('rm',$backupPath.'/current && ln -s '.$backupName.' '.$backupPath.'/current');

		//Delete mysql dir from user directory
		$this->myExec('rm','-rf '.$mysqlPath);
	}
	
	public function archiveForUpload($user,$backupsStorage,$archiver,$maxArchiveSize='',$archivesCount=3) {
		$backupPath = $backupsStorage.$user;
		$result = $this->myExec('ls','-td '.$backupPath.'/backup-* | xargs -n 1 basename');
				
		if(!empty($result))
			$backupNames = array_slice($result, 0, $archivesCount); //3 last backups to upload

		if(!empty($backupNames)) {

			foreach ($backupNames as $key => $value) {
				$backupNames[$key] = $backupPath.'/'.$value;
			}

			$backupNames = implode(' ', $backupNames);

			// Archive utilite test
			if($archiver=='gzip' || $archiver=='pigz') {				
				exec('which '.$archiver,$result);
				if(empty($result)) {
					$this->log .= "Not found $archiver program!\n";
					die("Not found $archiver program!\n"); //TODO: Fix to exception
				}
				
				$arch_bin = $archiver;
				if($archiver=='pigz')
					$arch_bin .= ' -9 -p 32';
			}

			if(!empty($maxArchiveSize))
				$this->myExec('tar','cf - '.$backupNames.' | '.$arch_bin.' | split -b '.$maxArchiveSize.' -d - '.$this->backupArchiveName.'.tar.gz');
			else
				$this->myExec('tar','cf - '.$backupNames.' | '.$arch_bin.' > '.$this->backupArchiveName.'.tar.gz');
		}
	}
	
	public function archiveTasksUpload($tasks) {
		if(is_array($tasks)) {
			foreach($tasks as $taskname => $task) {
				$this->log .= "Starting upload task: $taskname \n";
				$this->remoteBackup($task,$this->backupArchiveName);
			}
		}
	}
	
	public function cleanForUpload($messagePattern='Clean tmp archives') {
		if(!empty($this->backupArchiveName))
			$this->myExec('rm',$this->backupArchiveName.'*',true,false,$messagePattern);
	}	
	
	public function cleanOldBackups($user,$backupsStorage,$days,$backupNamePattern = 'backup-',$messagePattern='Removed: :arg:') {
		$backupPath = $backupsStorage.$user;
		$result = $this->myExec('find',$backupPath.' -name "'.$backupNamePattern.'*" -mtime +'.$days,false,true);
		if(is_array($result)) {
			foreach($result as $dir) {
				$this->myExec('rm -rf',$dir,true,false,$messagePattern);	
			}
		}
	}
	
	public function diskFree($device='',$messagePattern = 'Available disk space: :result:') {
		$arg = '-H';
		if(!empty($device)) {
			$arg = '-H '.$device;
		}
		$result = $this->myExec('df',$arg,true,false,'');
		echo str_replace(':result:',$result[1],$messagePattern);
	}
	
	private function remoteBackup($task) {
		if(!isset($task['type'])) {
			$this->log .= "Backup task without type!\n";
			return false;
		}
		
		$result = $this->myExec('ls',$this->backupArchiveName.'*');
		if(!is_array($result))
			return false;
			
		if($task['type']=='ftp') {			
			foreach($result as $file)
				$this->myExec('curl','-T '.$file.' ftp://'.$task['host'].'/'.$task['path'].'/ --user '.$task['user'].':'.$task['password']);
			return $result;
		}
		
		if($task['type']=='s3') {
			foreach($result as $file) {
				$this->myExec('s3cmd ','put '.$file.' s3://'.$s3BucketPath);
			}
			return $result;
		}
		
		return false;
	}
	private function myExec($bin,$arg,$log=false,$dryrun=false,$messagePattern='') {
		if(!isset($this->apps[$bin])) {
			exec('which '.$bin,$result);
			if(empty($result)) {
				echo "Not found $bin program!\n";
				if($log) $this->log .= "Not found $bin program!\n";
				return false;
			}
			
			$this->apps[$bin] = true;
		}
		
		$result = array();
		if(!$dryrun) {
			if(!empty($messagePattern)) {
				$execMessage = $this->myExecMessage($messagePattern,$bin,$arg);
				echo $execMessage;	
			}
			exec($bin.' '.$arg,$result);
			if($log) {
				if(isset($execMessage))
					$this->log .= $execMessage;
				$this->log .= print_r($result,true)."\n";
			}
		}
		else {
			echo $bin.' '.$arg."\n";
		}

		return $result;
	}
	
	private function myExecMessage($messagePattern='',$bin='',$arg='') {
		$message = '';
		if(!empty($messagePattern)) {
			$message = str_replace(':bin:',$bin,$messagePattern);
			$message = str_replace(':arg:',$arg,$message);
			return $message."\n";
		}
		
		return $bin.' '.$arg."\n";
	}
	
}

?>