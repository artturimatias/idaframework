<?php
/**
 * @package ida
 */

/**
   * needs database
*/
require_once('class.IdaDB.php');

/**
 * Class for logins 
 * @package ida
 */
class IdaSession {

	static function login ($xml) {

            $xpath = new DOMXPath($xml);
            $userNode = $xpath->query("/login/user");
            $passNode = $xpath->query("/login/pass");
            $seedNode =  $xpath->query("/login/seed");
            
            $user = trim($userNode->item(0)->firstChild->nodeValue);
            $passwd = trim($passNode->item(0)->firstChild->nodeValue);
            $seed = trim($seedNode->item(0)->firstChild->nodeValue);
            
            $user_count = mb_strlen($user);
            $pass_count = mb_strlen($passwd);
            
            // only numbers, alphabet and . and _ are allowed
            $user_match_count = preg_match_all('/[0-9]|[a-z]|\.|\_/i', $user,  $arr, PREG_PATTERN_ORDER);
            $pass_match_count = preg_match_all('/[0-9]|[a-z]|\.|\_/i', $passwd,  $arr, PREG_PATTERN_ORDER);


            if( $user_count == $user_match_count && $pass_count == $pass_match_count) {
                    return IdaSession::startSession($user, $passwd, $seed);
            } else {
                    return IdaSession::writeErrorLog($user, $passwd);
            }
	}

	private function startSession($user, $passwd, $seed) {

        // get seed
        $where = array(
            "act" => "loginseed",
            "seed" => $seed,
            "http_user_agent" =>  $_SERVER['HTTP_USER_AGENT'],
            "remote_addr" => $_SERVER['REMOTE_ADDR']
        );

        //$sql = "SELECT seed FROM "._DBPREFIX."__sys_eventlog WHERE ";

        // get hashed password for user
        $sql = "SELECT username, passwd FROM "._DBPREFIX."__sys_users WHERE username = ?";
        $where = array($user);
        $res = IdaDB::prepareExecute($sql, $where, "onerow");
        
        if($res["username"] == $user) {
        
            $hash = sha1($res["passwd"].$seed);
            
            if($hash == $passwd) {
            
                $where = array("username"=>$user);

                $res = IdaDB::select('_sys_users', $where);
                $pass = count($res);

                    // if valid then start session
                if ($pass) {
                    $myrow = $res[0];
                    session_start();

                    $_SESSION['firstname'] = $myrow['firstname'];
                    $_SESSION['lastname'] = $myrow['lastname'];
                    $_SESSION['name'] = $myrow['firstname'].' '.$myrow['lastname'];
                    $_SESSION['username'] = $user;
                    $_SESSION['status'] = $myrow['status'];
                    $_SESSION['style_sheet'] = $myrow['css'];
                    $_SESSION['lang'] = $myrow['lang'];
                    $_SESSION['uid'] = $myrow['person_id'];
                    
                    return IdaSession::writeLog($user, $passwd);
                    
                } else {

                    return IdaSession::writeErrorLog($user, $passwd);
                        
                }
            } else {
                return IdaSession::writeErrorLog($user, $passwd);
            }

        } else {
            return IdaSession::writeErrorLog($user, $passwd);
        }
    
	}

	static function checkSession() {
		session_start();     
		if (!isset($_SESSION['username'])) {
            return 0;
		} else {
			return 1;
		}
	}
	
	static function logout() {
        
        session_start();     
        if (isset($_SESSION['username'])) {
            session_destroy();
            return "<response status=\"ok\">logged out</response>";
        }
	}
	
	 function writeLog($user, $pass) {

		$values = array(
			"uid" => $_SESSION['uid'],
			"host_info" =>  $_SERVER['HTTP_USER_AGENT'],
			"host_ip" => $_SERVER['REMOTE_ADDR'],
			"act" => "login"
		);

		return "<response status=\"ok\">".$user."</response>";
		IdaDB::insert("_event_log", $values);
		//echo $_SERVER['REMOTE_ADDR'];
		//echo $_SERVER['HTTP_USER_AGENT'];
        echo $user;
	}
	
	private function writeErrorLog($user, $passwd) {
        IdaLog::errorLog("invalid_login");
		return "<response status=\"bad\">invalid login!</response>";
		//echo $_SERVER['REMOTE_ADDR'];
		//echo $_SERVER['HTTP_USER_AGENT'];
	}
}


?>
