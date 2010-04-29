<?php
/*
 *      This program is free software; you can redistribute it and/or modify
 *      it under the terms of the GNU General Public License as published by
 *      the Free Software Foundation; either version 2 of the License, or
 *      (at your option) any later version.
 *      
 *      This program is distributed in the hope that it will be useful,
 *      but WITHOUT ANY WARRANTY; without even the implied warranty of
 *      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *      GNU General Public License for more details.
 *      
 *      You should have received a copy of the GNU General Public License
 *      along with this program; if not, write to the Free Software
 *      Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 *      MA 02110-1301, USA.
 */

/**
 * idarecord.php
 * @package ida
 */


/**
 * Constants
 */
define("DEBUG", 0);
define("SQL_DEBUG", 0);

define("TIME_SPAN_TABLE", 4);


require_once('class.IdaClass.php');
require_once('class.IdaTable.php');
require_once('class.IdaDebug.php');
require_once('class.IdaDB.php');
require_once('class.Globals.php');
require_once('class.IdaXML.php');
require_once('class.Template.php');




$wtime = 0;
$qCounter = 0;


/**
    * This is the main class of IDA
    * - instance is created by class/type name or class id
    * - example:
    * <code>
    *    $rec = new IdaRecord('Person');
    *    $rec = new IdaRecord(array("class_id"=>1,"type_id"=>4));
    * </code>
    * @package ida
*/

class IdaRecord extends IdaClass {
    function __construct($classId) {

        $this->G = singleton::getInstance("Globals");
        global $G;
        $this->indexWords = true;
        $this->isEditable = false;
    
        if(is_array($classId)) {
            
            $this->getClassInfo($classId["class_id"]);
            
        } else 
            $this->getClassInfo($classId);

        if(!$this->classId)
            throw new Exception('Class '.$classId.' not found!');

        $this->foundRecs = array();
        $this->errors = array();
        $this->G->objectCount++;
    }



    /**
        * fetch id number and title of class
    */
    public function getClassInfo($classId) {

        $res = XML::getClassID($classId);

        if(!$res) {
            $res = XML::attributeQuery("//rdfs:Class", "rdf:ID", $classId, "rdf:ID", $this->G->CRM);
        }
        $this->classId = $res["id"];
        $this->className = $res["title"];
        $this->classNameFull = implode(".", $res);;
        $this->unique = $this->G->getUniqueFlag($this->classId);
        $this->mapId = 0;
     

    }


/* *******************************************************************
 *                         TEMPLATES 
 * *******************************************************************
 */ 
 
    public function makeXMLInputGrouped($dom, $par=0, $withTables=0) {


        $templates = Template::getInputTemplate($this);
        $objNode = XML::initGetXML($this, $dom);

       foreach($templates as $t) {
            // DATA --- write table data to xml
            $tables = $t->getElementsByTagName("table");
            if($t->hasAttribute("table")){

                $objNode->appendChild($dom->importNode($t, true));
                $idatable = new IdaTable($t->getAttribute("table"));
                $idatable->makeXmlInput($objNode->lastChild, $withTables);

            } else if($t->getAttribute("action") == "create_by_default") {

                $objNode->appendChild($dom->importNode($t, true));
                $instance = new IdaRecord($t->getAttribute("class"));
                $x = $instance->makeXMLInputGrouped($dom, $this, $withTables);
                $objNode->lastChild->setAttribute("act", $t->getAttribute("action"));
                $objNode->lastChild->appendChild($x);
           

            } else {

                $objNode->appendChild($dom->importNode($t, true));

            }

            //echo $t->getAttribute("title");
        }

        return $objNode;
    }


  /* *******************************************************************
 *                         SAVING 
 * *******************************************************************
 */ 


    /**
    * validates xml and searches id's for subnodes
    * - $this->data holds parsed objects
    * - nothing is saved in this stage
    * @return null
    */

    public function parseDataFromXML(&$xml, $templateCheck=1) {

        Debug::printMsg('<li><i>'.$this->className.'</i> (parsing from XML)</li><ul style="margin-left:2em">');

        // xml backup
        $this->logXML = new DomDocument("1.0", "UTF-8");
        $this->logXML->appendChild ($this->logXML->createElement($this->className));
        $this->logXMLData = $this->logXML->createElement("data");
        $this->logXMLLinks = $this->logXML->createElement("links");
        $this->logXML->firstChild->appendChild($this->logXMLData);
        $this->logXML->firstChild->appendChild($this->logXMLLinks);

        $this->path = XML::pathToClass($this->className, array());
        $this->template = Template::getInputTemplate($this);
        $this->xmlId = $xmlId;
        $this->data = array();

       foreach ($xml->childNodes as $link) {
           
            Debug::printMsg('<li><span style="background-color:gray; color:white">LINK: '.$link->tagName.'</span>');

            foreach($link->childNodes as $child) {

                // skip text nodes
                if($child->nodeType == 3)
                    continue;
                    
                $linkId = XML::getPropertyID($link->tagName);
                $linkFull = $linkId["id"].$linkId["dir"].".".$linkId["title"];
               // if($this->className == "Place" && $linkId == "P89B")
                 //       throw new Exception('Parts must be added to whole!');
                $node = DataNode::factory($child);
                $node->linkId = $linkId;
                $node->indexWords = $this->indexWords;

                // check link validity
                Template::checkLinkRange($this, $linkFull, $node);

                // check for conversion id (for imports)
                if($child->hasAttribute("map_id")) {
                    if ($child->getAttribute("map_id") != "0") {
                        $rec_id = IdaDb::getIdByMapId($child->getAttribute("map_id"));
                        $node->id = $rec_id;
                        $this->data[] = $node;
                    } else
                        throw new Exception('Invalid map_id!');

                } else if($child->hasAttribute("id")) {
                    if (ctype_digit($child->getAttribute("id")) && $child->getAttribute("id") != "0") {
                        $node->id = $child->getAttribute("id");
                        $this->append($node);
                    } else
                        throw new Exception('Invalid id!');

                } else {

                    // if node is not an event, try to find ID (table gives empty array)
                    if ($node->unique)
                        $matches = $node->searchByXML($child);
                    else
                        $matches = 0;

                    // if we did not found any matches, then create a record
                    if(empty($matches)) {

                        // load and check data from xml
                        $node->parseDataFromXML($child, $templateCheck);
                        $this->append($node);

                    // else if we found just 1 match then we can link to it
                    } else if(count($matches) == 1) {

                        $node->id = $matches[0];
                        $this->data[] = $node;
                        Debug::printMsg('<li><span style="color:green">found ID! ('.$node->id.')</span> </li>');

                    // if we have multiple matches then stop the show
                    } else {
                        throw new Exception('Can not link! Multiple matches found!');
                    }
                }

                }
            Debug::printMsg('</li>');
        }



    // check if required values are still missing
    /*
    $templateCheck = 0;
    if($templateCheck) {
        Debug::printPara("Checking missing things against template:");
        foreach($this->template as $temp) {
            if($temp["required"]) {
                if($temp["target_type"] == "type") {
                    Debug::printMsg('<span style="color:red">MISSING '.$temp["property_title"].$this->G->typeNames[$temp["target"]]["title"].'!</span>');                    
                    $this->errors[] = 'required value missing: '.$temp["property_title"].": ".$this->G->typeNames[$temp["target"]]["title"];      

                }else if($temp["target_type"] == "table") {
                    
                    $this->errors[] = 'required value missing: '.$temp["property_title"];

                } else {
                    Debug::printMsg('<span style="color:red">MISSING '.$this->G->classNames[$temp["target"]]["title"].'!</span>');
                    $this->errors[] = 'required value missing: '.$temp["property_title"].": ".$temp["target_table"].$this->G->classNames[$temp["target"]]["title"];
                }
            }
        }
    }
    */



    if(DEBUG) {
        echo '<li><span style="background-color:green; color:white">Done Parsing of object:</span>'.$this->className;
        echo '<br>Found '.count($this->errors, COUNT_RECURSIVE).' errors</li></ul>';
        echo '<pre>';
        print_r($this->errors);
        echo '</pre>';
    }

}

    /**
    *   saves whole record by iterating over $this->data
    * - writes also type hierarchy
    */
    function save() {


        Debug::printMsg('<h2>Saving '.$this->className.'</h2>');

        if($this->hasErrors())
            throw new Exception('Can not save! Errors found!'.print_r($this->errors));
                          
        // make record id
        if($this->insertMe()) {
            Debug::printMsg('<p>InsertMe() gave: '.$this->id.'</p>');
        } else {
            Debug::printMsg('<p><span style="color:red">Record is empty!</span></p>');
            return 0;
        }

        // save records (if needed) and link them
        if(is_array($this->data)) {
            foreach($this->data as $obj) {
                $obj->save();
                $obj->link($this);
            }
        }

        // if this is Place, then add it to place hierarchy (under 0 node by default)
        $val = XML::order($this->classNameFull);
        if($val) {
            IdaDb::insert2PreOrderedTree("_sys_".$val."order", $this->id, 0);
        }
       
        $this->logXML->firstChild->setAttribute("id", $this->id);
        IdaLog::saveXML($this->logXML->saveXML(), $this->id);
        return $this->id;
        
    }



    public function link($domain) {

        Debug::printMsg("<h2>Linking("
            .$domain->className."[".$domain->id."]->"
            .$this->linkId." "
            .$this->className."[".$this->id."])</h2>");

        if(is_array($this->linkId))
            IdaDB::linkRecordToRecord($domain, $this);
        else
            throw new Exception('cannot call link-function without setting link!');
       
        // we write log xml only if we are inserting
        if($domain->logXMLLinks) {
            $domain->logXMLLinks->appendChild($domain->logXML->createElement($this->linkId["title"]));
            $linkTarget = $domain->logXML->createElement($this->className);
            $linkTarget->setAttribute("id", $this->id);
            $domain->logXMLLinks->lastChild->appendChild($linkTarget);
        }

    }



/* *******************************************************************
 *                         GETTING DATA
 * *******************************************************************
 */ 

 /*


     private function createXMLRecursive2(&$domNode, $group, $round) {

        if(is_array($this->records[$group])) {
            foreach($this->records[$group] as $link) {

                if($link["subject"] == $this->id) {           
                    $property = $domNode->ownerDocument->createElement(Template::getFullLinkName($link));
                    $domNode->appendChild($property);

                    $record = new IdaRecord($link);
                    $record->id = $link["id"]; 
                    $record->records = & $this->records;
                    $record->tables = & $this->tables;      

                $p = Template::getFullLinkName($link,".");
                $typeName = $this->tables["appellation"]->rows[$link["id"]][0]["name"];
                $property->setAttribute($p, $typeName);


                    
                    $record->getLinkedXML($property, $this->id, $round);

                }
            }
        }

     }
*/

    /**
    * - load data based on result set
    * - 2 rounds (should be enough)
    * @param integer $id
    * @param DomNodeList resultTemplateXML
    */
    public function loadResult($id, $resultTemplateXML) {

        $this->records = array();
        $this->id = $id;

        echo __FUNCTION__;

        foreach($resultTemplateXML as $property) {

            echo "<li>load result loop link=".$property->tagName."</li>";
            $this->loadLinks($property, array($this->id));

        }

        Debug::printArray($this->records, "records");

        // create tables and fetch data
        $this->createTables();
        $this->fetchTableData();
        //die("<br>--loadresult");
    }


    private function loadLinks($linkNode, $idArray, $masterId=0) {


        if($linkNode->nodeType == 3)
                return;

        $link = XML::getPropertyID($linkNode->tagName,0);
       Debug::printMsg( "<br> link=".$link["id"].$link["title"]);

        // if property was found, then get links
        if(!empty($link)) {

            if($link["dir"] == 'B')
                $target = "subject";
            else 
                $target = "property";
               
            // get instanes that are linked here with current property 
            $result = IdaDb::getLinkedByGroup2($target, $idArray,array($link["id"]), "time_span", array("start_year"), array($masterId));


    //    Debug::printArray($result, "result");
            if($linkNode->hasChildNodes()) {

                // get only ids from array
                $idArray = IdaTable::get_by_key("id", $result);
                foreach($linkNode->childNodes as $subNode) {

                    $class = XML::getClassID($subNode->tagName, 0);
                    // if this node is a class, then dive deeper
                    if(!empty($class)) {
                        echo "going deeper";
                        foreach($subNode->childNodes as $subProp) {
                            echo $subProp->tagName;
                            if($subProp->nodeType == 3)
                                continue;
                            $this->loadLinks($subProp, $idArray, $this->id); 
                        }
                    } else {

                        //$this->loadLinks($subNode, $idArray, $this->id); 
                    }
                }

            }

            if(!empty($result))
                $this->records[] = $result;

            unset($result);
        }
    }


    static function loadResult_testi($resultTemplateXML, &$levelData) {

        // load all linked records to temp table
        foreach($resultTemplateXML as $property) {

            IdaRecord::mene($property, 1, $levelData);

        }

    }

    // load links
    static function mene($property, $level, &$levelData) {

        if(!$property)
            return;

        if($property->nodeType != 3 ) {

            $level2 = $level + 1;
            IdaRecord::loadLinks_testi($property, "level".$level, "level".$level2, $level, $levelData);

            if($property->hasChildNodes()) {

                foreach($property->childNodes as $subProperty) {

                   IdaRecord::mene($subProperty, $level + 1, $levelData);
                }


            } else {

            }
        }
    }


    static function loadLinks_testi($property, $sourceTable, $targetTable, $level, &$levelData) {

        $link = XML::getPropertyID($property->tagName);
        $attrs = $property->attributes->length;


        for($i=0; $i<$attrs;$i++) {
            $attr = $property->attributes->item($i)->name;
            $levelData[$level][] = array("col"=>$property->getAttribute($attr),"link_id"=> $link['id']); 
        }



        if($level == 0)
            return;

        if($link["dir"] == 'B')
            $target = "subject";
        else 
            $target = "property";
            
        IdaDb::getLinkedRecordsByLink($target, array($link["id"]), $sourceTable, $targetTable, "appellation", array("name"));

    }


    static function teeXML($dom, $xmlNode, $levelData, $hideLinks=0 ) {



            $level1 = IdaDB::select("level1", array(), array("id","subject","link_type","link_dir","class_id"),"","all");
            $level2 = IdaDB::select("level2", array(), array("id","subject","link_type","link_dir","class_id"),"","all");
            $level3 = IdaDB::select("level3", array(), array("id","subject","link_type","link_dir","class_id"),"","all");
            $level4 = IdaDB::select("level4", array(), array("id","subject","link_type","link_dir","class_id"),"","all");
            $level5 = IdaDB::select("level5", array(), array("id","subject","link_type","link_dir","class_id"),"","all");

            $ehtoo [] = $level1;
            $ehtoo [] = $level2;
            $ehtoo [] = $level3;
            $ehtoo [] = $level4;
            $ehtoo [] = $level5;

            foreach($level1 as $key=>$val) {
                IdaRecord::koe($dom, $xmlNode,$key, $ehtoo, 0, $levelData, $hideLinks);
            }



    }


    static function koe($dom, $xmlNode, $id, $ehtoo, $level, $levelData, $hideLinks=0) {

            if(isset($ehtoo[$level][$id])) {

                $prev = $dom->createElement("holder");
                foreach($ehtoo[$level][$id] as $sub) {

                    $class = XML::getClassId($sub['class_id']);
                    $node = $dom->createElement($class['title']);
                    $node->setAttribute("id", $sub['subject']);
                    if(is_array($levelData[$level])) {
                        foreach($levelData[$level] as $le) {
                            if(is_array($le)) {
                                if(isset($le['col'])) {
                                    if($le['link_id'] == $sub['link_type']) {
                                     //$le["data"]->pickDataAsXML($node, $sub['subject']);
                                     $le["data"]->pickDataAsAttribute($node, $sub['subject']);
                                    }
                                }
                            }
                        }
                    }
                    
                    // showing links?
                    if($hideLinks) {

                        $xmlNode->appendChild($node);

                    } else {

                        if(isset($sub["link_type"])) {
                            $lid = XML::getPropertyID($sub["link_type"].$sub["link_dir"]);
                            // group properties
                            if($prev->tagName != $lid["title"]) {
                                $property = $dom->createElement($lid["title"]);
                                $xmlNode->appendChild($property);
                                $property->appendChild($node);
                            } else {
                                $prev->appendChild($node);
                            }
                            $prev = $property;

                        // if link_type is not set, then this is root level
                        } else {
                            $xmlNode->appendChild($node);
                        }
                    }


                    IdaRecord::koe($dom, $node, $sub['subject'], $ehtoo, $level+1, $levelData, $hideLinks);
                
                }
            }


    }

    /**
    * - creates xml from record data
    * - heuristicLoad must be called first
    * @param DomDocument $xml
    * @return DomNode
    */
    function getGroupedXML($dom, $round=0) {

        $round++;
        $groups = array();
        $nodes = array();

        Debug::printMsg("getGroupedXML=".$this->className.":".$this->id);

        $objNode = XML::initGetXML($this, $dom);
        $dom->appendChild($objNode);
  //      if($this->isEditable) {
            $this->getTableXML($objNode, $this->id, $this->isEditable);

    //    }  
/*
        foreach($this->tables as $table) {

            $table->pickDataAsAttribute($objNode, $this->id);
        }
*/

        // add files
        if(array_key_exists($this->id, $this->tables["file"]->rows)) {
            $fileNode = $dom->ownerDocument->createElement("files");
            $objNode->appendChild($fileNode);
            foreach($this->tables["file"]->rows[$this->id] as $fileRow) {
                $fNode = $dom->ownerDocument->createElement("file");
                $fNode->setAttribute("filename", $fileRow["filename"]);
                $fNode->setAttribute("extension", $fileRow["extension"]);
                $fileNode->appendChild($fNode);
            }
        }


/*        // add short note 

       // if($round > 1) {
            if(is_array($this->tables["note"]->rows[$this->id])) {
                $note = $this->tables["note"]->rows[$this->id][0]["message"];
                $objNode->setAttribute("has_note",sprintf("%."._SHORT_NOTE_LENGTH."s", $note));
            }
       // }
  */  
        // last defence for never ending recursion
        if($round < 20)
            $this->createXMLRecursive($objNode, $round);
     
       // die("<br>--getgroupedxml");
    }



     private function createXMLRecursive(&$domNode, $round) {

            $prevTitle = "";
            foreach($this->records as $row) {

                foreach($row as $link) {

                    $lid = XML::getPropertyID($link["link_type"].$link["link_dir"]);
                    $linkTitle = $lid["title"];

                    if($link["subject"] == $this->id) {           
                        if($prevTitle != $linkTitle) {
                            $property = $domNode->ownerDocument->createElement($lid["title"]);
                            $domNode->appendChild($property);
                            $prevTitle = $linkTitle;
                        }

                        $class = XML::getClassID($link["class_id"]);
                        $record = new IdaRecord($class["id"]);
                        $record->id = $link["id"]; 
                        $record->records = & $this->records;
                        $record->tables = & $this->tables;      
                        // TODO HACK: shorten images
                        if($record->className != "Digital_Image")
                            $record->isEditable = $this->isEditable;      
                        
        Debug::printPara("diving into property=".$linkTitle);

                        if(!in_array($record->id, $prevIds))
                            Debug::printArray($prevIds);
                            $record->getGroupedXML($property, $round);
                    }
                }
            }

     }


     private function createXMLAttribute(&$links, &$dom, &$objNode) {

 
        if(is_array($links)) {
            foreach($links as $link) {

            if($link["subject"] == $this->id) {           

                $property = Template::getFullLinkName($link,".");
                $typeName = $this->tables["appellation"]->rows[$link["id"]][0]["name"];
                $objNode->setAttribute($property, $typeName);

                }
            }
        }
    }
       


// **********************************************************
//  TEST CODE -- TEST CODE -- TEST CODE
// ******************************************************




    /**
        * get class or type name based of record id
        * @param integer $rid record id
        * @return string 
    */
    static function getClassNameFromRecordId($rid) {

        if(!ctype_digit(trim($rid)))
            throw new Exception("Invalid record ID!");
        

        $id = IdaDB::select("_sys_classes_join", array("subject"=>$rid), "property","","onecell");
        
        if(!isset($id))
            throw new Exception('No classname found for record!');

        $res = XML::getClassID($id);

        return $res;
    }


/* *******************************************************************
 *                         EDITING DATA
 * *******************************************************************
 */ 



    public function editRow($xml) {

        $recId = $xml->getAttribute('record');
        $rowId = $xml->getAttribute('row');
        $table = new IdaTable($xml->firstChild->getAttribute('name'));

        // we must check that this row belongs to this record
        $rec = IdaDB::checkRowRecId($table, $rowId);

        if($recId == $rec) {

            $table->editRow($xml->firstChild, $rowId);
            $this->id = $recId;

            // update word index
            IdaDB::updateIndex($this, $table);

        }

        return;
    }



    public function addRow($xml) {

        $this->id = $xml->getAttribute('record');
        $table = new IdaTable($xml->firstChild->getAttribute("name"));
        $table->parseDataFromXML($xml->firstChild);
        $link = XML::getPropertyID($xml->getAttribute("link"));
        $table->linkId = $link;
        $linkFull = $link["id"].$link["dir"].".".$link["title"];

        // check that this is legal link
        if(Template::checkLinkRange($this, $linkFull, $table)) {
            $table->save();
            $table->link($this);

        } 

        return;
    }


    function linkRec ($target, $linkId) {

        $link = XML::getPropertyID($linkId);

        // we shall not link to our selves
        if($this->id == $target->id)
            throw new Exception("Cannot link itself!");
            

        $linkFull = $link["id"].$link["dir"].".".$link["title"];
        // check that this is legal link
        if(Template::checkLinkRange($this, $linkFull, $target)) {

            $target->linkId = $link;
            $target->link($this);

        } 
    }


   public function delete () {

        // delete "broader term" property
        if(count($this->data[$this->id])) {
            foreach($this->data[$this->id] as $key=>$prop) {
                if($prop["link"] == "P127F")
                    $broader = $key;
            }
        }
        unset($this->data[$this->id][$broader]);
     
        // if there are still links, we must stop
        if(count($this->data[$this->id])) 
            throw new Exception("Cannot remove because record has links! (remove them first)");

        // remove table data linked to this record
        foreach($this->tables as $table) {
            $table->deleteMyData($this->id);
        }
 
        // remove link to class hierarchy and then the record itself
        IdaDb::deleteBy("_sys_classes_join", array("subject"=>$this->id));
        IdaDb::deleteBy("_sys_records", array("id"=>$this->id));
        
    }
    
/* *******************************************************************
 *                         SEARCHING DATA
 * *******************************************************************
 */ 



    // search from word index
    function quicksearch($search) {

        $count = 1;
        $search = trim($search);
        $search = mb_strtolower($search);
        $search = str_replace(","," ", $search);

        // remove repetative spaces
        $search = preg_replace('/(\S ) +/', '$1', $search);
        $searchArray = mb_split(" ", $search);
        
       // search and write results to temporary table
       IdaDb::searchByName($this, $searchArray);

       // search and write results to temporary table
        if(count($searchArray) > 1) { 
            for($count=0; $count < count($searchArray); $count++) {
                $term = trim($searchArray[$count+1]);
                IdaDb::searchByNameRecursive($count+1, $term);
               
            }
        }

        
       
       // create xml
        return XML::makeQuickSearchXML($count-1);                     
    }



    function searchByXML_new($xml, $table, $recurLevel=0) {

        Debug::printMsg('<h2>Searching '.$this->className.'</h2>');

        // remove empty nodes
        foreach ($xml->childNodes as $link) {
            if($link->nodeType == 3)
                $xml->removeChild($link);
        }

        if($this->checkIds($xml, $recurLevel)) {

            return;

        } else if(!$xml->hasChildNodes()) {

            IdaDB::getAllInstances_new($this, $table);
            return;

        } else {

            try {

                foreach($xml->childNodes as $link) {
                    $this->searchByXML_link($link, $recurLevel);
                }

            // we did not find anything so erase findigs table
            } catch (Exception $e) {
                echo $e->getMessage(); 
                $sql = "DELETE FROM "._DBPREFIX."_".$table;
                IdaDb::exec($sql);
            }
        }

    }

    function searchByXML_link($property, $recurLevel=0) {


        Debug::printMsg('<li><b> '.$this->className.'->'.$property->tagName.'-> </b></li>');

        foreach ($property->childNodes as $class) {

            // remove empty nodes
            foreach ($class->childNodes as $cl) {
                if($cl->nodeType == 3)
                    $class->removeChild($cl);
            }


           //TODO: empty text nodes should be removed 
            if($class->nodeType == 3 )
                continue;

            Debug::printMsg('<b>'.$class->tagName.' </b><br>');
            
            $node = DataNode::factory($class);

            // if search node has id, then we do not have to dive any deeper
            if($this->checkIds($class, $recurLevel+1)) {

                IdaDb::allJoinsClass_new($this, $property->tagName, $node, $recurLevel);

            // if search node is data node, then do not dive any deeper
            } else if(get_class($node) == "IdaTable") {

                $node->searchByXml_new($class,  $this, $recurLevel);

            // if search node has no child nodes, then do not dive any deeper (get all instances)
            } else if(!$class->hasChildNodes()) {

                IdaDb::allJoinsClass_new($this, $property->tagName, $node, $recurLevel);

            // else dive deeper
            } else {

/*
                $level = $recurLevel + 2;
                // create temporary table for records
                $sql = "CREATE TEMPORARY TABLE  
                            "._DBPREFIX."_root_".$level." 
                            (id integer,
                            INDEX(id))";

               IdaDB::exec($sql);
*/
                foreach($class->childNodes as $link) {
                    Debug::printMsg('<b>Diving in '.$link->tagName.'</b>');
                    $node->searchByXML_link($link, $recurLevel+1);
                }
                // check
                IdaDb::allJoinsClass_new($this, $property->tagName, $node, $recurLevel);
/*
                // drop temporary table
                $sql = "DROP TABLE "._DBPREFIX."_root_".$level; 
               IdaDB::exec($sql);
*/
            }
        }
    }


    function searchByXML_deep($xml, $table, $recurLevel=0) {


        // if node has id, then stop searching
       // if($this->checkIds($xml, $recurLevel)) {

         //   return 0;


       // } else {
            foreach ($xml->childNodes as $link) {

               //TODO: empty text nodes should be removed 
                if($link->nodeType == 3 )
                    continue;

                foreach($link->childNodes as $child) {
                    
                    // skip text nodes
                    if($child->nodeType == 3)
                        continue;

                    Debug::printMsg('<li><b> '.$this->className.'->'.$link->tagName.'->'.$child->tagName.' </b></li>');
                    $node = DataNode::factory($child);

                    // if node has id, then do not dive any deeper
                    if($this->checkIds($child, $recurLevel+1)) {

                        $level = $recurLevel + 1;
                        IdaDb::allJoinsClass_new($this, $link->tagName, $node, $table, "root_".$level);

                    } else if(get_class($node) == "IdaTable") {

                        $node->searchByXml_new($child, $table, $this, $link->tagName, "root_".$recurLevel);

                    } else {
                        Debug::printMsg('<b>Diving in </b>');

/*
                        if($node->searchByXml_new($child, $table, $recurLevel+1, $this, $link->tagName, "root_0")) {
                            Debug::printMsg("nodes found<br>");
                            IdaDb::allJoinsClass_new($this, $link->tagName, $node, $table, "root_1");
                        } else {

                            Debug::printMsg("nodes NOT found<br>");
                            IdaDb::allJoinsClass_new($this, $link->tagName, $node, $table);

                        
                        }
                        */
                    }
                }
            }
       // }
    }

    // check if node has id and put it to temp table
    function checkIds($xml, $recurLevel) {


        if($xml->hasAttribute("id")) {

            $foundId = (int)$xml->getAttribute("id");
            Debug::printDiv("Found ID: ".$foundId);
            IdaDb::insert("root_".$recurLevel, array("id"=>$foundId),1,1);
            return $foundId;

        } else if($xml->hasAttribute("map_id")) {

            $mapId = $xml->getAttribute("map_id");
            $res = IdaDb::select("_sys_records", array("map_id"=>$mapId), array("id"),"","onecol");
            if(count($res) != 1) {
                
                throw new Exception("map_id \"".$mapId."\" not found!");
            }

            $foundId = $res[0];
            IdaDb::insert("root_".$recurLevel, array("id"=>$foundId),1,1);
            return $foundId;
         }       

         return 0;
    }

    /**
    * - search records by xml
    * - TODO:sub-parts!!!
    * @return array
    */

    // $findings and classid is for table method
    function searchByXML($xml,$search=0,$findings=0, $classId=0, $linkId=0) {

        Debug::printMsg('<div style="margin-left:2em;border-left:solid 1px grey;padding:1em"><h2>Searching '.$this->className.'</h2>');
        $this->tree = XML::nodeTree($this->classNameFull, array($this->classNameFull));

        // remove empty nodes
        foreach ($xml->childNodes as $link) {
            if($link->nodeType == 3)
                $xml->removeChild($link);
        }

        // if record has no search terms, then return all linked instances

        if($xml->hasAttribute("id")) {

            $foundId = $xml->getAttribute("id");
            Debug::printMsg("Found ID ".$foundId);
            Debug::printMsg('</div>');

            return array($foundId);

        } else if($xml->hasAttribute("map_id")) {

            $mapId = $xml->getAttribute("map_id");
            Debug::printMsg("Found MAP ID ".$mapId);
            Debug::printMsg('</div>');
            $res = IdaDb::select("_sys_records", array("map_id"=>$mapId), array("id"),"","onecol");
            if(count($res) != 1) {
                
                        throw new Exception("map_id \"".$mapId."\" not found!");
            }

            $foundId = $res[0];

            // get place hierarchy
            if($this->className == "Place" && $this->placeSearch == "deep")
                return IdaDB::getInstanceTree("_sys_placeorder", $foundId, true);
            else 
                return array($foundId);

                
        } else if(!$xml->hasChildNodes()) {

            Debug::printMsg('<p>No search terms found for '.$this->className.'. Getting all!</p>');
            $this->foundRecs = IdaDB::getAllInstances($this);
            //Debug::printArray($this->foundRecs);
             


        } else {
            foreach ($xml->childNodes as $link) {

                $lid = XML::getPropertyID($link->tagName);

               //TODO: empty text nodes should be removed 

                // sddkip text nodes
                if($link->nodeType == 3 )
                    continue;

                // if link has no children, then find all linked records
                if(!$link->hasChildNodes()) {
                    Debug::printMsg($lid["id"].".".$lid["dir"]);
                    $res = IdaDb::allJoins(array(), $lid);
                    Debug::printArray($res);
                }

                foreach($link->childNodes as $child) {
                    
                    // skip text nodes
                    if($child->nodeType == 3)
                        continue;


                    Debug::printMsg('<li><b> '.$this->className.'->'.$link->tagName.'->'.$child->tagName.' </b></li>');

                    $node = DataNode::factory($child);

                    // if there is no children, then just search for links
                    if(!$child->hasChildNodes() && !$child->hasAttribute("id") && !$child->hasAttribute("map_id")) {
                        if(empty($findings)) {
                            $cl1 = XML::nodeTree($this->classNameFull, array());
                            foreach($cl1 as $class){
                                $ex = explode(".", $class);
                                $classes1[] = $ex[0];

                            }
                            $classes1[] = $this->classId;

                            $cl2 = XML::nodeTree($node->classNameFull, array());
                            foreach($cl2 as $class){
                                $ex = explode(".", $class);
                                $classes2[] = $ex[0];

                            }
                            $classes2[] = $node->classId;

                            $this->foundRecs = IdaDb::allJoinsClass($classes1, $lid, $classes2);
                            //Debug::printArray($this->foundRecs);
                        }
                    } else {

                        $node->placeSearch = $this->placeSearch;

                        foreach($this->tree as $t) {
                            $arr[] = XML::getClassId($t);
                        }
                        foreach($arr as $a) {
                            $tar[] = "'".$a["id"]."'";
                        }
                        $treeString = implode(",", $tar);

                        // search match
                        $matches = $node->searchByXML($child, $search, $this->foundRecs, $treeString, $link->tagName);

                        Debug::printArray($matches, "Search for $node->tableName $node->classId ended with result:");

                        if(count($matches)) {
                            Debug::printDiv("Matches found for $this->className !");
                            Debug::printArray($matches);
                            /*
                            if($this->className == "Place" && $search && (count($matches) == 1 || $orFlag) ) {  // $this->search flags that we must search place hierarchy 
                                Debug::printDiv("Get instance tree");
                                $matches = IdaDB::getInstanceTree("_sys_placeorder", $matches[0], true);
                            }
    */
                            // if OR flag is not set, then we search joins with previous hits
                            if(count($this->foundRecs) && $orFlag == false) {
                                Debug::printDiv("Searchig for joins...");
                                $res = $node->isJoin($matches, $this->foundRecs, $linkName);
                            } else {
                                if(get_class($node) != "IdaTable") {
                                    Debug::printDiv("Getting all joins!");
                                    Debug::printArray($matches, "found ".$node->className);
                                    //$cl3 = XML::nodeTree($node->classNameFull, array());
                                    foreach($this->tree as $class){
                                        $ex = explode(".", $class);
                                        $classes3[] = $ex[0];
                                    }

                                    Debug::printArray($this->tree, "class tree ".$this->className);
                                    $res = IdaDb::allJoins($matches, $lid, $classes3);
                                }else {
                                  echo "res = matches";
                                  $res = $matches;
                              }
                            }

                            // if there are joins, then make them foundrecs
                            if($res) {
                                if($orFlag) {
                                    Debug::printArray($this->foundRecs, "similar:");
                                    Debug::printArray($res, "res:");
                                    $this->foundRecs =  array_merge($this->foundRecs, $res);
                                } else {
                                $this->foundRecs = $res;
                                $list = implode(', ',$res);
                                Debug::printDiv($this->className." ".$link->tagName." ".$node->className.$node->tableName." joins found!", "green");
                                Debug::PrintDiv($list);
                                Debug::printMsg('</div>');
                                }

                            } else {

                                Debug::printDiv($this->className." ".$link->tagName." ".$node->className.$node->tableName." no joins found!", "red");
                                Debug::printMsg('</div>');
                                if($orFlag) 
                                    return $this->foundRecs;
                                else
                                    return array();
                            }

                        } else {
                            Debug::printDiv("No matches ".$this->className, "red");
                            Debug::printMsg('</div>');
                            return array();

                        }
                }
            }
                // set OR flag
                if($link->tagName == "OR")
                    $orFlag = true;
                else
                    $orFlag = false;


            }
        }

        Debug::printMsg('</div>');
        return $this->foundRecs;
    }


/* *******************************************************************
 *                         PRIVATE FUNCTIONS 
 * *******************************************************************
 */ 




    // **************** TABLES *********************
    
    private function createTables() {
    
        foreach($this->G->tableInfo as $table) {
            $this->tables[$table["title"]] = new IdaTable($table["title"]);
        }   
    }


    private function fetchTableData() {
    
        foreach($this->tables as $table) {
            $table->loadData($this->records, $this->id);
        }
    }


    function getTableXML ($node, $recordId, $mode=0) {
   
        $shorts = array("file");

        foreach($this->tables as $table) {
            // exclude files since they are handled elsewhere (getFileXML())
            if(!in_array($table->tableName, $shorts)) {
                
                $table->pickDataAsXML($node, $recordId, $mode);
            }
        }
    }



    // **************** SAVING *********************

    
    private function insertMe() {

        // make record if there is data and there is no id
        if(count($this->data)) {
            $vals = array ("map_id"=>$this->mapId);
            $this->id = IdaDB::insert ("_sys_records",$vals);
            $this->setTypes ($this->id, $this->classId);

            return $this->id;

        } else {
            echo "has id". $this->id;
            return 0;
        }
    }



    // append a sub record to the "parent" record
    private function append ($node) {

        if(empty($node->id)) {
            Debug::printMsg('<li><span style="color:orange">No predefined id found, so creating node</span> ('.$node->className.$node->tableName.')</li>');
    }

        // if this record is required then make sure it is valid (error==0) and non-empty
        if($this->isRequired($node)) {

            Debug::printMsg('<li><span style="color:orange">Property is required</span> ('.$node->classId.$node->tableName.')</li>');

            // required but has errors or is empty and has no id
            if(($node->hasErrors() || $node->isEmpty()) && empty($node->id)) {

                if($node->hasErrors()) {

                    $this->errors[] = $node->getErrors();
                    Debug::printMsg('<span style="color:red">...but has errors!</span>');

                } else if ($node->isEmpty()) {

                    Debug::printMsg('<span style="color:red">...but is empty!</span>');
                }
 

            // required, no errors and non-empty -> accept
            } else {

                $this->data[] = $node;
                // mark to template that this was found
                Debug::printMsg('<li><span style="color:green">...and found!</span> </li>');
                $this->markIsRequired($node);
            }

        // not required but has errors
        } else if($node->hasErrors()) {
            
            $this->errors[] = $node->errors;

        // not required but is non-empty or has id  -> accept
        } else if (!$node->isEmpty() || !empty($node->id)) {

            $this->data[] = $node;
        }

    }




    private function setTypes($id, $classId) {

        // set the class 
        $vals = array('property'=>$classId, 'subject'=>$id);
        IdaDB::insert("_sys_classes_join", $vals);

    }
    
}




/* *******************************************************************
 *                         GENERIC FUNCTIONS 
 * *******************************************************************
 */ 


// TODO can use usort?
function sortByKeys($arr, $rows) {

    $res = array();
    foreach($rows as $key=>$val) {
        if(key_exists($key, $arr))
        $res[] = $arr[$key];
    }
    return $res;
}


function is_intval($value) {
     return 1 === preg_match('/^[+-]?[0-9]+$/', $value);
}

function cmp($a, $b)
{
    return strcmp($a["date"], $b["date"]);
}


?>
