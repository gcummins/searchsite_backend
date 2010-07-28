<?php

// Make sure this script is not called directly
if (!defined('APP_NAME'))
{
	die("This script cannot be accessed directly.");
}

$task = getTask();

switch ($task)
{
	default:
		showTable();
		break;
}

function showTable()
{
	global $adminLink, $module, $feedDatabase;
	
	// Get a count of all available processes
	$processCountQuery = "SELECT COUNT(*) FROM `activeProcesses`;";
	$feedDatabase->query($processCountQuery);
	
	/*
	 if (false === ($processCountResult = mysql_query($processCountQuery, $adminLink)))
	{
		returnError(902, $processCountQuery, true, $adminLink);
	}
	*/
	$processCount = $feedDatabase->firstField();
	//list($processCount) = mysql_fetch_row($ProcessCountResult);
	
	$paginator = new Pagination($module, $processCount);
	
	// Retrieve the process information	
	$query = "SELECT `pid`, `processName`, `unixStartTime`, `status` FROM `activeProcesses` ORDER BY `processName` " . $paginator->getLimitString();
	$feedDatabase->query($query);
	/*
	if (false === ($processListResult = mysql_query($query, $adminLink)))
	{
		returnError(902, $query, true, $adminLink);
	}
	*/
	
	
}

?>