<?php

# you must modify sqlefs_login.php for this example to work!
require_once 'sqlefs_login.php'; 
header('Content-Type: text/plain');
define('br', "\r\n");
define('hr', "===========================\r\n");

echo 'Creating file...' . br;
$efs->write('test.txt' , 'Some file contents.');
echo 'Copying file...' . br;
$efs->cp('test.txt', 'copy of test.txt');
echo 'Copying file again...' . br;
$efs->cp('test.txt', 'copy2 of test.txt');
echo hr;

echo 'Listing of: ' . $efs->cd() . br;
print_r(  $efs->ls()  );
echo hr;

echo 'Deleting test.txt...' . br;
$efs->rm('test.txt');
echo hr;

echo 'Listing of: ' . $efs->cd() . br;
print_r(  $efs->ls()  );
echo hr;

echo 'Deleting all files in folder...' . br;
$efs->rmdir();
echo hr;

echo 'Listing of: ' . $efs->cd() . br;
print_r(  $efs->ls()  );

?>