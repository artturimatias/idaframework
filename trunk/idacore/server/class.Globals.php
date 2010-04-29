<?php

//$G_propertyTitles = IdaDB::getPropertyTitles();
$CRM = new DomDocument('1.0', 'UTF-8');
$CRM_p = new DomDocument('1.0', 'UTF-8');

// check if classes and properties are overridden
$root = $_SERVER['DOCUMENT_ROOT'];
$arr_path = explode("/", $_SERVER['PHP_SELF']);
unset($arr_path[count($arr_path)-1]);
unset($arr_path[count($arr_path)-1]);
$path = implode($arr_path,"/");

if(file_exists($root.$path.'/classes.rdfs')) {
    $classes_rdfs = $root.$path.'/classes.rdfs';
} else {
    $classes_rdfs = '../classes.rdfs';
}

if(file_exists($root.$path.'/properties.rdfs')) {
    $properties_rdfs = $root.$path.'properties.rdfs';
} else {
    $properties_rdfs = '../properties.rdfs';
}

if(file_exists($root.$path.'/templates.xml')) {
    $templates_xml = $root.$path.'templates.xml';
} else {
    $templates_xml = '../templates.xml';
}



if(!$CRM->Load($classes_rdfs, LIBXML_NOBLANKS))
    die("file not found");
if(!$CRM_p->Load($properties_rdfs, LIBXML_NOBLANKS))
    die("file not found");

$G_xpath = new DOMXPath($CRM);
$G_xpath_p = new DOMXPath($CRM_p);

$G_classNames = Globals::getIDs("//rdfs:Class", $G_xpath);
$G_propertyNames = Globals::getIDs("//rdf:Property", $G_xpath_p);

// combined global variable and cache object
class Globals {

  function __construct() {
    $this->tableInfo = IdaDB::getTableNames();
    $this->tableData = IdaDB::getTableInfo();
    
    // global object counter
    $this->objectCount = 0;
    
    $this->buildTableInfo();

  }
    static function getIDs($path, $xpath) {

        $res = $xpath->query($path);
        foreach($res as $node) {
            $name = $node->getAttribute("rdf:ID");
            $ex = explode(".", $name);
            $arr[$ex[0]] = $ex[1];
        }
        return $arr;

    }
 
    
    
    private function buildTableInfo() {
    
	foreach($this->tableData as $data) {
	    $this->tableInfo[$data["tbl_id"]]["columns"][$data["title"]] = $data;
//	    $this->tableInfo[$data["tbl_id"]]["columns"][$data["title"]]["action"] = $this->tableInfo[$data["tbl_id"]]["action"]; 
	    
	    }
    
    }
 


    function getUniqueFlag ($classId) {

       // Debug::printArray($this->classNames);
        //return $this->classNames[(int)$classId]["is_unique"];
    }

    function getTableName ($tableId) {

	return array("id"=>$tableId,"title"=>$this->tableInfo[$tableId]["title"], "shortcut"=>$this->tableInfo[$tableId]["shortcut"]);
    }
    
    function getTableId ($tableName) {

	foreach($this->tableInfo as $key=>$value) {
	    if($value["title"] == $tableName)
		return array("id"=>$key,"title"=>$tableName, "shortcut"=>$value["shortcut"]);
	}
    }    
  
  
   function getTypeId ($typeName) {

        foreach($this->typeNames as $key=>$value) {
            if($value["title"] == $typeName)
                return array("id"=>$key,"title"=>$value);
        }
    } 
  
}

// from:http://www.developertutorials.com/tutorials/php/php-singleton-design-pattern-050729/page4.html
class singleton
{

    function getInstance ($class)

    // implements the 'singleton' design pattern.

    {
        static $instances = array();  // array of instance names

        if (!array_key_exists($class, $instances)) {

            // instance does not exist, so create it

            $instances[$class] =& new $class;

        } // if

        
        $instance =& $instances[$class];

        return $instance;

    } // getInstance    

} // singleton


// create one global object
$G = singleton::getInstance("Globals");

?>
