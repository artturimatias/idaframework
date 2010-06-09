<?php
/**
 * class.Template.php
 * Class for accessing templates 
 * @package ida
 */


class Template {

    static function getInputTemplate(&$instance) {

        global $G;
        global $templates_xml;

        $root = "/template/addtemplate/";
        $classPath = XML::pathToClass($instance->classNameFull, array($instance->classNameFull)); 
        foreach($classPath as $class) {
            $ex = explode(".", $class);
            $q[] = $root.$ex[1]."/descendant::*";
        }

        $query = implode($q, "|");

        $templateXML = new DomDocument('1.0', 'UTF-8');
        if(!$templateXML->Load($templates_xml, LIBXML_NOBLANKS))
            die("file ".$templates_xml ." not found or not valid!");

        $xpath = new DOMXPath($templateXML);
        return  $xpath->query($query);


    }


    // this function applies template XML to a record XML
    // NOTE: we are assuming that there is only one property per record
    static function applyTemplate($recNode, $tempNode) {

        //Template::applyTemplateTables($recNode, $tempNode);
        $attributes = array("action", "required", "group", "translation", "class");

        // let's browse through non-data properties
        foreach($tempNode->childNodes as $property) {

            $not_found = 1;

            foreach($recNode->childNodes as $node) {
                // if property is found from record, then set some attributes to it
                if($node->tagName == $property->tagName) {
                    foreach($attributes as $a) {
                        if($property->hasAttribute($a))
                            $node->setAttribute($a, $property->getAttribute($a));
                    }

                    $not_found = 0;

                    // if action is "create_by_default", it means that record is editable
                    // so we have to make sure that is templated too
                    if($node->getAttribute("action") == "create_by_default") {
                        foreach($node->childNodes as $subNode) {
                            Template::applyTemplate($subNode, $property->firstChild);
                        }
                    }
        
                }

            }

            // if property was not found, them import it from template
            if($not_found) {

                $inode = $node->ownerDocument->importNode($property, 1); 
                $recNode->appendChild($inode);
            }


        }

   }


    static function getImportDirContent() {

        $arr = array();
        $search = array(".", "..");

        if(is_dir(_IMPORT_DIRECTORY)) {
            $d = dir(_IMPORT_DIRECTORY);
            $entry = $d->read();
            while (false !== ($entry = $d->read())) {
                if (!in_array($entry, $search)) {
                    $arr[] = $entry;
                }
            }
            $d->close();
        }
        return $arr;

    }



    static function getTemplates() {
        $templateDir = "./init/templates/";
        $arr = array();
        $search = array(".", "..","_system");

        $d = dir($templateDir);
        while (false !== ($entry = $d->read())) {
            if (!in_array($entry, $search)) {
                $entry = str_replace(".xml", "", $entry);
                $arr[] = $entry;
            }
        }
        $d->close();

        sort($arr);
        return $arr;

    }


    // pick context sensitive properties from template
    static function checkContext($row, $parent) {
       
Debug::printMsg("<h2>parent=".$parent->hasType."</h2>");
        if(empty($row["context_has_type"]))  
            return 1;         

        if($parent->hasTypePath)
            if(in_array($row["context_has_type"], $parent->hasTypePath))
                return 1;
                
        if($row["context_has_type"] == $parent->hasType)
            return 1;     
                       
        return 0;
        
    }


    public static function checkLinkRange(&$instance, $link, $target) {

        global $classes_rdfs;
        global $properties_rdfs;

        // if target is table, then check shorcut(s) -> all descadents are ok
        if(get_class($target) == "IdaTable") {

            $tree = XML::nodeTree($target->classNameFull, array());
            $path = XML::pathToClass($target->classNameFull, array($target->classNameFull));
            $targetPath = array_merge($path, $tree);
            //$targetPath = Array("Appellation");

        } else {

            $targetPath = XML::pathToClass($target->classNameFull, array($target->classNameFull));
            Debug::printArray($targetPath, $target->classNameFull);
        }

        // check that instance have path
        if(!$instance->path)
            $instance->path = XML::pathToClass($instance->classNameFull, array($instance->classNameFull));

        $crmDom = new DomDocument('1.0', 'UTF-8');
        if(!$crmDom->Load($properties_rdfs, LIBXML_NOBLANKS))
            die("file not found");

        $xpath = new DOMXPath($crmDom);
        $query = "//rdf:Property[@rdf:ID='".$link."']/descendant-or-self::*";
        $result = $xpath->query($query);
        if($result->length ) {
            foreach($result as $node) {
                switch($node->tagName) {

                case "rdf:Property" :
                    $ex = explode(".", $node->getAttribute("rdf:ID"));
                    $direction = $link[mb_strlen($ex[0])-1];
                    break;
     
                case "rdfs:range" :
                    $range = substr($node->getAttribute("rdf:resource"),1);
                    break;               
      
                case "rdfs:domain" :
                    $domain = substr($node->getAttribute("rdf:resource"),1);
                    break;               
               }
    
            }

        } else {

            throw new Exception('Property "'.$link.'" not found');
        }




        if($direction == "B" || $direction == "F") {

            if(!in_array($range, $targetPath)){
                Template::linkError($domain, $link, $range, $instance->classNameFull, $target->classNameFull);
            }

            if(!in_array($domain, $instance->path)){
                Template::linkError($domain, $link, $range, $instance->classNameFull, $target->classNameFull);
            }


        } else {

            throw new Exception('Direction not found (SERIOUS BUG!)');
        }
        
        // return success if no errors thrown
        return 1;
    }


    private function linkError($domain, $link, $range, $subject, $target) {

        global $G;
        $s = "->";
        // $clNames = IdaDB::getClassNames();
        $msg = "TEMPLATE ERROR: You should have:".$domain.$s.$link.$s.$range;
        
        $msg .= " ----> But You have: ".$subject.$s.$link.$s.$target;


       throw new Exception($msg);


    }

    
    // get class/typename of a target or subject of template ($type = class/target)
    static function getTargetName ($link) {

        global $G;

        if ($link["target_type"] == "type")
            return $G->typeNames[$link["target"]]["title"];
        else
            return $G->classNames[$link["target"]]["title"];        
        
    }    
 


    static function getSubjectName ($link) {

        global $G;

       if(!empty($link["has_type"]))
            return $G->typeNames[$link["has_type"]]["title"];
        else
            return $G->classNames[$link["class"]]["title"];
    }


    static function getFullLinkName(&$link, $separator=0) {

        global $G;
        if($separator)
            $sep = $separator;
        else
            $sep = _PROPERTY_SEPARATOR;

        $dir = mb_strtolower($link['link_dir']);
        $title = $G->propertyTitles[$link["link_type"]][0][$dir];
        return XML::makeValidXMLNodeText($link["link_type"].$link["link_dir"].$sep.$title);

    }

    static function linkName(&$linked, $reverse=0) {

        global $G_propertyTitles;
        $plainLink = $linked['link_type'];

        if($reverse) {
            $rev = Template::reverseLinkId($linked['link_type'].$linked['link_dir']);
            $dir = mb_strtolower($rev[mb_strlen($rev)-1]);

        } else {
            $dir = mb_strtolower($linked['link_dir']);
        }
        
        $title = $G_propertyTitles[$plainLink][0][$dir];
        return XML::makeValidXMLNodeText($title);
    }
    

    static function reverseLinkId($linkId) {

        $linkDir = $linkId[mb_strlen($linkId)-1];
        if($linkDir == "F")
            $linkDir = "B";
        else
            $linkDir = "F";
            
        return substr_replace($linkId, $linkDir,-1); 
        
    }


}

?>
