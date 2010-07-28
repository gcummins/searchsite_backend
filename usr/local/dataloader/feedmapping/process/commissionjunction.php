<?php
// Mapping and processing function for Commission Junction feeds.

function loadCJData($sourceTable, $targetTable)
{
	global $link, $db, $statsVendorsProcessed, $statsRecordsProcessed;

	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	// Determine if the requested table exists
	$checkExistingTableQuery = "SHOW TABLES LIKE '$sourceTable';";
	$db->query($checkExistingTableQuery);

	if (!$db->rowCount())
	{
		logger("The table '$sourceTable' does not exist. Skipping.", LEVEL_DATA_WARNING);
		return;
	}
	
	// Determine how many rows are in the table. This is used for statistical analysis.
	$recordCountQuery = "SELECT count(*) AS count FROM $sourceTable;";
	$db->query($recordCountQuery);
	
	$countRow = $db->firstObject();
	$totalRowCount = $countRow->count;
	$statsRecordsProcessed += $totalRowCount;
	
	// Create a speedcheck
	$speedcheck = new SpeedCheck("Copying CJ rows to permanent tables");
	
	logger("Loading data from Commission Junction table '$sourceTable' containing $totalRowCount products.", LEVEL_INFORMATION);

	// Determine the program name for thsi table
	$programName = ""; // Default value, in case we cannot find the program name using the queries below
	$db->query("SELECT `compname` FROM `tmp_compchecker` WHERE `tablename`='$targetTable' LIMIT 1;");
	
	if ($db->rowCount() == 0)
	{
		// Unable to find the program name in tmp_compchecker. Check in compchecker
		$db->query("SELECT `compname` FROM `compchecker` WHERE `tablename`='$targetTable' LIMIT 1;");
		if ($db->rowCount() != 0)
		{
			$programName = $db->escape_string($db->firstField());
		}
	}
	else
	{
		$programName = $db->escape_string($db->firstField());
	}
	
	// Create a dumpfile of all products in the selected table.
	
	if (file_exists(FEEDMAPPING_COMMISSIONJUNCTION_OUTFILE))
	{
		deleteDumpFile(FEEDMAPPING_COMMISSIONJUNCTION_OUTFILE);
	}
	
	$createOutfileQuery = "SELECT
		null as `id`,
		'$programName' as `ProgramName`,
		`ProgramURL` as `ProgramURL`,
		NOW() as `LastUpdated`,
		`Name` as `ProductName`,
		`Keywords` as `Keywords`,
		`Description` as `LongDescription`,
		'' as `InterimDescription`,
		'' as `ShortDescription`,
		'' as `BriefDescription`,
		`SKU` as `SKU`,
		`Manufacturer` as `Manufacturer`,
		`ManufacturerID` as `ManufacturerID`,
		`UPC` as `UPC`,
		`ISBN` as `ISBN`,
		`Currency` as `Currency`,
		`SalePrice` as `SalePrice`,
		`Price` as `Price`,
		`RetailPrice` as `RetailPrice`,
		`FromPrice` as `FromPrice`,
		REPLACE(`BuyURL`, '&', '&amp;') as `BuyURL`,
		null as `AddToCartURL`,
		REPLACE(`ImageURL`, '&', '&amp;') as `ImageURL`,
		REPLACE(`ImpressionURL`, '&', '&amp;') as `ImpressionURL`,
		null as `Category`,
		`ThirdPartyCategory` as `SecondaryCategory`,
		null as `CategoryId`,
		`AdvertiserCategory` as `CategoryCrumbs`,
		`Author` as `Author`,
		`Artist` as `Artist`,
		`Title` as `Title`,
		`Publisher` as `Publisher`,
		`Label` as `Label`,
		`Format` as `Format`,
		`Special` as `Special`,
		`PromotionalText` as `PromotionalText`,
		`StartDate` as `StartDate`,
		`EndDate` as `EndDate`,
		null as `ShippingCost` 
		INTO OUTFILE '" . FEEDMAPPING_COMMISSIONJUNCTION_OUTFILE . "' FIELDS OPTIONALLY ENCLOSED BY '\"'
		FROM `$sourceTable`;";
	
	logger("Creating data outfile from table '$sourceTable'", LEVEL_FILE_OPERATION);
	$db->query($createOutfileQuery);
	
	logger("Loading data from outfile into the table '$targetTable'", LEVEL_DATABASE_OPERATION);
	$db->query("LOAD DATA INFILE '" . FEEDMAPPING_COMMISSIONJUNCTION_OUTFILE . "' INTO TABLE `$targetTable` FIELDS OPTIONALLY ENCLOSED BY '\"';");

	// Remove the file that we created
	deleteDumpFile(FEEDMAPPING_COMMISSIONJUNCTION_OUTFILE, "Removing the data file '" . FEEDMAPPING_COMMISSIONJUNCTION_OUTFILE . "'...");
	
	// End the speed check
	$speedcheck->stop();
	logger($speedcheck->getLogMessage(), LEVEL_INFORMATION);

	// Remove the temporary table
	removeTable($sourceTable);
	
	// Increment the vendor counter
	$statsVendorsProcessed++;
}
?>