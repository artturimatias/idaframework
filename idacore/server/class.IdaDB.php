<?php

/**
 * SQL
 * @package ida
 */

/**
 *
 */
ob_start();

// check if config.php is overridden
$root = $_SERVER['DOCUMENT_ROOT'];
$arr_path = explode("/", $_SERVER['PHP_SELF']);
unset($arr_path[count($arr_path)-1]);
unset($arr_path[count($arr_path)-1]);
$path = implode($arr_path,"/");
if(file_exists($root.$path.'/config.php')) {
    $config = $root.$path.'/config.php';
} else {
    $config = '../config.php';
}
require_once('class.IdaXMLIO.php');
require_once($config);
require_once('MDB2.php');

define('_DSN', _DATABASE_TYPE.'://'._DBUSER.':'._DBPASS.'@localhost/'._DBNAME);
set_exception_handler("IdaXMLIO::sendError");


/**
 * Static class for all sql-query stuff
 * @package ida
 */
class IdaDB {


    static function searchByName($target, $terms) { 

        if(count($terms)) {
            $searchWord = " WHERE word LIKE ? ";
            $term = array($terms[0]."%");
            print_r($term);
        } else {
            $searchWord = " ";
            $term = array();

        }

        // if we have more than one search terms, we must include word_id so we can check that we don't match same word twice
        if (count($terms) > 1)
            $includeWordId = "word_id ,";
        else
            $includeWordId = " ";


        // get class tree so we can limit matches to certain class
        $ctree = XML::nodeTree($target->classNameFull, array($target->classNameFull));
        //$ctree = array("'".$target->classId."'");
        foreach($ctree as $node) {
            $ex = explode(".", $node);
            $nodes[] = "'".$ex[0]."'";
        }
        $class = " in (".implode(",", $nodes).")";
        $classQuery = " AND cj.property $class"; 

    $sql = "
        CREATE TEMPORARY TABLE "._DBPREFIX."_tem0 AS 
        SELECT DISTINCT
            cj.property as class_id, 
            cj.has_type as type_id,
            record_id,
            name,
            start_year,
            start_month,
            $includeWordId
            start_day

        FROM
            "._DBPREFIX."__sys_words as w 
        INNER JOIN
            "._DBPREFIX."__sys_words_join as wj
            ON
                wj.word_id = w.id
                AND
                wj.column_id = 1
        LEFT JOIN
            "._DBPREFIX."_appellation_join as aj
            ON
                aj.subject = wj.record_id
        LEFT JOIN
            "._DBPREFIX."_appellation as a
            ON
                a.id = aj.property

         LEFT JOIN
            "._DBPREFIX."_time_span_join as tj
            ON
                tj.subject = wj.record_id
        LEFT JOIN
            "._DBPREFIX."_time_span as t
            ON 
                t.id = tj.property

         INNER JOIN
             "._DBPREFIX."__sys_classes_join AS cj
            ON
                record_id = cj.subject
                $classQuery  
        $searchWord

        ORDER BY 
            name
        ";

/*    
    $sql = "CREATE TEMPORARY TABLE "._DBPREFIX."_tem0 AS 
            select cj.*,a.* 
            FROM ida__sys_classes_join as cj 
            INNER JOIN ida_appellation_join AS aj ON aj.subject = cj.subject 
            INNER JOIN ida_appellation as a ON a.id = aj.property 
            WHERE cj.property ".$class;
*/


        IdaDb::prepareExecute($sql, $term, "onecol");

    }








    static function searchByNameRecursive ($round, $term) {


       $term = $term."%";


        $prev = $round - 1;
        // valitse ne recordit tem:istä
        $sql = "
            CREATE TEMPORARY TABLE "._DBPREFIX."_tem$round AS
            SELECT 
                    t.record_id,
                    t.word_id, 
                    type_id,
                    class_id,
                    start_year,
                    start_month,
                    name
        
                FROM 
                    "._DBPREFIX."__sys_words_join AS wj 
                INNER JOIN 
                    "._DBPREFIX."__sys_words AS w 
                ON  
                    wj.word_id = w.id 
                INNER JOIN
                    "._DBPREFIX."_tem$prev as t
                ON
                    t.record_id = wj.record_id
                    AND
                    t.word_id != wj.word_id
                        
                WHERE
                    w.word like ?
                    ";
                

        Debug::printMsg($sql);
        IdaDb::prepareExecute($sql, array($term), "onecol");

    }

    static function searchByClass($target, $orderField=0) {


        $ctree = XML::nodeTree($target->classNameFull, array($target->classNameFull));
        $cpath = XML::pathToClass($target->classNameFull, array($target->classNameFull));

        // if order field is not set, then order by appellation or time-span
        if(!$orderField) {
            if(in_array(_EVENT_CLASSNAME, $cpath)) {
                $table = "time_span";
                $field = "a.start_year,a.start_month,a.start_day";
            } else {
                $table = "appellation";
                $field = "a.name";
            }
        }

        $orderSql = " LEFT JOIN "._DBPREFIX."_".$table."_join AS aj ON rec.id = aj.subject LEFT JOIN "._DBPREFIX."_".$table." AS a ON a.id = aj.property ORDER BY ".$field;

        foreach($ctree as $node) {
            $ex = explode(".", $node);
            $nodes[] = "'".$ex[0]."'";
        }
        $class = " in (".implode(",", $nodes).")";
        $classQuery = " AND cj.property $class"; 



    $sql = "
        SELECT DISTINCT
            cj.property as class_id, 
            rec.id as id,
            $field
        FROM
             "._DBPREFIX."__sys_records AS rec

        INNER JOIN
             "._DBPREFIX."__sys_classes_join AS cj
            ON
                rec.id = cj.subject
                $classQuery  

        $orderSql

        ";

        return IdaDb::prepareExecute($sql);

    }
    // list all types that are in use under certain class (target)
    static function listTypes($target) {

        $classId = $target->classId;

        $sql = "
            SELECT DISTINCT  
                    rj.property AS record_id,
                    name
        
                FROM 
                    "._DBPREFIX."__sys_classes_join AS cj 
                INNER JOIN 
                    "._DBPREFIX."__sys_records_join AS rj 
                    ON  
                        rj.subject = cj.subject 
                INNER JOIN
                    "._DBPREFIX."_appellation_join as aj
                    ON
                        aj.subject = rj.property
                INNER JOIN
                    "._DBPREFIX."_appellation as a
                    ON
                        a.id = aj.property
                        
                WHERE
                    cj.property = '$classId' AND rj.link_type = 'P2'
                    ORDER BY name
                    ";

        return IdaDb::prepareExecute($sql, array());


    }

    static function getIdByMapId($mapId) {

        return IdaDb::select("_sys_records", array("map_id"=>$mapId), array("id"), "", "onecell");
    }


    static function prepare ($sql, $type=MDB2_PREPARE_RESULT) {

       // Debug::printPara($sql);

        $mdb2 =& MDB2::singleton(_DSN);
        if (PEAR::isError($mdb2)) {
            throw new Exception($mdb2->getMessage());
        }

        $mdb2->setFetchMode(MDB2_FETCHMODE_ASSOC);
        $mdb2->setCharset("utf8");

        $prep = $mdb2->prepare($sql, array(), $type);

        if (PEAR::isError($prep)) {
            throw new Exception($prep->getMessage());
        }

        return $prep;


    }


    static function execute($prep, $data, $type="default") {

        if(DEBUG) {
            $time_start = microtime(true);
        }

        $result = $prep->execute($data);

        if(DEBUG) {
             $time_end = microtime(true);
            $time = $time_end - $time_start;
            global $wtime ;
            global $qCounter ;
            $qCounter++;
            $wtime += $time;
            if(SQL_DEBUG) echo "<p>execute Executed in <b> $time </b> sec </p>";

        }

        if (PEAR::isError($result)) {
            throw new Exception($result->getMessage());
        }

        switch($type) {

        case "default":
            return $result->fetchAll();
            break;

        case "all":
            return $result->fetchAll(MDB2_FETCHMODE_ASSOC,1,0,1);
            break;

        case "onecol":
            return $result->fetchCol();
            break;

        case "onerow":
            return $result->fetchRow();
            break;

        case "onecell":
            return $result->fetchOne();
            break;

        case "insert":
            return $result;
            break;
        }
    }


    static function prepareExecute($sql, $data, $type="default") {

        if(DEBUG) {
            $time_start = microtime(true);
            }

        if($type=="insert" || $type=="update" || $type=="delete")
            $prep = IdaDB::prepare($sql, MDB2_PREPARE_MANIP);
        else
            $prep = IdaDB::prepare($sql, MDB2_PREPARE_RESULT);


        if(DEBUG) {
            $time_end = microtime(true);
            $time = $time_end - $time_start;
            global $wtime ;
            global $qCounter ;
            $qCounter++;
            $wtime += $time;
           // echo "<p> prepareExecute Executed in <b> $time </b>sec </p>";
        }

        $res = IdaDB::execute($prep, $data, $type);
        $prep->free();
        return $res;

    }






    static function query($sql,$type="default", $key=0) {

        if(DEBUG) {
            $time_start = microtime(true);
             if(SQL_DEBUG) echo "<p>".__FUNCTION__."->".$sql."</p>";
            }


        if(stristr($sql, "insert")) {
            throw new Exception('<p>insert used in query! (use exec)</p>');
            die();
        }

        $mdb2 =& MDB2::singleton(_DSN);
        $mdb2->setCharset("utf8");

        if (PEAR::isError($mdb2)) {
           // trigger_error($mdb2->getMessage(), E_USER_ERROR);
            throw new Exception($mdb2->getMessage().$mdb2->getUserInfo());
        }

        $mdb2->setFetchMode(MDB2_FETCHMODE_ASSOC);

        switch($type) {

        case "default":
            $result = $mdb2->query($sql);
            break;

        case "all":
            $result = $mdb2->queryAll($sql,0,0,$key);
            break;

        case "onerow":
            $result = $mdb2->queryRow($sql);
            break;

        case "onecol":
            $result = $mdb2->queryCol($sql);
            break;

        case "onecell":
            $result = $mdb2->queryOne($sql);
            break;
        }

        if (PEAR::isError($result)) {
           // trigger_error($result->getMessage(), E_USER_ERROR);
           echo "ERROR";
            throw new Exception('sql:<p>'.$sql.$result->getMessage().'</p>');
            }

        if(DEBUG) {
             $time_end = microtime(true);
            $time = $time_end - $time_start;
            global $wtime ;
                        global $qCounter ;
            $qCounter++;
            $wtime += $time;
            if(SQL_DEBUG) echo "<p>query Executed in <b> $time </b>sec </p>";
         //   echo "<p>KOKONAISAIKA  <b> $wtime </b>sec </p>";
        }


        return $result;
    }




    static function exec($sql) {

        $dsn =_DATABASE_TYPE.'://'._DBUSER.':'._DBPASS.'@localhost/'._DBNAME;

        $mdb2 =& MDB2::singleton($dsn);
        $mdb2->setCharset("utf8");
        if (PEAR::isError($mdb2)) {
            trigger_error($mdb2->getMessage(), E_USER_ERROR);
            //throw new Exception($mdb2->getMessage().$mdb2->getUserInfo());
        }

        $affected = $mdb2->exec($sql);
        if (PEAR::isError($affected)) {
            //trigger_error($affected->getMessage(), E_USER_ERROR);
            throw new Exception('sql:<p>'.$sql.'</p>');
        }
    }




    static function nextId($tbl) {

        $dsn =_DATABASE_TYPE.'://'._DBUSER.':'._DBPASS.'@localhost/'._DBNAME;

        $mdb2 =& MDB2::singleton($dsn);
        if (PEAR::isError($mdb2)) {
            trigger_error($mdb2->getMessage(), E_USER_ERROR);
            //throw new Exception($mdb2->getMessage().$mdb2->getUserInfo());
        }

        return $mdb2->nextId(_DBPREFIX.'_'.$tbl);
    }




    static function currId($table) {

        $dsn =_DATABASE_TYPE.'://'._DBUSER.':'._DBPASS.'@localhost/'._DBNAME;

        $mdb2 =& MDB2::singleton($dsn);
        if (PEAR::isError($mdb2)) {
            //trigger_error($mdb2->getMessage(), E_USER_ERROR);
            throw new Exception($mdb2->getMessage().$mdb2->getUserInfo());
        }

        $id = $mdb2->currID(_DBPREFIX."_".$table);
        if (PEAR::isError($id)) {
            //trigger_error($id->getMessage(), E_USER_ERROR);
            die($id->getMessage());
        }
        return $id;
    }



/***************************************************************************
    IdaDB::linkRowToRecord
----------------------------------------------------------------------------
    links data table row to record
****************************************************************************/



    static function linkRowToRecord($table, $domain) {

        $link = $table->linkId["id"].$table->linkId["dir"];

        $vals = array('subject'=>$domain->id, 'link_type'=>$link, 'property'=>$table->id);

        $res = IdaDB::select($table->tableName."_join", $vals, array("id"),"","onerow");

        if(count($res) == 0) {
            IdaDB::insert($table->tableName.'_join', $vals);
        }

    }


    static function unLinkRow ($tableName, $tableId, $rowId, $recordId) {


        $vals = array('subject'=>$recordId, 'property'=>$rowId);
        $res = IdaDB::select($tableName."_join", $vals, array("id"),"","onerow");

        if(count($res)) {
            IdaDB::removeWordIndexes ($recordId, $tableId, $rowId);
            IdaDB::deleteBy($tableName."_join",array("id"=>$res["id"]));
        }
    }

    // check for previous links and link records 
    static function linkRecordToRecord($subject, $target) {

        if(empty($target->id)) 
            throw new Exception("Linking (".$target->linkId['title'].") error: Target id not found!");
        if(empty($subject))
            throw new Exception("Linking (".$target->linkId['title'].") error: Subject id not found!");

        $dir = $target->linkId["dir"];
        $link = $target->linkId["id"];



        // we must reverse bakcward links
        if($dir == "B")
            $vals = array('subject'=>$target->id, 'link_type'=>$link, 'property'=>$subject->id);
        else
            $vals = array('subject'=>$subject->id, 'link_type'=>$link, 'property'=>$target->id);

        
        $res = IdaDB::select("_sys_records_join", $vals);


        if(!count($res)) {
            IdaDB::insert("_sys_records_join", $vals);
            /*
            // TODO: link hardcoded!
           if($link == "P89") {
               Debug::printMsg("insert2preorderedtree");
               if($dir == "F")
                    IdaDb::insert2PreOrderedTree("_sys_placeorder", $subject->id, $target->id);
                else if($dir == "B")
                    IdaDb::insert2PreOrderedTree("_sys_placeorder", $target->id, $subject->id);
            }
            */
 
        } else {
            throw new Exception("Link (".$subject->className.":".$subject->id." -> ".$target->linkId['title']." -> ".$target->className.":".$target->id." ) allready exists!");
        }
    }




    static function unLinkFromRecord($subjectId, $targetId, $linkId) {
        
        
        // get dir from link
        $dir = $linkId["dir"];
        $link = $linkId["id"];


        // we must reverse bakcward links
        if($dir == "B")
            $vals = array('subject'=>$targetId, 'link_type'=>$link, 'property'=>$subjectId);
        else
            $vals = array('subject'=>$subjectId, 'link_type'=>$link, 'property'=>$targetId);


        $res = IdaDB::select("_sys_records_join", $vals,"id","","onecell");


        if(count($res) ) {
            IdaDB::deleteBy("_sys_records_join",array("id"=>$res));

            // TODO: link hardcoded!
           if($link == "P89") {
               Debug::printMsg("removeFrompreorderedtree");
               if($dir == "F") {
                    // remove from tree
                 //   IdaDb::removeFromPreOrderedTree("_sys_placeorder", $subjectId);
                    // put it back in root level
                   // IdaDb::insert2PreOrderedTree("_sys_placeorder", $subjectId, 0);
              }
          }

        } else {
            throw new Exception("No such link found!".$subjectId."->".$link."->".$targetId );
        }
    }




    static function pathToNode($table, $id) {

        $ids = array();
        $where = array("id"=>$id);
        $res = IdaDb::select($table, $where, array("lft", "rgt"));

        foreach($res as $result) {

            $sql = "SELECT id FROM "._DBPREFIX."_".$table." WHERE lft < ? AND rgt > ? ORDER BY lft ASC";
            $arr = IdaDb::prepareExecute($sql, array($result["lft"], $result["rgt"]),"onecol");
           
            $ids = array_merge($arr, $ids);

        }
        $ids[] = $id;
        return $ids;
    }


    /**
        * get all sub nodes
        * @return array
     */
    static function NodeTree($table, $id) {
 
        $ids = array();
        $where = array("id"=>$id);
        $root = IdaDb::select($table, $where, array("lft", "rgt"));
       
        foreach($root as $cl) {
            $sql = "SELECT id  FROM "._DBPREFIX."_".$table." WHERE lft BETWEEN ? AND ? ORDER BY lft ASC";
            $res = IdaDb::prepareExecute($sql, array($cl["lft"], $cl["rgt"]),"onecol");
         
            // 
            $ids = array_merge($res, $ids);
            
        }
        
       // $ids[] = $id;     // insert class itself
        return $ids;
    
    }





    static function getTableColumns() {

        $sql = "SELECT id, table_id FROM "._DBPREFIX."__sys_columns";

        $data = IdaDB::prepareExecute($sql,array(), "all");
        return $data;
       // return IdaDB::query($sql,'onecol');
     }

/*********************************************************************************************
/*********************************************************************************************
                                    Generic functions
**********************************************************************************************
*********************************************************************************************/

    // return by default:
    // [first_col]=>[0]=>
    // [first_col]=>[0]=>

    static function select($table, $wherePairs, $sel='*', $order='',$qtype='default') {

    if(count($wherePairs))
        $where_str = " WHERE ".IdaDB::makeWhereFromArraysForPrep($wherePairs);
    else
        $where_str = "";

        if(is_array($sel)) {
            $sel = implode(',',$sel);
        }

        $sql = "SELECT
                    $sel
                FROM
                    "._DBPREFIX."_$table
                
                    $where_str $order";

        return IdaDB::prepareExecute($sql, $wherePairs, $qtype);


    }



/***************************************************************************
    IdaDB::insert
----------------------------------------------------------------------------
    insert with uid
****************************************************************************/


    static function insert($table, $inserts, $no_id=0, $no_date=0) {

        if(is_array($inserts)) {

            $tableName = _DBPREFIX."_$table";

            // set id
            if(!$no_id) {
                $inserts['id'] = IdaDB::nextId($tableName);
            }

           // $inserts['uid'] = $_SESSION['uid'];
           if(!$no_date) {
                $inserts['uid'] = 0;
                $inserts['timestamp'] = date('Y-m-d H:i:s');
           }

            foreach($inserts as $col=>$value) {

                $cols[] = $col;
                $values[] = $value;
                $holders[] = "?";

            }

            $cols_str = implode(',',$cols);
            $holder_str = implode(',',$holders);

        } else {

            throw new Exception('IdaDB::insert error:<p>must be array!</p>');
        }

        $sql = "INSERT into $tableName ($cols_str) VALUES ($holder_str)";

Debug::printMsg($sql);
Debug::printArray($values);
        IdaDB::prepareExecute($sql, $values, "insert");

        if(isset($inserts["id"]))
            return $inserts['id'];
        else
            return array();

    }


    static function update($tablename, $sets, $wherecol, $whereval) {
       
        if(is_array($sets)) {
            foreach($sets as $key=>$val) {
                $values[] = $val;
                $setpairs[] = $key." = ?";
            }
        }
        
        $set_str = implode(',',$setpairs);

        $sql = "UPDATE "._DBPREFIX."_$tablename SET $set_str WHERE $wherecol = '$whereval'";

        IdaDB::prepareExecute($sql, $values, "update");
     

    }



    static function isEmpty($table) {

        $res = IdaDB::select($table, array());
        if(count($res)) return 0;
        else return 1;
    }


    static function removeFromPreOrderedTree ($table, $id) {
    
        
        $select = array('id'=>$id);     
        $res = IdaDB::select($table, $select, array("lft","rgt"));      
        foreach($res as $ressub) {
 
            $rgt = $ressub["rgt"];
            $uvals = array($rgt - 1);
            $rgt_minus = (int) $rgt - 1;
            
            $sql = "DELETE FROM "._DBPREFIX."_".$table." WHERE id = $id";
            IdaDb::exec($sql);
            
             // subtract 2 from any value bigger than rgt value of the parent
            $sql = "update "._DBPREFIX."_".$table." set rgt = rgt-2 where rgt > $rgt_minus";
            IdaDb::exec($sql);
            
            $sql = "update "._DBPREFIX."_".$table."  set lft = lft-2 where lft > $rgt_minus";
            IdaDb::exec($sql);
            
        } 
        
    }
     


    // thanks to http://www.sitepoint.com/print/hierarchical-data-database/
    static function insert2PreOrderedTree($table, $insertId, $parentId) {

        // we allow only one instance in a tree so delete previous joins
        IdaDB::removeFromPreOrderedTree($table, $insertId);
echo "RUNNING PRE\n";
        // search for superclass (existence)
        $select = array('id'=>$parentId);     
        $res = IdaDB::select($table, $select, array("lft","rgt")); 

        // if that is found then add under it
        foreach($res as $ressub) {    

            $lft = $ressub["lft"];
            $rgt = $ressub["rgt"];
    
            $uvals = array($rgt - 1);
            $rgt_minus = (int) $rgt - 1;

            // add 2 to any value bigger than rgt value of the parent
            $sql = "update "._DBPREFIX."_".$table." set rgt = rgt+2 where rgt > $rgt_minus";
            IdaDb::exec($sql);
            
            $sql = "update "._DBPREFIX."_".$table."  set lft = lft+2 where lft > $rgt_minus";
            IdaDb::exec($sql);
            
            $insert = array("id"=>$insertId, "lft"=>$ressub["rgt"], "rgt"=>$rgt+1);
            IdaDB::insert($table, $insert,NO_ID);
        }
    }
/*
    // thanks to http://www.sitepoint.com/print/hierarchical-data-database/
    static function insert2PreOrderedTreeAsParent($table, $insertId, $childId) {

        // search for child
        $select = array('id'=>$childId);     
        $res = IdaDB::select($table, $select, array("lft","rgt")); 

        // if that is found then search if we are above it allready
        foreach($res as $ressub) {    
        
            $lft = $ressub["lft"];
            $rgt = $ressub["rgt"];
        
            $sql = "SELECT lft, rgt from "._DBPREFIX."_".$table." WHERE id = ? AND lft = ? AND rgt > ? ";   
            
            $vals = array($insertId, ($ressub["lft"])-1, $ressub["rgt"] );  
            $res2 = IdaDb::prepareExecute($sql, $vals);
            
            if(!count($res2)) {

                // search if record is in root level
                $res = IdaDB::select($table, array($insertId), array("lft","rgt")); 

                $uvals = array($lft - 1);

                // add 2 to any value bigger than rgt value of the parent
                 $sql = "update "._DBPREFIX."_".$table." set rgt = rgt+2, lft = lft+2 where rgt > ? AND lft > ?";
                IdaDb::prepareExecute($sql, array($rgt,$rgt));
                  $sql = "update "._DBPREFIX."_".$table." set rgt = rgt+2 where lft = 1 ";
                IdaDb::prepareExecute($sql, array());
                
                $sql = "update "._DBPREFIX."_".$table."  set lft = lft+1, rgt = rgt+1 where lft > ? AND lft < ?";
                IdaDb::prepareExecute($sql, array($lft-1, $rgt));

                
                $insert = array("id"=>$insertId, "lft"=>$lft, "rgt"=>$rgt+2);
                IdaDB::insert($table, $insert,NO_ID);
            }   
        }
    }
*/

    static function getOneLevelFromTree($tableName, $root) {
    
// select one level ordered by name from preordered traversal table
$sql = "SELECT 
node.id, ((node.rgt - node.lft)-1) AS children,(COUNT(parent.id) - (sub_tree.depth + 1)) AS depth, name 
FROM 
ida__sys_placeorder AS node, 
ida__sys_placeorder AS parent,
ida__sys_placeorder AS sub_parent,
ida_appellation AS appel,
ida_appellation_join AS appel_j, 
(SELECT node.id, (COUNT(parent.id) - 1) AS depth
 FROM 
ida__sys_placeorder AS node,
ida__sys_placeorder AS parent
WHERE 
node.lft 
BETWEEN 
parent.lft 
AND 
parent.rgt 
AND 
node.id = ? 

GROUP BY 
node.id ORDER BY node.lft) AS sub_tree 
WHERE 
node.lft 
BETWEEN parent.lft 
AND parent.rgt 
AND node.lft 
BETWEEN sub_parent.lft 
AND sub_parent.rgt 
AND sub_parent.id = sub_tree.id 
AND 
appel_j.subject = node.id
AND
appel.id = appel_j.property
GROUP BY 
node.id HAVING depth = 1 ORDER BY name;
";
        $tree = IdaDb::prepareExecute($sql, array($root));
        //Debug::printArray($tree);
        return $tree;

    }

    static function getInstanceTree($tableName, $root, $justId=false) {
    
        $select = array("id"=>$root);
        $res = IdaDB::select($tableName, $select, array("lft","rgt"));   

        if($justId) {
            $select = " o.id ";
            $mode = "onecol";
        } else {
            $select = "  cj.property, cj.has_type,o.id, lft, rgt, name ";
            $mode = "default";
        }

       // retrieve all descendants of the $root node
        $sql = "SELECT 
                    $select
                FROM 
                    "._DBPREFIX."_".$tableName." AS o  
                LEFT JOIN
                    "._DBPREFIX."__sys_classes_join AS cj 
                ON
                    cj.subject = o.id
                WHERE 
                    lft BETWEEN ? AND ? 
                ORDER BY lft ASC";


        // retrieve all descendants of the $root node
        $sql = "SELECT 
                    $select
                FROM 
                    "._DBPREFIX."_".$tableName." AS o  
                INNER JOIN
                    "._DBPREFIX."_appellation_join AS appel_j 
                ON
                    appel_j.subject = o.id

                INNER JOIN
                    "._DBPREFIX."_appellation AS appel 
                ON
                    appel.id = appel_j.property

                LEFT JOIN
                    "._DBPREFIX."__sys_classes_join AS cj 
                ON
                    cj.subject = o.id
                WHERE 
                    lft BETWEEN ? AND ? 
                ORDER BY lft ASC ";

// select one level ordered by name from preordered traversal table
        $sql = "SELECT 
                node.id, (COUNT(parent.id) - (sub_tree.depth + 1)) AS depth, name 
FROM 
ida__sys_placeorder AS node, 
ida__sys_placeorder AS parent,
ida__sys_placeorder AS sub_parent,
ida_appellation AS appel,
ida_appellation_join AS appel_j, 
(SELECT node.id, (COUNT(parent.id) - 1) AS depth
 FROM 
ida__sys_placeorder AS node,
ida__sys_placeorder AS parent
WHERE 
node.lft 
BETWEEN 
parent.lft 
AND 
parent.rgt 
AND 
node.id = ? 

GROUP BY 
node.id ORDER BY node.lft) AS sub_tree 
WHERE 
node.lft 
BETWEEN parent.lft 
AND parent.rgt 
AND node.lft 
BETWEEN sub_parent.lft 
AND sub_parent.rgt 
AND sub_parent.id = sub_tree.id 
AND 
appel_j.subject = node.id
AND
appel.id = appel_j.property
GROUP BY 
node.id HAVING depth = 1 ORDER BY name;
";
        $tree = IdaDb::prepareExecute($sql, array(0), $mode);
       // $tree = IdaDb::prepareExecute($sql, array($res[0]["lft"], $res[0]["rgt"]), $mode);
        //Debug::printArray($tree);
        echo "<pre>";
        print_r($tree);
        die();
        return $tree;

    }

    static function getRowById($table, $id) {
        $sql = "SELECT * FROM "._DBPREFIX."_".$table." WHERE id = $id";
        $result = IdaDB::query($sql, "onerow");
        return $result;

    }

    static function checkRowRecId($table, $id) {
        $sql = "SELECT subject FROM "._DBPREFIX."_".$table->tableName."_join WHERE property = $id";
        $result = IdaDB::query($sql, "onecell");
        return $result;

    }


/**
   * IdaDB::deleteBy
   * delete row 
*/
    static function deleteBy($table, $where) {
        
        $where_str = IdaDB::makeWhereFromArraysForPrep($where);
        $sql = "DELETE FROM "._DBPREFIX."_".$table." WHERE ".$where_str;
        IdaDB::prepareExecute($sql, $where);
        
    }

    static function deleteAll($table) {
        
        $sql = "DELETE FROM "._DBPREFIX."_".$table;
        IdaDB::exec($sql);
        
    }




    static function fetchMaxValue($table, $field) {
        $sql = "SELECT MAX($field) AS $field FROM "._DBPREFIX."_".$table;
        $res = IdaDB::query($sql);
        if($res->numRows()) {
            $row = $res->fetchRow();
            return $row[$field];
        }
    }



    static function getMaxValue($maxcol, $table, $selcol, $selval) {
        if($selcol != '') $where = "WHERE $selcol = '$selval'";
        $sql = "SELECT MAX($maxcol) as maxval from $table $where";
        $result = IdaDB::query($sql);
        $row = $result->fetchRow();
        return $row['maxval'];
    }

    static function countRows($table) {

        $sql = "SELECT COUNT(id) AS count FROM "._DBPREFIX."_".$table;
        $result = IdaDB::query($sql);
        $row = $result->fetchRow();
        return $row['count'];
    }
    


/*********************************************************************************************
/*********************************************************************************************
                                   Record spesific
**********************************************************************************************
*********************************************************************************************/

/**
    * get all instances of a class and its subclasses
    * get all instances
    * @return array
*/

    static function getAllInstances_new($class, $table) {


        $path = XML::nodeTree($class->classNameFull, array($class->classNameFull));
        foreach($path as $node) {
            $c = XML::getClassID($node);
            $arr[] = "'".$c["id"]."'";
            
        }
        $strTree = implode(",", $arr);    
       

        $sql = "INSERT INTO
                    "._DBPREFIX."_".$table."
                    (id)
                SELECT
                    cl_j.subject
                FROM
                    "._DBPREFIX."__sys_classes_join as cl_j
                WHERE
                    cl_j.property in ( $strTree )
                    ";

        IdaDB::exec($sql, array(), "onecol");

    }


    static function getAllInstances($class) {


        $path = XML::nodeTree($class->classNameFull, array($class->classNameFull));
        foreach($path as $node) {
            $c = XML::getClassID($node);
            $arr[] = "'".$c["id"]."'";
            
        }
        $strTree = implode(",", $arr);    
       

        $sql = "SELECT
                    cl_j.subject
                FROM
                    "._DBPREFIX."__sys_classes_join as cl_j
                WHERE
                    cl_j.property in ( $strTree )
                    ";

        $res = IdaDB::prepareExecute($sql, array(), "onecol");
        return $res;

    }




    static function selectByWordIndex(&$table, $wherePairs, $ids=0, $classId, $linkId, $inv=0) {

        $count = 0;
        $words = IdaDb::makeWordArray($wherePairs["name"]); // TODO: fixed column name
        $where = array();
        $vals = array();
        $recs = array();


        if(!count($words)) 
            return 0;

        foreach($words as $word) {
                
            //if($word[mb_strlen($word)-1] == "*") {
            if(mb_ereg_match(".*\*", $word)) {
                $where[] = " select w.id FROM  "._DBPREFIX."__sys_words as w,  "._DBPREFIX."__sys_words_join as j WHERE w.word like ? AND j.word_id = w.id AND j.table_id = $table->tableId";
                $vals[] = mb_ereg_replace('\*','',$word)."%";
            } else {
                $where[] = " select w.id FROM  "._DBPREFIX."__sys_words as w,  "._DBPREFIX."__sys_words_join as j WHERE w.word = ? AND j.word_id = w.id AND j.table_id = $table->tableId";
                $vals[] = $word;
            }
            $count++;
        }
        
        $whereStr = implode(" UNION ", $where);

        // check first if we found all words from index
        $r = IdaDB::prepareExecute($whereStr, $vals, "onecol");
        $wids = implode(",", $r);
        $wordCount = count($vals);
        $hitCount = $wordCount - 1; // number of hits needed

        if(count($r) >= count($vals)) {
            $sql = "SELECT 
                        record_id 
                    FROM 
                        ida__sys_words_join 
                    INNER JOIN 
                        ida__sys_words as w 
                        ON w.id = word_id 
                    INNER JOIN
                        ida__sys_classes_join as cj 
                        ON cj.subject = record_id 
                    WHERE  
                        word_id IN ( 
                            $wids
                        )
                    AND
                        cj.property in ($classId)

                    GROUP BY
                        record_id 
                    HAVING count(record_id)>".$hitCount;

                    if($ids) $sql .= "
                    AND record_id in (".$ids.")";

        $recs = IdaDB::prepareExecute($sql, array(), "onecol");

        }

        return $recs;

    }




    // occurs_during pseudo link
    static function selectJoinedTimeSpan($table, $wherePairs, $ids=0, $classId, $linkId, $inv=0) {

       if(array_key_exists("start_year", $wherePairs) && array_key_exists("end_year", $wherePairs))
            $where = " t.start_year BETWEEN  ".$wherePairs["start_year"]." AND ".$wherePairs["end_year"]." ";
        else if(array_key_exists("start_year", $wherePairs))
            $where = " t.start_year >  ".$wherePairs["start_year"]." ";
        else if(array_key_exists("end_year", $wherePairs))
            $where = " t.start_year <  ".$wherePairs["end_year"]." ";

        $sql = "SELECT
                    tj.subject
                FROM
                    "._DBPREFIX."_".$table."_join as tj, 
                    "._DBPREFIX."_$table as t, 
                    "._DBPREFIX."__sys_classes_join as typej
                WHERE
                $where
                AND
                    t.id = tj.property
                AND
                    tj.link_type = ?
                AND
                     typej.subject = tj.subject
                AND
                     typej.property in (".$classId.") ";

                if($ids) $sql .= "
                AND
                    tj.subject in (".$ids.")";

           //     $sql .= " ORDER by name ";
        return IdaDB::prepareExecute($sql, array($linkId), "onecol");

    }



    static function selectJoinedTimeSpan_new($table, $class, $linkId, $recurLevel, $inv=0) {
   
        $par = " ";

       if(array_key_exists("start_year", $table->data) && array_key_exists("end_year", $table->data))
            $where = " t.start_year BETWEEN  ".$table->data["start_year"]." AND ".$table->data["end_year"]." ";
        else if(array_key_exists("start_year", $table->data))
            $where = " t.start_year >  ".$table->data["start_year"]." ";
        else if(array_key_exists("end_year", $table->data))
            $where = " t.start_year <  ".$table->data["end_year"]." ";


        // add direction to link (always F)
        $linkId = $linkId."F";
        $parentTable = "root_".$recurLevel;
        $tempTable = "search_tmp";

        // get class trees
        $cl1 = XML::nodeTree($class->classNameFull, array($class->classId));
        foreach($cl1 as $cl){
            $ex = explode(".", $cl);
            $classes1[] = $ex[0];

        }

        $class1 = IdaDb::implodeArray($classes1);

        // if there are records in the parent table, then we must write
        // result to the temp table
        $count = IdaDb::countRows($parentTable);
        if($count) {
            $targetTable = $tempTable;
            $parent = true;
            Debug::printMsg("There are previous hits in parent table:
            ".$count."<br>");

        // otherwise we can put result directly to parent table
        } else {
            $targetTable = $parentTable;
            $parent = false;
        }

        if($parent) {
            $par = " INNER JOIN 
                    "._DBPREFIX."_".$parentTable." AS root1
                ON
                    tj.subject = root1.id ";
        }


        $where_str = IdaDB::makeWhereFromArrays($table->data, $inv);
        $sql = "INSERT INTO 
                     "._DBPREFIX."_".$targetTable." 
                     (id)
                SELECT
                    tj.subject
                FROM
                    "._DBPREFIX."_".$table->tableName."_join as tj 
                INNER JOIN
                    "._DBPREFIX."_".$table->tableName." AS t
                ON
                    t.id = tj.property
                INNER JOIN
                    "._DBPREFIX."__sys_classes_join AS cj 
                ON
                    cj.subject = tj.subject ";

        $sql .= $par;
        $sql .= "WHERE
                    $where
                AND
                    tj.link_type = ?
                AND
                     cj.property in (".$class1.") ";

        IdaDB::prepareExecute($sql, array($linkId), "onecol");

        // if we did not find anything, we throw error
        $resCount = IdaDb::countRows($targetTable);
        if($resCount == 0)
            throw new Exception("did not find anything");


        // update parent table
        if($parent) {
            // clear parent table
            $sql = "DELETE FROM "._DBPREFIX."_".$parentTable;
            IdaDb::exec($sql);
            
            // put result into parent table
            $sql = "INSERT INTO 
                    "._DBPREFIX."_".$parentTable."
                    SELECT id
                    FROM 
                    "._DBPREFIX."_".$targetTable."
                    ";
            IdaDb::exec($sql);

            // clear temp table
            $sql = "DELETE FROM "._DBPREFIX."_".$targetTable;
            IdaDb::exec($sql);
        }
    }




    static function selectJoined_new($table, $class, $linkId, $recurLevel, $inv=0) {
   
        $par = " ";

        // add direction to link (always F)
        $linkId = $linkId."F";
        $parentTable = "root_".$recurLevel;
        $tempTable = "search_tmp";

        // get class trees
        $cl1 = XML::nodeTree($class->classNameFull, array($class->classId));
        foreach($cl1 as $cl){
            $ex = explode(".", $cl);
            $classes1[] = $ex[0];

        }

        $class1 = IdaDb::implodeArray($classes1);

        // if there are records in the parent table, then we must write
        // result to the temp table
        $count = IdaDb::countRows($parentTable);
        if($count) {
            $targetTable = $tempTable;
            $parent = true;
            Debug::printMsg("There are previous hits in parent table:
            ".$count."<br>");

        // otherwise we can put result directly to parent table
        } else {
            $targetTable = $parentTable;
            $parent = false;
        }

        if($parent) {
            $par = " INNER JOIN 
                    "._DBPREFIX."_".$parentTable." AS root1
                ON
                    tj.subject = root1.id ";
        }


        $where_str = IdaDB::makeWhereFromArrays($table->data, $inv);
        $sql = "INSERT INTO 
                     "._DBPREFIX."_".$targetTable." 
                     (id)
                SELECT
                    tj.subject
                FROM
                    "._DBPREFIX."_".$table->tableName."_join as tj 
                INNER JOIN
                    "._DBPREFIX."_".$table->tableName." AS t
                ON
                    t.id = tj.property
                INNER JOIN
                    "._DBPREFIX."__sys_classes_join AS cj 
                ON
                    cj.subject = tj.subject ";

        $sql .= $par;
        $sql .= "WHERE
                    $where_str
                AND
                    tj.link_type = ?
                AND
                     cj.property in (".$class1.") ";

        IdaDB::prepareExecute($sql, array($linkId), "onecol");

        // if we did not find anything, we throw error
        $resCount = IdaDb::countRows($targetTable);
        if($resCount == 0)
            throw new Exception("did not find anything");


        // update parent table
        if($parent) {
            // clear parent table
            $sql = "DELETE FROM "._DBPREFIX."_".$parentTable;
            IdaDb::exec($sql);
            
            // put result into parent table
            $sql = "INSERT INTO 
                    "._DBPREFIX."_".$parentTable."
                    SELECT id
                    FROM 
                    "._DBPREFIX."_".$targetTable."
                    ";
            IdaDb::exec($sql);

            // clear temp table
            $sql = "DELETE FROM "._DBPREFIX."_".$targetTable;
            IdaDb::exec($sql);
        }
    }




    static function selectJoined($table, $wherePairs, $ids=0, $classId, $linkId, $inv=0) {

        $where_str = IdaDB::makeWhereFromArrays($wherePairs, $inv);
        $sql = "SELECT
                    tj.subject
                FROM
                    "._DBPREFIX."_".$table."_join as tj, "._DBPREFIX."_$table as t, "._DBPREFIX."__sys_classes_join as typej
                WHERE
                    $where_str
                AND
                    t.id = tj.property
                AND
                    tj.link_type = ?
                AND
                     typej.subject = tj.subject
                AND
                     typej.property in (".$classId.") ";

                if($ids) $sql .= "
                AND
                    tj.subject in (".$ids.")";


        return IdaDB::prepareExecute($sql, array($linkId), "onecol");

    }

    // select certain classes that are linked to certain classes via certain link
    // for example : Building->was_producd_by->Production
    static function allJoinsClass_new ($subject,  $linkName, $target, $recurLevel, $getAll=false) {

        Debug::printDiv($target->className."::".__FUNCTION__);

        $par = "";
        $ids = "";
        $parent = false;
        $own = false;

        $ownLevel = $recurLevel + 1;
        $parentTable = "root_".$recurLevel;
        $secondTable = "root_".$ownLevel;
        $tempTable = "search_tmp";

        // if there are previous records, then we must compare
        // our search to those
        $parentCount = IdaDb::countRows($parentTable);
        $ownCount = IdaDb::countRows($secondTable);

        if($parentCount && $ownCount) {
            $targetTable = $tempTable;
            $parent = true;
            $own = true;
            Debug::printMsg("There are previous hits in both parent table:".$parentCount." and own table:".$ownCount."<br>");
        } else if($parentCount) {
            $targetTable = $secondTable;
            $parent = true;
            Debug::printMsg("There are previous hits in parent table:".$parentCount."<br>");
        } else if($ownCount) {
            $targetTable = $parentTable;
            $own = true;
            Debug::printMsg("There are previous hits in own table:".$ownCount."<br>");
        } else {
            $targetTable = $parentTable;
            Debug::printMsg("There are NO previous hits <br>");
        }

        $link = XML::getPropertyID($linkName);

        
        // get class trees
        $cl1 = XML::nodeTree($subject->classNameFull, array($subject->classId));
        foreach($cl1 as $class){
            $ex = explode(".", $class);
            $classes1[] = $ex[0];

        }

        $cl2 = XML::nodeTree($target->classNameFull, array($target->classId));
        foreach($cl2 as $class){
            $ex = explode(".", $class);
            $classes2[] = $ex[0];

        }

        if(!is_array($classes1) || !is_array($classes2))
            throw new Exception(__FUNCTION__.": classes array missing!");

        $class1 = IdaDb::implodeArray($classes1);
        $class2 = IdaDb::implodeArray($classes2);

        // get link direction
        $dir = $link["dir"];
        $p = $link["id"];

        // check link direction
        if($dir == 'B') {
            $sel = "rj.property";
            $targ = "rj.subject";
        } else {
            $sel = "rj.subject";
            $targ = "rj.property";

        }

        if($parent) {
            $par = " INNER JOIN 
                    "._DBPREFIX."_".$parentTable." AS root1
                ON
                    $sel = root1.id 
                    ";
        }


        if($own) {
            $ids = " INNER JOIN 
                    "._DBPREFIX."_".$secondTable." AS root2
                ON
                    $targ = root2.id 
                    ";
        }

        $sql = "INSERT INTO
                "._DBPREFIX."_".$targetTable."
                (id)
            SELECT DISTINCT
                $sel 
            FROM
                "._DBPREFIX."__sys_records_join AS rj 
            INNER JOIN 
                "._DBPREFIX."__sys_classes_join As cj 
            ON 
                $sel = cj.subject 
            INNER JOIN 
                "._DBPREFIX."__sys_classes_join AS cj2 
            ON 
                $targ = cj2.subject ";
    // joins in parent and own table
    $sql .= $par; 
    $sql .= $ids; 
                
    $sql .= "AND 
                cj.property IN ($class1) 
            AND 
                rj.link_type = '$p' 
            AND 
                cj2.property IN ($class2) 
            "
                ;

            IdaDb::exec($sql);
    

        // if we did not find anything, we throw error
        $resCount = IdaDb::countRows($targetTable);
        if($resCount == 0)
            throw new Exception("did not find anything");

            // if there were findings in bot tables,
            // then update parent table from temp table
            if($parent && $own) {
                // clear parent table
                $sql = "DELETE FROM "._DBPREFIX."_".$parentTable;
                IdaDb::exec($sql);
                
                // put result into parent table
                $sql = "INSERT INTO 
                        "._DBPREFIX."_".$parentTable."
                        SELECT id
                        FROM 
                        "._DBPREFIX."_".$tempTable."
                        ";
                IdaDb::exec($sql);
            } else if($parent) {
                // clear parent table
                $sql = "DELETE FROM "._DBPREFIX."_".$parentTable;
                IdaDb::exec($sql);
                
                // put result into parent table
                $sql = "INSERT INTO 
                        "._DBPREFIX."_".$parentTable."
                        SELECT id
                        FROM 
                        "._DBPREFIX."_".$secondTable."
                        ";
                IdaDb::exec($sql);
            }
}



    // select certain classes that are linked to certain classes via certain link
    // for example : Building->was_producd_by->Production
    static function allJoinsClass ($classes1,  $link, $classes2) {

        if(!is_array($classes1) || !is_array($classes2))
            throw new Exception(__FUNCTION__.": classes array missing!");

        $class1 = IdaDb::implodeArray($classes1);
        $class2 = IdaDb::implodeArray($classes2);

        //$cpath = XML::pathToClass($target->classNameFull, array($target->classNameFull));

        // if order field is not set, then order by appellation or time-span
/*
        if(!$orderField) {
            if(in_array(_EVENT_CLASSNAME, $cpath)) {
                $table = "time_span";
                $field = "a.start_year,a.start_month,a.start_day";
            } else {
                $table = "appellation";
                $field = "a.name";
            }
        }
*/

//TODO: make more generic order by (using only appellation
        $table = "appellation";
        $field = "a.name";


        // get link direction
        $dir = $link["dir"];
        $p = $link["id"];

        // check link direction
        if($dir == 'B') {
            $sel = "rj.property";
            $targ = "rj.subject";
        } else {
            $sel = "rj.subject";
            $targ = "rj.property";

        }

        $orderSql = " LEFT JOIN "._DBPREFIX."_".$table."_join AS aj ON $sel = aj.subject LEFT JOIN "._DBPREFIX."_".$table." AS a ON a.id = aj.property ORDER BY ".$field;


    $sql = "SELECT DISTINCT
                $sel 
            FROM
                "._DBPREFIX."__sys_records_join AS rj 
            INNER JOIN 
                "._DBPREFIX."__sys_classes_join As cj 
            ON 
                $sel = cj.subject 
            INNER JOIN 
                "._DBPREFIX."__sys_classes_join AS cj2 
            ON 
                $targ = cj2.subject 
            AND 
                cj.property IN ($class1) 
            AND 
                rj.link_type = '$p' 
            AND 
                cj2.property IN ($class2) 
            "
                ;

    return IdaDB::prepareExecute($sql, array(), "onecol");
}
/**
    * check if instances are linked to the current record(s) (both directions)
    * if $props is empty, then returns all joins
    * @return array
*/
    static function IsJoin($subs, $props, $link) {

        // get link direction
        $dir = $link[mb_strlen($link)-1];
        $p = mb_substr($link,0,(mb_strlen($link)-1));


        // check link direction
        if($dir == 'B') {
            $subjects = IdaDB::implodeArray($subs, "integer");
            $propertys = IdaDB::implodeArray($props, "integer");
            $sel = "property";
        } else {
            $subjects = IdaDB::implodeArray($props, "integer");
            $propertys = IdaDB::implodeArray($subs, "integer");
            $sel = "subject";

        }

        $sql = "SELECT
                   $sel 
                FROM
                    "._DBPREFIX."__sys_records_join
                WHERE
                    subject in ( $subjects )
                AND
                    property in ( $propertys )
                AND
                    link_type = ?
                ";

        Debug::printMsg($sql);
        Debug::printMsg($p);
        $res = IdaDB::prepareExecute($sql, array($p), "onecol");

        return $res;
    }

    static function implodeArray($arr, $type="text") {

        $new = array();
        $mdb2 =& MDB2::singleton(_DSN);

        if (PEAR::isError($mdb2)) {
            throw new Exception($mdb2->getMessage().$mdb2->getUserInfo());
        }

        foreach($arr as $a) {
            $new[] = $mdb2->quote($a);
        }

        return implode(',', $new);

    }

    static function quote($value) {

        $mdb2 =& MDB2::singleton(_DSN);

        if (PEAR::isError($mdb2)) {
            throw new Exception($mdb2->getMessage().$mdb2->getUserInfo());
        }

        return $mdb2->quote($value);

    }



//TODO: change query from NOT IN to something else
    static function fetchUploadedFiles() {
            $sql = "SELECT
                     *
                    FROM
                        "._DBPREFIX."_file
                    WHERE NOT EXISTS (
                        SELECT *
                        FROM
                        "._DBPREFIX."_file_appellation
                        WHERE 
                        filename = fname)
                    ";

            return IdaDB::prepareExecute($sql, array());
    }



    static function fetchUnlinkedFiles() {
            $sql = "SELECT
                                    f.id, f.filename, f.minithumb_width
                            FROM
                                    "._DBPREFIX."_file_join as fj,
                                    "._DBPREFIX."_file as f
                            WHERE
                                fj.property = f.id
                            GROUP BY
                                property, f.id, f.filename, f.minithumb_width
                            HAVING
                                    COUNT(property) = 1
                                    ";

         
            return IdaDB::prepareExecute($sql, array());
    }


    static function getLinkboardContent() {
        $sql = "SELECT
                    fileid, filename
                FROM
                    "._DBPREFIX."__sys_linkboard,
                    "._DBPREFIX."_file as f
                WHERE
                    fileid = f.id";

       return IdaDB::prepareExecute($sql, array());
    }




    static function getTableInfo() {
        
        $sql = "SELECT 
                col.id as title,
                col.nid as col_id,
                tbl.id as tbl_id,
                tbl.title as tablename,
                col.required, 
                col.width, 
                col.display, 
                col.col_type as type, 
                col.prefix, 
                col.options

                
            FROM
                "._DBPREFIX."__sys_columns AS col 
            LEFT JOIN
                "._DBPREFIX."__sys_tables as tbl
                ON
                col.table_id = tbl.title
            ORDER BY 
                col.display_order";

        $result = IdaDB::query($sql,"all");
        return $result;
    }
    

     
    static function getTableNames() {
        $sql = "SELECT id,title, shortcut
            FROM "._DBPREFIX."__sys_tables 
            ORDER BY display_order
    
                
            ";
        $result = IdaDB::query($sql,"all",1);
        return $result;
    }


   static function getLinkedRecordsByLink($get, $linkGroup, $sourceTable,$targetTable, $orderTable=0, $orderFields=0) {

    $ordj_property = "";
    $ord_lines = "";
    $order_by = "";
    $order = "";

    if(!count($linkGroup))
       return array();

   // get objects where I am subject
   // get objects where I am property

    $links = IdaDb::implodeArray($linkGroup);

    if($get == "subject") {
        $dir = 'B';
        $active_instance = "property";
        $target_instance = $get;
    } else if($get == "property") {
        $dir = 'F';
        $active_instance = "subject";
        $target_instance = $get;
    }
    // order by table if it table set
    if($orderTable) {
        $ordj_property = " AND recj.".$target_instance." = ordj.subject AND ordj.property = ord.id ";
        $ord_lines = ", "._DBPREFIX."_".$orderTable." as ord, "._DBPREFIX."_".$orderTable."_join as ordj ";
        $orderBy = " ORDER BY ".implode(",", $orderFields);
        $order = "
                LEFT JOIN 
                    "._DBPREFIX."_".$orderTable."_join AS ordj 
                ON 
                    ordj.subject = recj.".$target_instance." 
                LEFT JOIN 
                    "._DBPREFIX."_".$orderTable." AS ord 
                ON 
                    ord.id = ordj.property 
                $orderBy

                ";
    }

        $sql = "INSERT INTO "._DBPREFIX."_".$targetTable." SELECT
                    recj.".$active_instance." as subject,
                    recj.".$get." as id,
                    '".$dir."' as link_dir,
                    recj.link_type,
                    classj.property as class_id
                 FROM
                    "._DBPREFIX."__sys_records_join as recj
                INNER JOIN
                    "._DBPREFIX."_".$sourceTable."
                ON
                    "._DBPREFIX."_".$sourceTable.".subject = recj.".$active_instance."  
                INNER JOIN 
                    "._DBPREFIX."__sys_classes_join AS classj 
                ON 
                    classj.subject = recj.".$target_instance." 
                    AND 
                    recj.link_type in (".$links.") 
       ".$order." 
                    
                ";

         Debug::printMsg("<p>".$sql."</p>");
        return IdaDB::prepareExecute($sql, array());

     }


   static function getLinkedByGroup2($get, $domains, $linkGroup, $orderTable='', $orderFields=0, $not_in=0, $mode="default") {

    $ordj_property = "";
    $ord_lines = "";
    $order_by = "";

    if(!count($domains) || !count($linkGroup))
       return array();

   // get objects where I am subject
   // get objects where I am property

    $id = implode(",", $domains);
    $links = IdaDb::implodeArray($linkGroup);

    if(is_array($not_in)) {
       $str = implode(',',$not_in);
       $prop_not_in = " AND recj.property NOT IN($str)";
       $subj_not_in = " AND recj.subject NOT IN($str)";
    }

    if($get == "subject") {
        $dir = 'B';
        $active_instance = "property";
        $target_instance = $get;
    } else if($get == "property") {
        $dir = 'F';
        $active_instance = "subject";
        $target_instance = $get;
    }

    // order by table if it table set
    if($orderTable != "") {
        $ordj_property = " AND recj.".$target_instance." = ordj.subject AND ordj.property = ord.id ";
        $ord_lines = ", "._DBPREFIX."_".$orderTable." as ord, "._DBPREFIX."_".$orderTable."_join as ordj ";
        $orderBy = " ORDER BY ".implode(",", $orderFields);
        $order = "
                LEFT JOIN 
                    "._DBPREFIX."_".$orderTable."_join AS ordj 
                ON 
                    ordj.subject = recj.".$target_instance." 
                LEFT JOIN 
                    "._DBPREFIX."_".$orderTable." AS ord 
                ON 
                    ord.id = ordj.property 
                $orderBy

                ";
               
    }



        $objects = array();

        $sql = "SELECT
                    recj.".$active_instance." as subject,
                    recj.".$get." as id,
                    '".$dir."' as link_dir,
                    recj.link_type,
                    classj.property as class_id,
                    classj.has_type as has_type
                 FROM
                    "._DBPREFIX."__sys_records_join as recj
                INNER JOIN 
                    "._DBPREFIX."__sys_classes_join AS classj 
                ON 
                    classj.subject = recj.".$target_instance." 
                    AND 
                    recj.link_type in (".$links.") 
                    AND 
                    recj.".$active_instance." in (".$id.") 
                    
                    $prop_not_in
        
                $order
                    
                ";

       //  Debug::printMsg("<p>".$sql."</p>");

        return IdaDB::prepareExecute($sql, array(),$mode);

     }



/**
    * IdaDB::getAllLinkedRecords
    * search records that are linked to record $id
    * @param id record id
    * @param not_in allready found records (prevents infinite recursion in recursive call)
    * @return array
*/

    static function getAllLinkedRecords($idArray, $not_in=0) {

    if(!count($idArray))
       return array();

       // get objects where I am subject
       // get objects where I am property
    $id = implode(",", $idArray);
   // $events = Array(5,7,8,9,10,11,12,13,14,15,16,17,63,64,65,66,67,68,69,79,80,81,83);

    if(is_array($not_in)) {
       $str = implode(',',$not_in);
       $prop_not_in = " AND oj.property NOT IN($str)";
       $subj_not_in = " AND oj.subject NOT IN($str)";
    }

        $objects = array();

        $sql = "SELECT
                    o.id,
                    oj.link_type,
                    oj.subject as subject,
                    oj.property as prop,
                    tj.property as class_id,
                    tj.has_type as has_type
                FROM
                    "._DBPREFIX."__sys_records as o,
                    "._DBPREFIX."__sys_records_join as oj,
                    "._DBPREFIX."__sys_classes_join as tj
                WHERE
                        tj.subject = o.id
                    AND
                        oj.property = o.id
                    AND
                        oj.subject in (".$id.")
                        $prop_not_in

                    OR (
                        tj.subject = o.id
                    AND
                        oj.subject = o.id
                    AND
                        oj.property in (".$id.")
                        $subj_not_in
                    )

                    ";

         Debug::printMsg($sql);

        $res = IdaDB::prepareExecute($sql, array());

        foreach($res as $row) {

            if(in_array($row["subject"], $idArray))
                $objects[$row["subject"]][] = $row;
            else
                $objects[$row["prop"]][] = $row;
        }


        return $objects;
     }





// *****************************************
//          PRIVATE functions
// *****************************************

    private function makeWhereFromArrays($pairs, $inv=0) {

        $mdb2 =& MDB2::singleton(_DSN);
        if (PEAR::isError($mdb2)) {
            throw new Exception($mdb2->getMessage().$mdb2->getUserInfo());
        }
        



        if(is_array($pairs)){

            $where_str = ' ';
            foreach($pairs as $key=>$val) {


                // check for wildcards
                if(strstr($val, "*")) {
                    if($inv)
                        $operator = " NOT like ";
                    else
                        $operator = " LIKE ";

                    $val = str_replace("*", "%", $val);
                } else
                    if($inv)
                        $operator = "!=";
                    else
                        $operator = "=";

              // check for * ( = is not null)
                if($val == "%") {
                    $val = null;
                    if($inv)
                        $operator = " is ";
                    else
                        $operator = " is NOT ";
                }

               $clean = $mdb2->quote($val);

                $where[] = $key.$operator.$clean;
            }
            $where_str .= implode(' AND ',$where);

        } else {

            $where_str = '';

        }
        return $where_str;
    }


    private function makeWhereFromArraysForPrep($pairs) {

        $mdb2 =& MDB2::singleton(_DSN);
        if (PEAR::isError($mdb2)) {
            throw new Exception($mdb2->getMessage().$mdb2->getUserInfo());
        }

        if(is_array($pairs)){

            $where_str = ' ';
            foreach($pairs as $key=>$val) {
                $clean = $mdb2->quote($val);
                $where[] = $key."= :".$key;
            }
            $where_str .= implode(' AND ',$where);

        } else {

            throw new Exception("Must be array!");

        }
        return $where_str;
    }


/*********************************************************************************************
                                    WORD INDEXING FUNCTIONS
**********************************************************************************************/

     

    static function indexWords($recId, $table) {

        try {

            $jData = IdaDb::insertWords2Temp($table, $recId);
            Debug::printArray($table->data, "table->data:");

            foreach($table->columns as $key=>$val) {
                $jData["column_id"] = $val["col_id"];
               
                Debug::printArray($jData, "jdata");
                IdaDB::linkExistingWords($jData, $recId);
                IdaDb::cleanUnchangedWords($jData);
                IdaDB::addNewWords($jData);
            }

            $sql = "DELETE from ida_index";
            IdaDb::exec($sql);
            
        } catch (Exception $e) {
            throw new Exception("Error in word indexing! (".$e->getMessage().")");
        }

    }




    static function updateIndex($recId, $table) {
/*
        // create a temporary table for words and fill it with input
        $jData = IdaDb::createTempIndex($table, $recId);

        // loop per column
        foreach($table->data as $key=>$val) {

            $jData["column_id"] = $table->columns[$key]["col_id"];

            // unlink words that are not present any more in column value
            IdaDb::removeFromWordIndex($jData);

            // remove unchanged words from temp table
            IdaDb::cleanUnchangedWords($jData);

            // make links to the words already in index
            IdaDb::linkExistingWords($jData);

            //  new words are what is left
            IdaDB::addNewWords($jData);

        }
        */
    }






    static function createTempTable() {
 
                   
        // create temporary table for words
        $sql = "CREATE TEMPORARY TABLE 
                    ida_index 
                    (word char(".TEMP_TABLE_CHAR_LENGTH."),
                    col_id integer)";

        IdaDB::exec($sql);
    }


    static function dropTempTable() {

        $sql = "DROP TABLE ida_index";
        IdaDb::exec($sql);
            

    }

    static function insertWords2Temp($table, $recId) {

         Debug::printMsg("<h3>Indexing 
                        $recId with 
                        table $table->tableName</h3>");
        
        $words = array();
        $jData = array(
                    "record_id" => $recId,
                    "table_id" => $table->tableId,
                    "row_id" => $table->rowId,
                    );
               
        
 
        foreach($table->data as $key=>$val) {

            $inserts = array();
            $colId = $table->columns[$key]["col_id"];
            $words = IdaDB::makeWordArray($val);
            Debug::printArray($words, "words:");
            // insert words to temporary table
            foreach($words as $word) {
                $inserts[] = array($word,$colId);
            }
            Debug::printArray($inserts, "words (word, col_id):");
            
            if(count($inserts)) {
                $prep = IdaDB::prepare("INSERT INTO ida_index VALUES (?,?)");
                foreach ($inserts as $row) {
                    IdaDB::execute($prep, $row, "insert");
                }  
                $prep->free();
            }
        }  

        return $jData;
    }


    static function removeWordIndexes ($recordId, $tableId, $rowId) {
    
        // select word ids from join table
        $where = array("record_id"=>$recordId, "table_id"=>$tableId, "row_id"=>$rowId);
        $words = IdaDb::select("_sys_words_join", $where, array("id", "word_id"));
        
        foreach($words as $rid) {
            IdaDB::deleteBy("_sys_words", array("id"=>$rid["word_id"]));
            IdaDB::deleteBy("_sys_words_join", array("id"=>$rid["id"]));
        }
        
    }



    private function linkExistingWords($data, $recId) {

Debug::printMsg("link existing words<br>");

        // link words found but not linked to this record/column
        $sql = "
            SELECT 
                    w.id as word_id, w.word
                FROM 
                    "._DBPREFIX."__sys_words AS w
                INNER JOIN
                    ida_index as i
                ON 
                    w.word = i.word
                    AND
                    i.col_id = ".$data['column_id']."
                WHERE NOT EXISTS (
                    SELECT wj.id 
                    FROM "._DBPREFIX."__sys_words_join AS wj
                    WHERE
                    wj.record_id = ".$data['record_id']." 
                    AND
                    wj.table_id = ".$data['table_id']."
                    AND
                    wj.column_id = ".$data['column_id']."
                    AND
                    wj.row_id = ".$data['row_id']."
                    AND
                    wj.word_id = w.id  )
                      
                ";

Debug::printMsg($sql);

        $res = IdaDb::prepareExecute($sql, array());

        Debug::printArray($res, "existing words that are not linked:");


        // link words
        foreach($res as $link) {
   
            $values = array($link["word"], $data['column_id']);
   
            $sql = "DELETE from ida_index 
                    WHERE word = ?
                    AND col_id = ?";

            IdaDb::prepareExecute($sql, $values, "delete");      
            //IdaDb::exec($sql);
  
            $data["word_id"] = $link["word_id"];
            IdaDb::insert("_sys_words_join", $data);
  
        }

    }



    private function addNewWords($data) {


        $sql = "SELECT 
                    word 
            FROM 
                ida_index 
            WHERE
                col_id = ".$data['column_id']
            ;  
               
        $res = IdaDb::prepareExecute($sql, array());

        Debug::printArray($res, "adding following new words:");

        foreach($res as $word) {
            $word["id"] = IdaDb::nextId(_DBPREFIX."__sys_words");
            IdaDb::insert("_sys_words", $word, NO_ID);
            $data["word_id"] = $word["id"];
            IdaDb::insert("_sys_words_join", $data);
        }

    }



    static function cleanUnchangedWords($data) {

        // lets find words that hasn't changed and thus can be removed 
        // from temporary word table
        
        // Must be done with two queries since mysql has problems with temp tables:
        // http://bugs.mysql.com/bug.php?id=10327


        $sql = "

                    SELECT 
                        w.word 
                    FROM 
                        "._DBPREFIX."__sys_words AS w
                    INNER JOIN
                        "._DBPREFIX."__sys_words_join AS wj
                    ON
                        w.id = wj.word_id
                    INNER JOIN
                        ida_index as i
                    ON 
                        w.word = i.word
                    WHERE
                        record_id = ".$data['record_id']." 
                        AND
                        table_id = ".$data['table_id']." 
                        AND
                        row_id = ".$data['row_id']." 
                        AND
                        column_id = ".$data['column_id']." 
                
                    ";        

        $res = IdaDb::prepareExecute($sql, array(),"onecol"); 
        if(count($res)) {
            $wordList = IdaDb::implodeArray($res);
            $sql = "DELETE FROM ida_index WHERE word in ($wordList)"; 
        
            IdaDb::prepareExecute($sql, array()); 
        }
        
    }

    static function removeFromWordIndex($data) {


        Debug::printArray($data, "remove from word index:");

        // create temporary table for words
        $sql = "CREATE TEMPORARY TABLE 
                    temppi 
                    (id integer, word_id integer)";
                    
        IdaDb::exec($sql);

        $sql = "
        
                INSERT INTO   
                    temppi
                SELECT 
                    wj.id, wj.word_id
                FROM
                    "._DBPREFIX."__sys_words as w
                INNER JOIN
                    "._DBPREFIX."__sys_words_join AS wj
                ON
                    w.id = wj.word_id  
                    AND
                    record_id = ".$data['record_id']." 
                    AND
                    table_id = ".$data['table_id']." 
                    AND
                    row_id = ".$data['row_id']." 
                    AND
                    column_id = ".$data['column_id']." 
                       
                    
                WHERE NOT EXISTS (
                    SELECT
                        w.id 
                    FROM
                        ida_index as i
                    WHERE
                        w.word = i.word
                    AND
                        wj.column_id = ".$data['column_id']."
                    AND
                        wj.row_id = ".$data['row_id']."
                    AND
                        wj.table_id = ".$data['table_id'].")
                   ";

        IdaDb::exec($sql);


        // remove join between object and word


        $sql = "DELETE FROM 
                    "._DBPREFIX."__sys_words_join 
                WHERE id IN (
                    SELECT 
                        id
                    FROM
                        temppi
                    )";

        IdaDb::exec($sql);
        
        $sql = "DROP TABLE temppi";
        IdaDb::exec($sql);
        
        
        // TODO: TEE LOPPUUUNN!!!!!!! PITÄÄ POISTAA SANAT JOIHIN EI ENÄÄ VIITATA
        
        
        /*
        if($joinId) IdaDB::deleteBy('_sys_words_join', array("id"=>$joinId));

        // if there are no joins left, delete word from word index
        $res = IdaDB::select('_sys_words_join', array('word_id'=>$wordId),'id');

        if(!count($res)) {
            IdaDB::deleteBy('_sys_words', array("id"=>$wordId));
        }*/
    }


    private function makeWordArray($lines) {

        $rawWords = array();
        $words = array();
        $remove = array('(',')',',','.',':','[',']', '{','}', '"', '\'');
        $lines = strip_tags($lines);
        $lines = str_replace('&nbsp;',' ',$lines); // remove html spaces
        $lines = html_entity_decode($lines);
        $lines = str_replace($remove,' ',$lines);
        $rawWords = explode(' ',$lines);
        //$rawWords = mb_split(" ", $lines);

        foreach($rawWords as &$word) {
            $word = trim($word);
            $word = mb_strtolower($word, "UTF-8");
            //$word = IdaDB::quote($word);
            if($word != '' && (mb_strlen($word, "UTF-8") > _MIN_INDEXWORD_LENGTH)) {
                // cut long words
                if(mb_strlen($word, "UTF-8") > _MAX_INDEXWORD_LENGTH) {
                    $word = mb_substr($word, 0, _MAX_INDEXWORD_LENGTH -1,
                    "UTF-8");
                }
                $words[] = $word;
            }

        }

        return array_unique($words);
    }

    static function dropIndex () {

        $sql = "DELETE FROM "._DBPREFIX."__sys_words_join";

        IdaDb::exec($sql);
 
        $sql = "DELETE FROM "._DBPREFIX."__sys_words";

        IdaDb::exec($sql);

   }

    static function putTmpTable($tableName, $set, $orderTable, $orderBy) {

        $count = 1;
        // empty set, go back
        if(count($set) == 0)
            return;

        foreach($set as $id) {
            $inserts[] =  "(".$count.",".$id.")";
            $count++;
        }


        // create temporary table for words
        $sql = "CREATE TEMPORARY TABLE 
                    just_tmp 
                    (id integer,
                    subject integer,
                    INDEX(id))";

        IdaDB::exec($sql);

        $sql = "INSERT INTO just_tmp (id, subject) VALUES ";
            $sql .= implode(",", $inserts);
        
        IdaDb::exec($sql);

        // insert classes
        $sql = "INSERT INTO "._DBPREFIX."_".$tableName." (id, subject,class_id) SELECT t.subject, t.subject, cj.property FROM just_tmp AS t, ida__sys_classes_join AS cj LEFT JOIN "._DBPREFIX."_".$orderTable."_join AS aj ON aj.subject = cj.subject LEFT JOIN "._DBPREFIX."_".$orderTable." AS a ON a.id = aj.property WHERE cj.subject = t.subject  ORDER BY $orderBy";


        IdaDb::exec($sql);
    }

    static function putTmpTable_new($tableName, $orderTable, $orderBy) {

        // insert classes
        $sql = "INSERT INTO "._DBPREFIX."_".$tableName." (id, subject,class_id) SELECT t.id, t.id, cj.property FROM ida_root_0 AS t, ida__sys_classes_join AS cj LEFT JOIN "._DBPREFIX."_".$orderTable."_join AS aj ON aj.subject = cj.subject LEFT JOIN "._DBPREFIX."_".$orderTable." AS a ON a.id = aj.property WHERE cj.subject = t.id  ORDER BY $orderBy";


        IdaDb::exec($sql);
    }




    static function createTmpTables($rootClass) {

            // create temporary table for records
            $sql = "CREATE TEMPORARY TABLE 
                        "._DBPREFIX."_level1
                        (id integer,
                        subject integer,
                        link_dir varchar(1),
                        link_type varchar(6),
                        class_id varchar(6),
                        INDEX(subject))";

            IdaDB::exec($sql);

            // create temporary table for records
            $sql = "CREATE TEMPORARY TABLE 
                        "._DBPREFIX."_level2
                        (id integer,
                        subject integer,
                        link_dir varchar(1),
                        link_type varchar(6),
                        class_id varchar(6),
                        INDEX(subject))";

            IdaDB::exec($sql);


            // create temporary table for records
            $sql = "CREATE TEMPORARY TABLE 
                        "._DBPREFIX."_level3
                        (id integer,
                        subject integer,
                        link_dir varchar(1),
                        link_type varchar(6),
                        class_id varchar(6),
                        INDEX(subject))";

            IdaDB::exec($sql);


            // create temporary table for records
            $sql = "CREATE TEMPORARY TABLE 
                        "._DBPREFIX."_level4
                        (id integer,
                        subject integer,
                        link_dir varchar(1),
                        link_type varchar(6),
                        class_id varchar(6),
                        INDEX(subject))";

            IdaDB::exec($sql);

            // create temporary table for records
            $sql = "CREATE TEMPORARY TABLE 
                        "._DBPREFIX."_level5
                        (id integer,
                        subject integer,
                        link_dir varchar(1),
                        link_type varchar(6),
                        class_id varchar(6),
                        INDEX(subject))";

            IdaDB::exec($sql);


            // if rootClass is event, then sort first level records by date
            $eventTree = XML::nodeTree("E5.Event", array());
            if(in_array($rootClass, $eventTree)) {
                IdaDB::putTmpTable_new("level1", "time_span", "start_year, start_month, start_day");
            } else {
                IdaDB::putTmpTable_new("level1", "appellation", "name");
            }
    }

static function loadLevelData($result) {

            $levelData = array(array(),array(),array(),array(),array());
            IdaRecord::mene($result->item(0), 0, $levelData);

            // load data
            $tables = array();
            foreach($levelData as $key=>$data) {
                foreach($data as $key2=>$k) {
                    $tableId = IdaDB::select("_sys_columns",array("id"=>$k['col']),array("table_id"),"","onecell");
                    
                    $table = new IdaTable($tableId);
                    $levelData[$key][$key2]["data"] = $table;
                    $ll = $key+1;
                    $table->loadDataByTempTable("level".$ll);
                }
            }

        return $levelData;


    }
}







?>
