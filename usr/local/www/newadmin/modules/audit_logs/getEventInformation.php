<?php

require "../includes/backend_requirements.php";

$callingMethod = 'post';


// Ensure that the correct parameters have been passed
// This script requires a uid.
// We will return user information for a matching uid

if (isset($_REQUEST['eventid']) && !empty($_REQUEST['eventid']))
{
	if ((int)$_REQUEST['eventid'] > 0)
	{
		$eventID = (int)$_REQUEST['eventid'];
	}
	else
	{
		returnError(201, "Event ID is invalid.");
		exit();
	}
}
else
{
	returnError(200, "The parameter 'eventid' must be provided.", true);
	exit();
}

// Get the user information
$query = "SELECT user_id, users.username, type, date_time, modules.display_name as module_display_name, script, task, referer, ip, note FROM `log` "
 . "LEFT JOIN modules ON log.module = modules.id "
 . "LEFT JOIN users ON log.user_id = users.id "
 . "WHERE log.id=$eventID LIMIT 1;";
$result = mysql_query($query, $adminLink) or returnError(902, $query, 'true', $adminLink);

if (mysql_num_rows($result) > 0)
{
	$row = mysql_fetch_object($result);
	
	$output = '{"event": {"uid": "' . $row->user_id . '", "username": "' . $row->username . '", "type": "' . $row->type . '", "date_time": "' . date('m/d/Y G:i:s', strtotime($row->date_time)) . '", "module":"' .$row->module_display_name .'", "script":"' . $row->script . '", "task": "' . $row->task . '", "referer": "' . $row->referer . '", "ip": "' . $row->ip . '", "note": "' . $row->note . '"} }';
	
	echo $output;
	exit();
}
else
{
	returnError(102, 'Event Not Found', true);
	exit();
}