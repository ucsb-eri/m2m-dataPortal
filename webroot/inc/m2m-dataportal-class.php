<?php
// this requires the AJM phplib install
require_once('inc/dbhManagementCore.php');
require_once('inc/Parsedown.php');

////////////////////////////////////////////////////////////////////////////////
class m2mDataPortalDb extends dbMgmtCore {
    // hollow extended class, probably won't need to build this out, but here
    // just in case.
}
////////////////////////////////////////////////////////////////////////////////
class m2mDataPortal {
    ////////////////////////////////////////////////////////////////////////////
    function __construct($dsn){
        $this->dbh = new m2mDataPortalDb('m2m',$dsn,"M2M Data Portal Database");
    }
    ////////////////////////////////////////////////////////////////////////////
    function print_pre($o,$label){
        print "<pre>";
        print_r($o);
        print "</pre>";
    }
    ////////////////////////////////////////////////////////////////////////////
    function generateBreadCrumb($dirID){
        $parID = $dirID;
        $b = '';
        $a = array();
        // recursively get the parent till parent == '' or 0
        while( ! ($parID == '' || $parID == '0' )){
            $parName = $this->dbh->fetchValNew("SELECT dname FROM dirs WHERE did = ?;",array($parID));
            //$label = ($parName == '') ? "TopLevel" : "$parName";
            $a[] = "<a href=\"?dirID=$parID\">$parName</a>";
            $parID = $this->dbh->fetchValNew("SELECT dparent FROM dirs WHERE did = ?;",array($parID));
        }
        $a[] = "<a href=\"?dirID=0\">TopLevel</a>";
        $b .= implode("&nbsp;&gt;&nbsp;",array_reverse($a));
        $b .= "<br>\n";
        return $b;
    }
    ////////////////////////////////////////////////////////////////////////////
    function display($dirID){
        $b = '';
        //$b .= "Hey There ... Again - Displaying: $dirID<br>\n";
        //$b .= "<a href=\"?dirID=1\">Link 1</a><br>\n";
        //$b .= "<a href=\"?dirID=2\">Link 2</a><br>\n";
        //$b .= "<a href=\"?dirID=yogurt\">Link Yogurt</a><br>\n";

        // want to provide a link to the parent dirID
        $up = $this->dbh->fetchValNew("SELECT dparent FROM dirs WHERE did = ?;",array($dirID));
        $b .= "<a href=\".\">Top directory</a><br>\n";
        $b .= "<a href=\"?dirID=$up\">Up a directory</a><br>\n";
        $b .= $this->generateBreadCrumb($dirID);

        $b .= $this->listDirs($dirID);
        // // list directories
        // $h = $this->dbh->getKeyedHash('dname',"SELECT * FROM dirs WHERE dparent = ?;",array($dirID));
        // //$this->print_pre($h,"Hash");
        // foreach($h as $k => $v){
        //     $url = "?dirID={$v['did']}";
        //     $label = "";
        //     $b .= "dir: <a href=\"$url\">$k</a><br>\n";
        // }

        $b .= $this->listFiles($dirID);
        // // list files
        // // pull out README.md and display at top
        // $h = $this->dbh->getKeyedHash('fname',"SELECT * FROM files LEFT JOIN dirs on f_did = did WHERE f_did = ?;",array($dirID));
        // //$this->print_pre($h,"Hash");
        // foreach($h as $k => $v){
        //     $url = "data/{$v['fpath']}";
        //     $label = "{$v['fname']}";
        //     $b .= "file: <a href=\"$url\">$label</a><br>\n";
        // }
        return $b;
    }
    ////////////////////////////////////////////////////////////////////////////
    function listDirs($dirID){
        $b = '';
        // list directories
        $h = $this->dbh->getKeyedHash('dname',"SELECT * FROM dirs WHERE dparent = ?;",array($dirID));
        //$this->print_pre($h,"Hash");
        foreach($h as $k => $v){
            $url = "?dirID={$v['did']}";
            $label = "";
            $b .= "dir: <a href=\"$url\">$k</a><br>\n";
        }
        return $b;
    }
    ////////////////////////////////////////////////////////////////////////////
    function listFiles($dirID){
        $b = '';
        // list files
        // pull out README.md and display at top
        $h = $this->dbh->getKeyedHash('fname',"SELECT * FROM files LEFT JOIN dirs on f_did = did WHERE f_did = ?;",array($dirID));
        //$this->print_pre($h,"Hash");
        foreach($h as $k => $v){
            $url = "data/{$v['fpath']}";
            $label = "{$v['fname']}";
            $b .= "file: <a href=\"$url\">$label</a><br>\n";
        }
        return $b;
    }
}
?>
