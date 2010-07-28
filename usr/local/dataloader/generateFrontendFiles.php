<?php

/*
 * Updates:
 * 
 * 20100517: Modified the timetable on which this script runs. Currently it runs constantly. This modification will add
 * a check to determine the most recent run time of the feedFieldMap.php script. If feedFieldMap.php has run more recently
 * than this script, fresh data is available and we can generate new files. If feedFieldMap.php has not processed new files
 * since the most recent run of this script, this script will sleep until fresh files are available. -gcummins
 */

include_once "config.inc.php";
define('PROCESS_NAME', FRONTEND_GENERATOR_PROCESS_NAME);
define('LOG_FILE_NAME', FRONTEND_GENERATOR_LOGFILE);

include "include/functions.php";
include "include/db.class.php";

define('SEARCHSITE_OUTPUT_PATH', GLOBAL_APP_PATH . 'searchsite_dump_files');

declare(ticks=1); // Global callback for the signal handler.

// Fork as a daemon
$pid = pcntl_fork();
if ($pid < 0)
{
	exit("Error: Could not fork.\n");
}
else if ($pid) // This is the parent
{
	exit;
}
else
{
	// Detach from the controlling terminal
	$sid = posix_setsid();
	if ($sid < 0)
	{
		exit("Error: Count not enter daemon mode.\n");
	}
}

if (isset($db))
{
	unset ($db);
}

while (true)
{
	// Connect to the database
	$db = new DatabaseConnection(DATABASE_HOST, DATABASE_USER, DATABASE_PASSWORD, DATABASE_NAME);
	
	statusReport(PROCESS_NAME, 'awake');
	
    $lastRunOfGenerateFilesScript = getRunTimeOfGenerateFilesScript();
    $lastRunOfFeedFieldMapScript = getRuntimeOfFeedFieldMapScript();
	
    if (FALSE == $lastRunOfFeedFieldMapScript || ($lastRunOfGenerateFilesScript > $lastRunOfFeedFieldMapScript))
    {
        logger("The feedmapping script has not run since the last time this script processed files. Sleeping for " . FRONTEND_SLEEP_TIME_BETWEEN_LOOPS . " seconds." , LEVEL_STATUS);
        
        statusReport(PROCESS_NAME, 'sleep');
        
        unset($db);
        sleep(FRONTEND_SLEEP_TIME_BETWEEN_LOOPS);

        continue; // Skip to the beginning of the while() loop.
    }
    
	// TODO: Need to lock feedFieldMap from accessing tables while the dumps are being created
	// If feedFieldMap is running, it may change tables that this script is dumping.
	// Determine if feedFieldMap is running.
	// - If it is, wait until it ends.
	// - if it is not, block it from running until this script ends.
	$db->query("SELECT `pid` FROM `activeProcesses` WHERE `processName` = '" . FEEDMAPPING_PROCESS_NAME . "' LIMIT 1;");
	
	// Retrieve a list of frontend sites
	$db->query("SELECT `id`, `name`, `outputPath`, `vendorExclusionMethod` FROM `frontend_sites` WHERE `rotateProducts` = 1");

	if ($db->rowCount())
	{
		if ($db->rowCount() == 1)
		{
			logger("There is one site to process.", LEVEL_INFORMATION);
		}
		else
		{
			logger("There are " . $db->rowCount() . " sites to process.", LEVEL_INFORMATION);
		}
		
		foreach ($db->objects() as $site)
		{
			$siteStartTime = time();	
			
			// Check the stats database to see if either
			// a) no entry exists for this site, or
			// b) sufficient time has passed since the last time we processed this site.
			logger("Checking the last run time for site '" . $site->name . "'.", LEVEL_DATABASE_OPERATION);
			$db->query("SELECT `endTime`, `sleepTime` FROM `stats_frontendfilegenerator` WHERE `siteName`='" . $site->name . "' ORDER BY `endTime` DESC LIMIT 1;"); // Get only the most recent entry

			if ($db->rowCount())
			{
				$statRow = $db->firstObject();
				if ((time() - $statRow->endTime) < $statRow->sleepTime)
				{
					logger((time() - $statRow->endTime) . " seconds have elapsed since the last run for this site, which is less than the specified sleep time. Skipping this site for now.", LEVEL_DEBUG);
					continue; // Skip to the next site in the loop
				}
				else
				{
					logger((time() - $statRow->endTime) . " seconds have elapsed since the last run for this site, which is greater than the specified sleep time.", LEVEL_DEBUG);
					logger("Processing frontend site '" . $site->name . ".'", LEVEL_INFORMATION);
				}
			}
			
			// We will conduct several tests to ensure that we have good data
			if (substr($site->outputPath, -1) != '/')
			{
				logger("The output path for the site '" . $site->name. "' does not contain a trailing '/'. The path must be corrected before files can be processed.", LEVEL_DATA_WARNING);
				continue;
			}
			
			if (empty($site->outputPath) || !is_dir(GLOBAL_APP_PATH . $site->outputPath))
			{
				logger("The output path for site '" . $site->name . "' is invalid. Files cannot be processed.", LEVEL_DATA_WARNING);
				continue;
			}
			if (!is_writable(GLOBAL_APP_PATH . $site->outputPath))
			{
				logger("The output path is not writable. Files cannot be processed.", LEVEL_DATA_WARNING);
				continue;
			}
			// Determine if this site has a "ready to transfer" file in the output location.
			// If it does, we will not process files for this site. The frontend site needs to remove the
			// existing files before we will reprocess.
			if (file_exists(GLOBAL_APP_PATH . $site->outputPath . FRONTEND_READY_TO_TRANSFER_FILENAME))
			{
				logger("The \"ready to transfer\" file exists. This script will not process new files until the frontend has retrieved the existing files.", LEVEL_INFORMATION);
				
				statusReport(PROCESS_NAME, 'sleep');
				
				logger("Sleeping for " . FRONTEND_SLEEP_TIME_BETWEEN_LOOPS . " seconds.", LEVEL_STATUS);
				
				unset($db);
                sleep(FRONTEND_SLEEP_TIME_BETWEEN_LOOPS);
        
                continue; // Skip to the beginning of the while() loop.
			}
			
			// All checks have passed, so we are ready to generate the files.
			$arrTablesToProcess = getAllowedProductTables($site->id, $site->vendorExclusionMethod);
			
			$numberOfTablesToProcess = count($arrTablesToProcess);
			statusReport(PROCESS_NAME, 'process', 'Files: ' . $numberOfTablesToProcess);
			
			if (!$numberOfTablesToProcess)
			{
				logger("There are no tables to process.", LEVEL_DATA_WARNING);
			}
			else
			{
				if (count($numberOfTablesToProcess) == 1)
				{
					logger("There is one table to process.", LEVEL_INFORMATION);
				}
				else
				{
					logger("There are $numberOfTablesToProcess tables to process.", LEVEL_INFORMATION);
				}
			}

			$tableCounter = 0;
			foreach ($arrTablesToProcess as $table)
			{
				logger("Creating data outfile from table '$table.'", LEVEL_DATABASE_OPERATION);
				
				$mayCreateFile = true;
				if (file_exists(GLOBAL_APP_PATH . $site->outputPath . $table . ".txt"))
				{
					if (false === @unlink(GLOBAL_APP_PATH . $site->outputPath . $table . ".txt"))
					{
						logger("The file '" . GLOBAL_APP_PATH . $site->outputPath . $table . ".txt' already exists, but this script is unable to delete it. Please delete it manually to ensure that the products are up to date.", LEVEL_DATA_WARNING);
						$mayCreateFile = false;
					}
				}
				if ($mayCreateFile)
				{
					// Added a null field to allow for auto_increment values to work in the frontend database.
					$createTableQuery = "SELECT null, " . getFieldList()
						. " INTO OUTFILE '"
						. GLOBAL_APP_PATH . $site->outputPath . $table . ".txt'"
						. " FIELDS OPTIONALLY ENCLOSED BY '\"' FROM $table"
						. " WHERE"
							. " ((`Currency` = 'USD' OR `Currency` = 'usd' OR `Currency` = 'US' OR `Currency` = 'us' OR `Currency` = '')"
							. " AND ((`StartDate` <= NOW() AND `EndDate` >= NOW())"
							. " OR (`StartDate` = '0000-00-00 00:00:00' AND `EndDate` = '0000-00-00 00:00:00')"
							. " OR (`StartDate` = '0000-00-00 00:00:00' AND `EndDate` >= NOW())"
							. " OR (`StartDate` <= NOW() AND `EndDate` = '0000-00-00 00:00:00')));";
					$db->query($createTableQuery);
					$tableCounter++;
				}
			}
			
			logger("Creating the \"ready to transfer\" flag file.", LEVEL_FILE_OPERATION);
			if (false === fopen(GLOBAL_APP_PATH . $site->outputPath . FRONTEND_READY_TO_TRANSFER_FILENAME, 'w'))
			{
				$message = "Failed to create the \"ready to transfer\" flag file (" . GLOBAL_APP_PATH . $site->outputPath . FRONTEND_READY_TO_TRANSFER_FILENAME . "). Exiting...";
				logger($message, LEVEL_CRITICAL);
				shutdown($message);
			}
			
			logger("Creating statistics entry for '" . $site->name . "'.", LEVEL_DATABASE_OPERATION);
			//$db->query("INSERT INTO `stats_frontendfilegenerator` (`startTime`, `endTime`, `siteName`, `filesCreated`, `sleepTime`) VALUES ($siteStartTime, " . time() . ", '" . $site->name . "', $tableCounter, " . (FRONTEND_SLEEP_TIME_AFTER_FILE_GENERATION) . ");");
			$db->query("INSERT INTO `stats_frontendfilegenerator` (`startTime`, `endTime`, `siteName`, `filesCreated`) VALUES ($siteStartTime, " . time() . ", '" . $site->name . "', $tableCounter);");
		}
	}
	else
	{
		logger("There are no sites to process at this time.", LEVEL_INFORMATION);
	}
	
	unset($db);
}

function getFieldList()
{
	// A list of all fields that are copied from the master database to the frontend database.
	//return "ProgramName, ProgramURL, LastUpdated, ProductName, Keywords, LongDescription, InterimDescription, ShortDescription, BriefDescription, SKU, Manufacturer, ManufacturerID, UPC, ISBN, Currency, SalePrice, Price, RetailPrice, FromPrice, BuyURL, AddToCartURL, ImageURL, ImpressionURL, Category, SecondaryCategory, CategoryID, CategoryCrumbs, Author, Artist, Title, Publisher, Label, Format, Special, PromotionalText, StartDate, EndDate, ShippingCost";
	$fieldList  = "ProgramName, ProductName, Keywords,";
	$fieldList .= " IF (LENGTH(`BriefDescription`) > 0, SUBSTRING(`BriefDescription`, 1, 150), IF (LENGTH(`ShortDescription`) > 0, SUBSTRING(`ShortDescription`, 1, 150), IF (LENGTH(`InterimDescription`) > 0, SUBSTRING(`InterimDescription`, 1, 150), SUBSTRING(`LongDescription`, 1, 150)))) as `description`, ";
	$fieldList .= " Currency, IF(`SalePrice` > 0, `SalePrice`, IF(`Price` > 0, `Price`, IF(`RetailPrice` > 0, `RetailPrice`, NULL))) AS `price`, BuyURL, ImageURL, ImpressionURL, StartDate, EndDate";
	return $fieldList;	
}

function getAllowedProductTables($siteId, $vendorExclusionMethod)
{
	// Get a list of product tables with their update times from the specified database.
	// Ensure that the table names are in the format of : al_0?_???
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $db;
	
	// Sites can use one of two vendor exclusion methods:
	//  0. All vendors are allowed except those explicitly specified
	//  1. No vendors are allowed except those explicity specified
	
	// Get a list of all available product tables
	$db->query("SHOW TABLE STATUS WHERE NAME LIKE 'al_0%'");

	if (!$db->rowCount())
	{
		return array();
	}
	
	$arrAvailableTables = array();
	foreach ($db->objects() as $row)
	{
		if (substr($row->Name, -7) != 'datapro')
		{
			$arrAvailableTables[] = $row->Name;
		}
	}
	
	// Get a list of specified vendors for this site
	$db->query("SELECT `vendor_table_name` from `frontend_sites_vendors` WHERE `frontend_site_id` = $siteId;");
	
	$arrSpecifiedTables = array();
	foreach ($db->objects() as $row)
	{
		if (substr($row->vendor_table_name, -7) != 'datapro')
		{
			$arrSpecifiedTables[] = $row->vendor_table_name;
		}
	}
	
	if ($vendorExclusionMethod == 0)
	{
		$arrTablesToProcess = array_diff($arrAvailableTables, $arrSpecifiedTables);
	}
	else
	{
		$arrTablesToProcess = array_diff($arrSpecifiedTables, $arrAvailableTables);
	}

	return $arrTablesToProcess;
}

function getRuntimeOfFeedFieldMapScript()
{
    // Check the database to determine the last runtime of the 
    // script "feedFieldMap.php"
    global $db;
    
    // Retrieve the most recent 'start' and 'stop' times from the status report table
    $db->query("SELECT `timestamp` FROM `status_reports` WHERE `processName` = '" . FEEDMAPPING_PROCESS_NAME . "' AND `type`='stop' ORDER BY `timestamp` DESC LIMIT 1;");
    if (!$db->rowCount())
    {
        return false;
    }
    else
    {
        $stopTime = (int)$db->firstField();
    }
    $db->query("SELECT `timestamp` FROM `status_reports` WHERE `processName` = '" . FEEDMAPPING_PROCESS_NAME . "' AND `type`='start' ORDER BY `timestamp` DESC LIMIT 1;");
    if (!$db->rowCount())
    {
        return false;
    }
    else
    {
        $startTime = (int)$db->firstField();
    }
    
    // If the most recent start time is newer than the most recent stop time, feedmapping is either currently running or crashed.
    if ($startTime > $stopTime)
    {
        return false;
    }
    else
    {
        return $stopTime;
    }
}

function getRunTimeOfGenerateFilesScript()
{
    global $db;
    
    $db->query("SELECT `timestamp` FROM `status_reports` WHERE `processName` = '" . FRONTEND_GENERATOR_PROCESS_NAME . "' AND `type`='process' ORDER BY `timestamp` DESC LIMIT 1;");
    
    if ($db->rowCount())
    {
        return (int)$db->firstField();
    }
    else
    {
        return false;
    }
}
?>
