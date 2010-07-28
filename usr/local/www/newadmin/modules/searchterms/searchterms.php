<?php

// Make sure this script is not called directly
if (!defined('APP_NAME'))
{
	die("This script cannot be accessed directly.");
}

define('SEARCHTERMS_DEFAULT_RECORD_COUNT', 1000);

$task = getTask();

switch ($task)
{
	default:
		showTable();
		break;
}

function showTable()
{
	global $dealhuntingDatabase, $module;
	
	returnMessage(1000, LANGUAGE_VIEWED_MAIN_PAGE, false);
	
	if (isset($_REQUEST['paging_orderby']) && !empty($_REQUEST['paging_orderby']))
	{
		switch($_REQUEST['paging_orderby'])
		{
			case 'hit':
				$orderbystring = " ORDER BY hit DESC";
				break;
			case 'count':
				$orderbystring = " ORDER BY num DESC";
				break;
			case 'term':
			default:
				$orderbystring = " ORDER BY term";
				break;
		}
	}
	else
	{
		$orderbystring =  "ORDER BY term";
	}
	
	$view = "all";
	$viewstring = "";
	$groupbystring = " GROUP BY term,hit";
	if (isset($_REQUEST['viewrecords']) && !empty($_REQUEST['viewrecords']))
	{
		switch ($_REQUEST['viewrecords'])
		{
			case 'misses':
				$view = "misses";
				$viewstring = " WHERE hit=0";
				$groupbystring = " GROUP BY term";
				break;
			case 'hits':
				$view = "hits";
				$viewstring = " WHERE hit=1";
				$groupbystring = " GROUP BY term";
				break;
			case 'all':
			default:
				$view = "all";
				$viewstring = "";
				$groupbystring = " GROUP BY term, hit";
				break;
		}
	}
	
	$rowCountQuery = "SELECT"
		. " count(`hit`) as `count`"
		. " FROM " . DEALHUNTING_SEARCHTERMS_TABLE
		. " $viewstring $groupbystring;";
	
	$dealhuntingDatabase->query($rowCountQuery);
	$rowCount = $dealhuntingDatabase->rowCount();
	
	// Include the pagination class
	include "includes/pagination.class.php";
	
	$paginator = new Pagination($module, $rowCount);
	
	// Set the paginator "extraUrlParameters" property
	if (isset($_REQUEST['viewrecords']) && !empty($_REQUEST['viewrecords']))
	{
		switch ($_REQUEST['viewrecords'])
		{
			case 'misses':
				$paginator->extraUrlParameters = "&viewrecords=misses";
				break;
			case 'hits':
				$paginator->extraUrlParameters = "&viewrecords=hits";
				break;
			case 'all':
			default:
				$paginator->extraUrlParameters = "&viewrecords=all";
				break;
		}
		
	}
	
	// Assemble the query string
	$query = "SELECT"
		. " DISTINCT `term`, `hit`, count(`hit`) as `num`"
		. " FROM " . DEALHUNTING_SEARCHTERMS_TABLE
		. " $viewstring $groupbystring $orderbystring " . $paginator->getLimitString();

	$dealhuntingDatabase->query($query);
	
	// Include the table-creation class
	include_once "includes/pageContentTable.class.php";
	
	foreach ($dealhuntingDatabase->objects() as $row)
	{
		$arrDataRows[] = array('', array(
			array('', htmlentities($row->term)),
			array('', $row->hit),
			array('', $row->num)
		));
	}
	
	// Set the table action
	$action = null;

	// Create an element to hold the Filter-By controls
	$filterControls  = "<div class=\"filter_controls\"><form action=\"" . $_SERVER['PHP_SELF'] . "\" method=\"GET\">";
	$filterControls .= "Sort by: <select name=\"paging_orderby\"><option value=\"term\"";
	if ($paginator->orderby == 'term')
	{
		$filterControls .= " selected=\"selected\"";
	}
	$filterControls .= ">Term</option><option value=\"hit\"";
	if ($paginator->orderby == 'hit')
	{
		$filterControls .= " selected=\"selected\"";
	}
	$filterControls .= ">Hit</option><option value=\"count\"";
	if ($paginator->orderby == 'count')
	{
		$filterControls .= " selected=\"selected\"";
	}
	$filterControls .= ">Count</option></select>&nbsp;";
	$filterControls .= "View: <select name=\"viewrecords\"><option value=\"all\"";
	if ($paginator->extraUrlParameters == "&viewrecords=all")
	{
		$filterControls .= " selected=\"selected\"";
	}
	$filterControls .= ">All</option><option value=\"hits\"";
	if ($paginator->extraUrlParameters == "&viewrecords=hits")
	{
		$filterControls .= " selected=\"selected\"";
	}
	$filterControls .= ">Hits</option><option value=\"misses\"";
	if ($paginator->extraUrlParameters == "&viewrecords=misses")
	{
		$filterControls .= " selected=\"selected\"";
	}
	$filterControls .=">Misses</option></select>";
	$filterControls .= "<input type=\"submit\" value=\"Apply Filter\">";
	$filterControls .= "<input type=\"hidden\" name=\"paging_spage\" value=\"" . $paginator->spage . "\" />";
	$filterControls .= "<input type=\"hidden\" name=\"paging_rpp\" value=\"" . $paginator->rpp . "\" />";
	$filterControls .= "<input type=\"hidden\" name=\"module\" value=\"" . $module . "\" />";
	$filterControls .= "</form></div>";
	
	// Create an array to hold the "addendum" to the table
	$arrAddendum = array(
		$filterControls,
		$paginator->generateLinkBar()
	);
	
	// Create the table
	$theTable = new pageContentTable('Search Terms', array('Search Term', 'Hit', 'Count'), null, null, $arrDataRows, $arrAddendum);

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