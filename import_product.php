<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
include('../../config/config.inc.php'); 
include('../../init.php');
require_once('vendor/autoload.php');
require_once('config.php');
require_once('class/import.php');
 

$import = new Import();
     


?>