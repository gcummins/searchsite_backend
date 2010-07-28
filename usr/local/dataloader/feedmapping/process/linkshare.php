<?php
// Mapping and processing function for Linkshare feeds.

function loadLinkShareData($sourceTable, $targetTable)
{
	global $link, $db, $statsVendorsProcessed, $statsRecordsProcessed;
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);

	// Determine if the requested table exists
	$checkExistingTableQuery = "SHOW TABLES LIKE '$sourceTable';";
	$db->query($checkExistingTableQuery);
	
	if ($db->rowCount() == 0)
	{
		logger("The table '$sourceTable' does not exist. Skipping.", LEVEL_DATA_WARNING);
		return;
	}
	
	// Determine how many rows are in the table. This is used for statistical analysis.
	$recordCountSQL = "SELECT count(*) AS count FROM $sourceTable;";
	$db->query($recordCountSQL);
	
	$countRow = $db->firstObject();
	$totalRowCount = $countRow->count;
	$statsRecordsProcessed += $totalRowCount;
	
	// Create a speedcheck
	$speedcheck = new SpeedCheck("Copying LinkShare rows to permanent tables");
	
	logger("Loading data from LinkShare table '$sourceTable' containing $totalRowCount products.", LEVEL_INFORMATION);

	// Determine the ProgramName for this table
	$programName = ""; // Default value, in case we cannot find the program name using the queries below
	$programNameQuery = "SELECT `compname` FROM `compchecker` WHERE `tablename`='$targetTable' LIMIT 1;";
	$db->query($programNameQuery, false);
	
	if (false === $db->queryResult)
	{
		// Unable to find the program name in the table 'compchecker.' Check in tmp_compchecker
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
	if (file_exists(FEEDMAPPING_LINKSHARE_OUTFILE))
	{
		deleteDumpFile(FEEDMAPPING_LINKSHARE_OUTFILE);
	}	
	
	// Next, create an outfile containing all the products in this table.
	$createOutfileQuery = "SELECT
		null as `id`,
		'$programName' as `ProgramName`,
		'' as `ProgramURL`,
		NOW() as `LastUpdated`,
		`ProductName` as `ProductName`,
		`Keywords` as `Keywords`,
		`LongProductDescription` as `LongDescription`,
		'' as `InterimDescription`,
		`ShortProductDescription` as `ShortDescription`,
		'' as `BriefDescription`,
		`Sku Number` as `SKU`,
		`ManufacturerName` as `Manufacturer`,
		`ManufacturerPartNumber` as `ManufacturerID`,
		`UniversalPricingCode` as `UPC`,
		null as `ISBN`,
		`Currency` as `Currency`,
		`SalePrice` as `SalePrice`,
		`SalePrice` as `Price`,
		`RetailPrice` as `RetailPrice`,
		null as `FromPrice`,
		REPLACE(`ProductURL`, '&', '&amp;') as `BuyURL`,
		null as `AddToCartURL`,
		REPLACE(`ProductImageURL`, '&', '&amp;') as `ImageURL`,
		REPLACE(`Pixel`, '&', '&amp;') as `ImpressionURL`,
		`PrimaryCategory` as `Category`,
		`SecondaryCategories` as `SecondaryCategory`,
		null as `CategoryID`,
		null as `CategoryCrumbs`,
		null as `Author`,
		null as `Artist`,
		null as `Title`,
		null as `Publisher`,
		null as `Label`,
		null as `Format`,
		null as `Special`,
		null as `PromotionalText`, 
		if (`BeginDate` != '', CONCAT(str_to_date(`BeginDate`, '%m/%d/%Y'), ' 00:00:00'), '0000-00-00 00:00:00') as `StartDate`,
		if (`EndDate` != '', CONCAT(str_to_date(`EndDate`, '%m/%d/%Y'), ' 00:00:00'), '0000-00-00 00:00:00') as `EndDate`,
		null as `ShippingCost`
		INTO OUTFILE '" . FEEDMAPPING_LINKSHARE_OUTFILE . "' FIELDS OPTIONALLY ENCLOSED BY '\"'
		FROM `$sourceTable`;";
		
	logger("Creating data outfile from table '$sourceTable.'", LEVEL_FILE_OPERATION);
	$db->query($createOutfileQuery);
	
	// The dump file has been created. Now use "LOAD DATA INFILE" to load the formatted data into the new table.
	logger("Loading data from the outfile into table '$targetTable.'", LEVEL_DATABASE_OPERATION);
	$loadInfileQuery = "LOAD DATA INFILE '" . FEEDMAPPING_LINKSHARE_OUTFILE . "' INTO TABLE `$targetTable` FIELDS OPTIONALLY ENCLOSED BY '\"';";
	$db->query($loadInfileQuery);
	
	// Remove the file that we created
	//deleteDumpFile(FEEDMAPPING_LINKSHARE_OUTFILE, "Removing the data file '" . FEEDMAPPING_LINKSHARE_OUTFILE . "'...");

	// End the speed check
	$speedcheck->stop();
	logger($speedcheck->getLogMessage(), LEVEL_INFORMATION);
	
	// Remove the temporary table
	removeTable($sourceTable);
	
	// Increment the vendor count for stats
	$statsVendorsProcessed++;
}
?>
