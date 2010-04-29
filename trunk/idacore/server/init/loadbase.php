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

    <h2>Base Semantics</h2>
    
    <?php
    
    require_once('../class.IdaDB.php');
    require_once('../class.DBManager.php');
    require_once('../class.TemplateManager.php');



    //ob_start();

    $tables = DBManager::getTables();
    if (PEAR::isError($tables)) {
        ob_end_clean();
        echo "<p class\"error\">Error with database!</p>";
    }

    // load CRM 
    try {
        TemplateManager::loadCRM();
    } catch (Exception $e) {
        ob_end_clean();
        echo "<p class=\"error\">".$e->getMessage()."</p>";
    } 
    

    if(TemplateManager::isCRM()  && count($tables)) {
        echo "<p class=\"pass\">CRM loaded created!</p>";
        echo "<ul><li><a href=\"datatables.php\">Continue</a></li></ul>\n";
    }

    ?>

    
    </div>
</body>

</html>
