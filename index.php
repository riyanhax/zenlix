<?php

//echo 'fuck you';exit;

session_start();
error_reporting(E_ALL ^ E_NOTICE);
error_reporting(0);
ini_set('error_reporting', E_ALL);

include_once ("conf.php");

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

if (!isset($CONF_DB)) {
    include "sys/install.php";
    exit(0);
}

include_once ("functions.inc.php");
include_once ('app/classes/classes.php');

include_once ('library/AltoRouter/AltoRouter.php');
include_once ('library/phpoffice/phpexcel/Classes/PHPExcel.php');
include_once ("app/core/route_actions.php");
include_once ("app/core/route_lists.php");

require_once 'arava_tools.php';
