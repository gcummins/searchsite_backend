<?php

require_once "../includes/backend_requirements.php";

// Make sure a section ID was provided
if (!isset($_GET['sectionid']) || empty($_GET['sectionid']))
{
	returnError(100, "Parameter 'sectionid' must be provided", true);
}
else
{
	$sectionid = intval($_GET['sectionid']);
}

$query = "SELECT id, name, display_name, enable_logging, icon FROM " . DB_NAME . ".modules WHERE menu_section=$sectionid ORDER BY `order` ASC;";
if (false === $result = mysql_query($query, $adminLink))
{
	returnError(902, $query, true, $adminLink, 'ajax');
}

// Output the result in JSON format
$output = '{"modules":[';

if (mysql_num_rows($result) > 0)
{
	while ($row = mysql_fetch_object($result))
	{
		$output .= '{
			"id":' . $row->id . ',
			"name":"' . $row->name .'",
			"display_name":"' . $row->display_name . '",	
			"enable_logging":' . $row->enable_logging . ',
			"icon":"' . $row->icon . '"},';
	}
	$output = substr($output, 0, -1);
	$output .= ']}';
}
else
{
	$output .= 'null]}';
}
echo $output;

?>