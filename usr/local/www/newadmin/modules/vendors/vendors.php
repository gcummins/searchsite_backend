<?php

// Make sure this script is not called directly
if (!defined('APP_NAME'))
{
	die("This script cannot be accessed directly.");
}

$task = getTask();

switch ($task)
{
    case "manage":
          vendor_manage();
          break;
	default:
		showTable();
		break;
}

function showTable()
{
	// Show a paginated list of all vendors
	
	global $dealhuntingDatabase, $feedDatabase, $module, $moduleName;
	
	returnMessage(1000, LANGUAGE_VIEWED_MAIN_PAGE, false);
	
	if (isset($_REQUEST['paging_orderby']) && !empty($_REQUEST['paging_orderby']))
	{
		switch ($_REQUEST['paging_orderby'])
		{
			case 'lastupdate':
				$orderbyString = "ORDER BY `vardatetime` DESC";
				break;
			case 'lastupdates':
				$orderbyString = "ORDER BY `vardatetime` ASC";
				break;
			case 'filetype':
				$orderbyString = "ORDER BY `file` DESC";
				break;
			case 'norecs':
				$orderbyString = "ORDER BY `norecs` DESC";
				break;
			case 'norecsd':
				$orderbyString = "ORDER BY `norecs` ASC";
				break;
			case 'company':
			default:
				$orderbyString = "ORDER BY `compname` ASC";
				break;
		}
	}
	else
	{
		$orderbyString = "ORDER BY `compname` ASC";
	}
	
	$feedDatabase->query("SELECT count(*) as count FROM `compchecker`;");
	
	$rowCount = $feedDatabase->firstField();
	
	// Include the pagination class
	include_once "includes/pagination.class.php";
	
	$paginator = new Pagination($module, $rowCount);
	
	// Get the table rows
	$query = "SELECT"
		. " `id`, `compname`, `catalog`, `vardatetime`, `norecs`, `file`, `approved`, `filename`"
		. " FROM `compchecker`"
		. " $orderbyString " . $paginator->getLimitString();
	
	$feedDatabase->query($query);
		
	// Include the table-creation class
	include_once "includes/pageContentTable.class.php";
	
	$arrDataRows = array();
	//while (false !== ($row = mysql_fetch_object($result)))
	foreach ($feedDatabase->objects() as $row)
	{
	    // Determine if the catalog is blocked
	    if ($row->approved == 0)
	    {
	        $catalogStatus = "Active";
	    }
	    else
	    {
	        $catalogStatus = "Blocked";
	    }
	    
		$arrDataRows[] = array('', array(
			array('', htmlspecialchars($row->compname)),
			array('', htmlspecialchars($row->catalog)),
			array('', date('m/d/Y H:i:s', $row->vardatetime)),
			array('', getFileType($row->file)),
			array('', (int)$row->norecs),
			array('', '<a href="#" onclick="vendors_toggleStatus(\'' . addslashes($row->filename) . '\', \'' . $row->approved . '\', \'' . ADMINPANEL_WEB_PATH . '\', \'' . $moduleName . '\', this)" class="vendors_status_link_' . $catalogStatus . '">' . $catalogStatus . '</a>')
		));
	}
	
	// Set the table action
	$action = '';
	
	// Create the select control
	$selectControlString  = "<div class=\"orderby_div\">";
	$selectControlString .= "\nSort By: <select name=\"paging_orderby\" onchange=\"loadSortOrder(this, '" . ADMINPANEL_WEB_PATH . "', $module);\">\n";
	$selectControlString .= "\t<option value=\"company\" ";
	if ($paginator->orderby == "company")
	{
		$selectControlString .= "selected=\"selected\"";
	}
	$selectControlString .= ">Vendor (ascending)</option>\n";
	$selectControlString .= "\t<option value=\"lastupdate\" ";
	if ($paginator->orderby == "lastupdate")
	{
		$selectControlString .= "selected=\"selected\"";
	}
	$selectControlString .= ">Last Update (newest first)</option>\n";
	$selectControlString .= "\t<option value=\"lastupdates\" ";
	if ($paginator->orderby == "lastupdates")
	{
		$selectControlString .= "selected=\"selected\"";
	}
	$selectControlString .= ">Last Update (oldest first)</option>\n";
	$selectControlString .= "\t<option value=\"filetype\" ";
	if ($paginator->orderby == "filetype")
	{
		$selectControlString .= "selected=\"selected\"";
	}
	$selectControlString .= ">File Type</option>\n";
	$selectControlString .= "\t<option value=\"norecs\" ";
	if ($paginator->orderby == "norecs")
	{
		$selectControlString .= "selected=\"selected\"";
	}
	$selectControlString .= ">Products (high to low)</option>\n";
	$selectControlString .= "\t<option value=\"norecsd\" ";
	if ($paginator->orderby == "norecsd")
	{
		$selectControlString .= "selected=\"selected\"";
	}
	$selectControlString .= ">Products (low to high)</option>\n";
	$selectControlString .= "</select></div>\n";
	
	// Create an array to hold the "addendum" to the table
	$arrAddendum = array(
		$selectControlString,
		$paginator->generateLinkBar()
	);
	
	// Create the table
	$theTable = new pageContentTable('Product Catalogs', array('Vendor', 'Catalog', 'Last Update', 'File Type', 'Products', 'Status'), '', $action, $arrDataRows, $arrAddendum);
	
	if ($theTable->error())
	{
		returnError(301, $theTable->error(), true);
	}
	else
	{
		echo $theTable->createTable();
	}
}

function getFileType($fileTypeNumber)
{
	if (defined('DEALHUNTING_FEED_TYPE_'.$fileTypeNumber)) // Defined in configuration.php
	{
		return constant('DEALHUNTING_FEED_TYPE_'.$fileTypeNumber);
	}
	else
	{
		return $fileTypeNumber;
	}
}

function vendor_manage()
{
    global $feedDatabase, $module;
    
    $siteid = getParameter('siteid');
    
    // Gather a list of all subsites
    $feedDatabase->query("SELECT `id`, `displayName` FROM `frontend_sites`");
    
    if ($feedDatabase->rowCount() < 1)
    {
        dhError("There are no subsites in the database.", 'showTable');
    }
    
    
    $subsiteSelect = "<select name=\"siteid\">";
    foreach ($feedDatabase->arrays() as $subsiteDetail)
    {
        $subsiteSelect .= "<option value=\"" . $subsiteDetail['id'] . "\">" . $subsiteDetail['displayName'] . "</option>\n";
    }
    $subsiteSelect .= "</select>";
    
    echo "Vendors for " . $subsiteSelect . "<br />";
    
    //Gather the basic information for this site
	$feedDatabase->query("SELECT `id`, `displayName` FROM `frontend_sites` WHERE `id`=$siteid LIMIT 1;");
	
	if ($feedDatabase->rowCount() < 1)
	{
		dhError("The site with ID $siteid does not exist in the database.", 'showTable');
	}
	
	$siteDetails = $feedDatabase->firstObject();
	
	returnMessage(1000, "Viewed vendors for site : " . $siteDetails->displayName, false);
	
	// 
	
}
?>