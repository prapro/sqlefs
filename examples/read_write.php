<?php

# you must modify sqlefs_login.php for this example to work!
require_once 'sqlefs_login.php'; 
header('Content-Type: text/plain');

$efs->write('test.txt' , 'Hello World');

echo $efs->read('test.txt');

?>