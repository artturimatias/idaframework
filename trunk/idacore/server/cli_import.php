#!/usr/bin/php -q
<?php 

// prevent execution from browser
if(isset($_SERVER['REQUEST_URI'])) {
	die("Execute this script from command line!");
}

set_error_handler("myErrorHandler");

include('MDB2.php');
include('config.php');
require_once('class.IdaDB.php');
require_once('class.IdaRecord.php');
require_once('class.IdaXML.php');

$xml = new DOMDocument('1.0', 'UTF-8');

ob_end_clean();
system("clear");
mainHeader(1);
readXML($xml);
//drawMenu();
//mainLoop();

// *****************************************************
// Part #2 - A simple menu system
// *****************************************************

function drawMenu() {

      system("clear");
      mainHeader();

      echo "(1) import file\n";
      echo "(2) drop word indexes\n";
      echo "(3) create word index\n";
      echo "(0) Exit!\n";
      echo "----------------------------\n\n";

    
}



function mainLoop($xml) {

      echo "\n\n----------------------------";
      echo "\nPlease enter a number between 0 and 3 : ";
 
    do {

      $choice = trim(fgets(STDIN));

    } while(!(($choice == "0") || ($choice == "1") || ($choice == "2") || ($choice == "3")));
    // *****************************************************



    system("clear");
    mainHeader(1);
    
    switch($choice){
      case "1":
        import($xml);
        break;
      case "2":
        dropIndex();
        break;
      case "3":
        reIndex();
        break;
      case "0":
      default:
        echo "\n\nBye!\nExiting.....\n\n";
        exit();
    }

}




function readXML($xml) {

    $opts = getopt("f:");
    $xml_file = $opts["f"];

    if($xml_file != false && file_exists($xml_file))
        printBold("Parsing XML file:\n");
    else
        die("\nFile not found!\n\n");


    // check XML
    if($xml->load($xml_file,LIBXML_NOBLANKS)) {

        printSuccess("XML parsed OK\n");
        drawMenu();
        mainLoop($xml);

     } else {

        printError("XML parsing failed!\n");
     }


}

function import($xml) {

    $result = "";
    $root = $xml->firstChild;
    $mode = null;

    // find tablenames for columns
    $cols = IdaDb::getTableColumns();

    // match column names to tables
    XML::PrepareXML($root, $cols);

    switch($root->tagName) {

    case "editlinks":
        foreach($root->childNodes as $link) {
            $res = XML::makeLink($link);
            echo $res."\n";
        }
    break;


    case "editlinks_search":

        // create temporary table for records
        $sql = "CREATE TEMPORARY TABLE 
                    "._DBPREFIX."_root_0
                    (id integer,
                    INDEX(id))";

        IdaDB::exec($sql);

        // create temporary table for records
        $sql = "CREATE TEMPORARY TABLE 
                    "._DBPREFIX."_root_1
                    (id integer,
                    INDEX(id))";

        IdaDB::exec($sql);

        // create temporary table for records
        $sql = "CREATE TEMPORARY TABLE 
                    "._DBPREFIX."_root_2
                    (id integer,
                    INDEX(id))";

        IdaDB::exec($sql);

        // create temporary table for records
        $sql = "CREATE TEMPORARY TABLE 
                    "._DBPREFIX."_root_3
                    (id integer,
                    INDEX(id))";

        IdaDB::exec($sql);

        // create temporary table for records
        $sql = "CREATE TEMPORARY TABLE 
                    "._DBPREFIX."_root_4
                    (id integer,
                    INDEX(id))";

        IdaDB::exec($sql);



        // create temporary table for records
        $sql = "CREATE TEMPORARY TABLE 
                    "._DBPREFIX."_search_tmp
                    (id integer,
                    INDEX(id))";

        IdaDB::exec($sql);




        // check the search node
        $xpath = new DOMXPath($xml);
        $searchNode = $xpath->query("/editlinks_search/search//*[@map_id]");
        $searchClass = $xpath->query("/editlinks_search/search/*");
        if($searchNode->length) {
            $mode = $searchNode->item(0)->getAttribute("map_id");
            $className = $searchClass->item(0)->tagName;
        }

        $linkNodes = $xpath->query("/editlinks_search/link");

        foreach($linkNodes as $link) {
            $val = $link->getAttribute($mode);
            $searchNode->item(0)->setAttribute("map_id",$val);

            try {
                $searchRec = new IdaRecord($className);
                $searchRec->searchByXML_new($searchClass->item(0), "root_0");
                //$records = IdaDb::select("root_0", array(), array("id") );
                //echo "VALUE:".$val;
                //print_r($records);
                $records = IdaDb::select("root_0", array(), array("id"), "", "onecell");

                // if indirect record was not found then link directly map_id
                $link->setAttribute("target_id", $records);
                //else
                  //  $link->setAttribute($mode, $val );


            } catch (Exception $e) {

                echo "ERROR: ".$e->getMessage();
            }
           

           $res = XML::makeLink($link);
           echo $res."\n";

            // reset temp table 
            $sql = "DELETE FROM "._DBPREFIX."_root_0";
            IdaDb::exec($sql);
 
            // reset temp table 
            $sql = "DELETE FROM "._DBPREFIX."_root_1";
            IdaDb::exec($sql);
             $sql = "DELETE FROM "._DBPREFIX."_root_2";
            IdaDb::exec($sql);
             $sql = "DELETE FROM "._DBPREFIX."_root_3";
            IdaDb::exec($sql);
             $sql = "DELETE FROM "._DBPREFIX."_root_4";
            IdaDb::exec($sql);
 
            // reset temp table 
            $sql = "DELETE FROM "._DBPREFIX."_search_tmp";
            IdaDb::exec($sql);
 


        }
    break;

    case "import":
        foreach($root->childNodes as $class) {
            $res=  XML::importXML($class);
            echo $res."\n";
            if(stristr($res, "unknown"))
                die();
            echo "Memory used: ".number_format(memory_get_usage())." bytes\n";
        }
    break;

    default:
        echo "invalid root node! (".$root->tagName.")";

    }


}

function dropIndex() {


    drawMenu();
    printBold("Deleteting word index\n");

    $result = "";

    try {

        IdaDB::dropIndex();
        printSuccess("Word indexes dropped\n");


    } catch (Exception $e) {


        printError("Dropping failed! ".$e->getMessage()."\n");
        exit();
    }

    mainLoop();
	
}

function reIndex() {


    drawMenu();
    printBold("Creating word index\n");

    $result = "";
    $tableName="appellation";

    try {

        $rows = IdaDB::select($tableName, array(), array("id"), "", "onecol");

        $table = new IdaTable($tableName);
        IdaDb::createTempTable();

        foreach($rows as $row) {

            $result = IdaDB::select($tableName."_join", array("property"=>$row), array("subject"), "", "onecell");
            $table->load($row);
            IdaDB::indexWords($result, $table);
            $table->clear();
        }

        printSuccess("Word indexing done for table $tableName\n");
        IdaDb::dropTempTable();


    } catch (Exception $e) {


        printError("Dropping failed! ".$e->getMessage()."\n");
        exit();
    }

    mainLoop();
	
}


function testDB () {

    drawMenu();
    printBold("Testing database:\n");

	switch(_DATABASE_TYPE) {
	case "pgsql" :
		if(function_exists("pg_connect")) {
			printSuccess("- PHP's PostgreSQL support OK\n");
		}
		break;
	case "mysql" :
		if(function_exists("mysql_connect")) {
			printSuccess("- PHP's Mysql support OK\n");
		}
		break;	
	default:
		printError("Wrong database type!");
	}

    if(defined("MDB2_OK")) {
        printSuccess("- MDB2 OK\n");
        test();
        
    } else {
        printError("- MDB2 not found!\n");
        echo "Install pear package called MDB2.\n";
    }

    mainLoop();

	
}


function test() {
	
	printBold("\nTesting connection:\n");

	$error = 0;
	$connection = 0;


	$dsn =_DATABASE_TYPE.'://'._DBUSER.':'._DBPASS.'@localhost/'._DBNAME;
	$path = $_SERVER['DOCUMENT_ROOT']._APP_DATA_PATH;
	$dirs = array("images","thumbnails", "minithumbnails", "xml");
	
	echo "connection parameters: ".$dsn."\n";
	
	$mdb2 = MDB2::connect($dsn);
	
	if (PEAR::isError($mdb2)) {

		printError("- Cannot not connect!\n");
	
		
	} else {
		$connection = 1;
		printSuccess("- Connection OK\n");

	}
}





function printError($str) {
	echo "\033[31m".$str."\033[37m";
	echo "\033[0m";
}

function printSuccess($str) {
	echo "\033[32m".$str."\033[37m";
	echo "\033[0m";
}

function printBold($str) {
	echo "\033[1m".$str;
	echo "\033[0m";
}

function mainHeader() {
    

		echo "     ***********************\n";
		echo "     IDA-Framework test tool\n";
		echo "     ***********************\n";
	   
        
    
}


function myErrorHandler($errno, $errstr, $errfile, $errline)
{
    switch ($errno) {
    	
    case E_ERROR:
    	echo "huuu";
    	break;
    		
    case E_WARNING:
    	printError("ERROR:\n");
    	echo " $errstr\n";
    	break;
    	
    case E_USER_ERROR:
        echo "FATAL ERROR [$errno] $errstr\n";
        echo "on line $errline in file $errfile";
        exit(1);
        break;

    case E_USER_WARNING:
        echo "<b>My WARNING</b> [$errno] $errstr<br />\n";
        break;

    case E_USER_NOTICE:
        echo "<b>My NOTICE</b> [$errno] $errstr<br />\n";
        break;

    default:
       // echo "Unknown error type: [$errno] $errstr<br />\n";
        break;
    }

    /* Don't execute PHP internal error handler */
    return true;
}


exit(0);
