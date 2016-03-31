<?php

# you must modify sqlefs_login.php for this example to work!
require_once 'sqlefs_login.php'; 

if (isset($_FILES['myupload'])) {
	# save the file
	$filename = $efs->upload('myupload');
	# download it...
	$efs->download($filename);

	# NOTE:
	# ordinarily we would sanitize a user given $filename with
	# $efs->tofile($filename)
	# but $efs->upload sanitizes to file name for us.

	# delete it in case its big
	$efs->rm($filename);
	# exit
	exit;
}
?>
<html>
<head><head>
<body>
<h1>Upload and Download Example</h1>
<form role="form" method="post" enctype="multipart/form-data">
<input type="file" name="myupload">
</form>
</body>
</html>
