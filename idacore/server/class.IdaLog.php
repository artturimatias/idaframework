<?php
/**
 * IdaLog
 * @package ida
 */

 /**
 * static logging class
 * @package ida
 */
class IdaLog {

    // save insert xml as a file
    static function saveXML($xml, $id) {
    
        $path = $_SERVER['DOCUMENT_ROOT']._APP_DATA_PATH.'xml/inserts/';
        if (is_writable($path)) {
/*
            $fileName = "insert_".$id.".xml";
            $handle = fopen($path.$fileName, "x");

            if (fwrite($handle, $xml) === FALSE) {
                throw new Exception('file write failed!');
         
            } 
  */          
        } else {
            // We don't mind if we can not save xml-file
            //throw new Exception('xml directory not writable!');
        }
    }

    // save edit xml as a file
    static function saveEditXML($xml, $id, $act) {
   
        $recordId = (int)$id;
/*
        // get number of edits
        $editCount = IdaDb::select("_sys_eventlog", array("record_id"=>$recordId), " count (id) ", "", "onecell");
        $editCount = $editCount + 1;
        $path = $_SERVER['DOCUMENT_ROOT']._APP_DATA_PATH.'xml/edits/';
        if (is_writable($path)) {

            $fileName = $id."_edit_".$editCount.".xml";
            $handle = fopen($path.$fileName, "x");

            if (fwrite($handle, $xml) === FALSE) {
                throw new Exception('file write failed!');
         
            } else {
                IdaLog::eventLog($act, $recordId, $fileName);
            }
            
        } else {
            // We do mind if we can not save xml-file
            throw new Exception('xml directory not writable!');
        }
*/        
    }

    static function writeXML2Disk($dir,$fileName) {

        $path = $_SERVER['DOCUMENT_ROOT']._APP_DATA_PATH.'cache/';
        if (is_writable($path)) {

            $fileName = $fileName.".xml";
            $handle = fopen($path.$fileName, "x");

            if (fwrite($handle, $xml) === FALSE) {
                throw new Exception('file write failed!');
         
            } else {
                IdaLog::eventLog($act, $recordId, $fileName);
            }
            
        } else {
            // We do mind if we can not save xml-file
            throw new Exception('cache directory not writable!');
 

        }
    }

    static function eventLog($act, $recId, $fileName) {

        $inserts = array(
            "act"=>$act,
            "record_id"=>$recId,
            "xmlfile_id"=>$fileName,
            "http_user_agent" =>  $_SERVER['HTTP_USER_AGENT'],
            "remote_addr" => $_SERVER['REMOTE_ADDR']
        );

        return IdaDB::insert("_sys_eventlog", $inserts);

    }
    
    static function errorLog($message) {

        $inserts = array(
            "error" => $message,
            "http_user_agent" =>  $_SERVER['HTTP_USER_AGENT'],
            "remote_addr" => $_SERVER['REMOTE_ADDR'],
            "request_uri" => $_SERVER['REQUEST_URI']

        );
        
        IdaDB::insert("_sys_errorlog", $inserts);
    }

    static function seedLog($seed) {

        $inserts = array(
            "act" => "loginseed",
            "seed" => $seed,
            "http_user_agent" =>  $_SERVER['HTTP_USER_AGENT'],
            "remote_addr" => $_SERVER['REMOTE_ADDR']
        );
        
        return IdaDB::insert("_sys_eventlog", $inserts);
    }
    
}

?>
