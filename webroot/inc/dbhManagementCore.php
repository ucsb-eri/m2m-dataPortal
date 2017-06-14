<?php
/*
  Version 2015-12-03 by Aaron Martin
    Updating notes below.  Am interested in getting rid of some of the requirements.
    Would like to separate out the core functionality with no requirements (so that 
    getKeyedHash and fetchList can be used without the table management and possibly 
    the CSV functionality.
    
    The print_pre stuff can be dealt with pretty easily by just creating a class 
    method that has the same functionality as the print_pre global function.
    
    writeBuf is one of the requirements for the exportCSV routine.  I think that is
    pulled from a require_once inside csvUtils.  That could also be easily duplicated
    within the class.
    
    I think I will end up branching this really quick and splitting the dbMgmt
    into dbMgmtCore and dbMgmt which will extend the core functionality with CSV.
  Version 2014-11-05 by Aaron Martin
    Minor changes to logic in updateViaPreparedPostHash
*/
require_once("printUtils.php");
class dbMgmtCore {
    function __construct($key,$dsn,$desc,&$log = null){
        $this->db = $key;
        $this->dsn = $dsn;
        // type is not really used as I expect this to remain sqlite
        list($this->type,$this->path) = explode(":",$this->dsn);
        
        $this->dbInError = false;
        $this->readOnly = false;
        $this->log = $log;
        $this->counter = 0;
        $this->mesgs = "";
        $this->tables = array();
        // need to figure out how I want to handle triggers, views and indexes, not sure if I need tables created before doing those
        $this->inits = array();
        $this->pdos = null; // pdo statement for queries
        $this->result = null;
        $this->desc = $desc;
        
        // error goodies
        $this->errPrefix = array();
        $this->errPrefix['fail'] = "<span class=\"fail\">ERROR - </span>";
        $this->errPrefix['notice'] = "<span class=\"notice\">NOTICE - </span>";
      
        $this->errBuf = "";

        //$this->tablesCreated = false;
        
        //dprint(__METHOD__,0,0,"Getting ready to call myTables() in " . $this->db);
        $this->myTables();
        
        $GLOBALS['dbh'][$key] =& $this;
    }
    ////////////////////////////////////////////////////////////////////////////
    // stub method to overload 
    // The idea being that you define dbMgmtTables objects and use dbMgmt->addTable
    // to add them in.
    //
    // Also, that overloaded function should be a good place to put queries such as:
    //   create trigger
    //   create index
    //   create view
    //
    // NOTE: had made this private at one point, but then it apparently doesnt get
    // extended to the child class.  Even declaring it private in the child class
    // does not get it overloaded.
    ////////////////////////////////////////////////////////////////////////////
    protected function myTables(){
        //dprint(__METHOD__,0,0,"Calling myTables in base class");
    }
    ////////////////////////////////////////////////////////////////////////////
    function addInit($qstr){
        $this->inits[] = $qstr;
    }
    ////////////////////////////////////////////////////////////////////////////
    // Do not yet have an execute feed through, but I have to think how I want to use this anyway,
    // I typically use methods such as fetchList, getKeyedHash as they return already polished php 
    // structures, so have to consider setting up a prepared statement version of those, pFetchList()???
    // pGetKeyedHash()?
    ////////////////////////////////////////////////////////////////////////////
    function prepare($pquery){
            if ( ! $this->initDB() ){
                print "initDB() returned false in prepare<br />\n";
                return false; 
            }
            return $this->dbh->prepare($pquery);
    }
    ////////////////////////////////////////////////////////////////////////////
    function isWritable(){
         return is_writable($this->path);
    }
    ////////////////////////////////////////////////////////////////////////////
    function displayMesgs(){
        print $this->mesgs;
        $this->mesgs = "";
    }
    ////////////////////////////////////////////////////////////////////////////
    function tableExists($table){
        $count = $this->fetchVal("count(*) from sqlite_master","type='table' and name='{$table}'");
        return ($count != 0);
    }
    ////////////////////////////////////////////////////////////////////////////
    function initDB(){
        // if init has already failed, then just bail now
        if( $this->dbInError ) {
            $this->errBuf .= $this->errPrefix['fail'] . "dbInError already set at initDB<br />\n";
            return false;
        }
        
        $dir = dirname($this->path);
        if( ! file_exists($dir)){
            // if the directory doesn't exist then the file can't exist and we
            // dont even have a shot at creating the db
            $this->errBuf .= $this->errPrefix['fail'] . "DB Directory: <span class=\"b\">$dir</span>, doesn't exist, so the file cant exist and we will be unable to create it<br />\n";
            $this->dbInError = true;
            return false;
        }
        elseif( ! is_writable($dir) &&  ! file_exists($this->path) ){
            // if the directory exists but is not writable and no file exists, 
            // then we are still dont have a shot at creating the db
            $this->errBuf .= $this->errPrefix['fail'] . "DB Directory: <span class=\"b\">$dir</span>, doesn't exist, so the file cant exist and we will be unable to create it<br />\n";
            $this->dbInError = true;
            return false;
        }
        elseif( file_exists($this->path) && ! is_writable($this->path)){
            // file exists, but is not writable, so we should be able to proceed
            // in readonly mode
            $this->errBuf .= $this->errPrefix['notice'] . "In readOnly mode<br />\n";
            $this->readOnly = true;
        }
        elseif( ! file_exists($this->path)){
            // At this case, the directory must exist and be writable
            //dprint("Database not found",0,0,"Initializing Database: $this->path");
            $this->errBuf .= $this->errPrefix['notice'] . "Database not found; Initialize DB: <span class=\"b\">" . $this->path . "</span><br />\n";
            $this->tablesCreated = false;
        }
        else {
            // not sure we need to do anything here
        }
        
        // the above setup is much cleaner, removed all the old code
        if( ! isset($this->dbh)){
            try {
                $this->dbh = new PDO($this->dsn);
            } catch (PDOException $exception){
                print $exception->getMessage() . " " . $this->dsn;
                //die($exception->getMessage() . " " . $this->dsn);
                $this->dbInError = true;
                return false;
            }
        }
        
        if( ! isset($this->tables) || ! is_array($this->tables)){
            dprint("dbMgmt::initDB()",0,0,"problem with table array, initiating backtrace");
            $bt = debug_backtrace();
            $this->print_pre($bt);
        }
        
        //if ( ! $this->tablesCreated ) 
        //$this->createTables();
        
        foreach($this->tables as $table){
            //dprint("building table",0,0,"db: " . $this->db . ", table: " . $table->name);
            if( ! $this->tableCreated[$table->name] ){
                // check to see if the table exists
                // maybe do a pragma query???
                // what about seeing if we need to alter the db (ie: dbMgmtTable has changed, but db has not been
                // recreated).  Need to see if there are any new columns to be added via alter....
                $qstr = "select * from $table->name";
                $result = $this->dbh->query($qstr);
                if( $result == null ){
                    // table probably doesnt exist
                    $qs = $table->createTable();
                    dprint("creating table $table->name",0,2,"$qs");;
                    $result = $this->dbh->query("$qs");
                    if( $result == null ){
                        dprint("problem creating table",0,0,$table->name . " with query: $qs");
                        $ei = $this->dbh->errorInfo();
                        $this->print_pre($ei);
                        return false;
                    }
                }
                $this->tableCreated[$table->name] = true;
            }
        }
        foreach($this->inits as $init){
            $this->dbh->query($init);
        }
        return true;
    }
    ////////////////////////////////////////////////////////////////////////////
    function addTable($table){
        $this->tables[$table->name] = $table;
        $this->tableCreated[$table->name] = false;
        
        // maybe should create tables here?
    }
    ////////////////////////////////////////////////////////////////////////////
    // return an associative array with the names table column descriptions keyed
    // with the column name
    ////////////////////////////////////////////////////////////////////////////
    function getcolDesc($table){
        return ( isset($this->tables[$table]->colDesc) && is_array($this->tables[$table]->colDesc) ) ? $this->tables[$table]->colDesc : array();
    }
    ////////////////////////////////////////////////////////////////////////////
    function query($qstr,$values = null){
        if ( ! $this->initDB() ) {
            return false; 
        }
        $error = "";
         
        //dprint("executing query on {$this->db} db",0,0,"$qstr");
        // original method before refined the prepare/execute options
        //$this->pdos = $this->dbh->query("$qstr");
        
        if( is_object($this->result)){
            //dprint("having to closeCursor on non-empty result before query",1,0,"");
            //$this->print_pre($this->result,"remaining result");
            $this->result->closeCursor();
        }
        
        if( is_string($qstr) && is_null($values)){
            //print "running straight query<br />";
            if( ($this->pdos = $this->dbh->query("$qstr")) === false ){
                $error .= "straight query failed<br />\n";
            }
        }
        else if (  is_string($qstr) && ! is_null($values) ){
            if( is_array($values)){
                if( ($this->pdos = $this->dbh->prepare($qstr)) === false ){
                    $error .= "prepare failed";
                }
                else {
                    if( $this->pdos->execute($values) === false ){
                        $error .= "execute failed";
                    }
                }
            }
            else {
                $error .= "2nd argument should be an array or null<br />";
                // error condition
            }
        }
        else if ( is_object($qstr) && method_exists($qstr,'bindParam')){ // This means its a PDOStatement
            $this->pdos = $qstr;
            if( is_null($values)){  // Assume prepared PDOStatement, just execute
                //print "executing with no values<br />\n";
                $qstr->execute();
            }
            else if ( is_array($values)){
                //print "executing with values<br />\n";
                //$this->print_pre($values,"executing with values");
                if( $qstr->execute($values) === true ){
                }
                else {
                    $this->print_pre($qstr->errorInfo(),"error info",true);
                }
            }
            else {
                $error .= "2nd argument to getKeyedHash is not null or array<br />\n";
            }
        }
        else {
            $error .= "1st argument to getKeyedHash is not string or PDOStatement Object<br />\n";
        }
        
        // have had some issues with detecting errors, checking for null kind of worked, but 
        // doesn't align perfectly with the docs
        if ($this->pdos == null  || $error != "" ) {
        //if ($error != "") {
                //$error = $result->errorCode();
                //dprint(__FUNCTION__,0,0,"$error:");
                dprint("error on query",0,0,"error: $error, $qstr, debug_backtrace follows:");
                $dbt = debug_backtrace();
                $ei = $this->dbh->errorInfo();
                $this->print_pre($ei);
                $this->print_pre($qstr);
                $this->print_pre($dbt);
                die("fatal db query error<br>\n");
        }
        else {
            if( 1 && $this->log && ! preg_match("/^select/",$qstr)){
                // log this entry
                // would be nice to somehow pull the prog_coord for the given site....
                // options: use a SESSION var that would be set before a query (relies on programming)
                //          try to derive from the query (ie: look for prog_coord string or p_name and then map (not reliable))
                $htmlized = str_replace("'","&apos;",$qstr);
                $istr = "insert into logEntries ('l_user','l_db','l_dsn','l_ip','l_query') values('{$_SESSION['u_name']}','{$this->db}','{$this->dsn}','{$_SERVER['REMOTE_ADDR']}','$htmlized')";
                //$istr = "insert into logEntries ('l_user','l_db','l_dsn','l_ip','l_query') values('{$_SESSION['u_name']}','{$this->db}','{$this->dsn}','{$_SERVER['REMOTE_ADDR']}','fixed string {$this->counter}')";
                //dprint("Creating a log entry",0,0,"$istr");
                $this->log->query($istr);
                //usleep(1000000);
            }
        }
        //print_r($result);
        //print "just printed result<br>\n";
        return($this->pdos);
    }
    ////////////////////////////////////////////////////////////////////////////
    function createTables(){
        foreach($this->tables as $table){
            $qs = $table->createTable();
            print __METHOD__ . "() - {$table->name}: qs: $qs<br>\n";
            $result = $this->dbh->query("$qs");
        }
        $this->tablesCreated = true;
    }
    ////////////////////////////////////////////////////////////////////////////
    function resetTable($which){
        if ( isset( $this->tables[$which])){
            $this->query("BEGIN TRANSACTION");
            $this->query("drop table if exists $which");
            $this->query($this->tables[$which]->createTable());
            $this->query("END TRANSACTION");
        }
    }
    ////////////////////////////////////////////////////////////////////////////
    function getTable($name){
        foreach($this->tables as $table){
            if( $table->name == $name ) return $table;
        }
        return null;
    }
    ////////////////////////////////////////////////////////////////////////////
    function verifyDB(){
        print "Verifying tables in db: <span class=\"dbmgmt_db\">" . $this->db . "</span> at: <span class=\"dbmgmt_path\">" . $this->path . "</span><br />\n";
        foreach(array_keys($this->tables) as $t){
            if($this->verifyTableByName($t) === false) print $this->errBuf;
        }
    }
    ////////////////////////////////////////////////////////////////////////////
    function verifyTableByName($tablename){
        if( $this->dbInError )  return false;
        
        if( ! isset($this->tables[$tablename])){
            print "invalid table name $tablename<br>\n";
        }
        
        $table = $this->tables[$tablename];
        //foreach($this->tables as $table){
        print "<span class=\"indent\">verifying table $table->name</span><br>\n";
        $actual_col = array();
        
        // these are arrays from the table object indexed by the field name
        $spec_col = $table->colType;
        $spec_ext = $table->colTypeExt;
        
        $result = $this->query("pragma table_info('$table->name')");
        $r = $result->fetchAll(PDO::FETCH_ASSOC);
        foreach($r as $col){
            $actual_col[$col['name']] = $col['type'];
        }
        //$this->print_pre($actual_col,"pragma");
        //$this->print_pre($spec_col,"spec");
        $akeys = array_keys($actual_col);
        $skeys = array_keys($spec_col);
        $ikeys = array_intersect($akeys,$skeys);
        
        $missing_from_spec = array_diff($skeys,$akeys);
        $missing_from_actu = array_diff($akeys,$skeys);
        //$this->print_pre($ikeys);
        if( count($missing_from_spec)){
            print "found in the table spec but not in the actual db: {$this->db} {$table->name}<br>\n";
            $this->print_pre($missing_from_spec);
            // need to run alter to add the columns...
            foreach($missing_from_spec as $field){
                $qstr = "alter table {$table->name} add column $field {$spec_col[$field]} {$spec_ext[$field]}";
                print "$qstr<br>\n";
                if( ! count($missing_from_actu)){
                    // if there are not opposing entries, then most likely we can just automatically add
                    print "<font color='green'>automatically altering the database to match the spec with: </font>$qstr<br>\n";
                    $this->query($qstr);
                }
                else {
                    print "There are discrepancies of the opposing type in the database and there is no automatic way to resolve.<br>\n";
                    print "will eventually try to create a form here that would allow the user to make a choice to apply<br>\n";
                    print "<font color='green'>To update from the command line: </font>sqlite3 {$this->path} \"$qstr\"<br>\n";
                }
            }
        }
        if( count($missing_from_actu)){
            print "found in the actual db but not in the table spec: {$this->db} {$table->name}<br>\n";
            print "this may require removing a column from the database table and cannot be done automatically<br>\n";
            
            $this->print_pre($missing_from_actu);
        }
        //$this->print_pre($actual_col);
        //}
    }
    ////////////////////////////////////////////////////////////////////////////
    function getTableDescriptionHash($table){
        // would be nice to be able to query
        //print "getting pragma for table: $table<br>\n";
        $result = $this->query( "pragma table_info('" . $table . "')");
        $r = $result->fetchAll(PDO::FETCH_ASSOC);
        //$this->print_pre($r,"pragma result");
        return $r;
    }
    ////////////////////////////////////////////////////////////////////////////
    function getColumnNamesFromTable($table,$skipcols = array()){
        $r = $this->getTableDescriptionHash($table);
        foreach($r as $v){            
            if( ! in_array($v['name'],$skipcols))  $dbcols[] = $v['name'];
        }
        return $dbcols;
    }
    ////////////////////////////////////////////////////////////////////////////////
    // The array input is expected to be a indexed array of hashed arrays with 
    // values keyed by column names.  
    // skipcols is an array of column names to avoid inserting values for (primary keys possibly)
    // Output from csv2array() is suitable
    //
    // if the integer primary key field is provided, sqlite effectively drops any 
    // rows larger than that number upon insert.  This is convenient for much of our
    // use, but needs to be kept in mind.
    // 
    // may want to add a qualifier to skip insertion or warn if not enough fields match
    //
    // Routine above converted to use a prepared statment...
    ////////////////////////////////////////////////////////////////////////////////
    // The prepared statement version
    ////////////////////////////////////////////////////////////////////////////////
    function insertArrayOfHashes($a,$table,$skipcols,$do_query = true){
        // would be nice to be able to query
        // Hmmmm, this fails on gem (cricess db)...  So then we have no columns to enter...
        if( ! isset($this->tables[$table])){
            print "Table: <span class=\"b\">$table</span> not found in: <span class=\"b\">" . $this->desc . " Database</span> at key: <span class=\"b\">" . $this->db . "</span><br />\n";
            return 0;
        }
        
        $r = $this->getTableDescriptionHash($table);
        $dbcols = array();
        
        // go through the table schema description to determine the primary key of the table
        foreach($r as $v){
            if( $v['pk'] == 1 ) $primary_key = $v['name'];
            
            if( ! in_array($v['name'],$skipcols))  $dbcols[] = $v['name'];
            $type[$v['name']] = $v['type'];
        }
        //$this->print_pre($r,"pragma result (table schema) for $table");        
        
        // now that we have a list of column names, we need to compare to the hash
        $datacols = array_keys($a[0]);
        //$this->print_pre($datacols,"data columns");
        //$this->print_pre($dbcols,"db columns");
        
        $exportcols = array_intersect($dbcols,$datacols);
        $skippedcols = array_diff($dbcols,$datacols);
        $this->mesgs .= $this->print_pre($skippedcols,"skipped columns",true);
        $this->mesgs .= $this->print_pre($exportcols,"export columns",true);

        $this->query("BEGIN TRANSACTION");

        $exportcolscount = count($exportcols);
        if( $exportcolscount <= 0 ){
            print "Error on Exported Columns array<br />\n";
            $this->print_pre($exportcols,"Exported Columns");
            $this->print_pre($r,"Table Description Hash");
            $this->print_pre($dbcols,"DB Columns (from db schema)");
            $this->print_pre($datacols,"Data Columns (from hash keys)");
        }
        // prepare a PDOStatement for doing multiple executes while looping through hash
        $pq = "insert into $table (";
        $pq .= implode(",",$exportcols);
        $pq .= ") values (" . implode(",",array_fill(0,$exportcolscount,"?")) . ")";
        
        $stm = $this->dbh->prepare($pq);
        if( $stm === false ){
            print "prepare set error code: " . $this->dbh->errorCode() . "<br>\n";
            print "prepare set error info: " . print_r($this->dbh->errorInfo()) . "<br>\n";
            print "prepared query: $pq <br>\n";
            print "Prepare failed<br>\n";
        }
        else {
            $this->mesgs .= "Prepare succeeded<br>\n";
        }
        
        foreach($a as $row){
            $pqvals = array();
            foreach($exportcols as $col){
                $pqvals[] = $row[$col];
            }

            if( $do_query ){
                if( $stm->execute($pqvals) === true ){
                    $this->mesgs .= "statement execution succeeded<br>\n";
                }
                else {
                    $this->mesgs .= "statement execution failed<br>\n";
                    $this->mesgs .= $this->print_pre($stm->errorInfo(),"error info",true);
                }
            }
            else {
                print "TEST MODE: ";
            }
            $pqvstr = implode(",",$pqvals);
            $this->mesgs .= "insertArrayOfHashes(): prepared query: $pq   : valstr: $pqvstr<br>\n";
        }
        $this->query("END TRANSACTION");

        // return the rowid of this entry, (0 if it fails), should be last primary key...
        return $this->getLastInserted($primary_key,$table);
        // should return last primary key....
    }
    ////////////////////////////////////////////////////////////////////////////////
    function getLastInserted($key,$table){
        $qstr = "select $key from $table where $key=last_insert_rowid()";
        $qstr = 'SELECT last_insert_rowid() as last_insert_rowid';
        //$qstr = "select last_insert_rowid()";
        $this->mesgs .= "qstr for lastInserted: $qstr<br>\n";
        $result = $this->query("$qstr");
        //$r = $result->fetchAll(PDO::FETCH_ASSOC);
        $r = $result->fetch();
        return $r['last_insert_rowid'];
    }
    ////////////////////////////////////////////////////////////////////////////
    function fetchVal($select,$where = "",$values = null){
        //$this->print_pre($dbh);
        
        $qstr = "select $select";
        if( $where != "" ) $qstr .= " where $where";
        
        //print "fetchVal: qstr: $qstr<br>\n";
        $result = $this->query("$qstr",$values);
        $r = $result->fetchAll(PDO::FETCH_COLUMN);
        return (isset($r[0])) ? $r[0] : '';
    }
    ////////////////////////////////////////////////////////////////////////////
    function fetchValNew($qstr,$values = null){
        $result = $this->query("$qstr",$values);
        $r = $result->fetchAll(PDO::FETCH_COLUMN);
        return (isset($r[0])) ? $r[0] : '';
    }
    ////////////////////////////////////////////////////////////////////////////////
    function updateVal($table,$field,$value,$where = ""){
        $qstr = "update $table set $field='$value'";
        if( $where != "" ) $qstr .= "where $where";
        else return false;
        
        $this->query($qstr);
        return $result;
    }
    ////////////////////////////////////////////////////////////////////////////////
    // Pretty sure we expect $select to specify only one record
    ////////////////////////////////////////////////////////////////////////////////
    function fetchList($select,$where = "",$order = "",$values = null){
        //if( ! is_object($this)) {
        //    $this->print_pre(debug_backtrace(),"fetchList: debug_backtrace");
        //}
        
        $qstr = "select $select";
        if( $where != "" ) $qstr .= " where $where";
        if( $order != "" ) $qstr .= " order by $order";
        
        //print "fetchVal: qstr: $qstr<br>\n";
        if( ($result = $this->query("$qstr",$values)) === false) {
            print "query: $qstr failed, possibly db init error<br />\n";
            return array();
        }
        //$this->print_pre($result,"dbh");
        $r = $result->fetchAll(PDO::FETCH_COLUMN);
        //$this->print_pre($r,"result fetchAll");
        //$this->print_pre($dbh,"dbh");
        return $r;
    }
    ////////////////////////////////////////////////////////////////////////////////
    // newer version of above function that relies on the user assembling, but 
    // more cleanly supports the newer prepare/execute query routine
    ////////////////////////////////////////////////////////////////////////////////
    function fetchListNew($qstr,$values = null){
        $result = $this->query("$qstr",$values);
        $r = $result->fetchAll(PDO::FETCH_COLUMN);
        return $r;
    }
    ////////////////////////////////////////////////////////////////////////////////
    function getKeyedHashSingle($key,$val,$qstr,$values = null){
        $result = $this->query("$qstr",$values);
        $r = $result->fetchAll(PDO::FETCH_ASSOC);
        //$this->print_pre($r);
        
        foreach($r as $row){
            $hash[$row[$key]] = $row[$val];
        }
        return $hash;
    }
    // Interesting idea, but not sure if it will actually work
    ////////////////////////////////////////////////////////////////////////////////////
    ////function dbCheckError($caller){
    ////    if( $this->dbInError ){
    ////        print "Cannot complete request from $caller due to previous errors<br />\n";
    ////        return true;
    ////    }
    ////    return false;
    ////}
    ////////////////////////////////////////////////////////////////////////////
    // want to create a prepared statement version of this, but I wonder if I 
    // could get away with checking the type of $qstr...  It its a string then
    // run a regular query, if its a prepared statement object ....
    // the problem then becomes attaching the arguments to the execute
    // so we may have to do the prepare and execute BEFORE handing off the PDOstatement
    // object to this routine...  or allow another value (the data array) to be passed 
    // in for the execute  $data = null
    ////////////////////////////////////////////////////////////////////////////
    // OK, so getKeyedHash now handles a couple possible usages:
    //   key and basic query string
    //   key and string for a prepare and an array of values for an execute
    //   key and a PDOStatement object and an array of values for an execute or null if prebound
    ////////////////////////////////////////////////////////////////////////////
    function getKeyedHash($key = "",$qstr = "", $values = null ){
        $hash = array();  // initialize output hash
        
        // nice idea, but InitDB() may not actually get called till the fetch all
        //if( $this->dbCheckError(__FUNCTION__)) return $hash;
        
        //$this->print_pre($values,"values passed into getKeyedHash() for qstr: $qstr");
        if( ($pdos = $this->query($qstr,$values)) === false){
            $this->errBuf .= $this->errPrefix['fail'] . "Cannot complete query <span class=\"b\">$qstr</span> in " . __METHOD__ . "<br />\n";
            return $hash;
        }
        $r = $pdos->fetchAll(PDO::FETCH_ASSOC);
        //$this->print_pre($r,"results from fetchAll()");
        
        if(! is_array($key)){
            if( preg_match("/,/",$key)) $key = explode(",",$key);
        }
        
        if( $key == "" ) return $r;
        
        foreach($r as $row){
            //print "getKeyedHash(): processing row<br>\n";
            // if key is an array, create a compound key for the hash
            if( is_array($key)) {
                $ckey_elem = array();
                foreach( $key as $k){
                    //print "key: $k<br>\n";
                    $ckey_elem[] = $row[$k];
                }
                $ckey = implode(",",$ckey_elem);
            }
            else $ckey = $row[$key];
            
            $hash[$ckey] = $row;
        }
        return $hash;
    }
    ////////////////////////////////////////////////////////////////////////////
    function dispErr($label = "",$class = ""){
        $b = "";
        if ($this->errBuf != "" ){
            //$classstr =  ;
            $b .= "<div " . (( $class == "" ) ? "" : "class=\"$class\"") . ">";
            $b .= ( $label == "" ) ? "" : "<span class=\"dispErrLabel\">$label</span><br />\n"  ;
            $b .= $this->errBuf;
            $b .= "</div>\n";
            $this->errBuf = "";  // reset errBuf
        }
        return  $b;
    }
    ////////////////////////////////////////////////////////////////////////////
    // this is an exact copy of the global print_pre found in printUtils.php
    ////////////////////////////////////////////////////////////////////////////
    function print_pre($v,$label = "",$returnme = false){
        $b = "";
        if ( $label != "" ){
            $b .= "<hr><font color=blue>$label</font>\n";
        }
        $b .= "<pre style='margin: 0; padding: 0;'>";
        
        $b .= print_r($v,true);
        
        $b .= "</pre>";
        if ( $label != "" ){
            $b .= "<font color=blue>$label</font><hr>\n";
        }
        
        if( ! $returnme ) print $b;
        return $b;
    }
}
////////////////////////////////////////////////////////////////////////////////
class dbMgmtTable {
    ////////////////////////////////////////////////////////////////////////////
    function __construct($tablename,$tabledesc = ""){
        $this->name = $tablename;
        $this->tabledesc = $tabledesc;
    }
    ////////////////////////////////////////////////////////////////////////////
    function addCol($fieldname,$type, $args = "",$default = "",$desc = ""){
        $this->fieldname[] = $fieldname;
        $this->type[] = $type;
        $this->args[] = $args;
        $this->createstr[] = "$fieldname $type $args";
        $this->default[] = $default;
        $this->desc[] = $desc;
        $this->colDesc[$fieldname] = $desc;
        $this->colType[$fieldname] = $type;
        $this->colTypeExt[$fieldname] = $args;
    }
    ////////////////////////////////////////////////////////////////////////////
    function createTable(){
        $createstr = "create table {$this->name} (" . implode(",",$this->createstr) . ")";
        return $createstr;
        //print "createstr: $createstr<br>\n";
    }
}
?>
