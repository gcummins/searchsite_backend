<?php

// Make sure this script is not called directly
if (!defined('APP_NAME'))
{
	die("This script cannot be accessed directly.");
}

$task = getTask();

switch ($task)
{
	case 'submitProperties':
		submitProperties();
		break;
	case 'properties':
		showProperties();
		break;
	default:
		showTable();
		break;
}

function submitProperties()
{
	global $adminLink, $module;
	
	if (!isPermitted('edit', $module))
	{
		showTable();
		return 0;
	}
	
	if (isset($_REQUEST['daemonid']))
	{
		$daemonid = (int)$_REQUEST['daemonid'];
	}
	
	// Make sure the daemonid matches a daemon listed in the database
	$query = "SELECT count(*) AS reccount FROM daemons WHERE id=$daemonid;";
	if (false == ($daemonCountResult = mysql_query($query, $adminLink)))
	{
		returnError(902, $query, true, $adminLink);
	}
	
	$daemonRow = mysql_fetch_object($daemonCountResult);
	if ($daemonRow->reccount <= 0)
	{
		returnError(201, 'The Daemon ID specified is not valid.', false);
		returnToMainPage();
		return;
	}
	
	// Gather the required fields
	$arrFields = array('name', 'description', 'logrec', 'basepath', 'daemon', 'storage', 'unrec', 'upload', 'work', 'appath', 'db_server', 'db_name', 'db_username', 'db_password');
	
	foreach ($arrFields as $field)
	{
		if (isset($_REQUEST[$field]) && !empty($_REQUEST[$field]))
		{
			if (substr($field, 0, 3) == 'db_')
			{
				$$field = mysql_real_escape_string($_REQUEST[$field]);
			}
			else
			{
				if (get_magic_quotes_gpc())
				{
					$$field = mysql_real_escape_string(stripslashes($_REQUEST[$field]), $adminLink);
				}
				else
				{
					$$field = mysql_real_escape_string($_REQUEST[$field]);
				}
			}
		}
		else
		{
			$$field = null;
		}
	}
	
	// Prepare the insertion query
	$query = "UPDATE `daemons` SET ";
	
	foreach ($arrFields as $field)
	{
		if (substr($field, 0, 3) != 'db_')
		{
			$query .= "`$field` = '" . $$field . "', ";
		}
	}
	
	// Trim the trailing comma from the string
	$query = substr($query, 0, -2);
	
	$query .= " WHERE id=$daemonid;";
	if (false === mysql_query($query, $adminLink))
	{
		returnError(902, $query, true, $adminLink);
	}
	else
	{
		returnMessage(1001, sprintf(LANGUAGE_MODIFY_OBJECT_NAME, 'properties for daemon', $name), true);
	}
	returnToMainPage();
}

function showProperties()
{
	global $adminLink, $module;
	
	// Ensure that a daemon id was provided
	if (!isset($_REQUEST['daemonid']) || empty($_REQUEST['daemonid']))
	{
		returnError(201, "A Daemon ID must be provided.", false);
		returnToMainPage();
		return;
	}
	else
	{
		$daemonid = (int)$_REQUEST['daemonid'];
	}
	
	// Gather data about the daemon from the database
	$query = "SELECT * FROM `daemons` WHERE id=$daemonid LIMIT 1;";
	if (false === ($daemonPropertiesResult = mysql_query($query, $adminLink)))
	{
		returnError(902, $query, true, $adminLink);
		exit();
	}
	
	if (!mysql_num_rows($daemonPropertiesResult))
	{
		// No entry was found matching the ID provided
		returnError(201, 'No matching daemon was found.', false);
		returnToMainPage();
		exit();
	}
	
	$propertiesRow = mysql_fetch_object($daemonPropertiesResult);
	
	returnMessage(1000, "Viewed properties for daemon '" . $propertiesRow->name . "'", false);
	
	?>
	<script type="text/javascript">
	function restoreDefaults()
	{
		document.getElementById('name').value = '<?php echo $propertiesRow->name; ?>';
		document.getElementById('description').value = '<?php echo $propertiesRow->description; ?>';
		document.getElementById('logrec').value = '<?php echo $propertiesRow->logrec; ?>';
		document.getElementById('basepath').value = '<?php echo $propertiesRow->basepath; ?>';
		document.getElementById('daemon').value = '<?php echo $propertiesRow->daemon; ?>';
		document.getElementById('storage').value = '<?php echo $propertiesRow->storage; ?>';
		document.getElementById('unrec').value = '<?php echo $propertiesRow->unrec; ?>';
		document.getElementById('upload').value = '<?php echo $propertiesRow->upload; ?>';
		document.getElementById('work').value = '<?php echo $propertiesRow->work; ?>';
		document.getElementById('appath').value = '<?php echo $propertiesRow->appath; ?>';
		document.getElementById('db_server').value = '<?php echo ADMINPANEL_DB_SERVER; ?>';
		document.getElementById('db_name').value = '<?php echo ADMINPANEL_DB_NAME; ?>';
		document.getElementById('db_username').value = '<?php echo ADMINPANEL_DB_USERNAME; ?>';
		document.getElementById('db_password').value = '<?php echo ADMINPANEL_DB_PASSWORD; ?>';
	}
	</script>
	<table class="contentTable">
	<tr class="table_titlebar">
		<td>Properties for <?php echo $propertiesRow->name; ?></td>
	</tr>
	<tr>
		<td>
		<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
		<label>Daemon name:</label>
		<?php echo $propertiesRow->appname; ?><br />
		<label>Display name:</label>
		<input type="text" class="textbox" name="name" id="name" value="<?php echo $propertiesRow->name; ?>" /><br />
		<label>Description:</label>
		<textarea name="description" id="description" rows="1" cols="40"><?php echo $propertiesRow->description; ?></textarea><br />
		<fieldset>
		<legend>Status</legend>
		<label>Daemon Status</label>
		<?php echo (!empty($propertiesRow->pid) && $propertiesRow->pid > 0) ? 'Started' : 'Stopped'; ?><br />
		<label>PID</label>
		<?php echo $propertiesRow->pid; ?><br />
		<label>Startup</label>
		<?php echo date('m/d/Y H:i:s', $propertiesRow->start); ?><br />
		<label>Up Time</label>
		<?php echo timeconverter(time() - $propertiesRow->start); ?><br />
		</fieldset>
		<fieldset>
		<legend>Log File Delivery</legend>
			<label>Log Type</label>
			<select name="logtype" id="logtype">
				<option value="0" <?php if ($propertiesRow->logtype == 0) echo "selected=\"selected\" "; ?>>No Update</option>
				<option value="1" <?php if ($propertiesRow->logtype == 1) echo "selected=\"selected\" "; ?>>Errors Only</option>
				<option value="2" <?php if ($propertiesRow->logtype == 2) echo "selected=\"selected\" "; ?>>File Completion Notification</option>
			</select>
			<label>Log Recipients</label>
			<textarea name="logrec" id="logrec" rows="1"><?php echo $propertiesRow->logrec; ?></textarea><br />
		</fieldset>
		<fieldset>
		<legend>Schedule</legend>
			<label>Always Working</label>
			<input type="checkbox" checked="checked" disabled="disabled" /><br />
			<label>Set Schedule</label>
			Start:<br />
			<label>&nbsp;</label>
			<select name="starttime" id="starttime" disabled="disabled">
			<?php
			for ($i=0; $i<48; $i++)
			{
				$hour = floor($i/2);
				if (0 == $i%2)
				{
					$minute = "00";
				}
				else
				{
					$minute= "30";
				}
				if ($hour == 0 && $minute == "00")
				{
					$timeString = "Midnight";
				}
				elseif ($hour == 12 && $minute == "00")
				{
					$timeString = "Noon";
				}
				else
				{
					if ($hour == 0)
					{
						$hour = "12"; // Adjust for the 59 minutes after midnight
					}
					$timeString = "$hour:$minute";
				}
				?><option value="<?php echo $timeString; ?>"><?php echo $timeString; ?></option>
				<?php
			}
			?>
			</select><br />
			<label>&nbsp;</label>
			End:<br />
			<label>&nbsp;</label>
			<select name="endtime" id="endtime" disabled="disabled">
			<?php
			for ($i=0; $i<48; $i++)
			{
				$hour = floor($i/2);
				if (0 == $i%2)
				{
					$minute = "00";
				}
				else
				{
					$minute= "30";
				}
				if ($hour == 0 && $minute == "00")
				{
					$timeString = "Midnight";
				}
				elseif ($hour == 12 && $minute == "00")
				{
					$timeString = "Noon";
				}
				else
				{
					if ($hour == 0)
					{
						$hour = "12"; // Adjust for the 59 minutes after midnight
					}
					$timeString = "$hour:$minute";
				}
				?><option value="<?php echo $timeString; ?>"><?php echo $timeString; ?></option>
				<?php
			}
			?>
			</select><br />
			
		</fieldset>
		<fieldset>
		<legend>System Path Configuration</legend>
			<label>Base pathinfo</label>
			<input type="text" class="textbox" style="width: 225px;" name="basepath" id="basepath" value="<?php echo $propertiesRow->basepath; ?>" /><br />
			<label>PID File</label>
			<input type="text" class="textbox" style="width: 225px;" name="daemon" id="daemon" value="<?php echo $propertiesRow->daemon; ?>" /><br />
			<label>Storage</label>
			<input type="text" class="textbox" style="width: 225px;" name="storage" id="storage" value="<?php echo $propertiesRow->storage; ?>" /><br />
			<label>Unknown Files</label>
			<input type="text" class="textbox" style="width: 225px;" name="unrec" id="unrec" value="<?php echo $propertiesRow->unrec; ?>" /><br />
			<label>Upload</label>
			<input type="text" class="textbox" style="width: 225px;" name="upload" id="upload" value="<?php echo $propertiesRow->upload; ?>" /><br />
			<label>Working</label>
			<input type="text" class="textbox" style="width: 225px;" name="work" id="work" value="<?php echo $propertiesRow->work; ?>" /><br />
			<label>Application</label>
			<input type="text" class="textbox" style="width: 225px;" name="appath" id="appath" value="<?php echo $propertiesRow->appath; ?>" /><br />
		</fieldset>
		<fieldset>
		<legend>Database Properties</legend>
		<label>Server</label>
		<input type="text" class="textbox" name="db_server" id="db_server" value="<?php echo ADMINPANEL_DB_SERVER; ?>" /><br />
		<label>Database</label>
		<input type="text" class="textbox" name="db_name" id="db_name" value="<?php echo ADMINPANEL_DB_NAME; ?>" /><br />
		<label>Username</label>
		<input type="text" class="textbox" name="db_username" id="db_username" value="<?php echo ADMINPANEL_DB_USERNAME; ?>" /><br />
		<label>Password</label>
		<input type="text" class="textbox" name="db_password" id="db_password" value="<?php echo ADMINPANEL_DB_PASSWORD; ?>" /><br />
		</fieldset>
		<table width="100%">
		<tr>
			<td align="center"><input type="button" value="Cancel" name="cancel" onclick="cancelForm('<?php echo ADMINPANEL_WEB_PATH; ?>', <?php echo $module; ?>);" /></td>
			<td align="center"><input type="button" value="Reset" name="reset" onclick="restoreDefaults();" /></td>
			<td align="center"><input type="submit" value="Submit" name="submit" /></td>
		</tr>
		</table>
		<input type="hidden" name="module" value="<?php echo $module; ?>" />
		<input type="hidden" name="task" value="submitProperties" />
		<input type="hidden" name="daemonid" value="<?php echo $daemonid; ?>" />		
		</form>
		</td>
	</tr>
	</table>
	<?php
}

function trimLastDirectory($directoryPath)
{
	if (strpos($directoryPath, '/') !== false)
	{
		$positionOfLastSlash = strrpos($directoryPath, '/');
		$directory = substr($directoryPath, 0, $positionOfLastSlash);
		if (!empty($directory))
		{
			return $directory;
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

function showTable()
{
	global $adminLink, $module, $moduleName, $adminDatabase;
	
	returnMessage(1000, LANGUAGE_VIEWED_MAIN_PAGE, false);
	
	include_once ADMINPANEL_APP_PATH . '/' . ADMINPANEL_MODULE_DIRECTORY . '/' . $moduleName . "/checkRunStatus.php";
	
	?>
	<table class="contentTable">
	<tr class="table_titlebar">
		<td>Global Policies</td>
	</tr>
	<tr>
		<td><span class="subheading">Registered Daemons</span>
		<table>
		<tr>
			<th>Name</th>
			<th>Description</th>
			<th>Status</th>
			<th>Last Run</th>
		</tr>
		<?php
		$arrDaemonNames = array('Dataloader', 'LinkShare Monitor');
		
		$adminDatabase->query("SELECT `name`, `description`, `pidfile_keyname`, `scriptname` FROM `daemons2`;");
		
		if ($adminDatabase->rowCount() > 0)
		{
			foreach ($adminDatabase->objects() as $daemon)
			{
				?>
			<tr>
				<td><?php echo $daemon->name; ?></td>
				<td><?php echo $daemon->description; ?></td>
				<td><?php
				// Determine whether to show the daemon as active or inactive
				$keyname = $daemon->pidfile_keyname;
				eval("\$keyvalue = $keyname;");
				$daemonStatus = checkRunStatus($daemon->scriptname, GLOBAL_PID_DIRECTORY . $keyvalue);
				unset($keyname);
				
				if ($daemonStatus == 0)
				{
					// Daemon is running
					$daemonOnButtonString = "<img src=\"" . ADMINPANEL_WEB_PATH . "/images/upa.gif\" />";
					
					$daemonOffButton = ADMINPANEL_WEB_PATH . "/images/dob.gif";
					
					if (isPermitted('edit', $module))
					{
						$daemonOffButtonString = "<a href=\"#\" onclick=\"toggleDaemon('" . $daemon->name . "', 'off', '" . ADMINPANEL_WEB_PATH . "'); return false;\"><img src=\"$daemonOffButton\" /></a>";
					}
					else
					{
						$daemonOffButtonString = "<img src=\"$daemonOffButton\" />";
					}
				}
				else
				{
					$daemonOnButton = ADMINPANEL_WEB_PATH . "/images/upb.gif";
					
					if (isPermitted('edit', $module))
					{
						$daemonOnButtonString = "<a href=\"#\" onclick=\"toggleDaemon('" . $daemon->name . "', 'on', '" . ADMINPANEL_WEB_PATH . "'); return false;\"><img src=\"$daemonOnButton\" /></a>";
					}
					else
					{
						$daemonOnButtonString = "<img src=\"$daemonOnButton\" />";
					}
					$daemonOffButtonString = "<img src=\"" . ADMINPANEL_WEB_PATH . "/images/doa.gif\" />";
				}
				?><!-- Status: <?php echo $daemonStatus; ?>, keyvalue: <?php echo $keyvalue; unset($keyvalue); ?> --><span class="daemon_onbutton">
						<?php echo $daemonOnButtonString; ?>
					</span>
					<span class="daemon_offbutton">
						<?php echo $daemonOffButtonString; ?>
					</span>
				</td>
				<td><?php echo lastRun($daemon->scriptname)?></td>
			</tr>
				<?php
			}
		}
	?>
		</table>
		</td>
	</tr>
	<!-- Disabled because the data is currently unused.
	<tr>
		<td><span class="subheading">Global Mapping</span>
		<table>
			<tr>
				<th>File Generator</th>
				<th># of Tables</th>
				<th>Last Modified</th>
			</tr>
	<?php
		$arrFeedSources = array('CJ', 'LinkShare', 'Performics');
		
		foreach ($arrFeedSources as $feedSource)
		{
			?>
			<tr>
				<td><?php echo $feedSource; ?></td>
				<td class="td_number"><blink>TODO: Insert number</blink></td>
				<td><blink>TODO: Insert Date</blink></td>
			</tr>	
			<?php
		}
	?>
		</table>
		</td>
	</tr>-->
	<tr>
		<td><span class="subheading">Database File System</span><br />
		<?php
			
			$databaseDirectory = trim(GLOBAL_DATABASE_DIRECTORY);
			if (!is_readable($databaseDirectory))
			{
				while (!is_readable($databaseDirectory))
				{
					// Loop until the directory is readable, or until we trim off everything.
					$databaseDirectory = trimLastDirectory($databaseDirectory);
					if (empty($databaseDirectory) || false === $databaseDirectory)
					{
						returnError(104, 'The specified directory is unreadable');
						break;
					}
				}
			}
			
			$totalSpace = disk_total_space($databaseDirectory);
			$freeSpace = disk_free_space($databaseDirectory);
			$usedSpace = $totalSpace - $freeSpace;
		?>
			<table class="fsspace">
			<tr>
				<td><img src="<?php echo ADMINPANEL_WEB_PATH; ?>/images/piecharts/used.gif" alt="Legend: Used Space" /></td>
				<td>Used Space</td>
				<td><?php echo number_format($usedSpace); ?> bytes</td>
				<td><?php echo round((($usedSpace/1024)/1024)/1024, 2); ?> GB</td>
			</tr>
			<tr>
				<td><img src="<?php echo ADMINPANEL_WEB_PATH; ?>/images/piecharts/free.gif" alt="Legend: Free Space" /></td>
				<td>Free Space</td>
				<td><?php echo number_format($freeSpace); ?> bytes</td>
				<td><?php echo round((($freeSpace/1024)/1024)/1024, 2); ?> GB</td>
			</tr>
			<tr>
				<td></td>
				<td>Capacity</td>
				<td><?php echo number_format($totalSpace); ?> bytes</td>
				<td><?php echo round((($totalSpace/1024)/1024)/1024, 2); ?> GB</td>
			</tr>
			<tr>
				<td colspan="4" style="text-align: center";>
				<img src="<?php echo ADMINPANEL_WEB_PATH; ?>/images/piecharts/<?php echo round(100*($usedSpace/$totalSpace), 0); ?>.gif" alt="Graph: <?php echo round(100*($usedSpace/$totalSpace),0); ?>%" />
				</td>
			</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td><span class="subheading">Feed Storage File System</span>
		<?php
		
			$datafeedDirectory = GLOBAL_APP_PATH;
			if (!is_readable($datafeedDirectory))
			{
				while (!is_readable($datafeedDirectory))
				{
					// Loop until the directory is readable, or until we trim off everything.
					$datafeedDirectory = trimLastDirectory($datafeedDirectory);
					if (empty($datafeedDirectory) || false === $datafeedDirectory)
					{
						$totalSpace = 0;
						$freeSpace = 0;
						returnError(104, 'The specified directory is unreadable');
						break;
					}
				}
			}
			$totalSpace = disk_total_space($datafeedDirectory);
			$freeSpace = disk_free_space($datafeedDirectory);
			$usedSpace = $totalSpace - $freeSpace;
		?>
			<table class="fsspace">
			<tr>
				<td><img src="<?php echo ADMINPANEL_WEB_PATH; ?>/images/piecharts/used.gif" alt="Legend: Used Space" /></td>
				<td>Used Space</td>
				<td><?php echo number_format($usedSpace); ?> bytes</td>
				<td><?php echo number_format((($usedSpace/1024)/1024)/1024, 2); ?> GB</td>
			</tr>
			<tr>
				<td><img src="<?php echo ADMINPANEL_WEB_PATH; ?>/images/piecharts/free.gif" alt="Legend: Free Space" /></td>
				<td>Free Space</td>
				<td><?php echo number_format($freeSpace); ?> bytes</td>
				<td><?php echo number_format((($freeSpace/1024)/1024)/1024, 2); ?> GB</td>
			</tr>
			<tr>
				<td></td>
				<td>Capacity</td>
				<td><?php echo number_format($totalSpace); ?> bytes</td>
				<td><?php echo number_format((($totalSpace/1024)/1024)/1024, 2); ?> GB</td>
			</tr>
			<tr>
				<td colspan="4" style="text-align: center";>
				<img src="<?php echo ADMINPANEL_WEB_PATH; ?>/images/piecharts/<?php echo round(100*($usedSpace/$totalSpace), 0); ?>.gif" alt="Graph: <?php echo round(100*($usedSpace/$totalSpace),0); ?>%" />
				</td>
			</tr>
			</table>
		</td>
	</tr>
	</table>	
	<?php
}

function lastRun($scriptname)
{
    global $feedDatabase;
    switch ($scriptname)
    {
        case 'dataloader.php':
            $feedDatabase->query("SELECT `timestamp` FROM `status_reports` WHERE `processName` = 'dataloader' ORDER BY `timestamp` DESC LIMIT 1");
            $timestamp = $feedDatabase->firstField();
            return date('Y-m-d H:i:s', $timestamp);
            break;
        case 'linkshare_retriever.php':
            $feedDatabase->query("SELECT `end_time` FROM `stats_linkshareretriever` ORDER BY `end_time` DESC LIMIT 1");
            return $feedDatabase->firstField();
            break;
        case 'feedFieldMap.php':
            $feedDatabase->query("SELECT `end_time` FROM `stats_feedfieldmap` ORDER BY `end_time` DESC LIMIT 1");
            return $feedDatabase->firstField();
            break;
        default:
            return "";
    };
}

function timeconverter( $vartotal )
{
	$varday = floor( $vartotal / 86400 );
	$varhour = floor( ( $vartotal - $varday * 86400 ) / 3600 );
	$varmin = floor( ( $vartotal - ( ( $varday * 86400 ) + ( $varhour * 3600 ) ) ) / 60 );
	$varsec = $vartotal - ( ( $varday * 86400 ) + ( $varhour * 3600 ) + ( $varmin * 60 ) );
	
	$varresult = "";
	$varcomma = false;
	if ( $varday == 1 )
	{
		$varresult.= $varday." day";
		$varcomma = true;
	}
	elseif ( $varday > 1 )
	{
		$varresult.= $varday." days";
		$varcomma = true;
	}
	
	if ( $varcomma == true )
	{
		$varresult.= ", ";
	}
	if ( $varhour == 1 )
	{
		$varresult.= $varhour." hour";
		$varcomma = true;
	}
	elseif ( $varhour > 1 )
	{
		$varresult.= $varhour." hours";
		$varcomma = true;
}

	if ( $varcomma == true )
	{
		$varresult.= ", ";
	}
	if ( $varmin == 1 )
	{
		$varresult.= $varmin." min";
		$varcomma = true;
	}
	elseif ( $varmin > 1 )
	{
		$varresult.= $varmin." mins";
		$varcomma = true;
	}
	if ( $varcomma == true )
	{
		$varresult.= ", ";
	}
	if ( $varsec == 1 )
	{
		$varresult.= $varsec." sec";
	}
	elseif ( $varsec > 1 )
	{
		$varresult.= $varsec." secs";
	}

    return $varresult;
}
?>
