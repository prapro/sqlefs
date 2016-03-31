<?php

# you must modify sqlefs_login.php for this example to work!
require_once 'sqlefs_login.php';
header('Content-Type: text/plain');

$data = array(
	'time' => $_SERVER['REQUEST_TIME'],
	'ip' => $_SERVER['REMOTE_ADDR'],
	'agent' => $_SERVER['HTTP_USER_AGENT'],
);

# save server data
$efs->save('test.dat' , $data);

# load and print request data
$loaded_data = $efs->load('test.dat');
print_r($loaded_data);

$efs->rm('test.dat');

?>