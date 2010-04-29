<?php

// XML manipulation helpers
class XML {

    static function splitPropertyName($name) {

        // if array form :array("P98B","was_born")
        if(is_array($name)) {
            $dir = $name[0][strlen($name[0])-1];
            $id = substr_replace($name[0],'',-1);
        } else {
            return XML::splitPropertyName(explode(".",$name));
        }

        return array("id"=>$id, "title"=>$name[1], "dir"=>$dir);



    }


    // returns array (id, title, dir)
    static function getPropertyID ($prop, $throwError=0) {

        global $G_propertyNames;

        // id
        if(!empty($G_propertyNames[$prop]))
            return XML::splitPropertyName(array($prop, $G_propertyNames[$prop]));

        // full name
        $ex = explode(".", $prop);
        if(count($ex) == 2)
            if(!empty($G_propertyNames[$ex[0]]))
                return XML::splitPropertyName(array($ex[0], $G_propertyNames[$ex[0]]));
            
        // title
        foreach($G_propertyNames as $key=>$val) {
            if($val == $prop)
                return XML::splitPropertyName(array($key, $val));
        }
        if($throwError)
            throw new Exception("Property \"$prop\" not found!");
    }    
 

    // returns array (id, title)
    static function getClassID ($class, $throwError=1) {

        global $G_classNames;

        // id
        if(!empty($G_classNames[$class]))
            return array("id"=>$class, "title"=>$G_classNames[$class]);

        // full name
        $ex = explode(".", $class);
        if(count($ex) == 2)
            if(!empty($G_classNames[$ex[0]]))
                return array("id"=>$ex[0], "title"=>$G_classNames[$ex[0]]);

        // title
        foreach($G_classNames as $key=>$value) {
            if($value == $class)
                return array("id"=>$key, "title"=>$value);
        }
        
        if($throwError)
            throw new Exception("Class $class not found!");
    } 




    static function attributeQuery($path, $attrName, $attrVal, $retAttr, &$xpath) {

        $query = $path."[@".$attrName."='".$attrVal."']";
        $result = $xpath->query($query);
        if($result->length == 1) {
            return $result->item(0)->getAttribute($retAttr);
        }


    }
 

    static function attributeQueryContains($path, $attrName, $attrVal, $retAttr, &$xpath, $side=0) {

        $query = $path."[contains(@".$attrName.",'".$attrVal."')]";
        $result = $xpath->query($query);
        if($result->length) {
            foreach($result as $node) {
                $name = $node->getAttribute($retAttr);
                $ex = explode(".", $name);
                if($ex[$side] == $attrVal) 
                    return $node->getAttribute($retAttr);
            }
        }


    }
     static function makeValidXMLNodeText($str) {

        return str_replace(' ','_',$str);

    }



    static function setAttributes(&$node, $attr) {

        if ($attr)
            foreach($attr as $key=>$val)
                $node->setAttribute($key, $val);
        
    }




    static function makeListXML($records) {
        
        $xml = "";

        foreach($records as $rec) {

            $xml = $xml."<rs id=\"".$rec['record_id']."\" is_identified_by=\"".$rec['name']."\"/>";

        }

        return $xml;
    }




    static function makeResultListXML($result) {

        if(count($result)) {
        $xml = "";
        $table = new IdaTable("appellation");
        $table->loadData($result);

/*
        // generate XML
        if(is_array($table->rows)) {
	        foreach($table->rows as $key=>$val) {
                $xml = $xml.'<rs id="'.$key.'">'.$val[0]["name"].' </rs>';
            }
        }
*/        
    if(is_array($result)) {
	        foreach($result as $record) {
                if(is_array($record)) {
                    $valName = " is_identified_by=\"".$table->rows[$record["id"]][0]["name"]."\" ";
                    $xml = $xml.'<rs id="'.$record["id"].'"'.$valName.' />';
                } else {

                    $valName = " is_identified_by=\"".$table->rows[$record][0]["name"]."\" ";
                    $xml = $xml.'<rs id="'.$record.'"'.$valName.' />';
                }
            }
        }

        return $xml;
        }
    }


    // find whole-part relations and create xml for quicksearch result
    static function makeQuickSearchXML($round) {

        global $G_classNames;
        $res = IdaDb::select("tem$round", array(),"DISTINCT record_id","","onecol");

        $part_of = IdaDb::getLinkedByGroup2("property", $res, array('P89') ,0,0,0,"all" );
        /*
        // get part-of relations
        $is_member = IdaDb::getLinkedByGroup2("subject", $res, Globals::$is_member,0,0,0,"all" );
        $part_of = IdaDb::getLinkedByGroup2("property", $res, Globals::$parts2,0,0,0,"all" );
        $mods = IdaDb::getLinkedByGroup2("subject", $res, Globals::$modifications,0,0,0,"all" );
        $refers_to = IdaDb::getLinkedByGroup2("property", $res, array("P67"),0,0,0,"all" );
        $took_place = IdaDb::getLinkedByGroup2("property", $res, array("P7"),0,0,0,"all" );
        $mods_id = IdaTable::get_by_key("id", $mods);

        // carriers for events and products
        $carried_out = IdaDb::getLinkedByGroup2("property", $mods_id + $res, Globals::$carried_out, 0,0,$res,"all");
        */
        
        Debug::printArray($res, "result:");
        Debug::printArray($is_member, "is member:");
        Debug::printArray($part_of, "part of:");
        Debug::printArray($carried_out,"carried_out:");

        // combine
        //$parts = $is_member + $part_of;

        // get part_of's appellations
        $table = new IdaTable("appellation");
        $table->loadData($part_of);
        /*
        $placeNames = new IdaTable("appellation");
        $placeNames->loadData($took_place);
        $refNames = new IdaTable("appellation");
        $refNames->loadData($refers_to);
       */

        Debug::printArray($took_place);
        Debug::printArray($placeNames->rows);

        // get query result from temporary table
        $records = IdaDb::select("tem$round", array()," DISTINCT record_id, name, start_year, start_month, class_id, type_id", "ORDER BY name");

        // generate XML
	    if(is_array($records)) {
	        foreach($records as $record) {

                $creatorName = $cName = $tName = $partName = 0;

                $className = $G_classNames[$record["class_id"]];
                 
                $date = XML::makeDate($record);

                // BY
                $created = $mods[$record["record_id"]][0]["id"];   
                $by = $carried_out[$created][0]["id"];   
               // $byXML = XML::pickFromArray($created, $carried_out, $table, "by");


                // EVENT BY
                $ebyXML = XML::pickFromArray($record["record_id"], $carried_out, $table, "by");

                // EVENT TOOK PLACE
                $placeXML = XML::pickFromArray($record["record_id"], $took_place, $placeNames, "took place in");

                // REFERS TO
                $refXML = XML::pickFromArray($record["record_id"], $refers_to, $refNames, "refers to");
                    
                // PART OF
                $partXML = XML::pickFromArray($record["record_id"], $part_of, $table, "in");
                if($partXML != "") $partXML = " part_of='".$partXML."'";

                $xml .= '<rs id="'.
                        $record["record_id"].
                        '" class="'.$className.'"'
                        .$partXML.'>'.
                        $record["name"]." ".$date.
                        $ebyXML.$placeXML.$refXML.' </rs>';
	        }
	    }

	    return $xml;
    }


    static function pickFromArray($recordId, $array, $table, $text) {

        $val = $array[$recordId][0]["id"];   
        $valName = $table->rows[$val][0]["name"];
       
        if($valName)
            return  " ".$text." ".$valName;


    }

    static function makeDate($record) {

        $date = $record["start_year"];
        if($record["start_month"])
            $date = $date.$records["start_month"];

        return $date;

    }

    static function initGetXML(&$instance, $dom, $act=0) {

        global $G;

        if($dom->ownerDocument)
            $dom = $dom->ownerDocument;

        $objNode = $dom->createElement(XML::makeValidXMLNodeText($instance->className));

    
        $dom->appendChild($objNode);
        isset($instance->id) ? $objNode->setAttribute("id", $instance->id) : null;
        
        if($act)
            $objNode->setAttribute("act", $act);
       
        return $objNode;


    }

    public function nodeTree($className, $path) {

        global $G_xpath;
        $query = "//rdfs:subClassOf[@rdf:resource='#".$className."']/parent::rdfs:Class";
        $res = $G_xpath->query($query);

        foreach($res as $node) {
            $name = $node->getAttribute("rdf:ID");
            $path = XML::nodeTree($name, $path);
            $path[] = $name;
        }

        return $path;
    }

    public function pathToClass($className, $path) {

        global $G_xpath;

        $query = "//rdfs:Class[@rdf:ID='".$className."']/descendant::rdfs:subClassOf";
        $res = $G_xpath->query($query);

        foreach($res as $node) {

            $name = $node->getAttribute("rdf:resource");
            $name = substr($name,1);
            $path = XML::pathToClass($name, $path);
            $path[] = $name;
        }

        return $path;

    }

    public function makeXMLTreeFromXML($xmlNode, $name, &$xpath) {

        $query = "//rdfs:subClassOf[@rdf:resource='#".$name."']/parent::*";
        $res = $xpath->query($query);

        foreach($res as $node) {
            $name = $node->getAttribute("rdf:ID");
            $cl = $xmlNode->ownerDocument->createElement("Class");
            $f = explode(".", $name);
            $cl->setAttribute("title", $f[1]);
            $cl->setAttribute("id", $f[0]);
            $xmlNode->appendChild($cl);
            XML::makeXMLTreeFromXML($cl, $name, $xpath);
        }
    }

    //TODO: Place is hard coded!
    static function order ($className) {
        
        $path = XML::pathToClass($className, array($className));
        if(in_array("E53.Place", $path))
            return "place";
        else
            return 0;
    }

    // thanks to http://www.sitepoint.com/print/hierarchical-data-database/
    static function makeXMLTree($tree,  $titles, $xmlNode, $nodeName="class") {

    global $G;
    $right = array();
    $curr = $xmlNode;
    foreach($tree as $row) {
        
       if (count($right)>0) {
           // check if we should remove a node from the stack
           while ($right[count($right)-1] < $row['rgt']) {
               array_pop($right);
               $curr = $curr->parentNode;
           }
       }
       
       // display indented node title
       $cl = $xmlNode->ownerDocument->createElement($nodeName);

        // class names and appellations are in diffrent forms
       // $title =  $titles[$row["id"]][0]["name"];

      // $cl->setAttribute("title", $title);
       $cl->setAttribute("id", $row["id"]);
       $curr->appendChild($cl);
       $curr = $cl;

       // add this node to the stack
       $right[] = $row['rgt'];       
       
        }
    }


    // find tables for columns
    static function prepareXML($xml, $cols) {

      // $table = $xml->ownerDocument->createElement("table");
      // $domNode = $xml->ownerDocument->importNode($table, true);
      // $xml->appendChild($table);
      
      
        if($xml->childNodes) {

            foreach($xml->childNodes as $child) {   

                //skip text nodes
                if($child->nodeName != '#text') {

                   $name = $child->tagName;

                    if (!ereg('^[A-Z]', $name)) {

                        // check if node name is a column's name
                        if(array_key_exists($name, $cols)) {

                            $parent = $child->parentNode;
                            $table = $xml->ownerDocument->createElement("table");
                            $table->setAttribute("name", $cols[$name][0]);

                            // move all fields to table node
                            while($parent->hasChildNodes()) {

                                    $table->appendChild($parent->firstChild);

                            }
                            // copy id from field node to table node
                            if($table->firstChild->hasAttribute("id"))
                                $table->setAttribute("id", $table->firstChild->getAttribute("id"));

                            $parent->appendChild($table);

                            // get out of loop
                            break;

                       // not caps and not column -> table name -> break out
                       } else break;


                    } else {

                        if($child->hasChildNodes()) {

                            foreach($child->childNodes as $link) {
                                   XML:: prepareXML($link, $cols);
                            }
                        }
                    }
                }
            }
        }

    }


    static function importXML($class) {

            try {

                if($class->hasAttribute("map_id")){
                    $mapId = $class->getAttribute("map_id");
                    $res = IdaDb::select("_sys_records", array("map_id"=>$mapId), array("id"));

                    if(count($res) != 0) {
                        throw new Exception("map_id \"".$mapId."\" allready found!");
                    }
                }

                $target = new IdaRecord($class->tagName);
                $target->indexWords = false;
                if(!empty($mapId))
                    $target->mapId = $mapId;

                // validate data and find ID's
                $target->parseDataFromXML(&$class, 0);

                if(!$target->hasErrors()) {


                     // make a new record
                    $target->save();
                    $result =   '<response status="ok" id="'.$target->id
                                .'" class="'.$target->className
                                .'"></response>';

                } else {

                    $result =  '<response status="error" map_id="'.$target->mapId.'">'
                                .error2XML($target->getErrors(), "reason")
                                .'</response>';

                }

            } catch (Exception $e) {

                $result =  '<response status="error" map_id="'.$mapId.'">'
                            .$e->getMessage()
                            .'</response>';

           }

        return $result;

    }



    static function removeLink($command) {


        try {
            $subjectId = $command->getAttribute("subject_id"); 
            $targetId = $command->getAttribute("target_id");

            $subjectClass = IdaRecord::getClassNameFromRecordId($subjectId);
            $targetClass = IdaRecord::getClassNameFromRecordId($targetId);

            $link = XML::getPropertyID($command->getAttribute("link"));
            IdaDB::unLinkFromRecord($subjectId, $targetId, $link);
            
            return '<response status="ok" subject="'.$subjectId.'" link="'.$command->getAttribute("link").'" target="'.$targetId.'" />';

        } catch (Exception $e) {


            return '<response status="error" subject="'.$subjectId.'" link="'.$command->getAttribute("link").'" target="'.$targetId.'">'.$e->getMessage().'</response>';
        }

    }

    static function makeLink($command) {


        try {
            if ($subjAttr = $command->getAttribute("subject_id")) {
                $recordId = (int)$subjAttr;
            } else {
                $subjAttr = $command->getAttribute("subject_map_id");
                $recordId = IdaDb::getIdByMapId($subjAttr);
            }

            if($command->hasAttribute("target_id")) {
                $targetAttr = $command->getAttribute("target_id");
                $targetId = (int)$targetAttr;
            } else {
                $targetAttr = $command->getAttribute("target_map_id");
                $targetId = IdaDb::getIdByMapId($targetAttr);
            }

            $recordClass = IdaRecord::getClassNameFromRecordId($recordId);
            $targetClass = IdaRecord::getClassNameFromRecordId($targetId);
            $subject = new IdaRecord($recordClass["id"]);
            $target = new IdaRecord($targetClass["id"]);
            //$instance->path = XML::pathToClass($instance->classId);
            $subject->id = $recordId;
            $target->id = $targetId;

            $subject->linkRec($target, $command->getAttribute("link"));
            $subjStr = $subject->className.":".$recordId.":".$subjAttr; 
            $targetStr = $target->className.":".$targetId.":".$targetAttr; 

            //IdaLog::saveEditXML($root->ownerDocument->saveXML(), $instance->id, $root->tagName);"
            return '<response status="ok" subject="'.$subjStr.'" link="'.$command->getAttribute("link").'" target="'.$targetStr.'" />';

        } catch (Exception $e) {


            return '<response status="error" subject="'.$subjAttr.'" link="'.$command->getAttribute("link").'" target="'.$targetAttr.'">'.$e->getMessage().'</response>';
        }

        }

}

