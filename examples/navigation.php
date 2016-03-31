<?php

# you must modify sqlefs_login.php for this example to work!
require_once 'sqlefs_login.php'; 
header('Content-Type: text/plain');
define('br', "\r\n");
define('hr', "===========================\r\n");

$start_dir = $efs->cd();

# set path to some new place
echo 'I went to: ' .  $efs->cd('lets/visit/some/new/sub/directory/')  . br;
echo hr;

# demonstrate up
while ($start_dir != $efs->cd()) {
	echo 'up to ' .  $efs->up()  . br;
}
echo hr;

# now we're back to start, lets see subdirectories
echo 'Listing of ' . $efs->cd() . br;
print_r(  $efs->subs()  );
echo br;
echo 'Since we did not write a file there, that path is not listed as a subdirectory' . br ;
echo hr;

# listing a non-existing path
$new_path = date("Y/m/d/H/i/s");
echo 'Listing contents of ' . $new_path . br;
print_r(  $efs->ls($new_path)  );
echo br;
echo 'Listing contents of non-existing path does not cause an error.' . br;
echo hr;


?>