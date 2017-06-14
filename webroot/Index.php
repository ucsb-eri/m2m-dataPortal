<!DOCTYPE html>
<html>
<head>
<title>M2M data project website</title>
</head>
<body>
<h1>M2M data project website</h1>
<?php
// this requires the AJM phplib install
require_once('inc/dbhManagementCore.php');
require_once('inc/Parsedown.php');
define('DSN','sqlite:../var/m2m-dataportal.sqlite3');

class m2mDataPortalDb extends dbMgmtCore {
    // hollow extended class, probably won't need to build this out, but here
    // just in case.
}
class m2mDataPortal {
    function __construct($dsn){
        $this->dbh = new m2mDataPortalDb('m2m',$dsn,"M2M Data Portal Database");
    }
    function print_pre($o,$label){
        print "<pre>";
        print_r($o);
        print "</pre>";
    }
    function display($dirID){
        $b = '';
        //$b .= "Hey There ... Again - Displaying: $dirID<br>\n";
        //$b .= "<a href=\"?dirID=1\">Link 1</a><br>\n";
        //$b .= "<a href=\"?dirID=2\">Link 2</a><br>\n";
        //$b .= "<a href=\"?dirID=yogurt\">Link Yogurt</a><br>\n";

        // want to provide a link to the parent dirID
        $up = $this->dbh->fetchValNew("SELECT dparent FROM dirs WHERE did = ?;",array($dirID));
        $b .= "<a href=\"?dirID=$up\">Up a directory</a><br>\n";

        // list directories
        $h = $this->dbh->getKeyedHash('dname',"SELECT * FROM dirs WHERE dparent = ?;",array($dirID));
        //$this->print_pre($h,"Hash");
        foreach($h as $k => $v){
            $b .= "dir: $k, v={$v['did']} - <a href=\"?dirID={$v['did']}\">$k</a><br>\n";
        }

        // list files
        // pull out README.md and display at top
        $h = $this->dbh->getKeyedHash('fname',"SELECT * FROM files LEFT JOIN dirs on f_did = did WHERE f_did = ?;",array($dirID));
        //$this->print_pre($h,"Hash");
        foreach($h as $k => $v){
            $url = ()
            $b .= "file: $k, v={$v['fid']} - <a href=\"?dirID={$v['fid']}\">$k - {$v['fname']}</a> -- <a href=\"data/{$v['fpath']}\">{$v['fpath']}</a><br>\n";
        }
        return $b;
    }
}

$dirID = ( isset($_GET['dirID'])) ? intval(stripslashes($_GET['dirID'])) : 0;

$m2m = new m2mDataPortal(DSN);
print $m2m->display($dirID);

?>
</body>
</html>
