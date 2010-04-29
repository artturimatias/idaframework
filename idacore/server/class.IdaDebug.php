<?php
/**
 * class.IdaDebug.php
 * @package ida
 */


/**
    * A class for debug printing
    * @package ida
*/
class Debug {

    static function writeDebugXML(&$node, $time_start) {
        global $qCounter;
        $time_end = microtime(true);
        $time = $time_end - $time_start;
        $node->setAttribute("mem",number_format(memory_get_usage()));
        $node->setAttribute("query_count",$qCounter);
        $node->setAttribute("time",sprintf("%01.4f",$time));
  
    }

    static function memoryUsage() {
        if(DEBUG) {
            echo '<p><strong>';
            echo "Memory used: ".number_format(memory_get_usage())." bytes";
            echo '</strong></p>';
        }
    }

    static function printMsg ($msg) {

        if(DEBUG) {
            echo $msg;
        }
    }

    static function printPara ($msg) {

        if(DEBUG) {
            echo "<p>";
            echo $msg;
            echo "<p>";
        }
    }

    static function printDiv ($msg, $color="white") {

        if(DEBUG) {
            if($color)
                $divStyle = 'style="background-color:'.$color.'"';
            echo "<p $divStyle >";
            echo $msg;
            echo "<p>";
        }
    }

    static function printArray($arr, $title="-") {

        if(DEBUG) {
            echo '<p><strong>'.$title.'</strong></p>';
            echo '<pre>';
            print_r($arr);
            echo '</pre>';
        }
    }

    static function printXML($xml, $title="-") {

        if(DEBUG) {
            echo '<p><strong>'.$title.'</strong></p>';
            echo '<textarea style="height:200px;width:300px">'.$xml->asXML().'</textarea>';

        }
    }

    static function printTextarea($text, $title="-") {

        if(DEBUG) {
            echo '<p><strong>'.$title.'</strong></p>';
            echo '<textarea style="height:200px;width:600px">'.$text.'</textarea>';

        }
    }

    static function printIframe($text, $title="-") {

        if(DEBUG) {
            if(IdaSession::checkSession()) {
                $_SESSION["iframe_content"] = $text;
            }
            $int = rand(0, 10000);
            echo '<p><strong>'.$title.'</strong></p>';
            echo '<iframe src="debug.php?cache='.$int.'" style="height:40em;width:600px"></iframe>';

        }
    }

    static function debugPage() {

        echo "<html>\n<head>\n";
        echo '<link rel="stylesheet" type="text/css" href="../css/initStyle.css" /><body>'."\n";
        echo "<h1>IDA-Debug</h1>\n";


    }

}


?>
