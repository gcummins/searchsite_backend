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
		gatherFormFields('edit');
		break;
	case 'saveNew':
		gatherFormFields('new');
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
	global $dealhuntingDatabase, $module;
	
	if (!isPermitted('delete', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, false);
		returnToMainPage(getStartPage());
		return;
	}
	
	if (!isset($_GET['banner_id']) || (int)$_GET['banner_id'] < 1)
	{
		returnError(201, "A banner ID must be provided.", false);
		returnToMainPage(getStartPage());
		return;
	}
	else
	{
		$id = (int)$_GET['banner_id'];

		$dealhuntingDatabase->query("DELETE FROM `" . DEALHUNTING_BANNERS_TABLE . "` WHERE `id`=$id LIMIT 1;");
		
		returnMessage(1101, sprintf(LANGUAGE_DELETE_OBJECT_ID, 'banner', $id), true);
		returnToMainPage(getStartPage());
		return;
	}
}

function gatherFormFields($saveType)
{
	// This function will gather and sanitize the form fields,
	// then pass the data to the appropriate function for
	// editing or insertion.
	global $dealhuntingDatabase;
	
	$arrFields = array(); // To hold the sanitized values
	
	// Field: ID
	if (isset($_POST['banner_id']))
	{
		$id = (int)$_POST['banner_id'];
	}
	else
	{
		$saveType = 'new'; // If no ID was provided, save this as a new banner.
	}
	
	// Field: name
	if (isset($_POST['banner_name']) && !empty($_POST['banner_name']))
	{
		$arrFields['name'] = $dealhuntingDatabase->escape_string($_POST['banner_name']);
	}
	else
	{
		returnError(200, "A name is required when creating a banner.", false);
		returnToMainPage(getStartPage());
		return;
	}
	
	// Field: link_url
	if (isset($_POST['banner_link_url']) && !empty($_POST['banner_link_url']))
	{
		$arrFields['link_url'] = $dealhuntingDatabase->escape_string($_POST['banner_link_url']);
	}
	else
	{
		returnError(200, "A link URL is required when creating a banner.", false);
		returnToMainPage(getStartPage());
		return;
	}
	
	// Field: image_url
	if (isset($_POST['banner_image_url']) && !empty($_POST['banner_image_url']))
	{
		$arrFields['image_url'] = $dealhuntingDatabase->escape_string($_POST['banner_image_url']);
	}
	else
	{
		returnError(200, "An image URL is required when creating a banner.", false);
		returnToMainPage(getStartPage());
		return;
	}
	
	// Field: image_url
	if (isset($_POST['banner_tracker_url']))
	{
		$arrFields['tracker_url'] = $dealhuntingDatabase->escape_string($_POST['banner_tracker_url']);
	}
	else
	{
		$arrFields['tracker_url'] = '';
	}
	
	// Field: alt_text
	if (isset($_POST['banner_alt_text']))
	{
		$arrFields['alt_text'] = $dealhuntingDatabase->escape_string($_POST['banner_alt_text']);
	}
	else
	{
		$arrFields['alt_text'] = '';
	}
	
	// Field: height
	if (isset($_POST['banner_height']) && (int)$_POST['banner_height'] > 0)
	{
		$arrFields['height'] = (int)$_POST['banner_height'];
	}
	else
	{
		$arrFields['height'] = BANNER_DEFAULT_HEIGHT;
	}
	
	// Field: width
	if (isset($_POST['banner_width']) && (int)$_POST['banner_width'] > 0)
	{
		$arrFields['width'] = (int)$_POST['banner_width'];
	}
	else
	{
		$arrFields['width'] = BANNER_DEFAULT_WIDTH;
	}
	
	if (isset($_POST['banner_open_new_window']) && $_POST['banner_open_new_window'] == '0')
	{
		$arrFields['open_new_window'] = 0;
	}
	else
	{
		// Set the default value
		$arrFields['open_new_window'] = 1;
	}
	
	if (isset($_POST['banner_default']) && $_POST['banner_default'] == '1')
	{
		$arrFields['default'] = 1;
	}
	else
	{
		// Set the default value
		$arrFields['default'] = 0;
	}

	
	// Send the data to the action functions
	if ($saveType == 'edit')
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
	global $dealhuntingDatabase, $module;
	
	if (!isPermitted('edit', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, false);
		returnToMainPage(getStartPage());
		return;
	}
	
	// Generate the MySQL update query
	$query = "UPDATE `" . DEALHUNTING_BANNERS_TABLE . "` SET"
		. " `name`='"			. $arrFields['name']			. "', "
		. " `link_url`='"		. $arrFields['link_url']		. "', "
		. " `image_url`='"		. $arrFields['image_url']		. "', "
		. " `tracker_url`='"	. $arrFields['tracker_url']		. "', "
		. " `alt_text`='"		. $arrFields['alt_text']		. "', "
		. " `height`="			. $arrFields['height']			. ", "
		. " `width`="			. $arrFields['width']			. ", "
		. " `open_new_window`="	. $arrFields['open_new_window']	. ", "
		. " `default`="			. $arrFields['default']			. " "
		. " WHERE `id`=$id LIMIT 1;";
	
	$dealhuntingDatabase->query($query);
	
	returnMessage(1001, sprintf(LANGUAGE_MODIFY_OBJECT_NAME, 'banner', $arrFields['name']), true);
	returnToMainPage(getStartPage());
	return;
}

function saveNew($arrFields)
{
	global $dealhuntingDatabase, $module;
	
	if (!isPermitted('create', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, false);
		returnToMainPage(getStartPage());
		return;
	}
	
	// Generate the MySQL insert query
	$query = "INSERT INTO `" . DEALHUNTING_BANNERS_TABLE . "`"
		. " (`name`, `link_url`, `image_url`, `tracker_url`, `alt_text`,"
		. " `height`, `width`, `open_new_window`, `default`) VALUES ("
		. " '"	. $arrFields['name']			. "',"
		. " '"	. $arrFields['link_url']		. "',"
		. " '"	. $arrFields['image_url']		. "',"
		. " '"	. $arrFields['tracker_url']		. "',"
		. " '"	. $arrFields['alt_text']		. "',"
		. " "	. $arrFields['height']			. ","
		. " "	. $arrFields['width']			. ","
		. " "	. $arrFields['open_new_window']	. ","
		. " "	. $arrFields['default']			. ""
		. ");";

	$dealhuntingDatabase->query($query);
		
	returnMessage(1002, sprintf(LANGUAGE_CREATE_OBJECT_NAME, 'banner', $arrFields['name']), false);
	returnToMainPage(getStartPage());
	return;
}

function showTable()
{
	global $dealhuntingDatabase, $module, $moduleName;
	
	returnMessage(1000, LANGUAGE_VIEWED_MAIN_PAGE, false);
	
	$orderbyString = getOrderByString();
	
	// Get a count of all available banners
	$dealhuntingDatabase->query("SELECT count(*) AS `count` FROM `" . DEALHUNTING_BANNERS_TABLE . "`;");
	
	$rowCount = $dealhuntingDatabase->firstField();
	
	// Include the pagination class
	include "includes/pagination.class.php";
	
	$paginator = new Pagination($module, $rowCount);
	
	$query = "SELECT"
		. " `id`, `name`, `link_url`, `image_url`, `alt_text`, `default`"
		. " FROM `" . DEALHUNTING_BANNERS_TABLE . "`"
		. " $orderbyString " . $paginator->getLimitString();
		
	$dealhuntingDatabase->query($query);
	
	// Include the table-creation class
	include_once "includes/pageContentTable.class.php";
	
	$arrListOfColumns = array();
	$arrDataRows = array();
	
	foreach ($dealhuntingDatabase->objects() as $row)
	{
		$actionButtons = null;
		if (isPermitted('edit', $module))
		{
			$actionButtons .= "<a href=\"#\" onclick=\"showEditDiv(" . $row->id . ", '" . ADMINPANEL_WEB_PATH . "', '" . $_SERVER['PHP_SELF'] . "', " . $module . ", '" . $moduleName . "'); return false;\"><img src=\"" . ADMINPANEL_IMAGES_ACTION_EDIT . "\" alt=\"Edit\" /></a>";
		}
		if (isPermitted('delete', $module))
		{
			$actionButtons .= "<a href=\"#\" onclick=\"showDeleteConfirmation(" . $row->id . ", '" . $row->name . "', '" . $_SERVER['PHP_SELF'] . "', " . $module . ");\"><img src=\"" . ADMINPANEL_IMAGES_ACTION_DELETE . "\" alt=\"Delete\" /></a>";
		}
		
		if (!empty($row->link_url) && !empty($row->image_url))
		{
			$imageString = "<a href=\"" . $row->link_url . "\" rel=\"external\"><img src=\"" . $row->image_url . "\" alt=\"" . $row->alt_text . "\" /></a>";
		}
		else
		{
			$imageString = "Unavailable";
		}
		
		$defaultString = ($row->default) ? "Yes" : "No";
		
		if (!empty($actionButtons))
		{
			$arrListOfColumns = array('Action', 'ID', 'Name', 'Image', 'Default');
			$arrDataRows[] = array('', array(
				array("class=\"action_buttons\"", $actionButtons),
				array("class=\"banner_td_id\"", $row->id),
				array("", $row->name),
				array("class=\"banner_td_imageurl\"", $imageString),
				array("class=\"banner_td_default\"", $defaultString)
			));	
		}
		else
		{
			$arrListOfColumns = array('ID', 'Name', 'Image', 'Default');
			$arrDataRows[] = array('', array(
				array("class=\"banner_td_id\"", $row->id),
				array('', $row->name),
				array("class=\"banner_td_imageurl\"", $row->image_url),
				array("class=\"banner_td_default\"", $defaultString)
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
	$selectControlString  = "<div class=\"orderby_div\">"
		. "\nSort By: <select name=\"paging_orderby\" onchange=\"loadSortOrder(this, '" . ADMINPANEL_WEB_PATH . "', " . $module . ");\">\n"
		. "\n\t<option value=\"name\"";
	if ($paginator->orderby == "name")
	{
		$selectControlString .= " selected=\"selected\"";
	}
	$selectControlString .= ">Name</option>"
		. "\n\t<option value=\"named\"";
	if ($paginator->orderby == "named")
	{
		$selectControlString .= " selected=\"selected\"";
	}
	$selectControlString .= ">Name (descending)</option>"
		. "\n\t<option value=\"id\"";
	if ($paginator->orderby == "id")
	{	
		$selectControlString .= " selected=\"selected\"";
	}
	$selectControlString .= ">ID (ascending)</option>"
		. "\n\t<option value=\"idd\"";
	if ($paginator->orderby == "idd")
	{
		$selectControlString .= " selected=\"selected\"";
	}
	$selectControlString .= ">ID (descending)</option>"
		. "\n</select></div>";
	
	$arrAddendum = array(
		$selectControlString,
		$paginator->generateLinkBar()
	);
	
	// Create the table
	$theTable = new pageContentTable('Banner Administration', $arrListOfColumns, 'Create Banner', $action, $arrDataRows, $arrAddendum);

	if ($theTable->error())
	{
		returnError(301, $theTable->error(), true);
	}
	else
	{
		echo $theTable->createTable();
	}
}

function getOrderByString()
{
	if (isset($_REQUEST['paging_orderby']) && !empty($_REQUEST['paging_orderby']))
	{
		switch ($_REQUEST['paging_orderby'])
		{
			case "id":
				$orderbyString = "ORDER BY id ASC";
				break;
			case "idd":
				$orderbyString = "ORDER BY id DESC";
				break;
			case "named":
				$orderbyString = "ORDER BY name DESC";
				break;
			case "name":
			default:
				$orderbyString = "ORDER BY name ASC";
				break;
		}
	}
	else
	{
		$orderbyString = "ORDER BY id ASC";
	}
	return $orderbyString;
}
?>