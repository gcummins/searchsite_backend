<?php

// Make sure this script is not called directly
if (!defined('APP_NAME'))
{
	die("This script cannot be accessed directly.");
}

$task = getTask();

switch ($task)
{
	case 'blockProductsSubmit':
		blockProductsSubmit();
		break;
	case 'saveEdit';
		gatherFormFields('edit');
		break;
	case 'saveNew';
		gatherFormFields('add');
		break;
	case 'deleteSubmit';
		deleteSubmit();
		break;
	case 'displayMessageContents':
		displayMessageContents();
		break;
	case 'displayMessages':
		displayMessages();
		break;
	case 'deleteMessageSubmit':
		deleteMessageSubmit();
		break;
	case 'manageProducts':
		manageProducts();
		break;
	case 'statistics':
		showStatistics();
		break;
	default:
		showTable();
		break;
}

/**
 * This function removes products from the search database in two ways:
 * 
 * 1. A call to the Sphinx function UpdateAtrributes will remove the 
 *    product from the current searchd process.
 * 2. The product buyUrl will be added to the `blocked_products` table, 
 *    which will prevent the product from entering the frontend site database 
 *    in the future. 
 * 
 * The searchform will be displayed after the changes have been enacted.
 *
 */
function blockProductsSubmit()
{
	global $feedDatabase;
	
	$siteid = getParameter('siteid');
	$arrProducts = getParameter('product_checkbox');
	
	if (is_array($arrProducts) && count($arrProducts))
	{
		// Retrieve the details for this subsite
		$feedDatabase->query("SELECT `name`, `displayName`, `databaseUsername`, `databasePassword`, `databaseHost` FROM `frontend_sites` WHERE `id`=$siteid LIMIT 1;");
		
		$subsiteDetails = $feedDatabase->firstObject();
		$siteName = $subsiteDetails->displayName;
		
		// Access the subsite control database to determine which database is online
		$subsiteControlDb = new DatabaseConnection($subsiteDetails->databaseHost, $subsiteDetails->databaseUsername, $subsiteDetails->databasePassword, $subsiteDetails->name . '_control');
		
		// Retrieve the name of the database that is currently online
		$subsiteControlDb->query("SELECT `database_name` FROM `database_names` WHERE `online`=1 LIMIT 1;");
		
		if (!$subsiteControlDb->rowCount())
		{
			dhError("This subsite does not have an online product database.");
			exit;
		}
		
		$onlineDatabaseName = $subsiteControlDb->firstField();
		
		// Connect to the Sphinx searchd instance
		require_once '/usr/local/www/search.currentcodes.com/sphinxapi.php';
		
		$cl = new SphinxClient();
		
		foreach ($arrProducts as $productId => $buyUrl)
		{
			// Remove the product from the live database
			$cl->UpdateAttributes($onlineDatabaseName, array('invalid'), array($productId=>array(1)));
			
    		// Insert the product into the blocked_products table
    		$feedDatabase->query("INSERT INTO `blocked_products` (`subsite_id`, `buyURL`) VALUES ($siteid, '$buyUrl')");
		}
		
		if (sizeof($arrProducts == 1))
		{
		    $displayMessage = "The selected product has been removed";
		}
		else if (sizeof($arrProducts > 1))
		{
		    $displayMessage = "The selected products have been removed";
		}
		manageProducts($displayMessage);
	}
}

/**
 * Provide a search engine to access all products in this site.
 * Allow the administrator to selectively remove products from the site database.
 */
function manageProducts($displayMessage = "")
{
	global $module, $feedDatabase;
	
	
	if (empty($displayMessage))
	{
	    dhMessage("This module allows you to view and remove products from a frontend database. All deleted products will be removed the next time the database is updated.", "information", '', false);
	}
	else
	{
	    dhMessage($displayMessage, "information", '', false);
	}

	// Retrieve the site ID
	$siteid = (int)getParameter('siteid');
	
	// Retrieve the details for this subsite
	$feedDatabase->query("SELECT `name`, `displayName`, `databaseUsername`, `databasePassword`, `databaseHost` FROM `frontend_sites` WHERE `id`=$siteid LIMIT 1;");
	
	$subsiteDetails = $feedDatabase->firstObject();
	$siteName = $subsiteDetails->displayName;
	
	// Access the subsite control database to determine which database is online
	$subsiteControlDb = new DatabaseConnection($subsiteDetails->databaseHost, $subsiteDetails->databaseUsername, $subsiteDetails->databasePassword, $subsiteDetails->name . '_control');
	
	// Retrieve the name of the database that is currently online
	$subsiteControlDb->query("SELECT `database_name` FROM `database_names` WHERE `online`=1 LIMIT 1;");
	
	if (!$subsiteControlDb->rowCount())
	{
		dhError("This subsite does not have an online product database.");
		exit;
	}
	
	$onlineDatabaseName = $subsiteControlDb->firstField();
	
	// Connect to the online frontend database
	$onlineDatabase = new DatabaseConnection($subsiteDetails->databaseHost, $subsiteDetails->databaseUsername, $subsiteDetails->databasePassword, $onlineDatabaseName);
	
	require_once '/usr/local/www/search.currentcodes.com/sphinxapi.php';
	require_once '/usr/local/www/search.currentcodes.com/include/productresults.class.php';
	
	// Display the search form
	?><form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" style="width: 100%; text-align: center;"/>
		<div>
			Retrieving products from "<?php echo $siteName; ?>"
			<input type="text" name="search" value="<?php if (isset($_REQUEST['search'])) { echo str_replace(array('"', '\''), array('&quot;', '&#39;'), $_REQUEST['search']); } ?>" />
			<input type="submit" name="submit" value="Search" /><br />
			Show <select name="searchresults_ppp">
				<option value="10"<?php echo (10==getParameter('searchresults_ppp', false)) ? "selected=\"selected\"" : ""; ?>>10</option>
				<option value="20"<?php echo (20==getParameter('searchresults_ppp', false)) ? "selected=\"selected\"" : ""; ?>>20</option>
				<option value="50"<?php echo (50==getParameter('searchresults_ppp', false)) ? "selected=\"selected\"" : ""; ?>>50</option>
				<option value="100"<?php echo (100==getParameter('searchresults_ppp', false)) ? "selected=\"selected\"" : ""; ?>>100</option>
			</select> products per page.&nbsp;&nbsp;
			<input type="checkbox" name="search_showimages" <?php echo (getParameter('search_showimages', false, $_REQUEST, false)) ? "checked=\"checked\"" : ""; ?> /> Show Images?
			<input type="hidden" name="ak" value="1" />
			<input type="hidden" name="module" value="<?php echo $module; ?>" />
			<input type="hidden" name="task" value="manageProducts" />
			<input type="hidden" name="siteid" value="<?php echo $siteid; ?>" />
		</div>
	</form><?php
	
	// If the search form has been submitted, retrieve the results
	if (false !== ($searchTerm = getParameter('search', false, $_REQUEST)))
	{		
		$cl = new SphinxClient();
		$cl->SetMatchMode(SPH_MATCH_EXTENDED2);
		$cl->SetRankingMode(SPH_RANK_PROXIMITY);
		
		// Show only products which have not been blocked/filtered
		$cl->SetFilter('invalid', array(0));
		
		$cl->SetFieldWeights(array('productname' => 100, 'keywords' => 10, 'longdescription' => 50));
		
		$productsPerPage = (int)getParameter('searchresults_ppp', false, $_REQUEST, 10);
		
		$page = getParameter('searchresults_page', false, $_REQUEST, 1);
		$offset = $productsPerPage*($page-1);
		
		$cl->SetSortMode(SPH_SORT_EXTENDED, "@weight DESC");
		$cl->SetLimits($offset, $productsPerPage);
		
		if (false === ($sphinxResults = $cl->Query($searchTerm, $onlineDatabaseName)))
		{
			?><div id="searchresults_topbar">
			<span id="searchresults_summary">No matches were found.</span>
			</div><?php
		}
		else
		{
			
			if (array_key_exists('matches', $sphinxResults) && is_array($sphinxResults['matches']))
			{
				$arrProducts = array();
				$maxDescriptionLength = 150;
				
				$arrProducts = array();
				$arrTitleSnippets = array();
				$arrDescriptionSnippets = array();
				
				foreach ($sphinxResults['matches'] as $doc=>$docinfo)
				{
					$arrProducts[$doc] = new ProductResult($doc, $docinfo, $onlineDatabase, $maxDescriptionLength, false);
				}
				
				?><form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" style="width: 100%">
				<div id="searchresults_topbar">
				<span id="searchresults_summary"><?php echo $sphinxResults['total']; ?> matches were found.</span>
				<span id="searchresults_control">
					<input type="button" value="Select All" onclick="selectAllProducts(<?php echo count($sphinxResults['matches']); ?>)" />
					<input type="submit" value="Delete selected products" />
				</span>
				</div>
				<table style="width: 100%"><?php
				
				$n=1;
				foreach ($arrProducts as $objProduct)
				{
					// Check if this product is marked for removal
					$feedDatabase->query("SELECT `subsite_id` FROM `blocked_products` WHERE `subsite_id`=$siteid AND `buyURL`='" . $objProduct->buyUrl . "' LIMIT 1;");
					
					if ($feedDatabase->rowCount() == 0)
					{
						$productLinkStyle = "";
					}
					else
					{
						$productLinkStyle = " text-decoration: line-through;";
					}
					echo "<tr>";
					echo "<td valign=\"top\"><input type=\"checkbox\" id=\"product_checkbox_$n\" name=\"product_checkbox[" . $objProduct->id . "]\" value=\"" . $objProduct->buyUrl . "\" /></td>";
					if (getParameter('search_showimages', false))
					{
						if (!empty($objProduct->imageUrl))
						{
							echo "<td style=\"padding-bottom: 30px;\" valign=\"top\"><img width=\"90\" src=\"" . $objProduct->imageUrl . "\" alt=\"Product Image\" /></td>";
						}
						else
						{
							echo "<td style=\"padding-bottom: 30px;\" valign=\"top\"><img width=\"90\" src=\"" . IMAGECACHE_SERVER . "/img_na.jpg\" alt=\"\" /></td>";
						}
					}
					echo "<td valign=\"top\" style=\"padding-bottom: 30px;$productLinkStyle\"><a href=\"" . $objProduct->buyUrl . "\" rel=\"external\" />" . $objProduct->productName . "</a><br />" . $objProduct->description . "</td>";
					echo "<td valign=\"top\" style=\"padding-bottom: 30px;\">" . $objProduct->price . "</td>";
					echo "</tr>";
					$n++;
				}
				?></table>				
				<div id="searchresults_bottombar">
				<span id="searchresults_summary"><?php echo $sphinxResults['total']; ?> matches were found.</span>
				<span id="searchresults_control">
					<input type="button" value="Select All" onclick="selectAllProducts(<?php echo count($sphinxResults['matches']); ?>)" />
					<input type="submit" value="Delete selected products" />
				</span>
				</div>
				<input type="hidden" name="siteid" value="<?php echo $siteid; ?>" />
				<input type="hidden" name="module" value="<?php echo $module; ?>" />
				<input type="hidden" name="task" value="blockProductsSubmit" />
				</form><?php
			}
			else
			{
				?><div style="text-align: center">No matches were found</div><?php
			}
		}
	}
}


function createApacheVhost($siteURL)
{
	$vhostTemplate = "<VirtualHost *>\n"
		. "\tServerAdmin %s\n"
		. "\tServerName %s\n"
		. "\tServerAlias %s\n"
		. "\tDocumentRoot %s\n"
		. "\tErrorLog %s-error_log\n"
		. "\tTransferLog %s-access_log\n"
		. "</VirtualHost>\n";
	
	if (substr($siteURL, 0, 4) == 'www.')
	{
		$serverAlias = substr($siteURL, 4);
	}
	else
	{
		$serverAlias = $siteURL;
	}
			
	if (substr(ADMINPANEL_VHOSTS_DOCUMENT_ROOT, -1) != '/')
	{
		$serverVhostDocumentRoot = ADMINPANEL_VHOSTS_DOCUMENT_ROOT . "/$siteURL";
	}
	else
	{
		$serverVhostDocumentRoot = ADMINPANEL_VHOSTS_DOCUMENT_ROOT . "$siteURL";
	}

	// Copy Vhost template into the output string, replacing the variables with correct values
	$output = sprintf($vhostTemplate, GLOBAL_ADMIN_EMAIL, $siteURL, $serverAlias, $serverVhostDocumentRoot, $siteURL, $siteURL);
	
		
	// Ensure that the directory is writable
	if (!is_writable(ADMINPANEL_VHOSTS_DIRECTORY))
	{
		returnError(401, "The Vhosts directory '" . ADMINPANEL_VHOSTS_DIRECTORY . "' is not writable. The Apache Vhost could not be created for this site.");
		return 401;
	}
	if (substr(ADMINPANEL_VHOSTS_DIRECTORY, -1) != '/')
	{
		$vhostFilename = ADMINPANEL_VHOSTS_DIRECTORY . '/' . $siteURL . ".conf";
	}
	else
	{
		$vhostFilename = ADMINPANEL_VHOSTS_DIRECTORY . $siteURL . ".conf";
	}
	
	if (file_exists($vhostFilename))
	{
		returnError(402, "The file '$vhostFilename' already exists. The Apache Vhost could not be created.");
		return 402;
	}
	
	$fileHandle = @fopen($vhostFilename, 'w');
	
	if ($fileHandle === false)
	{
		returnError(403, "Unable to open file '$vhostFilename'. The Apache Vhost could not be created.");
		return 403;
	}
	
	if (false === fwrite($fileHandle, $output))
	{
		returnError(404, "Unable to write data to '$vhostFilename'. The Apache Vhost could not be created.");
		return 404;
	}
	
	fclose($fileHandle);
	
	return 0;
}

function deleteSubmit()
{
	global $adminLink, $module;
	
	if (!isPermitted('delete', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, false);
		returnToMainPage(getStartPage());
		exit();
	}
	
	if (!isset($_REQUEST['objectid']) || 1 > (int)$_REQUEST['objectid'])
	{
		returnError(201, 'A valid ID must be provided.', false);
		returnToMainPage(getStartPage());
		exit();
	}
	else
	{
		$id = (int)$_REQUEST['objectid'];
		
		// Retrieve the site name and URL from the database
		// This will be used when removing the Apach Vhost, and later when
		// creating the log messages
		$siteNameQuery = "SELECT `name`, `url`, `db_name_a`, `db_name_b` FROM `" . ADMINPANEL_SUBSITES_TABLENAME . "` WHERE `id`=$id LIMIT 1;";
		if (false === ($siteNameResult = mysql_query($siteNameQuery, $adminLink)))
		{
			returnError(902, $siteNameQuery, false, $adminLink);
			returnToMainPage(getStartPage());
			exit();
		}
		
		if (!mysql_num_rows($siteNameResult))
		{
			returnError(102, "There is no site in the database matching the ID provided.");
			returnToMainPage(getStartPage());
			exit();
		}
		
		$arrResult = mysql_fetch_array($siteNameResult);
		list($siteName, $siteURL, $dbNameA, $dbNameB) = $arrResult;
		
		// Delete the Apache Vhost entry
		if (substr(ADMINPANEL_VHOSTS_DIRECTORY, -1) == '/')
		{
			$vhostFilename = ADMINPANEL_VHOSTS_DIRECTORY . $siteURL . '.conf';
		}
		else
		{
			$vhostFilename = ADMINPANEL_VHOSTS_DIRECTORY . '/' . $siteURL . '.conf';
		}
		
		if (false === unlink($vhostFilename))
		{
			returnError(405, "Unable to delete Vhost file '$vhostFilename'. Please remove the file manually.");
		}
		else
		{
			returnMessage(1000, "Deleted Apache Vhost '$vhostFilename' for subsite '$siteName' with ID: $id.", false);
		}
		
		// Delete databases associated with this subsite
		foreach (array($dbNameA, $dbNameB) as $subsiteDatabaseName)
		{
			$deleteDatabaseQuery = "DROP DATABASE $subsiteDatabaseName;";
			if (false === mysql_query($deleteDatabaseQuery, $adminLink))
			{
				returnError(902, $deleteDatabaseQuery, true, $adminLink);
				returnToMainPage(getStartPage());
				return 0;
			}
			else
			{
				returnMessage(1101, "Deleted database '$subsiteDatabaseName'", false);
			}
		}
		
		$deleteKeywordsQuery = "DELETE FROM `" . ADMINPANEL_SUBSITES_KEYWORDS_TABLENAME . "` WHERE `subsite_id`=$id;";
		if (false === mysql_query($deleteKeywordsQuery, $adminLink))
		{
			returnError(902, $deleteKeywordsQuery, false, $adminLink);
			returnToMainPage(getStartPage());
			exit();
		}
		else
		{
			returnMessage(1000, "Deleted all keywords for subsite '$siteName' with ID: $id.", false);
		}
		
		$query = "DELETE FROM " . ADMINPANEL_SUBSITES_TABLENAME . " WHERE `id`=$id LIMIT 1;";
		if (false === mysql_query($query, $adminLink))
		{
			returnError(902, $query, false, $adminLink);
			returnToMainPage(getStartPage());
			exit();
		}
		else
		{
			returnMessage(1101, sprintf(LANGUAGE_DELETE_OBJECT_NAME, 'sub-site', $siteName), false);
			returnToMainPage(getStartPage());
			exit();
		}
	}
}

function displayMessageContents()
{
	// Display a single message
	
	global $feedDatabase, $module;
	
	// Determine the subsite ID
	if (isset($_REQUEST['siteid']) && !empty($_REQUEST['siteid']))
	{
		$siteId = (int)$_REQUEST['siteid'];
	}
	else
	{
		returnError(200, "A site ID must be provided.");
	}
	
	// Retrieve the subsite database information
	$feedDatabase->query("SELECT `name`, `databaseUsername`, `databasePassword`, `databaseHost` FROM `frontend_sites` WHERE `id`=$siteId LIMIT 1;");

	$row = $feedDatabase->firstObject();
	
	if (empty($row->databaseHost))
	{
		dhError("No database exists for this site.", 'displayMessages');
		return;
	}
	
	if (isset($_REQUEST['messageid']) && !empty($_REQUEST['messageid']))
	{
		$messageId = (int)$_REQUEST['messageid'];
	}
	else
	{
		dhError("A message ID must be provided.", 'displayMessages');
		return;
	}
	
	// Connect to the subsite database
	$subsiteDatabase = new DatabaseConnection($row->databaseHost, $row->databaseUsername, $row->databasePassword, $row->name . '_control');
	
	// Retrieve the message details
	$subsiteDatabase->query("SELECT `postTime`, `ipAddress`, `emailAddress`, `userAgentString`, `name`, `comment`, `viewed` FROM `user_messages` WHERE `id`=$messageId LIMIT 1");
	
	if ($subsiteDatabase->rowCount() < 1)
	{
		dhError("The requested message does not exist.", 'displayMessages');
		return;
	}
	
	include_once "includes/pageContentTable.class.php";
	
	// Populate $arrDataRows with the message information
	$arrDataRows = array();
	
	$messageDetailRow = $subsiteDatabase->firstObject();
	
	$arrDataRows[] = array('', array(
		array('class="subsiteMessageHeader"', 'Sender'),
		array('', $messageDetailRow->name . " &lt;<a href=\"mailto:" . $messageDetailRow->emailAddress . "\">" . $messageDetailRow->emailAddress . "</a>&gt;")
		));
	$arrDataRows[] = array('', array(
		array('class="subsiteMessageHeader"', 'Received'),
		array('', $messageDetailRow->postTime)
		));
	$arrDataRows[] = array('', array(
		array('class="subsiteMessageHeader"', 'IP Address'),
		array('', "<a href=\"http://ws.arin.net/whois/?queryinput=" . $messageDetailRow->ipAddress . "\">" . $messageDetailRow->ipAddress . "</a>")
		));
	$arrDataRows[] = array('', array(
		array('class="subsiteMessageHeader"', 'Browser ID'),
		array('', $messageDetailRow->userAgentString)
		));
	$arrDataRows[] = array('', array(
		array('colspan="2"', "<div style=\"padding: 15px;\">" . $messageDetailRow->comment . "</div>")
		));
	
	$action = '';
	if (isPermitted('edit', $module))
	{
		$action .= "confirmMessageDelete('" . $subsiteDatabase->escape_string($messageDetailRow->name) . "', '$messageId', '" . ADMINPANEL_WEB_PATH . "', '$module', '$siteId');";
	}
	
	$arrAddendum = array(
		"<a href=\"" . ADMINPANEL_WEB_PATH . "/index.php?ak=1&module=$module&task=displayMessages&siteid=$siteId\">Return to Inbox</a>"
	);
		
	// Create the table
	$theTable = new pageContentTable('Viewing a message', array(), 'Delete', $action, $arrDataRows, $arrAddendum);
	
	if ($theTable->error())
	{
		returnError(301, $theTable->error(), true);
	}
	else
	{
		echo $theTable->createTable();
	}
	
	// Mark the message as read
	$subsiteDatabase->query("UPDATE `user_messages` SET `viewed`=1 WHERE `id`=$messageId LIMIT 1;");
	
	unset ($subsiteDatabase);
}

function displayMessages()
{
	// Display user messages for a certain subsite
	
	global $feedDatabase, $module;

	// Determine the subsite
	if (isset($_REQUEST['siteid']) && !empty($_REQUEST['siteid']))
	{
		$siteId = (int)$_REQUEST['siteid'];
	}
	else
	{
		returnError(200, "A site ID must be provided", false);
	}
	
	// Retrieve the subsite database information
	$feedDatabase->query("SELECT `name`, `databaseUsername`, `databasePassword`, `databaseHost` FROM `frontend_sites` WHERE `id`=$siteId LIMIT 1;");

	$row = $feedDatabase->firstObject();
	
	if (empty($row->databaseHost))
	{
		dhError("No database exists for this site.", 'showTable');
		return;
	}
	
	// Connect to the subsite database
	$subsiteDatabase = new DatabaseConnection($row->databaseHost, $row->databaseUsername, $row->databasePassword, $row->name . '_control');
	
	// Retrieve a count of messages 
	$subsiteDatabase->query("SELECT COUNT(*) as `count` FROM `user_messages`");

	$totalMessageCount = $subsiteDatabase->firstField();
	if ($totalMessageCount == 0)
	{
		dhError("There are no messages for the selected subsite.", 'showTable');
		return;
	}
	
	$subsiteName = ucfirst($row->name);
	
	// Retrieve a count of new messages
	$subsiteDatabase->query("SELECT COUNT(*) as `count` FROM `user_messages` WHERE `viewed`=0");
	$newMessageCount = $subsiteDatabase->firstField();

	if (isset($_REQUEST['paging_orderby']) && !empty($_REQUEST['paging_orderby']))
	{
		switch ($_REQUEST['paging_orderby'])
		{
			case 'read':
				$orderByString = "ORDER BY 'viewed` ASC, `postTime` ASC";
				break;
			case 'reverse':
				$orderByString = "ORDER BY `postTime` ASC";
				break;
			case 'unread':
			default: 
				$orderByString = "ORDER BY `viewed` ASC, `postTime` DESC";
				break;
		}
	}
	else
	{
		$orderByString = "ORDER BY `viewed` ASC, `postTime` DESC";
	}
	
	include_once "includes/pagination.class.php";
	
	$paginator = new Pagination($module, $totalMessageCount);
	
	$subsiteDatabase->query("SELECT `id`, `postTime`, `name`, CONCAT(SUBSTRING(`comment`, 1, 47), '...') AS `comment`, `viewed` FROM `user_messages` $orderByString " . $paginator->getLimitString());
	
	include_once "includes/pageContentTable.class.php";
	
	// Populate $arrDataRows with the message information
	$arrDataRows = array();

	foreach ($subsiteDatabase->objects() as $row)
	{
		$actionButtons = null;
		if (isPermitted('delete', $module))
		{
			$actionButtons .= "<a href=\"#\" onclick=\"confirmMessageDelete('" . $subsiteDatabase->escape_string($row->name) . "', " . $row->id . ", '" . ADMINPANEL_WEB_PATH . "', '$module', $siteId);\"><img src=\"" . ADMINPANEL_WEB_PATH . "/images/delete.png\" . alt=\"Delete\" /></a>";
		}
		
		$nameLink = "<a href=\"" . ADMINPANEL_WEB_PATH . "/index.php?ak=1&module=$module&task=displayMessageContents&siteid=$siteId&messageid=" . $row->id . "\">" . htmlspecialchars($row->name) . "</a>";
		
		if (!$row->viewed)
		{
			$nameLink = "<b>$nameLink</b>";
			$shortComment = "<b>" . $row->comment . "</b>";
			$dateString = "<b>" . naturalLanguageTimeString($row->postTime) . "</b>";
		}
		else
		{
			$shortComment = $row->comment;
			$dateString = naturalLanguageTimeString($row->postTime);
		}

		if (!empty($actionButtons))
		{
			$arrListOfColumns = array('Action', 'Name', 'Message', 'Received');
			$arrDataRows[] = array('', array(
				array("class=\"action_buttons wide_action_buttons\"", $actionButtons),
				array('', $nameLink),
				array('', $shortComment),
				array('', $dateString)
			));
		}
		else
		{
			$arrListOfColumns = array('Name', 'Message', 'Received');
			$arrDataRows[] = array('', array(
				array('', $nameLink),
				array('', $shortComment),
				array('', $dateString)
			));
		}
	}

	// Create an array to hold the "addendum" to the table
	$arrAddendum = array($paginator->generateLinkBar());

	// Create the table
	$theTable = new pageContentTable("$subsiteName messages", $arrListOfColumns, null, null, $arrDataRows, $arrAddendum);

	if ($theTable->error())
	{
		returnError(301, $theTable->error(), true);
	}
	else
	{
		echo $theTable->createTable();
	}

	unset ($subsiteDatabase);
}

function gatherFormFields($submitType)
{
	// This function will gather and sanitize form fields,
	// then pass the data along to the appropriate function for
	// editing or insertion.
		
	global $adminLink;
	
	$arrFields = array(); // To hold the sanitized values
	
	// Field: ID
	if (isset($_POST['site_id']))
	{
		$id = (int)$_POST['site_id'];
	}
	else
	{
		$submitType = 'add'; // Even if this was submitted as an edit, if no subsite_id was provided, create a new record.
	}
	
	// Field: name
	if (isset($_POST['edit_name']) && !empty($_POST['edit_name']))
	{
		$arrFields['name'] = array('string', mysql_real_escape_string($_POST['edit_name'], $adminLink));
	}
	else
	{
		// This field is required
		returnError(201, 'A name is required for this sub-site.', false);
		returnToMainPage();
		return 0;
	}
	
	// Field: url
	if (isset($_POST['edit_url']) && !empty($_POST['edit_name']))
	{
		$arrFields['url'] = array('string', mysql_real_escape_string($_POST['edit_url'], $adminLink));
		
		if (substr($arrFields['url'][1], 0, 4) != 'www.')
		{
			// The string does not start with 'www.'
			$arrFields['url'][1] = 'www.' . $arrFields['url'][1];
		}
	}
	else
	{
		// This field is required
		returnError(201, 'A URL is required for this sub-site.', false);
		returnToMainPage();
		return 0;
	}
		
	// Field: databaseusername
	if (isset($_POST['edit_databaseusername']) && !empty($_POST['edit_databaseusername']))
	{
		$arrFields['databaseusername'] = array('string', mysql_real_escape_string($_POST['edit_databaseusername'], $adminLink));
	}
	else
	{
		// This field is required
		returnError(201, 'A database username is required.', false);
		returnToMainPage(getStartPage());
		return 0;
	}
	
	if (!validate_mysql_username($arrFields['databaseusername'][1]))
	{
		// This is not a valid MySQL username
		returnError(201, "The username '" . $arrFields['databaseusername'][1] . "' is not valid.", false);
		returnToMainPage(getStartPage());
		return 0;
	}
	
	// Field: db_password
	if (isset($_POST['edit_databasepassword']) && !empty($_POST['edit_databasepassword']))
	{
		$arrFields['databasepassword'] = array('string', mysql_real_escape_string($_POST['edit_databasepassword'], $adminLink));
	}
	else
	{
		// This field is required
		returnError(201, 'A database password is required.', false);
		returnToMainPage(getStartPage());
		return 0;
	}
	
	// Retrieve the keywords for this sub-site
	if (isset($_POST['edit_keywords']) && is_array($_POST['edit_keywords']))
	{
		foreach ($_POST['edit_keywords'] as $keyword)
		{
			$arrKeywords[] = mysql_real_escape_string($keyword);
		}
		$arrFields['keywords'] = array('array', $arrKeywords);
	}
	elseif (isset($_POST['edit_keywords']) && !empty($_POST['edit_keywords']))
	{
		$arrFields['keywords'] = array('string', mysql_real_escape_string($keyword));
	}
	else
	{
		$arrFields['keywords'] = array('string', '');
	}
	
	
	// Redirect to the appropriate function
	if ($submitType == 'edit')
	{
		saveEdit($arrFields, $id);
	}
	elseif ($submitType == 'add')
	{
		saveNew($arrFields);
	}
	else
	{
		returnError(301, 'An invalid submit-type was selected. Please contact an administrator. (Script: ' . __FILE__ . ', Line: ' . __LINE__ . ')', false);
		returnToMainPage();
		return 0;
	}
}

function deleteMessageSubmit()
{
	global $feedDatabase, $module;
	
	returnMessage(1000, "In deleteMessageSubmit()", false);
	
	if (!isPermitted('delete', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, false);
		exit;
	}
	
	if (!isset($_REQUEST['objectid']) || 1 > (int)$_REQUEST['objectid'])
	{
		returnError(201, 'A valid message ID must be provided.', false);
		exit;
	}
	else
	{
		$id = (int)$_REQUEST['objectid'];
		
		if (!isset($_REQUEST['subsiteid']) || 1 > (int)$_REQUEST['subsiteid'])
		{
			returnError(201, 'A valid subsite ID must be provided.', false);
		}
		else
		{
			$subsiteId = (int)$_REQUEST['subsiteid'];
			
			// Connect to the admin database to retrieve the subsite database connection information
			$feedDatabase->query("SELECT `name`, `databaseUsername`, `databasePassword`, `databaseHost` FROM `frontend_sites` WHERE `id`=$subsiteId LIMIT 1");
			
			if ($feedDatabase->rowCount() < 1)
			{
				returnError(201, 'The subsite requested does not exist.', false);
				exit;
			}
			else
			{
				$subsiteRow = $feedDatabase->firstObject();
				
				if (empty($subsiteRow->databaseHost))
				{
					returnError(201, 'The selected subsite does not have a database.', false);
					exit;
				}
				
				$subsiteDatabase = new DatabaseConnection($subsiteRow->databaseHost, $subsiteRow->databaseUsername, $subsiteRow->databasePassword, $subsiteRow->name . "_control");
				
				$subsiteDatabase->query("DELETE FROM `user_messages` WHERE id=$id LIMIT 1");
				
				returnMessage(1101, "Message has been deleted", false);
				
				// TODO: Determine if we should return to the inbox or to the subsites main page
				// If there are messages remaining in the inbox, return there. Otherwise,
				// go back to the main page.
				
				?><script type="text/javascript" language="Javascript">
				location.href = "<?php echo ADMINPANEL_WEB_PATH . "/index.php?ak=1&module=$module&task=displayMessages&siteid=$subsiteId"; ?>";
				</script>
				<?php
				exit;
			}
		}
	}
}

function saveEdit($arrFields, $id)
{
	global $adminLink, $module;
	
	if (!isPermitted('edit', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, false);
		returnToMainPage();
		return 0;
	}
	
	// Create the MySQL UPDATE query from the array data
	$query = "UPDATE " . ADMINPANEL_SUBSITES_TABLENAME . " SET ";
	
	$keywordCount = 0;
	foreach ($arrFields as $column=>$arrMetaField)
	{
		list($type, $value) = $arrMetaField;
		
		// Create the keyword update query
		if ($column == 'keywords')
		{
			$keywordInsertQuery = "INSERT INTO `frontend_sites_keywords` (`site_id`, `keyword`) VALUES ";
			if ($type == 'array')
			{
				// For some reason, the order of the keywords is getting reversed each time
				// they are inserted. This line will reverse the reversal, allowing the keywords
				// to remain in the order selected by the user. 
				$value = array_reverse($value);
				
				foreach ($value as $keywordValue)
				{
					$keywordInsertQuery .= " ($id, '". $keywordValue . "'), ";
				}
				$keywordCount = count($value);
			}
			else
			{
				$keywordInsertQuery .= " ($id, '" . $value . "');";
				$keywordCount = 1;
			}
			
			$keywordInsertQuery = substr($keywordInsertQuery, 0, -2) . ";";
		}
		else
		{
			switch ($type)
			{
				case 'string':
					$query .= "`$column`='$value', ";
					break;
				case 'integer':
					$query .= "`$column`=" . (int)$value . ", ";
					break;
				default:
					returnError(301, 'Error in line ' . __LINE__ . ' of file ' . __FILE__ . '. Invalid type (\'' . $type . '\') detected in column ' . $column . '.', true);
					break;			
			}
		}
	}
	$query = substr($query, 0, -2) . " WHERE `id`=$id LIMIT 1;";

	// Create and execute the keyword-delete query
	$keywordDeleteQuery = "DELETE FROM `frontend_sites_keywords` WHERE `site_id`=$id;";
	if (false === mysql_query($keywordDeleteQuery, $adminLink))
	{
		returnError(902, $keywordDeleteQuery, false, $adminLink);
		returnToMainPage();
		exit();
	}
	else
	{
		returnMessage(1000, "Deleted existing keywords for site '" . $arrFields['name'] . "'.", false);
	}
	
	if ($keywordCount)
	{
		// Execute the keyword insert query
		if (false === mysql_query($keywordInsertQuery, $adminLink))
		{
			returnError(902, $keywordInsertQuery, false, $adminLink);
			returnToMainPage();
			exit();
		}
		else
		{
			$keywordMessage = "Inserted $keywordCount new keyword";
			if ($keywordCount > 1)
			{
				$keywordMessage .= 's';
			}
			$keywordMessage .= " for site '" . $arrFields['name'][1] . "'.";
			returnMessage(1000, $keywordMessage, false);
		}
	}
	
	// Execute the subsite update query
	if (false === mysql_query($query, $adminLink))
	{
		returnError(902, $query, false, $adminLink);
		returnToMainPage();
		exit();
	}
	// Update was successful
	returnMessage(1001, sprintf(LANGUAGE_MODIFY_OBJECT_NAME, 'sub-site', $arrFields['name'][1]), false);
	returnToMainPage();
	exit();
}

function saveNew($arrFields)
{
	global $adminLink, $module, $feedDatabase;
	
	if (!isPermitted('create', $module))
	{
		returnError(301, LANGUAGE_ERROR_OPERATION_NOT_PERMITTED, false);
		returnToMainPage();
		return 0;
	}
	
	// Determine if a site with this url already exists
	$feedDatabase->query("SELECT `id` FROM `frontend_sites` WHERE `url` = '" . $arrFields['url'][1] . "';", false);
	if ($feedsDatabase->error)
	{
		returnToMainPage(getStartPage());
		return 0;
	}
	if ($feedDatabase->rowCount())
	{
		// A site already exists with this URL
		returnError(202, "A site with the URL '" . $arrFields['url'][1] . "' already exists in the database.", false);
		returnToMainPage(getStartPage());
		return 0;
	}
	
	// Determine if a site exists with either of the selected datase names.
	// The purpose is to ensure that database names are not duplicated.
	$dbErrorOutput = '';
	
	foreach (array('control', 'one', 'two') as $databaseNamePrefix)
	{
		// See if the database name already exists
		$feedDatabase->query("SHOW databases LIKE '" . $arrFields['name'][1] . "_$databaseNamePrefix';", false);
		if ($feedDatabase->error)
		{
			returnToMainPage(getStartPage());
			return 0;
		}
		
		if ($feedDatabase->rowCount())
		{
			$dbErrorOutput .= "The database name '" . $arrFields['name'][1] . "_$databaseNamePrefix' already exists on this server.<br />";
		}
	}

	if (!empty($dbErrorOutput)) // If an error exists, display it and return
	{
		returnError(202, $dbErrorOutput, false);
		returnToMainPage(getStartPage());
		return 0;
	}
	else
	{
		foreach (array('control', 'one', 'two') as $databaseNamePrefix)
		{
			// Attempt to create the databases
			$feedDatabase->query("CREATE DATABASE " . $arrFields['name'][1] . "_$databaseNamePrefix;", false);
			if ($feedDatabase->error)
			{
				returnToMainPage(getStartPage());
				return 0;
			}
			else
			{
				returnMessage(1002, "Created database '" . $arrFields['name'][1] . "_$databaseNamePrefix'", false);
			}

			// Create the database user
			$feedDatabase->query("GRANT ALL ON " . $arrFields['name'][1] . "_$databaseNamePrefix.* TO '" . $arrFields['databaseusername'][1] . "'@'localhost'"
				. " IDENTIFIED BY '" . $arrFields['databasepassword'][1] . "';", false);
			if ($feedDatabase->error)
			{
				returnToMainPage(getStartPage());
				return 0;
			}
		} 
	}
	
	// Generate the MySQL INSERT query from the array data
	$query = "INSERT INTO `frontend_sites`";
	
	$columnString = "(";
	$valuesString = "VALUES (";
	
	$keywordCount = 0;
	foreach ($arrFields as $column=>$arrMetaField)
	{
		list($type, $value) = $arrMetaField;
		
		// Create the keyword update query
		if ($column == 'keywords')
		{
			$keywordInsertQuery = "INSERT INTO `frontend_sites_keywords` (`site_id`, `keyword`) VALUES ";
			if ($type == 'array')
			{
				// For some reason, the order of the keywords is getting reversed each time
				// they are inserted. This line will reverse the reversal, allowing the keywords
				// to remain in the order selected by the user. 
				$value = array_reverse($value);
				
				foreach ($value as $keywordValue)
				{
					$keywordInsertQuery .= " (*!*!*!, '". $keywordValue . "'), ";
					if (!empty($keywordValue))
					{
						$keywordCount++;
					}
				}
			}
			else
			{
				if (!empty($value))
				{
					$keywordInsertQuery .= " (*!*!*!, '" . $value . "');";
					$keywordCount = 1;
				}
			}
			
			$keywordInsertQuery = substr($keywordInsertQuery, 0, -2) . ";";
		}
		else
		{
			$columnString .= "`$column`, ";
			
			switch ($type)
			{
				case 'string':
					$valuesString .= "'$value', ";
					break;
				case 'integer':
					$valuesString .= "$value, ";
					break;
				default:
					returnError(301, 'Error in line ' . __LINE__ . ' of file ' . __FILE__ . '. Invalid type (\'' . $type . '\') detected in column ' . $column . '.', true);
					break;
			}
		}
	}
	$columnString = substr($columnString, 0, -2) . ")";
	$valuesString = substr($valuesString, 0, -2) . ")";
	
	$query .= " $columnString $valuesString;";
	
	$feedDatabase->query($query, false);
	if ($feedDatabase->error)
	{
		returnToMainPage();
		exit();
	}
	else
	{
		// Insert was successful
		
		// Get the ID of the new site
		$id = $feedDatabase->insert_id();
		
		returnMessage(1002, sprintf(LANGUAGE_CREATE_OBJECT_NAME, 'sub-site', $arrFields['name'][1]), false);
	}
	
	// If there are any keywords for this subsite, insert them now.
	if ($keywordCount)
	{
		$keywordInsertQuery = str_replace('*!*!*!', $id, $keywordInsertQuery);
		
		// Execute the keyword insert query
		$feedDatabase->query($keywordInsertQuery, false);
		if ($feedDatabase->error)
		{
			//returnError(902, $keywordInsertQuery, false, $adminLink);
			returnToMainPage();
			exit();
		}
		else
		{
			$keywordMessage = "Inserted $keywordCount new keyword";
			if ($keywordCount > 1)
			{
				$keywordMessage .= 's';
			}
			$keywordMessage .= " for bsite '" . $arrFields['name'][1] . "'.";
			returnMessage(1000, $keywordMessage, false);
		}
	}
	
	$createVhostResponse = createApacheVhost($arrFields['url'][1]);
	
	if ($createVhostResponse == 0)
	{
		returnMessage(1000, "Apache Vhost for '" . $arrFields['url'][1] . "' was successfully created.", false);
	}
	
	returnToMainPage();
	exit();
}

/**
 * This function will gather stats from the control database for this site
 * and present them in an easy-to-read manner.
 */
function showStatistics()
{
	global $feedDatabase, $module;
	
	$siteid = getParameter('siteid');
	
	// Gather the database connection details for this site
	$feedDatabase->query("SELECT `name`, `displayName`, `databaseUsername`, `databasePassword`, `databaseHost` FROM `frontend_sites` WHERE `id`=$siteid LIMIT 1;");
	
	if ($feedDatabase->rowCount() < 1)
	{
		dhError("The site with ID $siteid does not exist in the database.", 'showTable');
	}
	
	$databaseConnectionDetails = $feedDatabase->firstObject();
	
	if (empty($databaseConnectionDetails->name) || empty($databaseConnectionDetails->databaseUsername) || empty($databaseConnectionDetails->databaseHost))
	{
		dhError("The database connection information for this site is incomplete. The database cannot be accessed.", 'showTable');
	}
	
	returnMessage(1000, "Viewed statistics for site :" . $databaseConnectionDetails->displayName, false);
	
	// Connect to the control database
	$siteDb = new DatabaseConnection($databaseConnectionDetails->databaseHost, $databaseConnectionDetails->databaseUsername, $databaseConnectionDetails->databasePassword, $databaseConnectionDetails->name . '_control');

	// Get the date range, if requested (Format: Y-m-d H:i:s)
	$startDate = urldecode(getParameter('startdate', false));
	$endDate = urldecode(getParameter('enddate', false));
	
	if (false === strtotime($startDate))
	{
		$startDate = false;
	}
	
	if (false === strtotime($endDate))
	{
		$endDate = false;
	}
	
	// Construct the date contraint for the database query
	if (!empty($startDate))
	{
		if (!empty($endDate))
		{
			$queryDateConstraint = " (`date` >= '$startDate' AND `date` <= '$endDate')";
		}
		else
		{
			$queryDateConstraint = " `date` >= '$startDate' ";
		}
	}
	else if (!empty($endDate))
	{
		$queryDateConstraint = " `date` <= '$endDate'";
	}
	else
	{
		$queryDateConstraint = false;
	}
	
	// Get the number of searches
	$searchCountQuery = "SELECT COUNT(*) FROM `searchLog` WHERE `offset`=0";
	if (!empty($queryDateConstraint))
	{
		$searchCountQuery .= " AND $queryDateConstraint";
	}
	$siteDb->query($searchCountQuery);
	$searchCount = $siteDb->firstField();
	
	// Get the number of visitors
	$visitorCountQuery = "SELECT COUNT(DISTINCT `ipAddress`) FROM `searchLog`";
	if (!empty($queryDateConstraint))
	{
		$visitorCountQuery .= "WHERE $queryDateConstraint";
	}
	$siteDb->query($visitorCountQuery);
	$visitorCount = $siteDb->firstField();
	
	// Get the number of result pages viewed
	$pageViewCountQuery = "SELECT COUNT(*) FROM `searchLog`";
	if (!empty($queryDateConstraint))
	{
		$pageViewCountQuery .= "WHERE $queryDateConstraint";
	}
	$siteDb->query($pageViewCountQuery);
	$pageViewCount = $siteDb->firstField();
	
	// Get the average number of results per search
	$averageResultPerSearchQuery = "SELECT AVG(`totalResults`) FROM `searchLog` WHERE `offset`=0";
	if (!empty($queryDateConstraint))
	{
		$averageResultPerSearchQuery .= " AND $queryDateConstraint";
	}
	$averageResultPerSearchQuery .= " GROUP BY `offset`";
	$siteDb->query($averageResultPerSearchQuery);
	$averageResultsPerSearch = $siteDb->firstField();
	
	// Get the number of outbound clicks
	$outboundClicksQuery = "SELECT COUNT(*) FROM `outgoingLinks`";
	if (!empty($queryDateConstraint))
	{
		$outboundClicksQuery .= " WHERE $queryDateConstraint";
	}
	$siteDb->query($outboundClicksQuery);
	$outboundClicks = $siteDb->firstField();
	
	include_once "includes/pageContentTable.class.php";
	
	$arrDataRows = array();
	
	if ($visitorCount)
	{
		$searchesPerVisitor = round($searchCount/$visitorCount, 1);
		$clicksPerVisitor = round($outboundClicks/$visitorCount, 1);
	}
	else
	{
		$searchesPerVisitor = 0;
		$clicksPerVisitor = 0;
	}
	
	if ($searchCount)
	{
		$pagesPerSearch = round($pageViewCount/$searchCount, 1);
	}
	else
	{
		$pagesPerSearch = 0;
	}
	
	$arrDataRows[] = array('', array(
			array('valign="top" style="text-align: center"', $visitorCount),
			array('valign="top" style="text-align: center"', $searchCount . "<br /><span class=\"additional_text\">$searchesPerVisitor searches/visitor</span>"),
			array('valign="top" style="text-align: center"', round($averageResultsPerSearch)),
			array('valign="top" style="text-align: center"', $pageViewCount . "<br /><span class=\"additional_text\">$pagesPerSearch page(s)/search</span>"),
			array('valign="top" style="text-align: center"', $outboundClicks . "<br /><span class=\"additional_text\">$clicksPerVisitor click(s)/visitor</span>")
			));
	
	// Create an array of date ranges to be included in the date select control.
	$dateRanges = array(
			"All Time" => "",
			"Today" => "&startdate=" . urlencode(date('Y-m-d 00:00:01')),
			"Last 24 hours" => "&startdate=" . urlencode(date('Y-m-d%20H:i:s', time() - 86400)) . "&enddate=" . urlencode(date('Y-m-d%20H:i:s')),
			"Last 7 days" => "&startdate=" . urlencode(date('Y-m-d%20H:i:s', time() - 604800)) . "&enddate=". urlencode(date('Y-m-d%20H:i:s'))
		);
	$dateSelector = "<select onchange=\"changeDate(this, '" . $_SERVER['PHP_SELF'] . "?ak=1&module=$module&task=statistics&siteid=$siteid')\">";
	foreach ($dateRanges as $label => $location)
	{
		$dateSelector .= "<option value=\"$location\"";
		if ($label == urldecode(getParameter('datelabel', false)))
		{
			$dateSelector .= " selected=\"selected\"";
		}
		else
		{
			echo "<!--\n$location\n" . ('&startdate=' . urlencode(getParameter('startdate', false)) . '&enddate=' . urlencode(getParameter('enddate', false))) . "-->";
		}
		$dateSelector .= ">$label</option>";
	}
	$dateSelector .= "</select>";
	
	$arrAddendum = array($dateSelector);
			
	$theTable = new pageContentTable("Statistics for " . $databaseConnectionDetails->displayName, array('Visitors', 'Searches', 'Average results per search', 'Pages viewed', 'Outbound clicks'), null, null, $arrDataRows, $arrAddendum);
	
	if ($theTable->error())
	{
		returnError(301, $theTable->error(), true);
	}
	else
	{
		echo $theTable->createTable();
	}
}

function showTable()
{
	global $feedDatabase, $module, $moduleName;
	
	returnMessage(1000, LANGUAGE_VIEWED_MAIN_PAGE, false);
	
	// Set the orderby parameter
	if (isset($_REQUEST['paging_orderby']) && !empty($_REQUEST['paging_orderby']))
	{
		switch ($_REQUEST['paging_orderby'])
		{
			default:
				$orderbyString = "ORDER BY `id` ASC";
				break;
		}
	}
	else
	{
		$orderbyString = "ORDER BY `id` ASC";
	}
	
	// Get a count of all available subsites
	$feedDatabase->query("SELECT COUNT(*) as `count` FROM `frontend_sites`");
	
	include_once "includes/pagination.class.php";
	
	$paginator = new Pagination($module, $feedDatabase->rowCount());

	$feedDatabase->query("SELECT `id`, `name`, `displayName`, `databaseUsername`, `databasePassword`, `databaseHost`, `rotateProducts` FROM `frontend_sites` $orderbyString " . $paginator->getLimitString());
	
	// Include the table-creation class
	include_once "includes/pageContentTable.class.php";
	
	// Populate $arrDataRows with the subsite information
	$arrDataRows = array();
	foreach ($feedDatabase->objects() as $row)
	{
		$actionButtons = null;
		/**
		 * Disabled. All subsites will be created, edit, and removed by developers to ensure
		 * that the proper databases are created/destroyed, and that all links are in place.
		 */
		/*
		if (isPermitted('edit', $module))
		{
			$actionButtons .= "<a href=\"#\" onclick=\"showEditDiv(" . $row->id . ", '" . ADMINPANEL_WEB_PATH . "', '$moduleName', '" . $_SERVER['PHP_SELF'] . "', $module);\"><img src=\"" .  ADMINPANEL_WEB_PATH . "/images/edit.png\" alt=\"Edit\" /></a>";
		}
		if (isPermitted('delete', $module))
		{
			$actionButtons .= "<a href=\"#\" onclick=\"confirmSubsiteDelete('" . $row->displayName . "', " . $row->id . ", '" . ADMINPANEL_WEB_PATH . "', '$module');\"><img src=\"" . ADMINPANEL_WEB_PATH. "/images/delete.png\" alt=\"Delete\" /></a>";
		}
		*/
		if (isPermitted('edit', $module))
		{
			// Create a button for the 'load products' action
			$actionButtons .= "<a href=\"" . $_SERVER['PHP_SELF'] . "?ak=1&module=$module&task=manageProducts&siteid=" . $row->id . "\"><img src=\"" . ADMINPANEL_WEB_PATH . "/images/glass.png" . "\" alt=\"Manage Products...\" />";	
		}
		
		$productsLink = "<a href=\"" . $_SERVER['PHP_SELF'] . "?ak=1&module=$module&task=manageProducts&siteid=" . $row->id . "\">View Products...</a>";
		
		$statisticsLink = "<a href=\"" . $_SERVER['PHP_SELF'] . "?ak=1&module=$module&task=statistics&siteid=" . $row->id . "\">View statistics...</a>";

		// Connect to the frontend site control database and determine if any unread messages are waiting
		if (!empty($row->databaseHost))
		{
			$frontendDB = new DatabaseConnection($row->databaseHost, $row->databaseUsername, $row->databasePassword, $row->name . '_control');
			$frontendDB->query("SELECT SUM(`viewed`) as `numberViewed`, COUNT(*) AS `numberTotal` FROM `user_messages`");
			
			$messageCountRow = $frontendDB->firstObject();
			$totalMessages = $messageCountRow->numberTotal;
			$unreadMessages = $totalMessages - $messageCountRow->numberViewed;
			
			if (!$totalMessages)
			{
				$messageLink = "0 messages";
			}
			else if ($totalMessages && !$unreadMessages)
			{
				if ($totalMessages == 1)
				{
					$messagesString = "1 message";
				}
				else
				{
					$messagesString = "$totalMessages messages";
				}
			}
			else
			{
				if ($unreadMessages == 1)
				{
					$messagesString = "<b>1 new</b>";
				}
				else
				{
					$messagesString = "<b>$unreadMessages new</b>";
				}
				
				if ($totalMessages == 1)
				{
					$messagesString .= " (1 total)";
				}
				else
				{
					$messagesString .= " ($totalMessages total)";
				}
			}
			
			// Wrap the string in a link
			if ($totalMessages)
			{
				$messageLink = "<a href=\"" . ADMINPANEL_WEB_PATH . "/index.php?ak=1&module=$module&task=displayMessages&siteid=" . $row->id . "\">$messagesString</a>";
			}
		}
		else
		{
			// There is no database host listed, so we will assume that no database has been
			// created for this subsite.
			$messageLink = "0 messages";
		}
		
		$arrListOfColumns = array('Name', 'Products', 'Statistics', 'Messages');
		$arrDataRows[] = array('', array(
			array('', htmlspecialchars($row->displayName)),
			array('', $productsLink),
			array('', $statisticsLink),
			array('', $messageLink)
		));
		
		unset ($frontendDB);
	}
	
	/**
	 * Disabled. All subsites will be created by a developer. This will ensure 
	 * that all databases are created properly and linked.
	 */
	/*
	if (isPermitted('create', $module))
	{
		$action = "showNewDiv('" . ADMINPANEL_WEB_PATH . "', '" . $moduleName . "', '" . $_SERVER['PHP_SELF'] . "', $module);";
	}
	else
	{
		$action = '';
	}
	*/
	$action = '';
	
	// Create an array to hold the "addendum" to the table
	$arrAddendum = array(
		$paginator->generateLinkBar()
	);
	
	// Create the table
	$theTable = new pageContentTable('Sub-Sites', $arrListOfColumns, 'Create Sub-Site', $action, $arrDataRows, $arrAddendum);
	
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