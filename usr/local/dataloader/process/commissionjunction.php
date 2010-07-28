<?php

// Commission Junction feed processing for dataloader.

function processCommissionJunctionFeed($revision=1)
{
	// COMMISSION JUNCTION
	// Many vendors in one file. This can be a hassle on a crash.
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $work, $db, $basepath, $dbDatabase;
	
	if (!defined('CJ_FEED_NAME'))
	{
		define('CJ_FEED_NAME', 'Commission Junction');
	}
	
	// Determine the feed ID for this feed
	$db->query("SELECT `id` FROM `feeds` WHERE `name`='" . CJ_FEED_NAME . "' LIMIT 1;");
	
	if (!$db->rowCount())
	{
		logger("Unable to retrieve the ID of the feed source '" . CJ_FEED_NAME . ".'", LEVEL_CRITICAL);
		return;
	}
	$cjFeedId = $db->firstField();
	
	// See if the db_creation table is empty
	$db->query("SELECT * FROM `$dbDatabase`.`db_creation` WHERE `dbset`=$cjFeedId");
	
	// This is only true if db_creation has been truncated or deleted.
	if ($db->rowCount() == 0)
	{
		$db->query("INSERT INTO `$dbDatabase`.`db_creation` (`dbset`, `numcount`) VALUES ($cjFeedId,0)");		
	}

	// Get a list of Commission Junction files that are ready for processing
	$db->query("SELECT * FROM `$dbDatabase`.`filesitter` WHERE `feedfile`=$cjFeedId AND `process`=3");
		
	if ($db->rowCount())
	{
		// Process each of the files in turn
		foreach ($db->objects() as $fsQueue)
		{
			logger("Moving vendor with id " . $fsQueue->id . " to process 4 in filesitter.", LEVEL_DATABASE_OPERATION);
			$db->query("UPDATE `$dbDatabase`.`filesitter` SET `process` = 4, `datastart` = ".time()." WHERE `id` = ".$fsQueue->id);
	
			/*
			 * Step 1
			 *
			 *  Load raw data into al_01_datapro and delete the raw file.
			 * Filesitter process is at 4.
			 */
	
			// Delete the master table (al_01_datapro). Because of the number of edits this tables receives,
			// it is prone to errors. Dropping and recreating the table will help to avoid them.
			logger("Dropping table 'al_01_datapro'", LEVEL_DATABASE_OPERATION);
			$db->query("DROP TABLE IF EXISTS al_01_datapro");
			
			// Recreate the master table (al_01_datapro)
			verifyCJMasterTableExists($fsQueue->feedRevision);
			
			// Empty the table
			//logger("Emptying table 'al_01_datapro'...", LEVEL_INFORMATION);
			//$db->query("TRUNCATE TABLE `$dbDatabase`.`al_01_datapro`");
			
			// Drop the index
			//logger("Dropping index on field `ProgramName` in al_01_datapro", LEVEL_DATABASE_OPERATION);
			//$db->query("DROP INDEX `ProgramName` ON `".$dbDatabase."`.`al_01_datapro`");
			//logger("Done dropping index on field `ProgramName` in al_01_datapro", LEVEL_INFORMATION);
			
			// Load data from the feed file
			logger("Loading " . $work.$fsQueue->file . " (revision: " . $fsQueue->feedRevision . ").", LEVEL_FILE_OPERATION);
			
			/*
			 * On 2010/05/19 we discovered that some feeds arrive with prices in the following format:
			 *	1,299.99
			 *
			 * CJ guidelines say the data should arrive like this:
			 *  1299.99
			 *  
			 * Since many of the vendors ignore the guidelines, we need to fix the price data when importing.
			 * Prior to this fix, decimal values were getting truncated at the comma.
			 */
			$fieldList = getCJFieldList();
			
			$arrOriginalFieldNames = array("`SalePrice`", "`Price`", "`RetailPrice`", "`FromPrice`");
			$arrFieldNamesAsVariables = array("@`SalePrice`", "@`Price`", "@`RetailPrice`", "@`FromPrice`");
			
			$loadDataQuery = "LOAD DATA INFILE '" . $work.$fsQueue->file . "' INTO TABLE `$dbDatabase`.`al_01_datapro`"
			    . " IGNORE 1 LINES"
			    . " (" . str_replace($arrOriginalFieldNames, $arrFieldNamesAsVariables, $fieldList) . ")"
			    . " SET `SalePrice` = REPLACE (@`SalePrice`, ',', ''),"
			    . " `Price` = REPLACE (@`Price`, ',', ''),"
			    . " `RetailPrice` = REPLACE (@`RetailPrice`, ',', ''),"
			    . " `FromPrice` = REPLACE (@`FromPrice`, ',', '')";
			    
			//$db->query("LOAD DATA INFILE '".$work.$fsQueue->file."' INTO TABLE `$dbDatabase`.`al_01_datapro` IGNORE 1 LINES");
			$db->query($loadDataQuery);
			
			unset ($fieldList);
			unset ($arrOriginalFieldNames);
			unset ($arrFieldNamesAsVariables);
			unset ($loadDataQuery);
			
			logger("Done loading Commission Junction feed file.", LEVEL_INFORMATION);
			
			// Delete the feed file
			logger("Deleting ".$work.$fsQueue->file);
			if (false === (unlink($work.$fsQueue->file)))
			{
				logger("Failed to delete file '" . $work.$fsQueue->file . ".' The file should be deleted manually.", LEVEL_DATA_WARNING);
			}
			else
			{
				logger("File '" . $work.$fsQueue->file . "' has been deleted.", LEVEL_INFORMATION);
			}
			
			// Create a index on al_01_datapro
			//logger("Creating index on `ProgramName` in al_01_datapro", LEVEL_DATABASE_OPERATION);
			//$db->query("CREATE INDEX `ProgramName` ON `$dbDatabase`.`al_01_datapro` (ProgramName)");
			//logger("Done creating index on field 'ProgramName' in table 'al_01_datapro'", LEVEL_INFORMATION);
			
			// Filesitter process goes to 5.
			// Get TOTAL rows from the datapro table. This is for stats only.
			//
			logger("Determining the number of rows in al_01_datapro...", LEVEL_DATABASE_OPERATION);
			$db->query("SELECT COUNT(*) AS `rows` FROM `$dbDatabase`.`al_01_datapro`");
			
			$productsInDatapro = $db->rowCount();
			logger("There are $productsInDatapro rows in al_01_datapro.", LEVEL_INFORMATION);
			
			logger("Updating filesitter with the row count.", LEVEL_DATABASE_OPERATION);
			$updateQuery = "UPDATE `$dbDatabase`.`filesitter` SET `process`=5, `datalines`=$productsInDatapro WHERE `id` = ".$fsQueue->id;
			
			// Step 2
			logger("Truncating table tmpcompare.", LEVEL_DATABASE_OPERATION);
			$db->query("TRUNCATE TABLE ".$dbDatabase.".tmpcompare");
	
			// CJ datafeeds can have multiple vendors together in a
			// single datafile. Grab the vendor name and number of rows per vendor and place them
			// into the tmpcompare table.
			$countQuery = "SELECT DISTINCTROW `ProgramName`, `CatalogName`, COUNT(*) AS `ProgramName_count` FROM `$dbDatabase`.`al_01_datapro` GROUP BY `ProgramName`";
			logger("Counting the number of vendors in al_01_datapro", LEVEL_DATABASE_OPERATION);
			$db->query($countQuery);
			
			if ($db->rowCount() == 1)
			{
			    logger("There is one vendor in al_01_datapro.", LEVEL_INFORMATION);
			}
			else
			{
                logger("There are " . $db->rowCount() . " vendors in al_01_datapro.", LEVEL_INFORMATION);
			}
			$rightNow = time();
			$cjVendorInfo=array();		// This is for logging.
			
			logger("Inserting vendor information into tmpcompare.", LEVEL_DATABASE_OPERATION);
			foreach ($db->arrays() as $cntRow)
			{
				array_push($cjVendorInfo, $cntRow);
				
				// The fieldname 'file' is the affiliate ID (see the 'vendors' table)
				$db->query("INSERT INTO `$dbDatabase`.`tmpcompare` (`compname`, `catalog`, `vardatetime`, `file`, `norecs`) VALUES ( '" . $db->escape_string($cntRow['ProgramName']) . "', '" . $db->escape_string($cntRow['CatalogName']) . "', '$rightNow', $cjFeedId, '".$cntRow['ProgramName_count']."' )");
			}
	
			logger("Selecting all records from tmpcompare where file=\$cjFeedId", LEVEL_DATABASE_OPERATION);
			$db->query("SELECT * FROM `$dbDatabase`.`tmpcompare` WHERE `file`=$cjFeedId");
			
			logger("Looping through each vendor in tmpcompare", LEVEL_INFORMATION);
			foreach($db->objects() as $vendorRow)
			{
				$programname = $vendorRow->compname;
				$catalog= $vendorRow->catalog;
				
				logger("Processing Commission Junction  data for '$programname'", LEVEL_INFORMATION);
				$sqlProgName = $db->escape_string($programname);
				
				// Retrieve data about this vendor from the compchecker table.
				logger("Retrieving information about '$programname' from compchecker.", LEVEL_DATABASE_OPERATION);
				$db->query("SELECT * FROM `$dbDatabase`.`compchecker` WHERE `compname`='$sqlProgName' AND `catalog` = '" . $db->escape_string($catalog) . "' AND `file`=$cjFeedId");
				
				// Ensure that an entry was found in compchecker
				if ($db->rowCount() == 0)
				{
					$tableExistsInCompchecker = false;
					logger("No entry for vendor '$sqlProgName' was found in the compchecker table.", LEVEL_PROGRAMMING_WARNING);
				}
				else
				{
					$tableExistsInCompchecker = true;
					$compCheckRow = $db->firstObject();
				}
				
				logger("Verifying that this vendor is listed in al_01_datapro.", LEVEL_DATABASE_OPERATION);
				$db->query("SELECT `ProgramName` FROM `al_01_datapro` WHERE `ProgramName`='$sqlProgName'");
				$numRows=$db->rowCount();
				
				if ($tableExistsInCompchecker && $numRows && trim($programname) == trim($compCheckRow->compname))
				{
					// Yes. Production table exists.
					// Add a temporary record to tmp_compchecker.
					
					// Copy existing record from compchecker into tmp_compchecker. First trash any existing tables and rows for this vendor.
					logger("Table exists in al_01_datapro and compchecker, and the name in compchecker matches the name retrieved from al_01_datapro.", LEVEL_MINUTIA);
					logger("Checking if the catalog exists in tmp_compchecker. If it does, we will retrieve the ID and table name.", LEVEL_MINUTIA);
					$db->query("SELECT `id`, `tablename` FROM `$dbDatabase`.`tmp_compchecker` WHERE `compname` = '$sqlProgName' AND `catalog` = '" . $db->escape_string($catalog) . "' AND `file`=$cjFeedId LIMIT 1");
					
					if ($db->rowCount())
					{
						$tmpId = $db->firstArray();
						logger("Program $sqlProgName was found in tmp_compchecker. Deleting existing temporary table.", LEVEL_DATABASE_OPERATION);
						$db->query("DROP TABLE IF EXISTS `$dbDatabase`.`tmp_" . $tmpId['tablename'] . "`");
						
						logger("Removing $sqlProgName entry from tmp_compchecker.", LEVEL_DATABASE_OPERATION);
						$db->query("DELETE FROM `$dbDatabase`.`tmp_compchecker` WHERE `tablename`='".$tmpId['tablename']."'");
					}
					logger("Copying information about $sqlProgName from compchecker into tmp_compchecker", LEVEL_DATABASE_OPERATION);
					$db->query("INSERT INTO `$dbDatabase`.`tmp_compchecker` (`compname`, `catalog`, `vardatetime`, `file`, `norecs`, `tablename`, `companyname`, `approved`, `filename`, `working`, `reload`, `df_lines`, `df_size`, `reindex`) SELECT `compname`, `catalog`, `vardatetime`, `file`, `norecs`, `tablename`, `companyname`, `approved`, `filename`, `working`, `reload`, `df_lines`, `df_size`, `reindex` FROM `$dbDatabase`.`compchecker` WHERE `compname`='$sqlProgName' AND `catalog` = '" . $db->escape_string($catalog) . "' AND `file`=1");
	
					$rightNow = time();
					logger("Updating the tmp_compchecker record for $sqlProgName with the current time, number of records, and filename.", LEVEL_MINUTIA);
					$db->query("UPDATE ".$dbDatabase.".tmp_compchecker SET vardatetime = '$rightNow', norecs = '$numRows', filename = '".$fsQueue->storage."', working = 1, reload = 1, reindex = 1 WHERE compname = '$sqlProgName' AND `catalog` = '" . $db->escape_string($catalog) . "'");
	
					logger("Updating filesitter with the id of this vendor.", LEVEL_MINUTIA);
					$db->query("UPDATE `$dbDatabase`.`filesitter` SET `compcheckerid` = ".$compCheckRow->id." WHERE `id` = ".$fsQueue->id);
					
					// Create temporary table
					$tmpTableName="tmp_".$compCheckRow->tablename;
					logger("Creating temporary table '$tmpTableName'", LEVEL_DATABASE_OPERATION);
					logger("Revision is '" . $fsQueue->feedRevision . ".'", LEVEL_DEBUG);
					$tmpTableDef=cj_table($dbDatabase, $tmpTableName, $fsQueue->feedRevision); // Retrieve the table-creation SQL statement.
					$db->query($tmpTableDef);
					
					logger("Truncating table '$tmpTableName' to ensure that it contains no old data.", LEVEL_DATABASE_OPERATION);
					$db->query("TRUNCATE TABLE `$dbDatabase`.`$tmpTableName`");
				}
				else
				{
					// Production table does not exist. Create a new product table name and
					// table for this vendor.	
					if (!$tableExistsInCompchecker)
					{
						logger("Table does not exist in compchecker.", LEVEL_INFORMATION);
					}
					elseif (trim($programname) != trim($compCheckRow->compname))
					{
						logger("The program name retrieved from tmpcompare (" . trim($programname) . ") does not match the name found in compchecker (" . trim($compCheckRow->compname) . ").", LEVEL_DATA_WARNING);
					}
					
					if (!$numRows)
					{
						logger("Vendor does not exist in al_01_datapro.", LEVEL_DATA_WARNING);
					} 
					
					logger("Determining what to name this table by fetching the existing count from db_creation.", LEVEL_MINUTIA);
					$db->query("SELECT numcount FROM `$dbDatabase`.`db_creation` WHERE `dbset` = $cjFeedId LIMIT 1");
					$varCount = 1 + $db->firstField();
					
					logger("Updating db_creation with a new table count for Commission Junction.", LEVEL_MINUTIA);
					$db->query("UPDATE `$dbDatabase`.`db_creation` SET `numcount`= $varCount WHERE `dbset`=$cjFeedId"); // 'dbset=1' updates the Commission Junction record.
	
					// Create the name for the new production table
					$vartable = "al_01_".$varCount;
					logger("The new production table name will be '$vartable.'");
					
					// Add this vendor info into compchecker and tmp_compchecker.
					logger("Inserting information for vendor $sqlProgName into compchecker.", LEVEL_DATABASE_OPERATION);
					$rightNow = time();
					$db->query("INSERT INTO `$dbDatabase`.`compchecker` (`compname`, `catalog`, `vardatetime`, `file`, `norecs`, `tablename`, `companyname`, `filename`, `working`, `reload`, `reindex`) VALUES ('$sqlProgName', '" . $db->escape_string($catalog) ."', '$rightNow', $cjFeedId, ".$vendorRow->norecs.", '$vartable', '$sqlProgName', '".$fsQueue->storage."', 0, 1, 1 )");
					
					logger("Inserting information for vendor '$sqlProgName' into tmp_compchecker.", LEVEL_DATABASE_OPERATION);
					$new_company_id = $db->insert_id();
					$db->query("INSERT INTO `$dbDatabase`.`tmp_compchecker` (`compname`, `catalog`, `vardatetime`, `file`, `norecs`, `tablename`, `companyname`, `filename`, `working`, `reload`, `reindex`) VALUES ('$sqlProgName', '" . $db->escape_string($catalog) ."', '$rightNow', $cjFeedId, ".$vendorRow->norecs.", '$vartable', '$sqlProgName', '".$fsQueue->storage."', 0, 1, 1 )");
	
					logger("Adding the new company id to filesitter.", LEVEL_DATABASE_OPERATION);
					$db->query("UPDATE `$dbDatabase`.`filesitter` SET `compcheckerid` = $new_company_id WHERE `id` = ".$fsQueue->id);
					
					$tmpTableName="tmp_".$vartable;
					logger("Creating new temporary table '$tmpTableName' (vendor: $programname, catalog: $catalog).");
					$tmpTableDef=cj_table($dbDatabase, $tmpTableName, $fsQueue->feedRevision); // Retrieve the table-creation SQL statement
					$db->query($tmpTableDef);
									
					logger("Truncating table '$tmpTableName' to ensure that it contains no old data.", LEVEL_INFORMATION);
					$truncateTableQuery = "TRUNCATE TABLE `$dbDatabase`.`$tmpTableName`";
					$db->query($truncateTableQuery);
					logger("Permanent and temporary tables for this catalog have been created.", LEVEL_INFORMATION);
				}
			}
			
			logger("Truncating table 'tmpcompare.'", LEVEL_DATABASE_OPERATION);
			$truncateTableQuery = "TRUNCATE TABLE ".$dbDatabase.".tmpcompare";
			$db->query($truncateTableQuery);
			
			// Step 3
			$db->query("SELECT `id`, `compname`, `catalog`, `tablename` FROM `$dbDatabase`.`tmp_compchecker` WHERE `file`=1 AND `reload`=1");
	
			foreach ($db->arrays() as $cntRow)
			{
				$temporaryTableName = 'tmp_' . $cntRow['tablename'];
				logger("Updating 'tmp_compchecker' for vendor with ID: " . $cntRow['id'], LEVEL_DATABASE_OPERATION);
				$db->query("UPDATE `$dbDatabase`.`tmp_compchecker` SET working = 1, reload = 0, reindex = 1 WHERE id = ".$cntRow['id']);
				
				logger("Truncating table '$temporaryTableName.'", LEVEL_DATABASE_OPERATION);
				$truncateTableQuery = "TRUNCATE TABLE `".$dbDatabase."`.`$temporaryTableName`";
				$db->query($truncateTableQuery);
				
				logger("Retrieving structure information for table '$temporaryTableName.'", LEVEL_DATABASE_OPERATION);
				$describeTableQuery = "DESCRIBE `$dbDatabase`.`$temporaryTableName`";
				$db->query($describeTableQuery);
				
				logger("Copying all data in al_01_datapro for vendor: '" . $cntRow['compname'] . "' and catalog: '" . $cntRow['catalog'] . "' into outfile '" . DATALOADER_COMMISSIONJUNCTION_OUTFILE . ".'", LEVEL_FILE_OPERATION);
				$db->query("SELECT " . getCJFieldList() . " INTO OUTFILE '" . DATALOADER_COMMISSIONJUNCTION_OUTFILE . "' FROM `$dbDatabase`.`al_01_datapro` WHERE `ProgramName` = '" . $db->escape_string($cntRow['compname']) . "' AND `CatalogName` = '" . $db->escape_string($cntRow['catalog']) . "'");
				
				logger("Loading data from outfile into table '$temporaryTableName'.", LEVEL_DATABASE_OPERATION);
				$db->query("LOAD DATA INFILE '" . DATALOADER_COMMISSIONJUNCTION_OUTFILE . "' INTO TABLE `$dbDatabase`.`$temporaryTableName`");
				
				logger("Deleting the dump file...", LEVEL_FILE_OPERATION);
				if (file_exists(DATALOADER_COMMISSIONJUNCTION_OUTFILE) && is_writable(DATALOADER_COMMISSIONJUNCTION_OUTFILE))
				{
					if (@unlink(DATALOADER_COMMISSIONJUNCTION_OUTFILE))
					{
						logger("'" . DATALOADER_COMMISSIONJUNCTION_OUTFILE . "' has been deleted.", LEVEL_FILE_OPERATION);
					}
					else
					{
						logger("Failed to delete '" . DATALOADER_COMMISSIONJUNCTION_OUTFILE . "'.", LEVEL_DATA_WARNING);
					}
				}
				else
				{
					logger("The file '" . DATALOADER_COMMISSIONJUNCTION_OUTFILE . "' does not exist or is not writable.", LEVEL_DATA_WARNING);
				}				
				
				logger("Deleting data from al_01_datapro for vendor: '" . $cntRow['compname'] . "' and catalog: '" . $cntRow['catalog'] . "' to free disk space.", LEVEL_DATABASE_OPERATION);
				$db->query("DELETE FROM `$dbDatabase`.`al_01_datapro` WHERE `ProgramName`='" . $db->escape_string($cntRow['compname']) . "' AND `CatalogName` = '" . $db->escape_string($cntRow['catalog']) . "';");
				
				logger("Done populating table '$temporaryTableName' for vendor: '" . $cntRow['compname'] . "' and catalog '" . $cntRow['catalog'] . "'.");
	
				logger("Retrieving table status for table '$temporaryTableName.'", LEVEL_DATABASE_OPERATION);
				$db->query("SHOW TABLE STATUS LIKE 'tmp_".$cntRow['tablename']."'");
				
				$table_search = $db->firstArray();
				$varlines = $table_search['Rows'];
				$varsize = $table_search['Data_length'] + $table_search['Index_length'];
				$db->query("UPDATE `$dbDatabase`.`tmp_compchecker` SET `vardatetime` = '".time()."', `working` = 0, `df_lines` = $varlines, `df_size` = $varsize, `reindex` = 1 WHERE id = ".$cntRow['id']);
			}
			logger("Truncating table al_01_datapro.", LEVEL_DATABASE_OPERATION);
			$db->query("TRUNCATE TABLE `".$dbDatabase."`.`al_01_datapro`");
			
			logger("Updating filesitter to set process=6 for the file with id: " . $fsQueue->id . ".", LEVEL_DATABASE_OPERATION);
			$db->query("UPDATE `$dbDatabase`.`filesitter` SET `process`=6, `dataend`=".time()." WHERE `id`=".$fsQueue->id);
	
			logger("Inserting data into logfile for the file with id: " . $fsQueue->id . ".", LEVEL_DATABASE_OPERATION);
			$logfileFromFilesitterQuery="INSERT INTO `".$dbDatabase."`.`logfile`
			(original, file, time, filestart, fileend, datastart, dataend, serial, size, postsize, process, feedfile, storage, datalines, notes, compcheckerid)
			SELECT original, file, time, filestart, fileend, datastart, dataend, serial, size, postsize, process, feedfile, storage, datalines, notes, compcheckerid
			FROM ".$dbDatabase.".filesitter WHERE id = ".$fsQueue->id;
			$db->query($logfileFromFilesitterQuery);
	
			logger("Deleting filesitter entry: ".$fsQueue->id . ". (Line: " . __LINE__ . ")", LEVEL_INFORMATION);
			$logFileInsertId=$fsQueue->id;
			$db->query("DELETE FROM `$dbDatabase`.`filesitter` WHERE `id`=".$fsQueue->id);
		// ********** End Process 3
		}
	}
	else
	{
		logger("There are no feed files to process.", LEVEL_INFORMATION);
	}
	
	if (isset($cjVendorInfo))
	{
		unset($cjVendorInfo);
	}

	$db->query("UPDATE `$dbDatabase`.`globalmapping` SET datemod=".time()." WHERE `id`=1");
	
	logger("Commission Junction processing operation complete.");
}

function getCJFieldList()
{
	return "`ProgramName`, `ProgramURL`, `CatalogName`, `LastUpdated`, `Name`, `Keywords`, `Description`, `SKU`, `Manufacturer`, `ManufacturerID`, `UPC`, `ISBN`, `Currency`, `SalePrice`, `Price`, `RetailPrice`, `FromPrice`, `BuyURL`, `ImpressionURL`, `ImageURL`, `AdvertiserCategory`, `ThirdPartyID`, `ThirdPartyCategory`, `Author`, `Artist`, `Title`, `Publisher`, `Label`, `Format`, `Special`, `Gift`, `PromotionalText`, `StartDate`, `EndDate`, `Offline`, `Online`";
}

function verifyCJMasterTableExists($revision=1)
{
	// Verify that the Commission Junction master table (al_01_datapro) exists

	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);

	$masterTableName = 'al_01_datapro';
	
	global $db, $dbDatabase;
	$cjCheckMasterTableSQL = "DESCRIBE `$masterTableName`;";
	
	$db->query($cjCheckMasterTableSQL, false);
	
	if ($db->error)
	{
		// The table does not exist, so we need to create it.
		$cjCreateMasterTableSQL = cj_table($dbDatabase, $masterTableName, $revision);
		
		logger("Table `$masterTableName` does not exist. Creating...", LEVEL_DATA_WARNING);
		
		$db->query($cjCreateMasterTableSQL);
		logger("Table `$masterTableName` was created successfully.", LEVEL_INFORMATION);
	}
}

?>
