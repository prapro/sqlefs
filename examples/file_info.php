<?php

# you must modify sqlefs_login.php for this example to work!
require_once 'sqlefs_login.php'; 
header('Content-Type: text/plain');
define('br', "\r\n");
define('hr', "===========================\r\n");

echo 'Creating some files...' . br;
$efs->write('test1.txt' , 'Some file contents.');
$efs->write('test2.txt' , 'Some file contents.');
echo hr;

echo 'See details on files in current path...' . br;
print_r(  $efs->details()  );
echo hr;

# info is actually the same as details, but returns false if no file is found
echo 'See details on a specific file.' . br;
print_r(  $efs->info('test1.txt')  );
echo hr;

echo 'See the owner.' . br;
print_r(  $efs->owner('test1.txt')  );
echo hr;

echo 'See if a file exists.' . br;
print_r(  $efs->exists('test1.txt')  );
echo hr;

?>