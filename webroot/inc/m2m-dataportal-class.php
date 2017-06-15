<?php
// this requires the AJM phplib install
require_once('ext/dbhManagementCore.php');
require_once('ext/Parsedown.php');

////////////////////////////////////////////////////////////////////////////////
class m2mDataPortalDb extends dbMgmtCore {
    // hollow extended class, probably won't need to build this out, but here
    // just in case.
}
////////////////////////////////////////////////////////////////////////////////
class m2mDataPortal {
    ////////////////////////////////////////////////////////////////////////////
    function __construct($dsn){
        $this->now = time();
        $this->dbh = new m2mDataPortalDb('m2m',$dsn,"M2M Data Portal Database");
    }
    ////////////////////////////////////////////////////////////////////////////
    function print_pre($o,$label){
        print "<pre>";
        print_r($o);
        print "</pre>";
    }
    ////////////////////////////////////////////////////////////////////////////
    function getReadmeMarkdown($dirID){
        $b = '';
        $q = "SELECT fpath FROM files WHERE f_did = ? AND fname LIKE 'README.md';";
        //echo "q: $q -- $dirID<br>\n";
        $mdName = $this->dbh->fetchValNew("$q",array($dirID));
        if ( $mdName != "" ){
            $pd = new Parsedown();
            $b .= $pd->text(file_get_contents('data/'.$mdName));
        }
        return ( $b != "" ) ? "<div class=\"markDown\">\n" . $b . "</div>\n" : "" ;
    }
    ////////////////////////////////////////////////////////////////////////////
    function generateBreadCrumb($dirID){
        $parID = $dirID;
        $b = '';
        $b .= "<div class=\"breadCrumb\"><!-- begin breadCrumb -->\n";
        $a = array();
        // recursively get the parent till parent == '' or 0
        while( ! ($parID == '' || $parID == '0' )){
            $parName = $this->dbh->fetchValNew("SELECT dname FROM dirs WHERE did = ?;",array($parID));
            //$label = ($parName == '') ? "TopLevel" : "$parName";
            $a[] = "<a class=\"breadCrumb\" href=\"?dirID=$parID\">$parName</a>";
            $parID = $this->dbh->fetchValNew("SELECT dparent FROM dirs WHERE did = ?;",array($parID));
        }
        $a[] = "<a class=\"breadCrumb\" href=\"?dirID=0\">TopLevel</a>";
        $b .= implode("<span class=\"breadCrumb\">&nbsp;&gt;&nbsp;</span>",array_reverse($a));
        $b .= "</div><!-- end breadCrumb -->\n";
        //$b .= "<br>\n";
        return $b;
    }
    ////////////////////////////////////////////////////////////////////////////
    function display($dirID){
        $b = '';
        // want to provide a link to the parent dirID
        //$up = $this->dbh->fetchValNew("SELECT dparent FROM dirs WHERE did = ?;",array($dirID));
        //// $b .= "<a href=\".\">Top directory</a><br>\n";
        //// $b .= "<a href=\"?dirID=$up\">Up a directory</a><br>\n";
        $b .= $this->getReadmeMarkdown($dirID);
        $b .= $this->generateBreadCrumb($dirID);

        $b .= $this->listDirs($dirID);
        $b .= $this->listFiles($dirID);
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
            $b .= "folder: <a href=\"$url\">$k</a><br>\n";
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
            $url = "data/{$v['fpath']}?{$this->now}";
            $label = "{$v['fname']}";
            $b .= "file: <a href=\"$url\">$label</a><br>\n";
        }
        return $b;
    }
}
?>
