<?php 

# just incase we want to see errors. Careful!!!
ini_set('display_errors',E_ALL);
ini_set('display_startup_errors',E_ALL);
ini_set('error_reporting', E_ALL);
error_reporting(E_ALL);

require 'sqlefs.class.php';
$efs = new efsclass();

session_start();

$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : '';
$cmd = isset($_REQUEST['cmd']) ? $_REQUEST['cmd'] : '';
$do = isset($_REQUEST['do']) ? $_REQUEST['do'] : '';

$alert = '';
$msg = '';
$pre = '';
$post = '';
$pagecontent = '';
$clilines = 24;





########### HELPER FUNCTIONS

function session_setup() {
	global $efs, $clilines;
	$_SESSION['history'] = array();
	writeln("SQLEFS");
	writeln("Version: " . $efs->version);
	writeln($efs->website);
	writeln("======================");
	writeln('Type "help" for command list.');
	array_pad($_SESSION['history'], $clilines, '');
	$_SESSION['connection'] = '';
	$_SESSION['state'] = $efs->state;
	$_SESSION['lastpage'] = 'login';
}

function session_load() {
	global $efs, $page;
	if ($_SESSION['connection'] != '') {
		$efs->connect($_SESSION['connection']);
	}
	$efs->state = $_SESSION['state'];
	if ($page=='') {$page = $_SESSION['lastpage'];}
}

function session_save() {
	global $efs, $page;
	$_SESSION['state'] = $efs->state;
	$_SESSION['history'] = array_slice($_SESSION['history'], -40);
	if ($efs->state['db'] != '') { # if we have a db name add it to the connection if needed
		$pos = strpos ($efs->connection, ';dbname=');
		if ($pos === false) {
			$conn = explode (' ', $efs->connection);
			$efs->connection = $conn[0] . ';dbname=' . $efs->state['db'] . ' ' . $conn[1]. ' ' . $conn[2];
		}
	}
	$_SESSION['connection'] = $efs->connection;
	$_SESSION['lastpage'] = $page;
}

function write($txt) {
	$_SESSION['history'][count($_SESSION['history'])-1] .= $txt;
}
function writeln($txt) {
	$_SESSION['history'][] = $txt;
}
function formln($txt) {
	$_SESSION['history'][] = print_r($txt, true);
}

$helpdata = array(
#		'mount' => "Connect SQLEFS to the SQL database.\nExample:\n>> mount mysql:host=localhost;dbname=mydatabase mysqlusername mysqlpassword",
		'assign' => "Assign an owner to a file.\nUsage:\nassign FILE OWNER",
		'cat' => 'Display contents of specified file in console.',
		'cd' => "Shows current path specification, or changes to a specified user, drive, path.\nExample:\n>> cd joe@c:/myfolder",
		'cls' => 'Clear screen.',
		'cp' => 'Copy a file.\nUsage:\ncp SOURCE DESTINATION',
		'disk' => "Return or set current disk\nExample:\n>> disk C\nC\n>> disk\nC",
		'exists' => 'Test if a file exists.',
		'exit' => 'Exit session.',
		'extension' => 'Retrieve extension part of a specified file.',
		'fdisk' => "Create databases and volumes (tables) and display information about them.\Usage:\nfdisk [show|showsql|describe|create] [TABLE] [OPTIONS]\nExample:\n>> fdisk create C blob",
		'format' => "Create an SQLEFS filesystem on a volume.\Usage:\format [show|showsql|describe|create] [TABLE] [OPTIONS]\nExample:\n>> fdisk create C blob",
		'help' => 'Displays command list or information on a specified command.',
		'info' => "Return information on a file.\nUsage:\n>> info FILE",
		'issuper' => "Returns whether specified username is superuser\nUsage:\n>> issuper NAME",
		'ls' => 'Returns a listing of files in the current or specified directory.',
		'details' => 'Lists information for each file in current or specified directory, or specific file.',
		'owner' => 'Display owner of a specified file.',
		'resolve' => 'Show context of a given file specification .',
		'rm' => 'Delete a specified file.',
		'rmdir' => 'Delete all files in a directory.',
		'subs' => 'List subdirectories  in current or specified directory.',
#		'okname' => 'Check if given text is a valid user name.',
#		'toname' => 'Strip given text into a valid user name.',
#		'okfile' => 'Check if given text is a valid file name.',
#		'tofile' => 'Strip given text into a valid file name.',
#		'okpath' => 'Check if given text is a valid file path.',
#		'topath' => 'Strip given text into a valid file path.',
		'up' => 'Changes current path to parent directory.',
		'ver' => 'Prints SQLEFS version.',
);

function help($arg = 'help') {
	global $helpdata;
	if ($arg == 'help') {
		foreach($helpdata as $key => $val){
			writeln($key);
		}
		writeln("Usage:\nhelp [COMMAND]");
	} else {
		writeln($helpdata[$arg]);
	}
}


############# SETUP PAGE VIEWS

$pageviews = array (
## LOGIN ##
'login'=>
'
 <form role="form" method="post">
  <input type="hidden" class="form-control" id="do" name="do" value="login">
  <div class="form-group">
    <label for="user">SQL Server Username:</label>
    <input type="text" class="form-control" id="user" name="user">
  </div>
  <div class="form-group">
    <label for="pwd">SQL Server Password:</label>
    <input type="password" class="form-control" id="pwd" name="pwd">
  </div>
   <div class="form-group">
   <label for="db">Optional SQL Database:</label>
    <input type="text" class="form-control" id="db" name="db" placeholder="">
  </div>
  <div class="form-group">
    <label for="host">Optional SQL Server Host:</label>
    <input type="text" class="form-control" id="host" name="host" placeholder="localhost">
  </div>
  <button type="submit" class="btn btn-primary">Submit</button>
</form>
<hr>
 <div class="panel-group">
  <div class="panel panel-default">
    <div class="panel-heading">
      <h4 class="panel-title">
        <a data-toggle="collapse" href="#collapse1">Common Issues</a>
      </h4>
    </div>
    <div id="collapse1" class="panel-collapse collapse">
      <div class="panel-body">
<ol>
<li>You may have to use the hosting service\'s control panel to create or enable your database.</li>
<li>Your SQL Server username and password may not be than your hosting username and password.</li>
</ol>
</div>
    </div>
  </div>
</div>
',

'database'=>'
 <div class="panel-group">
  <div class="panel panel-default">
    <div class="panel-heading">
      <h4 class="panel-title">
        <a data-toggle="collapse" href="#collapse1">Create Database</a>
      </h4>
    </div>
    <div id="collapse1" class="panel-collapse collapse">
      <div class="panel-body">
 <form role="form" method="post">
  <input type="hidden" class="form-control" id="do" name="do" value="newdb">
  <div class="form-group">
    <label for="db">Database Name:</label>
    <input type="text" class="form-control" id="db" name="db" placeholder="">
  </div>
  <button type="submit" class="btn btn-default">Submit</button>
</form>
</div>
    </div>
  </div>
</div>

<div class="panel-group">
  <div class="panel panel-default">
    <div class="panel-heading">
      <h4 class="panel-title">
        <a data-toggle="collapse" href="#collapsedbissues">Common Issues</a>
      </h4>
    </div>
    <div id="collapsedbissues" class="panel-collapse collapse">
      <div class="panel-body">
<ol>
<li>If you do not have a database listed, you will need to create one.</li>
<li>You may not have permission to create databases. In this case try using your servers control panel to create one.</li>
</ol>
</div>
    </div>
  </div>
</div>
',

## DISKMAN ##
'diskman'=>'
 <div class="panel-group">
  <div class="panel panel-default">
    <div class="panel-heading">
      <h4 class="panel-title">
        <a data-toggle="collapse" href="#collapse1">Create Disk</a>
      </h4>
    </div>
    <div id="collapse1" class="panel-collapse collapse">
      <div class="panel-body">
 <form role="form" method="post">
  <input type="hidden" class="form-control" id="do" name="do" value="newvol">
  <div class="form-group">
    <label for="name">Disk Name:</label>
    <input type="text" class="form-control" id="name" name="name" placeholder="">
  </div>
 <div class="form-group">
  <label for="sel1">Format:</label>
  <select class="form-control" id="sel1" name="format">
    <option value="single">Single Block</option>
    <option disabled value="multi">Multi Block</option>
  </select>
</div>
 <div class="form-group">
  <label for="sel2">Block Size:</label>
  <select class="form-control" id="sel2" name="block">
    <option value="tinyblob">0-255 bytes (tinyblob)</option>
    <option value="blob">0-65,535 bytes (blob)</option>
    <option value="mediumblob">0-16,777,215 bytes (mediumblob)</option>
    <option value="longblob">0-4,294,967,295 bytes (longblob)</option>
  </select>
</div>
  <button type="submit" class="btn btn-default">Submit</button>
</form>
</div>
    </div>
  </div>
</div>

<div class="panel-group">
  <div class="panel panel-default">
    <div class="panel-heading">
      <h4 class="panel-title">
        <a data-toggle="collapse" href="#collapsediskissues">Common Issues</a>
      </h4>
    </div>
    <div id="collapsediskissues" class="panel-collapse collapse">
      <div class="panel-body">
<ol>
<li>If you do not have a volume listed, you will need to create one.</li>
<li>Volumes are database tables using the SQLEFS format. Currently non-SQLEFS tables will show up too, so make sure to select one created with SQLEFS.
<li>In some cases, you may not have permission to create tables, or your server limits the number of tables you can create.</li>
</ol>
</div>
    </div>
  </div>
</div>
',

'fileman'=>'
<form role="form" method="post">
 <div class="btn-group btn-group-justified">
  <div class="btn-group"><button type="button" class="btn btn-primary" data-toggle="modal" data-target="#popupnewfile">+ file</button></div>
  <div class="btn-group"><button type="button" class="btn btn-primary" data-toggle="modal" data-target="#popupnewfolder">+ folder</button></div>
  <div class="btn-group"><button type="button" class="btn btn-primary" data-toggle="modal" data-target="#popupupload">Upload</button></div>
  <div class="btn-group"><button type="button" class="btn btn-primary" id="filedownload">Download</button></div>
  <div class="btn-group"><button type="button" class="btn btn-primary" id="fileview">View</button></div>

  <div class="btn-group"><button type="button" class="btn btn-primary" data-toggle="modal" data-target="#popupcopy">Copy</button></div>
  <div class="btn-group"><button type="button" class="btn btn-primary" data-toggle="modal" data-target="#popupowner">Owner</button></div>
  <div class="btn-group"><button type="button" class="btn btn-danger" data-toggle="modal" data-target="#popupdelete" id="filedelete">Delete</button></div>

</div>
</form>

<div id="popupnewfile" class="modal fade" role="dialog">
  <div class="modal-dialog">
<form role="form" method="post">
    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Create Emtpy file</h4>
      </div>
      <div class="modal-body">
  <div class="form-group">
    <label for="newfilename">Enter file name:</label>
    <input type="text" class="form-control" id="newfilename" name="name" value="">
  </div>
<ul>
<li>Create a file under the user Joe: <code>Joe@file.txt</code></li>
<li>Create a file under the a new or existing folder: <code>some/sub/folder/file.txt</code></li>
<li>Create a file under an absolute path: <code>/root/path/file.txt</code></li>
</ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-default" name="do" value="newfile">Create</button>
     </div>
    </div>
</form>
  </div>
</div>

<div id="popupnewfolder" class="modal fade" role="dialog">
  <div class="modal-dialog">
<form role="form" method="post">
    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Create Emtpy file</h4>
      </div>
      <div class="modal-body">
  <div class="form-group">
    <label for="newfoldername">Enter folder name:</label>
    <input type="text" class="form-control" id="newfoldername" name="name" value="">
  </div>
<ul>
<li>Use a trailing slash: path/</li>
<li>You can make your path off root: /path/from/root/</li>
<li>We really are not creating a folder, just merely navigating to the specified path.</li>
</ul>
<p>
Remember: There is no need to create folders in SQLEFS, but it is useful to navigate to empty paths in order to create files. The path is only stored if a file is written to it. 
</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-default" name="do" value="newfolder">Create</button>
     </div>
    </div>
</form>
  </div>
</div>


<div id="popupupload" class="modal fade" role="dialog">
  <div class="modal-dialog">
<form role="form" method="post" enctype="multipart/form-data">
    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Upload file</h4>
      </div>
      <div class="modal-body">
  <div class="form-group">
    <label for="fileupload">Select file for upload:</label>
    <input type="file" class="form-control" id="fileupload" name="fileupload">
    <div style="height: 30"></div>
  </div>
<p>
The single file system allows files only up to its max block size.
</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-default" name="do" value="upload">Upload</button>
     </div>
    </div>
</form>
  </div>
</div>

<div id="popupcopy" class="modal fade" role="dialog">
  <div class="modal-dialog">
<form role="form" method="post">
<input type="hidden" class="form-control" id="filecopysrcfile" name="srcfile">
    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Copy File</h4>
      </div>
      <div class="modal-body">
  <div class="form-group">
    <label for="copyname">Enter new file name:</label>
    <input type="text" class="form-control" id="copyname" name="dstfile">
  </div>
<ul>
<li>Relative path: <code>sub/folder/filecopy.txt</code></li>
<li>Absolute path: <code>/off/root/filecopy.txt</code></li>
<li>Absolute path in another volume: <code>c:/in/another/volume/filecopy.txt</code></li>
<li>Set different user: <code>joe@filecopy.txt</code></li>
<li>Combination: <code>joe@c:/sub/folder/filecopy.txt</code></li>
</ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-default" name="do" value="copy" id="filecopy">Copy</button>
     </div>
    </div>
</form>
  </div>
</div>

<div id="popupowner" class="modal fade" role="dialog">
  <div class="modal-dialog">
<form role="form" method="post">
<input type="hidden" class="form-control" id="fileownerfile" name="file">
    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Assign Owner</h4>
      </div>
      <div class="modal-body">
  <div class="form-group">
    <label for="copyname">Enter the owner name:</label>
    <input type="text" class="form-control" id="copyname" name="owner">
  </div>
<ul>
<li>User names may use letters, digits, spaces, and underscores.</li>
<li>End super users with an "!": <code>superuser!</code></li>
<li>You may use any name. SQLEFS does not maintain a user list.</li>
</ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-default" name="do" value="owner" id="fileowner">Assign</button>
     </div>
    </div>
</form>
  </div>
</div>

<div id="popupdelete" class="modal fade" role="dialog">
  <div class="modal-dialog">
<form role="form" method="post">
    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Delete File</h4>
      </div>
      <div class="modal-body">
  <div class="form-group">
    <label for="copyname">Delete:</label>
    <input type="text" class="form-control" id="filedeletefile" name="file" readonly>
  </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-danger" name="do" value="delete">Confirm</button>
     </div>
    </div>
</form>
  </div>
</div>
',

'console'=>'
<hr>
 <div class="panel-group">
  <div class="panel panel-default">
    <div class="panel-heading">
      <h4 class="panel-title">
        <a data-toggle="collapse" href="#collapse1">Common Issues</a>
      </h4>
    </div>
    <div id="collapse1" class="panel-collapse collapse">
      <div class="panel-body">
<ol>
<li>First thing to do is to select your volume with: <code>cd myefsdisk:</code> or <code>disk myefsdisk:</code></li>
<li>SQLEFS uses <code>ls</code> instead of the dir command as in DOS (dir is taken by PHP).</li>
<li>For programming optimization <code>ls</code> only displays files in a directory! Use <code>subs</code> to see sub-directories.</li>
<li>Directories are automatic, therefore there are no commands for creating or deleting them.</li>
<li>There are no "." or ".." commands or referencing. Use <code>up</code> to go up.</li>
<li>Paths work like this: [username@][drive:][/][folder/][file.extension]</li>
<li>Not all SQLEFS commands have been put into the console.</li>
</ol>
</div>
    </div>
  </div>
</div>
',

'about'=>'
<h1>SQLEFS</h1>
<div class="well">
<p>Created by: Ryan Cole</p>
<p>Website: practicalproductivity.com/sqlefs</p>
<p>Email: <a href="mailto:ryan@practicalproductivity.com">ryan@practicalproductivity.com</a></p>
<p>License: MIT</p>
</div>
'
);




$menuviews = array (

'login'=>'
      <li class="active"><a href="admin.php?page=login">Login</a></li>
      <li><a href="admin.php?page=database">Database</a></li>
      <li><a href="admin.php?page=diskman">Disk Manager</a></li>
      <li><a href="admin.php?page=fileman">File Manager</a></li>
      <li><a href="admin.php?page=console">Console</a></li>
      <li><a href="admin.php?page=about">About</a></li>
',

'database'=>'
      <li><a href="admin.php?page=login">Login</a></li>
      <li class="active"><a href="admin.php?page=database">Database</a></li>
      <li><a href="admin.php?page=diskman">Disk Manager</a></li>
      <li><a href="admin.php?page=fileman">File Manager</a></li>
      <li><a href="admin.php?page=console">Console</a></li>
      <li><a href="admin.php?page=about">About</a></li>
',

'diskman'=>'
      <li><a href="admin.php?page=login">Login</a></li>
      <li><a href="admin.php?page=database">Database</a></li>
      <li class="active"><a href="admin.php?page=diskman">Disk Manager</a></li>
      <li><a href="admin.php?page=fileman">File Manager</a></li>
      <li><a href="admin.php?page=console">Console</a></li>
      <li><a href="admin.php?page=about">About</a></li>
',

'fileman'=>'
      <li><a href="admin.php?page=login">Login</a></li>
      <li><a href="admin.php?page=database">Database</a></li>
      <li><a href="admin.php?page=diskman">Disk Manager</a></li>
      <li class="active"><a href="admin.php?page=fileman">File Manager</a></li>
      <li><a href="admin.php?page=console">Console</a></li>
      <li><a href="admin.php?page=about">About</a></li>
',

'console'=>'
      <li><a href="admin.php?page=login">Login</a></li>
      <li><a href="admin.php?page=database">Database</a></li>
      <li><a href="admin.php?page=diskman">Disk Manager</a></li>
      <li><a href="admin.php?page=fileman">File Manager</a></li>
      <li class="active"><a href="admin.php?page=console">Console</a></li>
      <li><a href="admin.php?page=about">About</a></li>
',

'about'=>'
      <li><a href="admin.php?page=login">Login</a></li>
      <li><a href="admin.php?page=database">Database</a></li>
      <li><a href="admin.php?page=diskman">Disk Manager</a></li>
      <li><a href="admin.php?page=fileman">File Manager</a></li>
      <li><a href="admin.php?page=console">Console</a></li>
      <li class="active"><a href="admin.php?page=about">About</a></li>
'
);


############# PAGE PRE-PROCESSOR

if ($do == 'logout') {
		session_unset();
		echo '<html><head><meta http-equiv="refresh" content="0;URL=admin.php"></head><body></body></html>';
		exit;
}


############# LOAD SESSIONS

if (!isset($_SESSION['connection'])) {session_setup();}

session_load();


############# PAGE PROCESSOR
# this is where the "do" commands are processed
# handles everything but console commands
# and logout, which is handled in the page pre-processor section.

try {
  switch ($do) {
	case '': 
		#$msg = 'nothing';
		break;
	case 'login':
		$dbm = 'mysql';
		$host = $_REQUEST['host'] == '' ? 'localhost' : $_REQUEST['host'];  
		$db = $_REQUEST['db'] == '' ? null : $_REQUEST['db'];
		$efs->connect($db, $_REQUEST['user'], $_REQUEST['pwd'], $host, $dbm);
		break;
	case 'logout':
		session_setup();
		break;
	case 'db':
		$efs->db($_REQUEST['db']);
		break;
	case 'newdb':
		$efs->fdisk('new', $_REQUEST['db']);
		break;
	case 'newvol':
		$efs->format($_REQUEST['name'], $_REQUEST['format'], $_REQUEST['block']);
		break;
	case 'go':
		$efs->cd($_REQUEST['path']);
		break;
	case 'up':
		if ( '/' != $efs->state['path'] ) { # not root
			$efs->up();
		} else { #root
			$efs->state['vol'] = '';
			$efs->cd();
		}
		break;
	case 'refresh':
		break;
	case 'newfile':
		$efs->write($_REQUEST['name'], '');
		break;
	case 'newfolder':
		$efs->cd($_REQUEST['name']);
		break;
	case 'click':
		# figure out if clicked was file, dir, or volume
		$sel = $_REQUEST['filebox'];
		switch (substr($sel, -1)) {
			case ':':
				$efs->cd($sel);
				break;
			case '/':
				$efs->cd($sel);
				break;
#			default:
#				break;
		}
		break;
	case 'upload':
		$efs->upload("fileupload");
		break;
	case 'download':
		$efs->download($_REQUEST['file']);
		exit;
		break;
	case 'view':
		$efs->view($_REQUEST['file']);
		exit;
		break;
	case 'copy':
		$efs->cp($_REQUEST['srcfile'], $_REQUEST['dstfile']);
		break;
	case 'owner':
		$efs->assign($_REQUEST['file'], $_REQUEST['owner']);
		break;
	case 'delete':
		$efs->rm($_REQUEST['file']);
		break;
  }

} catch (Exception $e) {
	# $alert = $cmd . ' failed.'; 
	$alert = $e->getMessage();
}


############ CONSOLE OPERATIONS
# process console commands
# $cmd[0] is the command (first word)
# $cmd[1] is the first argument, etc

# split up console command if given one
if (isset($_REQUEST['cmd'])) {
	$cmd = explode(' ', $_REQUEST['cmd']);
} else {
	$cmd = array(false);
}

# if given a console command, write it out. 
if ($cmd[0] !== false) {
	writeln($efs->state['user'] . '@' . $efs->state['vol'] . ':> ' . $_REQUEST['cmd']);
}
try {
	switch ($cmd[0]) {
		case false: 
			break;
		case 'ver': // display version
			writeln($efs->version);
			break;
		case 'exit':
			session_unset();
			session_start();
			session_setup();
			break;
		case 'cls':
			$_SESSION['history'] = array();
			break;
		case 'disk':
			if (isset($cmd[1])) {
				writeln($efs->disk($cmd[1]));
			} else {
				writeln($efs->disk());
			}
			break;
		case 'info':
			formln($efs->info($cmd[1]));
			break;
		case 'issuper':
			formln($efs->issuper());
			break;
		case 'ls':
			if (isset($cmd[1])) {
				$result = $efs->ls($cmd[1]);
			} else {
				$result = $efs->ls();
			}
			foreach($result as $fi) {writeln($fi);}
			break;
		case 'subs':
			if (isset($cmd[1])) {
				$result = $efs->subs($cmd[1]);
			} else {
				$result = $efs->subs();
			}
			foreach($result as $fi) {writeln($fi[0]);}
			break;
		case 'details':
			if (isset($cmd[1])) {
				$result = $efs->details($cmd[1]);
			} else {
				$result = $efs->details();
			}
			writeln('filename    modified    type    owner    size');
			foreach($result as $dat) {
				writeln($dat[0] . '    ' . $dat[1] . '    ' . $dat[2] . '    ' . $dat[3] . '    ' . $dat[4]);
			}
			break;
		case 'cd':
			if (isset($cmd[1])) {
				writeln($efs->cd($cmd[1]));
			} else {
				writeln($efs->cd());
			}
			break;
		case 'read':
			writeln($efs->read($cmd[1]));
			break;
		case 'cat':
			writeln($efs->read($cmd[1]));
			break;
		case 'rm':
			formln($efs->rm($cmd[1]));
			break;
		case 'rmdir':
			if (isset($cmd[1])) {
				formln($efs->rmdir($cmd[1]));
			} else {
				formln($efs->rmdir());
			}
			break;
		case 'cp':
			if (isset($cmd[3])) {
				formln($efs->cp($cmd[1],$cmd[2],$cmd[3]));
			} else {
				formln($efs->cp($cmd[1],$cmd[2]));
			}
			break;
		case 'owner':
			writeln($efs->owner($cmd[1]));
			break;
		case 'assign':
			if (isset($cmd[2])) {
				formln($efs->assign($cmd[1],$cmd[2]));
			} else {
				writeln($efs->assign($cmd[1]));
			}
			break;
		case 'extension':
			writeln($efs->extension($cmd[1]));
			break;
		case 'resolve':
			if (isset($cmd[2])) {
				formln($efs->resolve($cmd[1],$cmd[2]));
			} else {
				formln($efs->resolve($cmd[1]));
			}
			break;
		case 'okname':
			formln($efs->okname($cmd[1]));
			break;
		case 'toname':
			writeln($efs->toname($cmd[1]));
			break;
		case 'okfile':
			formln($efs->okfile($cmd[1]));
			break;
		case 'tofile':
			writeln($efs->tofile($cmd[1]));
			break;
		case 'okpath':
			formln($efs->okpath($cmd[1]));
			break;
		case 'topath':
			writeln($efs->topath($cmd[1]));
			break;
		case 'up':
			writeln($efs->up());
			break;
		case 'format':
			if (isset($cmd[3])) {
				formln($efs->format($cmd[1],$cmd[2],$cmd[3]));
			} else if (isset($cmd[2])) {
				formln($efs->format($cmd[1],$cmd[2]));
			} else if (isset($cmd[1])) {
				formln($efs->format($cmd[1]));
			} else {writeln('format must include name parameter.');}
			break;
		case 'fdisk':
			formln($efs->fdisk($cmd[1]));
			break;
		case 'help':
			if (isset($cmd[1])) {
				help($cmd[1]);
			} else {
				help();
			}
			break;
		case '':
			break;
		default:
			writeln('Command not understood. Try "help".');
	}
} catch (Exception $e) {
	writeln($e->getMessage());
}

session_save();

############ OUTGOING PAGE HANDLING

if ($_SESSION['connection'] === '') {
	$page = 'login';
	$pagecontent = $pageviews[$page];
} else { # handle as logged in

	if ($page == 'login') {
		$pagecontent = $pageviews[$page];
		$msg = 'You are mounted to: ' . $_SESSION['connection'];
		$pagecontent = '
	 <form role="form" method="post">
	<button type="submit" class="btn btn-info" name="do" value="logout">Logout</button>
	</form>
	';
	}

	if ($efs->state['db'] == '') { # no db selected
		$page = 'database';
	} else { # these pages need database connection
		if ($page == 'diskman') {
			$pagecontent = $pageviews[$page];
			$pre = '<h2>Volumes in ' . $efs->state['db'] . ':</h2>';
		
			foreach ($efs->fdisk('vols') as $rec) {
				if ($rec != $efs->state['vol']) {
					$pre .= '<div class="panel-group"><div class="panel panel-default"><div class="panel-heading">';
					$pre .= '<h4 class="panel-title"><a data-toggle="collapse" href="#collapse_' . $rec . '">' . $rec; # heading
					$pre .= '</a></h4></div><div id="collapse_' . $rec . '" class="panel-collapse collapse"><div class="panel-body"><pre>';
					$desc = $efs->fdisk('desc', $rec);
					$pre .= '<div class="table-responsive"><table class="table">';
		  			foreach ($desc as $d) {
						$pre .= '<tr>';
						for ($i = 0; $i < 5; $i++) {
							$pre .= '<td>' . $d[$i] . '</td>';
						}
						$pre .= '</tr>';
					}
					$pre .= '</table></div>';
		
					$pre .= '</pre></div><div class="panel-footer"> --- </div></div></div></div>'; #footer
				} else {
					$pre .= '<div class="form-group"><input type="submit" class="btn btn-primary disabled" value="' . $rec . '"></input></div>';
				}
			}
			$pre .= '</form>';
		}
	
		if ($page == 'fileman') {
			$pagecontent = $pageviews[$page];
			$post .= '
		 <div class="well"><form class="form-inline" role="form">
		    <input type="path" class="form-control" id="path" name="path" value="' . $efs->cd() . '" style="width:70%">
		 <button type="submit" class="btn btn-default" name="do" value="go">Go</button>
		  <button type="submit" class="btn btn-default" name="do" value="up">Up</button>
		  <button type="submit" class="btn btn-default" name="do" value="refresh">Refresh</button>
		</form>
		<form role="form" id="fileboxform">
		<input type="hidden" id="fileaction" name="do" value="">
		<input type="hidden" id="actioninfo" name="info" value="">
		<select class="form-control" id="filebox" name="filebox" size="12">
		';
		
			if ($efs->state['vol'] == '') {
				$vols = $efs->fdisk('vols');
				foreach ($vols as $v) {
					$post .= '<option value="' . $v . ':">';
					$post .= $v;
					$post .= ':</option>';
				}
			} else { 
				$dirs = $efs->subs();
				foreach ($dirs as $d) {
					$post .= '<option value="' . $d[0] . '">';
					$post .=  $d[0];
					$post .= '</option>';
				}
				$files = $efs->details();
				foreach ($files as $f) {
					$post .= '<option value="' . $f['filename'] . '">';
					$post .=  $f['filename'] . ' . . . ';
					$post .= $f['modified'] . ' ';
					$post .= $f['owner'] . ' ';
					$post .= '</option>';
				}
			}
			$post .= '</select></form></div>';
		}
		
		if ($page=='console') {
			$pagecontent = $pageviews[$page];
			$_SESSION['history'] = array_slice($_SESSION['history'], -$clilines);
			$pre .= '<div class="well cli"><pre class="cli" id="clihistory">';
			foreach ($_SESSION["history"] as $ln) {$pre .= $ln . "\n";}  
			$pre .= '</pre><form method="post">';
			$pre .= $efs->state['user'] . '@' . $efs->state['vol'] . ':> '; 
			$pre .= '<input type="text" id="efscmd" name="cmd" autofocus class="cli" style="width: 70%"></input></form></div>';
		}
	}

	if ($page == 'database') {
		$pagecontent = $pageviews[$page];
		$pre = '<h2>Select database to use:</h2>';
	
		foreach ($efs->fdisk('dblist') as $rec) {
			if ($rec != $efs->state['db']) {
				$pre .= '<form role="form" method="post">';
				$pre .= '<input type="hidden" class="form-control" name="do" value="db">';
				$pre .= '<input type="hidden" class="form-control" name="db" value="' . $rec . '">';
				$pre .= '<div class="form-group"><input type="submit" class="btn btn-primary" value="' . $rec . '"></input></div>';
				$pre .= '</form>';
			} else {
				$pre .= '<div class="form-group"><input type="submit" class="btn btn-primary disabled" value="' . $rec . '"></input></div>';
			}
		}
		$pre .= '</form>';
	}
}

if ($page == 'about') {
	$pagecontent = $pageviews[$page];
	$post .= '<p>You are using version: <span class=".text-info">' . $efs->version . '</span></p>';
	$post .= '<iframe src="http://practicalproductivity.com/sqlefs/version.php?ver=' . $efs->version . '" height="50" width="300" frameborder="0"></iframe></p>';
}

############ OUTGOING MESSAGE FORMATTING

if ($alert<>'') {
	$alert = '<div class="alert alert-warning"><strong>Oops!</strong> ' . $alert . '</div>';
}

if ($msg<>'') {
	$msg = '<div class="alert alert-success"><strong>Success!</strong> ' . $msg . '</div>';
}

################# END OF PHP MAIN

?><!DOCTYPE html>
<html lang="en">
<head>
  <title>SQLEFS Admin</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="http://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.0/jquery.min.js"></script>
  <script src="http://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>
<script>
$(document).ready(function(){
	$('[data-toggle="popover"]').popover();

	$('#filebox').on('dblclick', function() {
		$("#fileaction").val('click');
		$('form#fileboxform').submit();
  	});
	$('#fileview').on('click', function() {
		var filebox = $("#filebox option:selected");
		var file = filebox.val();
		var href = window.location.href;
		window.open(href +'&do=view&file=' + file);
		return true;
  	});
	$('#filedownload').on('click', function() {
		var filebox = $("#filebox option:selected");
		var file = filebox.val();
		var href = window.location.href;
		window.open(href +'&do=download&file=' + file);
		return true;
  	});
	$('#filecopy').on('click', function() {
		var filebox = $("#filebox option:selected");
		var file = filebox.val();
		var srcfile = $("#filecopysrcfile");
		srcfile.val(file);
		return true;
  	});
	$('#fileowner').on('click', function() {
		var filebox = $("#filebox option:selected");
		var file = filebox.val();
		var formfile = $("#fileownerfile");
		formfile.val(file);
		return true;
  	});
	$('#filedelete').on('click', function() {
		var filebox = $("#filebox option:selected");
		var file = filebox.val();
		var formfile = $("#filedeletefile");
		formfile.val(file);
		return true;
  	});
	$('#clihistory').on('click', function() {
		$("#efscmd").focus();
		return false;
  	});

});


</script>
<style>
	.cli {
		background-color: #000;
		color: #FFF;
		font-family: Monospace;
		font-weight: bold;
		font-size: 1em;
		border-width: 0px;
	}
	#clihistory {
		margin: 0px;
		padding: 0px;
	}
</style>
</head>
<body>
<nav class="navbar navbar-inverse navbar-fixed-top">
  <div class="container-fluid">
    <div class="navbar-header">
      <a class="navbar-brand" href="#">SQLEFS Admin</a>
    </div>
    <ul class="nav navbar-nav">
<?php echo $menuviews[$page]; ?>
    </ul>
  </div>
</nav>
<div class="container" style="margin-top: 60px;">
<?php echo $alert . $msg .  $pre . $pagecontent . $post; ?>
</div>
<hr>

</body>
</html>