<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>

    <title> Setup </title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <link rel="stylesheet" type="text/css" href="../../css/initStyle.css" />
</head>

<body>
    <div id="content">

    <h1>IDA-framework main user setup </h1>

    
    <?php
    
    require_once('../class.IdaDB.php');
    require_once('../class.DBManager.php');
    require_once('../class.Template.php');

   
    
    $isEmpty = IdaDB::isEmpty("_sys_users");

    if(isset($_GET["act"])) {
        if($isEmpty && $_GET["act"] == "add") {
        
            if($_POST["u"] != "" && $_POST["p"] != "") {

                try {
                
                    DBManager::addMainUser($_POST["u"], $_POST["p"]);
                    
                } catch (Expection $e) {
                    echo $e->getMessage();
                }
                $isEmpty = 0;
            }
        }
    }

 
    if($isEmpty) {

        echo "<h2>Create main user who has ALL the power</h2>\n";
        echo "<form method=\"post\" action=\"?act=add\">";
        echo "username:     <input name=\"u\" value=\"demo\"></input>";
        echo "<br>password: <input name=\"p\" value=\"user\"></input>";
        echo "<br><input type=\"submit\"/>";
        echo "</form>";

    } else {
    
        echo "<p class=\"pass\">Main user is created!</p>\n";
        echo "<div><h2>The IDA-server is now installed!</h2>\n";
        echo "<p>Continue to <a href='../../../introduction/'>introduction application</a>!</p></div>\n";
    }
            


    ?>
    
    </div>
</body>

</html>
