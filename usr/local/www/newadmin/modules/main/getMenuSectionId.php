<?php

require_once "../includes/backend_requirements.php";

// Make sure a section ID was provided
if (!isset($_GET['moduleid']) || empty($_GET['moduleid']))
{
	returnError(100, "Parameter 'sectionid' must be provided", true);
}
else
{
	$moduleid = intval($_GET['moduleid']);
}

$query = "SELECT menu_section FROM " . DB_NAME . ".modules WHERE id=$moduleid LIMIT 1;";
if (false === ($result = mysql_query($query, $adminLink)))
{
	returnError(902, $query, true, $adminLink, 'ajax');
	exit();
}

if (!mysql_num_rows($result))
{
	// The request module is not valid. Return zero so the script will return the default menu and module
	echo '0';
	die();
}
else
{
	// A match was found for the module ID, so return the module's menu section ID.
	$row = mysql_fetch_object($result);
	echo $row->menu_section;
	die();
}
?>