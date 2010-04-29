<?php 

// prevent execution from browser
if(isset($_SERVER['REQUEST_URI'])) {
	die("Execute this script from command line!");
}

set_error_handler("myErrorHandler");

include('MDB2.php');
include('config.php');

//require_once('class.IdaRecord.php');
//require_once('class.DBManager.php');

ob_end_clean();

system("clear");
mainHeader(1);
drawMenu();
mainLoop();

// *****************************************************
// Part #2 - A simple menu system
// *****************************************************

function drawMenu() {

      system("clear");
      mainHeader();

      echo "(1) test XML support\n";
      echo "(2) test UTF-8 support\n";
      echo "(3) test database\n";
      echo "(0) Exit!\n";
      echo "----------------------------\n\n";

    
}



function mainLoop() {

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
        testXML();
        break;
      case "2":
        testUTF8();
        break;
      case "3":
        testDB();
        break;
      case "0":
      default:
        echo "\n\nBye!\nExiting.....\n\n";
        exit();
    }

}




function testXML() {
    $err = 0;

    drawMenu();
    
	printBold("Testing PHP's XML settings:\n");

	if(!extension_loaded("dom")) {
		printError("- DOM extension not loaded!\n");
		echo "If in fedora, install php-xml.\n";
		$err = 1;

	}

	if(!extension_loaded("SimpleXML")) {
		printError("- Simple XML extension is not loaded!\n");
		echo "If in fedora, install php-xml. ";
		$err = 1;
	
	}	

	// we are here so XML should be ok
	if(!$err) 
	    printSuccess("XML support OK");
	
	mainLoop();
}

function testUTF8() {
	
	$mbstringError = 0;
	
	drawMenu();
	printBold("Testing UTF-8 support\n");

	if (extension_loaded("mbstring")) {
		printSuccess("- UTF-8 support (mbstring) OK\n");
    } else {
        printError("- UTF-8 support (mbstring) not found!\n");
    }

	// settings array
	$settings = array ("mbstring.internal_encoding"=>"UTF-8", "mbstring.language"=>"neutral", "mbstring.encoding_translation"=>1, "mbstring.http_input"=>"auto", "mbstring.http_output"=>"UTF-8", "mbstring.detect_order"=>"auto", "default_charset"=>"UTF-8");
	

	
	foreach($settings as $setting=>$value) {
        echo "- ".$setting." = \"" .ini_get($setting). "\"\n";
	}	

	printBold("NOTE:\n");
	echo "You should check UTF-8 settings from: idacore/server/init/";

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
