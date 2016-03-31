<?php

# BEFORE USING EXAMPLES YOU MUST HAVE THE FOLLOWING:
	# A DATABASE
	# A VOLUME (AKA SQLEFS TABLE / DISK)
	# YOUR SQL SERVER USERNAME
	# YOUR SQL SERVER PASSWORD
	# THIS FILE MODIFIED TO THOSE PARAMETERS!!!

# MAKE SURE THIS PATH IS CORRECT...
require '../sqlefs.class.php';
$efs = new efsclass();

# SET TO YOUR OWN database, username, and password!!!
$efs->connect('mysql:mysql:host=localhost;dbname=myefsdb name pass');

# SET C to your own volume name if different than C
$efs->disk('c');

# EXAMPLES WILL CREATE, DELETE, AND MODIFY FILES WITHIN THIS FOLDER:
$efs->cd('/Test_Files/');

?>