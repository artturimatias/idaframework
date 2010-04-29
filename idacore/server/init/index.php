<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>

    <title> Test </title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <link rel="stylesheet" type="text/css" href="../../css/initStyle.css" />
</head>

<body>
    <div id="content">
    <h1>IDA-Framework test page</h1>
    
<?php
    echo "<h2>Machine</h2>\n";
    echo "<li>PHP: ".phpversion()."</li>";
    echo "<li>OS: ".php_uname("s")." ".php_uname("m")."</li>";

    // check if config.php is overridden
    $root = $_SERVER['DOCUMENT_ROOT'];
    $arr_path = explode("/", $_SERVER['PHP_SELF']);
    // back from idacore/server/init/
    unset($arr_path[count($arr_path)-1]);
    unset($arr_path[count($arr_path)-1]);
    unset($arr_path[count($arr_path)-1]);
    $path = implode($arr_path,"/");
    if(file_exists($root.$path.'/config.php')) {
        $config = $root.$path.'/config.php';
    } else {
        $config = '../../config.php';
    }

    include('MDB2.php');
    include($config);
 
    $settingError = 0;


	echo "<h2>Timezone (from config.php)</h2>\n";

            echo date_default_timezone_get();

	echo "<h2>Testing PHP's XML settings</h2>\n";
	
	if(!extension_loaded("dom")) {
		echo "<div class=\"error\">DOM extension is not loaded!";
		echo "<p class=\"info\">If in fedora, install php-xml. </p>";
		die();

	}

	if(!extension_loaded("SimpleXML")) {
		echo "<div class=\"error\">Simple XML extension is not loaded!";
		echo "<p class=\"info\">If in fedora, install php-xml. </p>";
		die();
	}	
	
	// we are here so XML should be ok
	echo "<p class=\"pass\">XML support OK</p>\n";
	
	
	
	
	echo "<h2>Testing UTF-8 settings</h2>\n";

	if (extension_loaded("mbstring")) {
		echo "<p class=\"pass\">UTF-8 support (mbstring) OK</p>\n";
    } else {
        echo "<p class=\"error\">UTF-8 support (mbstring) not found!</p>\n";
        echo "<p class=\"info\">If in fedora, install php-mbstring. </p>";
    }

	// settings array
	$settings = array ("mbstring.internal_encoding"=>"UTF-8", "mbstring.language"=>"neutral", "mbstring.encoding_translation"=>1, "mbstring.http_input"=>"auto", "mbstring.http_output"=>"UTF-8", "mbstring.detect_order"=>"auto", "default_charset"=>"UTF-8");
	

	
	foreach($settings as $setting=>$value) {

		if(!$set = ini_get($setting)) {
			echo "<p class=\"error\">$setting not set!</p>";
			$settingError = 1;
		} else {
			$warning = "";
			if(strtolower($set) != strtolower($value)) {
				$warning = "<span class=\"error\">(should be \"$value\")</span>";
				$settingError = 1;
			}
			echo "<li>$setting = \"" .$set . "\" $warning </li>\n";
				
		}
		
	}



	echo '<li>post_max_size = ' . ini_get('post_max_size') . "</li>\n";
	echo '<li>upload_max_size = ' . ini_get('upload_max_filesize') . "</li>\n";
	

	if($settingError) {
		echo "<div class=\"error\">UTF-8 settings warning!";
		echo "<p class=\"info\">It seems that some mbstring settings are not correct. Things WILL NOT work with non-ASCII characters. You should either:</p>";
		echo "<ul><li>enable .htaccess in Apache settings";
		echo "<ul><li class=\"info\">There is a .htaccess file in /idacore/server/</li></ul></li>";
		echo "<li>OR make configurations in php.ini</li></ul>";
		echo "</div>";
	}

	

    echo "<h2>Testing database</h2>\n";

    echo "<li>type: "._DATABASE_TYPE."</li>\n";
    echo "<li>database: "._DBNAME."</li>\n";

    if(defined("MDB2_OK")) {
         echo "<p class=\"pass\">MDB2 OK</p>\n";

        
    } else {
        echo "<div class=\"error\">MDB2 not found!\n";
        echo "<p class=\"info\">Install pear package called MDB2.</p></div>\n";
        die();
    }
  
  
    
	echo "<h2>Testing database connection</h2>\n";

	$dsn =_DATABASE_TYPE.'://'._DBUSER.':'._DBPASS.'@localhost/'._DBNAME;
	$path = $_SERVER['DOCUMENT_ROOT']._APP_DATA_PATH;

	$mdb2 = MDB2::connect($dsn);
	
	if (PEAR::isError($mdb2)) {
		echo "<p class=\"error\">Cannot not connect with MDB2!</p>\n";
		echo "<p class=\"info\">Check you settings. You can also try to execute <strong>cli_test.php</strong> from command line (in idacore/server/ )</p></div>\n";
		die();
	} else {
		echo "<p class=\"pass\">Connection OK</p>\n";

	}

	

	echo "<h2>Testing directory permissions</h2>\n";
        $dirError = 0;

	$dirs = array("images","thumbnails", "files", "xml", "cache");
	foreach($dirs as $dir) {
		 if (is_writable($path.$dir)) {
			echo "<li>".$path.$dir." <span class=\"pass\">is writable<span></li>\n";
		 } else {
			echo "<li>".$path.$dir." <span class=\"error\">is not writable!</span></li>\n";
			$dirError = 1;
		 }
	}


	if(!$dirError) {
		echo "<p><a href=\"initdb.php\">Go to setup</a></p>\n";    
	} else {
	   echo "<div class=\"error\">Data directories are not writable. <p class=\"info\"> You can proceed but file uploads will not work. (Doesn't matter now since file handling is not implemented yet)</p></div>\n";
		echo "<p><a href=\"initdb.php\">Go to setup</a></p>\n"; 
	}

    

?>

    </div>

</body>
</html>
