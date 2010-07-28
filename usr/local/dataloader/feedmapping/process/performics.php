<?php

// Mapping and processing function for Performics feeds
function loadPerformicsData($sourceTable, $targetTable)
{
	global $link, $db, $statsVendorsProcessed, $statsRecordsProcessed;

	// Determine if the requested table exists
	$checkExistingTableQuery = "SHOW TABLES LIKE '$sourceTable';";
	$db->query($checkExistingTableQuery);
	
	if (!$db->rowCount())
	{
		logger("The table '$sourceTable' does not exist. Skipping.", LEVEL_DATA_WARNING);
		return;
	}
	
	// Determine how many rows are in the table.
	$recordCountSQL = "SELECT count(*) AS count FROM $sourceTable;";
	$db->query($recordCountSQL);
	$countRow = $db->firstObject();
	$totalRowCount = $countRow->count;
	$statsRecordsProcessed += $totalRowCount;
	
	// Create a speedcheck
	$speedcheck = new SpeedCheck("Copying Performics rows to permanent tables");
	
	logger("Loading data from Performics table '$sourceTable' containing $totalRowCount products.");

	// Determine the ProgramName for this table
	$programName = ""; // Default value, in case we cannot find the program name using the queries below
	$programNameQuery = "SELECT `compname` FROM `compchecker` WHERE `tablename`='$targetTable' LIMIT 1;";
	$db->query($programNameQuery, false);
	
	if (false === $db->queryResult)
	{
		// Unable to find the program name in the table 'compchecker.' Check in 'tmp_compchecker.'
		$programNameQuery = "SELECT `compname` FROM `tmp_compchecker` WHERE `tablename`='$targetTable' LIMIT 1;";
		$db->query($programNameQuery);
		
		if ($db->rowCount())
		{
			$row = $db->firstObject();
			$programName = $db->escape_string($row->compname);
		}
	}
	else
	{
		$row = $db->firstObject();
		$programName = $db->escape_string($row->compname);
	}
	
	// We will create a dumpfile of all the products in the selected table.
	
	// First, delete the dump file if it exists
	if (file_exists(FEEDMAPPING_PERFORMICS_OUTFILE))
	{
		deleteDumpFile(FEEDMAPPING_PERFORMICS_OUTFILE);
	}
	
	// Next, create an outfile containing all the products in this table.
	$createOutfileQuery = "SELECT
		null as `id`,
		'$programName' as `ProgramName`,
		'' as `ProgramURL`,
		NOW() as `LastUpdated`,
		`Product_name` as `ProductName`,
		`Product_Keyword` as `Keywords`,
		`Long_desc` as `LongDescription`,
		`Interim_desc` as `InterimDescription`,
		`Short_desc` as `ShortDescription`,
		`Brief_desc` as `BriefDescription`,
		'' as `SKU`,
		`Manufacturer` as `Manufacturer`,
		`Manf_ID` as `ManufacturerID`,
		`UPC` as `UPC`,
		`ISBN` as `ISBN`,
		null as `Currency`,
		`Sale_price` as `SalePrice`,
		`Price` as `Price`,
		null as `RetailPrice`,
		null as `FromPrice`,
		REPLACE(`Product_URL`, '&', '&amp;') as `BuyURL`,
		null as `AddToCartURL`,
		REPLACE(`Image_URL`, '&', '&amp;') as `BuyURL`,
		null as `ImpressionURL`,
		`Category` as `Category`,
		null as `SecondaryCategory`,
		`Category_ID` as `CategoryID`,
		`Category` as `CategoryCrumbs`,
		`Author` as `Author`,
		null as `Artist`,
		`Publisher` as `Publisher`,
		null as `Label`,
		`Media` as `Format`,
		`Daily_specials_indicator` as `Special`,
		`Product_promo_text` as `PromotionalText`,
		null as `StartDate`,
		`Remove_Date` as `EndDate`,
		`Ship_Cost` as `ShippingCost`
		INTO OUTFILE '" . FEEDMAPPING_PERFORMICS_OUTFILE . "' FIELDS OPTIONALLY ENCLOSED BY '\"'
		FROM `$sourceTable`;";
	
	logger ("Creating data outfile from table '$sourceTable.'", LEVEL_FILE_OPERATION);
	$db->query($createOutfileQuery);
	
	// The table has been created. Now use "LOAD DATA INFILE" to load the formatted data into the new table.
	logger("Loading data from the outfile into table '$targetTable.'", LEVEL_DATABASE_OPERATION);
	$loadInfileQuery = "LOAD DATA INFILE '" . FEEDMAPPING_PERFORMICS_OUTFILE . "' INTO TABLE `$targetTable` FIELDS OPTIONALLY ENCLOSED BY '\"';";
	$db->query($loadInfileQuery);
	
	// Remove the file that we created
	deleteDumpFile(FEEDMAPPING_PERFORMICS_OUTFILE, "Removing the data file '" . FEEDMAPPING_PERFORMICS_OUTFILE . "'...");
	
	// End the speed check
	$speedcheck->stop();
	logger($speedcheck->getLogMessage(), LEVEL_INFORMATION);
	
	// Remove the temporary table
	removeTable($sourceTable);
	
	$statsVendorsProcessed++;
}

?>