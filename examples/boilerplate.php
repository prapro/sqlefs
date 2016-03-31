<?php

require 'sqlefs/sqlefs.php'; # Loads class and assigns efs object.
$efs->connect("mysql:host=localhost;dbname=myefsdb username password") # connect to myefsdb database.
$efs->cd("c:/"); # select the C volume table and root path.

?>