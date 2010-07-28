<?php

// Performics feed processing for dataloader.

function processPerformicsFeed()
{
	// PERFORMICS
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $work, $link, $dbDatabase, $db;

	$varfile='';

	$truncateTableQuery = "TRUNCATE TABLE `$dbDatabase`.`tmpcompare`";
	$db->query($truncateTableQuery);

	// This is only true if db_creation has been dropped or deleted.
	$selectQuery = "SELECT * FROM `$dbDatabase`.`db_creation` where `dbset`=3";
	$db->query($selectQuery);
	
	if($db->rowCount() == 0)
	{
		$db->query("INSERT INTO `$dbDatabase`.`db_creation` (`dbset`, `numcount`) VALUES (3,0)");
	}

	$selectQuery = "SELECT * FROM `$dbDatabase`.`filesitter` WHERE `feedfile`=3 AND `process`=3";
	$db->query($selectQuery);
	
	// Loop through any relevant entries in filesitter.
	foreach ($db->arrays() as $fsQueue)
	{
		logger("Start Performics processing.  Filesitter process is \"" . getProcessName(4) . "\"");
		$rightNow=time();
		$updateQuery = "UPDATE `$dbDatabase`.`filesitter` SET `process` = 4, `datastart` = $rightNow WHERE `id` = ".$fsQueue['id'];
		$db->query($updateQuery);

		// Grab the filename from filesitter.
		$varfile = $fsQueue['file'];

		// ********** Start Process 1
		//
		logger("Loading Performics file: " . $work.$fsQueue['file']);
		
		// Empty master table.
		//if(!mysql_query("TRUNCATE TABLE ".$dbDatabase.".al_03_datapro", $link)) sqlError($link, __LINE__ , TRUE);
		$db->query("TRUNCATE TABLE `$dbDatabase`.`al_03_datapro`");
		
		// Load delimited file into a master table.
		//if(!mysql_query("LOAD DATA INFILE '".$work.$fsQueue['file']."' INTO TABLE al_03_datapro FIELDS TERMINATED BY '|' IGNORE 1 LINES", $link)) sqlError($link, __LINE__ , TRUE);
		$db->query("LOAD DATA INFILE '". addslashes($work.$fsQueue['file']) ."' INTO TABLE al_03_datapro FIELDS TERMINATED BY '\t' IGNORE 1 LINES");

		/*
		 * This section was made irrelevant when Performics changed the way they name their files in May 2010.
		 * It has been replaced by the next code block, which retrieves the "Brand" name from the table.
		// This is to get the vendor name.
		// programname is what I call the vendor.
		$pgmNameAry = array();
		$pgmNameAry = explode('.',$fsQueue['file']);
		$programname = $pgmNameAry[0];
		unset($pgmNameAry);
		*/
		
		// Extract the company name from the filename
		if (false !== ($positionOfPeriod = strpos($fsQueue['original'], '.')))
		{
		    // Remove the period and file extension
		    $programname = substr($fsQueue['original'], 0, $positionOfPeriod);
		}
		else
		{
		    $programname = $fsQueue['original'];
		}
		
		// Remove underscores from the name
		$programname = str_replace('_', ' ', $programname);
		
		/*
		// Retrieve the program name from the newly-created database table
		// Some fields do not include data in the Brand field. We will keep searching until we find one that does.
		
		// First, determine the number of rows in the table
		$db->query("SELECT COUNT(*) FROM `al_03_datapro`");
		$vendorRowCount = (int)$db->firstField();
		
		$programname = '';
		$startRecord = 1;
		while (empty($programname))
		{
		    $db->query("SELECT `Brand` FROM `al_03_datapro` LIMIT 0, $startRecord;");
		    
		    $programname = $db->firstField();
		    $startRecord++;
		    
		    if ($startRecord >= $vendorRowCount)
		    {
		        logger("The vendor name could not be determined for this catalog.", LEVEL_DATA_WARNING);
		        
		        // Remove the entry from filesitter
		        $db->query("DELETE FROM `$dbDatabase`.`filesitter` WHERE `process` = 4 AND `id` = ".$fsQueue['id']);
		        
		        // Remove the data from the table
		        $db->query("TRUNCATE TABLE `$dbDatabase`.`al_03_datapro`");
		        
		        logger("The load operation has been aborted for this catalog.", LEVEL_INFORMATION);
		        return;
		    }
		}
		*/
		logger("Processing Performics file: " . $work.$fsQueue['file']." for ".$programname);
		
		// Retrieve the number of rows in the master table.
		$db->query("SELECT COUNT(*) AS numRows FROM ".$dbDatabase.".al_03_datapro");
		$productsInDataproTable = $db->firstField();
		 
		logger("Load data from Performics complete.  Filesitter process goes to \"" . getProcessName(5) . "\"", LEVEL_DATABASE_OPERATION);
		
		$fsProc5 = "UPDATE `$dbDatabase`.`filesitter` SET `process`=5, `dataend`='$rightNow', `datalines`=$productsInDataproTable WHERE `id` = ".$fsQueue['id'];
		$db->query($fsProc5);

		// Start Process 2
		//
		// Escape quotes and metachrs from the vendorname.
		// Quotes will gag the following query otherwise.
		$sqlProgName = $db->escape_string($programname);
		$selectQuery = "SELECT * FROM `$dbDatabase`.`compchecker` WHERE `compname`='$sqlProgName' AND `file`=3 LIMIT 1";
		$db->query($selectQuery);
		
		if ($db->rowCount() == 1)
		{
		    logger("There was one row returned by this query.", LEVEL_MINUTIA);
		}
		else
		{
		    logger("There were " . $db->rowCount() . " rows returned by this query.", LEVEL_MINUTIA);
		}
		
		if ($db->rowCount())
		{		
			$compCheckRow = $db->firstObject();
		}

		// Table handling modifications.  A temporary table will always be created here.
		// If the vendor does not exist in compchecker, a production table will be created as well.
		// The temporary tables will be removed by srchtableloader.php when it is run.

		// Does the vendor table exist?
		if ($db->rowCount() && (trim($programname) == trim($compCheckRow->compname)))
		{
			// There is an existing vendor table.
			// Add a temporary record to tmp_compchecker.
			
			// Copy existing record from compchecker into tmp_compchecker.
			logger("Retrieving information about $sqlProgName from tmp_compchecker.", LEVEL_INFORMATION);
			$selectQuery = "SELECT `id`, `tablename` FROM `$dbDatabase`.`tmp_compchecker` WHERE `compname` = '$sqlProgName' AND `file`=3 LIMIT 1";
			$db->query($selectQuery);
			
			if (!$db->rowCount())
			{
				logger("No records were found in table tmp_compchecker where compname=$sqlProgName and file=3.", LEVEL_INFORMATION);
			}
			else
			{
				$tmpId = $db->firstObject();
				logger("Dropping table tmp_" . $tmpId->tablename, LEVEL_DATABASE_OPERATION);
				$dropTableQuery = "DROP TABLE IF EXISTS `$dbDatabase`.`tmp_" . $tmpId->tablename . "`";
				$db->query($dropTableQuery);
				
				logger("Deleting '" . $tmpId->tablename . "' entry from tmp_compchecker.", LEVEL_DATABASE_OPERATION);
				$deleteQuery = "DELETE FROM `$dbDatabase`.`tmp_compchecker` WHERE tablename='" . $tmpId->tablename . "'";
				$db->query($deleteQuery);
			}
			
			logger("Copying program information from compchecker to tmp_compchecker", LEVEL_DATABASE_OPERATION);
			$insertQuery = "INSERT INTO `$dbDatabase`.`tmp_compchecker` (`compname`, `vardatetime`, `file`, `norecs`, `tablename`, `companyname`, `approved`, `filename`, `working`, `reload`, `df_lines`, `df_size`, `reindex`) SELECT `compname`, `vardatetime`, `file`, `norecs`, `tablename`, `companyname`, `approved`, `filename`, `working`, `reload`, `df_lines`, `df_size`, `reindex` FROM `$dbDatabase`.`compchecker` WHERE `compname`='$sqlProgName' AND `file`=3";
			$db->query($insertQuery);
			
			logger("Updating the program entry in tmp_compchecker with the current time and process information.", LEVEL_DATABASE_OPERATION);
			$rightNow = time();
			$updateQuery = "UPDATE `$dbDatabase`.`tmp_compchecker` SET `vardatetime`='$rightNow', `norecs`=$productsInDataproTable, `filename`='".addslashes($fsQueue['file'])."', `working`=1, `reload`=0, `reindex`=1 WHERE `id` = ".$compCheckRow->id;
			$db->query($updateQuery);
			
			logger("Updating the program entry in compchecker with the current time and process information.", LEVEL_DATABASE_OPERATION);
			$updateQuery = "UPDATE `$dbDatabase`.`compchecker` SET `vardatetime`='$rightNow', `norecs`=$productsInDataproTable, `filename`='".addslashes($fsQueue['file'])."', `working`=1, `reload`=0, `reindex`=1 WHERE `id` = ".$compCheckRow->id;
			$db->query($updateQuery);
			
			logger("Updating the filesitter table with the new compchecker id.", LEVEL_DATABASE_OPERATION);
			$updateQuery = "UPDATE `$dbDatabase`.`filesitter` SET `compcheckerid` = ".$compCheckRow->id." WHERE `id` = ".$fsQueue['id'];
			$db->query($updateQuery);
						
			// Create temporary table
			$pfTableName="tmp_".$compCheckRow->tablename;
			logger("Creating Performics table '$pfTableName.'", LEVEL_DATABASE_OPERATION);
			$tmpTableDef = performics_table($dbDatabase, $pfTableName);
			$db->query($tmpTableDef);
			
			logger("Truncating table '$pfTableName'", LEVEL_DATABASE_OPERATION);
			$truncateTableQuery = "TRUNCATE TABLE `$dbDatabase`.`$pfTableName`";
			$db->query($truncateTableQuery);
		}
		else
		{
			// Vendor table does not exist, so create a new table.
			//
			// Generate new tablename.
			logger("Getting table number count for Performics.", LEVEL_INFORMATION);
			$selectQuery = "SELECT `numcount` FROM `$dbDatabase`.`db_creation` WHERE `dbset`=3";
			$db->query($selectQuery);
			if ($db->rowCount() == 0)
			{
				shutdown("Unable to determine the number of existing tables from db_creation. Shutting down.", LEVEL_CRITICAL);
			}
			
			// Increment the number to create a new table name
			$varCount = 1 + $db->firstField();  
			
			
			logger("Update the count of existing Performics tables.", LEVEL_DATABASE_OPERATION);
			$db->query("UPDATE `$dbDatabase`.`db_creation` SET `numcount`='$varCount' WHERE `dbset`=3");
			
			// Set the table name
			$tableName = "al_03_".$varCount;
			$tmpTableName = "tmp_$tableName";
			
			logger("Inserting vendor information into compchecker table.", LEVEL_DATABASE_OPERATION);
			$rightNow = time();			
			$db->query("INSERT INTO `$dbDatabase`.`compchecker` (`compname`, `vardatetime`, `file`, `norecs`, `tablename`, `companyname`, `filename`, `working`, `reindex`) VALUES ('$sqlProgName', '$rightNow', 3, $productsInDataproTable, '$tableName', '$sqlProgName', '".addslashes($fsQueue['file'])."', 1, 1 )");
			
			logger("Inserting vendor information into tmp_compchecker table.", LEVEL_DATABASE_OPERATION);
			$new_company_id = $db->insert_id(); // Get the company ID from the last insert statement
			$db->query("INSERT INTO `$dbDatabase`.`tmp_compchecker` (`compname`, `vardatetime`, `file`, `norecs`, `tablename`, `companyname`, `filename`, `working`, `reindex`) VALUES ('$sqlProgName', '$rightNow', 3, $productsInDataproTable, '$tableName', '$sqlProgName', '".addslashes($fsQueue['file'])."', 1, 1 )");
			
			logger("Adding new company to the filesitter table.", LEVEL_DATABASE_OPERATION);
			$db->query("UPDATE ".$dbDatabase.".filesitter SET compcheckerid = ".$new_company_id." WHERE id = ".$fsQueue['id']);

			
			logger("Creating new table: '$tmpTableName'' for vendor '$programname'", LEVEL_DATABASE_OPERATION);
			// Retrieve the table definition
			$tableDefinition = performics_table($dbDatabase, $tmpTableName);
			$db->query($tableDefinition);
			
			//logger("Truncating table '$pfTableName.'", LEVEL_DATABASE_OPERATION);
			//$db->query("TRUNCATE TABLE `$dbDatabase`.`$vartable`");
		}

		// Start Process 3
		//
		// Now move data from master table to temp table.
		logger("Retrieving information about company '$sqlProgName' from tmp_compchecker table.", LEVEL_INFORMATION);
		$db->query("SELECT `id`, `compname`, `tablename` FROM `$dbDatabase`.`tmp_compchecker` WHERE `compname`='$sqlProgName' AND `file`=3");
		
    	if ($db->rowCount() == 0)
    	{
    		shutdown("Failed to find information about company '$sqlProgName' in the tmp_compchecker table.", LEVEL_CRITICAL);
    	}
    	
 		$compCheckRow = $db->firstObject();
 		
		logger("Dumping products from 'al_03_datapro' into '" . DATALOADER_PERFORMICS_OUTFILE . "'", LEVEL_DATABASE_OPERATION);
 		$db->query("SELECT 0 as `id`, " . getPerformicsFieldList() . " INTO OUTFILE '" . DATALOADER_PERFORMICS_OUTFILE . "' FIELDS OPTIONALLY ENCLOSED BY '\"' FROM `al_03_datapro`;");

 		logger("Loading products from '" . DATALOADER_PERFORMICS_OUTFILE . "' into table 'tmp_" . $compCheckRow->tablename . ".'", LEVEL_DATABASE_OPERATION);
		$db->query("LOAD DATA INFILE '" . DATALOADER_PERFORMICS_OUTFILE . "' INTO TABLE `tmp_" . $compCheckRow->tablename . "` FIELDS OPTIONALLY ENCLOSED BY '\"';");
 		
		logger("Deleting the dump file...", LEVEL_FILE_OPERATION);
		if (file_exists(DATALOADER_PERFORMICS_OUTFILE) && is_writable(DATALOADER_PERFORMICS_OUTFILE))
		{
			if (@unlink(DATALOADER_PERFORMICS_OUTFILE))
			{
				logger("'" . DATALOADER_PERFORMICS_OUTFILE . "' has been deleted.", LEVEL_FILE_OPERATION);
			}
			else
			{
				logger("Failed to delete '" . DATALOADER_PERFORMICS_OUTFILE . "'.", LEVEL_DATA_WARNING);
			}
		}
		else
		{
			logger("The file '" . DATALOADER_PERFORMICS_OUTFILE . "' does not exist or is not writable.", LEVEL_DATA_WARNING);
		}
		
 		//logger("Copying data from 'al_03_datapro' into 'tmp_" . $compCheckRow->tablename . "'.", LEVEL_DATABASE_OPERATION);
		//$db->query("INSERT INTO `$dbDatabase`.`tmp_".$compCheckRow->tablename."` SELECT * FROM `$dbDatabase`.`al_03_datapro`");
 		
		// Retrieve statistics about the new table.
 		$db->query("SHOW TABLE STATUS FROM `$dbDatabase` LIKE 'tmp_" . $compCheckRow->tablename . "'");
 		
 		$table_search = $db->firstArray();
 		$varlines = $table_search['Rows'];
 		$varsize = $table_search['Data_length'] + $table_search['Index_length'];
		$rightNow = time();
		
		$db->query("UPDATE `$dbDatabase`.`tmp_compchecker` SET `vardatetime` = '$rightNow', `working` = 0, `df_lines` = $varlines, `df_size` = $varsize, `reindex` = 1 WHERE `id` = ".$compCheckRow->id);
		
		$db->query("TRUNCATE TABLE `$dbDatabase`.`al_03_datapro`");
		$rightNow=time();
		logger("Performics processing is almost complete.  Filesitter goes to process \"" . getProcessName(6) . "\"");
		$db->query("UPDATE `$dbDatabase`.`filesitter` SET `process`=6, `dataend`=$rightNow WHERE `id`=".$fsQueue['id']);

		$logfileFromFilesitterQuery="INSERT INTO `$dbDatabase`.`logfile`
		(`original`, `file`, `time`, `filestart`, `fileend`, `datastart`, `dataend`, `serial`, `size`, `postsize`, `process`, `feedfile`, `storage`, `datalines`, `notes`, `compcheckerid`)
		SELECT `original`, `file`, `time`, `filestart`, `fileend`, `datastart`, `dataend`, `serial`, `size`, `postsize`, `process`, `feedfile`, `storage`, `datalines`, `notes`, `compcheckerid`
		FROM `$dbDatabase`.`filesitter` WHERE `id` = ".$fsQueue['id'];

		$db->query($logfileFromFilesitterQuery, false);
		if ($db->error === true)
		{
			// This query may have failed because the table does not exist
			// (Possible cause: first install)
			// Lets try to create it.

			$createLogfileTableSQL = "CREATE TABLE IF NOT EXISTS `logfile` (
				`id` bigint(20) NOT NULL auto_increment,
				`original` varchar(50) NOT NULL default '',
				`file` varchar(50) NOT NULL default '',
				`time` int(11) NOT NULL default '0',
				`filestart` int(11) NOT NULL default '0',
				`fileend` int(11) NOT NULL default '0',
				`datastart` int(11) NOT NULL default '0',
				`dataend` int(11) NOT NULL default '0',
				`serial` varchar(32) NOT NULL default '',
				`size` varchar(25) NOT NULL default '',
				`postsize` varchar(25) NOT NULL default '0',
				`process` tinyint(4) NOT NULL default '0',
				`feedfile` int(11) NOT NULL default '0',
				`storage` varchar(50) NOT NULL default '',
				`datalines` bigint(20) NOT NULL default '0',
				`notes` text NOT NULL,
				`compcheckerid` int(11) NOT NULL default '0',
				PRIMARY KEY  (`id`),
				KEY `time` (`time`),
				KEY `process` (`process`)
				) ENGINE=MyISAM DEFAULT CHARSET=latin1";

			logger("Attempting to create table `logfile.`", LEVEL_DATABASE_OPERATION);
			$db->query($createLogfileTableSQL);
			
			// Try again to create the log record
			logger("Trying again to insert a log record.");
			$db->query($logfileFromFilesitterQuery);
		}

		logger("Deleting filesitter entry: ".$fsQueue['id']);
		$db->query("DELETE FROM `$dbDatabase`.`filesitter` WHERE `id` = ".$fsQueue['id']);
	}
	if (false === (unlink($work.$varfile)))
	{
		logger("Unable to delete file $work.$varfile. The file should be manually removed.", LEVEL_DATA_WARNING);
	}
	
	$db->query("UPDATE `".$dbDatabase."`.`globalmapping` SET datemod=".time()." where id=3", false);
	if ($db->error === true)
	{
		// Perhaps the table does not exist.
		// (Possible cause: first install)
 		// Lets try to create it
		$createGlobalmappingTableSQL = "CREATE TABLE `globalmapping` (
				`id` int(11) NOT NULL auto_increment,
				`filegenerator` varchar(30) NOT NULL default '',
				`nooffields` int(11) NOT NULL default '0',
				`datemod` int(11) NOT NULL default '0',
				PRIMARY KEY  (`id`)
				) ENGINE=MyISAM DEFAULT CHARSET=latin1";

		logger("Table `globalmapping` does not exist. Creating...");
		$db->query($createGlobalmappingTableSQL);
		
		logger("Table `globalmapping' was created successfully. Creating initial entries (CJ, LinkShare, Performics)...");

		// Insert the three feed sources into the table
		$insertSourcesSQL = "INSERT INTO `globalmapping` (filegenerator, nooffields) VALUES"
				. "('CJ', 35), "
				. "('LinkShare', 28), "
				. "('Performics', 23);";

		$db->query($insertSourcesSQL);
		
		logger("Entries were created successfully. Trying again to update the modification date...");
		// Try again to update the record
		$db->query("UPDATE `$dbDatabase`.`globalmapping` SET `datemod`=".time()." WHERE `id`=3");
		logger("Modification date was updated successfully.");
	}
	logger("Performics processing operation complete.");
}

function getPerformicsFieldList()
{
	//return "`ProductId`, `Category`, `ProductURL`, `ProductName`, `Price`, `Long_Description`, `Interim_Description`, `Short_Description`, `Brief_Description`, `BuyURL`, `Category_ID`, `UPC`, `Rew_Points`, `ImageURL`, `In_Stock`, `Shop_Avail`, `Shop_Cost`, `Remove_Date`, `Brand`, `Partner_Specific`, `Manufacturer`, `MPN`, `Product_Keywords`";
	//return "`ProductID`, `ProductName`, `ProductURL`, `BuyURL`, `ImageURL`, `Category`, `CategoryID`, `PFXCategory`, `BriefDesc`, `ShortDesc`, `IntermDesc`, `LongDesc`, `ProductKeyword`, `Brand`, `Manufacturer`, `ManfID`, `ManufacturerModel`, `UPC`, `Platform`, `MediaTypeDesc`, `MerchandiseType`, `Price`, `SalePrice`, `VariableCommission`, `SubFeedID`, `InStock`, `Inventory`, `RemoveDate`, `RewPoints`, `PartnerSpecific`, `ShipAvail`, `ShipCost`, `ShippingIsAbsolut`, `ShippingWeight`, `ShipNeeds`, `ShipPromoText`, `ProductPromoText`, `DailySpecialsInd`, `GiftBoxing`, `GiftWrapping`, `GiftMessaging`, `ProductContainerName`, `CrossSellRef`, `AltImagePrompt`, `AltImageURL`, `AgeRangeMin`, `AgeRangeMax`, `ISBN`, `Title`, `Publisher`, `Author`, `Genre`, `Media`, `Material`, `PermuColor`, `PermuSize`, `PermuWeight`, `PermuItemPrice`, `PermuSalePrice`, `PermuInventorySta`, `Permutation`, `PermutationSKU`, `BaseProductID`, `Option1Option2`, `Option3`, `Option4`, `Option5`, `Option6`, `Option7`, `Option8`, `Option9`, `Option10`, `Option11`, `Option12`, `Option13`, `Option14`, `Option15`, `Option16`, `Option17`, `Option18`, `Option19`, `Option20`";
	return "`Product_ID`, `Product_name`, `Product_URL`, `Buy_URL`, `Image_URL`, `Category`, `Category_ID`, `PFX_Category`, `Brief_desc`, `Short_desc`, `Interim_desc`, `Long_desc`, `Product_Keyword`, `Brand`, `Manufacturer`, `Manf_ID`, `Manufacture_model`, `UPC`, `Platform`, `Media_type_desc`, `Merchandise_type`, `Price`, `Sale_price`, `Variable_Commission`, `Sub_FeedID`, `In_Stock`, `Inventory`, `Remove_date`, `Rew_points`, `Publisher_Specific`, `Ship_avail`, `Ship_Cost`, `Shipping_is_absolute`, `Shipping_weight`, `Ship_needs`, `Ship_promo_text`, `Product_promo_text`, `Daily_specials_indicator`, `Gift_boxing`, `Gift_wrapping`, `Gift_messaging`, `Product_container_name`, `Cross_selling_reference`, `Alt_image_prompt`, `Alt_image_URL`, `Age_range_min`, `Age_range_max`, `ISBN`, `Title`, `Publisher`, `Author`, `Genre`, `Media`, `Material`, `Permutation_color`, `Permutation_size`, `Permutation_weight`, `Permutation_item_price`, `Permutation_sale_price`, `Permutation_inventory_status`, `Permutation`, `Permutation_SKU`, `BaseProductID`, `Option1_Value`, `Option2_Value`, `Option3_Value`, `Option4_Value`, `Option5_Value`, `Option6_Value`, `Option7_Value`, `Option8_Value`, `Option9_Value`, `Option10_Value`, `Option11_Value`, `Option12_Value`, `Option13_Value`, `Option14_Value`, `Option15_Value`, `Option16_Value`, `Option17_Value`, `Option18_Value`, `Option19_Value`, `Option20_Value`";
}

?>
