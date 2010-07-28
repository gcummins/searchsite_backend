<?php

require "../includes/backend_requirements.php";

/*
 * This script will return one of three possible values:
 * 
 *  0 - An error occurred, or no data is available
 *  1 - The requested value is valid and can be used
 *  2 - The requested value is not valid and cannot be used
 */

// Gather the required fields: subsite_id, field_name, field_value
if (isset($_REQUEST['subsite_id']))
{
	$subsiteId = (int)$_REQUEST['subsite_id'];
}
else
{
	echo -1;
	exit;
}

if (isset($_REQUEST['field_name']) && !empty($_REQUEST['field_name']))
{
	$fieldName = mysql_real_escape_string(stripslashes($_REQUEST['field_name']));
}
else
{
	echo -2;
	exit;
}

if (isset($_REQUEST['field_value']) && !empty($_REQUEST['field_value']))
{
	$fieldValue = mysql_real_escape_string(stripslashes($_REQUEST['field_value']));
}
else
{
	echo -3;
	exit;
}

// If the field is a database name, we need to check a few different places
if ($fieldName == 'db_name_a' || $fieldName == 'db_name_b')
{
	// First, ensure that the name is valid according to MySQL standards
	if (!validate_mysql_db_name($fieldValue))
	{
		echo 2;
		exit;
	}
	
	// Next, check both database name columns in the table
	$query = "SELECT `id` FROM `subsites` WHERE `db_name_a`='$fieldValue' OR `db_name_b`='$fieldValue' LIMIT 1;";
	if (false === ($dbCheck1Result = mysql_query($query, $adminLink)))
	{
		echo mysql_error($adminLink);
		exit;
	}
	else if (mysql_num_rows($dbCheck1Result))
	{
		// This database name is already in use
		echo 2;
		exit;
	}
	else if ($fieldValue == 'mysql')
	{
		// The database name 'mysql' is reserved
		echo 2;
		exit;
	}
	else
	{
		// This database name is available
		echo 1;
		exit;
	}
}
else // This is a regular field, so just check for matches
{
	$query = "SELECT `id` FROM `subsites` WHERE `$fieldName`='$fieldValue' LIMIT 1;";

	if (false === ($result = mysql_query($query, $adminLink)))
	{
		echo -4;
		exit();
	}
	else if (mysql_num_rows($result))
	{
		echo 2;
		exit();
	}
	else
	{
		echo 1;
		exit();
	}
}
?>