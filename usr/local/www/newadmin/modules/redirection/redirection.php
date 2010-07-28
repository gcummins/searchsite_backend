<?php

// Make sure this script is not called directly
if (!defined('APP_NAME'))
{
	die("This script cannot be accessed directly.");
}

$task = getTask();

switch ($task)
{
	case 'saveNew':
		saveNewRedirection();
		break;
	case 'saveEdit':
		saveEditRedirection();
		break;
	case 'deleteRedir':
		deleteRedirection();
		break;
	default:
		showTable();	
		break;
}

function deleteRedirection()
{
	global $dealhuntingDatabase, $module;
	
	if (!isPermitted('delete', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, false);
		returnToMainPage(getStartPage());
		return 0;
	}
	
	$redir_id		= (int)$_REQUEST['redirectionid'];
	
	$query			= "DELETE FROM " . DEALHUNTING_REDIRECTION_TABLE . " WHERE "
						. "id=" . $redir_id . " LIMIT 1;";
						
	$dealhuntingDatabase->query($query, false);

	if (true === $dealhuntingDatabase->error)
	{
		returnError(902, $query, false, $dealhuntingDatabase);
		returnToMainPage(getStartPage());
		exit();
	}
	
	returnMessage(1101, sprintf(LANGUAGE_DELETE_OBJECT_ID, 'redirection', $redir_id), true);
	returnToMainPage(getStartPage());
}

function saveNewRedirection()
{
	global $dealhuntingDatabase, $module;

	if (!isPermitted('create', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, false);
		returnToMainPage();
		return 0;
	}
	
	// Gather the required fields: 'description', 'url'
	$description = mysql_real_escape_string($_REQUEST['redirection_description']);
	$url = mysql_real_escape_string(urldecode($_REQUEST['redirection_url']));
	
	$query = "INSERT INTO " . DEALHUNTING_REDIRECTION_TABLE . " (url, description) "
		. "VALUES ('" . $url . "', '" . $description . "');";

	$dealhuntingDatabase->query($query, false);
	
	if (true === $dealhuntingDatabase->error)
	{
		returnError(902, $query, 'true', $dealhuntingDatabase);
		returnToMainPage();
		exit();
	}	
	else
	{
		returnMessage(1002, sprintf(LANGUAGE_CREATE_OBJECT_NAME, 'redirection', $description), true);
		returnToMainPage();
	}
}

function saveEditRedirection()
{
	global $dealhuntingDatabase, $module, $moduleName;
	
	if (!isPermitted('edit', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, false);
		returnToMainPage();
		return 0;
	}
	
	// Gather the required fields: 'description', 'url'
	$description = mysql_real_escape_string($_REQUEST['redirection_description']);
	$url = mysql_real_escape_string(urldecode($_REQUEST['redirection_url']));
	$redir_id = (int)$_REQUEST['redirection_id'];
	
	$dealhuntingDatabase->query("UPDATE " . DEALHUNTING_REDIRECTION_TABLE . " SET "
		. " url='" . $url . "', description='" . $description . "' WHERE id=" . $redir_id . " LIMIT 1;");
	
	returnMessage(1001, sprintf(LANGUAGE_MODIFY_OBJECT_NAME, 'redirection', $description), true);
	returnToMainPage();
	
}

function getOrderByString()
{
	if (isset($_REQUEST['paging_orderby']) && !empty($_REQUEST['paging_orderby']))
	{
		switch ($_REQUEST['paging_orderby'])
		{
			case "company":
			case "companya":
				$orderbyString = "ORDER BY description ASC";
				break;
			case "companyd":
				$orderbyString = "ORDER BY description DESC";
				break;
			case "id":
			default:
				$orderbyString = "ORDER BY id ASC";
				break;
		}
	}
	else
	{
		$orderbyString = "ORDER BY id ASC";
	}
	return $orderbyString;
}

function getRecordPosition($recordId)
{
	// This function will determine the position of $recordId in the result set.
	// This will allow the script to display the new record via pagination after
	// an insertion.
	
	// This function is not yet complete, and is therefore unused by the script. The first
	// page is currently displayed after a new record is inserted.
	global $dealhuntingDatabase;
	
	$dealhuntingDatabase->query("SELECT `id` FROM " . DEALHUNTING_REDIRECTION_TABLE . " " . getOrderByString());
	
	
	$arrRows = array();
	foreach ($dealhuntingDatabase->objects() as $row)
	{
		$arrRows[] = $row->id;
	}
	
	// TODO: Finish this function
	predump($arrRows);
	die();
	return array_search($recordId);
}

function showTable()
{
	global $dealhuntingDatabase, $module, $moduleName;
	
	returnMessage(1000, LANGUAGE_VIEWED_MAIN_PAGE, false);

	$orderbyString = getOrderByString();
	
	// Get a count of all available companies
	$dealhuntingDatabase->query("SELECT count(*) as count from " . DEALHUNTING_REDIRECTION_TABLE . ";");
	$rowCount = $dealhuntingDatabase->firstField();
	
	// Include the pagination class
	include "includes/pagination.class.php";
	
	$paginator = new Pagination($module, $rowCount);
		
	$query = "SELECT"
		. " `id`, `url`, `description`"
		. " FROM " . DEALHUNTING_REDIRECTION_TABLE
		. " $orderbyString " . $paginator->getLimitString();
	$dealhuntingDatabase->query($query);
	
	// Include the table-creation class
	include_once "includes/pageContentTable.class.php";
	
	foreach ($dealhuntingDatabase->objects() as $row)
	{
		$actionButtons = null;
		if (isPermitted('edit', $module))
		{
			$actionButtons .= "<a href=\"#\" onclick=\"showEditDiv(" . $row->id . ", '" . ADMINPANEL_WEB_PATH . "', '" . $_SERVER['PHP_SELF'] . "', " . $module . ", '" . $moduleName . "'); return false;\"><img src=\"" . ADMINPANEL_IMAGES_ACTION_EDIT . "\" alt=\"Edit\" /></a>";
		}
		if (isPermitted('delete', $module))
		{
			$actionButtons .= "<a href=\"#\" onclick=\"showDeleteConfirmation(" . $row->id . ", '" . $row->description . "', '" . $_SERVER['PHP_SELF'] . "', " . $module . ");\"><img src=\"" . ADMINPANEL_IMAGES_ACTION_DELETE . "\" alt=\"Delete\" /></a>";
		}
		if (strlen($row->url) > ADMINPANEL_URL_LENGTH)
		{
			$theUrl = "<a href=\"" . htmlspecialchars($row->url) . "\" rel=\"external\">" . htmlspecialchars(substr($row->url, ADMINPANEL_URL_LENGTH-3)) . "...</a>";
		}
		else
		{
			$theUrl = "<a href=\"" . htmlspecialchars($row->url) . "\" rel=\"external\">" . htmlspecialchars($row->url) . "</a>";
		}
		if (!empty($actionButtons))
		{
			$arrListOfColumns = array('Action', 'Company', 'URL');
			$arrDataRows[] = array('', array(
				array("class=\"action_buttons\"", $actionButtons),
				array('', $row->description),
				array('', $theUrl)
			));
		}
		else
		{
			$arrListOfColumns = array('Company', 'URL');
			$arrDataRows[] = array('', array(
				array('', $row->description),
				array('', $theUrl)
			));
		}
	}
	
	// Create the table action
	if (isPermitted('create', $module))
	{
		$action = "showNewDiv('" . ADMINPANEL_WEB_PATH . "', '" . $_SERVER['PHP_SELF'] . "', " . $module . ", '" . $moduleName . "')";
	}
	else
	{
		$action = '';
	}
	
	// Create the select control
	$selectControlString  = "<div class=\"orderby_div\">";
	$selectControlString .= "\nSort By: <select name=\"paging_orderby\" onchange=\"loadSortOrder(this, '" . ADMINPANEL_WEB_PATH . "', " . $module . ");\">\n";
	$selectControlString .= "\n\t<option value=\"id\"";
	if ($paginator->orderby == "id")
	{
		$selectControlString .= " selected=\"selected\"";
	}
	$selectControlString .= ">ID (default)</option>";
	$selectControlString .= "\n\t<option value=\"company\"";
	if ($paginator->orderby == "company" || $paginator->orderby == "companya")
	{
		$selectControlString .= " selected=\"selected\"";
	}
	$selectControlString .= ">Company (ascending)</option>";
	$selectControlString .= "\n\t<option value=\"companyd\"";
	if ($paginator->orderby == "companyd")
	{
		$selectControlString .= " selected=\"selected\" ";
	}
	$selectControlString .= ">Company (descending)</option>";
	$selectControlString .=	"</select></div>";
	
	$arrAddendum = array(
		$selectControlString,
		$paginator->generateLinkBar()
	);
	
	// Create the table
	$theTable = new pageContentTable('Redirection Administration', $arrListOfColumns, 'Create Redirection', $action, $arrDataRows, $arrAddendum);
	
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