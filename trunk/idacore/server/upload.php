<?php
/**
 *upload.php
 *- detects file types (uses unix file-command)
 *- saves file with new name (date + uid)
* @package ida
*/


ob_start();

require_once('config.php');
require_once('class.IdaDB.php');
require_once('class.IdaUpLoader.php');


if(IdaSession::checkSession()) {
        

    try {
        $up = new IdaUpLoader();
        $fileName = $up->upLoad();

    } catch (Exception $e)  {
        ob_end_clean();
        echo 'Error:';
        echo $e->getMessage();
        die();
    }

    ob_end_clean();
    echo $fileName;
        
} else {
    $msg = "<error>You are not logged in!</error>";
    echo $msg;
    //IdaXMLIO::sendXML($msg);
}            



?>
