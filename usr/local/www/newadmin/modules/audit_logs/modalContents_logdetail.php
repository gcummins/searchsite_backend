<?php

require_once "../includes/backend_requirements.php";

if (isset($_REQUEST['objectid']) && !empty($_REQUEST['objectid']))
{
	$logId = (int)$_REQUEST['objectid'];
}
else
{
	returnError(201, "No log ID was provided. Please contact an administrator.", true, null, 'ajax');
	exit();
}

if (isset($_REQUEST['requesttype']) && !empty($_REQUEST['requesttype']))
{
	switch ($_REQUEST['requesttype'])
	{
		case 'byuser':
			$requestType = "byuser";
			break;
		default:
			$requestType = "generic";
			break;
	}
}
else
{
	$requestType = "generic";
}

// Retrieve the log detail for this id
$query = "SELECT `type`, `date_time`, `modules`.`display_name` as `moduleName`, `script`, `task`, `url`, `referer`, `ip`, `note`"
	. " FROM `log`"
	. " LEFT JOIN `modules` on `modules`.`id`=`module`"
	. " LEFT JOIN `users` ON `users`.`id`=`log`.`user_id`"
	. " WHERE `log`.`id`=$logId LIMIT 1;";

if (false === ($result = mysql_query($query, $adminLink)))
{
	returnError(902, $query, true, $adminLink, 'ajax');
	exit();
}

$row = mysql_fetch_object($result);

$output = '';
if ($requestType != "byuser")
{
	$output .= "<label>Name</label><span>" . $row->firstName . " " . $row->lastName . "</span>";
	$output .= "<label>Username</label><span>" . $row->username . "</span>";
}

// IP Address
$output .= "<label>IP Address</label><span>";
if (!empty($row->ip))
{
	$output .= $row->ip;
}
else
{
	$output .= "&nbsp;";
}
$output .=  "</span>";

// Date
if ($requestType != "bydate")
{
	$output .= "<label>Date</label><span>" . date('l, n/j/Y g:i A', strtotime($row->date_time)) . "</span>";
}

// Type
if ($requestType != "bytype")
{
	$output .= "<label>Type</label><span>" . ucfirst($row->type) . "</span>";
}

// Module
if ($requestType != "bymodule")
{
	$output .= "<label>Module</label><span>";
	if (!empty($row->moduleName))
	{
		$output .= $row->moduleName;
	}
	else
	{
		$output .= "&nbsp;";
	}
	$output .=  "</span>";
	
}

// Description
$output .= "<label>Description</label><span>";
if (!empty($row->task))
{
	$output .= $row->task;
}
else
{
	$output .= "&nbsp;";
}
$output .=  "</span>";
$output .= "<label>Script</label><span>" . $row->script . "</span>";
$output .= "<label>Parameters</label><span>";
 if (!empty($row->url))
{
	$output .= $row->url;
}
else
{
	$output .= "&nbsp;";
}
$output .= "</span>";
if (!empty($row->note))
{
	$output .= "<label>Note</label><span>" . $row->note . "</span>";
}

echo $output;
exit();
?>