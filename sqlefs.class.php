<?php

# SQLEFS
# Copyright 2015
# MIT License
# Created by Ryan Cole
# Contact ryan@practicalproductivity.com

# USAGE:
# install in sub-directory sqlefs
# use admin.php to create new database and add new volume (sqlefs table)
# or create an automated install script from the second example below.

# MINIMAL TEST (verify install)
/*
include 'sqlefs/sqlefs.php'; # Loads class and assigns efs object.
echo $efs->version; # Should display SQLEFS version.
*/

# INSTALL SCRIPT EXAMPLE
/* 
require 'sqlefs/sqlefs.class.php'; # Loads class 
$efs = new efsclass(); # assigns efs object.
$efs->connect("mysql:host=localhost username password") # Connect to sql server only. Use own user/pass of course!
$efs->fdisk("new", "myefsdb"); # You may have to create your database through your webserver control panel!
$efs->db("myefsdb") # connect to database!
$efs->format("c", "single", "blob"); # c is the volume name, single is the format type, and blob is the block size (up to 64k files)
*/

# VERIFY YOUR INSTALL EXAMPLE
/*
require 'sqlefs/sqlefs.class.php'; # Loads class
$efs = new efsclass(); # assigns efs object.
$efs->connect("mysql:host=localhost;dbname=myefsdb username password") # connect to database this time!
$efs->cd("c:/"); # you must select a volume using cd or disk.
$efs->write("myfirstfile.txt", "It worked!");
echo $efs->read("myfirstfile.txt");
*/



# Volume table; "single" type
# fullpath	tinytext	 
# path	tinytext	 
# filename	tinytext	 
# data	any blob type.
# type	tinytext	 
# owner	tinytext	 
# modified	datetime

	
class efsclass {
	public $version = "0.8.4";
	public $website = "practicalproductivity.com/sqlefs";
	public $fslink;
	public $state = array( 'db' => '', 'vol' => '', 'user' => '', 'path' => '', 'volref' => array() );
	public $connection = '';

	public $extensiontab = array (
		'' => 'application/octet-stream',
		'bmp' => 'image/bmp',
		'gif' => 'image/gif',
		'html' => 'text.html',				
		'jpg' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'js' => 'application/x-javascript',
		'mp3' => 'audio/mpeg3',
		'mpg' => 'video/mpeg',
		'pdf' => 'application/pdf',
		'png' => 'image/png',
		'mid' => 'audio/midi',
		'midi' => 'audio/midi',
		'mov' => 'video/quicktime',
		'txt' => 'text/plain',
		'xml' => 'application/xml',
	);
	
# if single arg we use string connection spec "mysql:host=localhost;dbname=foo name pass" 
# otherwise we use defaults
	function connect($db, $user = false, $pass = false, $host = 'localhost', $dbm = 'mysql') {
		$this->state = array ( 'db' => '','vol' => '','user' => '','path' => '','volref' => array() );
		if (!$user && !$pass) { # use connection string
			$conndata = explode(' ', $db); 
			$this->fslink = new PDO($conndata[0], $conndata[1], $conndata[2]);
			$this->fslink->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$namepos = strpos($conndata[0], 'dbname=');
			if ($namepos!==false) {$this->state['db'] = substr($conndata[0], $namepos + 7);}
			$this->state['user'] = $conndata[1] . '!';
			$this->connection = $db;			
		} else {
			// Create connection
			$connstring = $dbm . ':host=' . $host;
			if ($db != null) {$connstring .= ';dbname=' . $db;}
			$this->fslink = new PDO($connstring, $user, $pass);
			$this->fslink->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			if ($db != null) {$this->state['db'] = $db;}
			$this->state['user'] = $user . '!';
			$this->connection = $connstring . ' ' . $user . ' ' . $pass;
		}
		if ($this->state['db'] != '') {$this->db($this->state['db']);}
		return true;
	}
	
	# sets the database
	function db($dbase) {
		$stmt = $this->fslink->prepare("USE " . $dbase);
		$stmt->execute();
		$this->fdisk('volumes'); # updates state volume path reference table
		$this->state['db'] = $dbase;
	} 

	# sets the current simulated volume (aka disk drive, volume table). Argless will return current drive.
	function disk($name = false) {
		if ( $name == false ) { return $this->state['vol']; }
		$this->cd($this->toname($name) . ':');
		return $this->state['vol'];
	}

	# change the user, volume, or default file path. Argless returns current path.
	function cd($fp = '') {
		$udpnf = $this->resolve($fp);
		if ( $this->state['path'] != '' ) {
			$this->state['volref'][$this->state['vol']] = $this->state['path']; 
			$this->state['path'] = $udpnf[2];
		} else {
			$this->state['path'] = '/';
		}
		$this->state['user'] = $udpnf[0];
		$this->state['vol'] = $udpnf[1];

		return $this->state['user'] .'@' . $this->state['vol'] . ":" . $this->state['path'];
	}

	# fetch information on a single file
	function info($fp) {
		$dir = $this->details($fp);
		if ($dir[0]) {return $dir[0];} else {return false;}
	}

	# check if super user (ends with !)
	function issuper($user) {return '!' === substr($user,-1);}
	
	# return just the files in a path in simple array
	function ls($fp = "") {
		$udpnf = $this->resolve($fp);
		if ($udpnf[3]==='') {$pathtype = 'path'; $limit = '';} else {$pathtype = 'fullpath'; $limit = 'LIMIT 1';}		
		if ($this->issuper($udpnf[0])) { #superuser
			$stmt = $this->fslink->prepare("SELECT filename FROM `" . $udpnf[1] . "` WHERE " . $pathtype . " = ? ORDER BY modified ".$limit);
			$stmt->execute(array($udpnf[4]));
		} else {
			$stmt = $this->fslink->prepare("SELECT filename FROM `" . $udpnf[1] . "` WHERE " . $pathtype . " = ? AND owner = ? ORDER BY modified ".$limit);
			$stmt->execute(array($udpnf[4], $udpnf[0]));
		}
		$outdir=array();
		return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
	}
	
	# return all information about files in a path in array of associative arrays
	function details($fp = "") {
		$udpnf = $this->resolve($fp);
		if ($udpnf[3]==='') {$pathtype = 'path'; $limit = '';} else {$pathtype = 'fullpath'; $limit = 'LIMIT 1';}		
		if ($this->issuper($udpnf[0])) { #superuser
			$stmt = $this->fslink->prepare(
				"SELECT filename, modified, type, owner, LENGTH(data) AS size FROM `" . $udpnf[1] . "` WHERE " . $pathtype . " = ? ORDER BY modified ".$limit);
			$stmt->execute(array($udpnf[4]));
		} else {
			$stmt = $this->fslink->prepare("SELECT filename, modified, type, owner, LENGTH(data) AS size FROM `" . $udpnf[1] . "` WHERE " . $pathtype . " = ? AND owner = ? ORDER BY modified ".$limit);
			$stmt->execute(array($udpnf[4], $udpnf[0]));
		}
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	# return immediate subfolders in a path.
	function subs($fp = "") {
		$udpnf = $this->resolve($fp);
		$startat = 1+ strlen($udpnf[2]);	
		$expression = '^' . $udpnf[2];
		$stmt = $this->fslink->prepare(
			"SELECT DISTINCT CONCAT(SUBSTRING_INDEX(SUBSTRING(path, ?), '/', 1), '/') FROM `" . $udpnf[1] . "` WHERE path REGEXP ? AND CHAR_LENGTH(path) > ? ");
		$stmt->execute(array($startat,$expression,$startat));
		return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
	}

	# move up a directory
	function up() {
		$dirs = explode('/', $this->state['path']);
		array_pop($dirs);
		array_pop($dirs);
		$this->state['path'] = implode('/', $dirs) . '/';
		return $this->cd();
	}

	# read a file.
	function read($fp) {
		$udpnf = $this->resolve($fp);
		if ($this->issuper($udpnf[0])) { #superuser
			$stmt = $this->fslink->prepare("SELECT data FROM `" . $udpnf[1] . "` WHERE fullpath = ? LIMIT 1");
			$stmt->execute(array($udpnf[4]));
		} else {
			$stmt = $this->fslink->prepare("SELECT data FROM `" . $udpnf[1] . "` WHERE fullpath = ? AND owner = ? LIMIT 1");
			$stmt->execute(array($udpnf[4],$udpnf[0]));
		}
		$result = $stmt->fetchAll();
		if (0 == $stmt->rowCount()) {return false;}
		return $result[0]['data'];
	}

	# write/overwrite a file.
	function write($fp, $data, $ts = false) {
		$udpnf = $this->resolve($fp);
		if (!$ts) {$ts = date('Y-m-d H:i:s');}
		if (!$this->issuper($udpnf[0]) && $this->exists($udpnf[4]) && $udpnf[0] !== $this->owner($udpnf[4])) {return false;} # security failure 
		$stmt = $this->fslink->prepare("REPLACE INTO `" . $udpnf[1] . "` (fullpath, path, filename, data, modified, type, owner) VALUES (?, ?, ?, ?, ?, ?, ?)");
		$stmt->execute(array(
			$udpnf[4], 
			$udpnf[2], 
			$udpnf[3],
			$data, 
			$ts, 
			'f',
			$udpnf[0]
		));
		return true;
	}

	# remove file.
	function rm($fp) {
		$udpnf = $this->resolve($fp);
		if (!$this->issuper($udpnf[0]) && $this->state['user'] !== $this->owner($udpnf[4])) {return false;} # security failure 
		$stmt = $this->fslink->prepare("DELETE FROM `" . $udpnf[1] . "` WHERE fullpath = ?");
		$stmt->execute(array($udpnf[4]));
		return true;
	}

	# remove all files in directory. (Does not affect subfiles).
	function rmdir($fp = '') {
		$udpnf = $this->resolve($fp);
		if ($this->issuper($udpnf[0])) {
			$stmt = $this->fslink->prepare("DELETE FROM `" . $udpnf[1] . "` WHERE path = ?");
			$stmt->execute(array($udpnf[4]));
		} else { 
			$stmt = $this->fslink->prepare("DELETE FROM `" . $udpnf[1] . "` WHERE path = ? AND owner = ?");
			$stmt->execute(array($udpnf[4],$udpnf[0]));
		}
		return true;
	}

	# copy a file.
	function cp($fp, $newfp, $ts=false) {
		$udpnffrom = $this->resolve($fp);
		$udpnfto = $this->resolve($newfp,$udpnffrom);
		if (!$ts) {$ts = date('Y-m-d H:i:s');}
		
		if (!$this->issuper($udpnffrom[0]) && $udpnffrom[0] !== $this->owner($udpnffrom[4])) {return false;} # security failure 
		if (!$this->issuper($udpnfto[0]) && $this->exists($udpnfto[4]) && $udpnfto[0] !== $this->owner($udpnfto[4])) {return false;} # security failure
		
		$stmt = $this->fslink->prepare("
			CREATE TEMPORARY TABLE tmptable_1 SELECT * FROM `" . $udpnffrom[1] . "` WHERE fullpath = ?;
			UPDATE tmptable_1 SET fullpath = ?, path = ?, filename = ?, owner = ?, modified = ?;
			REPLACE INTO `" . $udpnfto[1] . "` SELECT * FROM tmptable_1;
			DROP TEMPORARY TABLE IF EXISTS tmptable_1;
		");
		$stmt->execute(array($udpnffrom[4],$udpnfto[4], $udpnfto[2], $udpnfto[3], $udpnfto[0], $ts));
		return true;
	}

	# see if file exists.
	function exists($fp) {
		$udpnf = $this->resolve($fp);
		$stmt = $this->fslink->prepare("SELECT fullpath FROM `" . $udpnf[1] . "` WHERE fullpath = ? LIMIT 1");
		$stmt->execute(array($udpnf[4]));
		$result = $stmt->fetchAll();
		return 0 != count($result);
	}

	# check file ownership.
	function owner($fp) {
		$udpnf = $this->resolve($fp);
		$stmt = $this->fslink->prepare("SELECT owner FROM `" . $udpnf[1] . "` WHERE fullpath = ? LIMIT 1");
		$stmt->execute(array($udpnf[4]));
		$result = $stmt->fetchAll();
		if (0 == $stmt->rowCount()) {return false;}
		return $result[0]['owner'];
	}

	# set file ownership.
	function assign($fp, $user = false) {
		$udpnf = $this->resolve($fp);
		if ($user === false) {$user = $udpnf[0];} 
		$stmt = $this->fslink->prepare("UPDATE `" . $udpnf[1] . "` SET owner = ? WHERE fullpath = ? LIMIT 1");
		$stmt->execute(array($user,$udpnf[4]));
		return true;
	}

	# Display file for viewing in HTML
	function view($fn) {
		header('Content-Type:'.$this->extensiontab[$this->extension($fn)]);
		echo $this->read($fn);
		return true;
	}

	function upload($uploadname) {
		$filename = $this->tofile(basename($_FILES[$uploadname]["name"]));
		$this->write($filename, file_get_contents($_FILES[$uploadname]["tmp_name"]));
		return $filename;
	}

	# Sets header and echo's content for file download
	function download($fn) {
		$udpnf = $this->resolve($fn);
		$filecontent = $this->read($fn);
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="'. $udpnf[3] .'"');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . strlen($filecontent));
		echo ($filecontent);
		return true;
	}


	# load serialized PHP data file
	function load($fn) {
		return unserialize($this->read($fn));
	}

	# save serialized PHP data file
	function save($fn, $data) {
		return $this->write($fn, serialize($data));
	}

	# return extension part of a file
	function extension($fn) {
		$sep=strrpos($fn,".");
		if ($sep){ # has extension seperator
			return substr($fn,1+$sep);
		}
		return '';
	}

	# Extract a path information into an array of parts taking into account current defaults.
 	# array( USER, DRIVE/VOLUME, PATH, FILE, PATH+FILE )
	# second parameter is used to specify custom defaults.
	function resolve($fn,$defs=false) {
		if ($defs) {
			$user=isset($defs[0]) ? $defs[0] : $this->state['user'];
			$drive=isset($defs[1]) ? $defs[1] : $this->state['vol'];
			$path=isset($defs[2]) ? $defs[2] : $this->state['path'];
			$file=isset($defs[3]) ? $defs[3] : '';
		} else {
			$user=$this->state['user']; $drive=$this->state['vol']; $path=$this->state['path']; $file='';
		}
		if (0===strlen($fn)){return array($user, $drive, $path, $file, $path . $file);}
		$sep=strpos($fn,'@');
		if ($sep){ # has owner
			$user=substr($fn,0,$sep);
			$fn=substr($fn,1+$sep);
			if (0===strlen($fn)){return array($user, $drive, $path, $file, $path . $file);}
		}
		$sep=strpos($fn,":");
		if ($sep){ # has volume seperator
			$drive=substr($fn,0,$sep);
			if (!isset($this->state['volref'][$drive])) {
				trigger_error("Invalid volume selected.", E_USER_ERROR);
			}
			$fn=substr($fn,1+$sep);
			if (0===strlen($fn)){return array($user, $drive, $path, $file, $path . $file);}
		}
		if ("/" !== substr($fn, -1)) { # is file
			$sep=strrpos($fn,"/");
			if ($sep) {
				$file=substr($fn,1+$sep);
				$fn=substr($fn,0,1+$sep);
			} else {
				$file=$fn;
				$fn='';
			}
			if (0===strlen($fn)){return array($user, $drive, $path, $file, $path . $file);}
		}	
		if ("/" === substr($fn, 0, 1)) { # use complete path
			$path = $fn;
		} else { # incomplete path, use default
			$path .= $fn;
		}
		return array($user, $drive, $path, $file, $path . $file);
	}

	# detects/forces normal directory or file names
	# EXAMPLE: this-file-is-ok, this file is ok
	# ALLOWED: Letters, digits, space, underscore
	# NOT ALLOWED: <dot>, @, :, !, begining space, ending space, empty string, and other characters. 
	function okname($fn) {
		if (preg_match('|^[a-z0-9\040\-\_]+$|i', $fn) && substr($fn, 0, 1) <> ' ' && substr($fn, -1) <> ' ' && strlen($fn) != 0) {
			return true;
		} else { return false; }
	}
	function toname($fn) {
		$fn = trim($fn);
		$fn = preg_replace("|[^a-zA-Z0-9\040\-\_]|", "_", $fn);
		if (strlen($fn) == 0) {$fn = 'Untitled';}
		return $fn;
	}
	
	# detects/forces a file name with extension dot allowed.
	# EXAMPLE: this-file-is-ok.txt, this file is ok .txt, .this is ok too
	# ALLOWED: Letters, digits, space, dot 
	# NOT ALLOWED: @, :, !, begining space, ending space, empty string, and other characters. 
	function okfile($fn) {
		if (preg_match('|^[a-z0-9\040\-\_\.]+$|i', $fn) && substr($fn, 0, 1) <> ' ' && substr($fn, -1) <> ' ' && strlen($fn) != 0) {
			return true;
		} else { return false; }
	}
	function tofile($fn) {
		$fn = trim($fn);
		$fn = preg_replace("|[^a-zA-Z0-9\040\-\_\.]|", "_", $fn);
		if (strlen($fn) == 0) {$fn = 'Untitled';}
		return $fn;
	}

	# detects/forces a regular path.
	# EXAMPLE: this/path/is/ok/, this/path/file.isok, spaces in/dirs are fine/, could-just-be-a-file 
	# ALLOWED: Letters, digits, space, dot, slash
	# NOT ALLOWED: @, :, !, begining space, ending space, empty string, and other characters. 
	function okpath($fn) {
		if (preg_match('|^[a-z0-9\040\-\_.\/]+$|i', $fn) && substr($fn, 0, 1) <> ' ' && substr($fn, -1) <> ' ' && strlen($fn) != 0) {
			return true;
		} else { return false; }
	}
	function topath($fn) {
		$fn = trim($fn);
		$fn = preg_replace("|[^a-zA-Z0-9\040\-\_.\/]|", "_", $fn);
		if (strlen($fn) == 0) {$fn = 'Untitled';}
		return $fn;
	}

	# detects/forces a relative path.
	# EXAMPLE: this/path/is/ok/, this/path/file.isok, spaces in/dirs are fine/, could-just-be-a-file 
	# ALLOWED: Letters, digits, space, dot, slash
	# NOT ALLOWED: @, :, !, begining slash, begining space, ending space, empty string, and other characters. 
	function okrelative($fn) {
		if (preg_match('|^[a-z0-9\040\-\_.\/]+$|i', $fn) && substr($fn, 0, 1) <> ' ' && substr($fn, -1) <> ' ' && strlen($fn) != 0 && $fn[0] != '/') {
			return true;
		} else { return false; }
	}
	function torelative($fn) {
		$fn = trim($fn);
		$fn = preg_replace("|[^a-zA-Z0-9\040\-\_.\/]|", "_", $fn);
		while (strlen($fn) != 0 && $fn[0] === '/') { $fn = array_slice($fn,1); }
		if (strlen($fn) == 0) {$fn = 'Untitled';}
		return $fn;
	}

	# fdisk cmds:
	# dblist - returns list of databases
	# how - returns sql of creating a database
	# new - creates a database
	# vols - returns volumes in database
	# desc - returns table description of volume
	function fdisk($cmd = 'dblist', $arg1 = null) {
		if ($cmd == 'vols') {$cmd = 'volumes';}
		switch ($cmd) {
			case 'dblist':
				$sql = 'SHOW DATABASES'; # shows available disks
				$stmt = $this->fslink->prepare($sql);
				$stmt->execute();
				return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
				break;
			case 'how':
				$sql = 'SHOW CREATE DATABASE `' . $arg1 . '`'; # shows sql
				break;
			case 'new':
				$sql = 'CREATE DATABASE `' . $arg1 . '`';
				break;
			case 'volumes':
				$sql = 'SHOW TABLES'; # shows available disks
				$stmt = $this->fslink->prepare($sql);
				$stmt->execute();
				$tables = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
				# update volref (ensures table verification)
				foreach ($tables as $tbl) {
					$this->state['volref'][$tbl] = isset($this->state['volref'][$tbl]) ? $this->state['volref'][$tbl] : '/';
				}
				return $tables;
			case 'desc':
				$sql = 'DESCRIBE ' . $arg1; # describes a table
				break;
		}
		$stmt = $this->fslink->prepare($sql);
		$stmt->execute();
		return $stmt->fetchAll();
	}

	# Create a volume (aka disk, table)
	# format (NAME, FORMAT_TYPE, BLOCK)
	# FORMAT_TYPE should be single (multi not yet supported)
	# BLOCK can be any sql blob type: 
	# 	TINYBLOB (0-255 bytes)
	#	BLOB (0-65,535 bytes)
	#	MEDIUMBLOB (0-16,777,215 bytes)
	#	LONGBLOB (0-4,294,967,295 bytes)
	function format($name = null, $fmt = 'single', $block = 'blob', $show = false) {
		$sql = '';
		if ($show) {$sql .= 'SHOW ';}
		switch ($fmt) {
			case 'single':
				$sql = 'CREATE TABLE `' . $name . '` (' .
					'`fullpath` tinytext NOT NULL,' .
					'`path` tinytext NOT NULL,' .
					'`filename` tinytext NOT NULL,' .
					'`type` tinytext CHARACTER SET armscii8 NOT NULL,' .
					'`owner` tinytext CHARACTER SET armscii8 NOT NULL,' .
					'`data` ' . $block . ' NOT NULL,' .
					'`modified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,' .
					# '`nextblock` integer,' .
					'PRIMARY KEY (`fullpath`(128))' .
					') ENGINE=MyISAM DEFAULT CHARSET=ascii';
				break;
			case 'multi':
				$sql = 'CREATE TABLE `' . $name . '` (' .
					'`fullpath` tinytext NOT NULL,' .
					'`path` tinytext NOT NULL,' .
					'`filename` tinytext NOT NULL,' .
					'`type` tinytext CHARACTER SET armscii8 NOT NULL,' .
					'`owner` tinytext CHARACTER SET armscii8 NOT NULL,' .
					'`data` ' . $block . ' NOT NULL,' .
					'`modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,' .
					'`nextblock` integer,' .
					'PRIMARY KEY (`fullpath`(128))' .
					') ENGINE=MyISAM DEFAULT CHARSET=ascii';
				break;
		}
		$stmt = $this->fslink->prepare($sql);
		$stmt->execute();
		$rv = $stmt->fetchAll();
		$this->fdisk('volumes'); # updates state volume path reference table
		return $rv;
	}

	# NOT IMPLIMENTED
	function chkdisk() {
		$sql = 'CHECK TABLE test <options>'; # chkdisk function
		#	options = FOR UPGRADE|QUICK|FAST|MEDIUM|EXTENDED|CHANGED 
		$sql = 'REPAIR TABLE test <options>'; # one should backup first
		#	options = QUICK|EXTENDED|USE_FRM #FRM last resort
	}

	# NOT IMPLIMENTED	
	function defrag() {
		$sql = 'OPTIMIZE TABLE test'; # defrag 
	}

	# NOT IMPLIMENTED
	function backup() {
		$sql = "BACKUP TABLE test TO '/actual/path/to/backup/directory'";
		$sql = "RESTORE TABLE test FROM '/actual/path/to/backup/directory'";
	}

	# NOT IMPLIMENTED
	function set ($var = false , $value = false) {
		# probably good idea to prepend variables with "fsvar" to avoid collisions
		$sql = "set @foo = 'foo';";
		$sql = "select @foo;"; # returns table with value
	}

	# NOT IMPLIMENTED
	# mounting is automatic, but mount may be faster. 
	function mount($volume) {

	}
}

?>