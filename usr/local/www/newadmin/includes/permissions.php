<?php

function isPermitted($action, $module=0)
{
	// Determine if an action is allowed for this user or group
	
	global $adminLink;
	
	// Set some easy-to-use variables.
	$theUser = $_SESSION['userid'];
	$theGroup = $_SESSION['group_id'];
	
	// If the user is an administrator, the answer is always 'yes'
	if ($theGroup <= ADMINPANEL_GROUPS_ADMINISTRATOR_ID)
	{
		return true;
	}
	else
	{
		// Check the database for permissions
		
		// First, check if this group is permitted to perform this action
		$query = "SELECT allowed FROM `users_allowed_actions` WHERE module=$module AND group_id=$theGroup AND action='$action' LIMIT 1;";
		if (false === ($result = mysql_query($query, $adminLink)))
		{
			returnError(902, $query, true, $adminLink);
		}
		
		if (mysql_num_rows($result))
		{
			$row = mysql_fetch_object($result);
			if ($row->allowed)
			{
				// This group is allowed to perform this action
				return true;
			}
		}
		
		// Next, check if this user is permitted to perform this action.
		// We should only get to this point if the group check did not return a positive result
		$query = "SELECT allowed FROM `users_allowed_actions` WHERE module=$module AND user_id=$theUser AND action='$action' LIMIT 1;";
		if (false === ($result = mysql_query($query, $adminLink)))
		{
			returnError(902, $query, true, $adminLink);
		}
		
		if (mysql_num_rows($result))
		{
			$row = mysql_fetch_object($result);
			if ($row->allowed)
			{
				// This user is allowed to perform this action
				return true;
			}
			else
			{
				return false;
			}
		}
		else
		{
			return false;
		}
	}
}

?>
