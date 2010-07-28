<?php

require_once "../includes/backend_requirements.php";

// Set the name of the module to be used in the links
// created by this script.
// Consider pulling this data dynamically from the database
$moduleName = 'audit_logs';

if (isset($_REQUEST['objecttype']) && !empty($_REQUEST['objecttype']))
{
	$objectType = htmlentities($_REQUEST['objecttype']);
}
else
{
	returnError(200, "An object type must be provided. Please contact an administrator.", true, null, 'ajax');
	exit();
}

switch ($objectType)
{
	case 'user':
		// This is a request for logs pertaining to a particular user
		loadLogEntriesByUser();
		break;
	case 'module':
		// This is a request for logs pertaining to a particular module
		loadLogEntriesByModule();
		break;
	case 'daterange':
		// The user selected a date range via the form provided
		loadLogEntriesBySelectedDateRange();
		break;
	case 'fixedrange':
		// The user clicked one of the fixed-range links provided
		loadLogEntriesByFixedDateRange();
		break;
	case 'search':
		loadLogEntriesBySearch();
		break;
	default:
		returnError(201, "The object type provided is invalid. Please contact an administrator.", true, null, 'ajax');
		exit();
		break;
}

function loadLogEntriesBySearch()
{
	// We will assemble the search parameters and query, and then pass handling off to the 
	// appropriate function to return the search entries
	
	global $adminLink;
	
	// Assemble the search query
	if (csvRequested())
	{
		$query = "SELECT `log`.`id`, `type`, `date_time`, `task`, `modules`.`display_name` as `moduleName`, "
			. " `ip`, `users`.`username`, `script`, `url`, `note`, "
			. " CONCAT(`users`.`firstName`, ' ', `users`.`lastName`) as `fullName`"
			. " FROM `log`"
			. " LEFT JOIN `modules` on `modules`.`id`=`log`.`module`"
			. " LEFT JOIN `users` on `users`.`id`=`log`.`user_id`";
	}
	else
	{
		$query = "SELECT `log`.`id`, `type`, `date_time`, `task`, `modules`.`display_name` as `moduleName`, "
			. " CONCAT(`users`.`firstName`, ' ', `users`.`lastName`) as `fullName`"
			. " FROM `log`"
			. " LEFT JOIN `modules` on `modules`.`id`=`log`.`module`"
			. " LEFT JOIN `users` on `users`.`id`=`log`.`user_id`";
	}
		
	// Retrieve the search words
	if (isset($_REQUEST['searchterm']))
	{
		$searchTerm =  preg_replace("/[^a-zA-Z0-9 ]/", "", urldecode($_REQUEST['searchterm'])); // Decode and clean the string;
		if (!empty($searchTerm))
		{
			// Treat each search term as a seperate word. We will not do phrase matching.
			$arrSearchTerms = explode(' ', $searchTerm);
			$termWhereString = '';
			foreach ($arrSearchTerms as $term)
			{
				if (!empty($termWhereString))
				{
					$termWhereString .= " OR";
				}
				// Search each of the following fields: task, ip, note
				$termWhereString .= " `task` LIKE '%$term%'"
					. " OR `ip` LIKE '%$term%'"
					. " OR `note` LIKE '%$term%'";
			}
		}
		else
		{
			$termWhereString = '';
		}
	}
	else
	{
		$termWhereString = '';
	}
	
	// Retrieve the selected user ids. The search will be limited to these users. If the 'all users' 
	// option was selected, or if none were selected, we will not include this limitation in the query
	if (isset($_REQUEST['searchusers']) && is_array($_GET['searchusers']))
	{
		$arrUsers = $_REQUEST['searchusers'];
		
		if (!in_array('all', $arrUsers) && count($arrUsers))
		{
			// The option 'all users' was not selected, and the array is not of zero length.
			// We need to create a list containing each of the selected user IDs.
			$userWhereString = "`user_id` IN (";
			
			foreach ($arrUsers as $searchUserId)
			{
				$userWhereString .= "$searchUserId, ";
			}
			
			$userWhereString = substr($userWhereString, 0, -2) . ")";
		}
		else
		{
			$userWhereString = '';
		}
	}
	else
	{
		$userWhereString = '';
	}
	
	// Retrieve the date range
	if (isset($_REQUEST['datetype']) && $_REQUEST['datetype'] != 'all')
	{
		$startTime = retrieveSelectedDate('start');
		$endTime = retrieveSelectedDate('end');
	}
	
	
	$metaWhereString = '';
	if (!empty($termWhereString))
	{
		$metaWhereString .= " WHERE ($termWhereString)";
	}
	if (!empty($userWhereString))
	{
		if (empty($metaWhereString))
		{
			$metaWhereString = " WHERE ($userWhereString)";
		}
		else
		{
			$metaWhereString .= " AND ($userWhereString)";
		}
	}
	if (isset($startTime) && isset($endTime))
	{
		if ($startTime > $endTime)
		{
			// Swap the dates so $startTime is older than $endTime
			$tempTime = $endTime;
			$endTime = $startTime;
			$startTime = $tempTime;
		}
		$daterangeWhereString = "`date_time` >= '" . date('Y-m-d H:i:s', $startTime) . "'"
				. " AND `date_time` <= '" . date('Y-m-d H:i:s', $endTime) . "'";
				
		if (empty($metaWhereString))
		{
			$metaWhereString = " WHERE ($daterangeWhereString)";
		}
		else
		{
			$metaWhereString .= " AND ($daterangeWhereString)";
		}
	}
	$query .= $metaWhereString;
	
	
	if (countRequested())
	{
		$query = "SELECT count(`log`.`id`) as `count`"
			. " FROM `log`"
			. " $metaWhereString;";
		
		echo getCount($query);
		exit;
	}
	
	if (isset($_REQUEST['searchsortby']))
	{
		switch ($_REQUEST['searchsortby'])
		{
			case 'module':
				$sortbyString = "ORDER BY `moduleName` ASC, `date_time` DESC, `log`.`id` DESC";
				break;
			case 'user':
				$sortbyString = "ORDER BY `users`.`lastName` ASC, `users`.`firstName` ASC, `date_time` DESC, `log`.`id` DESC";
				break;
				//returnError(9999, "Sort by 'user' is not yet complete.", true, null, 'ajax');
				//exit();
			case 'date':
			default:
				$sortbyString = "ORDER BY `date_time` DESC, `log`.`id` DESC";
				break;
		}
	}
	else
	{
		$sortbyString = "ORDER BY `date_time` DESC, `log`.`id` DESC";
	}
	
	$query .= " $sortbyString;";
	
	if (false === ($result = mysql_query($query, $adminLink)))
	{
		returnError(902, $query, true, $adminLink, 'ajax');
		exit();
	}
	//returnError(9999, "Correct the WHERE statement when a term and a user_id is included. Currently results are returned for all users.", true, null, 'ajax');
	loadLogEntries($result);
}

function loadLogEntriesBySelectedDateRange()
{
	// We will assemble start and end dates, then hand those dates off to the
	// generic function that returns the log entries.
	
	
	// Assemble the start date
	$startTime = retrieveSelectedDate('start');
	
	// Assemble the end date
	$endTime = retrieveSelectedDate('end');
	
	// Hand off to a generic function to return the entries in this range.
	loadLogEntriesByDate($startTime, $endTime);
}
function loadLogEntriesByFixedDateRange()
{
	// We will assemble start and end dates (in Unix time) based on
	// the fixed range selected.
	// Then we will hand those dates off to the generic function 
	// that returns the log entries.
	
	// Retrieve the fixed-range that was requested
	$iFixedRange = $_REQUEST['linktype'];
	switch ($iFixedRange)
	{
		case 'yesterday':
			$startTime	= strtotime("yesterday"); // Yesterday at 12:00:00 AM
			$endTime	= $startTime + 86399; // Last night at 11:59:59 PM
			break;
		case 'pastweek':
			$startTime	= strtotime("midnight last week");
			$endTime	= strtotime("yesterday") + 86399; // Last night at 11:59:59 PM
			break;
		case 'today':
		default:
			$startTime	= strtotime("today"); // Today at 12:00:00 AM
			$endTime	= $startTime + 86399; // Tonight at 11:59:59 PM
			break;
	}
	
	// Hand off to a generic function to return the entries in this range.
	loadLogEntriesByDate($startTime, $endTime);
}

function loadLogEntriesByDate($startTime, $endTime)
{
	global $adminLink;
	
	if ($startTime < $endTime)
	{
		$orderbyString = "ORDER BY `date_time` DESC, `log`.`id` DESC";
	}
	else
	{
		// This allows users an easy way of reversing the results, if they so desire.
		// They can simply select a "start date" that is later than the "end date."
		$orderbyString = "ORDER BY `date_time` ASC, `log`.`id` ASC";
		// Swap the values to make sure that $endTime is later than $startTime
		$tempTime = $endTime;
		$endTime = $startTime;
		$startTime = $tempTime;
	}
	
	if (countRequested())
	{
		$query = "SELECT count(`log`.`id`) as `count`"
			. " FROM `log`"
			. " WHERE `date_time` >= '" . date('Y-m-d H:i:s', $startTime) . "' AND"
			. " `date_time` <= '" . date('Y-m-d H:i:s', $endTime) . "'";
		
		echo getCount($query);
		exit;
	}
	
	// Get a list of log entries within the date range specified
	if (csvRequested())
	{
		$query = "SELECT `log`.`id`, `type`, `date_time`, `task`, `modules`.`display_name` as `moduleName`, "
			. " `ip`, `users`.`username`, `script`, `url`, `note`, "
			. " CONCAT(`users`.`firstName`, ' ', `users`.`lastName`) as `fullName`"
			. " FROM `log`"
			. " LEFT JOIN `modules` on `modules`.`id`=`log`.`module`"
			. " LEFT JOIN `users` on `users`.`id`=`log`.`user_id`"
			. " WHERE `date_time` >= '" . date('Y-m-d H:i:s', $startTime) . "' AND"
			. " `date_time` <= '" . date('Y-m-d H:i:s', $endTime) . "'"
			. " $orderbyString;";
	}
	else
	{
		$query = "SELECT `log`.`id`, `type`, `date_time`, `task`, `modules`.`display_name` as `moduleName`, "
			. " CONCAT(`users`.`firstName`, ' ', `users`.`lastName`) as `fullName`"
			. " FROM `log`"
			. " LEFT JOIN `modules` on `modules`.`id`=`log`.`module`"
			. " LEFT JOIN `users` on `users`.`id`=`log`.`user_id`"
			. " WHERE `date_time` >= '" . date('Y-m-d H:i:s', $startTime) . "' AND"
			. " `date_time` <= '" . date('Y-m-d H:i:s', $endTime) . "'"
			. " $orderbyString;";
	}
		
	if (false === ($result = mysql_query($query, $adminLink)))
	{
		returnError(902, $query, true, $adminLink, 'ajax');
		exit();
	}
	loadLogEntries($result);
}

function loadLogEntries($result, $entryTagField='task')
{
	global $moduleName;

	if (csvRequested())
	{
		// Generate a CSV document, and return it
		header("Content-Type: application/vnd.ms-excel");
		header("Content-Disposition: attachment; filename=\"report.csv\"");
		$output = "Date, IP Address, Name, Username, Log Type, Module, Description, Script, Parameters, Note\n";
		while ($row = mysql_fetch_object($result))
		{
			$formattedDate = date('l, n/j/y', strtotime($row->date_time));
			$output .= "\"{$formattedDate}\","
				. "\"{$row->ip}\","
				. "\"{$row->fullName}\","
				. "\"{$row->username}\","
				. "\"{$row->type}\","
				. "\"{$row->moduleName}\","
				. "\"{$row->task}\","
				. "\"{$row->script}\","
				. "\"{$row->url}\","
				. "\"{$row->note}\"\n";
		}
		echo $output;
		exit();
	}
	else
	{
	
	if (!mysql_num_rows($result))
	{
		$output = "<table class=\"table_logentries\"><tr><td><li>No log entries were found.</td></tr></table>";
		echo $output;
		exit();
	}
		
		$output = "<table class=\"table_logentries\">";
		$output .= "<tr><th>Entry</th><th>Module</th><th>User</th><th>Date/Time</th></tr>";
		
		while ($row = mysql_fetch_object($result))
		{
			$secondsInADay = 86400; // 60*60*24
			if (strtotime($row->date_time) >= mktime(0, 0, 0))
			{
				$formattedDate = "Today";
			}
			elseif (strtotime($row->date_time) > (mktime(0, 0, 0) - $secondsInADay))
			{
				$formattedDate = "Yesterday";
			}
			else
			{
				$formattedDate = date('l, n/j/y', strtotime($row->date_time));
			}
			
			// Return a link for each log entry
			$entryName = '';
			if (!empty($row->moduleName))
			{
				$entryName .= $row->moduleName;
			}
			
			$moduleString = $row->moduleName;
			
			$fullName  = $row->fullName;
			
			if (!empty($row->$entryTagField))
			{
				if (!empty($entryName))
				{
					$entryName .= " : " . $row->$entryTagField;
				}
				else
				{
					$entryName = $row->$entryTagField;
				}
			}
			if (empty($entryName))
			{
				$entryName = "Entry";
			}
			
			// Limit the length of the entry name
			if (strlen($entryName) > ADMINPANEL_AUDITLOG_MAX_LOG_TAG_LENGTH)
			{
				$entryName = trim(substr($entryName, 0, ADMINPANEL_AUDITLOG_MAX_LOG_TAG_LENGTH-3)) . '...';
			}
			
			$output .= "<tr class=\"" . $row->type . "\" onclick=\"loadLogEntryDetail(" . $row->id . ", '" . ADMINPANEL_WEB_PATH . "', '$moduleName'); return false;\">";
			$output .= "<td>$entryName</td>";
			$output .= "<td>$moduleString</td>";
			$output .= "<td>$fullName</td>";
			$output .= "<td>$formattedDate, " . date("g:i A", strtotime($row->date_time)) . "</td>";
			$output .= "</tr>";
		}
		$output .= "</table>";
		echo $output;
		exit();
	}
}

function loadLogEntriesByModule()
{
	global $adminLink;
	
	if (isset($_REQUEST['objectid']) && !empty($_REQUEST['objectid']))
	{
		$moduleId = (int)$_REQUEST['objectid'];
	}
	else
	{
		returnError(201, "No module ID was provided. Please contact an administrator.", true, null, 'ajax');
		exit();
	}
	
	$orderbyString = "ORDER BY `date_time` DESC, `log`.`id` DESC";

	if (countRequested())
	{
		$query = "SELECT count(`log`.`id`) as `count`"
			. " FROM `log`"
			. " WHERE `module`=$moduleId;";
		
		echo getCount($query);
		exit;
	}
	
	// Get a list of log entries for this module, ordered by date
	if (csvRequested())
	{
		$query = "SELECT `log`.`id`, `modules`.`display_name` as `moduleName`,"	
			. " `ip`, `users`.`username`, `script`, `url`, `note`, "
			. " CONCAT(`users`.`firstName`, ' ', `users`.`lastName`) as `fullName`,"
			. " `type`, `date_time`, `task`"
			. " FROM `log`"
			. " LEFT JOIN `modules` on `modules`.`id`=`log`.`module`"
			. " LEFT JOIN `users` on `users`.`id`=`user_id`"
			. " WHERE `module`=$moduleId"
			. " $orderbyString;";
	}
	else
	{
		$query = "SELECT `log`.`id`, `modules`.`display_name` as `moduleName`,"	
			. " CONCAT(`users`.`firstName`, ' ', `users`.`lastName`) as `fullName`,"
			. " `type`, `date_time`, `task`"
			. " FROM `log`"
			. " LEFT JOIN `modules` on `modules`.`id`=`log`.`module`"
			. " LEFT JOIN `users` on `users`.`id`=`user_id`"
			. " WHERE `module`=$moduleId"
			. " $orderbyString;";
	}
	
	if (false === ($result = mysql_query($query, $adminLink)))
	{
		returnError(902, $query, true, $adminLink, 'ajax');
		exit();
	}
	
	loadLogEntries($result, 'nameString');
}

function loadLogEntriesByUser()
{
	global $adminLink;
	
	if (isset($_REQUEST['objectid']) && !empty($_REQUEST['objectid']))
	{
		$userId = (int)$_REQUEST['objectid'];
	}
	else
	{
		returnError(201, "No user ID was provided. Please contact an administrator.", true, null, 'ajax');
		exit();
	}
	
	if (countRequested())
	{
		$query = "SELECT count(`log`.`id`) as `count`"
			. " FROM `log`"
			. " WHERE `user_id`=$userId;";
		
		echo getCount($query);
		exit;
	}
	
	// Get a list of log entries for this user, ordered by date
	if (csvRequested())
	{
		$query = "SELECT `log`.`id`, `type`, `date_time`, `task`, `modules`.`display_name` as `moduleName`,"
			. " `ip`, `users`.`username`, `script`, `url`, `note`, "
			. " CONCAT(`users`.`firstName`, ' ', `users`.`lastName`) as `fullName`"
			. " FROM `log`"
			. " LEFT JOIN `modules` on `modules`.`id`=`log`.`module`"
			. " LEFT JOIN `users` on `users`.`id`=`log`.`user_id`"
			. " WHERE `user_id`=$userId"
			. " ORDER BY `date_time` DESC, `log`.`id` DESC;";
	}
	else
	{
		$query = "SELECT `log`.`id`, `type`, `date_time`, `task`, `modules`.`display_name` as `moduleName`,"
			. " CONCAT(`users`.`firstName`, ' ', `users`.`lastName`) as `fullName`"
			. " FROM `log`"
			. " LEFT JOIN `modules` on `modules`.`id`=`log`.`module`"
			. " LEFT JOIN `users` on `users`.`id`=`log`.`user_id`"
			. " WHERE `user_id`=$userId"
			. " ORDER BY `date_time` DESC, `log`.`id` DESC;";
	}
	
	if (false === ($result = mysql_query($query, $adminLink)))
	{
		returnError(902, $query, true, $adminLink, 'ajax');
		exit();
	}
	
	if (!mysql_num_rows($result))
	{
		$output = "<table class=\"table_logentries\"><tr><td><li>No log entries were found.</td></tr></table>";
		echo $output;
		exit();
	}
	
	loadLogEntries($result);
}
function getCount($query)
{
	global $adminLink;
	
	if (false === ($result = mysql_query($query, $adminLink)))
	{
		returnError(902, $query, true, $adminLink, 'ajax');
		exit;
	}
	
	$row = mysql_fetch_object($result);
	
	$output = "{count:" . $row->count . "}";
	return $output;	
}


function retrieveSelectedDate($type)
{
	// Assemble start and end dates (in Unix time) based on the dates selected.
	$arrRequiredFields = array(
		$type.'_month',
		$type.'_day',
		$type.'_year'
		);
	
	foreach ($arrRequiredFields as $field)
	{
		if (isset($_REQUEST[$field]) && !empty($_REQUEST[$field]))
		{
			$$field = (int)$_REQUEST[$field];
		}
		else
		{
			returnError(200, "The parameter '" . ucwords(str_replace('_', ' ', $field)) . "' is required. Please contact an administrator.", true, null, 'ajax');
			exit();
		}
	}
	
	// Assemble the date
	if ($type == 'end')
	{
		return mktime(23, 59, 59, ${$type.'_month'}, ${$type.'_day'}, ${$type.'_year'});
	}
	else
	{
		return mktime(0, 0, 0, ${$type.'_month'}, ${$type.'_day'}, ${$type.'_year'});
	}
}

function csvRequested()
{
	if (isset($_REQUEST['getcsv']) && $_REQUEST['getcsv'] == 'true')
	{
		return true;
	}
	else
	{
		return false;
	}
}

function countRequested()
{
	// The frontend can request a simply record count, rather than the actual data.
	// This function will check to see if that record count was requested.
	if (isset($_REQUEST['getcount']))
	{
		return true;
	}
	else
	{
		return false;
	}
}
?>