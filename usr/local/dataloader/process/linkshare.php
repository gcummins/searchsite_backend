<?php

// Linkshare feed processing for dataloader.
if (!defined('FEED_ID_LINKSHARE'))
{
	define('FEED_ID_LINKSHARE', 2);
}

function processLinkshareFeed()
{
	// LINKSHARE

	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $work, $db, $head_exec, $dbDatabase;
	
	$varfile='';
	$db->query("TRUNCATE TABLE `$dbDatabase`.`tmpcompare`");
	
	$db->query("SELECT * FROM `$dbDatabase`.`db_creation` WHERE `dbset`=" . FEED_ID_LINKSHARE);
	
	// This is only true if db_creation has been dropped or deleted.
	if($db->rowCount() == 0)
	{
		logger("No entry was found in table 'db_creation' for LinkShare feeds. This script believes that no LinkShare feeds exist. If this is incorrect, please notify the developers.", LEVEL_DATA_WARNING);
		$db->query("INSERT INTO `$dbDatabase`.`db_creation` (`dbset`, `numcount`) VALUES (" . FEED_ID_LINKSHARE . ",0)");
	}

	$db->query("SELECT * FROM `$dbDatabase`.`filesitter` WHERE `feedfile` = " . FEED_ID_LINKSHARE . " AND `process` = 3");
	
	// Loop through any relevant entries in filesitter.
	foreach ($db->objects() as $fsQueue)
	{
		// Initiate speed checking
		$speedcheck = new SpeedCheck("Linkshare process");
		
		$varfile=$fsQueue->file;
		logger("Start LinkShare processing.  Filesitter process goes to \"" . getProcessName(4) . "\"");
		$rightNow=time();
		$db->query("UPDATE `$dbDatabase`.`filesitter` SET `process` = 4, `datastart` = $rightNow WHERE `id` = ".$fsQueue->id);

		// ********** Start Process 4
		
		// Speed check: load file
		$speedCheckLoadFile = new SpeedCheck("Load Linkshare file '" . $fsQueue->file . "'");
		logger("Loading LinkShare file: " . $work.$fsQueue->file);
		
		// Load delimited file into a master table.
		
		// Empty master table.
		
		// First, check if the master table exists
		$db->query("DESCRIBE `al_0" . FEED_ID_LINKSHARE . "_datapro`;", false);
		if ($db->error === true)
		{
			// The table does not exist, so we need to create it.
			$linkshareCreateMasterTableSQL = linkshare_table($dbDatabase, 'al_0' . FEED_ID_LINKSHARE . '_datapro');
			logger("Table 'al_0" . FEED_ID_LINKSHARE . "_datapro' does not exist. Creating...");
			$db->query($linkshareCreateMasterTableSQL);
			logger("Table `al_0" . FEED_ID_LINKSHARE . "_datapro` was created successfully.");
		}

		logger("Truncating table 'al_0" . FEED_ID_LINKSHARE . "_datapro.", LEVEL_DATABASE_OPERATION);
		$db->query("TRUNCATE TABLE `$dbDatabase`.`al_0" . FEED_ID_LINKSHARE . "_datapro`");
		logger("Loading data from file '" . $work.$fsQueue->file . "' into al_0" . FEED_ID_LINKSHARE . "_datapro.", LEVEL_DATABASE_OPERATION);
		$db->query("LOAD DATA INFILE '".$work.$fsQueue->file."' INTO TABLE al_0" . FEED_ID_LINKSHARE . "_datapro FIELDS TERMINATED BY '|' IGNORE 1 LINES");

		// Due to filesize limits in php, we use this ugly hack to grab the first
		// line of a Linkshare file.
		// This is to get the vendor name.
		//
		$hdrLine=array();
		$pgmNameAry = array();
		logger("Executing '{$head_exec}head -1 " . $work.$fsQueue->file . "' to get the vendor name.", LEVEL_FILE_OPERATION);
		exec($head_exec . 'head -1 ' . $work.$fsQueue->file, $arrHeadOutput, $errLvl);
		logger("Result of head command (serialized): " . implode(' :: ', $arrHeadOutput), LEVEL_DEBUG);
		foreach($arrHeadOutput as $outputLine)
		{
			if(strlen($outputLine)!=0)
			{
				logger("The following data will be placed in \$pgmNameAry: $outputLine", LEVEL_DEBUG);
				$pgmNameAry = explode("|", $outputLine);
				break;
			}
		}
		if (!count($arrHeadOutput) || !is_array($pgmNameAry) || count($pgmNameAry) < 3)
		{
			logger("This file does not contain a valid header and will be removed.", LEVEL_DATA_WARNING);
			
			// Delete the file
			if (false === (unlink($work.$varfile)))
			{
				logger("Unable to delete file '" . $work.$varfile . ".' The file must be deleted manually.", LEVEL_DATA_WARNING);
			}
			else
			{
				logger("Done deleting ".$work.$varfile, LEVEL_INFORMATION);
			}
			
			// Delete relavant table entries.
			logger("Deleting entry for this vendor from the filesitter table.", LEVEL_DATABASE_OPERATION);
			$db->query("DELETE FROM `$dbDatabase`.`filesitter` WHERE `id`=" . $fsQueue->id);
			
			// Skip to the next file in the list
			continue;
		}

		// programname is another term for "vendor"
		$programname = $pgmNameAry[2];
		logger("Program Name is: $programname", LEVEL_DEBUG);
		unset($pgmNameAry);

		// Get the number of rows in the master table.
		logger("Processing LinkShare file: " . $work.$fsQueue->file." for ".$programname);
		$db->query("SELECT COUNT(*) AS numRows FROM `$dbDatabase`.`al_0" . FEED_ID_LINKSHARE . "_datapro`");
		$productCountInDatapro = $db->firstField();

		logger("Load Data from LinkShare complete.  Filesitter process goes to \"" . getProcessName(5) . "\"");
		$db->query("UPDATE `$dbDatabase`.`filesitter` SET `process`=5, `dataend`='$rightNow', `datalines`=$productCountInDatapro WHERE `id` = ".$fsQueue->id);

		// End process 4 speed check
		$speedCheckLoadFile->stop();
		logger($speedCheckLoadFile->getLogMessage(), LEVEL_INFORMATION);
		
		// Start Process 5
		$speedCheckProcess5 = new SpeedCheck("Process 5");
		
		// Escape quotes and metachrs from the vendorname.
		// Quotes will cause the following query to fail.
		$sqlProgName = $db->escape_string($programname);
		logger("Retrieving information about program '$sqlProgName' FROM compchecker.", LEVEL_MINUTIA);
		$db->query("SELECT * FROM `$dbDatabase`.`compchecker` WHERE `compname` = '$sqlProgName' AND `file`=" . FEED_ID_LINKSHARE);
		
		logger("There were " . $db->rowCount() . " rows returned by this query.", LEVEL_MINUTIA);
		if ($db->rowCount())
		{
			$compCheckRow = $db->firstObject();
		}
		
		// Table handling modifications.  A temporary table will always be created here.
		// If the vendor does not exist in compchecker, a production table will be created as well.
		// The temporary tables will be removed by srchtableloader.php when it is run.
		// At that time the data will be transferred to the production table and indexed by
		// the sphinx indexer.

		// Existing vendor table?
		//
		if ($db->rowCount() && (trim($programname) == trim($compCheckRow->compname)))
		{
			// Yes. Production table exists.
			// Add a temporary record to tmp_compchecker.
			//
			// Copy existing record from compchecker into tmp_compchecker.

			logger("Retrieving information about program '$programname' from the compchecker table.", LEVEL_INFORMATION);
			$db->query("SELECT `id`, `tablename` FROM `$dbDatabase`.`compchecker` WHERE `compname`='$sqlProgName' AND `file`=" . FEED_ID_LINKSHARE . " LIMIT 1;");
			if ($db->rowCount() == 0)
			{
				logger("No records were found in table compchecker where compname=$sqlProgName and file=3.", LEVEL_INFORMATION);
			}
			else
			{
				$tmpId = $db->firstObject();
				logger("Dropping existing temporary table 'tmp_" . $tmpId->tablename . "'", LEVEL_DATABASE_OPERATION);
				$db->query("DROP TABLE IF EXISTS `$dbDatabase`.`tmp_".$tmpId->tablename . "`");
				
				logger("Removing existing entry from tmp_compchecker.", LEVEL_INFORMATION);
				$db->query("DELETE FROM `$dbDatabase`.`tmp_compchecker` WHERE `tablename`='".$tmpId->tablename."'");
			}
			
			// Copy the record from compchecker into tmp_compchecker
			logger("Copying program information from compchecker to tmp_compchecker", LEVEL_DATABASE_OPERATION);
			$db->query("INSERT INTO `$dbDatabase`.`tmp_compchecker` (`compname`, `vardatetime`, `file`, `norecs`, `tablename`, `companyname`, `approved`, `filename`, `working`, `reload`, `df_lines`, `df_size`, `reindex`) SELECT `compname`, `vardatetime`, `file`, `norecs`, `tablename`, `companyname`, `approved`, `filename`, `working`, `reload`, `df_lines`, `df_size`, `reindex` FROM `$dbDatabase`.`compchecker` WHERE `compname` = '$sqlProgName' AND `file`=" . FEED_ID_LINKSHARE);
			
			// Updated the entry in tmp_compchecker
			logger("Updating the program entry in tmp_compchecker with the current time and process information.", LEVEL_DATABASE_OPERATION);
			$rightNow = time();
			$db->query("UPDATE `$dbDatabase`.`tmp_compchecker` SET `vardatetime`='$rightNow', `norecs`=$productCountInDatapro, `filename`='$varfile', `working`=1, `reload`=0, `reindex`=1 WHERE `compname`='$sqlProgName'");
			
			// Update compchecker
			logger("Updating the program entry in compchecker with the current time and process information.", LEVEL_DATABASE_OPERATION);
			$db->query("UPDATE `$dbDatabase`.`compchecker` SET `vardatetime`='$rightNow', `norecs`=$productCountInDatapro, `filename`='$varfile', `working`=1, `reload`=0, `reindex`=1 WHERE `compname` = '$sqlProgName'");
			
			// Update filesitter with the new compchecker ID
			logger("Updating the filesitter table with the new compchecker id.", LEVEL_DATABASE_OPERATION);
			$db->query("UPDATE `$dbDatabase`.`filesitter` SET `compcheckerid`=".$compCheckRow->id." WHERE `id`=".$fsQueue->id);
			
			// Create temporary table
			$lsTableName="tmp_".$compCheckRow->tablename;
			logger("Creating Linkshare table '$lsTableName.'", LEVEL_DATABASE_OPERATION);
			$tmpTableDef = linkshare_table($dbDatabase, $lsTableName);
			$db->query($tmpTableDef);
			
			logger("Truncating table '$lsTableName'", LEVEL_DATABASE_OPERATION);
			$truncateTableQuery="TRUNCATE TABLE `".$dbDatabase."`.`".$lsTableName."`";
			$db->query($truncateTableQuery);
		}
		else
		{
			// No. Production table does not exist, create new production table.
			// Generate new production tablename.
			$db->query("SELECT `numcount` FROM `$dbDatabase`.`db_creation` WHERE `dbset`=" . FEED_ID_LINKSHARE);
			$varCount = 1 + $db->firstField();
			logger("There are currently " . $db->firstField() . " LinkShare tables according to the db_creation table. Creating new table with ID number " . (1+$db->firstField()) . ".", LEVEL_INFORMATION);
			
			logger("Updating db_creation with the new table count.", LEVEL_DATABASE_OPERATION);
			$db->query("UPDATE `$dbDatabase`.`db_creation` SET `numcount` = '$varCount' WHERE `dbset` = " . FEED_ID_LINKSHARE);
			$vartable = "al_0" . FEED_ID_LINKSHARE . "_$varCount";
			// End generating new production tablename.
			
			// Add this vendor info into compchecker and tmp_compchecker.
			$rightNow = time();
			$db->query("INSERT INTO `$dbDatabase`.`compchecker` (`compname`, `vardatetime`, `file`, `norecs`, `tablename`, `companyname`, `filename`, `working`, `reindex`) VALUES ('$sqlProgName', '$rightNow', " . FEED_ID_LINKSHARE . ", '$productCountInDatapro', '$vartable', '$sqlProgName', '" . $fsQueue->file . "', 1, 1 )");
			$db->query("INSERT INTO `$dbDatabase`.`tmp_compchecker` (`compname`, `vardatetime`, `file`, `norecs`, `tablename`, `companyname`, `filename`, `working`, `reindex`) VALUES ('$sqlProgName', '$rightNow', " . FEED_ID_LINKSHARE . ", '$productCountInDatapro', '$vartable', '$sqlProgName', '" . $fsQueue->file . "', 1, 1 )");
			
			
			// Moved creation of the permanent table to the feedFieldMapping process, since the table is populate
			// in that script as well. --gcummins, 2008-12-23
			//logger("Creating new table: '$vartable' for '$programname'", LEVEL_DATABASE_OPERATION);
			//$varTblCreate = linkshare_table($dbDatabase, $vartable);
			//$db->query($varTblCreate);
			
			//logger("Adding an index field 'id' to table '$vartable'", LEVEL_DATABASE_OPERATION);
			//$db->query("ALTER TABLE `$dbDatabase`.`$vartable` ADD id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY");
			// Done creating new table.
			
			// Create tmp table
			logger("Creating new temporary table: 'tmp_$vartable' for '$programname'", LEVEL_DATABASE_OPERATION);
			$lsTableName="tmp_".$vartable;
			$tmpTableDef = linkshare_table($dbDatabase, $lsTableName);
			$db->query($tmpTableDef);
			
			logger("Truncate existing table '$lsTableName'.", LEVEL_DATABASE_OPERATION);
			$ttDef="TRUNCATE TABLE `".$dbDatabase."`.`".$lsTableName."`";
			$db->query($ttDef);

			// Done creating new and tmp table.
		}

		// End of speed check for process 5
		$speedCheckProcess5->stop();
		logger($speedCheckProcess5->getLogMessage(), LEVEL_INFORMATION);
		
		// Start Process 6
		
		// Now move data from datapro table to temp table.
		
		// Speed check for process 6
		$speedCheckProcess6 = new SpeedCheck("Process 6");
		
    	$db->query("SELECT `id`, `compname`, `tablename` FROM `$dbDatabase`.`tmp_compchecker` WHERE `compname` = '$sqlProgName' AND `file` = " . FEED_ID_LINKSHARE . " LIMIT 1");
 		$compCheckRow = $db->firstObject();
 		
		$db->query("DESCRIBE `$dbDatabase`.`tmp_".$compCheckRow->tablename);
		
		foreach($db->objects() as $descAry)
		{
			if(trim($descAry->Field) == "id")
			{
				$db->query("ALTER TABLE `$dbDatabase`.`tmp_".$compCheckRow->tablename."` DROP id");
			}
		}
		logger("Copy data from 'al_0" . FEED_ID_LINKSHARE . "_datapro' into 'tmp_" . $compCheckRow->tablename . ".'", LEVEL_DATABASE_OPERATION);
 		$db->query("INSERT INTO ".$dbDatabase.".tmp_" . $compCheckRow->tablename . " SELECT * FROM `$dbDatabase`.`al_0" . FEED_ID_LINKSHARE . "_datapro`");

 		logger("Altering table 'tmp_" . $compCheckRow->tablename . "' to add index field 'id.'", LEVEL_DATABASE_OPERATION);
 		$db->query("ALTER TABLE `$dbDatabase`.`tmp_" . $compCheckRow->tablename . "` ADD `id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY");

 		$db->query("SHOW TABLE STATUS FROM `$dbDatabase` LIKE 'tmp_" . $compCheckRow->tablename ."'");
 		$table_search = $db->firstArray();
 		$varlines = $table_search['Rows'];
 		$varsize = $table_search['Data_length'] + $table_search['Index_length'];
		$rightNow = time();
		
		logger("Updating tmp_compchecker record for '$sqlProgName.'", LEVEL_DATABASE_OPERATION);
		$db->query("UPDATE ".$dbDatabase.".tmp_compchecker SET vardatetime = '".$rightNow."', working = 0, df_lines = $varlines, df_size = $varsize, reindex = 1 where compname = '$sqlProgName'");

		logger("Truncating table 'al_0" . FEED_ID_LINKSHARE . "_datapro.'", LEVEL_DATABASE_OPERATION);
		$db->query("TRUNCATE TABLE ".$dbDatabase.".al_0" . FEED_ID_LINKSHARE . "_datapro");
		
		$rightNow=time();
		logger("LinkShare processing is almost finished.  Filesitter goes to process \"" . getProcessName(6) . "\"");
		$db->query("UPDATE `$dbDatabase`.`filesitter` SET `process` = 6, `dataend` = $rightNow WHERE `id` = ".$fsQueue->id);

		logger("Creating entry in table 'logfile.'", LEVEL_DATABASE_OPERATION);
		$logfileFromFilesitterQuery="INSERT INTO `$dbDatabase`.`logfile`
		(`original`, `file`, `time`, `filestart`, `fileend`, `datastart`, `dataend`, `serial`, `size`, `postsize`, `process`, `feedfile`, `storage`, `datalines`, `notes`, `compcheckerid`)
		SELECT `original`, `file`, `time`, `filestart`, `fileend`, `datastart`, `dataend`, `serial`, `size`, `postsize`, `process`, `feedfile`, `storage`, `datalines`, `notes`, `compcheckerid`
		FROM `$dbDatabase`.`filesitter` WHERE `id` = ".$fsQueue->id;
		$db->query($logfileFromFilesitterQuery);

		logger("Deleting filesitter entry: ".$fsQueue->id);
		$db->query("DELETE FROM `$dbDatabase`.`filesitter` WHERE `id` = ".$fsQueue->id);
		
		$speedCheckProcess6->stop();
		logger($speedCheckProcess6->getLogMessage(), LEVEL_INFORMATION);
		
		$speedcheck->stop();
		logger($speedcheck->getLogMessage());
	}
	if (false === (unlink($work.$varfile)))
	{
		logger("Unable to delete file '" . $work.$varfile . ".' The file must be deleted manually.", LEVEL_DATA_WARNING);
	}
	else
	{
		logger("Done deleting ".$work.$varfile);
	}
	$db->query("UPDATE `$dbDatabase`.`globalmapping` SET `datemod`=".time()." WHERE `id`=" . FEED_ID_LINKSHARE);
	
	logger("Linkshare processing operation complete.");
}
?>
