<?php

require "../includes/backend_requirements.php";

$scriptName = htmlentities($_REQUEST['scriptname']);
$module = (int)$_REQUEST['module'];
$moduleName = getModuleName($module);

if (isset($_REQUEST['objectid']) && !empty($_REQUEST['objectid']))
{
	
}


?>