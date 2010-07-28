<?php

// Make sure this script is not called directly
if (!defined('APP_NAME'))
{
	die("This script cannot be accessed directly.");
}

function writeLog($logType, $task, $note, $url, $module)
{
	global $adminLink;
	
	if (isset($_SESSION['userid']))
	{
		$uid = (int)$_SESSION['userid'];
	}
	else
	{
		$uid = -1;
	}
	
	$ipaddress = null;
	if (!empty($_SERVER['REMOTE_ADDR']))
	{
		$ipaddress = mysql_real_escape_string($_SERVER['REMOTE_ADDR'], $adminLink);
	}
	
	// Sanitize the script filename
	$script = mysql_real_escape_string($_SERVER['SCRIPT_FILENAME'], $adminLink);
	
	// Sanitize the referer string
	if (array_key_exists('HTTP_REFERER', $_SERVER))
	{
		$referer = mysql_real_escape_string($_SERVER['HTTP_REFERER'], $adminLink);
	}
	else
	{
		$referer = 'unavailable';
	}

	// Sanitize the URL
	$url = mysql_real_escape_string($url, $adminLink);
	
	// Sanitize the $note
	$note = mysql_real_escape_string($note, $adminLink);
	
	// Prepare the query to insert into the log
	$query = "INSERT INTO log (user_id, type, date_time, module, script, task, url, referer, ip, note) VALUES "
		. "($uid, '$logType', NOW(), '$module', '$script', '" . addslashes($task) . "', '$url', '$referer', '$ipaddress', '$note');";
	
	// Execute the query
	if (false === (mysql_query($query, $adminLink)))
	{
		returnError(902, $query, true, $adminLink);
	}
}

function logError($task, $note, $url=null, $module=null)
{	
	$logType = 'error';
	
	if (empty($url))
	{
		$url = $_SERVER['REQUEST_URI'] . $_SERVER['QUERY_STRING'];
	}
	
	writeLog($logType, $task, $note, $url, $module);
}

function logNotice($task, $note, $url=null, $module=null)
{
	$logType = 'notice';
	
	if (empty($url))
	{
		$url = $_SERVER['REQUEST_URI'] . $_SERVER['QUERY_STRING'];
	}
	
	writeLog($logType, $task, $note, $url, $module);
}	
?>