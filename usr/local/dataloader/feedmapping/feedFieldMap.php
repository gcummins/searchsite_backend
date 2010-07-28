<?php

/*
	File: feedFieldMap.php
	Author: George Cummins
	Date: 7/5/07
	Description: Map data in source-specific data tables into one master product table
	Specifics:
		Apply field maps where necessary
		Format data as needed (ie. length of descriptions)
		Insert a source field to identify the upstream feed provider
		Insert a Program (vendor) field for records where it is not included
*/

// Edit some php settings.
//ini_set('mysql.default_socket', '/tmp/mysql.sock');
//ini_set('error_reporting', E_ALL);
//ini_set('error_log', "/usr/local/dataloader/dataloader.err");
//ini_set('log_errors', '1');
//ini_set('log_errors_max_len', '2048');
//ini_set('display_errors', '1');
//ini_set('magic_quotes_runtime', '0');

include_once("../config.inc.php");
define('PROCESS_NAME', FEEDMAPPING_PROCESS_NAME);
define('LOG_FILE_NAME', FEEDMAPPING_LOGFILE);

include_once("../include/functions.php");
include_once("../include/speedcheck.php");
include_once("../include/db.class.php");

define('PID_FILE', FEEDMAPPING_PID_FILE);

setProcessStatusDefines();

// Determine if a feedFieldMap.php process is already running or if it is restricted
$db->query("SELECT `pid`, `unixStartTime`, `status` FROM `activeProcesses` WHERE `processName` = '" . FEEDMAPPING_PROCESS_NAME . "' LIMIT 1");
if ($db->rowCount())
{
	$row = $db->firstObject();
	while ($row->status == PROCESS_STATUS_BLOCKED)
	{
		logger("feedFieldMap.php is currently blocked from running by another process. Sleeping for " . FEEDMAPPING_SLEEP_TIME_WHEN_BLOCKED . " seconds.");
		unset($db); // Close the database connection to avoid a timeout
		sleep(FEEDMAPPING_SLEEP_TIME_WHEN_BLOCKED);
		$db = new DatabaseConnection(DATABASE_HOST, DATABASE_USER, DATABASE_PASSWORD, DATABASE_NAME); // Reconnect to the database
		$db->query("SELECT `pid`, `unixStartTime`, `status` FROM `activeProcesses` WHERE `processName` = '" . FEEDMAPPING_PROCESS_NAME . "' LIMIT 1"); // Run the query again
		if (!$db->rowCount())
		{
			break; // Stop checking if the entry in activeProcesses has been removed.
		}
		$row = $db->firstObject();
	}
}

// Create an entry in the statistics table
$statsStartTime = date('Y-m-d H:i:s');
$db->query("INSERT INTO `stats_feedfieldmap` (`start_time`) VALUES ('$statsStartTime')");
$statsVendorsProcessed = 0;
$statsRecordsProcessed = 0;

// Create an entry for the reporting system
statusReport(PROCESS_NAME, 'start');

// Check to ensure that no other instance of this script is running, and then create a locked PID file.
$pid = getmypid();

$pidFileHandle = null;	// This variable will be used via 'global' in the createPIDFile function.
						// This is necesary because the flock used in the function is broken when the
						// file handle goes out of scope. Using a global variable ensures that the 
						// file handle will not go out of scope when the function returns.
createPIDfile(GLOBAL_PID_DIRECTORY . FEEDMAPPING_PID_FILE, $pid);


// Tell dataloader to suspend
$restartDataloader = true; // Will be set to false later if dataloader is not currently running.

if (file_exists(GLOBAL_PID_DIRECTORY . DATALOADER_PID_FILE))
{
	// Retrieve the dataloader pid
	if (false === ($dataloaderPidFileHandle = fopen(GLOBAL_PID_DIRECTORY . DATALOADER_PID_FILE, 'r')))
	{
		logger("Unable to open the dataloader PID file for reading.", LEVEL_DATA_WARNING);
	}
	else
	{
		$dataloaderPID = fread($dataloaderPidFileHandle, filesize(GLOBAL_PID_DIRECTORY . DATALOADER_PID_FILE));

		logger("Telling dataloader to suspend.", LEVEL_STATUS);
		exec (GLOBAL_PATH_TO_KILL_COMMAND . " -SIGUSR1 $dataloaderPID", $arrKillCommandOutput, $return_var);

		if ($return_var == 0)
		{
			logger("Dataloader has suspended.", LEVEL_INFORMATION);
		}
		else
		{
			logger("Unable to suspend dataloader. The exit code of the kill command was: $return_var. The output of the command was: " . implode(' :: ', $arrKillCommandOutput));
			$restartDataloader = false;
		}
		fclose($dataloaderPidFileHandle);
	}	
}
else
{
	logger("Dataloader does not appear to be running.", LEVEL_INFORMATION);
	$restartDataloader = false;
}

// Add signal handlers
declare(ticks=1);
pcntl_signal(SIGTERM, "signal_handler");
pcntl_signal(SIGQUIT, "signal_handler");
pcntl_signal(SIGINT, "signal_handler");

// Notify all other processes that this process is running
logger("Setting '" . FEEDMAPPING_PROCESS_NAME . "' to be an active process.", LEVEL_INFORMATION);
changeProcessStatus(FEEDMAPPING_PROCESS_NAME, PROCESS_STATUS_ACTIVE, $pid);

// Delete the 'Do Not Reindex' flag file, if it exists
if (file_exists(FEEDMAPPING_DO_NOT_REINDEX_FILENAME))
{
	@unlink(FEEDMAPPING_DO_NOT_REINDEX_FILENAME);
}

/*$arrFeedTypes = array("01" => array("id" => 1, "name" => "CJ"),
		"02" => array("id" => 2, "name" => "LinkShare"),
		"03" => array("id" => 3, "name" => "Performics") );*/
$arrFeedTypes = getFeedTypes();

// Get a list of tables we wish to process
$arrTablesToProcess = getUnprocessedTables();

if (0 == count($arrTablesToProcess))
{
	logger("There are no new tables to process.", LEVEL_INFORMATION);
}
else
{	
	// Include the product class file
	include "class.product.php";
	
	foreach ($arrTablesToProcess as $destinationTable)
	{
		// Which source-specific table are we processing?
		// Later this will be automated so that we can process all available tables;
		$sourceDataTable = FEEDMAPPING_TEMPORARY_TABLE_PREFIX . $destinationTable;
	
		logger("Deleting table '$destinationTable' if it exists", LEVEL_DATABASE_OPERATION);
		$db->query("DROP TABLE IF EXISTS $destinationTable;");
		
		logger("Recreating table '$destinationTable.'", LEVEL_DATABASE_OPERATION);
		createProductTable($destinationTable);
			
		// Load data from the source table into the master table
		loadData($sourceDataTable, $destinationTable);
	
		// Mark this table as updated
		markAsUpdated($destinationTable);
	}
	
	logger("Finished mapping records into the permanent tables.", LEVEL_INFORMATION);
}

// Regenerate the sphinx.conf file for indexing
$statsAvailableVendors = createSphinxConfigurationFile();

// Check for "lost tables"
checkForLostTables();

// Finish the entry in the statistics table
$db->query("UPDATE `stats_feedfieldmap` SET `end_time`='" . date('Y-m-d H:i:s') . "', `vendors_processed`=$statsVendorsProcessed, `records_processed`=$statsRecordsProcessed, `available_vendors`=$statsAvailableVendors WHERE `start_time`='$statsStartTime';");

// Remove the entry for this script from the Active Processes list
$db->query("DELETE FROM `activeProcesses` WHERE `processName`='" . $_SERVER['SCRIPT_NAME'] . "';");

// Check to see if we should reindex the tables
$reindexTables = false;
if ($argc > 1)
{
	if (false !== (array_search('--reindex', $argv)))
	{
		$reindexTables = true;
	}
}

if ($reindexTables)
{
	logger("Closing the database connection.", LEVEL_STATUS);
	unset($db);
	
	// Reindex the tables
	logger("Reindexing the backend tables.", LEVEL_DATABASE_OPERATION);
	exec(GLOBAL_PATH_TO_INDEXER . ' --config ' . DATALOADER_PATH_TO_BACKEND_SPHINX_CONFIGURATION_FILE . ' --all --rotate', $arrReindexOutput, $return_var);
	if ($return_var == 0)
	{
		logger("Reindex was completed successfully.", LEVEL_INFORMATION);
		// TODO: examine the output of the reindex command and log the statistics (records indexex, etc);
	}
	else
	{
		logger("The reindexing process did not complete successfully, and exited with error code: $return_var. The command output was: " . implode(' :: ', $arrReindexOutput));
	}
	logger("Re-opening the database connection.", LEVEL_STATUS);
	$db = new DatabaseConnection(DATABASE_HOST, DATABASE_USER, DATABASE_PASSWORD, DATABASE_NAME);
}
else
{
	logger("Will not reindex tables based on request. Use --reindex to force reindexing.", LEVEL_INFORMATION);
}

changeProcessStatus(FEEDMAPPING_PROCESS_NAME, 'end', $pid);

statusReport(PROCESS_NAME, 'stop');

if ($restartDataloader)
{
	logger("Telling Dataloader to resume.", LEVEL_STATUS);
	exec(GLOBAL_PATH_TO_KILL_COMMAND . " -SIGUSR2 $dataloaderPID", $arrKillCommandOutput, $return_var);
	
	if ($return_var == 0)
	{
		logger("Dataloader resumed successfully.", LEVEL_INFORMATION);
	}
	else
	{
		logger("Dataloader failed to restart. The kill -SIGUSR2 command exited with code: $return_var. The command output was: " . implode(' :: ', $arrKillCommandOutput));
	}
}

deletePIDFile(GLOBAL_PID_DIRECTORY . FEEDMAPPING_PID_FILE);

function checkForLostTables()
{
	// It has been noted that occasionally there are tmp product tables in the database
	// that do not appear in tmp_compchecker, and are therefore orphaned. We need to determine
	// how these tables are orphaned (probably when dataloader or this script crashes), send 
	// a notice to the administrator, and possibly deal with the tables, either by deleting
	// them or processing them.
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $db;
	
	// First, get a list of tables from tmp_compchecker
	$db->query("SELECT `tablename` FROM `tmp_compchecker` WHERE `vardatetime` < " . (time() - (12*60*60)) . ";"); // Older than twelve hours
	$arrTablesInTmpCompchecker = array();
	foreach ($db->objects() as $tablename)
	{
		$arrTablesInTmpCompchecker[] = $tablename;
	}
	
	// Next, get a list of tmp tables in the database
	$db->query("SHOW TABLE STATUS WHERE `Name` LIKE 'tmp_al_%'");
	$arrTmpTables = array();
	foreach ($db->objects() as $tablename)
	{
		$arrTmpTables[] = $tablename;
	}
	
	// Now compare the two lists to find orphaned tables.
	
}

function createProductTable($tableName)
{
	// This function creates the master product table
	// This should only be needed on a first-install or during
	// debugging

	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $link, $db;

	$createTableSQL = "CREATE TABLE `$tableName` ("
				. " `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,"
				. " `ProgramName` VARCHAR(100) NOT NULL,"
				. " `ProgramURL` VARCHAR(2000) NOT NULL,"
				. " `LastUpdated` DATETIME NOT NULL,"
				. " `ProductName` VARCHAR(255) NOT NULL,"
				. " `Keywords` VARCHAR(500) NOT NULL default '',"
				. " `LongDescription` VARCHAR(3000) NOT NULL default '',"
				. " `InterimDescription` VARCHAR(2000) NOT NULL default '',"
				. " `ShortDescription` VARCHAR(500) NOT NULL default '',"
				. " `BriefDescription` VARCHAR(255) NOT NULL default '',"
				. " `SKU` VARCHAR(100) NULL,"
				. " `Manufacturer` VARCHAR (250) NOT NULL default '',"
				. " `ManufacturerID` VARCHAR(64) NULL,"
				. " `UPC` VARCHAR(15) NULL,"
				. " `ISBN` VARCHAR(64) NULL,"
				. " `Currency` VARCHAR(3) NOT NULL default 'USD',"
				. " `SalePrice` DECIMAL(10,2) NULL,"
				. " `Price` DECIMAL(10,2) NULL,"
				. " `RetailPrice` DECIMAL(10,2) NULL,"
				. " `FromPrice` DECIMAL(10,2) NULL,"
				. " `BuyURL` VARCHAR(2000) NOT NULL,"
				. " `AddToCartURL` VARCHAR(2000) NULL,"
				. " `ImageURL` VARCHAR(2000) NULL,"
				. " `ImpressionURL` VARCHAR(2000) NULL,"
				. " `Category` VARCHAR(300) NULL,"
				. " `SecondaryCategory` VARCHAR(2000) NULL,"
				. " `CategoryID` INT NULL,"
				. " `CategoryCrumbs` VARCHAR(2000) NULL,"
				. " `Author` VARCHAR(130) NULL,"
				. " `Artist` VARCHAR(130) NULL,"
				. " `Title` VARCHAR(130) NULL,"
				. " `Publisher` VARCHAR(130) NULL,"
				. " `Label` VARCHAR(130) NULL,"
				. " `Format` VARCHAR(64) NULL,"
				. " `Special` VARCHAR(5) NULL,"
				. " `PromotionalText` VARCHAR(300) NULL,"
				. " `StartDate` DATETIME NULL,"
				. " `EndDate` DATETIME NULL,"
				. " `ShippingCost` DECIMAL(10,2) NULL)"
			. " ENGINE=MyISAM DEFAULT CHARSET=latin1;";

	$db->query($createTableSQL);
	
	logger("Table `$tableName` was created successfully.");
}

function createSphinxConfigurationFile()
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $link, $db;

	logger("Creating Sphinx configuration file.", LEVEL_FILE_OPERATION);
	
	// First, check to see if any tables have been updated. If not, the index is up-to-date
	// and does not need to be recreated
	// TODO: Consider optimization. 'updated' does not currently have an index
	$query = "SELECT count(*) AS count FROM sphinxTableList WHERE updated=1;";
	$db->query($query);
	
	if (!$db->rowCount())
	{
		// There are no updated tables, so we need to notify the reindex process not to run

		// Create a file in the run/ directory that tells reindex.sh to restart dataloader and then
		// terminate.
		$fh = fopen(DO_NOT_REINDEX_FILENAME, 'w');
		fclose($fh);
		logger("No tables have been updated, so no sphinx.conf was generated.");
		return;
	}

	$query = "SELECT DISTINCT tablename FROM sphinxTableList;";
	$db->query($query);
	
	if (!$db->rowCount())
	{
		logger("There are no tables in sphinxTableList to process.", LEVEL_DATA_WARNING);
		exit(-1);
	}

	$arrSources = array();
	$output = "";

	$counter = 1000;
	
	foreach ($db->objects() as $row)
	{
		// Ensure that the target table exists and contains an ID field
		$tableIsValid = false;
		$query = "DESCRIBE `" . $row->tablename . "`";
		$db->query($query);
		
		foreach ($db->objects() as $fieldRow)
		{
			if (strtolower($fieldRow->Field) == 'id')
			{
				// This table exists and an ID field was found
				$tableIsValid = true;
				break;
			}
		}
		
		if ($tableIsValid)
		{
			// Add the new source configuration to the output string
			
			if ($counter == 1000) // See comment in the else statement, below.
			{
				$masterSourceName = $row->tablename;
				
				$output .= "
				source " . $row->tablename . "
				{
					type = mysql
					sql_host = localhost
					sql_user = rvoelker
					sql_pass = rv99105
					sql_db = datafeeds
					sql_sock = /tmp/mysql.sock
					sql_port = 3306
					sql_attr_uint = price
					sql_query = SELECT CONCAT('" . $counter . "', id) AS docid, ProductName, Keywords, LongDescription, Price*100 AS price FROM " . $row->tablename . " WHERE (StartDate <= NOW() AND EndDate >= NOW()) OR (StartDate = '0000-00-00 00:00:00' AND EndDate = '0000-00-00 00:00:00') OR (StartDate = '0000-00-00 00:00:00' AND EndDate >= NOW()) OR (StartDate <= NOW() AND EndDate = '0000-00-00 00:00:00');	
					sql_query_info = SELECT ProductName, LongDescription FROM " . $row->tablename . " WHERE id=SUBSTRING(\$id, 10)
				}";
			}
			else
			{
				// We can reduce the size of the configuration file (and the parsing time)
				// by removing duplicate elements from the source definition.
				// The elements are inherited from the first source ($masterSourceName)
				$output .= "
				source " . $row->tablename . " : $masterSourceName
				{				
					sql_query = SELECT CONCAT('" . $counter . "', id) AS docid, ProductName, Keywords, LongDescription, Price*100 AS price FROM " . $row->tablename . " WHERE (StartDate <= NOW() AND EndDate >= NOW()) OR (StartDate = '0000-00-00 00:00:00' AND EndDate = '0000-00-00 00:00:00') OR (StartDate = '0000-00-00 00:00:00' AND EndDate >= NOW()) OR (StartDate <= NOW() AND EndDate = '0000-00-00 00:00:00');
					sql_query_info = SELECT ProductName, LongDescription FROM " . $row->tablename . " WHERE id=SUBSTRING(\$id, 10)
				}";
			}
		
			// Add the table name to the source array for use later when we 
			// reference each of the sources in the index
			$arrSources[$counter] = $row->tablename;
		
			// Incremement the counter to make sure we have a unique ID
			$counter++;
	
			// Mark the table in sphinxTableList as not updated
			$toggleUpdatedFlagQuery = "UPDATE `sphinxTableList` SET `updated`=0 WHERE `tablename`='{$row->tablename}' LIMIT 1;";
			$db->query($toggleUpdatedFlagQuery);
		}
		else
		{
			// Remove the table from sphinxTableList
			logger("Table '" . $row->tablename . "' is not valid.", LEVEL_INFORMATION);
			$query = "DELETE FROM `sphinxTableList` WHERE `tablename`='" . $row->tablename . "'";
			$db->query($query);
		}
	}
	
	$output .= "
	index datafeeds
	{
	";
		foreach ($arrSources as $source)
		{
			$output .= "\tsource = $source\n\t";
		}
	
	$output .= "
		path = /usr/local/sphinx/var/data/dataloader
		html_strip = 1
		mlock = 0
		morphology = none
		stopwords = /usr/local/sphinx/var/stopwords.txt
		min_word_len = 3
		charset_type = sbcs
	}";
	
	$output .= "
	indexer
	{
		mem_limit = 256M
	}
	
	searchd
	{
		listen = 3312
		log = /usr/local/sphinx/var/log/searchd.log
		query_log = /usr/local/sphinx/var/log/query.log
		read_timeout = 5
		client_timeout = 300
		max_children = 0
		pid_file = /usr/local/sphinx/var/log/searchd.pid
		max_matches = 50000
		seamless_rotate = 1
		preopen_indexes = 0
		unlink_old = 1
		max_packet_size = 8M
		max_filters = 256
		max_filter_values = 4096
	}";

	$sphinxConfigurationFile = '/usr/local/dataloader/sphinx.conf';
	if (false === ($fh = fopen($sphinxConfigurationFile, 'w')))
	{
		shutdown("Unable to open Sphinx configuration file ($sphinxConfigurationFile) for writing.");
	}
	else
	{
		if (false === fwrite($fh, $output))
		{
			$writeFailedMessage = "Unable to write data to the configuration file '$sphinxConfigurationFile.'";
			logger($writeFailedMessage, LEVEL_CRITICAL);
			shutdown($writeFailedMessage);
		}

		if (false === fclose($fh))
		{
			$closeFailedMessage = "Unable to close the file '$sphinxConfigurationFile.'";
			logger($closeFailedMessage, LEVEL_DATA_WARNING);
		}
	}
	
	// We need to create an entry in the database for each of the number-to-tablename listings. For example, 1000000 = al_01_1, 1000001 = al_01_2.
	// This will be used by the frontend when retrieving products.
	
	// Ensure that we have tables in $arrSources
	if (!count($arrSources))
	{
		logger("No source tables were found to be processed.", LEVEL_DATA_WARNING);
		exit(0);
	}
	
	// First, truncate the existing table
	$query = "TRUNCATE tablenameMap";
	$db->query($query);
	
	// Next, create entries for each of the new mappings
	$query = "INSERT INTO `tablenameMap` (`number`, `tablename`) VALUES ";
	
	foreach ($arrSources as $number => $tablename)
	{
		$query .= "('$number', '$tablename'), ";
	}
	
	$query = substr($query, 0, -2) . ";";
	$db->query($query);

	// Need to add a section to restart Sphinx searchd, or at least notify it to rotate the indexes.

	// May also need a section in this script to take the 'frontend' database offline and replace
	// it with this newly-updated backend database, if we decide to go that route.
	
	logger("Done creating configuration file.", LEVEL_INFORMATION);
	
	return $counter;
}

function deleteDumpFile($fileLocation, $message=null)
{
	// Attempt to delete it the supplied file
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	// Set a default message if necessary
	if ($message == null)
	{
		$message = "The dump file '$fileLocation' exists. Attempting to delete...";
	}
	
	// Create a log entry
	logger($message, LEVEL_FILE_OPERATION);
	
	// Attempt to delete the file
	if (false === unlink($fileLocation))
	{
		logger("Unable to delete the existing dump file '$fileLocation'. Exiting...", LEVEL_CRITICAL);
		shutdown("Exiting because of undeletable dump file. Please remove this file manually.", false);
	}
}

function email_crit_error($message)
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	mail(GLOBAL_ADMIN_EMAIL, 'Dataloader Critical Error', $message);
	return;
}

function getFeedTypes()
{
	// Create an array to help us identify which type of table we are processing. Source-specific
	// table names are in the format of "al_xx_y" WHERE 'xx' is a two-character string identifying
	// the source, and 'yy' is an auto-incremented number for each vendor under that source.
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);

	global $db;
	
	$db->query("SELECT `id`, `name` FROM `feeds`");
	
	$arrFeedTypes = array();
	
	foreach ($db->objects() as $row)
	{
		$keyname = str_pad($row->id, 2, "0", STR_PAD_LEFT);
		$arrFeedTypes[$keyname] = array('id' => $row->id, 'name' => $row->name);
	}
	
	return $arrFeedTypes;
}

function getUnprocessedTables()
{
	// Get a list of tables from tmp_compchecker
	// These tables have not yet been copied into the standardized format in the
	// al_xx_x tables.
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $link, $db;

	$query = "SELECT `compname`, `file`, `tablename` FROM `tmp_compchecker` WHERE `working`=0;";
	$db->query($query);
	
	if (!$db->rowCount() )
	{
		return array();
	}
	else
	{
		$arrUnprocessedTables = array();
		
		foreach ($db->objects() as $row)
		{
			if (doesTableExist('tmp_'.$row->tablename))
			{
				// Add the table to the list to be processed.
				$arrUnprocessedTables[] = $row->tablename;
				
				// Add the vendor to the 'vendors' table if it does not already exist
				$selectQuery = "SELECT `id` FROM `vendors` WHERE `feed_id` = " . $row->file . " AND `name`= '" . $db->escape_string($row->compname) . "' LIMIT 1;";
				$db->query($selectQuery);
				
				if ($db->rowCount() == 0)
				{
					// The vendor was not found in the 'vendors' table, so we will add it
					logger("Adding new vendor '" . $row->compname . "' to the vendors table.", LEVEL_DATABASE_OPERATION);
					$insertQuery = "INSERT INTO `vendors` (`feed_id`, `name`) VALUES (" . $row->file . ", '" . $db->escape_string($row->compname) . "');";
					$db->query($insertQuery);
				}
			}
			else
			{
				// Remove the entry from tmp_compchecker
				logger("An entry exists for 'tmp_" . $row->tablename . "' in the tmp_compchecker table, but the table 'tmp_" . $row->tablename . "' does not exist. Removing the entry...", LEVEL_DATABASE_OPERATION);
				$deleteQuery = "DELETE FROM `tmp_compchecker` WHERE `tablename`='" . $row->tablename . "'";
				$db->query($deleteQuery);
			}
		}
		return $arrUnprocessedTables;
	}
}

function identifySource($tableName, $returnType="name")
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $arrFeedTypes;

	// Determine the source of the table we are processing
	$sourceID = substr($tableName, strlen(FEEDMAPPING_TEMPORARY_TABLE_PREFIX)+3, 2);

	switch ($returnType)
	{
		case "name":
			return $arrFeedTypes[$sourceID]['name'];
			break;
		case "id":
		default:
			return $arrFeedTypes[$sourceID]['id'];
			break;
	}
}

function loadData($sourceTable, $targetTable)
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	$sourceType = identifySource($sourceTable);

	switch ($sourceType)
	{
		case "Commission Junction":
			include_once "process/commissionjunction.php";
			loadCJData($sourceTable, $targetTable);
			break;
		case "Linkshare":
			include_once "process/linkshare.php";
			loadLinkShareData($sourceTable, $targetTable);
			break;
		case "Performics":
			include_once "process/performics.php";
			loadPerformicsData($sourceTable, $targetTable);
			break;
		default:
			logger("Unable to identify source of table $sourceTable ($sourceType was returned).", LEVEL_PROGRAMMING_WARNING);
			break;
	}
}

function markAsUpdated($targetTable)
{
	// Maintain a list of tables that Sphinx needs to index.
	// If the table already exists in the list, mark it as updated.
	// If it does not exist in the lists, add it and mark it.
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);

	global $link, $db;
	
	$query = "UPDATE `sphinxTableList` SET `updated`=1 WHERE `tablename`='$targetTable';";
	$db->query($query);
	
	if ($db->affectedRows == 0)
	{
		$query = "INSERT INTO `sphinxTableList` (`tablename`, `updated`) VALUES ('$targetTable', 1);";
		mysql_query($query, $link) or shutdown("Unable to mark '$targetTable' as updated. MySQL said: " . mysql_error());
	}
	
}

function removeTable($sourceTable)
{
	// All data has been moved from the temporary source table into the live data table.
	// We can not remove the source table

	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $link, $db;

	logger("Dropping table $sourceTable.", LEVEL_DATABASE_OPERATION);

	// Speed Check start time
	$startTime = microtime(true);
	
	$deleteTableQuery = "DROP TABLE IF EXISTS `$sourceTable`;";
	$db->query($deleteTableQuery);

	// Remove the 'tmp_' string from the front of the table name.
	// We receive a tablename that looks like 'tmp_al_01_1,'
	// but for our processing, we need it to be 'al_01_1.'
	$sourceTable = substr($sourceTable, strlen(FEEDMAPPING_TEMPORARY_TABLE_PREFIX));
	
	// Speed check end time
	$endTime = microtime(true);
	logger("SPEEDCHECK: Time used to drop table '$sourceTable': " . round($endTime-$startTime, 2) . " seconds.");

	
	$deleteEntrySpeedChecker = new SpeedCheck("Deleting entry from tmp_compchecker");
	logger("Removing $sourceTable entry from tmp_compchecker.");

	// Delete the table entry from tmp_compchecker
	$compcheckerQuery = "DELETE FROM tmp_compchecker WHERE tablename='$sourceTable'";
	$db->query($compcheckerQuery);
	
	$deleteEntrySpeedChecker->stop();
	logger($deleteEntrySpeedChecker->getLogMessage(), LEVEL_INFORMATION);
}

function signal_handler($signal)
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
		
	switch ($signal)
	{
		case SIGTERM:
			$signalName = "SIGTERM";
			$quit = true;
			break;
		case SIGINT:
			$signalName = "SIGINT";
			$quit = true;
			break;
		case SIGQUIT:
			$signalName = "SIGQUIT";
			$quit = true;
			break;
		default:
			$signalName = "UNKNOWN ($signal)";
			$quit = false;
			break;			
	}
	if ($quit)
	{
		// Remove the entry from the activeProcesses table
		changeProcessStatus(FEEDMAPPING_PROCESS_NAME, 'end', getmypid());
		
		shutdown("The feed mapper is stopping on signal: $signalName", false);
	}
	else
	{
		logger("The feed mapper received the following unhandled signal: $signalName", LEVEL_STATUS);
	}
}

function truncateTable($tableName)
{
	// This function truncates an existing product table
	// in preparation for loading new data

	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $link, $db;

	// TODO: Drop index prior to truncating? reindex.sh is failing because of a duplicate entry for the key
	
	logger("Truncating existing table `$tableName`");
	$truncateTableSQL = "TRUNCATE TABLE `$tableName`;";
	$db->query($truncateTableSQL);
}

?>
