
<?php 

// prevent execution from browser
if(isset($_SERVER['REQUEST_URI'])) {
	die("Execute this script from command line!");
}

require_once('class.IdaRecord.php');
require_once('class.DBManager.php');

ob_end_clean();

system("clear");
mainHeader(1);



// *****************************************************
// Part #1 - Retrieving user input from the command line
// *****************************************************


//echo "Please enter your first name: ";
//$firstname = fgets(STDIN, 100);
//$firstname = trim($firstname);
// *****************************************************



mainLoop();

// *****************************************************
// Part #2 - A simple menu system
// *****************************************************

function mainLoop() {
 

   
    do {
      system("clear");
      mainHeader(1);
      
      echo "MENU:\n";
      echo "(1) list tables\n";
      echo "(2) show statistics\n";
      echo "(3) reindex\n";
      echo "\n(0) Exit!\n";
      echo "\nPlease enter a number between 0 and 3 : ";
      $choice = trim(fgets(STDIN));

    } while(!(($choice == "0") || ($choice == "1") || ($choice == "2") || ($choice == "3")));
    // *****************************************************



    system("clear");
    mainHeader(1);
    
    switch($choice){
      case "1":
        listTables();
        break;
      case "2":
        $counter = 100;
        break;
      case "3":
        $counter = 10;
        break;
      case "0":
      default:
        echo "\n\nThanks for using my CLI count down application!\nExiting.....\n\n";
        exit();
    }

}



function listTables() {

    $nums = array();
    $count = 1;
    
    $tables = IdaDb::select("_sys_tables", array(),"id,title"," order by title", "all");
 
    foreach($tables as $key=>$table) {

            $nums[$count] = $key;
            echo $count.". ".$table[0]."\n";
            $count++;
        
    }
 
    do {
        
      echo "Choose table\n";
      $ch = trim(fgets(STDIN));
      echo "choise: ".$ch;
    } while(!array_key_exists($ch, $nums));  
    
    tableInfo($nums[(int)$ch], $tables[$nums[(int)$ch]][0]);  
    
}


function tableInfo($tableId, $tableName) {
    
    $count = 1;
    $num = array();
    
    system("clear");
    mainHeader(1);    
    
    echo "TABLENAME: ".$tableName."\n";
    $result = IdaDb::getTableInfo();
    echo "Choose column:\n";
    echo "---------------------------------------------\n";
    
    foreach($result as $row) {
        if($row["tblid"] == $tableId) {
            $num[$count] = $row["title"];
            echo " ".$count.". ".$row["title"]."\n";
            $count++;
        }
    }
    echo "---------------------------------------------\n";
    
    do {
        
      
      $rowSel = trim(fgets(STDIN));
      echo "choise: ".$rowSel;
    } while(!array_key_exists($rowSel, $num));    
    
    columnInfo($num[(int)$rowSel], $result, $tableName, $tableId);
    
}



function columnInfo($colName, $columns, $tableName, $tableId) {

    $count = 1;
    $nums = array(0,1);
    
    system("clear");
    mainHeader(1); 
    
    echo "COLUMN NAME: ".$colName."\n";
    
    foreach($columns as $col) {
        if($col["title"] == $colName){
            echo "\nTYPE: ".$col["type"];
            echo "\nREQUIRED: ".$col["required"];
            echo "\nWIDTH: ".$col["width"];
            echo "\nWDISPLAY: ".$col["display"];
        }
    }   
 
 
    echo "\nMENU:\n";
    
    echo "1. set width\n";
    echo "2. set display\n";
    echo "--------------\n";
    echo "0. return\n";

    do {
      $rowSel = trim(fgets(STDIN));
      echo "choise: ".$rowSel;
    } while(!array_key_exists($rowSel, $nums));  
   
    switch($rowSel){
      case "0":
        tableInfo($tableId,$tableName);
        break;
      case "1":
        editColWidth();
        break;
      case "3":
        $counter = 10;
        break;
      case "0":
      default:
        echo "\n\nThanks for using my CLI count down application!\nExiting.....\n\n";
        exit();
    }
   
}

function editColWidth() {
    
    
}

function mainHeader($head) {
    
    switch($head) {
        case "1":
            echo "     ***********************\n";
            echo "     IDA-Framework admin tool\n";
            echo "     ***********************\n\n";
            break;
        
    }
}

echo "\n\nBye!\n\n";
exit(0);
?>





