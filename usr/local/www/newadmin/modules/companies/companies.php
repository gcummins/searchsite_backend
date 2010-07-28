<?php

// Make sure this script is not called directly
if (!defined('APP_NAME'))
{
	die("This script cannot be accessed directly.");
}

$task = getTask();

switch ($task)
{
	case 'saveEdit':
		saveEdit();
		break;
	case 'saveNew':
		saveNew();
		break;
	case 'deleteSubmit':
		deleteSubmit();
		break;
	default:
		showTable();
		break;
}

function deleteSubmit()
{
	// In Progress :: to be completed
	
	global $dealhuntingDatabase, $module;
	
	if (!isPermitted('delete', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, false);
		returnToMainPage(getStartPage());
		return 0;
	}
	
	if (isset($_REQUEST['companyid']) && is_numeric($_REQUEST['companyid']))
	{
		$companyID = (int)$_REQUEST['companyid'];
		
		$query = "DELETE FROM `" . DEALHUNTING_COMPANIES_TABLE . "` WHERE `id`=$companyID LIMIT 1;";
		$dealhuntingDatabase->query($query, false);
		
		if (true === $dealhuntingDatabase->error)
		{
			returnError(902, $query, true, $dealhuntingDatabase);
			returnToMainPage(getStartPage());
			return 0;
		}
		else
		{
			returnMessage(1101, sprintf(LANGUAGE_DELETE_OBJECT_ID, 'company', $companyID, true));
		}
	}
	else
	{
		returnError(200, "Invalid company ID was supplied", false);
	}
	
	returnToMainPage(getStartPage());
}

function saveEdit()
{
	global $dealhuntingDatabase, $module;
	
	if (!isPermitted('edit', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, false);
		returnToMainPage();
		return 0;
	}
	
	// Collect all the field values and types
	$arrFields = getFieldNames();
	$arrFieldsTypes = getFieldTypes();
	
	if (isset($_REQUEST['edit_company_id']) && is_numeric($_REQUEST['edit_company_id']))
	{
		$companyID = (int)$_REQUEST['edit_company_id'];
	}
	else
	{
		returnError(200, "Invalid company ID was supplied", false);
		showTable();
		return 0;
	}
	
	$query = "UPDATE " . DEALHUNTING_COMPANIES_TABLE . " SET ";
	
	foreach ($arrFields as $formFieldName => $dbFieldName)
	{
		$query .= "`$dbFieldName`=";
		switch ($arrFieldsTypes[$formFieldName])
		{
			case 'integer':
				$query .= (int)$_REQUEST[$formFieldName];
				break;
			case 'datetime':
				$query .= "'" . date('Y-m-d H:i:s', strtotime($_REQUEST[$formFieldName])) . "'";
				break;
			case 'bool':
				if ((int)$_REQUEST[$formFieldName])
				{
					$query .= "1";
				}
				else
				{
					$query .= "0";
				}
				break;
			case 'checkbox':
				if (isset($_REQUEST[$formFieldName]) && $_REQUEST[$formFieldName] == 'on')
				{
					$query .= "1";
				}
				else
				{
					$query .= "0";
				}
				break;
			case 'string':
			default:
				$query .= "'" . mysql_real_escape_string($_REQUEST[$formFieldName]) . "'";
				break;		
		}
		$query .= ", ";
	}

	// Strip the trailing comma from the string and continue the query
	$query = substr($query, 0, -2) . " WHERE `id`=$companyID LIMIT 1";
	
	$dealhuntingDatabase->query($query);
	
	returnMessage(1001, sprintf(LANGUAGE_MODIFY_OBJECT_NAME, 'company', $_REQUEST['edit_company']), true);

	returnToMainPage();
}

function saveNew()
{
	global $dealhuntingDatabase, $module;
	
	if (!isPermitted('create', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, false);
		returnToMainPage();
		return 0;
	}

	// Collect all the field values and types
	$arrFields = getFieldNames();
	$arrFieldsTypes = getFieldTypes();
	
	
	$query = "INSERT INTO " . DEALHUNTING_COMPANIES_TABLE . " (";
	$valuesString = " VALUES (";

	foreach ($arrFields as $formFieldName => $dbFieldName)
	{
		$query .= "`$dbFieldName`, ";
		switch ($arrFieldsTypes[$formFieldName])
		{
			case 'integer':
				$valuesString .= (int)$_REQUEST[$formFieldName] . ", ";
				break;
			case 'datetime':
				$valuesString .= "'" . date('Y-m-d H:i:s', strtotime($_REQUEST[$formFieldName])) . "', ";
				break;
			case 'bool':
				if ((int)$_REQUEST[$formFieldName])
				{
					$valuesString .= "1, ";
				}
				else
				{
					$valuesString .= "0, ";
				}
				break;
			case 'checkbox':
				if (isset($_REQUEST[$formFieldName]) && $_REQUEST[$formFieldName] == 'on')
				{
					$valuesString .= "1, ";
				}
				else
				{
					$valuesString .= "0, ";
				}
				break;
			case 'string':
			default:
				$valuesString .= "'" . mysql_real_escape_string($_REQUEST[$formFieldName]) . "', ";
				break;
		}
	}
	
	// Strip the trailing comma from the string and continue the query
	$query = substr($query, 0, -2) . ")" . substr($valuesString, 0, -2) . ");";
	$dealhuntingDatabase->query($query);
	
	returnMessage(1002, sprintf(LANGUAGE_CREATE_OBJECT_NAME, 'company', $_REQUEST['edit_company']), true);

	returnToMainPage();
}

function showTable()
{
	global $dealhuntingDatabase, $module, $moduleName;
	
	returnMessage(1000, LANGUAGE_VIEWED_MAIN_PAGE, false);
	
	if (isset($_REQUEST['paging_orderby']) && !empty($_REQUEST['paging_orderby']))
	{
		switch ($_REQUEST['paging_orderby'])
		{
			case "companyd":
				$orderbyString = "ORDER BY `company` DESC";
				break;
			case "company";
			case "companya":
			default:
				$orderbyString = "ORDER BY `company` ASC";
				break;
		}
	}
	else
	{
		$orderbyString = "ORDER BY `company` ASC";
	}
	
	// Get a count of all available companies
	$dealhuntingDatabase->query("SELECT count(*) as count FROM " . DEALHUNTING_COMPANIES_TABLE . ";");
	
	$rowCount = $dealhuntingDatabase->firstField();

	// Include the pagination class
	include "includes/pagination.class.php";
		
	$paginator = new Pagination($module, $rowCount);
	
	$query = "SELECT"
		. " `id`, `company`, `url`"
		. " FROM " . DEALHUNTING_COMPANIES_TABLE
		. " $orderbyString " . $paginator->getLimitString();

	$dealhuntingDatabase->query($query);
	
	// Include the table-creation class
	include_once "includes/pageContentTable.class.php";
	
	foreach ($dealhuntingDatabase->objects() as $row)
	{
		// Create action links only if allowed for this user
		$actionButtons = null;
		if (isPermitted('edit', $module))
		{
			$actionButtons .= "<a href=\"#\" onclick=\"showEditDiv(" . $row->id . ", '" . ADMINPANEL_WEB_PATH . "', '$moduleName', '" . $_SERVER['PHP_SELF'] . "', $module);\"><img src=\"" .  ADMINPANEL_WEB_PATH . "/images/edit.png\" alt=\"Edit\" /></a>";
		}
		if (isPermitted('delete', $module))
		{
			$actionButtons .= "<a href=\"#\" onclick=\"confirmCompanyDelete('" . $row->company . "', " . $row->id . ", '" . ADMINPANEL_WEB_PATH . "', '$module');\"><img src=\"" . ADMINPANEL_WEB_PATH. "/images/delete.png\" alt=\"Delete\" /></a>";
		}
		
		if (strlen($row->url) > ADMINPANEL_URL_LENGTH)
		{
			$theUrl = "<a href=\"" . $row->url . "\" rel=\"external\">" . substr($row->url, ADMINPANEL_URL_LENGTH-3) . "...</a>";
		}
		else
		{
			$theUrl = "<a href=\"" . $row->url . "\" rel=\"external\">" . $row->url . "</a>";
		}
		if (!empty($actionButtons))
		{
			$arrListOfColumns = array('Action', 'Company', 'URL');
			$arrDataRows[] = array('', array(
				array("class=\"action_buttons\"", $actionButtons),
				array('', htmlspecialchars($row->company)),
				array('', $theUrl)
			));
		}
		else
		{
			$arrListOfColumns = array('Company', 'URL');
			$arrDataRows[] = array('', array(
				array('', htmlspecialchars($row->company)),
				array('', $theUrl)
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
	
	// Create the select control
	$selectControlString  = "\n\t\t\t\t\t\t\t<div class=\"orderby_div\">"
		. "\n\t\t\t\t\t\t\t\tSort By: <select name=\"paging_orderby\" onchange=\"loadSortOrder(this, '" . ADMINPANEL_WEB_PATH . "', " . $module . ");\">"
		. "\n\t\t\t\t\t\t\t\t\t<option value=\"company\" ";
	if ($paginator->orderby == "company" || $paginator->orderby == "companya")
	{
		$selectControlString .= "selected=\"selected\"";
	}
	$selectControlString .= ">Company (ascending)</option>";
	
	$selectControlString .= "\n\t\t\t\t\t\t\t\t\t<option value=\"companyd\" ";
	if ($paginator->orderby == "companyd")
	{
		$selectControlString .= "selected=\"selected\"";
	}
	$selectControlString .= ">Company (descending)</option>"
		. "\n\t\t\t\t\t\t\t\t</select>"
		. "\n\t\t\t\t\t\t\t</div>";
	
	// Create an array to hold the "addendum" to the table
	$arrAddendum = array(
		$selectControlString,
		$paginator->generateLinkBar()
	);
	
	// Create the table
	$theTable = new pageContentTable('Companies Administration', $arrListOfColumns, 'Create Company', $action, $arrDataRows, $arrAddendum );
	
	if ($theTable->error())
	{
		returnError(301, $theTable->error(), true);
	}
	else
	{
		echo $theTable->createTable();
	}
}

function getFieldNames()
{
	return array(
			'edit_company' =>'company',
			'edit_aff_type' =>'aff_type',
			'banner_id' =>'banner_id',
			'edit_expiration_date' =>'enddate',
			'edit_url' =>'url',
			'edit_clean_url' =>'clean_url',
			'edit_joblo_url' =>'joblo_url',
			'edit_alert' =>'alert',
			'edit_usship' =>'usship',
			'edit_canadaship' => 'canadaship',
			'edit_ukship' => 'ukship');
}

function getFieldTypes()
{
	return array(
			'edit_company' =>'string',
			'edit_aff_type' =>'integer',
			'banner_id' =>'integer',
			'edit_expiration_date' =>'datetime',
			'edit_url' =>'string',
			'edit_clean_url' =>'string',
			'edit_joblo_url' =>'string',
			'edit_alert' =>'bool',
			'edit_usship' =>'checkbox',
			'edit_canadaship' =>'checkbox',
			'edit_ukship' =>'checkbox');
}
?>