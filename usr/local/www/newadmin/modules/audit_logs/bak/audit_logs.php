<?php

// Make sure this script is not called directly
if (!defined('APP_NAME'))
{
	die("This script cannot be accessed directly.");
}

$task = getTask();

// Determine if a record year was specified. Only events from that year will be loaded
$eventsFromYear = false;
if (array_key_exists('year', $_REQUEST) && is_numeric($_REQUEST['year']) && intval($_REQUEST['year']) > 2000)
{
	$eventsFromYear = intval($_REQUEST['year']);
}

switch ($task)
{
	case 'logsByUser':
		logsByUser();
		break;
	case 'logsByDate':
		logsByDate();
		break;
	case 'logsByModule':
		logsByModule();
		break;
	case 'advancedLogSearch';
		advancedLogSearch();
		break;
	case 'submitAdvancedLogSearch';
		submitAdvancedLogSearch();
		break;
	case 'reportGenerator':
		reportGenerator();
		break;
	case 'logMaintenance':
		logMaintenance();
		break;
	default:
		showTable();
		break;
}

function logsByUser()
{
	global $eventsFromYear;
	
	// Determine if a specific order was requested
	// This will be passed to demoFramelessNodes.php for appropriate ordering
	if (isset($_REQUEST['order']))
	{
		$orderRequest = $_REQUEST['order'];
		switch ($orderRequest)
		{
			case 'usernameR':
				$orderString = 'ORDER BY username DESC';
				break;
			case 'uid':
				$orderString = 'ORDER BY id ASC';
				break;
			case 'uidR':
				$orderString = 'ORDER BY id DESC';
				break;
			case 'username':
			default:
				$orderString = 'ORDER BY username ASC';
				break;
		}
	}
	else
	{
		$orderString = 'ORDER BY username ASC';
	}
	?>
	<script src="<?php echo ADMINPANEL_WEB_PATH; ?>/js/tv/ua.js"></script>
	<!-- Infrastructure code for the TreeView. DO NOT REMOVE.   -->
	<script src="<?php echo ADMINPANEL_WEB_PATH; ?>/js/tv/ftiens4.js"></script>
	<!-- Scripts that define the tree. DO NOT REMOVE.           -->
	<script src="<?php echo ADMINPANEL_WEB_PATH; ?>/js/tv/treeviewLogsByUser.php?orderstring=<?php echo $orderString; ?>&year=<?php echo $eventsFromYear; ?>"></script>

	<div id="treeDiv" style="height: <?php echo $_SESSION['page_height'] - 30; ?>px; overflow: auto;">
	<table border=0>
		<tr>
			<td>
				<font size=-2><a style="font-size:7pt;text-decoration:none;color:silver; display: none;" href="http://www.treemenu.net/" target="_blank">Javascript Tree Menu</a></font>
			</td>
		</tr>
	</table>
	&nbsp;

	<span class=TreeviewSpanArea>
		<script>initializeDocument()</script>
	</span>
	</div>
	<div id="logEntryDiv">
	&nbsp;
	</div>
	<?php
}

function logsByDate()
{
	global $eventsFromYear;
	
	// Determine if a specific order was requested
	// This will be passed to demoFramelessNodes.php for appropriate ordering
	if (isset($_REQUEST['order']))
	{
		$orderRequest = $_REQUEST['order'];
		switch ($orderRequest)
		{
			case 'ASC':
				$orderString = 'ASC';
				break;
			case 'DESC':
			default:
				$orderString = 'DESC';
				break;
		}
	}
	else
	{
		$orderString = 'DESC';
	}
	?>
		<script src="<?php echo ADMINPANEL_WEB_PATH; ?>/js/tv/ua.js"></script>
	<!-- Infrastructure code for the TreeView. DO NOT REMOVE.   -->
	<script src="<?php echo ADMINPANEL_WEB_PATH; ?>/js/tv/ftiens4.js"></script>
	<!-- Scripts that define the tree. DO NOT REMOVE.           -->
	<script src="<?php echo ADMINPANEL_WEB_PATH; ?>/js/tv/treeviewLogsByDate.php?orderstring=<?php echo $orderString; ?>&year=<?php echo $eventsFromYear; ?>"></script>

	<div id="treeDiv" style="height: <?php echo $_SESSION['page_height'] - 30; ?>px; overflow: auto;">
	<table border=0>
		<tr>
			<td>
				<font size=-2><a style="font-size:7pt;text-decoration:none;color:silver; display: none;" href="http://www.treemenu.net/" target="_blank">Javascript Tree Menu</a></font>
			</td>
		</tr>
	</table>
	&nbsp;

	<span class=TreeviewSpanArea>
		<script>initializeDocument()</script>
	</span>
	</div>
	<div id="logEntryDiv">
	&nbsp;
	</div>
	<?php
}

function logsByModule()
{
	global $eventsFromYear;
	
	// Determine if a specific order was requested
	// This will be passed to demoFramelessNodes.php for appropriate ordering
	if (isset($_REQUEST['order']))
	{
		$orderRequest = $_REQUEST['order'];
		switch ($orderRequest)
		{
			case 'ASC':
				$orderString = 'ASC';
				break;
			case 'DESC':
			default:
				$orderString = 'DESC';
				break;
		}
	}
	else
	{
		$orderString = 'DESC';
	}
	?>
	<script src="<?php echo ADMINPANEL_WEB_PATH; ?>/js/tv/ua.js"></script>
	<!-- Infrastructure code for the TreeView. DO NOT REMOVE.   -->
	<script src="<?php echo ADMINPANEL_WEB_PATH; ?>/js/tv/ftiens4.js"></script>
	<!-- Scripts that define the tree. DO NOT REMOVE.           -->
	<script src="<?php echo ADMINPANEL_WEB_PATH; ?>/js/tv/treeviewLogsByModule.php?orderstring=<?php echo $orderString; ?>&year=<?php echo $eventsFromYear; ?>"></script>

	<div id="treeDiv" style="height: <?php echo $_SESSION['page_height'] - 30; ?>px; overflow: auto;">
	<table border=0>
		<tr>
			<td>
				<font size=-2><a style="font-size:7pt;text-decoration:none;color:silver; display: none;" href="http://www.treemenu.net/" target="_blank">Javascript Tree Menu</a></font>
			</td>
		</tr>
	</table>
	&nbsp;

	<span class=TreeviewSpanArea>
		<script>initializeDocument()</script>
	</span>
	</div>
	<div id="logEntryDiv">
	&nbsp;
	</div>
	<?php
}

function advancedLogSearch()
{
	global $adminLink, $module;
	
	?>
	<table class="contentTable">
		<tr class="table_titlebar">
			<td>Advanced Log Search</td>
		</tr>
		<tr>
			<td>
				<form action="<?php echo ADMINPANEL_WEB_PATH; ?>/index.php" method="post">
					<label>Search Term</label>
					<input type="text" class="textbox" name="search_term" id="search_term" /><br />
					<label>Which account(s)</label>
					<select name="uid[]" class="textbox" multiple="multiple" size="4">
						<option value="-1" selected="selected">All Users</option>
					<?php
					
					// Get a list of all available users
					$query = "SELECT id, username, firstName, lastName FROM users ORDER BY lastName ASC, firstName ASC;";
					if (false == ($usersResult = mysql_query($query, $adminLink)))
					{
						returnError(902, $query, 'true', $adminLink);
					}
					
					while (false !== ($usersRow = mysql_fetch_object($usersResult)))
					{
						?><option value="<?php echo $usersRow->id; ?>"><?php echo $usersRow->firstName . " " . $usersRow->lastName; ?>&nbsp;(<?php echo $usersRow->username; ?>)</option>
						<?php
					}
					?>
					</select><br />
					<label>Date range</label><br />
					<div style="width: 225px; margin-left: 135px;">
					<input type="radio" name="date_range" onchange="toggleDateFields();" value="any" checked="checked" />Any time<br />
					<input type="radio" name="date_range" onchange="toggleDateFields();" value="lastweek" />Within the last week<br />
					<input type="radio" name="date_range" onchange="toggleDateFields();" value="lastmonth" />Within the last month<br />
					<input type="radio" name="date_range" onchange="toggleDateFields();" value="lastyear" />Within the last year<br />
					<input type="radio" name="date_range" id="date_range_specific" value="specific_dates" onchange="toggleDateFields();" />Specify dates:<br />
					<label style="text-align: left">From:</label>
					<select class="textbox" name="from_month" id="from_month" disabled="disabled">
						<?php for ($i=1; $i<13; $i++)
						{
							?><option value="<?php echo $i; ?>"><?php echo date('F', mktime(1, 1, 1, $i, 1, date('Y'))); ?></option>
							<?php
						}
						?>
					</select>
					<select class="textbox" name="from_day" id="from_day" disabled="disabled">
						<?php for($i=1; $i<32; $i++)
						{
							?><option value="<?php echo $i; ?>"><?php echo $i; ?></option>
							<?php
						}
						?>
					</select>
					<select class="textbox" name="from_year" id="from_year" disabled="disabled">
						<?php for ($i=2007; $i<2010; $i++)
						{
							?><option value="<?php echo $i; ?>"><?php echo $i; ?></option>
							<?php
						}
						?>
					</select><br />
					<label style="text-align: left">To:</label>
					<select class="textbox" name="to_month" id="to_month" disabled="disabled">
						<?php for ($i=1; $i<13; $i++)
						{
							?><option value="<?php echo $i; ?>"><?php echo date('F', mktime(1, 1, 1, $i, 1, date('Y'))); ?></option>
							<?php
						}
						?>
					</select>
					<select class="textbox" name="to_day" id="to_day" disabled="disabled">
						<?php for($i=1; $i<32; $i++)
						{
							?><option value="<?php echo $i; ?>"><?php echo $i; ?></option>
							<?php
						}
						?>
					</select>
					<select class="textbox" name="to_year" id="to_year" disabled="disabled">
						<?php for ($i=2007; $i<2010; $i++)
						{
							?><option value="<?php echo $i; ?>"><?php echo $i; ?></option>
							<?php
						}
						?>
					</select></div><br style="clear: both;"/>
					<label>Sort Results By</label>
					<div style="width: 225px; margin-left: 135px;">
					<input type="radio" name="sort_by" value="date" checked="checked" />Date<br />
					<input type="radio" name="sort_by" value="user" disabled="disabled" />User (IP)<br />
					<input type="radio" name="sort_by" value="module" disabled="disabled" />Module (IP)<br />
					</div>
					<label>&nbsp;</label>
					<input type="hidden" name="module" value="<?php echo $module; ?>" />
					<input type="hidden" name="task" value="submitAdvancedLogSearch" />
					<input type="submit" value="Search" />
				</form>
			</td>
		</tr>
	</table>
	<?php
}

function submitAdvancedLogSearch()
{	
	// Gather the required fields from the form
	$searchTerm = $_REQUEST['search_term'];
	$arrUIDs = $_REQUEST['uid'];
	
	$dateRange = $_REQUEST['date_range'];
	
	if ($dateRange == 'specific_dates')
	{
		$fromMonth = $_REQUEST['from_month'];
		$fromDay = $_REQUEST['from_day'];
		$fromYear = $_REQUEST['from_year'];
		$toMonth = $_REQUEST['to_month'];
		$toDay = $_REQUEST['to_day'];
		$toYear = $_REQUEST['to_year'];
	}
	
	// Ensure that search criteria has been entered
	if (empty($searchTerm) && count($arrUIDs) == 1 && $arrUIDs[0] == '-1' && $dateRange == 'any')
	{
		$_SESSION['sysmessage'] = "Please enter a search term, or select users and/or date ranges for the records you require.";
		$_SESSION['sysmtype'] = "error";
		
		advancedLogSearch();
		return;
	}
	else
	{
		if (count($arrUIDs) >= 1 && $arrUIDs[0] != '-1')
		{
			// One or more users was selected, and "All Users" was not selected
			
			$uidString = '(';
			foreach ($arrUIDs as $theUID)
			{
				$uidString .= " user_id=$theUID OR";
			}
			
			// Cut off the last ' OR'
			$uidString = substr($uidString, 0, -3) . ")";
		}
		else
		{
			$uidString = '';
		}
	}
	
	
	// Begin to assemble the query:
	$query = "SELECT id FROM log WHERE";
	$queryUnchanged = true;
	
	$searchString = '';
	if (!empty($searchTerm))
	{
		$searchString .= " (`task` LIKE '%" . addslashes($searchTerm) . "%' OR `note` LIKE '%" . addslashes($searchTerm) . "%') ";
		$queryUnchanged = false;
	}
	
	$query .= $searchString;
	
	if (isset($uidString) && !empty($uidString))
	{
		if ($queryUnchanged)
		{
			// Nothing has been added to the query yet
			$query .= " $uidString ";
		}
		else
		{
			$query .= " AND $uidString ";
		}
		$queryUnchanged = false;
	}

	switch ($dateRange)
	{
		case 'lastweek':
			$endTime = time();
			$startTime = $endTime - (7 * 24 * 60 * 60);
			break;
		case 'lastmonth':
			$endTime = time();
			$startTime = $endTime - (30 * 24 * 60 * 60);
			break;
		case 'lastyear':
			$endTime = time();
			$startTime = $endTime - (365 * 24 * 60 * 60);
			break;
		case 'specific_dates':
			// Assemble the dates from the form fields
			$endTime = strtotime("$toYear-$toMonth-$toDay 00:00:00");
			$startTime = strtotime("$fromYear-$fromMonth-$fromDay 00:00:00");
			break;
		case 'any':
		default:
			// No date is specified
			$endTime = false;
			$startTime = false;
			break;
	}
	
	if ($endTime !== false)
	{
		// Create the date-range statement
		if ($endTime < $startTime)
		{
			// The user selected the dates backwards, so swap them
			$tempTime = $endTime;
			$endTime = $startTime;
			$startTime = $tempTime;
		}
		$timeString = "(`date_time` >= '" . date('Y-m-d H:i:s', $startTime) . "' AND `date_time` <= '" . date('Y-m-d H:i:s', $endTime) . "')";
	}
	else
	{
		$timeString = '';
	}


	if (!empty($timeString))
	{
		if ($queryUnchanged)
		{
			// Nothing has been added to the query yet
			$query .= " $timeString ";
		}
		else
		{
			$query .= " AND $timeString ";
		}
	}
	//$query .= ";";
	
	// Now include the files to make the TreeView layout
	?>
		<script src="<?php echo ADMINPANEL_WEB_PATH; ?>/js/tv/ua.js"></script>
	<!-- Infrastructure code for the TreeView. DO NOT REMOVE.   -->
	<script src="<?php echo ADMINPANEL_WEB_PATH; ?>/js/tv/ftiens4.js"></script>
	<!-- Scripts that define the tree. DO NOT REMOVE.           -->
	<script src="<?php echo ADMINPANEL_WEB_PATH; ?>/js/tv/treeviewSearchLogsByDate.php?query=<?php echo urlencode($query); ?>&year=<?php echo '2007'; ?>"></script>

	<div id="treeDiv" style="height: <?php echo $_SESSION['page_height'] - 30; ?>px; overflow: auto;">
	<table border=0>
		<tr>
			<td>
				<font size=-2><a style="font-size:7pt;text-decoration:none;color:silver; display: none;" href="http://www.treemenu.net/" target="_blank">Javascript Tree Menu</a></font>
			</td>
		</tr>
	</table>
	&nbsp;

	<span class=TreeviewSpanArea>
		<script>initializeDocument()</script>
	</span>
	</div>
	<div id="logEntryDiv">
	&nbsp;
	</div>
	<?php
	
	return;
}

function reportGenerator()
{
	notImplemented('reportGenerator()');
	return;
}

function logMaintenance()
{
	notImplemented('logMaintenance()');
	return;
}
function notImplemented($function)
{
	$_SESSION['sysmessage'] = "The function '$function' is a work in progress.";
	$_SESSION['sysmtype'] = 'info';
	returnToMainPage();
}

function showTable()
{
	global $module;
	?>
	<table class="contentTable">
		<tr>
			<td>
			<div style="background-image: url('<?php echo ADMINPANEL_WEB_PATH; ?>/images/icons/user.gif');" onclick="openTask('logsByUser', '<?php echo ADMINPANEL_WEB_PATH; ?>', <?php echo $module; ?>);">
				<span class="div_titlebar">&nbsp;Organized By User</span>
				<p>Use this type of searching to locate log files organized by user.</p>
			</div>
			</td>
			<td>&nbsp;</td>
			<td>
			<div style="background-image: url('<?php echo ADMINPANEL_WEB_PATH; ?>/images/icons/basic.gif');" onclick="openTask('advancedLogSearch', '<?php echo ADMINPANEL_WEB_PATH; ?>', <?php echo $module; ?>);">
				<span class="div_titlebar">&nbsp;Advanced Log Search</span>
				<p>Use the basic search for quick searching and general results.</p>
			</div>
			</td>
		</tr>
		<tr>
			<td>
			<div style="background-image: url('<?php echo ADMINPANEL_WEB_PATH; ?>/images/icons/date.gif');" onclick="openTask('logsByDate', '<?php echo ADMINPANEL_WEB_PATH; ?>', <?php echo $module; ?>);">
				<span class="div_titlebar">&nbsp;Organized By Date</span>
				<p>Use this type of searching to locate log files by date and time</p>
			</div>
			</td>
			<td>&nbsp;</td>
			<td>
			<div style="background-image: url('<?php echo ADMINPANEL_WEB_PATH; ?>/images/icons/report.gif');" onclick="openTask('reportGenerator', '<?php echo ADMINPANEL_WEB_PATH; ?>', <?php echo $module; ?>);">
				<span class="div_titlebar">&nbsp;Report Generator</span>
				<p>Use this function to generate reports for printing or saving.</p>
			</div>
			</td>
		</tr>
		<tr>
			<td>
			<div style="background-image: url('<?php echo ADMINPANEL_WEB_PATH; ?>/images/icons/location.gif');" onclick="openTask('logsByModule', '<?php echo ADMINPANEL_WEB_PATH; ?>', <?php echo $module; ?>);">
				<span class="div_titlebar">&nbsp;Organized By Module</span>
				<p>Use this type of searching to locate log files by module.</p>
			</div>
			</td>
			<td>&nbsp;</td>
			<td>
			<div style="background-image: url('<?php echo ADMINPANEL_WEB_PATH; ?>/images/icons/maint.gif');" onclick="openTask('logMaintenance', '<?php echo ADMINPANEL_WEB_PATH; ?>', <?php echo $module; ?>);">
				<span class="div_titlebar">&nbsp;Log File Maintenance</span>
				<p>For log file system maintenance use this selection.</p>
			</div>
			</td>
		</tr>
	</table>
	<?php
}