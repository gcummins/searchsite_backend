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
		saveNewAd();
		break;
	case 'deletead':
		deleteAd();
		break;
	case 'reorder':
		reorderAd();
		break;
	case 'saveChanges':
		saveChanges();
	default:
		showTable();
		break;
}	

function deleteAd()
{
	global $dealhuntingDatabase, $module;
	
	if (!isPermitted('delete', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, false);
		returnToMainPage(getStartPage());
		return 0;
	}
	
	if (isset($_REQUEST['topdealid']) && intval($_REQUEST['topdealid'] > 0))
	{
		$adToDelete = (int)$_REQUEST['topdealid'];
	}
	else
	{
		returnError(201, "A Top Deal ID must be provided.", false);
		returnToMainPage(getStartPage());
		return;
	}

	$query = "DELETE FROM `" . DEALHUNTING_TOPDEALS_TABLE. "` WHERE id=$adToDelete LIMIT 1;";
	$dealhuntingDatabase->query($query, false);
	
	if (true === $dealhuntingDatabase->error)
	{
		returnError(902, $query, true, $dealhuntingDatabase);
		returnToMainPage(getStartPage());
		return;
	}
	returnMessage(1101, sprintf(LANGUAGE_DELETE_OBJECT_ID, 'top deal', $adToDelete), true);
	returnToMainPage(getStartPage());
}
function reorderAd()
{
	global $dealhuntingDatabase, $module;
	
	if (!isPermitted('edit', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, false);
		returnToMainPage(getStartPage());
		return 0;
	}
	

	if (isset($_REQUEST['aid']))
	{
		$adToReorder = (int)$_REQUEST['aid'];
	}
	else
	{
		// The ad ID that was provided is invalid. 
		returnError(201, "A Top Deal ID must be provided.", false);
		returnToMainPage(getStartPage());
		return;
	}
	
	$dealhuntingDatabase->query("SELECT id, ordering FROM " . DEALHUNTING_TOPDEALS_TABLE . " ORDER BY ordering;");

	$arrIdsAndOrdering = array();
	
	foreach ($dealhuntingDatabase->objects() as $row)
	{
		$arrIdsAndOrdering[$row->id] = $row->ordering;
	}

	checkAdOrdering($arrIdsAndOrdering);
	
	// Now determine which direction we are moving the selected ad
	if (isset($_REQUEST['orderdir']) && !empty($_REQUEST['orderdir']) && ($_REQUEST['orderdir'] == 'up' || $_REQUEST['orderdir'] == 'down'))
	{
		// We have a valid ad id and a valid direction
		// Do an extra check to ensure that the new ordering value will not place the ad ordering outside of the required range
		$orderDirection = $_REQUEST['orderdir'];
		$currentOrdering = (int)$_REQUEST['curorder'];
		if ($orderDirection == 'up' && $currentOrdering == 1)
		{
			// No sense in trying to get higher in the list than the first position...
			return 0;
		}
		elseif ($orderDirection == 'down' && $currentOrdering == max($arrIdsAndOrdering))
		{
			// Do not try to get lower than the last position.
			return 0;
		}
		else
		{
			switch ($orderDirection)
			{
				case 'up':
					$factor = -1;
					break;
				case 'down':
					$factor = 1;
					break;
				default:
					die("For some reason, and invalid orderDirection was provided. Please contact a system administrator to examine " . $_SERVER['SCRIPT_FILENAME'] . ", line " . __LINE__);
					break;
			}

			$dealhuntingDatabase->query("UPDATE " . DEALHUNTING_TOPDEALS_TABLE . " SET ordering=$currentOrdering WHERE ordering=" . ($currentOrdering + $factor) . ";");

			$dealhuntingDatabase->query("UPDATE " . DEALHUNTING_TOPDEALS_TABLE . " SET ordering=" . ($currentOrdering + $factor) . " WHERE id=$adToReorder;");
		}
	}
	
	returnToMainPage();
}

function checkAdOrdering($arrIdsAndOrdering)
{
	// The next few lines check the top and bottom of the array. The first value should be equal to one,
	// and the last value should be equal to the number of elements in the array.
	
	// We should consider checking each item in the array, because there is a chance that the following
	// scenario would exist:
	
	// array (1, 2, 2, 4)
	
	// This array would pass the upper- and lower-bound checks, but still is not ordered correctly.
	// Is it worth using the processing time to check this every time this script is run?

	// The value of the first element of the array should be '1'
	if (1 != min($arrIdsAndOrdering))
	{
		// The first element does not have a value of '1', so we will need do refactor the ordering
		refactorAdOrdering($arrIdsAndOrdering);
	}
	elseif(max($arrIdsAndOrdering) != count($arrIdsAndOrdering))
	{
		refactorAdOrdering($arrIdsAndOrdering);
	}
	// else, no need to refactor. The ordering is fine.
}

function refactorAdOrdering($arrIdsAndOrdering)
{
	global $dealhuntingDatabase;
	
	// Create the new ordering values, and update the database.
	$i=1;
	foreach ($arrIdsAndOrdering as $key=>$value)
	{
		$arrIdsAndOrdering[$key] = $i;
		$dealhuntingDatabase->query("UPDATE " . DEALHUNTING_TOPDEALS_TABLE . " SET ordering = $i WHERE id=$key LIMIT 1;");
		$i++;
	}
}
function saveChanges()
{
	global $dealhuntingDatabase, $module;
	
	if (!isPermitted('edit', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, false);
		returnToMainPage();
		return 0;
	}
	
	$deal_enabled				= (int)$_REQUEST['ad_enabled'];
	$deal_link 					= $dealhuntingDatabase->escape_string(urldecode($_REQUEST['link']));	
	$deal_image 				= $dealhuntingDatabase->escape_string(urldecode($_REQUEST['image']));
	$deal_impression 			= $dealhuntingDatabase->escape_string(urldecode($_REQUEST['impression']));
	$deal_alttext 				= $dealhuntingDatabase->escape_string(urldecode($_REQUEST['alttext']));
	$deal_linktext 				= $dealhuntingDatabase->escape_string($_REQUEST['linktext']);
	$deal_subtext 				= $dealhuntingDatabase->escape_string($_REQUEST['subtext']);
	$deal_start_date 			= $dealhuntingDatabase->escape_string($_REQUEST['edit_start_date']);
	$deal_end_date 				= $dealhuntingDatabase->escape_string($_REQUEST['edit_end_date']);
	$deal_delete_if_expired 	= (int)$_REQUEST['delete_if_expired'];
	$deal_id 					= (int)$_REQUEST['topdealid'];
	
	$query = "UPDATE `" . DEALHUNTING_TOPDEALS_TABLE . "` SET `enabled`='$deal_enabled', `link`='$deal_link', `image`='$deal_image', `image_alttext`='$deal_alttext', `impression_image`='$deal_impression', `linktext`='$deal_linktext', `subtext`='$deal_subtext', `start_date`='$deal_start_date', `end_date`='$deal_end_date', `delete_if_expired`='$deal_delete_if_expired' WHERE `id`=$deal_id;";
	$dealhuntingDatabase->query($query, false);
	
	if (true === $dealhuntingDatabase->error)
	{
		returnError(902, $query, 'true', $dealhuntingDatabase);
		returnToMainPage();
		exit();
	}
	
	returnMessage(1001, sprintf(LANGUAGE_MODIFY_OBJECT_ID, 'top deal', $deal_id), true);
	returnToMainPage();
}

function saveNewAd()
{
	global $dealhuntingDatabase, $module;
	
	if (!isPermitted('create', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, false);
		returnToMainPage();
		return 0;
	}
	
	$deal_enabled					= (int)$_REQUEST['enabled'];
	$deal_link 						= $dealhuntingDatabase->escape_string(urldecode($_REQUEST['link']));
	$deal_image 					= $dealhuntingDatabase->escape_string(urldecode($_REQUEST['image']));
	$deal_impression 				= $dealhuntingDatabase->escape_string(urldecode($_REQUEST['impression']));
	$deal_alttext 					= $dealhuntingDatabase->escape_string(urldecode($_REQUEST['alttext']));
	$deal_linktext 					= $dealhuntingDatabase->escape_string($_REQUEST['linktext']);
	$deal_subtext 					= $dealhuntingDatabase->escape_string($_REQUEST['subtext']);
	$deal_start_date 				= $dealhuntingDatabase->escape_string($_REQUEST['start_date']);
	$deal_end_date 					= $dealhuntingDatabase->escape_string($_REQUEST['end_date']);
	$deal_delete_if_expired 		= (int)$_REQUEST['delete_if_expired'];
	
	$query = "INSERT INTO " . DEALHUNTING_TOPDEALS_TABLE
			. " (enabled, link, image, image_alttext, impression_image, linktext, subtext, start_date, end_date, delete_if_expired)"
			. " VALUES ("
			. "'$dealEnabled', '$deal_link', '$deal_image', '$deal_alttext', '$deal_impression', '$deal_linktext', '$deal_subtext', '$deal_start_date', '$deal_end_date', '$delete_if_expired'"
			. ");";
			
	$dealhuntingDatabase->query($query, false);
	if (true === $dealhuntingDatabase->error)
	{
		returnError(902, $query, true, $dealhuntingDatabase);
		returnToMainPage();
		exit();
	}
	
	returnMessage(1002, sprintf(LANGUAGE_CREATE_OBJECT_ID, 'top deal', $dealhuntingDatabase->insert_id()), true);
	returnToMainPage();
}

function showTable()
{
	global $module, $moduleName, $dealhuntingDatabase;

	returnMessage(1000, LANGUAGE_VIEWED_MAIN_PAGE, false);
	
	// First, a little housekeeping.
	// We will scan for and remove any ads with the following properties:
	// 1. The expiration date is on or before today's date, and
	// 2. The 'delete_if_expired' flag is true for that ad
	$dealhuntingDatabase->query("DELETE FROM `" . DEALHUNTING_TOPDEALS_TABLE . "` WHERE `end_date` <= CURDATE() AND `delete_if_expired`=1;");
	
	// Get the max 'ordering' value
	$dealhuntingDatabase->query("SELECT MAX(ordering) as max_ordering FROM " . DEALHUNTING_TOPDEALS_TABLE . ";");
	$max_ordering_row = $dealhuntingDatabase->firstObject();
	$max_ordering = $max_ordering_row->max_ordering;
	
	// Get the min 'ordering' value
	$dealhuntingDatabase->query("SELECT MIN(ordering) as min_ordering FROM " . DEALHUNTING_TOPDEALS_TABLE . ";");
	$min_ordering_row = $dealhuntingDatabase->firstObject();
	$min_ordering = $min_ordering_row->min_ordering;
	
	// Create the legend
	include "modules/includes/legend.class.php";
	$legend = new Legend(array(
		array('good', 'Displayed'),
		array('waiting', 'Upcoming'),
		array('invalid', 'Disabled'),
		array('expired', 'Expired')
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
	
	// Get a count of all available topdeals
	$dealhuntingDatabase->query("SELECT COUNT(*) as `count` FROM `" . DEALHUNTING_TOPDEALS_TABLE . "`;");
	$rowCount = $dealhuntingDatabase->firstField();
	
	$dealhuntingDatabase->query("SELECT `id`, `enabled`, `link`, `image`, `image_alttext`, `impression_image`, `linktext`, `subtext`, `start_date`, `end_date`, `delete_if_expired`, `ordering` FROM `" . DEALHUNTING_TOPDEALS_TABLE . "` ORDER BY `ordering`;");
		
	// Include the table-creation class
	include "includes/pageContentTable.class.php";
	
	foreach ($dealhuntingDatabase->objects() as $row)
	{
		$actionButtons = null;
		if (isPermitted('edit', $module))
		{
			$actionButtons .= "<a href=\"#\" onclick=\"showEditDiv(" . $row->id . ", '" . ADMINPANEL_WEB_PATH . "', '$moduleName', '" . $_SERVER['PHP_SELF'] . "', $module);\"><img src=\"" .  ADMINPANEL_WEB_PATH . "/images/edit.png\" title=\"Edit\" /></a>";
		}
		if (isPermitted('delete', $module))
		{
			$actionButtons .= "<a href=\"#\" onclick=\"confirmDealDelete('" . htmlspecialchars($row->image) . "', " . $row->id . ", '" . ADMINPANEL_WEB_PATH . "', '$module');\"><img src=\"" . ADMINPANEL_WEB_PATH. "/images/delete.png\" title=\"Delete\" /></a>";
		}
		// Set the row-coloring class
		if (!empty($row->end_date) && $row->end_date != '0000-00-00' && strtotime("Today") > strtotime($row->end_date))
		{
			$rowModifier = "class=\"legend_expired\"";
		}
		elseif (1 != intval($row->enabled))
		{
			$rowModifier = "class=\"legend_disabled\"";
		}
		elseif (!empty($row->start_date) && strtotime("Today") < strtotime($row->start_date))
		{
			$rowModifier = "class=\"legend_upcoming\"";
		}
		else
		{
			$rowModifier = "class=\"legend_good\"";
		}
		
		$imagead = "<a href=\"" . htmlspecialchars($row->link) . "\" rel=\"external\"><img alt=\"" . $row->image_alttext . "\" src=\"" . htmlentities($row->image) . "\" />";
		
		if (!empty($row->impression_image))
		{
			$imagead .= "<br /><img height=\"1\" width=\"1\" src=\"" . htmlentities($row->impression_image) . "\" alt=\"trackerimage\" />";
		}
		if (!empty($row->linktext))
		{
			$imagead .= "<br />" . $row->linktext;
		}
		$imagead .= "</a>";
		if (!empty($row->subtext))
		{
			$imagead .= "<br />" . htmlentities($row->subtext);
		}
		if (isPermitted('edit', $module))
		{
			if ($row->ordering !== $min_ordering)
			{
				$uparrow = "<a href=\"" . $_SERVER['PHP_SELF'] . "?module=" . $module . "&amp;task=reorder&amp;aid=" . $row->id . "&amp;orderdir=up&amp;curorder=" . $row->ordering . "\"><img src=\"/images/arrowup.gif\" title=\"Move Up\" /></a>";
			}
			else
			{
				$uparrow = "&nbsp;";
			}
			if ($row->ordering !== $max_ordering)
			{
				$downarrow = "<a href=\"" . $_SERVER['PHP_SELF'] . "?module=" . $module . "&amp;task=reorder&amp;aid=" . $row->id . "&amp;orderdir=down&amp;curorder=" . $row->ordering . "\"><img src=\"/images/arrowdown.gif\" title=\"Move Down\" /></a>";
			}
			else
			{
				$uparrow = "&nbsp;";
			}
		}
		
		if (!empty($actionButtons))
		{
			if (isPermitted('edit', $module))
			{
				$arrListOfColumns = array('Action', 'Image', 'Move Up', 'Move Down');
				$arrDataRows[] = array($rowModifier, array(
					array("class=\"action_buttons\"", $actionButtons),
					array("class=\"imagead_cell\"", $imagead),
					array("class=\"uparrow\" align=\"center\" valign=\"middle\"", $uparrow),
					array("class=\"downarrow\" align=\"center\" valign=\"middle\"", $downarrow)
				));
			}
			else
			{
				$arrListOfColumns = array('Action', 'Image');
				$arrDataRows[] = array($rowModifier, array(
					array("class=\"action_buttons\"", $actionButtons),
					array("class=\"imagead_cell\"", $imagead)
				));
			}
		}
		else
		{
			if (isPermitted('edit', $module))
			{
				$arrListOfColumns = array('Image', 'Move Up', 'Move Down');
				$arrDataRows[] = array($rowModifier, array(
					array("class=\"imagead_cell\"", $imagead),
					array("width=\"10%\" align=\"center\" valign=\"middle\"", $uparrow),
					array("width=\"10%\" align=\"center\" valign=\"middle\"", $downarrow)
				));
			}
			else
			{
				$arrListOfColumns = array('Image');
				$arrDataRows[] = array($rowModifier, array(
					array("class=\"imagead_cell\"", $imagead)
				));
			}
		}
	}
	
	// Set the table action
	if (isPermitted('create', $module))
	{
		$action = "showNewDiv('" . ADMINPANEL_WEB_PATH . "', '" . $_SERVER['PHP_SELF'] . "', $module, '$moduleName');";
	}
	else
	{
		$action = '';
	}
	
	$arrAddendum = array();
	
	// Create the table
	$theTable = new pageContentTable('Top Deals Administration', $arrListOfColumns, 'Create Top Deal', $action, $arrDataRows, $arrAddendum);
	
	if ($theTable->error())
	{
		returnError(301, $theTable->error(), true);
	}
	else
	{
		echo $theTable->createTable();
	}
}