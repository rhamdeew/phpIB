<?php

class phpIB {

	public $log = true;
	public $onlyUpload = false;
	public $dryRun = false;
	private $logString = '';
	private $apps = array();
	private $backupArchiveName = '';
	private $startTime = '';

	function __construct($backupsStorage,$backupsArchiveTempDir,$message = 'Starting backup process') {
		$this->startTime = time();
		$this->toLog($message."\n");
		$this->myExec('mkdir','-p '.$backupsStorage);
		$this->myExec('mkdir','-p '.$backupsArchiveTempDir);
	}

	function __destruct() {
		if(!empty($this->logString))
			$this->report(false);
	}

	public function getISPUsersData() {
		$this->toLog("Getting ISPmanager data\n");
		$now = time();
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

		$timeDiff = time()-$now;
		$this->toLog("Done in $timeDiff sec.\n");
		$this->toLog("\n");

		return $users;
	}

	public function backupISPUser($user,$userPath,$userDatabases,$backupsStorage,$backupsArchiveTempDir,$backupName,$excludeFile,$mysqlUser,$mysqlPassword) {
		$this->toLog("Starting rsync\n");
		$now = time();
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

		if(!is_dir($backupPath)) {
			$this->myExec('mkdir','-p '.$backupPath);
		}

		if(file_exists(__DIR__.'/'.$excludeFile)) {
			$this->toLog("Use exclude file $excludeFile\n");
			$this->myExec('rsync','-a --del --delete-excluded --exclude-from \''.__DIR__.'/'.$excludeFile.'\' --link-dest='.$backupPath.'/current '.$path.' '.$backupPath.'/'.$backupName);
		}
		else {
			$this->myExec('rsync','-a --del --delete-excluded --link-dest='.$backupPath.'/current '.$path.' '.$backupPath.'/'.$backupName);
		}

		$this->myExec('rm -f',$backupPath.'/current');
		$this->myExec('ln -s',$backupPath.'/'.$backupName.' '.$backupPath.'/current');

		//Delete mysql dir from user directory
		$this->myExec('rm','-rf '.$mysqlPath);

		$timeDiff = time()-$now;
		$this->toLog("Done in $timeDiff sec.\n");
		$this->toLog("\n");
	}

	public function archiveForUpload($user,$backupsStorage,$archiver,$maxArchiveSize='',$archivesCount=3) {
		$this->toLog("Starting archieving process\n");
		$now = time();
		$backupPath = $backupsStorage.$user;
		$result = $this->myExec('ls','-rtd '.$backupPath.'/backup-* | xargs -n 1 basename');

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
					$this->toLog("Not found $archiver program!\n");
					die("\n"); //TODO: Fix to exception
				}

				$arch_bin = $archiver;
				if($archiver=='pigz')
					$arch_bin .= ' -9 -p 32';

				$this->toLog("Use $archiver\n");
			}

			if(!empty($maxArchiveSize))
				$this->myExec('tar','cf - '.$backupNames.' | '.$arch_bin.' | split -b '.$maxArchiveSize.' -d - '.$this->backupArchiveName.'.tar.gz');
			else
				$this->myExec('tar','cf - '.$backupNames.' | '.$arch_bin.' > '.$this->backupArchiveName.'.tar.gz');

			$result = $this->myExec('ls','-lah '.$this->backupArchiveName.'.tar.gz*',true);

			$timeDiff = time()-$now;
			$this->toLog("Done in $timeDiff sec.\n");
			$this->toLog("\n");

			return $result;
		}

		return false;
	}

	public function archiveTasksUpload($tasks,$user) {
		if(is_array($tasks)) {
			foreach($tasks as $taskname => $task) {
				$now = time();
				$this->toLog("\nStarting upload task: $taskname \n");
				$this->remoteBackup($task,$this->backupArchiveName,$user);
				$timeDiff = time()-$now;
				$this->toLog("Done in $timeDiff sec.\n");
				$this->toLog("\n");
			}
		}
	}

	public function cleanForUpload($messagePattern='Clear tmp archives') {
		if(!empty($this->backupArchiveName))
			$this->myExec('rm',$this->backupArchiveName.'*',true,false,$messagePattern);
	}

	public function cleanOldBackups($user,$backupsStorage,$days,$backupNamePattern = 'backup-',$messagePattern='Removed: :arg:') {
		$backupPath = $backupsStorage.$user;
		$result = $this->myExec('find',$backupPath.'/ -name "'.$backupNamePattern.'*" -type d | xargs -n 1 basename',false,false);
		if($days==0) $days = 3;
		$seconds = 60*60*24*$days;
		$now = time();
		if(is_array($result)) {
			foreach($result as $dir) {
				$dirtime = str_replace($backupNamePattern,'',$dir);
				if($now-strtotime($dirtime)>$seconds) {
					$this->myExec('rm -rf',$backupPath.'/'.$dir,true,false,$messagePattern);
				}
			}
		}
	}

	public function diskFree($device='',$messagePattern = "Available disk space:\n :result:") {
		$arg = '-H';
		if(!empty($device)) {
			$arg = '-H '.$device;
		}
		$result = $this->myExec('df',$arg,false,false,'');
		$this->toLog(str_replace(':result:',$result[1],$messagePattern)."\n");
	}


	public function printDelimiter() {
		$this->toLog("\n--------------------------\n");
	}

	public function toLog($msg) {
		$this->logString .= $msg;
		echo $msg;
	}

	public function report($send=true,$mailAddress='',$mailSubject='backup complete') {
		$now = time();
		$timeDiff = $now - $this->startTime;
		$this->toLog("All backups complete in $timeDiff sec.\n");
		if($send && !empty($mailAddress)) {
			$this->myMail($mailAddress,$mailSubject,$this->log);
		}
		$this->log = '';

	}

	private function remoteBackup($task,$user) {
		if(!isset($task['type'])) {
			$this->toLog("Backup task without type!\n");
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
				$this->myExec('aws','s3 cp '.$file.' s3://'.$task['path']);
			}
			return $result;
		}
		if($task['type']=='rsync') {
			$tmp = explode('/',$user);
			$tmp = end($tmp);
			$this->myExec('rsync',$task['args'].' '.$task['localpath'].' '.$task['hostname'].':'.$task['remotepath'].$tmp.'/');
			return $result;
		}
		if($task['type']=='local') {
			foreach($result as $file) {
				$this->myExec('cp',$file.' '.$task['localpath']);
			}
			if(isset($task['owner'])) {
				$this->myExec('chown','-R '.$task['owner'].':'.$task['owner'].' '.$task['localpath']);
			}
			return $result;
		}

		return false;
	}
	private function myExec($bin,$arg,$log='',$dryrun='',$messagePattern='') {

		if($log==='') {
			$log = $this->log;
		}
		if($dryrun==='') {
			$dryrun = $this->dryRun;
		}

		if(!isset($this->apps[$bin])) {
			exec('which '.$bin,$result);
			if(empty($result)) {
				if($log) $this->toLog("Not found $bin program!\n");
				else echo "Not found $bin program!\n";

				return false;
			}

			$this->apps[$bin] = true;
		}

		$result = array();
		if(!$dryrun) {
			if(!empty($messagePattern))
				$execMessage = $this->myExecMessage($messagePattern,$bin,$arg);

			exec($bin.' '.$arg,$result);

			if($log && isset($execMessage))
					$this->toLog($execMessage);
		}
		else {
			$this->toLog($bin.' '.$arg."\n");
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

	private function myMail($mailAddress,$mailSubject = 'backup complete',$message,$fromUser = "backup robot",$fromEmail = "robot@backup") {
		$fromUser = "=?UTF-8?B?".base64_encode($fromUser)."?=";
		$mailSubject = "=?UTF-8?B?".base64_encode($mailSubject)."?=";

		$headers = "From: $fromUser <$fromEmail>\r\n".
			"MIME-Version: 1.0" . "\r\n" .
			"Content-type: text/plain; charset=UTF-8" . "\r\n";

		return mail($mailAddress,$mailSubject,$message,$headers);
	}

}

?>
