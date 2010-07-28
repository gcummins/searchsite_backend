<?php

/*
	File: create_frontend_database.php
	Author: George Cummins
	Date: 11/05/08
	Description: Copy data from the master database into the frontend searchsite database
*/

include_once("../include/global_configuration.php");

define('LOG_FILE_NAME', SEARCHSITE_LOGFILE);

define('COPY_PRODUCTS', true);

include_once("../include/functions.php");
include_once("../include/speedcheck.php");
include_once("../include/db.class.php");
//include_once("../connect.php");

// Get the name of the offline database
if (false === ($offlineDatabaseName = getOfflineDatabase()))
{
	logger("No valid name for the offline database could be found. Exiting...", LEVEL_DATA_WARNING);
	shutdown("No valid name for the offline database could be found. Exiting...");
}
else
{
	if (false === ($offlineDb = getOfflineDatabaseLink($offlineDatabaseName)))
	{
		logger("Unable to open a link to the database '$offlineDatabaseName.'", LEVEL_CRITICAL);
		shutdown("Unable to open a link to the database '$offlineDatabaseName.'");
	}
}

/*
   For each al_0?_* table in the master database,
   I. Check if the table exists in the offline database
     A. If the table exists, truncate it and copy the new records in from the master.
	 B. If the table does not exist, create it
   II. Populate the table with records from the master.


   After the tables have been created, generate a sphinx configuration file with sources for all tables
   in the offline database, and reindex.    
*/

$productTables = getProductTablesInDatabase(DATABASE_NAME, $db);

//$counter = 0;
$indexKeyCounter = 1;

// Create a statistic entry
if (defined('SEARCHSITE_STATISTICS_DATABASE_NAME'))
{
	$statsDb = new DatabaseConnection(SEARCHSITE_STATISTICS_DATABASE_HOST, SEARCHSITE_STATISTICS_DATABASE_USERNAME, SEARCHSITE_STATISTICS_DATABASE_PASSWORD, SEARCHSITE_STATISTICS_DATABASE_NAME);
	$statsDb->query("REPLACE INTO `progress` (`processName`, `totalObjects`, `completedObjects`, `objectDescription`) VALUES ('copying tables to searchsite frontend', " . count($productTables) . ", 0, 'tables successfully copied');");
}

if (COPY_PRODUCTS)
{
	foreach ($productTables as $table=>$lastupdate)
	{
		// Delete the table if it currently exists
		deleteProductTable($table, $offlineDb);
		
		// Create the product table (using "IF NOT EXISTS")
		createProductTable($table, $offlineDb);
			
		// Truncating a table resets the auto_increment value, so we will set it here:
		setAutoIncrement($table, $indexKeyCounter, $offlineDb);
	
		// Copy the products from the master database table into the frontend table
		copyProducts($table, DATABASE_NAME, $db, $table, $offlineDatabaseName, $offlineDb);
		
		// Create the index on the table
		createIndex($table, $offlineDatabaseName);
	
		// Determine the key with which to start the next table
		$indexKeyCounter = 1 + getMaxIndex($table, $indexKeyCounter);
		
		if ($indexKeyCounter >= 2000000000)
		{
			logger("There are currently $indexKeyCounter products in this database. If the number of products exceeds 2,147,483,648 the product table definition must be changed to use BIGINT as the field type for the product key. Please contact the developers for more information.", LEVEL_CRITICAL);
		}
		
		// Update the progress meter
		if (defined('SEARCHSITE_STATISTICS_DATABASE_NAME'))
		{
			$statsDb->query("UPDATE `progress` SET `completedObjects` = `completedObjects` + 1 WHERE `processName`='copying tables to searchsite frontend';");
		}
	}
}

// Clear the progress meter
if (defined('SEARCHSITE_STATISTICS_DATABASE_NAME'))
{
	$statsDb->query("DELETE FROM `progress` WHERE `processName`='copying tables to searchsite frontend';");
}

logger("Finished mapping records into the permanent tables.", LEVEL_INFORMATION);

if (COPY_PRODUCTS)
{
	logger("Updating statistics table with the total number of products.", LEVEL_DATABASE_OPERATION);
	$offlineDb->query("REPLACE INTO `stats` (`id`, `value`, `description`) VALUES (1, " . ($indexKeyCounter - 1) . ", 'Number of products in the database');");
}

// Regenerate the sphinx.conf file for indexing
$retval = createSphinxConfigurationFile($offlineDatabaseName);

logger("Swapping the fresh database with the currently-online database", LEVEL_DATABASE_OPERATION);
swapFrontendDatabases();

logger("Exiting with code $retval.", LEVEL_INFORMATION);
exit($retval);

function copyProducts($sourceTable, $sourceDatabaseName, $sourceDatabaseLink, $targetTable, $targetDatabaseName, $targetDatabaseLink)
{
	// Copy products from the master database to identical tables in the frontend database
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	$speedcheck = new SpeedCheck("Copying product rows to search site table '$targetTable.'");
	
	logger("Creating data outfile from table '$sourceTable'.", LEVEL_DATABASE_OPERATION);
	$query = "SELECT " . getFieldList() . " INTO OUTFILE '" . SEARCHSITE_OUTFILE . "' FIELDS OPTIONALLY ENCLOSED BY '\"'"
		. " FROM `$sourceDatabaseName`.`$sourceTable`;";
	$sourceDatabaseLink->query($query);
	
	// The outfile has been created. Now load the data into the table in the offline database
	logger("Loading data from the outfile into table '$targetTable.'", LEVEL_DATABASE_OPERATION);
	$query = "LOAD DATA INFILE '" . SEARCHSITE_OUTFILE . "' INTO TABLE `$targetTable` FIELDS OPTIONALLY ENCLOSED BY '\"';";
	$targetDatabaseLink->query($query);
	
	// Remove the file that we created
	deleteDumpFile(SEARCHSITE_OUTFILE, "Removing the data file '" . SEARCHSITE_OUTFILE . "'...");
	
	// End the speed check
	$speedcheck->stop();
	logger($speedcheck->getLogMessage(), LEVEL_INFORMATION);
}

function createIndex($table, $databaseName)
{
	// Add an auto_increment index column to the table
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $offlineDb;
	
	// Now, add the column and auto_increment flag
	$addIndexSpeedCheck = new SpeedCheck("Creating index on table '$table'");
	$query = "ALTER TABLE `$databaseName`.`$table` ADD `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;";
	$offlineDb->query($query);
	
	$addIndexSpeedCheck->stop();
	logger($addIndexSpeedCheck->getLogMessage(), LEVEL_INFORMATION);
}

function createProductTable($tableName, $databaseLink)
{
	// This function creates a product table

	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $offlineDb;
	
	$createTableSQL = "CREATE TABLE IF NOT EXISTS `$tableName` ("
				. " ProgramName VARCHAR(100) NOT NULL,"
				. " ProgramURL VARCHAR(2000) NOT NULL,"
				. " LastUpdated DATETIME NOT NULL,"
				. " ProductName VARCHAR(255) NOT NULL,"
				. " Keywords VARCHAR(500) NOT NULL default '',"
				. " LongDescription VARCHAR(3000) NOT NULL default '',"
				. " InterimDescription VARCHAR(2000) NOT NULL default '',"
				. " ShortDescription VARCHAR(500) NOT NULL default '',"
				. " BriefDescription VARCHAR(255) NOT NULL default '',"
				. " SKU VARCHAR(100) NULL,"
				. " Manufacturer VARCHAR (250) NOT NULL default '',"
				. " ManufacturerID VARCHAR(64) NULL,"
				. " UPC VARCHAR(15) NULL,"
				. " ISBN VARCHAR(64) NULL,"
				. " Currency VARCHAR(3) NOT NULL default 'USD',"
				. " SalePrice DECIMAL(10,2) NULL,"
				. " Price DECIMAL(10,2) NULL,"
				. " RetailPrice DECIMAL(10,2) NULL,"
				. " FromPrice DECIMAL(10,2) NULL,"
				. " BuyURL VARCHAR(2000) NOT NULL,"
				. " AddToCartURL VARCHAR(2000) NULL,"
				. " ImageURL VARCHAR(2000) NULL,"
				. " ImpressionURL VARCHAR(2000) NULL,"
				. " Category VARCHAR(300) NULL,"
				. " SecondaryCategory VARCHAR(2000) NULL,"
				. " CategoryID INT NULL,"
				. " CategoryCrumbs VARCHAR(2000) NULL,"
				. " Author VARCHAR(130) NULL,"
				. " Artist VARCHAR(130) NULL,"
				. " Title VARCHAR(130) NULL,"
				. " Publisher VARCHAR(130) NULL,"
				. " Label VARCHAR(130) NULL,"
				. " Format VARCHAR(64) NULL,"
				. " Special VARCHAR(5) NULL,"
				. " PromotionalText VARCHAR(300) NULL,"
				. " StartDate DATETIME NULL,"
				. " EndDate DATETIME NULL,"
				. " ShippingCost DECIMAL(10,2) NULL)"
			. " ENGINE=MyISAM DEFAULT CHARSET=latin1;";

	$offlineDb->query($createTableSQL);
	logger("Table `$tableName` was created successfully.", LEVEL_INFORMATION);
}

function createSphinxConfigurationFile($offlineDatabaseName)
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $offlineDb;
	
	// First, get a list of all tables in the database.
	$arrTables = getProductTablesInDatabase($offlineDatabaseName, $offlineDb);
	
	if (!count($arrTables))
	{
		// There are no updated tables, so we need to notify the reindex process not to run

		// Create a file in the run/ directory that tells reindex.sh to restart dataloader and then
		// terminate.
		$fh = fopen(SEARCHSITE_DO_NOT_REINDEX_FILENAME, 'w');
		fclose($fh);
		logger("No product tables exist in the database, so no sphinx.conf was generated.", LEVEL_DATA_WARNING);
		return;
	}

	$output = "";

	$counter = 1000;
	$sourcesOutputString = '';
	$arrMapIdToTablename = array();
	foreach ($arrTables as $table=>$lastupdate)
	{
		// Ensure that the target table exists and contains an ID field
		$tableIsValid = false;
		$offlineDb->query("DESCRIBE `$offlineDatabaseName`.`$table`");
		
		foreach ($offlineDb->objects() as $fieldRow)
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
			// Determine the feed type and table number for this table
			list($prefix, $feedType, $tableNumber) = explode('_', $table);
			
			$feedType = 0+$feedType; // Convert to an integer.
			
			// Add the new source configuration to the output string
			if ($counter == 1000)
			{
				$masterSourceName = $table;
				
				$output .= "
				source $table
				{
					type = mysql
					sql_host = localhost
					sql_user = root
					sql_pass = 1yaaya5
					sql_db = $offlineDatabaseName
					sql_sock = /tmp/mysql.sock
					sql_port = 3306				
					sql_query = SELECT id, ProductName, Keywords, LongDescription, Price*100 as price, $feedType as feedid, $tableNumber as tableid FROM $table WHERE (StartDate <= NOW() AND EndDate >= NOW()) OR (StartDate = '0000-00-00 00:00:00' AND EndDate = '0000-00-00 00:00:00') OR (StartDate = '0000-00-00 00:00:00' AND EndDate >= NOW()) OR (StartDate <= NOW() AND EndDate = '0000-00-00 00:00:00');
					sql_query_info = SELECT ProductName, LongDescription FROM $table WHERE id=\$id
					sql_attr_uint = feedid
					sql_attr_uint = tableid
					sql_attr_uint = price
				}";
			}
			else
			{
				// We can reduce the size of the configuration file (and the parsing time)
				// by removing duplicate elements from the source definition.
				// The elements are inherited from the first source ($masterSourceName)
				$output .= "
				source $table : $masterSourceName
				{
					sql_query = SELECT id, ProductName, Keywords, LongDescription, Price*100 as price, $feedType as feedid, $tableNumber as tableid FROM $table WHERE (StartDate <= NOW() AND EndDate >= NOW()) OR (StartDate = '0000-00-00 00:00:00' AND EndDate = '0000-00-00 00:00:00') OR (StartDate = '0000-00-00 00:00:00' AND EndDate >= NOW()) OR (StartDate <= NOW() AND EndDate = '0000-00-00 00:00:00');
					sql_query_info = SELECT ProductName, LongDescription FROM $table WHERE id=\$id
				}";				
			}
		
			// Add the table name to the source array for use later when we 
			// reference each of the sources in the index
			$arrMapIdToTablename[$counter] = $table;
		
			$sourcesOutputString .= "\tsource = $table\n\t";
			// Incremement the counter to make sure we have a unique ID
			$counter++;
		}
		else
		{
			logger("Table '$table' is not valid.", LEVEL_INFORMATION);
		}
	}
	
	$output .= "
	index searchsite
	{
	$sourcesOutputString
		path = /usr/local/sphinx/var/data/searchsite
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
		mem_limit = 512M
	}
	
	searchd
	{
		listen = " . SEARCHSITE_SPHINX_PORT . "
		log = /usr/local/sphinx/var/log/searchsite_searchd.log
		query_log = /usr/local/sphinx/var/log/searchsite_query.log
		read_timeout = 5
		client_timeout = 300
		max_children = 0
		pid_file = /usr/local/sphinx/var/log/searchsite_searchd.pid
		max_matches = 50000
		seamless_rotate = 1
		preopen_indexes = 0
		unlink_old = 1
		max_packet_size = 8M
		max_filters = 256
		max_filter_values = 4096
	}";

	logger("Creating index configuration file...", LEVEL_FILE_OPERATION);
	$sphinxConfigurationFile = SEARCHSITE_CONTROL_DIRECTORY . 'sphinx.conf';
	if (false === ($fh = fopen($sphinxConfigurationFile, 'w')))
	{
		shutdown("Unable to open Sphinx configuration file ($sphinxConfigurationFile) for writing.");
	}
	else
	{
		if (false !== fwrite($fh, $output))
		{
			logger("Index configuration file was created successfully.", LEVEL_INFORMATION);
		}
		else
		{
			shutdown("Writing configuration data to the index file failed.");
		}
		fclose($fh);
	}
	
	// We need to create an entry in the database for each of the number-to-tablename listings. For example, 1000000 = al_01_1, 1000001 = al_01_2.
	// This will be used by the frontend when retrieving products.

	// First, truncate the existing table
	logger("Emptying table 'tablenameMap'...", LEVEL_DATABASE_OPERATION);
	$offlineDb->query("TRUNCATE `tablenameMap`");
	
	// Next, create entries for each of the new mappings
	logger("Inserting new table map values into 'tablenameMap'", LEVEL_DATABASE_OPERATION);
	$query = "INSERT INTO `tablenameMap` (`number`, `tablename`) VALUES ";
	
	foreach ($arrMapIdToTablename as $number => $tablename)
	{
		$query .= "('$number', '$tablename'), ";
	}
	
	$query = substr($query, 0, -2) . ";";
	$offlineDb->query($query);
		
	return 0;
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
	if (false === @unlink($fileLocation))
	{
		logger("Unable to delete the existing dump file '$fileLocation'. Exiting...", LEVEL_CRITICAL);
		shutdown("Exiting because of undeletable dump file ($fileLocation). Please remove this file manually.", false);
	}
}

function getActiveDatabaseNameFromFile()
{
	// Retrieve the active database from the designated file.
	$filename = SEARCHSITE_ONLINEDBNAME_FILE;
	if (!file_exists($filename))
	{
		logger("The file '$filename' does not exist.", LEVEL_PROGRAMMING_WARNING);
		return false;
	}
	if (!is_readable($filename))
	{
		logger("The file '$filename' is not readable.", LEVEL_DATA_WARNING);
		return false;
	}
	if (false === ($filehandle = fopen($filename, 'r')))
	{
		logger("The file '$filename' cannot be opened.", LEVEL_DATA_WARNING);
		return false;
	}
	if (false === ($inputString = fread($filehandle, 256)))
	{
		logger("Unable to retrieve the online database name from file '$filename'.", LEVEL_PROGRAMMING_WARNING);
		return false;
	}
	return trim($inputString);
	
}

function getFieldList()
{
	// A list of all fields that are copied from the master database to the frontend database.
	return "ProgramName, ProgramURL, LastUpdated, ProductName, Keywords, LongDescription, InterimDescription, ShortDescription, BriefDescription, SKU, Manufacturer, ManufacturerID, UPC, ISBN, Currency, SalePrice, Price, RetailPrice, FromPrice, BuyURL, AddToCartURL, ImageURL, ImpressionURL, Category, SecondaryCategory, CategoryID, CategoryCrumbs, Author, Artist, Title, Publisher, Label, Format, Special, PromotionalText, StartDate, EndDate, ShippingCost";	
}

function getMaxIndex($table, $indexKeyCounter)
{
	// Get the highest index value from the specified table
	
	global $offlineDb;
	
	$offlineDb->query("SELECT `id` FROM `$table` ORDER BY `id` DESC LIMIT 1;");
	
	if (!$offlineDb->rowCount())
	{
		// The table has no records
		return $indexKeyCounter - 1;
	}
	else
	{
		return $offlineDb->firstField();
	}
	
}

function getOfflineDatabase()
{
	// Determine which database is currently offline so that we can populate it 
	// with new data.
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	// First, get the names of the two databases for this site.
	list($dbOne, $dbTwo) = retrieveDatabaseNames();
	
	if (false === $dbOne)
	{
		logger("Unable to find the database names for this site in the configuration file.", LEVEL_INFORMATION);
		list($dbOne, $dbTwo) = getOfflineDatabaseNameFromDatabase();
	}
		
	if (false === $dbOne || false === $dbTwo)
	{
		shutdown("Unable to determine the database names for this site.");
	}
	else
	{
		// Retrieve the active database from a file.
		if (false === ($activeDatabaseName = getActiveDatabaseNameFromFile()))
		{
			shutdown("Unable to retrieve the active database name.");
		}
		
		switch ($activeDatabaseName)
		{
			case $dbOne:
				return $dbTwo;
				break;
			case $dbTwo:
				return $dbOne;
				break;
			default:
				logger("The retrieved active database name does not match either of the database names for this site.", LEVEL_DATA_WARNING);
				return false;
		}
	}
}

function getOfflineDatabaseNameFromDatabase()
{
	// TODO: Attempt to retrieve the database names for this site from a central database repository
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	logger("The function " . __FILE__ . ":" . __FUNCTION__ . " has not been implemented. Returning 'false.'");
	
	return array(false, false);
}

function getOfflineDatabaseLink($offlineDatabaseName)
{
	// Determine if we are using a site-specific database server, or
	// the global one
	if (defined('SEARCHSITE_DATABASE_HOST'))
	{
		$dbHost = SEARCHSITE_DATABASE_HOST;
	}
	else if (defined('DATABASE_HOST'))
	{
		$dbHost = DATABASE_HOST;
	}
	else
	{
		logger("Unable to determine the MySQL server host name.", LEVEL_CRITICAL);
		return false;
	}
	
	if (defined('SEARCHSITE_DATABASE_USER'))
	{
		$dbUser = SEARCHSITE_DATABASE_USER;
	}
	else if (defined('DATABASE_USER'))
	{
		$dbUser = DATABASE_USER;
	}
	else
	{
		logger("Unable to determine the MySQL username for this site.", LEVEL_CRITICAL);
		return false;
	}
	
	if (defined('SEARCHSITE_DATABASE_PASSWORD'))
	{
		$dbPassword = SEARCHSITE_DATABASE_PASSWORD;
	}
	else if (defined('DATABASE_PASSWORD'))
	{
		$dbPassword = DATABASE_PASSWORD;
	}
	else
	{
		logger("Unable to determine the MySQL password for this site.", LEVEL_CRITICAL);
		return false;
	}
	
	// Attempt to initiate a connection to the server.
	$offlineDb = new DatabaseConnection($dbHost, $dbUser, $dbPassword, $offlineDatabaseName);
	logger("Successfully connected to the offline database '$offlineDatabaseName.'", LEVEL_INFORMATION);
	return $offlineDb;
}

function getProductTablesInDatabase($databaseName, $databaseLink)
{
	// Get a list of product tables with their update times from the specified database.
	// Ensure that the table names are in the format of : al_0?_???
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	$databaseLink->query("SHOW TABLE STATUS FROM `$databaseName` WHERE NAME LIKE 'al_0%'");

	if (!$databaseLink->rowCount())
	{
		shutdown("No tables exist in the master database.");
	}
	
	$arrTables = array();
	foreach ($databaseLink->objects() as $row)
	{
		if (substr($row->Name, -7) != 'datapro')
		{
			$arrTables[$row->Name] = $row->Update_time;
		}
	}
	
	$numberOfTables = count($arrTables);
	
	if (!$numberOfTables)
	{
		shutdown("No tables exist in the master database");
	}
	else
	{
		if ($numberOfTables == 1)
		{
			$message = "There is 1 product table in the master database.";
		}
		else
		{
			$message = "There are $numberOfTables product tables in the master database.";
		}
		logger($message, LEVEL_INFORMATION);
		return $arrTables;
	}
}

function retrieveDatabaseNames()
{
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	// Get the names of the two databases for this site.
	$dbOne = false;
	$dbTwo = false;
	if (defined('SEARCHSITE_DATABASE_ONE'))
	{
		$dbOne = SEARCHSITE_DATABASE_ONE;
	}
	else
	{
		logger("Unable to find the database names for this site in the configuration file.", LEVEL_INFORMATION);
		return getOfflineDatabaseNameFromDatabase();
	}
	
	if (defined('SEARCHSITE_DATABASE_TWO'))
	{
		$dbTwo = SEARCHSITE_DATABASE_TWO;
	}
	else
	{
		// Attempt to construct the database name from the first name
		// Remove the last three characters, and add the string "two"
		$dbTwo = substring($dbOne, 0, -3) . "two";
	}
	
	return array($dbOne, $dbTwo);
}

function setAutoIncrement($table, $autoIncrementValue, $link)
{
	// Modify the table to use the provided auto_increment value
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	logger("Altering table '$table' to use auto_increment value $autoIncrementValue.", LEVEL_DEBUG);
	$link->query("ALTER TABLE `$table` AUTO_INCREMENT=$autoIncrementValue");
}

function swapFrontendDatabases()
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $offlineDb;
	
	logger("The function " . __FILE__ . ":" . __FUNCTION__ . " has not been implemented. Proceeding with currently-selected database.", LEVEL_PROGRAMMING_WARNING);
	//shutdown("The function " . __FILE__ . ":" . __FUNCTION__ . " has not been implemented.");
}

function deleteProductTable($table, $link)
{
	// Empty a table of all records
	logger("Deleteing table '$table' if it exists.", LEVEL_DATABASE_OPERATION);
	$link->query("DROP TABLE IF EXISTS `$table`");
}
?>
