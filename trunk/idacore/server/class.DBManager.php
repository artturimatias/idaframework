<?php
/**
 * class.DBManager.php
 * @package ida
 */

define("DEBUG", 0);

/**
    * Class for database init and table creation from XML
    * @package ida
*/
class DbManager {

     private static function init() {
    
        $dsn =_DATABASE_TYPE.'://'._DBUSER.':'._DBPASS.'@localhost/'._DBNAME;

        $mdb2 =& MDB2::connect($dsn);
        if (PEAR::isError($mdb2)) {
        
            throw new Exception($mdb2->getMessage());
        }
        
        // loading the Manager module
        $mdb2->loadModule('Manager');
        
        return $mdb2;

    }

    public static function initDb() {

        $mdb2 = DBManager::init();
        
        $def = DBManager::getDefaults();

        $tables = DBManager::getTables();

        // return if we allready have tables
        if(count($tables))
            throw new Exception("Database is not empty! Cannot init.");

        // create system tables
        $systables = DBManager::readTableXML("_system/tables_system.xml");
        DBManager::createTables($systables, $def["for_sys_all"], $mdb2);
   

        
    }

    public static function getTables() {

        $mdb2 = DBManager::init();
        return $mdb2->listTables();
    }

    public static function addTables($path) {

        $newTables = array();
        $mdb2 = DBManager::init();

        // loading the Manager module
        $mdb2->loadModule('Manager');
        
        $def = DBManager::getDefaults();

        // create data tables
        $datatables = DBManager::readTableXML($path."/tables.xml");

        // make sure that we add only new tables
        $tables = $mdb2->listTables();
        foreach($datatables as $table) {
            if(!in_array(_DBPREFIX.'_'.$table['tablename'], $tables))
                $newTables[] = $table;
        }
        
        
        DBManager::createTables($newTables, $def["for_data_all"], $mdb2, $def["defaultConstraints"]);
        DBManager::createJoinTables($newTables, $def["join_table"], $mdb2);
        DBManager::writeTableInfo($newTables, $mdb2);

        
        if(!count($newTables))
            throw new Exception("Tables from $path are allready inserted!");
        else
            echo "<h2>Tables inserted</h2>";

    }

    public static function addMainUser($user, $passwd) {
        $mdb2 = DBManager::init();
        $hashed = sha1($passwd);
        $max = IdaDB::fetchMaxValue("_sys_users", "uid");
        if($max != 0) return;

        // TODO person id is hard coded!!!
        $insert = array("person_id"=>1, "username"=>$user, "passwd"=>$hashed, "status"=>"admin", "lang"=>"fi");
        IdaDB::insert("_sys_users", $insert, 1);
    }

    private static function createTables($table_array, $for_all, $mdb2, $defaultConstraints = 0) {

        echo "<ul>";
        
  
        foreach($table_array as $table) {
            $tblname = _DBPREFIX.'_'.$table['tablename'];
            echo '<li>creating table '.$tblname.'</li>';

            $table_create = array_merge($table['table'], $for_all);
            
            $res = $mdb2->createTable($tblname, $table_create);
            if(PEAR::isError($res)) {
                throw new Exception("Creation of table $tblname failed! ".$res->getCode().$res->getUserInfo());
            }
            
            $mdb2->createSequence($tblname);

            // default constraints
            
            if($defaultConstraints){
                if (DEBUG) echo '<li>creating default constraints ';
                foreach($defaultConstraints as $key=>$val) {
                    $res = $mdb2->createConstraint($tblname, $tblname.'_id', $val);
                    if(PEAR::isError($res)) {
                        throw new Exception("Creation of table $tblname failed! ".$res->getCode().$res->getUserInfo());
                    }
                }
                if (DEBUG) echo '[OK]</li>';
            }

            // constraints
            if (DEBUG) echo '<li>creating constraints ';
            if(isset($table['constraints'])) {
                foreach($table['constraints'] as $key=>$val) {
                    $res = $mdb2->createConstraint($tblname, $key, $val);
                    if(PEAR::isError($res)) {
                        throw new Exception("Creation of constraints of table $tblname failed! ".$res->getCode().$res->getUserInfo());
                    }
                }
                if (DEBUG) echo '[OK]</li>';
                
            } else {
                if (DEBUG) echo "<span style=\"color:red;\">[No constraints set!]</span></li>";
            }


            // indices
            if (DEBUG) echo '<li>creating indices ';
            foreach($table['indices'] as $key=>$val) {
                $res = $mdb2->createIndex($tblname, $key, $val);
                if(PEAR::isError($res)) {
                    throw new Exception("Creation of table index $tblname failed! ".$res->getCode().$res->getUserInfo());
                }
            }
            if (DEBUG) echo '<p>'.$tblname.' [OK]</p>';
        }
        
    
        
        echo "</ul>";
    }



    private static function createJoinTables($table_array, $join_table, $mdb2) {

        foreach($table_array as $table) {
            $jtblname = _DBPREFIX.'_'.$table['tablename'].'_join';
            $res = $mdb2->createTable($jtblname, $join_table['table']);
            if(PEAR::isError($res)) {
                throw new Exception("Creation of join table $jtblname failed! ".$res->getCode().$res->getUserInfo());
            }

            $mdb2->createSequence($jtblname);

            // indices
            $indices = array();
            $indices[$jtblname."_id"] = array('fields'=> array('id'=>array()));
            $indices[$jtblname."_subj"] = array('fields'=> array('subject'=>array()));
            $indices[$jtblname."_prop"] = array('fields'=> array('property'=>array()));

            foreach($indices as $key=>$val) {
                $res = $mdb2->createIndex($jtblname, $key, $val);
                if(PEAR::isError($res)) {
                    throw new Exception("Creation of join table index $jtblname failed! ".$res->getCode().$res->getUserInfo());
                }
            }
        }
    }



    private static function writeTableInfo($table_array, $mdb2) {

        foreach($table_array as $table) {
            $tblname = $table['tablename'];

            // write table info to "tables" 
            $id = $mdb2->nextID(_DBPREFIX.'__sys_tables');
            $insert = array('id'=>$id,
                            'title'=>$tblname,
                            'quicksearch'=>$table["quicksearch"],
                            'display_order'=>$table["display_order"],
                            'shortcut'=>$table["shortcut"]
                            );

            if(isset($table["comment"])) { 
                $insert['comment'] = $table["comment"];
            }

            IdaDB::insert('_sys_tables', $insert, 1);

            // write field info to "columns" table
            $count = 0;
            foreach($table['table'] as $key=>$val) {

                $count = $mdb2->nextID(_DBPREFIX.'__sys_columns');
                $col_insert = array('id'=>$key, 'nid'=>$count, 'table_id'=>$tblname,'col_type'=>$val['type'], 'display_order'=>$count);
                
    
                if(isset($val['display_length'])) {
                    $col_insert['width'] = $val['display_length'];
                }

                if(isset($val['display'])) {
                    $col_insert['display'] = $val['display'];
                }
                    
                if(isset($val['prefix'])) {
                    $col_insert['prefix'] = $val['prefix'];
                }

                if(isset($val['required'])) {
                    $col_insert['required'] = $val['required'];
                }
                
                IdaDB::insert('_sys_columns', $col_insert, 1);
                $count++;
            }
        }

    }




    private static function readTableXML($file) {

        $tables = array();

        if($xml = simplexml_load_file($file)) {

            foreach($xml->table as $tab) {

                // fields
                $field_array = array();
                foreach($tab->declaration->field as $field) {

                    $fmeta = array();
                    $fmeta['type'] = ((string)$field->type);
                    $fmeta['length'] = ((string)$field->length);
                    $fmeta['notnull'] = ((string)$field->notnull);
                    
                    if($field->default)
                        $fmeta['default'] = trim(((string)$field->default));
                    
                    if($field->display_length)
                        $fmeta['display_length'] = trim(((string)$field->display_length));
                    else
                        $fmeta['display_length'] = 0;

                    if($field->display)
                        $fmeta['display'] = trim(((string)$field->display));

                    if($field->prefix)
                        $fmeta['prefix'] = trim(((string)$field->prefix));

                    if($field->required)
                        $fmeta['required'] = trim(((string)$field->required));
                        


                    $field_array["$field->name"] = $fmeta;
                    
                }

                $tbl_name = $tab->name;
                $table = array();
                $table["tablename"] = ((string)$tbl_name);
                $table["table"] = $field_array;

                if($tab->shortcutclass) 
                    $table["shortcut"] = trim(((string)$tab->shortcutclass[0]["ID"]));

                
                if($tab->quicksearch)
                    $table['quicksearch'] = trim(((string)$tab->quicksearch));
                else
                    $table['quicksearch'] = 1;
  
                if($tab->display_order)
                    $table['display_order'] = trim(((int)$tab->display_order));  
                else
                    $table['display_order'] = 99;

                if($tab->comment)
                    $table['comment'] = trim(((string)$tab->comment));
                    
                // indices
                $indices = array();
                foreach($tab->declaration->index as $ind) {

                    $fields = array();

                    foreach($ind->field as $indexfield) {
                        $fields["$indexfield->name"] = array();
                    }
                    
                    // if there is primary set, then we make a constraint
                    if($ind->primary) {
                        $def['fields'] = $fields;
                        $def['primary'] = true;
                        $table["constraints"]["$ind->name"] = $def;

                    // otherwise just index
                    } else {
                        $index = array();
                        $index['fields'] = $fields;
                        $indices["$ind->name"] = $index;
                    }
                }
                

                $table["indices"] = $indices;

                $tables[] = $table;
            }
            
        } else {
        
            throw new Exception("XML error in file: ".$file);
      
            
        }
        
        return $tables;
    }





    private static function getDefaults() {


        // all system tables have these
        $fsa = array (
            'uid' => array (
                'type' => 'integer',
                'unsigned' => 1,
                'notnull' => 1,
                'default' => 0,
            ),
            'timestamp' => array (
                'type' => 'timestamp',
            )
        );


        // all data tables have these
        $fda = array (
            'id' => array (
                'type' => 'integer',
                'unsigned' => 1,
                'notnull' => 1,
                'default' => 0,
            ),
            'uid' => array (
                'type' => 'integer',
                'unsigned' => 1,
                'notnull' => 1,
                'default' => 0,
            ),
            'timestamp' => array (
                'type' => 'timestamp',
            )
        );

        $dc = array (
            'prim_id' => array (
                'primary' => true,
                'fields' => array (
                    'id' => array()
                )
            )
        );

        // join tables are all same
        $jt = array (
            'table' => array(
                'id' => array (
                    'type' => 'integer',
                    'unsigned' => 1,
                    'notnull' => 1,
                    'default' => 0,
                ),
                'property' => array (
                    'type' => 'integer',
                    'unsigned' => 1,
                    'notnull' => 1
                ),
                'subject' => array (
                    'type' => 'integer',
                    'unsigned' => 1,
                    'notnull' => 1
                ),
                'link_type' => array (
                    'type' => 'text',
                    'length' => 5,
                    'notnull' => 1
                ),
                'link_info' => array (
                    'type' => 'text',
                    'length' => 255,
                ),
                'uid' => array (
                    'type' => 'integer',
                    'unsigned' => 1,
                    'notnull' => 1,
                    'default' => 0,
                ),
                'timestamp' => array (
                    'type' => 'timestamp',
                )
            )
        );

        return array("for_sys_all"=>$fsa, "for_data_all"=>$fda, "defaultConstraints"=>$dc, "join_table"=>$jt);
        
    }

}
    ?>
