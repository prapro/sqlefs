<?php

# you must modify sqlefs_login.php for this example to work!
require_once 'sqlefs_login.php'; 
define('br', '<br>');
define('hr', '<hr>');

echo 'Creating some files...' . br;
$efs->write('test1.txt' , 'Some file contents.');
$efs->write('test2.txt' , 'Some file contents.');
$efs->write('subdir1/testA.txt' , 'Some file contents.');
$efs->write('subdir2/testB.txt' , 'Some file contents.');
$efs->write('subdir2/deeper/testC.txt' , 'Some file contents.');
echo hr;

echo 'See files in ' . $efs->cd() . br;
print_r(  $efs->ls()  );
echo hr;

echo 'See subdirectories in ' . $efs->cd() . br;
print_r(  $efs->subs()  );
echo hr;

echo 'See files in subdir1/' . br;
print_r(  $efs->ls('subdir1/')  );
echo hr;

echo 'See details on files in subdir2/' . br;
print_r(  $efs->details('subdir2/')  );
echo hr;

echo 'See subdirectories in subdir2/' . br;
print_r(  $efs->subs('subdir2/')  );
echo hr;


# see more information using details 
echo 'Formatted file and directory listing...';
echo '<h3>Path: ' . $efs->cd() . '</h3>';
echo '<table border="1">';
$subs = $efs->subs();
foreach ($subs as $sd) {
	echo '<tr><td>' . $sd . '</td></tr>';
}
$files = $efs->details();
foreach ($files as $f) {
	echo '<tr><td>' . $f['filename'] . '</td>';
	echo '<td>' . $f['modified'] . '</td>';
	echo '<td>' . $f['owner'] . '</td>';
	echo '<td>' . $f['size'] . '</td></tr>';
}
echo '</table>';
echo '<hr>';


echo 'Deleting files...' . br;
$efs->rmdir();
$efs->rmdir('subdir1/');
$efs->rmdir('subdir2/');

?>