<?php

// Make sure this script is not called directly
if (!defined('APP_NAME'))
{
	die("This script cannot be accessed directly.");
}

$task = getTask();

switch ($task)
{
	case 'modperms':
		modifyPermissions();
		break;
	case 'deleteSubmit':
		deleteUser();
		break;
	case 'saveEdit':
		gatherFormFields('edit');
		break;
	case 'saveNew':
		gatherFormFields('new');
		break;
	default:
		showTable();
		break;
}
function modifyPermissions()
{
	global $adminLink;
	
	// Determine the id of the Permissions module, and redirect the user
	$query = "SELECT `id` FROM `modules` WHERE `name`='permissions' LIMIT 1;";
	if (false === ($result = mysql_query($query, $adminLink)))
	{
		returnError(902, $query, false, $adminLink);
		showTable();
		return;
	}
	
	if (!mysql_num_rows($result))
	{
		returnError(201, 'The permissions module could not be located. Please contact an administrator.', false);
		returnToMainPage();
		return;
	}
	else
	{
		$row = mysql_fetch_object($result);
		$module = (int)$row->id;
		
		if (isset($_REQUEST['uid']))
		{
			$uid = (int)$_REQUEST['uid'];
			$url = ADMINPANEL_WEB_PATH . "?module=$module&select_user=$uid";
			?>
			<script type="text/javascript">
			location.href = '<?php echo $url; ?>';
			</script>
			<?php
		}
		else
		{
			returnError(201, 'A user must be specified.', false);
			returnToMainPage();
			return;
		}
	}
}

function gatherFormFields($submitType)
{
	// This function will gather and sanitize the form fields,
	// then pass the data to the appropriate function for
	// editing or insertion.
	
	$arrFields = array(); // To hold the sanitized values
	
	// Field: ID
	if (isset($_POST['userid']))
	{
		$id = intval($_POST['userid']);
	}
	else
	{
		$submitType = 'new';	// Even if this was submitted as an edit, if no userid was provided, create a new record.
	}
	
	// Field: username
	if ($submitType == 'new')
	{
		if (isset($_POST['edit_username']) && !empty($_POST['edit_username']))
		{
			$arrFields['username'] = mysql_real_escape_string($_POST['edit_username']);
		}
		else
		{
			returnError(201, 'Username is required', false);
			returnToMainPage();
			return;
		}
	}
	
	// Field: password
	if (isset($_POST['edit_password']) && !empty($_POST['edit_password']) && $_POST['edit_password'] != '[hidden]' )
	{
		$arrFields['password'] = mysql_real_escape_string($_POST['edit_password']);
	}
	else
	{
		$arrFields['password'] = '';	// If this is an edit, we will later ensure that the
										// existing password is preserved.
	}
	
	// Field: firstName
	if (isset($_POST['edit_firstname']))
	{
		$arrFields['firstName'] = mysql_real_escape_string($_POST['edit_firstname']);
	}
	else
	{
		$arrFields['firstName'] = '';
	}
	
	// Field: lastName
	if (isset($_POST['edit_lastname']))
	{
		$arrFields['lastName'] = mysql_real_escape_string($_POST['edit_lastname']);
	}
	else
	{
		$arrFields['lastName'] = '';
	}
	
	// Field: emailAddress
	if (isset($_POST['edit_emailaddress']))
	{
		$arrFields['emailAddress'] = mysql_real_escape_string($_POST['edit_emailaddress']);
	}
	else
	{
		$arrFields['emailAddress'] = '';
	}
	
	// Field: group_id
	if (isset($_POST['edit_group_id']) && intval($_POST['edit_group_id']))
	{
		$arrFields['group_id'] = intval($_POST['edit_group_id']);
	}
	else
	{
		$arrFields['group_id'] = ADMINPANEL_GROUPS_USER_ID; // Default to the 'User' group
	}
	
	// Field: enabled
	if (isset($_POST['edit_enabled']) && intval($_POST['edit_enabled']) == 'on')
	{
		$arrFields['enabled'] = 1;
	}
	else
	{
		$arrFields['enabled'] = 0;
	}
	
	// Field: log_activity
	if (isset($_POST['edit_log_activity']) && intval($_POST['edit_log_activity']) == 'on')
	{
		$arrFields['log_activity'] = 1;
	}
	else
	{
		$arrFields['log_activity'] = 0;
	}
	
	// Field: password_change_next_logon
	if (isset($_POST['edit_password_change_next_logon']) && intval($_POST['edit_password_change_next_logon']) == 'on')
	{
		$arrFields['password_change_next_logon'] = 1;
	}
	else
	{
		$arrFields['password_change_next_logon'] = 0;
	}
	
	// Field: password_change_cannot
	if (isset($_POST['edit_password_change_cannot']) && intval($_POST['edit_password_change_cannot']) == 'on')
	{
		$arrFields['password_change_cannot'] = 1;
	}
	else
	{
		$arrFields['password_change_cannot'] = 0;
	}
	
	// Field: password_never_expires
	if (isset($_POST['edit_password_never_expires']) && intval($_POST['edit_password_never_expires']) == 'on')
	{
		$arrFields['password_never_expires'] = 1;
	}
	else
	{
		$arrFields['password_never_expires'] = 0;
	}

	// Field: account_locked
	if (isset($_POST['edit_account_locked']) && intval($_POST['edit_account_locked']) == 'on')
	{
		$arrFields['account_locked'] = 1;
	}
	else
	{
		$arrFields['account_locked'] = 0;
	}
	
	if ($submitType == 'edit')
	{
		saveEdit($arrFields, $id);
	}
	else
	{
		saveNew($arrFields);
	}
}

function saveEdit($arrFields, $id)
{	
	global $adminLink, $module;
	
	if (!isPermitted('edit', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, true);
		returnToMainPage();
		return;
	}
		
	// Generate the MySQL update query
	$query = "UPDATE `users` SET";
		
	if (!empty($arrFields['password']))
	{
		$query .= " `password`='" . md5($arrFields['password']) . "',";
	}
	
	$query .= " `firstName`='"				. $arrFields['firstName'] . "',"
		. " `lastName`='"					. $arrFields['lastName'] . "',"
		. " `emailAddress`='"				. $arrFields['emailAddress'] . "',"
		. " `group_id`="					. $arrFields['group_id'] . ","
		. " `enabled`="						. $arrFields['enabled'] . ","
		. " `log_activity`="				. $arrFields['log_activity'] . ","
		. " `password_change_next_logon`="	. $arrFields['password_change_next_logon'] . ","
		. " `password_change_cannot`="		. $arrFields['password_change_cannot'] . ","
		. " `password_never_expires`="		. $arrFields['password_never_expires'] . ","
		. " `account_locked`="				. $arrFields['account_locked'] . ""
		. " WHERE `id`=$id LIMIT 1;";
	
	if (false === mysql_query($query, $adminLink))
	{
		returnError(902, $query, false, $adminLink);
		returnToMainPage();
		exit();
	}
	returnMessage(1001, sprintf(LANGUAGE_MODIFY_OBJECT_ID, 'user', $id), false);
	returnToMainPage();
}

function saveNew($arrFields)
{
	global $adminLink, $module;
	
	if (!isPermitted('create', $module))
	{
		showTable();
		return 0;
	}
	
	// Make sure this username does not already exist
	$query = "SELECT `id` FROM `users` WHERE `username`='" . $arrFields['username'] . "';";
	if (false === ($userCheckResult = mysql_query($query, $adminLink)))
	{
		returnError(902, $query, true, $adminLink);
		returnToMainPage();
		return;
	}
	if (mysql_num_rows($userCheckResult))
	{
		returnError(201, "The user '" . $arrFields['username'] . "' already exists. Please select another username.", false);
		returnToMainPage();
		return;
	}
	
	// Generate the MySQL insert query
	$query = "INSERT INTO `users`"
		. " (`username`, `password`, `firstName`, `lastName`, `emailAddress`, `group_id`,"
		. " `enabled`, `log_activity`, `password_change_next_logon`, `password_change_cannot`,"
		. " `password_never_expires`, `account_locked`) VALUES ("
		. " '" . $arrFields['username']						. "',"
		. " '" . md5($arrFields['password'])				. "',"
		. " '" . $arrFields['firstName']					. "',"
		. " '" . $arrFields['lastName']						. "',"
		. " '" . $arrFields['emailAddress']					. "',"
		. " "  . $arrFields['group_id']						. ","
		. " "  . $arrFields['enabled']						. ","
		. " "  . $arrFields['log_activity']					. ","
		. " "  . $arrFields['password_change_next_logon']	. ","
		. " "  . $arrFields['password_change_cannot']		. ","
		. " "  . $arrFields['password_never_expires']		. ","
		. " "  . $arrFields['account_locked']				. ""
		. ");";
	if (false === mysql_query($query, $adminLink))
	{
		returnError(902, $query, false, $adminLink);
		returnToMainPage();
		exit();
	}
	returnMessage(1002, sprintf(LANGUAGE_CREATE_OBJECT_NAME, 'user', $arrFields['username']), false);
	returnToMainPage();
}

function deleteUser()
{
	global $adminLink, $module;
	
	if (isPermitted('delete', $module))
	{
		if (isset($_REQUEST['userid']) && (int)$_REQUEST['userid'] > 0)
		{
			$uid = (int)$_REQUEST['userid'];
			
			// First, delete any entries in the permission tables
			$query = "DELETE FROM `users_allowed_actions` WHERE `user_id`=$uid;";
			if (false === (mysql_query($query, $adminLink)))
			{
				returnError(902, $query, true, $adminLink);
				returnToMainPage();
				return;
			}
			
			// Next, delete the user from the users table
			$query = "DELETE FROM `users` WHERE `id`=$uid;";
			if (false === (mysql_query($query, $adminLink)))
			{
				returnError(902, $query, true, $adminLink);
				returnToMainPage();
			}
			
			if (mysql_affected_rows($adminLink))
			{
				returnMessage(1101, sprintf(LANGUAGE_DELETE_OBJECT_ID, 'user', $uid), false);
				returnToMainPage();
				return;
			}
			else
			{
				returnError(102, "The requested user does not exist.", false);
				returnToMainPage();
				return;
			}
			
		}
		else
		{
			returnError(103, 'User ID must be provided', true);
			returnToMainPage();
			return;
		}
	}
	else
	{
		returnError(301, 'This operation is not permitted.', false);
		returnToMainPage();
		return;
	}
}

function showTable()
{
	global $adminLink, $module, $moduleName;
	
	returnMessage(1000, LANGUAGE_VIEWED_MAIN_PAGE, false);
		
	// Create the legend
	include "modules/includes/legend.class.php";
	$legend = new Legend(array(
		array('good', 'Enabled'),
		array('invalid', 'Disabled')
	));
	echo $legend->create();
	
	if (isset($_REQUEST['paging_orderby']) && !empty($_REQUEST['paging_orderby']))
	{
		switch ($_REQUEST['paging_orderby'])
		{
			case 'group':
				$orderbyString = "ORDER BY groupname ASC";
				break;
			case 'groupd':
				$orderbyString = "ORDER BY groupname DESC";
				break;
			case 'username':
				$orderbyString = "ORDER BY username ASC";
				break;
			case 'usernamed':
				$orderbyString = "ORDER BY username DESC";
				break;
			case 'lastnamed':
				$orderbyString = "ORDER BY lastName DESC, firstName DESC";
				break;
			case 'lastname':
			default:
				$orderbyString = "ORDER BY lastName ASC, firstName ASC";
				break;
		}
	}
	else
	{
		$orderbyString = "ORDER BY lastName ASC, firstName ASC";
	}
	
	// Get a count of all available users
	$rowCountQuery = "SELECT count(*) as `count` FROM `users`;";
	if (false === ($rowCountResult = mysql_query($rowCountQuery, $adminLink)))
	{
		returnError(902, $rowCountQuery, true, $adminLink);
	}
	list($rowCount) = mysql_fetch_row($rowCountResult);
	
	// Include the pagination class
	include "includes/pagination.class.php";
	
	$paginator = new Pagination($module, $rowCount);
	
	// Load user information
	$query = "SELECT `users`.`id`, `username`, `firstName`, `lastName`, `emailAddress`, `group_id`,"
		. " `groups`.`name` as `groupname`, `enabled` FROM `users`"
		. " LEFT JOIN `groups` on group_id=`groups`.`id`" 
		. " $orderbyString;";
	if (false === ($result = mysql_query($query, $adminLink)))
	{
		returnError(902, $query, true, $adminLink);
	}
	
	// Include the table-creation class
	include "includes/pageContentTable.class.php";
	
	while (false !== ($row = mysql_fetch_object($result)))
	{
		if ($row->enabled != null && $row->enabled != 1)
		{
			$rowModifier = "class=\"legend_invalid\"";
		}
		else
		{
			$rowModifier = "class=\"legend_good\"";
		}
		if ($row->enabled)
		{
			$enableString = "Enabled";
		}
		else
		{
			$enableString = "Disabled";
		}
		$actionButtons = null;
		if (isPermitted('edit', $module))
		{
			$actionButtons .= "<a href=\"#\" onclick=\"showEditDiv(" . $row->id . ", '" . ADMINPANEL_WEB_PATH . "', '$moduleName', '" . $_SERVER['PHP_SELF'] . "', $module);\"><img src=\"" .  ADMINPANEL_WEB_PATH . "/images/edit.png\" alt=\"Edit\" /></a>";
			$permissionsModifyString = "<a href=\"" . ADMINPANEL_WEB_PATH . "/index.php?ak=1&module=$module&task=modperms&uid=" . $row->id . "\">Modify...</a>";
			$statusString = "<a href=\"#\" onclick=\"changeStatus(" . $row->id . ", " . $row->enabled . ", '" . ADMINPANEL_WEB_PATH . "', $module); return false;\">$enableString</a>";
		}
		else
		{
			$permissionsModifyString = "<span style=\"color: #aaa7a7\">Modify...</span>";
			$statusString = "<span style=\"color: #aaa7a7\">Enabled</span>";
		}
		if (isPermitted('delete', $module))
		{
			$actionButtons .= "<a href=\"#\" onclick=\"confirmUserDelete('" . $row->username . "', " . $row->id . ", '" . ADMINPANEL_WEB_PATH . "', '$module');\"><img src=\"" . ADMINPANEL_WEB_PATH . "/images/delete.png\" alt=\"Delete\" /></a>";
		}
		
		if (!empty($actionButtons))
		{
			$arrListOfColumns = array('Action', 'Username', 'Account Type', 'Full Name', 'Email Address', 'Permissions', 'Status');
			$arrDataRows[] = array($rowModifier, array(
				array("class=\"action_buttons\"", $actionButtons),
				array('', htmlspecialchars($row->username)),
				array('', htmlspecialchars($row->groupname)),
				array('', htmlspecialchars($row->firstName . " " . $row->lastName)),
				array('', htmlspecialchars($row->emailAddress)),
				array('align="center"', $permissionsModifyString),
				array('align="center"', $statusString)
			));
		}
		else
		{
			$arrListOfColumns = array('Username', 'Account Type', 'Full Name', 'Email Address', 'Permissions', 'Status');
			$arrDataRows[] = array($rowModifier, array(
				array('', htmlspecialchars($row->username)),
				array('', htmlspecialchars($row->groupname)),
				array('', htmlspecialchars($row->firstName . " " . $row->lastName)),
				array('', htmlspecialchars($row->emailAddress)),
				array('align="center"', $permissionsModifyString),
				array('align="center"', $statusString)
			));
		}
	}
	
	// Set the table action
	if (isPermitted('create', $module))
	{
		$action = "showNewDiv('" . ADMINPANEL_WEB_PATH . "', '" . $moduleName . "', '" . $_SERVER['PHP_SELF'] . "', " . $module . ");";
	}
	else
	{
		$action = '';
	}
	
	// Create the orderby select control
	$selectControlString  = "<div class=\"orderby_div\">";
	$selectControlString .= "\nSort By: <select name=\"paging_orderby\" onchange=\"loadSortOrder(this, '" . ADMINPANEL_WEB_PATH . "', " . $module . ");\">\n";
	$selectControlString .= "\t<option value=\"lastname\"";
	if ($paginator->orderby == "lastname")
	{
		$selectControlString .= " selected=\"selected\"";
	}
	$selectControlString .= ">Last Name</option>\n\t<option value=\"lastnamed\"";
	if ($paginator->orderby == "lastnamed")
	{
		$selectControlString .= " selected=\"selected\"";
	}
	$selectControlString .= ">Last Name (reversed)</option>\n\t<option value=\"username\"";
	if ($paginator->orderby == "username")
	{
		$selectControlString .= " selected=\"selected\"";
	}
	$selectControlString .= ">Username</option>\n\t<option value=\"usernamed\"";
	if ($paginator->orderby == "usernamed")
	{
		$selectControlString .= " selected=\"selected\"";
	}
	$selectControlString .= ">Username (reversed)</option>\n\t<option value=\"group\"";
	if ($paginator->orderby == "group")
	{
		$selectControlString .= " selected=\"selected\"";
	}
	$selectControlString .= ">Group</option>\n\t<option value=\"groupd\"";
	if ($paginator->orderby == "groupd")
	{
		$selectControlString .= " selected=\"selected\"";
	}
	$selectControlString .= ">Group (reversed)</option>\n";
	$selectControlString .= "</select></div>";
	// Create an array to hold the "addendum" to the table
	$arrAddendum = array(
		$selectControlString,
		$paginator->generateLinkBar()
	);
	
	// Create the table
	$theTable = new pageContentTable('User Manager', $arrListOfColumns, 'Create User', $action, $arrDataRows, $arrAddendum);
	
	if ($theTable->error())
	{
		returnError(301, $theTable->error(), true);
	}
	else
	{
		echo $theTable->createTable();
	}
}

?>
