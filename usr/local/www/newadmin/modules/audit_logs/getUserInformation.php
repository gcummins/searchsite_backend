<?php

require "../includes/backend_requirements.php";

// Ensure that the correct parameters have been passed
// This script requires a uid.
// We will return user information for a matching uid

if (isset($_REQUEST['uid']) && !empty($_REQUEST['uid']))
{
	if ((int)$_REQUEST['uid'] > 0)
	{
		$uid = (int)$_REQUEST['uid'];
	}
	else
	{
		returnError(201, "UID is invalid.");
		exit();
	}
}
else
{
	returnError(200, "The parameter 'uid' must be provided.", true);
	exit();
}

// Get the user information
$query = "SELECT id, username, firstName, lastName, emailAddress FROM users WHERE id=$uid LIMIT 1;";
if (false === ($result = mysql_query($query, $adminLink)))
{
	returnError(902, $query, true, $adminLink, 'ajax');
}


if (mysql_num_rows($result) > 0)
{
	$row = mysql_fetch_object($result);
	
	// Determine if the user is logged in
	$query = "SELECT id FROM users WHERE id=$uid AND authkey != '';";
	if (false === ($loginCheckResult = mysql_query($query, $adminLink)))
	{
		returnError(902, $query, true, $adminLink, 'ajax');
	}
	
	if (mysql_num_rows($loginCheckResult))
	{
		// User is currently logged in
		$loginStatus = 1;
	}
	else
	{
		$loginStatus = 0;
	}
	
	$output = '{"user": {"id": "' . $row->id . '", "username": "' . $row->username . '", "firstName":"' .$row->firstName .'", "lastName":"' . $row->lastName . '", "emailAddress":"' . $row->emailAddress . '", "lastLogin": "Never", "loginStatus": "' . $loginStatus . '"} }'; // Status is set to '0' now for all users. Need to update.
	
	echo $output;
	exit();
}
else
{
	returnError(102, 'User Not Found', true, null, 'ajax');
	exit();
}