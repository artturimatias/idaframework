<?php
/**
 * class.IdaClass.php
 * @package ida
 */

/**
 * Constants
 */
// define table ids for main classes
define("ACTOR", 39);
define("EVENT", 5);
define("THING", 22);
define("PLACE", 53);
define("PERSON", 21);

/**
    * Simple factory in order to avoid some if/elses
    * @package ida
*/
class DataNode
{
    // The parameterized factory method
    public static function factory($node)
    {
        
        if ($node->hasAttribute("name")) {
            return new IdaTable($node->getAttribute("name"));
        } else {
            return new IdaRecord($node->tagName);
        }

    }
       
}

/**
    * Abstract base class
    * @package ida
*/
abstract class IdaClass {
    public $classId;
    abstract public function getClassInfo($className);
    abstract function parseDataFromXML(&$xml);
    abstract function save();
    abstract function link($domainId);

    function searchByXML() {
        return array();
    }

    function hasErrors() {
        return count($this->errors);
    }

    function getErrors() {
        if(count($this->errors, COUNT_RECURSIVE))
            return $this->errors;
        else
            return null;
    }

    function isEmpty() {
                
        return !count($this->data);
    }

    public function isRequired($node) {
        $class = get_class($node);
/*
        // check if certain property is required
        foreach($this->template as $temp) {
            $prop = $temp["property"].$temp["property_dir"];

            if($class == "IdaRecord" && $prop == $node->linkId && $temp["required"] == 1) {
                return 1;

            } else if($class == "IdaTable" && $temp["target"] == $node->tableId && $temp["required"] == 1) {
                return 1;

            }
        }
*/
        return 0;

    }
    
 
    // IdaTable has it's own isJoin
    public function isJoin($matches, $similarObjets, $linkName) {
        return IdaDb::isJoin($matches, $similarObjets, $linkName);
    }

   
    public function markIsRequired($node) {

    // we found it so let's mark it as non-required
        $class = get_class($node);
        
        foreach($this->template as &$temp) {
            $prop = $temp["property"].$temp["property_dir"];
            
            if($class == "IdaRecord" &&  $prop == $node->linkId ) {
                $temp["required"] = 0;

            } else if($class == "IdaTable" && $temp["target"] == $node->tableId) {
                $temp["required"] = 0;
            }
        }

    }
}

?>
