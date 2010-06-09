<?php
ob_start();
require_once('class.IdaRecord.php');
require_once('class.IdaSession.php');
require_once('class.IdaLog.php');
require_once('class.IdaXMLIO.php');
require_once('class.IdaUpLoader.php');

define("SKIP_DEBUG", 1);
define("AS_EDITABLE", 1);

global $G;
libxml_use_internal_errors(true);
$time_start = microtime(true);

//NOTE: simpleXML is used when reading xml and DOM is used for writing!

try {

    $posted_xml = stripslashes($_POST['xml']);
    $posted_xml = trim($posted_xml);

    $xml = new DOMDocument('1.0', 'UTF-8');

    // check XML
    if(!$xml->loadXML($posted_xml,LIBXML_NOBLANKS)) {

        if(DEBUG) {
            Debug::DebugPage();
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                Debug::printPara(IdaXMLIO::display_xml_error($error, $xml));
            }
        }
        throw new Exception('XML parsing failed');



    }

    $root = $xml->firstChild;

    // find tablenames for columns
    $cols = IdaDb::getTableColumns();

    // match column names to tables
    PrepareXML($root, $cols);

    // action selector
    switch($root->tagName) {


        case "alku":

            $msg = "";
            $sql = "SELECT DISTINCT LEFT(name,1) FROM ida_appellation AS a order by name ";
            //$sql = "SELECT DISTINCT LEFT(name,1) FROM ida_appellation AS a INNER JOIN ida_appellation_join AS aj ON aj.property = a.id    INNER JOIN ida__sys_classes_join AS clj ON aj.subject = clj.subject AND clj.property = 'E21' order by name";


            $res = IdaDb::prepareExecute($sql, array(), "onecol");
            $nonValid = array(",","&");
            foreach($res as $letter) {
                if($letter != '&')
                    $msg .=  "<let ter=\"$letter\" />\n";
            }

            IdaXMLIO::sendXML($msg);

        break;


        case "testi":

        $target = new IdaRecord("Digital_Image");

        $dom = new DomDocument('1.0', 'UTF-8');
        $rootNode = $dom->createElement('Digital_Image');
        $linkNode = $dom->createElement('P1F.is_identified_by');
        $link2Node = $dom->createElement('P94B.was_created_by');
        $dom->appendChild($rootNode);
        $tableNode = $dom->createElement('table');
        $nameNode = $dom->createElement('name');
        $tableNode->setAttribute("name","appellation");
        $nameNode->nodeValue = "koe";
        $rootNode->appendChild($linkNode);
        $linkNode->appendChild($tableNode);
        $tableNode->appendChild($nameNode);
        $target->parseDataFromXML(&$rootNode);

            $target->save();
        if(!$target->hasErrors()) {
            $target->save();

        } else {

                      $result .=   '<response status="error" class="'.$target->className.'">'
                                    .error2XML($target->getErrors(), "reason")
                                    .'</response>';


                    }

                IdaXMLIO::sendXML($result);


        break;

        case "kuvalista":

            $sql = "select filename from ida_file_appellation as a, ida_file_appellation_join as aj,ida__sys_records as rec where aj.subject = rec.id and a.id = aj.property";
            $images = IdaDB::query($sql);

            while (($row = $images->fetchRow())) {
                echo $row["filename"].".jpg\n";
            }
        break;


//*********************
// GETTEMPLATE
//*********************
        // get template
        case "gettemplate":

            $G = singleton::getInstance("Globals");
            $className = $root->getAttribute("title");

            $IdaRecord = new IdaRecord($className);
            $dom = new DomDocument('1.0', 'UTF-8');

            // add root node
            $rootNode = $dom->createElement('root');
            $dom->appendChild($rootNode);


            $templateXML = $IdaRecord->makeXMLInputGrouped($dom, $type);
            $rootNode->appendChild($templateXML);

            Debug::MemoryUsage();
            IdaXMLIO::sendAsXML($dom->saveXML());
            break;

//*********************
// LOGIN
//*********************
        // login seed
        case 'loginseed' :
            $seed = mt_rand(10000, 100000).time();
            // save request's IP and seed
            IdaLog::seedLog($seed);
            IdaXMLIO::sendAsXML("<seed>".$seed."</seed>", SKIP_DEBUG);
            break;

//*********************
// LOGIN
//*********************
        // start session
        case "login":
        
            $result = IdaSession::login($xml);
            IdaXMLIO::sendXML($result, "root", SKIP_DEBUG);
            break;


//*********************
// LOGOUT
//*********************
        // end session
        case "logout":
            if(IdaSession::checkSession()) {
                $result = IdaSession::logout();
                IdaXMLIO::sendXML($result, "root", SKIP_DEBUG);
            } else {
                // session expired, send OK that user can log in again
                $msg = "<response status=\"ok\">Session expired!</response>";
                IdaXMLIO::sendXML($msg, "root", SKIP_DEBUG);
            }
            break;


//*********************
// IMPORT
//  - it calls parseDataFromXML differently(no template check)
//  - map_id (original id) is set for a record if it is provided
//  - no word indexing
//*********************
        // add new instance
        case "import":
            if(IdaSession::checkSession()) {


                foreach($root->childNodes as $class) {

                    $result .= XML::importXML($class);

                }

                IdaXMLIO::sendXML($result);

            } else {
                $msg = "<error>You are not logged in!</error>";
                IdaXMLIO::sendXML($msg);
            }

            break;



//*********************
// ADD
//*********************
        // add new instance
        case "add":
            if(IdaSession::checkSession()) {
                foreach($root->childNodes as $class) {

                    $target = new IdaRecord($class->tagName);

                    // validate data and find ID's
                    $target->parseDataFromXML(&$class);

                    if(!$target->hasErrors()) {

                        if($class->hasAttribute("nocheck")) {

                            // if nocheck is set, do not check uniqueness, just save
                            $target->save();
                            $result .=   '<response status="ok" id="'.$target->id
                                            .'" class="'.$target->className
                                            .'"></response>';
                        } else {

                            Debug::printMsg("Record is unique!");
                            // this is unique -> make a new record
                            if($target->save())
                            $result .=   '<response status="ok" id="'.$target->id
                                            .'" class="'.$target->className
                                            .'"></response>';
                            else
                                $result .= '<response status="error" class="'.$target->className.'"><reason>Record '
                                    .'is empty!</reason></response>';


                        }


                    } else {

                      $result .=   '<response status="error" class="'.$target->className.'">'
                                    .error2XML($target->getErrors(), "reason")
                                    .'</response>';


                    }
                 }

                IdaXMLIO::sendXML($result);

            } else {
                $msg = "<error>You are not logged in!</error>";
                IdaXMLIO::sendXML($msg);
            }
            break;

//*********************
// GET 
//*********************
        // get record by id(s)
        case 'get':

            $time_start = microtime(true);

            try {

                $dom = new DomDocument('1.0', 'UTF-8');
                $base = $dom->createElement('root');
                $dom->appendChild($base);

                $xpath = new DOMXPath($xml);
                $children = $xpath->query("/get/record");
                $result = $xpath->query("/get/result/*");


                foreach($children as $record) {
                    if($record->hasAttribute("id")) {
                        $recId = $record->getAttribute("id");
                        $class =  IdaRecord::getClassNameFromRecordId($recId);
                    } else if($record->hasAttribute("map_id")) {

                        $recId = IdaDb::getIdByMapId($record->getAttribute("map_id"));
                        $class =  IdaRecord::getClassNameFromRecordId($recId);
                    }
                   // Debug::printPara($className);
                    $idaRecord = new IdaRecord($class["title"]);


                    $templDom = new DomDocument('1.0', 'UTF-8');
                    

                    switch($root->getAttribute("mode")) {

                    case "editable":
 
                        $idaRecord->isEditable = 1;

                        $dom2 = new DomDocument('1.0', 'UTF-8');
                        $base2 = $dom2->createElement('root');
                        $dom2->appendChild($base2);
                        $xpath2 = new DOMXPath($dom2);
                      
                      
                        // get template 
                        $templateXML2 = $idaRecord->makeXMLInputGrouped($dom2);
                        $base2->appendChild($templateXML2);
                        $result = $xpath2->query("/root/".$class["title"]."/*");

                        // load with template
                      //  $idaRecord->loadResult($recId, $result);

/*

        // create temporary table for records
        $sql = "CREATE TEMPORARY TABLE 
                    "._DBPREFIX."_root_0
                    (id integer,
                    INDEX(id))";

        IdaDB::exec($sql);

        // create temporary table for records
        $sql = "CREATE TEMPORARY TABLE 
                    "._DBPREFIX."_root_1
                    (id integer,
                    INDEX(id))";

        IdaDB::exec($sql);

        // create temporary table for records
        $sql = "CREATE TEMPORARY TABLE 
                    "._DBPREFIX."_root_2
                    (id integer,
                    INDEX(id))";

        IdaDB::exec($sql);

        // create temporary table for records
        $sql = "CREATE TEMPORARY TABLE 
                    "._DBPREFIX."_root_3
                    (id integer,
                    INDEX(id))";

        IdaDB::exec($sql);

        // create temporary table for records
        $sql = "CREATE TEMPORARY TABLE 
                    "._DBPREFIX."_root_4
                    (id integer,
                    INDEX(id))";

        IdaDB::exec($sql);



        // create temporary table for records
        $sql = "CREATE TEMPORARY TABLE 
                    "._DBPREFIX."_search_tmp
                    (id integer,
                    INDEX(id))";

        IdaDB::exec($sql);


        IdaDB::insert("root_0", array("id"=>454),1,1); 


                        IdaDb::createTmpTables($target->classNameFull);
                        $levelData = IdaDB::loadLevelData($result);
                        IdaRecord::teeXML($dom, $base, $levelData, $hideLinks);
*/

                        // OLD METHOD
                        $idaRecord->loadResult($recId, $result);
                        $idaRecord->getGroupedXML($base);

                        Template::applyTemplate($base->firstChild, $templateXML2);  
                       // $templateXML = $idaRecord->makeXMLInputGrouped($dom);
                        //$templateNode = $dom->createElement('template');
                        //$templateNode->appendChild($templateXML);
                        //$base->appendChild($templateNode);
             
                       // $recNode->appendChild($idaRecord->getPossibleEvents($dom));
                       // $recNode->appendChild($idaRecord->getPossibleParts($dom));
                        break;

                    default:

                        $idaRecord->loadResult($recId, $result);
                        $idaRecord->getGroupedXML($base);
                        break;


                    }
            }

                    // dome debug info
                    Debug::memoryUsage();
                    Debug::writeDebugXML($base, $time_start);

                    IdaXMLIO::sendAsXML($dom->saveXML());

            } catch (Exception $e) {
                if(DEBUG) {
                    print '<h2>'.$e->getMessage().'</h2>';
                    print "<strong>in file:</strong> {$e->getFile()}<br />\n";
                    print "<strong>line:</strong> {$e->getLine()}<br />\n";
                    print '<strong>trace:</strong><pre>';
                    print_r($e->getTrace());
                    print "</pre>";

                } else 
                   
                    throw new Exception($e->getMessage().'Could not find record!');

            }
            if(DEBUG) {
                $time_end = microtime(true);
                $time = $time_end - $time_start;
                echo "<p> Executed in $time sec</p>";
                echo "<p>kyselyitä  $qCounter </p>";
            }

            break;


//*********************
// SEARCH
//*********************
        // search records by xml
        case "search":

            $hideLinks = 0;
            $G = singleton::getInstance("Globals");
            $target = new IdaRecord($root->firstChild->tagName);
            $target->placeSearch = ($root->getAttribute("place_search"));

            $dom = new DomDocument('1.0', 'UTF-8');
            $base = $dom->createElement('results');
            $dom->appendChild($base);

            $xpath = new DOMXPath($xml);
            $md5 = md5($xml->saveXML());

            //********  SEARCH CACHE ************//
             try {

                $cache = IdaXMLIO::getCacheFile($md5);
                IdaXMLIO::sendAsXml($cache);
                return;

            // if cache failed then start search
            } catch (Exception $e) {

                // if result set is defined then use it
                $result = $xpath->query("/search/result/descendant-or-self::*");
                if($result->length &&  $root->getAttribute("result") != "count") {
                    $root->setAttribute("result","result");
                    if($root->getAttribute("hidelinks") == "true") {
                        $hideLinks = 1;
                    }
                }

        // create temporary table for records
        $sql = "CREATE TEMPORARY TABLE 
                    "._DBPREFIX."_root_0
                    (id integer,
                    INDEX(id))";

        IdaDB::exec($sql);

        // create temporary table for records
        $sql = "CREATE TEMPORARY TABLE 
                    "._DBPREFIX."_root_1
                    (id integer,
                    INDEX(id))";

        IdaDB::exec($sql);

        // create temporary table for records
        $sql = "CREATE TEMPORARY TABLE 
                    "._DBPREFIX."_root_2
                    (id integer,
                    INDEX(id))";

        IdaDB::exec($sql);

        // create temporary table for records
        $sql = "CREATE TEMPORARY TABLE 
                    "._DBPREFIX."_root_3
                    (id integer,
                    INDEX(id))";

        IdaDB::exec($sql);

        // create temporary table for records
        $sql = "CREATE TEMPORARY TABLE 
                    "._DBPREFIX."_root_4
                    (id integer,
                    INDEX(id))";

        IdaDB::exec($sql);



        // create temporary table for records
        $sql = "CREATE TEMPORARY TABLE 
                    "._DBPREFIX."_search_tmp
                    (id integer,
                    INDEX(id))";

        IdaDB::exec($sql);





                $records = $target->searchByXML_new($root->firstChild, "root_0");
                //$recordCount =  count($records);
                $recordCount = IdaDb::countRows("root_0");
                $base->setAttribute("count", $recordCount); 
          
                switch($root->getAttribute("result")) {

                case "count":
                    break;

                case "result":
                        IdaDb::createTmpTables($target->classNameFull);
                        $levelData = IdaDB::loadLevelData($result);
                        IdaRecord::teeXML($dom, $base, $levelData, $hideLinks);


                        Debug::memoryUsage();
                        Debug::writeDebugXML($base, $time_start);
                    break;

                default:
                    Debug::memoryUsage();
                    $xml = XML::makeResultListXML($records);
                    IdaXMLIO::sendXml($xml, "results", null, $recordCount);
                    return;

                }

                // send XML and write cache file
                Debug::printArray($records,"result:");
                IdaXMLIO::sendAsXML($dom->saveXML(),0,$md5);
                break;

         } // cache try/catch ends



//*********************
// LINKBOARD
//*********************
        // get linkboard content and unlinked files
        case 'linkboard' :

            $dom = new DomDocument('1.0', 'UTF-8');
            $linkBoardNode = $dom->createElement('linkboard');
            $dom->appendChild($linkBoardNode);

            if($root->hasChildNodes()) {
                foreach($root->childNodes as $command){
                    switch($command->tagName) {

                    case 'add' :
                        $id = $command->getAttribute("rec_id");
                        $sql = "SELECT filename from ida_file_appellation AS f INNER JOIN ida_file_appellation_join AS fj ON f.id = fj.property AND fj.subject = $id";
                        $res = IdaDb::query($sql);
                        if(count($res) == 1) {
                            $row = $res->fetchRow();
                            IdaDb::insert("_sys_linkboard", array("record_id"=>$id, "filename"=>$row["filename"]));
                        }
                    break;

                    case 'clear' :
                        $sql = "DELETE FROM ida__sys_linkboard";
                        $res = IdaDb::exec($sql);
                    break;

                    }
                }
            } else {
                $linked = IdaDB::select("_sys_linkboard",array(),array("record_id", "filename"),"");
                foreach($linked as $file) {
                    $f = $dom->createElement('rs');
                    $f->setAttribute('id', $file["record_id"]);
                    $f->setAttribute('filename', $file["filename"]);
                    $linkBoardNode->appendChild($f);

                }
            }
            IdaXMLIO::sendAsXML($dom->saveXML());
            break;


//*********************
// VALIDATE
//*********************
        // check if insert (add) is valid
        case "validate":
            foreach($root->childNodes as $class) {
                $target = new IdaRecord($class->tagName);
                if($target) {
                    $target->parseDataFromXML($class);
                    $result[] = $target->errors;
                }
             }
            if($target->hasErrors()) {
                $msg = error2XML($result);
            } else {
                $msg =  '<response status="ok"></response>';
            }
            IdaXMLIO::sendXML($msg);

            break;



//*********************
// EDITDATA
//*********************
        // edit data (row)
        case "editdata":
            if(IdaSession::checkSession()) {
                $class = IdaRecord::getClassNameFromRecordId($root->getAttribute('record'));
                $instance = new IdaRecord($class["id"]);
                $instance->editRow($root);
                $msg =  '<response status="ok"></response>';
                IdaLog::saveEditXML($root->ownerDocument->saveXML(), $instance->id, $root->tagName);
            } else {
                $msg = "<error>You are not logged in!</error>";
            }
            if(!DEBUG) IdaXMLIO::sendXML($msg);

            break;

//*********************
// ADDDATA
//*********************
        // add a row to a existing record
        case "adddata":
            if(IdaSession::checkSession()) {
                $class = IdaRecord::getClassNameFromRecordId($root->getAttribute('record'));
                $instance = new IdaRecord($class["id"]);
                $instance->addRow($root);
                $msg =  '<response status="ok"></response>';
                IdaLog::saveEditXML($root->ownerDocument->saveXML(), $instance->id, $root->tagName);
            } else {
                $msg = "<error>You are not logged in!</error>";
            }
            if(!DEBUG) IdaXMLIO::sendXML($msg);
            
            break;

//*********************
// IMPORT_FILE
//*********************
        // get uploaded files
        case 'importfile' :

            $dom = new DomDocument('1.0', 'UTF-8');

            // add root node
            $res = $dom->createElement('root');
            $dom->appendChild($res);

            
            $files = IdaDB::fetchUploadedFiles();

            foreach($root->childNodes as $file) {

                if($file->hasAttribute("filename")) {

                    $filename = $file->getAttribute("filename");
                    $f = $dom->createElement('file');
                    $res->appendChild($f);
                    $f->setAttribute('filename', $filename);

                    try {
                       IdaUploader::importImage($filename);
                        $f->setAttribute('status', "ok");

                    } catch (Exception $e) {

                        $f->setAttribute('status', $e->getMessage());
                    }
                }
            }

            IdaXMLIO::sendAsXML($dom->saveXML());
            break;



//*********************
// uploads
//*********************
        // get uploaded files
        case 'uploads' :

            $dom = new DomDocument('1.0', 'UTF-8');

            // add root node
            $root = $dom->createElement('root');
            $dom->appendChild($root);


            $files = IdaDB::fetchUploadedFiles();

            foreach($files as $file) {

                $f = $dom->createElement('file');
                $f->setAttribute('filename', $file['fname']);
                $f->setAttribute('id', $file['id']);
                $f->setAttribute('original_filename', $file['original_filename']);
                $f->setAttribute('exif_date', $file['exif_date']);
                $root->appendChild($f);
            }

            IdaXMLIO::sendAsXML($dom->saveXML());
            break;

//*********************
// unlinked image
//*********************
        // get unlinked image
        case 'unlinked' :

            $dom = new DomDocument('1.0', 'UTF-8');

            // add root node
            $root = $dom->createElement('root');
            $dom->appendChild($root);

$sql = "select rec.id,filename from ida__sys_records AS rec, ida__sys_classes_join as clj, ida_file_appellation AS fa, ida_file_appellation_join as faj where not exists (select * from ida__sys_records_join as rj where rec.id = rj.subject ) AND clj.subject = rec.id AND clj.property = 'U71' AND faj.subject = rec.id AND faj.property = fa.id";

            $images = IdaDB::query($sql);
            while (($row = $images->fetchRow())) {

                $f = $dom->createElement('Digital_Image');
                $f->setAttribute('filename', $row['filename']);
                $f->setAttribute('id', $row['id']);
                $root->appendChild($f);
            }

            IdaXMLIO::sendAsXML($dom->saveXML());
            break;




//*********************
// LINKFILE
//*********************
        // link (any) file
        case "linkfile":
            $class = IdaRecord::getClassNameFromRecordId($xml[0]['record']);
            $instance = new IdaRecord($class);
            $instance->id = $xml[0]['record'];
            $table = new IdaTable("file");
            $table->id = $xml[0]['file'];
            $prop = explode(".", $xml[0]['property']);
            $table->linkId = $prop[0];
            
            $instance->linkRow($table);
            $result = '<status>ok</status>';
            IdaXMLIO::sendXML($result);
            break;


//*********************
// UNLINKFILE
//*********************
        // remove file linkage
        case "unlinkfile":
            IdaDb::unlinkRow("file", $xml[0]['file'], $xml[0]['record']);
            $result = '<status>ok</status>';
            if(!DEBUG) IdaXMLIO::sendXML($result);

            break;


//*********************
// EDITLINKS
//*********************

        case "editlinks":
        foreach($root->childNodes as $command) {
            switch($command->tagName) {

            case "link":

                $result[] = XML::makeLink($command);

                break;

            case "unlink":

                $result[] = XML::removeLink($command);

                break;

            }
        }

        IdaXMLIO::sendXML(implode("",$result));

        break;


//*********************
// DELETECLASS
//*********************
        // delete user class if it has no children classes or instances
        case 'deleteclass':

            if(IdaSession::checkSession()) {
                $classId = $root->getAttribute("id");
                $class = new IdaRecord($classId);

                $result = '<status>Not implemented!</status>';
            } else {
                $result = "<error>You are not logged in!</error>";
            }

            IdaXMLIO::sendXML($result);    

            break;




//*********************
// CLASSTREE
//*********************
        // combine CRM's class tree with type tree
        case 'classtree':
       
            global $classes_rdfs; 
            $classId = 1;
            $className = "";
            $typeId = 0;
 

            try {

                $crmDom = new DomDocument('1.0', 'UTF-8');
                if(!$crmDom->Load($classes_rdfs, LIBXML_NOBLANKS))
                    die("file not found");


                $classDom = new DomDocument('1.0', 'UTF-8');
                $classRoot = $classDom->createElement('root');
                $classCRM = $classDom->createElement('Class');
                $classDom->appendChild($classRoot);
                $classRoot->appendChild($classCRM);
                $xpath = new DOMXPath($crmDom);


                if($root->hasAttribute("title")) {

                    $crmClass = new IdaRecord($root->getAttribute("title"));
                    $classCRM->setAttribute("title",$crmClass->className);
                    $classCRM->setAttribute("id",$crmClass->classId);
                    
                    if($crmClass->hasType)
                        $typeId = $crmClass->hasType;
                    
                } else {

                    throw new Exception('Root class not defined!');

                }
  
                XML::makeXMLTreeFromXML($classCRM, $crmClass->classNameFull, $xpath);
                    
                IdaXMLIO::sendAsXML($classDom->saveXML());
            } catch (Exception $e) {

                    throw new Exception($e->getMessage().'Could not find record!');

            }
            break;



//*********************
// TABLEINFO
//*********************
        // information about table for ontology editor
        case 'tableinfo':
        
  
            $tableName = $root->getAttribute("title");
            $table = new IdaTable($tableName);

            $dom = new DomDocument('1.0','UTF-8');
            $root = $dom->createElement('root');
            $dom->appendChild($root);  
            
            $table->getTableInfoXML($root);
            
            IdaXMLIO::sendAsXML($dom->saveXML());      
            break;
            


//*********************
// GET_IMPORT_LIST
//*********************
        // get list of predefined templates
        case "getimportlist": 

            $dom = new DomDocument('1.0', 'UTF-8');
            $root = $dom->createElement('root');
            $dom->appendChild($root);

            $temps = Template::getImportDirContent();
            foreach($temps as $file) {

                    $templ = $dom->createElement('file');
                    $templ->setAttribute("filename", $file);
                    $root->appendChild($templ);
                
            }
            
        IdaXMLIO::sendAsXML($dom->saveXML());
        
        break;
            

           
 

//*********************
// ADDCLASS
//*********************
        // add class by writing it to the ontology.rdfs
        case 'addclass':

            global $classes_rdfs;
            try {

                $CRM = new DomDocument('1.0', 'UTF-8');
                if(!$CRM->Load($classes_rdfs, LIBXML_NOBLANKS))
                    die("file not found");

                $xpath = new DOMXPath($xml);
                $classes = $xpath->query("/addclass/Class");

                foreach($classes as $class) {
                    $classId = $class->getAttribute("title");
                    

                    // TODO:valid user class name = U[1-9].title
                    if($classId[0] == "U"){
                        
                        // skip if class exists
                        $c = XML::getClassID($classId, 0);
                        if(count($c))
                            continue;

                        $node = $CRM->createElement("rdfs:Class");
                        $node->setAttribute("rdf:ID", $classId);

                        $subs = $class->getElementsByTagName("subClassOf");
                        $superClassStatus = true;

                        foreach($subs as $sub) {
                            $name = $sub->getAttribute("title");
                            // check that superclass exists
                            $c = XML::getClassID($name);
                            if(count($c)) {
                                $subClass = $CRM->createElement("rdfs:subClassOf");
                                $subClass->setAttribute("rdf:resource", "#".$c["id"].".".$c["title"]);
                                $node->appendChild($subClass);

                           } else {
                                $superClassStatus = false;
                                break;
                           }
                        }

                        if($superClassStatus) {
                            $CRM->firstChild->appendChild($node);
                            $CRM->formatOutput = true;
                            $CRM->save($classes_rdfs);
                        }

                    }
                }




            } catch (Exception $e) {

                    throw new Exception($e->getMessage().'Could not find record!');

            }
            break;


       


//*********************
// CLASSINFO
//*********************
        // information about classes for ontology editor
        case 'classinfo':

            try {
                    $idaRecord = new IdaRecord($root->getAttribute("title"));


                    $dom = new DomDocument('1.0', 'UTF-8');
                    $root = $dom->createElement('root');
                    $class = $dom->createElement('class');
                    $dom->appendChild($root);
                    
                    if($idaRecord->hasType) {
                        $type = $dom->createElement("type");
                        $type->setAttribute("title", $idaRecord->G->typeNames[$idaRecord->hasType]["title"]);
                        $type->setAttribute("id", $idaRecord->hasType);
                        $root->appendChild($type);
                    }                    
                    
                    
                    $root->appendChild($class);

                    // get class comment if this is a CRM class
                    $commentNode = $dom->createElement('comment');
                    if(!$idaRecord->hasType) {
                        $vals = array("id"=>$idaRecord->classId);
                        //$comment = IdaDB::select("_sys_classes", $vals, array("comment"),"","onecell");
                        //$commentNode->nodeValue = $comment;
                    }
                    $class->appendChild($commentNode);

                    $class->setAttribute("title",$idaRecord->className);

                    $path = XML::pathToClass($idaRecord->classNameFull, array($idaRecord->classNameFull));
                    //$res = IdaRecord::pathToClass($idaRecord->classId, AS_ARRAY);
                    //$links = IdaDb::getProperties($res);
                    Debug::printArray($links);

                    $props = $dom->createElement('possible_properties');
                    $class->appendChild($props);
                   // add template
                    $templateNode = $dom->createElement('template');
                    $templateXML = $idaRecord->makeXMLInputGrouped($dom);
                    $templateNode->appendChild($templateXML);
                    $root->appendChild($templateNode);
                   // $templateNode->appendChild($idaRecord->getPossibleEvents($dom));
                   // $templateNode->appendChild($idaRecord->getPossibleParts($dom));


                    IdaXMLIO::sendAsXML($dom->saveXML());

            } catch (Exception $e) {

                    throw new Exception($e->getMessage().'Could not find record!');

            }
            break;


//*********************
// GETPLACES2
//*********************
        // get place hierarchy (2 iterations)
        case "getplaces2":

            global $G;
            $id = 0;

            // check that class is valid
            $target = new IdaRecord($root->firstChild->tagName);

            // if there are no search terms so list all by their NAMES
            // make search
            if($root->firstChild->hasAttribute("id"))
                $id = $root->firstChild->getAttribute("id");


            $round1 = IdaDb::select("_sys_placeorder", array(),"id");
            $tbl = new IdaTable("appellation");
            $tbl->loadData($round1);

            $dom = new DomDocument('1.0', 'UTF-8');
            $classRoot = $dom->createElement('root');
            $dom->appendChild($classRoot);

            $level = IdaDB::getOneLevelFromTree("_sys_placeorder", $id);
            foreach($level as $lnode) {
                $classRoot->appendChild($dom->createElement("rs"));
                 $classRoot->lastChild->setAttribute("id", $lnode["id"]);
                 $classRoot->lastChild->setAttribute("is_identified_by", $lnode["name"]);
                 if($lnode["children"] > 0) {
                    $classRoot->lastChild->setAttribute("children", "yes");
                    $level2 = IdaDB::getOneLevelFromTree("_sys_placeorder", $lnode["id"]);
                    foreach($level2 as $l2) {
                        $classRoot->lastChild->appendChild($dom->createElement("rs"));
                        $classRoot->lastChild->lastChild->setAttribute("id", $l2["id"]);
                        $classRoot->lastChild->lastChild->setAttribute("is_identified_by", $l2["name"]);
                        if($l2["children"] > 0) {
                            $classRoot->lastChild->lastChild->setAttribute("children", "yes");
                        }
                    }

                 }
            }
            IdaXMLIO::sendAsXML($dom->saveXML());
            break;


//*********************
// GETPLACES
//*********************
        // get place hierarchy 
        case "getplaces":

            global $G;
            $id = 0;

            // check that class is valid
            $target = new IdaRecord($root->firstChild->tagName);

            // if there are no search terms so list all by their NAMES
            // make search
            if($root->firstChild->hasAttribute("id"))
                $id = $root->firstChild->getAttribute("id");

            $dom = new DomDocument('1.0', 'UTF-8');
            $classRoot = $dom->createElement('root');
            $dom->appendChild($classRoot);

            $tree = IdaDB::getInstanceTree("_sys_placeorder", $id);
            XML::makeXMLTree($tree, $classRoot, "Place");
            IdaXMLIO::sendAsXML($dom->saveXML());
            break;

            


//*********************
// TABLEROWS
//*********************

        case 'tablerows':

            $xml = "";
            $allowedSystemTables = array("_sys_records", "_sys_records_join");
            $fields = array("id");

            try {

                if($root->hasAttribute("table")) { 
                    $tableName = $root->getAttribute("table");

                    if($root->hasAttribute("fields")) {
                        $fields = explode(",", $root->getAttribute("fields"));
                    }

                    if(!in_array($tableName, $allowedSystemTables)) {

                        // check that this is a valid data table
                        $table = new IdaTable($root->getAttribute("table"));
                       
                    } 


                    $result = IdaDB::select($tableName, array(), $fields);

                    foreach($result as $res) {

                        $f = "";
                        foreach($fields as $field) {
                            if($field == "link_type") // add F to links
                                $f .= " ".$field."=\"".$res[$field]."F\"";
                            else
                                $f .= " ".$field."=\"".$res[$field]."\"";
                         }

                        $xml .= "<row $f />";
                    }
                

                } else {

                    throw new Exception('No table defined!');
                }


            } catch (Exception $e) {

                print $e->getMessage();

                exit();
            }

            IdaXMLIO::sendXml($xml, "results");

            break;

//*********************
// DROPINDEX
//*********************

        case 'dropindex':

            $result = "";

            try {

                IdaDB::dropIndex();
                $result = "Word indexes dropped!";


            } catch (Exception $e) {

                print $e->getMessage();

                exit();
            }

            IdaXMLIO::sendXml($result, "results");



        break;


//*********************
// DROP_PLACETREE
//*********************

        case 'drop_placetree':

            $xml = "";

            try {

                IdaDb::deleteAll("_sys_placeorder");

            $target = new IdaRecord("Place");

                // create temporary table for records
                $sql = "CREATE TEMPORARY TABLE 
                            "._DBPREFIX."_root_0
                            (id integer,
                            INDEX(id))";

                IdaDB::exec($sql);

                // find all places
                $query = "<Place />";
                $xmlQuery = new DOMDocument('1.0', 'UTF-8');
                $xmlQuery->loadXML($query);
                $records = $target->searchByXML_new($xmlQuery->firstChild, "root_0");
                $result = IdaDB::select("root_0", array(), array("id"), "", "onecol");

                // insert dummy root (0) node
                IdaDb::insert("_sys_placeorder", array("id"=>0, "lft"=>1, "rgt"=>2),NO_ID);

                // put them in the placeorder table under the root object
                foreach($result as $row) {
                    IdaDb::insert2PreOrderedTree("_sys_placeorder", $row, 0);
                }

            } catch (Exception $e) {

                print $e->getMessage();

                exit();
            }
            IdaXMLIO::sendAsXml("<root>place tree dropped!</root>");



        break;


//*********************
// REBUILD_PLACETREE
//*********************

        case 'rebuild_placetree':

            $xml = "";

            try {
                $result = IdaDB::select("_sys_placeorder", array(), array("id"), "", "onecol");
                foreach($result as $place) {
                    $res = IdaDB::select("_sys_records_join", array("link_type"=>"P89", "property"=>$place), array("subject"), "", "onecol");
                    foreach($res as $subPlace) {
                        IdaDb::insert2PreOrderedTree("_sys_placeorder", $subPlace, $place);
                    }
                }
            } catch (Exception $e) {

                print $e->getMessage();

                exit();
            }
exit();
            IdaXMLIO::sendXml($xml, "results");



        break;




//*********************
// REINDEX
//*********************

        case 'reindex':

            $xml = "";

            try {

                if($root->hasAttribute("table")) { 

                    IdaDb::createTempTable();
                    $table = new IdaTable($root->getAttribute("table"));
                    $row = $root->getAttribute("row");
                    $result = IdaDB::select($table->tableName."_join", array("property"=>$row), array("subject"), "", "onecell");
                    $xml = $result;
                    $table->load($row);
                    IdaDB::indexWords($result, $table);
                    IdaDb::dropTempTable();


                } else {
                    throw new Exception('No table defined!');
                }


            } catch (Exception $e) {

                print $e->getMessage();

                exit();
            }

            IdaXMLIO::sendXml($xml, "results");



        break;


//*********************
// QUICKSEARCH
//*********************
        // search records by they appellations

        case 'quicksearch':


            try {

                if($root->hasAttribute("class")) 
                    $class = $root->getAttribute("class");
                else
                    $class = "E1";

                if(trim($root->nodeValue) != "") {
                    $instance = new IdaRecord($class);
                    $xml = $instance->quicksearch($root->nodeValue);
                } else {
                    $xml = "";
                }


            } catch (Exception $e) {

                print $e->getMessage();

                exit();
            }

            IdaXMLIO::sendXml($xml, "results");

            break;


//*********************
// LIST
//*********************
        // list types and records

        case 'list':


            try {

                if($root->hasAttribute("title")) 
                    $class = $root->getAttribute("title");
                else
                    throw new Exception('No class defined!');

                $instance = new IdaRecord($class);
                $result = IdaDb::listTypes($instance);
                Debug::printArray($result);

            } catch (Exception $e) {

                print $e->getMessage();

                exit();
            }

            $xml = XML::makeListXML($result);
            IdaXMLIO::sendXml($xml, "results");

            break;



        default:
            throw new Exception('well-formed XML but invalid content!'.$root->tagName);
            break;
    }



} catch (Exception $e) {
    if(DEBUG) {
        print '<h2>'.$e->getMessage().'</h2>';
        print "<strong>in file:</strong> {$e->getFile()}<br />\n";
        print "<strong>line:</strong> {$e->getLine()}<br />\n";
        print '<strong>trace:</strong><pre>';
        print_r($e->getTrace());
        print "</pre>";

    } else {

            $msg = '<response status="error">';
            $msg = $msg."<error>";
            $msg = $msg.$e->getMessage();
            $msg = $msg."</error>";
            $msg = $msg."</response>";

            IdaXMLIO::sendXml($msg);

    }
    exit();
}





// ****************************************************************'
//**************** FUNCTIONS ****************************************


function error2XML($errors, $tag="error", &$str="") {
    foreach($errors as $error) {
        if(is_array($error))
            error2XML($error, $tag, $str);
        else
            $str .= "<".$tag.">".$error."</".$tag.">";
    }
    return $str;
}


// find tables for columns
function prepareXML($xml, $cols) {

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
                                prepareXML($link, $cols);
                        }
                    }
                }
            }
        }
    }

}



function sort_by_key($array, $dkey) {
    global $key;
    $key = $dkey;
    function compare($a, $b) {
        global $key;
        return strcmp($a[$key], $b[$key]);
    }
    usort($array, "compare");
    return $array;
}



?>
