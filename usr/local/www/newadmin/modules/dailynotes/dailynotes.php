<?php

// Make sure this script is not called directly
if (!defined('APP_NAME'))
{
	die("This script cannot be accessed directly.");
}

$task = getTask();

switch ($task)
{
	case 'deleteSubmit':
		deleteNote();
		break;
	case 'saveNew':
		gatherFormFields('new');
		break;
	case 'saveEdit':
		gatherFormFields('edit');
		break;
	default:
		showTable();
		break;
}

function deleteNote()
{
	global $dealhuntingDatabase, $module;
	
	if (!isPermitted('delete', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, false);
		returnToMainPage(getStartPage());
		return;
	}
	
	if (!isset($_GET['noteid']) || intval($_GET['noteid']) < 1)
	{
		returnError(201, "A note ID must be provided.", false);
		returnToMainPage(getStartPage());
		return;
	}
	else
	{
		$id = (int)$_GET['noteid'];
		
		$query = "DELETE FROM " . DEALHUNTING_DAILYNOTES_TABLE . " WHERE `id`=$id LIMIT 1;";
		$dealhuntingDatabase->query($query, false);
		
		if (true === $dealhuntingDatabase->error)
		{
			returnError(902, $query, false, $dealhuntingDatabase);
			returnToMainPage(getStartPage());
			return;
		}
		else
		{
			returnMessage(1101, sprintf(LANGUAGE_DELETE_OBJECT_ID, 'daily note', $id), false);
			returnToMainPage(getStartPage());
			return;
		}
	}
}

function gatherFormFields($submitType)
{
	// This function will gather and sanitize the form fields,
	// then pass the data long to the appropriate function for
	// editing or insertion
	
	$arrFields = array(); // To hold the sanitized values
	
	// Field: ID
	if (isset($_POST['noteid']))
	{
		$id = intval($_POST['noteid']);
	}
	else
	{
		$submitType = 'new';	// Even if this was submitted as an edit, if no noteid was provided, create a new record.
	}
	
	// Field: note
	if (isset($_POST['edit_note']))
	{
		$arrFields['note'] = mysql_real_escape_string($_POST['edit_note']);
	}
	else
	{
		$arrFields['note'] = '';
	}
	
	// Field: showdate
	if (isset($_POST['edit_showdate']))
	{
		$arrFields['showdate'] = date('Y-m-d H:i:s', strtotime($_POST['edit_showdate']));
	}
	else
	{
		$arrFields['showdate'] = null;
	}
	
	// Field: top
	if (isset($_POST['edit_top']) && intval($_POST['edit_top']) == 1)
	{
		$arrFields['top'] = 1;
	}
	else
	{
		$arrFields['top'] = 0;
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

function saveNew($arrFields)
{
	global $dealhuntingDatabase, $module;
	
	if (!isPermitted('create', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, false);
		returnToMainPage();
		return 0;
	}
	
	// Generate the MySQL insert query from the array
	$query = "INSERT INTO " . DEALHUNTING_DAILYNOTES_TABLE
		. " (`top`, `note`, `showdate`) VALUES ("
		. " "  . $arrFields['top'] . ","
		. " '" . $arrFields['note'] . "',"
		. " '" . $arrFields['showdate'] . "'"
		. ");";

	$dealhuntingDatabase->query($query);
	
	returnMessage(1002, sprintf(LANGUAGE_CREATE_OBJECT_NAME, 'daily note', $arrFields['note']), false);
	returnToMainPage();
	exit();
}

function saveEdit($arrFields, $id)
{
	global $dealhuntingDatabase, $module;
	
	if (!isPermitted('edit', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, false);
		returnToMainPage();
		return 0;
	}
	
	// Generate the MySQL update query
	$query = "UPDATE " . DEALHUNTING_DAILYNOTES_TABLE . " SET "
		. " `top`=" . $arrFields['top'] . ","
		. " `note`='" . $arrFields['note'] . "',"
		. " `showdate`='" . $arrFields['showdate'] . "'"
		. " WHERE `id`=$id LIMIT 1;";
	
	$dealhuntingDatabase->query($query);
	
	returnMessage(1001, sprintf(LANGUAGE_MODIFY_OBJECT_NAME, 'daily note', $arrFields['note']), false);
	returnToMainPage();
}

function showTable()
{
	global $dealhuntingDatabase, $module, $moduleName;
	
	returnMessage(1000, LANGUAGE_VIEWED_MAIN_PAGE, false);
	
	// Create the legend
	include "modules/includes/legend.class.php";
	$legend = new Legend(array(
		array('good','Good'),
		array('waiting','Upcoming'),
		array('expired','Expired')
	));
	echo $legend->create();
	
	if (isset($_REQUEST['paging_orderby']) && !empty($_REQUEST['paging_orderby']))
	{
		switch ($_REQUEST['paging_orderby'])
		{
			default:
				$orderbyString = "ORDER BY showdate DESC";
				break;
		}
	}
	else
	{
		$orderbyString = "ORDER BY showdate DESC";
	}
	
	// Get a count of all available daily notes
	$rowCountQuery = "SELECT COUNT(`id`) as `count` FROM `" . DEALHUNTING_DAILYNOTES_TABLE . "`;";
	$dealhuntingDatabase->query($rowCountQuery);
	
	$rowCount = $dealhuntingDatabase->firstField();
	
	// Include the pagination class
	include "includes/pagination.class.php";
	
	$paginator = new Pagination($module, $rowCount);
	
	$query = "SELECT "
		. " `id`, `top`, `note`, `showdate`"
		. " FROM " . DEALHUNTING_DAILYNOTES_TABLE
		. " $orderbyString " . $paginator->getLimitString();
	
	$dealhuntingDatabase->query($query);
	
	// Include the table-creation class
	include_once "includes/pageContentTable.class.php";
	
	foreach ($dealhuntingDatabase->objects() as $row)
	{
		$actionButtons = null;
		if (isPermitted('edit', $module))
		{
			$actionButtons .= "<a href=\"#\" onclick=\"showEditDiv(" . $row->id . ", '" . ADMINPANEL_WEB_PATH . "', '$moduleName', '" . $_SERVER['PHP_SELF'] . "', $module);\"><img src=\"" .  ADMINPANEL_WEB_PATH . "/images/edit.png\" alt=\"Edit\" /></a>";
		}
		if (isPermitted('delete', $module))
		{
			$actionButtons .= "<a href=\"#\" onclick=\"confirmDailynoteDelete('" . date('m/d/Y', strtotime($row->showdate)) . "', " . $row->id . ", '" . ADMINPANEL_WEB_PATH . "', '$module');\"><img src=\"" . ADMINPANEL_WEB_PATH. "/images/delete.png\" alt=\"Delete\" /></a>";
		}
		if ($row->top == 1)
		{
			$top = 'Yes';
		}
		else
		{
			$top = 'No';
		}
		if (strtotime("Today") > strtotime($row->showdate))
		{
			$rowModifier = "class=\"legend_expired\"";
		}
		elseif (strtotime("Tomorrow") <= strtotime($row->showdate))
		{
			$rowModifier = "class=\"legend_waiting\"";
		}
		else
		{
			$rowModifier = "class=\"legend_good\"";
		}
		if (!empty($actionButtons))
		{
			$arrListOfColumns = array('Action', 'Show Date', 'Note', 'Top');
			$arrDataRows[] = array($rowModifier, array(
				array("class=\"action_buttons\"", $actionButtons),
				array('', date('m/d/Y', strtotime($row->showdate))),
				array('', $row->note),
				array('', $top)
			));
		}
		else
		{
			$arrListOfColumns = array('Show Date', 'Note', 'Top');
			$arrDataRows[] = array($rowModifier, array(
				array('', date('m/d/Y', strtotime($row->showdate))),
				array('', $row->note),
				array('', $top)
			));
		}
	}
	
	// Create the table action
	if (isPermitted('create', $module))
	{
		$action = "showNewDiv('" . ADMINPANEL_WEB_PATH . "', '" . $moduleName . "', '" . $_SERVER['PHP_SELF'] . "', " . $module . ");";
	}
	else
	{
		$action = '';
	}
	
	$arrAddendum = array(
	
		$paginator->generateLinkBar()
	);
	
	// Create the table
	$theTable = new pageContentTable('Daily Notes Administration', $arrListOfColumns, 'Create Note', $action, $arrDataRows, $arrAddendum);

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