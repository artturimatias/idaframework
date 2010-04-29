<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>

    <title> Setup </title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <link rel="stylesheet" type="text/css" href="../../css/initStyle.css" />
</head>

<body>
    <div id="content">

    <h1>IDA-framework setup </h1>

    <h2>Database-init</h2>
    
    <?php

    require_once('../class.IdaDB.php');
    require_once('../class.DBManager.php');


    ob_start();
    $error = 0;

    try {
        DBManager::initDb();
    } catch (Exception $e) {
        ob_end_clean();
        $error = 1;
        echo "<p class=\"error\">".$e->getMessage()."</p>";
        echo "<p>Check your table definition files, drop database and run setup.</p>";
    }
        
    $tables = DBManager::getTables();
    
    if (PEAR::isError($tables)) {
        echo "<p class\"error\">Error with database!</p>";
    }

    if(!$error && count($tables)) {
        echo "<p class=\"pass\">Database init OK!</p>";
        echo "<ul><li><a href=\"datatables.php\">Continue</a></li></ul>\n";
    }

    ?>

    
    </div>
</body>

</html>
