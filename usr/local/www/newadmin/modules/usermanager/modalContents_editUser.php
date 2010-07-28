<?php

require_once "../includes/backend_requirements.php";

$scriptName = htmlentities($_REQUEST['scriptname']);
$module = (int)$_REQUEST['module'];

if (isset($_REQUEST['userid']) && !empty($_REQUEST['userid']))
{
	// This is an edit request. Gather the information about this user from the database
	$formTitle	= "Edit User";
	$task		= "saveEdit";
	
	$id			= (int)$_REQUEST['userid'];
	
	// Create the query
	$query = "SELECT `username`, `firstName`, `lastName`, `emailAddress`, `group_id`, `enabled`,"
		. " `log_activity`, `password_change_next_logon`, `password_change_cannot`, `password_never_expires`,"
		. " `account_locked` FROM `users` WHERE id=$id LIMIT 1;";
	if (false === ($result = mysql_query($query, $adminLink)))
	{
		returnError(902, $query, true, $adminLink);
	}
	if (!mysql_num_rows($result))
	{
		returnError(201, "An invalid user ID was provided.", false);
		showTable();
		return;
	}
	$row = mysql_fetch_object($result);
	
	$username					= mysql_real_escape_string($row->username);
	$password					= '[hidden]';
	$firstName					= mysql_real_escape_string($row->firstName);
	$lastName					= mysql_real_escape_string($row->lastName);
	$emailAddress				= mysql_real_escape_string($row->emailAddress);
	$groupId					= (int)$row->group_id;
	$enabled					= (int)$row->enabled;
	$log_activity				= (int)$row->enabled;
	$password_change_next_logon	= (int)$row->password_change_next_logon;
	$password_change_cannot		= (int)$row->password_change_cannot;
	$password_never_expires		= (int)$row->password_never_expires;
	$account_locked				= (int)$row->account_locked;
	
	$usernameFieldDisabledString = " disabled=\"disabled\"";
}
else
{
	// This is a new user
	$formTitle					= "Create User";
	$task						= "saveNew";
	
	$id							= null;

	$username					= '';
	$password					= '';
	$firstName					= '';
	$lastName					= '';
	$emailAddress				= '';
	$groupId					= ADMINPANEL_GROUPS_USER_ID; // Default to the 'User' group
	$enabled					= 1;
	$log_activity				= 1;
	$password_change_next_logon	= 0;
	$password_change_cannot		= 0;
	$password_never_expires		= 1;
	$account_locked				= 0;
	
	$usernameFieldDisabledString = "";
}

// Create the 'group_id' select options
$query = "SELECT id, name FROM `groups` ORDER BY id ASC;";
if (false === ($result = mysql_query($query, $adminLink)))
{
	returnError(902, $query, true, $adminLink);
}

$groupIdSelectOptions = '';
while (false !== ($row = mysql_fetch_object($result)))
{
	$groupIdSelectOptions .= "<option value=\"" . $row->id . "\"";
	if ($groupId == $row->id)
	{
		$groupIdSelectOptions .= " selected=\"selected\"";
	}
	$groupIdSelectOptions .= ">" . $row->name . "</option>";
}


// These are used to create the checkboxes later.
if ($enabled)
{
	$enabledString = ' checked="checked"';
}
else
{
	$enabledString = '';
}
if ($log_activity)
{
	$log_activityString = ' checked="checked"';
}
else
{
	$log_activityString = '';
}
if ($password_change_next_logon)
{
	$password_change_next_logonString = ' checked="checked"';
}
else
{
	$password_change_next_logonString = '';
}
if ($password_change_cannot)
{
	$password_change_cannotString = ' checked="checked"';
}
else
{
	$password_change_cannotString = '';
}
if ($password_never_expires)
{
	$password_never_expiresString = ' checked="checked"';
}
else
{
	$password_never_expiresString = '';
}
if ($account_locked)
{
	$account_lockedString = ' checked="checked"';
}
else
{
	$account_lockedString = '';
}

echo <<< HEREDOC
<span class="edit_div_title">$formTitle</span>
	<table>
		<tr>
			<td class="detail_cell">
				<form action="$scriptName" method="post">
					<p>
						<label for="edit_username">Username</label>
						<input type="text" name="edit_username" id="edit_username" value="$username" maxlength="256" $usernameFieldDisabledString /><br />
						<label for="edit_group_id">Group</label>
						<select name="edit_group_id" id="edit_group_id">
							$groupIdSelectOptions
						</select>
						<label for="edit_password">Password</label>
						<input type="text" name="edit_password" id="edit_password" value="$password" maxlength="256" /><br />
						<label for="edit_firstname">First Name</label>
						<input type="text" name="edit_firstname" id="edit_firstname" value="$firstName" maxlength="128" /><br />
						<label for="edit_lastname">Last Name</label>
						<input type="text" name="edit_lastname" id="edit_lastname" value="$lastName" maxlength="128" /><br />
						<label for="edit_emailaddress">Email Address</label>
						<input type="text" name="edit_emailaddress" id="edit_emailaddress" value="$emailAddress" maxlength="256" /><br />
						<label for="edit_enabled">Enabled</label>
						<input type="checkbox" name="edit_enabled" id="edit_enabled" $enabledString /><br /><br />
						<label for="edit_log_activity">Log Activity</label>
						<input type="checkbox" name="edit_log_activity" name="edit_log_activity" $log_activityString /><br />
						<label for="edit_password_change_next_logon">Must change password at next login?</label>
						<input type="checkbox" name="edit_password_change_next_logon" id="edit_password_change_next_logon" $password_change_next_logonString /><br />
						<label for="edit_password_change_cannot">Cannot change password?</label>
						<input type="checkbox" name="edit_password_change_cannot" id="edit_password_change_cannot" $password_change_cannotString /><br />
						<label for="edit_password_never_expires">Password Never Expires</label>
						<input type="checkbox" name="edit_password_never_expires" id="edit_password_never_expires" $password_never_expiresString /><br />
						<label for="edit_account_locked">Account Locked</label>
						<input type="checkbox" name="edit_account_locked" id="edit_account_locked" $account_lockedString /><br />
					</p>
					<div class="form_button_div">
						<input type="submit" value="Submit" class="inputbutton" onclick="return validateForm_usermanager('{$scriptName}', {$module});" />
						<input type="button" value="Cancel" class="inputbutton" onclick="hideEditDiv();" />
					</div>
					<input type="hidden" name="userid" id="edit_userid" value="$id" />
					<input type="hidden" name="task" id="edit_task" value="$task" />
					<input type="hidden" name="module" value="$module" />
			</td>
		</tr>
	</table>
HEREDOC;
?>
