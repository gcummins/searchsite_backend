<?php

// Make sure this script is not called directly
if (!defined('APP_NAME'))
{
	die("This script cannot be accessed directly.");
}

$task = getTask();

switch ($task)
{
	case 'editExisting':
		gatherFormFields('edit');
		break;
	case 'addNew':
		gatherFormFields('add');
		break;
	case 'deleteSubmit':
		deleteCoupon();
		break;
	default:
		showTable();
		break;
}

function deleteCoupon()
{
	global $dealhuntingDatabase, $module;
	
	if (!isPermitted('delete', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, false);
		returnToMainPage(getStartPage());
		return 0;
	}
	
	if (!isset($_REQUEST['couponid']) || (int)$_REQUEST['couponid'] < 1)
	{
		returnError(201, 'A valid coupon ID must be provided', false);
		returnToMainPage(getStartPage());
		exit();
	}
	else
	{
		$id = (int)$_REQUEST['couponid'];
		
		$query = "DELETE FROM " . DEALHUNTING_COUPONS_TABLE . " WHERE `id`=$id LIMIT 1;";
		$dealhuntingDatabase->query($query);
		
		returnMessage(1101, sprintf(LANGUAGE_DELETE_OBJECT_ID, 'coupon', $id), false);
		returnToMainPage(getStartPage());
		return;
	}
}

function gatherFormFields($submitType)
{
	// This function will gather and sanitize form fields,
	// then pass the data along to the appropriate function for
	// editing or insertion
	
	$arrFields = array(); // To hold the sanitized values
	
	// Field: ID
	if (isset($_POST['couponid']))
	{
		$id = (int)$_POST['couponid'];
	}
	else
	{
		$submitType = 'add'; // Even if this was submitted as an edit, if no couponid was provided, create a new record
	}
	
	// Field: company
	if (isset($_POST['edit_company']))
	{
		$arrFields['company'] = array('integer', intval($_POST['edit_company']));
	}
	else
	{
		$arrFields['company'] = null;
	}
	
	// Field: joblo_url
	if (isset($_POST['edit_joblo_url']))
	{
		$arrFields['joblo_url'] = array('string', $_POST['edit_joblo_url']);
	}
	else
	{
		$arrFields['joblo_url'] = array('string', '');
	}
	
	// Field: aff_url
	if (isset($_POST['edit_aff_url']))
	{
		$arrFields['aff_url'] = array('string', $_POST['edit_aff_url']);
	}
	else
	{
		$arrFields['aff_url'] = array('string', '');
	}
	
	// Field: _desc
	if (isset($_POST['edit_description']))
	{
		$arrFields['_desc'] = array('string', $_POST['edit_description']);
	}
	else
	{
		$arrFields['_desc'] = array('string', '');
	}

	// Field: url
	if (isset($_POST['edit_url']))
	{
		$arrFields['url'] = array('string', $_POST['edit_url']);
	}
	else
	{
		$arrFields['url'] = array('string', '');
	}

	// Field: clean_url
	if (isset($_POST['edit_clean_url']))
	{
		$arrFields['clean_url'] = array('string', $_POST['edit_clean_url']);
	}
	else
	{
		$arrFields['clean_url'] = array('string', '');
	}
	
	// Field: _show
	if (isset($_POST['edit_show']) && intval($_POST['edit_show']) == 0)
	{
		$arrFields['_show'] = array('integer', 0);
	}
	else
	{
		$arrFields['_show'] = array('integer', 1);
	}
	
	// Field: enable
	if (isset($_POST['edit_start_date']))
	{
		$arrFields['enable'] = array('string', date('Y-m-d 00:00:00', strtotime($_POST['edit_start_date'])));
	}
	else
	{
		$arrFields['enable'] = array('string', date('Y-m-d 00:00:00'));
	}
	
	// Field: expire
	if (isset($_POST['edit_end_date']))
	{
		$arrFields['expire'] = array('string', date('Y-m-d 00:00:00', strtotime($_POST['edit_end_date'])));
	}
	else
	{
		$arrFields['expire'] = array('string', date('Y-m-d 00:00:00'));
	}
	
	// Field: code
	if (isset($_POST['edit_code']))
	{
		$arrFields['code'] = array('string', $_POST['edit_code']);
	}
	else
	{
		$arrFields['code'] = array('string', '');
	}
	
	// Field: deal 
	if (isset($_POST['edit_deal']) && intval($_POST['edit_deal']) == 1)
	{
		$arrFields['deal'] = array('integer', 1);
	}
	else
	{
		$arrFields['deal'] = array('string', 0);
	}
	
	// Field: 
	if (isset($_POST['edit_who']))
	{
		$arrFields['who'] = array('string',	$_POST['edit_who']);
	}
	else
	{
		$arrFields['who'] = array('string', '');
	}
	
	// Field: _when
	$arrFields['_when'] = array('string', date('Y-m-d')); // Set variable to the current time. This data will only be used in a new record.
	
	// Field: notes
	if (isset($_POST['edit_notes']))
	{
		$arrFields['notes'] = array('string', $_POST['edit_notes']);
	}
	else
	{
		$arrFields['notes'] = array('string', '');
	}
	
	// Field: 
	if (isset($_POST['edit_status']))
	{
		switch (intval($_POST['edit_status']))
		{
			case 3:
				$arrFields['status'] = array('integer', 3);
				break;
			case 2:
				$arrFields['status'] = array('integer', 2);
				break;
			case 1:
				$arrFields['status'] = array('integer', 1);
				break;
			case 0:
			default:
				$arrFields['status'] = array('integer', 0);
				break;
		}
	}
	else
	{
		$arrFields[''] = array('string', 0);
	}

	// redirect to the appropriate function
	if ($submitType == 'edit')
	{
		editExisting($arrFields, $id);
	}
	else
	{
		addNew($arrFields);
	}
}

function editExisting($arrFields, $id)
{
	// Update an existing coupon record

	global $dealhuntingDatabase, $module;
	
	if (!isPermitted('edit', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, false);
		returnToMainPage();
		return 0;
	}
	
	// Generate the MySQL UPDATE query from the array
	$query = "UPDATE " . DEALHUNTING_COUPONS_TABLE . " SET ";
	
	foreach ($arrFields as $column=>$arrMetaField)
	{
		list($type, $value) = $arrMetaField;
		
		if ($type == 'string')
		{
			$query .= "`$column`='" . mysql_real_escape_string($value) . "', ";
		}
		elseif ($type == 'integer')
		{
			$query .= "`$column`=$value, ";
		}
		else
		{
			if (ADMINPANEL_DEBUG)
			{
				returnError(301, 'Error in line ' . __LINE__ . ' of file ' . __FILE__ . '. Invalid type (\'' . $type . '\') detected in column ' . $column . '.', true);
			}
		}
	}
	$query = substr($query, 0, -2) . " WHERE `id`=$id LIMIT 1;";
	$dealhuntingDatabase->query($query);
	
	returnMessage(1001, sprintf(LANGUAGE_MODIFY_OBJECT_NAME, 'coupon', $arrFields['code'][1]), false);
	returnToMainPage();
	exit();
}

function addNew($arrFields)
{
	// Add a new coupon record
	
	global $dealhuntingDatabase, $module;
	
	if (!isPermitted('create', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, false);
		returnToMainPage();
		return 0;
	}
	
	// Generate the MySQL INSERT query from the array
	$query = "INSERT INTO " . DEALHUNTING_COUPONS_TABLE;
	$columnString = "(";
	$valueString = "VALUES (";
	foreach ($arrFields as $column=>$arrMetaField)
	{
		list($type, $value) = $arrMetaField;
		
		$columnString .= "`$column`, ";
		
		if ($type == 'string')
		{
			$valueString .= "'" . $dealhuntingDatabase->escape_string($value) . "', ";
		}
		elseif ($type == 'integer')
		{
			$valueString .= "$value, ";
		}
		else
		{
			if (ADMINPANEL_DEBUG)
			{
				die('Error in line ' . __LINE__ . ' of file ' . __FILE__ . '. Invalid type (\'' . $type . '\') detected in column ' . $column . '.');
			}
		}
	}
	$columnString = substr($columnString, 0, -2) . ")";
	$valuesString = substr($valueString, 0, -2) . ")";
	
	$query .= " $columnString $valuesString;";
	$dealhuntingDatabase->query($query);
	
	returnMessage(1002, sprintf(LANGUAGE_CREATE_OBJECT_NAME, 'coupon', $arrFields['code'][1]), false);
	returnToMainPage();
	exit();
}

function showTable()
{
	global $dealhuntingDatabase, $module, $moduleName;
	
	//returnMessage(1000, "Viewed main page", false);
	returnMessage(1000, LANGUAGE_VIEWED_MAIN_PAGE, false);
	
	// Create the legend
	include "modules/includes/legend.class.php";
	$legend = new Legend(array(
		array('good', 'Good'),
		array('bad', 'Bad'),
		array('maybe', 'Maybe'),
		array('invalid', 'Unusable'),
		array('unknownstatus', 'Unknown Status'),
		array('expired', 'Expired')
	));
	echo $legend->create();
	
	// Determine the order in which to display the coupons
	if (isset($_REQUEST['orderby']))
	{
		$inputOrderby = $_REQUEST['orderby'];
	}
	else
	{
		$inputOrderby = '';
	}
	switch ($inputOrderby)
	{
		default:
			$sqlOrderBy = 'ORDER BY companyName ASC, coupon.id DESC';
	}
	
	// Include the pagination class
	include "includes/pagination.class.php";
	
	// Count the number of coupons available
	$rowCountQuery = "SELECT count(*) as count FROM " . DEALHUNTING_COUPONS_TABLE
		. " LEFT JOIN " . DEALHUNTING_COMPANIES_TABLE
		. " ON " . DEALHUNTING_COMPANIES_TABLE . ".`id`=" . DEALHUNTING_COUPONS_TABLE . ".`company`";
	$dealhuntingDatabase->query($rowCountQuery);
	
	$rowCount = $dealhuntingDatabase->firstField();
	
	// Start a new paginator
	$paginator = new Pagination($module, $rowCount);
	
	// Get a list of coupons to be displayed
	$query = "SELECT "
		. DEALHUNTING_COUPONS_TABLE . ".`id`, "
		. DEALHUNTING_COUPONS_TABLE . ".`_desc` as `description`, "
		. DEALHUNTING_COUPONS_TABLE . ".`url`, "
		. DEALHUNTING_COUPONS_TABLE . ".`expire`, "
		. DEALHUNTING_COUPONS_TABLE . ".`code`, "
		. DEALHUNTING_COUPONS_TABLE . ".`status`, "
		. DEALHUNTING_COMPANIES_TABLE . ".`company` as `companyName`"
		. " FROM " . DEALHUNTING_COUPONS_TABLE
		. " LEFT JOIN " . DEALHUNTING_COMPANIES_TABLE
		. " ON " . DEALHUNTING_COMPANIES_TABLE . ".`id`=" . DEALHUNTING_COUPONS_TABLE . ".`company`"
		. " $sqlOrderBy " . $paginator->getLimitString();

	$dealhuntingDatabase->query($query);
	
	include_once("includes/pageContentTable.class.php");
	
	foreach ($dealhuntingDatabase->objects() as $row)
	{
		$actionButtons = null;
		if (isPermitted('edit', $module))
		{
			$actionButtons .= "<a href=\"#\" onclick=\"showEditDiv(" . $row->id . ", '" . ADMINPANEL_WEB_PATH . "', '$moduleName', '" . $_SERVER['PHP_SELF'] . "', $module);\"><img src=\"" .  ADMINPANEL_WEB_PATH . "/images/edit.png\" alt=\"Edit\" /></a>";
		}
		if (isPermitted('delete', $module))
		{
			$actionButtons .= "<a href=\"#\" onclick=\"confirmCouponDelete(" . $row->id . ", '" . ADMINPANEL_WEB_PATH . "', '$module');\"><img src=\"" . ADMINPANEL_WEB_PATH. "/images/delete.png\" alt=\"Delete\" /></a>";
		}
		$rowModifier = "";
		if ($row->expire != null && strtotime($row->expire) < time())
		{
			$rowModifier = "class=\"legend_expired\"";
		}
		else
		{
			switch ($row->status)
			{
				case 0:
					$rowModifier = "";
					break;
				case 1:
					$rowModifier = "class=\"legend_bad\"";
					break;
				case 2:
					$rowModifier = "class=\"legend_maybe\"";
					break;
				case 3:
					$rowModifier = "class=\"legend_invalid\"";
					break;
				default:
					$rowModifier= "class=\"legend_unknownstatus\"";
					break;
			}
			
		}
		if ($row->url)
		{
			$description = "<a href=\"" . htmlentities($row->url) . "\" rel=\"external\">". $row->description . "</a>";
		}
		else
		{
			$description = $row->description;
		}
		if (!empty($actionButtons))
		{
			$arrListOfColumns = array('Action', 'Company', 'Description', 'Code');
			$arrDataRows[] = array($rowModifier, array(
				array("class=\"action_buttons\"", $actionButtons),
				array('', $row->companyName),
				array('', $description),
				array('', $row->code)
			));
		}
		else
		{
			$arrListOfColumns = array('Company', 'Description', 'Code');
			$arrDataRows[] = array($rowModifier, array(
				array('', $row->companyName),
				array('', $description),
				array('', $row->code)
			));
		}
	}
	
	// Set the table action
	if (isPermitted('create', $module))
	{
		$action = 'showNewDiv(\''.ADMINPANEL_WEB_PATH.'\', \'' . $_SERVER['PHP_SELF'] . '\', \'' . $moduleName. '\', ' . $module . ');';
	}
	else
	{
		$action = '';
	}
	$theTable = new pageContentTable('Coupons Administration', $arrListOfColumns, 'Create Coupon', $action, $arrDataRows, $paginator->generateLinkBar());
	
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