<?php

/**
 * @package ida
 */



/**
 * constant
 */
define("ALLOW_EMPTY", 1);

/**
    * IdaTable
    * - all table related stuff
    * - table can be created by name or table id
    * - example:
    * <code> $tbl = new IdaTable('title');
    * </code>
     * @package ida
*/
class IdaTable extends IdaClass{

    /**
     * A name of the table (without prefix)
     * @var string
     */
    var $tableName;
    var $columns;
    var $id;

    /**
        * - table can be created by name or table id
      * @param string|integer $id
    */
    function __construct ($id) {

	    $this->globals = singleton::getInstance("Globals");
        $this->getClassInfo($id);
        $this->data = array();
        $this->id = 0;
        $this->searchMethod = "";

        // create columns
        $this->columns = $this->globals->tableInfo[$this->tableId]["columns"];
    }

    /**
        * fetch id number and title of class
    */
    public function getClassInfo($id) {

        //Debug::printMsg("<p>Creating table: ".$id);

        // sort of overloading of constructor
        if(is_numeric($id)) {
            $res = $this->globals->getTableName($id);
        } else {
            $res = $this->globals->getTableId($id);
        }


        $this->tableName = $res["title"];
        $this->tableId = $res["id"];
        $this->classId = $res["shortcut"];

        $res = XML::getClassID($this->classId);
        $this->classNameFull = implode(".", $res);;

        if(empty($this->tableName))
            throw new Exception('ERROR: invalid table id:'.$id);

    }


    function deleteMydata($recordId) {

        if(array_key_exists((int)$recordId, $this->rows)) {
            foreach($this->rows[(int)$recordId] as $row) {
                IdaDb::unLinkRow($this->tableName, $this->tableId, $row["id"], $recordId);
            }
        }        
    }


    // pick data and write it as a node attribute
    function pickDataAsAttribute(&$node, $recordId) {

        $data = & $this->rows[(int)$recordId];

        foreach($data as $row) {

            $linkName = XML::getPropertyID($row["link"]);

            foreach($this->columns as $col) {
                if(!empty($row[$col["title"]])) 
                    $node->setAttribute($col["title"], $row[$col["title"]]);
            }

        }
    }


    // pick data and create full xml (for editing)
    function pickDataAsXML($linkNode, $recordId, $editable=0) {

        // return if no data
        if(!array_key_exists((int)$recordId, $this->rows ))
            return 0;
        
        $data = & $this->rows[(int)$recordId];


        foreach($data as $row) {

		// print columns individually

		$linkName = XML::getPropertyID($row["link"]);
		$property = $linkNode->ownerDocument->createElement($linkName["title"]);
		$linkNode->appendChild($property);
		foreach($this->columns as $col) {

		    // skip empty fields if we are not editing 
		    if(!$editable && empty( $row[$col["title"]])) {

                    } else {
                        // if editable, then table fields as individual xml nodes	
			if($editable) {		

				$column = $linkNode->ownerDocument->createElement($col['title']);
				$column->nodeValue = $row[$col["title"]];
				$property->appendChild($column);

                        // otherwise write fields as attributes to node
			} else {	

                            $property->setAttribute($col['title'], $row[$col["title"]]);

                        }
			
			// if we are editing, then add attributes to columns
			if($editable) {
			    $property->setAttribute("row_id", $row["id"]);
			    $this->addAttributesToColumn($col, $column);
			}
			
		    }
		}
        }
   
        return 1;
    }




    // table data for input template 
    function makeXmlInput($linkNode, $withTableId=0) {

        foreach($this->columns as $col) {
    
            $column = $linkNode->ownerDocument->createElement($col['title']);
            $this->addAttributesToColumn($col, $column, $withTableId);

            $linkNode->appendChild($column);

            if($col['type'] == "select") {
                $this->setOptions($dom, $column, $col["options"]);
            }
        }
	}



	private function addAttributesToColumn($columnData, $columnNode, $withTableId=0) {

       // hide some fields
        $doNotDisplay = array("options","title","action", "tablename", "col_id", "prefix");
        if(!$withTableId)
            $doNotDisplay[] = "tbl_id";

        foreach($columnData as $key=>$val) {
            if (!in_array($key,$doNotDisplay))
            $columnNode->setAttribute($key, $val);
        }
    }



    // just dummy
    function isJoin($matches, $similarObjets, $linkName) {
        return $matches;
    }



    // return object ids as an array
    function searchByXML_new($xml, $class, $recurLevel) {

        $inverse = 0;
        $targetTable = "root_".$recurLevel;
        $linkName = $xml->parentNode->tagName;
        $linkArr = explode(".",$linkName);
        $linkStart = $linkArr[0];

        // check if we need NOT operator
        if($linkStart == "NOT") {
            $linkStart = $linkArr[1];
            $inverse = 1;
        }

        $link = XML::getPropertyID($linkName);

        $this->parseDataFromXML($xml,1);
        Debug::printArray($this->data);


        if(!count($this->data)) {
            return 0;
        }
        
        try {
            // TODO: different kind of tables? (text tables and numeric)
            if($this->tableName == "note") // use word index
                $this->searchMethod = "use_word_index";

            else if($this->searchMethod == "use_word_index") 
                $result = IdaDB::selectByWordIndex($this, $this->data, $ids_str, $classId, $link, $inverse);

            // pseudo property for comparing dates
            else if($linkName == "occurs_during") 
                $result = IdaDB::selectJoinedTimeSpan_new($this, $class, "P4", $recurLevel, $inverse);

            else 
                IdaDB::selectJoined_new($this, $class, $link["id"], $recurLevel, $inverse);
                    
        } catch (Exception $e) {
            
            throw new Exception($e->getMessage().'Could not find record!');
        }

        return 1;
    }




	// return object ids as an array
	function searchByXML($xml, $search=0, $ids=0, $classId=0, $linkName=0) {

        // if classid is not set, then we are calling from parser and not really searching
        if(!$classId)
            return array();

        $inverse = 0;
        $res = array();
        $ids_str = implode(',',$ids);

        $linkArr = explode(".",$linkName);
        $linkStart = $linkArr[0];

        // check if we need NOT operator
        if($linkStart == "NOT") {
            $linkStart = $linkArr[1];
            $inverse = 1;
        }

        $link = XML::getPropertyID($linkName);

        $this->parseDataFromXML($xml,1);
        Debug::printArray($this->data);


        if(!count($this->data)) {
            if(!count($ids)) {
              return array(-99);  // return -99 if we didn't have any valid fields and there is no ids
            }
            else {
              return $ids;         // return ids if there are ids
            }
        }
        
        try {
            // TODO: different kind of tables? (text tables and numeric)
            if($this->tableName == "note") // we do not search from notes
                return $ids;

            else if($this->method == "use_word_index")
                $result = IdaDB::selectByWordIndex($this, $this->data, $ids_str, $classId, $link, $inverse);

            // pseudo property for comparing dates
            else if($linkName == "occurs_during") 
                $result = IdaDB::selectJoinedTimeSpan($this->tableName,$this->data, $ids_str, $classId, "P4F", $inverse);

            else
                $result = IdaDB::selectJoined($this->tableName,$this->data, $ids_str, $classId, $link['id'].$link['dir'], $inverse);
		    
        } catch (Exception $e) {
            
            throw new Exception($e->getMessage().'Could not find record!');
        }

		return $result;
	}



/**
    * reads data from xml
*/
	function parseDataFromXML(&$xml, $allowEmpty=0) {

        $this->errors = array();
        $this->tableXML = $xml;

        Debug::printMsg('<p>'.$this->tableName.'->'. __FUNCTION__ ."</p>");

        foreach($xml->childNodes as $field) {
            $fname = $field->tagName;

            // make sure that column exists
            if(array_key_exists($fname, $this->columns)) {

                if($field->hasAttribute("method"))
                    $this->method = $field->getAttribute("method");

                // make sure that column has value
                if($field->hasChildNodes()) {

                    // if there are subnodes (xml) then save them as a text
                    if($field->firstChild->nodeType == 1) {
                        $simple = simplexml_import_dom($field->firstChild);
                        $nodeText = $simple->asXML();

                    // otherwise just save the nodevalue
                    } else {

                        $nodeText = htmlspecialchars($field->nodeValue);
                    }

                    // add to data if not empty
                    if(trim($nodeText) != '')
                        $this->data[$fname] = trim($nodeText);

                }

            } else {
                throw new Exception('ERROR: invalid column name:'.$fname);
            }
        }

        $this->validate($allowEmpty);
        //Debug::printArray($this->data);
       //Debug::printMsg("</ul>");
	}




/**
    * insert
*/
    function save() {

        Debug::printMsg('<p>'.$this->tableName.'->'. __FUNCTION__ ."</p>");

        // insert values only if there are values
        if(count($this->data)) {

            $this->id = IdaDB::insert($this->tableName,$this->data);
            $this->rowId = $this->id; // TODO: separate rows

            return $this->id;

        } else {

            return 0;
        }

    }



    function load($id) {

        $this->rowId = $id;
        $this->data = IdaDB::getRowById($this->tableName,$id);
        Debug::printArray($this->data,"this-data");

    }

    function clear() {

        unset($this->data);
        $this->rowId = 0;

    }

    function getXML($linkNode, $hiddenFields = array(), $editable=0) {

        $this->makeXmlInput($linkNode, $hiddenFields, $editable);
        $linkNode->setAttribute("id",$this->rowId);

        // add filename to the files in order to speed up things in client side
        if($this->tableName == "file")
            $tbl->setAttribute("filename", $this->data["filename"]);

       // return $tbl;

     }



    function link($domain) {

        if(isset($this->linkId)) {
            IdaDB::linkRowToRecord($this, $domain);
            // index words
            if($domain->indexWords) {
                IdaDb::createTempTable();
                IdaDB::indexWords($domain->id, $this);
                IdaDb::dropTempTable();
            }

        } else
            throw new Exception('cannot call linkData-function without setting link!');
      /* 
        if($domain->logXMLData) {
            $domain->logXMLData->appendChild($domain->logXML->createElement($this->linkId));
            $domain->logXMLData->lastChild->setAttribute("id", $this->id);
            $domain->logXMLData->lastChild->appendChild($domain->logXML->importNode($this->tableXML, 1));
        }
        */
    }



    function editRow($xml, $rowId) {

        $this->id = $rowId;
        $this->parseDataFromXML($xml,ALLOW_EMPTY);

        if(!$rowId)
            throw new Exception('ERROR: no row id in table edit.');


        IdaDB::update($this->tableName, $this->data, 'id', $rowId);

        return $this->data;
    }



    function getRow($rowId) {

        $values = IdaDB::getRowById($this->tableName, $rowId);
        return $values;
    }




    function setOptions($doc, $colNode, $options) {
         $opts = explode(':',$options);
         foreach($opts as $opt) {
            $option = $doc->createElement('option');
            $option->nodeValue = $opt;
            $colNode->appendChild($option);
        }
    }


    private function validate($allowEmpty) {

        foreach($this->columns as $col) {
            $this->isValidValue($this->data[$col["title"]], $col["title"], $allowEmpty);
        }

    }


    private function isValidValue($field, $fname, $allowEmpty) {

        Debug::printMsg("<li>Checking validity: ".$fname." = ".$field."</li>");
       // Debug::printArray($this->columns[$fname]);

        // check value if it is not empty
        if(trim($field) != '') {

            switch($this->columns[$fname]["type"]) {

                case "integer":
                    if (is_numeric($field))
                        return true;
                    else
                        throw new Exception('wrong column type:'.$fname);

                    break;

                case "text":
                    if(is_string($field))
                        return true;
                    else
                        throw new Exception('wrong column type:'.$fname);
                    break;
            }

        } else {
            // check if required value is missing
            if($this->columns[$fname]["required"] == 1) {
                $this->errors[] = 'required data value missing:'.$fname;
                Debug::printMsg('<br><span style="background-color:red; color:white">required table value missing:</span>'.$fname);

            } else if ($allowEmpty)
                    return true;
       }

        return false;

    }



    // loads ALL rows for records in temp table
    function loadDataByTempTable($tableName) {


        $cols_str = "";
        // set columns
        foreach($this->columns as $key=>$val) {
            $cols_str .= ", tbl.".$key;
        }

        //if we are time-span table then fetch order by date
        // TODO: generic order by!!

        $order_by = "";
        if($this->tableName == "time_span")
            $order_by = " ORDER BY start_year, start_month, start_day ";
        else if($this->tableName == "appellation")
            $order_by = " ORDER BY name";

  	    // TODO: move sql to IdaDB
  	    // TODO: replace DISTINCT with WHERE EXISTS structure
        $sql = "SELECT
               DISTINCT 
                    tbl_j.subject,
                    tbl.id,
                    tbl_j.link_type as link
                    $cols_str

                FROM
                    "._DBPREFIX."_".$this->tableName." AS tbl
                INNER JOIN
                    "._DBPREFIX."_".$this->tableName."_join as tbl_j
                ON
                        tbl_j.property = tbl.id
                INNER JOIN 
                    "._DBPREFIX."_".$tableName." as temppi 
                ON
                    tbl_j.subject = temppi.subject
		    ";

        $this->rows = IdaDB::prepareExecute($sql, array(), "all");
        Debug::printArray($this->rows, "table data:");
    }


    // loads ALL rows for records (parent is the main record)
    function loadData($records, $parent=0) {

        if(!is_array($records))
            return 0;

        $multi = false;
       
        foreach($records as $rec) {
            if (is_array($rec)) // check if 2-dimensional array
                $multi = true; 
        }

        if($multi)
            $recs = IdaTable::get_by_key("id", $records);
        else 
            $recs = $records;


        $cols_str = "";

        // add main record itself to the search query
        if($parent) 
            $recs[] = $parent;

        if(empty($recs))
            return 0;
 
        $rec_imp = implode(",", $recs);

        // set columns
        foreach($this->columns as $key=>$val) {
            $cols_str .= ", tbl.".$key;
        }

        //if we are time-span table then fetch order by date
        // TODO: generic order by!!

        $order_by = "";
        if($this->tableName == "time_span")
            $order_by = " ORDER BY start_year, start_month, start_day ";
        else if($this->tableName == "appellation")
            $order_by = " ORDER BY name";

  	    // TODO: move sql to IdaDB
        $sql = "SELECT
                    tbl_j.subject,
                    tbl.id,
                    tbl_j.link_type as link
                    $cols_str

                FROM
                    "._DBPREFIX."_".$this->tableName." AS tbl
                INNER JOIN
                    "._DBPREFIX."_".$this->tableName."_join as tbl_j
                ON
                    (
                        tbl_j.property = tbl.id
                     AND
                        tbl_j.subject IN ($rec_imp)
                    )
		   $order_by 
		    ";

        $this->rows = IdaDB::prepareExecute($sql, array(), "all");
        Debug::printArray($this->rows, "table data:");

    }


    function get_by_key($keyname, $pieces) {


        // create a new recursive iterator to get array items
        $it = new RecursiveIteratorIterator(new RecursiveArrayIterator($pieces));

        $arr = array();

        foreach($it AS $element) {

            if ($it->key() == $keyname) {

                $arr[] = $it->current();

            }

        }

        return  $arr;

    }
    
    static function implode_by_key($glue, $keyname, $pieces, $prefix="", $suffix=" as ") {


        // create a new recursive iterator to get array items
        $it = new RecursiveIteratorIterator(new RecursiveArrayIterator($pieces));

        $arr = array();

        foreach($it AS $element) {

            if ($it->key() == $keyname) {

                $arr[] = $prefix.$it->current().$suffix.$it->current();

            }

        }

        return implode($glue, $arr);

    }

    function implodeRecords($records) {

        if(is_array($records)) {
            return IdaDB::implodeArray($records, "integer");
        } else {
            return $records;
        }
    }

    function getTableInfoXML($DOMnode) {
        
        $table = $DOMnode->ownerDocument->createElement("table");
        $decl = $DOMnode->ownerDocument->createElement("declaration");
        $name = $DOMnode->ownerDocument->createElement("name",$this->tableName);
        $table->setAttribute("title", $this->tableName);
        $table->appendChild($name);
        $table->appendChild($decl);
        $DOMnode->appendChild($table);
        
        foreach($this->columns as $col) {
            $field = $DOMnode->ownerDocument->createElement("field", $col["title"]);
            $field->setAttribute("type", $col["type"]);
            $field->setAttribute("width", $col["width"]);
            $decl->appendChild($field);
        }
        
    }

}

?>
