<?php
require_once('class.IdaSession.php');
require_once('class.IdaLog.php');
require_once('class.IdaDebug.php');
/**
 * @package ida
 */

 /**
 * static class that handles sending of xml files
 * @package ida
 */
Class IdaXMLIO {

    /**
    * handles xml requests and creates XML based on request
    * - template
    * - get
    * - search
    * - uploads
    * - linkboard
    */
    static function xmlout($action) {


        switch($action) {





            default :
                throw new Exception('Illegal action!');

                break;

        }


    }

    // sends ready-made xml
    static function sendAsXML($xml, $skip=0, $md5=0) {

        if($md5)
            IdaXMLIO::writeCacheFile($md5, $xml);

        // save xml to session for debug.php
        if(IdaSession::checkSession()) {
            $_SESSION["iframe_content"] = $xml;
        }

        // if debugging, then print to iframe
        if(DEBUG) {

            Debug::printIframe($xml, "Result");

            // send a debug notice to client
          //  ob_end_clean();
          //  header("Content-Type: text/xml");
          //  echo "<error>DEBUG-MODE! </error>";

        } else {

            ob_end_clean(); // used HOURS for this!!!!
            header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
            header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
            header("Content-Type: text/xml");
            echo $xml;

        }
    }


    // add root tags and send
    static function sendXml($result, $tag="root", $skip=0, $count=0, $md5=0) {

        $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>";
        $xml .= "<".$tag;
       $xml .=  " count=\"".$count."\">";
        $xml .= $result;
        $xml .= "</".$tag.">";

        IdaXMLIO::sendAsXML($xml, $skip, $md5);

    }

    static function sendError($error) {

        IdaLog::errorLog($error->getMessage());

        if(DEBUG) {
            echo $error;
        } else {
            ob_end_clean();
            header("Content-Type: text/xml");
            echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>";
            echo "<root>";
            echo '  <response status="error">';
            echo "    <error>";
            echo         $error->getMessage();
            echo "    </error>";
            echo "  </response>";
            echo "</root>";
        }
    }


    static function write() {
        $myFile = "xml.txt";
        $fh = fopen($myFile, 'w') or die("can't open file");
        $stringData = stripslashes($_POST['xml']);

        fwrite($fh, $stringData);
        fclose($fh);
    }


    static function display_xml_error($error, $xml)
    {
        //$return  = $xml[$error->line - 1] . "\n";
        $return = str_repeat('-', $error->column) . "^\n";

        switch ($error->level) {
            case LIBXML_ERR_WARNING:
                $return .= "Warning $error->code: ";
                break;
            case LIBXML_ERR_ERROR:
                $return .= "Error $error->code: ";
                break;
            case LIBXML_ERR_FATAL:
                $return .= "Fatal Error $error->code: ";
                break;
        }

        $return .= trim($error->message) .
                "\n  Line: $error->line" .
                "\n  Column: $error->column";

        if ($error->file) {
            $return .= "\n  File: $error->file";
        }

        return "$return\n\n--------------------------------------------\n\n";
    }


    static function writeCacheFile($md5, $content) {

        $path = $_SERVER['DOCUMENT_ROOT']._APP_DATA_PATH.'cache/';

        if (is_writable($path)) {

            $fileName = $md5.".xml";
            $handle = fopen($path.$fileName, "w");

            if (fwrite($handle, $content) === FALSE) {
                throw new Exception('file write failed!');
            } 
            
        } else {
            // We do mind if we can not save xml-file
            throw new Exception('cache directory not writable!');
        }
 
        $time  = date('Y-m-d H:i:s');
        $cache = IdaDB::select("_sys_cache", array("id"=>$md5),array("id"),"","onecell");
        if(empty($cache))
            IdaDB::insert("_sys_cache", array("id"=>$md5), 1);
        else
            IdaDB::update("_sys_cache", array("timestamp"=>$time), "id", $md5);


    }

    static function getCacheFile($md5) {
// EXIT HETI!!
        throw new Exception('Cache file not found');
        $path = $_SERVER['DOCUMENT_ROOT']._APP_DATA_PATH.'cache/';

        $latestCache = IdaDB::select("_sys_cache", array("id"=>$md5),array("timestamp"),"","onecell");
        $latestEdit = IdaDB::select("_sys_eventlog", array(),array("MAX(timestamp)"),"","onecell");

//exit("cache:".$latestCache." md5:".$md5);

        // if timestamp in newer thatn latest edit, send cache file
        if($latestCache > $latestEdit) {

            if(file_exists($path.$md5.".xml")) {
                return file_get_contents($path.$md5.".xml");
            } else {
                throw new Exception('File not found');
            }
        }

        throw new Exception('Cache file not found');
    }


}

?>
