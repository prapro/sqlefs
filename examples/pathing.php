<?php

# you must modify sqlefs_login.php for this example to work!
require_once 'sqlefs_login.php'; 
header('Content-Type: text/plain');
define('br', "\r\n");
define('hr', "===========================\r\n");

# The filepath specification
# [owner@][volume:][/][directory/][file.ext]

# change user to bob...
$efs->cd('bob@');

# change volume FYI...
# $efs->cd('disk2:');

# go to root
$efs->cd('/'); 

# go into a subdirectory
$efs->cd('subdir/');

# cd ignores the file part
$efs->cd('/subdir/file.txt');

# we can combine these...

# see Sams files
$efs->ls('sam@');

# see Sams files in a subdirectory
$efs->ls('sam@subdir/');

# use resolve to break paths apart
echo 'resolve sam@subdir/' . br;
print_r(  $efs->resolve('sam@subdir/')  );
echo hr;

echo 'resolve sam@subdir/ without current path context...' . br;
print_r(  $efs->resolve('sam@subdir/', array('','','','','',''))  );
echo hr;

# you can use the state array to retrieve current defaults...
echo 'Current defaults take from state array:' . br; 
echo 'Volume: ' . $efs->state['vol'] . br;
echo 'User: ' . $efs->state['user'] . br;
echo 'Path: ' . $efs->state['path'] . br;


# Note that some commands that request arguments that are specifically NOT paths
# such as disk and assign. 
# disk('C') versus cd('C:')
# assign('file', 'bob') although assign('bob@file') is ok.


?>