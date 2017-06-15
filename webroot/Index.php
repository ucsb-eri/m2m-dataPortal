<!DOCTYPE html>
<html>
<head>
<title>M2M data project website</title>
<link rel="stylesheet" href="css/m2m-dataportal.css?v=1.0">
</head>
<body>
<h1>M2M data project website</h1>
<?php
////////////////////////////////////////////////////////////////////////////////
define('DSN','sqlite:../var/m2m-dataportal.sqlite3');
require_once('inc/m2m-dataportal-class.php');

$dirID = ( isset($_GET['dirID'])) ? intval(stripslashes($_GET['dirID'])) : 0;

$m2m = new m2mDataPortal(DSN);
print $m2m->display($dirID);

////////////////////////////////////////////////////////////////////////////////
?>
</body>
</html>
