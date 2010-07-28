<?php

// Make sure this script is not called directly
if (!defined('APP_NAME'))
{
	die("This script cannot be accessed directly.");
}

$task = getTask();

switch ($task)
{
	case 'submitChanges':
		saveChanges();
		break;
	default:
		showTable();
		break;
}

function saveChanges()
{
	global $adminLink, $module;
	
	if (isPermitted('edit', $module))
	{
		// Determine the user or group being modified
		if (isset($_POST['select_group']) && intval($_POST['select_group']) > 0)
		{
			$editType = 'group';
			$editId = intval($_POST['select_group']);
		}
		elseif (isset($_POST['select_user']) && intval($_POST['select_user']) > 0)
		{
			$editType = 'user';
			$editId = intval($_POST['select_user']);
		}
		else
		{
			returnError(201, "A user or group must be selected.", false);
			showTable();
			return -1;
		}
		
		// Create a query to delete all existing permissions for this group or user.
		// This will be used later. If we run the query now, there is a chance that the
		// script will get interrupted before the insert happens. We want to minimize this
		// chance as much as possible to ensure that we don't lose all permissions for this
		// group/user.
		$deleteQuery = "DELETE FROM `users_allowed_actions` WHERE `{$editType}_id`=$editId;";
		
		// Cycle through each of the elements posted
		$arrMenuPermissions = array();
		$arrModulePermissions = array();
		foreach ($_POST as $key=>$value)
		{
			if (substr($key, 0, 5) == 'perm_') // Use only permission settings
			{
				if (substr($key, 5, 5) == 'menu_')
				{
					$arrMenuPermissions[] = mysql_real_escape_string(substr($key, 5));
				}
				elseif (substr($key, 5, 7) == 'module_')
				{
					list ($recordType, $moduleString, $moduleId, $action) = explode('_', $key);
					$arrModulePermissions[(int)$moduleId][] = mysql_real_escape_string($action);
				}
			}
		}
		
		if (count($arrMenuPermissions) || count($arrModulePermissions))
		{
			// Create insert statements for each type of update.
			$menuQuery = "INSERT INTO `users_allowed_actions`"
				. " (`module`, `{$editType}_id`, `action`, `allowed`)"
				. " VALUES ";
			
			// Menu permissions are not associated with a module number.
			foreach ($arrMenuPermissions as $allowedMenu)
			{
				$menuQuery .= "(0, $editId, '$allowedMenu', 1), ";
			}
			
			// Module permissions
			foreach ($arrModulePermissions as $moduleId=>$arrAllowedModuleActions)
			{
				foreach ($arrAllowedModuleActions as $allowedModuleAction)
				{
					$menuQuery .= "($moduleId, $editId, '$allowedModuleAction', 1), ";
				}
			}
			$menuQuery = substr($menuQuery, 0, -2) . ";";
			
			// Run the queries
			if (false === mysql_query($deleteQuery, $adminLink))
			{
				returnError(902, $deleteQuery, false, $adminLink);
			}
			
			if (false === mysql_query($menuQuery, $adminLink))
			{
				returnError(902, $menuQuery, false, $adminLink);
				returnToMainPage();
				exit();
			}
			
			returnMessage(1001, sprintf(LANGUAGE_MODIFY_OBJECT_ID, "permissions for $editType", $editId), false);
		}
	}
	returnToMainPage();
}

function showTable()
{
	global $adminLink, $module;
	
	returnMessage(1000, LANGUAGE_VIEWED_MAIN_PAGE, false);
	
	?><h3>Modify Permissions for:</h3>
	<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post"><?php
	// Get a list of available groups
	$query = "SELECT `id`, `name` FROM `groups` WHERE id!=".ADMINPANEL_GROUPS_ADMINISTRATOR_ID.";";
	if (false === ($result = mysql_query($query, $adminLink)))
	{
		returnError(902, $query, true, $adminLink);
	}
	if (mysql_num_rows($result))
	{
		?>
		<label for="select_group">Entire Group: </label><select name="select_group" onchange="reloadPage(this.value, 'group', '<?php echo ADMINPANEL_WEB_PATH; ?>', <?php echo $module; ?>);">
		<option value="">-- Select One --</option>
		<?php
		while (false !== ($row = mysql_fetch_object($result)))
		{
			?>
			<option value="<?php echo $row->id; ?>"<?php
				if (isset($_REQUEST['modifyType']) && $_REQUEST['modifyType'] == 'group' && isset($_REQUEST['modify']) && $_REQUEST['modify'] == $row->id)
				{
					echo " selected=\"selected\"";
				}
				elseif (isset($_REQUEST['select_group']) && (int)$_REQUEST['select_group'] == $row->id)
				{
					echo " selected=\"selected\"";
				}
			?>><?php echo $row->name; ?></option>
			<?php
		}
		?></select>
		<?php
		$groupSelectIsDisplayed = true;
	}
	else
	{
		$groupSelectIsDisplayed = false;
	}
	
	// Get a list of available users
	$query = "SELECT `id`, CONCAT(`firstName`, ' ', `lastName`) as `name`, `username` FROM `users` ORDER BY `name` ASC;";
	if (false === ($result = mysql_query($query, $adminLink)))
	{
		returnError(902, $query, true, $adminLink);
	}
	if (mysql_num_rows($result))
	{
		if ($groupSelectIsDisplayed)
		{
			echo " or ";
		}
		
		?>
		<label for="select_user">Individual User: </label><select name="select_user" onchange="reloadPage(this.value, 'user', '<?php echo ADMINPANEL_WEB_PATH; ?>', <?php echo $module; ?>)">
		<option value="">-- Select One --</option>
		<?php
		while (false !== ($row = mysql_fetch_object($result)))
		{
			?>
			<option value="<?php echo $row->id; ?>"<?php
				if (isset($_REQUEST['modifyType']) && $_REQUEST['modifyType'] == 'user' && isset($_REQUEST['modify']) && $_REQUEST['modify'] == $row->id)
				{
					echo " selected=\"selected\"";
				}
				elseif (isset($_REQUEST['select_user']) && (int)$_REQUEST['select_user'] == $row->id)
				{
					echo " selected=\"selected\"";
				}
			?>><?php echo $row->name; ?>&nbsp;(<?php echo $row->username ?>)</option>
			<?php
		}
		?></select>
		<?php
	}
	
	if ((isset($_REQUEST['select_group']) && (int)$_REQUEST['select_group'] > 0) || (isset($_REQUEST['select_user']) && (int)$_REQUEST['select_user'] > 0))
	{
		// Get a list of permissions for this user or group.
		if (isset($_REQUEST['select_group']) && (int)$_REQUEST['select_group'] > 0)
		{
			$modifyType = 'group';
		}
		elseif (isset($_REQUEST['select_user']) && (int)$_REQUEST['select_user'] > 0)
		{
			$modifyType = 'user';
		}
		else 
		{
			returnError(201, "Cannot modify permissions for '$modify'", false);
			showTable();
			return 0;
		}			
		$modify = (int)$_REQUEST['select_'.$modifyType];
		
		// Create a query to select all permissions
		$query = "SELECT `module`, `action` FROM `users_allowed_actions` WHERE `{$modifyType}_id`=$modify AND `allowed`=1;";
		
		if (false === ($result = mysql_query($query, $adminLink)))
		{
			returnError(902, $query, false, $adminLink);
			return 0;
		}
		
		$arrActionsAllowed = array();
		while (false !== ($row = mysql_fetch_object($result)))
		{
			$arrActionsAllowed[$row->module][] = $row->action;
		}
		
		// Get a list of available menus
		$query = "SELECT `id`, `name`, `display_name` FROM `menu_sections` ORDER BY `order` ASC;";
		if (false === ($result = mysql_query($query, $adminLink)))
		{
			returnError(902, $query, true, $adminLink);
		}

		while (false !== ($row = mysql_fetch_object($result)))
		{
			if (array_key_exists(0, $arrActionsAllowed) && is_array($arrActionsAllowed[0]) && in_array('menu_'.$row->name, $arrActionsAllowed[0]))
			{
				$menuSpanClass = "";
				$menuCheckBoxSelected = " checked=\"checked\"";
			}
			else
			{
				$menuSpanClass = " hidden";
				$menuCheckBoxSelected = "";
			}
			?>
			<br /><input onclick="permissions_toggleSub(this, document.getElementById('menu_permissions_sub_<?php echo $row->id; ?>'));" class="menu_section_checkbox" type="checkbox" name="perm_menu_<?php echo $row->name; ?>" value="on"<?php echo $menuCheckBoxSelected; ?> />
			<label class="menu_section_label"><?php echo $row->display_name; ?></label>
			<span class="menu_permissions<?php echo $menuSpanClass; ?>" id="menu_permissions_sub_<?php echo $row->id; ?>">
			<?php
			$moduleQuery = "SELECT `id`, `display_name` FROM `modules` WHERE `menu_section`=".$row->id." ORDER BY `order` ASC;";
			if (false === ($moduleResult = mysql_query($moduleQuery, $adminLink)))
			{
				returnError(902, $query, true, $adminLink);
			}
			
			while (false !== ($moduleRow = mysql_fetch_object($moduleResult)))
			{
				if (array_key_exists($moduleRow->id, $arrActionsAllowed) && is_array($arrActionsAllowed[$moduleRow->id]) && in_array('view', $arrActionsAllowed[$moduleRow->id]))
				{
					$spanClass = "";
					$checkBoxSelected = " checked=\"checked\"";
				}
				else
				{
					$spanClass = " hidden";
					$checkBoxSelected = "";
				}
				?>
				<br /><input onclick="permissions_toggleSub(this, document.getElementById('module_permissions_sub_<?php echo $moduleRow->id; ?>'));" class="module_checkbox" type="checkbox" name="perm_module_<?php echo $moduleRow->id; ?>_view" value="on"<?php echo $checkBoxSelected; ?>/>
				<label><?php echo $moduleRow->display_name; ?></label>
				<span class="module_permissions<?php echo $spanClass; ?>" id="module_permissions_sub_<?php echo $moduleRow->id; ?>">
					<br />
					<input class="checkbox_first" type="checkbox" name="perm_module_<?php echo $moduleRow->id; ?>_create" value="on"<?php
					if (array_key_exists($moduleRow->id, $arrActionsAllowed) && is_array($arrActionsAllowed[$moduleRow->id]) && in_array('create', $arrActionsAllowed[$moduleRow->id]))
					{
						echo " checked=\"checked\"";
					}
					 ?> />
					<label class="action_details">Create</label>
					<input type="checkbox" name="perm_module_<?php echo $moduleRow->id; ?>_edit" value="on"<?php
					if (array_key_exists($moduleRow->id, $arrActionsAllowed) && is_array($arrActionsAllowed[$moduleRow->id]) && in_array('edit', $arrActionsAllowed[$moduleRow->id]))
					{
						echo " checked=\"checked\"";
					}
					 ?> />
					<label class="action_details">Edit</label>
					<input type="checkbox" name="perm_module_<?php echo $moduleRow->id; ?>_delete" value="on"<?php
					if (array_key_exists($moduleRow->id, $arrActionsAllowed) && is_array($arrActionsAllowed[$moduleRow->id]) && in_array('delete', $arrActionsAllowed[$moduleRow->id]))
					{
						echo " checked=\"checked\"";
					}
					 ?> />
					<label class="action_details">Delete</label>
				</span>
				<?php
				
			}
			?></span>
			<?php
		}
		?>
		<div class="form_button_div">
			<input type="submit" value="Submit" class="inputbutton" onclick="return validateForm_permissions(<?php echo $module; ?>);" />
			<input type="button" value="Cancel" class="inputbutton" onclick="hideEditDiv();" />
		</div>
		<input type="hidden" name="module" value="<?php echo $module; ?>" />
		<input type="hidden" name="task" value="submitChanges" />
		</form><?php
	}
}

?>
