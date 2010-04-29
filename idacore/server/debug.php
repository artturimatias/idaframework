<?php
ob_start();
require_once('class.IdaSession.php');

// the only purpose of this script is to echo response xml to iframe when debugging


header("Content-Type: text/xml");

 if(IdaSession::checkSession()) {

    echo $_SESSION["iframe_content"];

} else {

    echo "<error>You are not logged in!</error>";

}

?>