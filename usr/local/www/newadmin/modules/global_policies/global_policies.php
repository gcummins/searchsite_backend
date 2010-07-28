<?php

// Make sure this script is not called directly
if (!defined('APP_NAME'))
{
	die("This script cannot be accessed directly.");
}

$task = getTask();

switch ($task)
{
	case 'policy_submit':
		updatePolicy();
		break;
	default:
		showTable();
		break;
}

function updatePolicy()
{
	global $adminLink, $module;
	
	if (!isPermitted('edit', $module))
	{
		returnMessage(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, true);
		returnToMainPage();
		return;
	}
		
	// Gather the POSTed fields, and update the database
	if (!isset($_POST) || !count($_POST))
	{
		returnError(201, 'Please use the appropriate form to access this script', true);
		returnToMainPage();
	}
	
	// Create a list of all fields we will check
	$arrFields = array(
		'password_expire_on' => null,
		'password_expire' => null,
		'password_length_on' => null,
		'password_length' => null,
		'password_complex_on' => null,
		'password_history_on' => null,
		'password_history' => null,
		'lock_minutes_on' => null,
		'lock_minutes' => null,
		'lock_attempts_on' => null,
		'lock_attempts' => null,
		'lock_time_reset_on' => null,
		'lock_time_reset' => null);
	
	foreach ($arrFields as $key=>$field)
	{
		if (array_key_exists($key, $_POST))
		{
			// Determine if this is a toggle (checkbox) field or a numeric field
			if (substr($key, -3) == '_on')
			{
				// This is a toggle field. We must replace the form-provided 'on' with a '1'
				if ($_POST[$key] == 'on')
				{
					$arrFields[$key] = 1;
				}
				else
				{
					$arrFields[$key] = 0;
				}
			}
			else
			{
				$arrFields[$key] = $_POST[$key];
			}
		}
		else
		{
			// Determine if this is a toggle (checkbox) field or a numeric field
			if (substr($key, -3) == '_on')
			{
				// This is a toggle field
				$arrFields[$key] = 0;
			}
			else
			{
				$arrFields[$key] = null;
			}
		}
	}
	
	// Prepare the query
	$query = "UPDATE `global_policies` SET";
	
	foreach ($arrFields as $key=>$field)
	{
		// There are two special fields which may not be enabled.
		// If no values were provided for those fields, because they were disabled,
		// we do not want to update their value in the database
		if ($key == 'lock_minutes')
		{
			if ($field != '')
			{
				$query .= " `$key` = $field, ";
			}
			else
			{
				continue;
			}
		}
		elseif ($key == 'lock_time_reset')
		{
			if ($field != '')
			{
				$query .= " `$key` = $field, ";
			}
			else
			{
				continue;
			}
		}
		else
		{
			$query .= " `$key` = $field, ";
		}
	}
	
	// Trim the last comma from the query string
	$query = substr($query, 0, -2) . ";";
	
	if (false === mysql_query($query, $adminLink))
	{
		returnError(902, $query, true, $adminLink);
		returnToMainPage();
		exit();
	}

	returnMessage(1001, sprintf(LANGUAGE_MODIFY_OBJECT_NAME, '', 'global policy'), true);
	returnToMainPage();
}

function showTable()
{
	global $adminLink, $module;
	
	returnMessage(1000, LANGUAGE_VIEWED_MAIN_PAGE, false);
	
	// Retrieve the current parameters from the database
	$query = "SELECT password_expire_on, password_expire, password_length_on, password_length, password_complex_on, password_history_on, password_history, lock_minutes_on, lock_minutes, lock_attempts_on, lock_attempts, lock_time_reset_on, lock_time_reset FROM global_policies;";
	$result = mysql_query($query, $adminLink) or handle_error($query, true, 'mysql', $adminLink);
	
	if (!mysql_num_rows($result))
	{
		handle_error('Failed to retrieve policy information from the database.');
	}
	elseif (mysql_num_rows($result) > 1)
	{
		handle_error('There is a problem with the policy information table `global_policies`. It should contain only one row of data, but currently contains ' . mysql_num_rows($result) . '.');
	}
	else
	{
		$row = mysql_fetch_object($result);
	}
	
	?><table class="contentTable">
	<tr class="table_titlebar">
		<td>Global Policies</td>
	</tr>
	<tr>
		<td>
		<form action="<?php echo ADMINPANEL_WEB_PATH; ?>/index.php" method="post">
		<b>Password Policy</b><br />
		<input type="checkbox" name="password_expire_on" class="checkbox" <?php if ($row->password_expire_on) echo 'checked'; ?>>Password expires every <input type="text" name="password_expire" class="textbox" value="<?php echo $row->password_expire; ?>" /> days.<br />
		<input type="checkbox" name="password_length_on" class="checkbox" <?php if ($row->password_length_on) echo 'checked'; ?>>Password length must be more than <input type="text" name="password_length" class="textbox" value="<?php echo $row->password_length; ?>" /> characters.<br />
		<input type="checkbox" name="password_complex_on" class="checkbox" <?php if ($row->password_complex_on) echo 'checked'; ?>>Passwords must meet complexity requirements.<br />
		<input type="checkbox" name="password_history_on" class="checkbox" <?php if ($row->password_history_on) echo 'checked'; ?>>Enforce password history, remember <input type="text" name="password_history" class="textbox" value="<?php echo $row->password_history; ?>" /> passwords.<br />
		<b>Account Lockout Policy</b><br />
		<input type="checkbox" name="lock_attempts_on" id="lock_attempts_on" class="checkbox" <?php if ($row->lock_attempts_on) echo 'checked'; ?> onchange="javascript:toggleDisabled();">Lock account after <input type="text" name="lock_attempts" class="textbox" value="<?php echo $row->lock_attempts; ?>" /> failed attempts.<br />
		&nbsp;&nbsp;<input type="checkbox" name="lock_time_reset_on" id="lock_time_reset_on" class="checkbox" <?php if ($row->lock_time_reset_on) echo 'checked'; ?> disabled="disabled">Reset account lockout counter after <input type="text" name="lock_time_reset" id="lock_time_reset" class="textbox" value="<?php echo $row->lock_time_reset; ?>" disabled="disabled" /> minutes.<br />
		&nbsp;&nbsp;<input type="checkbox" name="lock_minutes_on" id="lock_minutes_on" class="checkbox" <?php if ($row->lock_minutes_on) echo 'checked'; ?> disabled="disabled">Lock account for <input type="text" name="lock_minutes" id="lock_minutes" class="textbox" value="<?php echo $row->lock_minutes; ?>" disabled="disabled" /> minutes.<br />
		<?php
		if (isPermitted('edit', $module))
		{
			?>
		<input type="submit" value="Save Changes" />
			<?php
		} ?><input type="hidden" name="module" value="<?php echo $module; ?>" />
		<input type="hidden" name="task" value="policy_submit" />
		</form>
		</td>
	</tr>
	</table>
	<?php
}

?>