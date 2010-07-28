<?php

// Generic functions
if (file_exists('../connect.php'))
{
	include_once "../connect.php";
}

define('TABLE_RESTRICTED_PROCESS_FILES', 'restrictedProcessFiles');

function changeProcessStatus($processName, $status, $pid=0)
{
	global $db;
	
	if (!isset($db) || !is_object($db))
	{
		$db = new DatabaseConnection(DATABASE_HOST, DATABASE_USER, DATABASE_PASSWORD, DATABASE_NAME);
	}
	
	switch ($status)
	{
		case 'end':
		case PROCESS_STATUS_END:
			// Delete the process entry from the table
			$db->query("DELETE FROM `activeProcesses` WHERE `processName`='$processName'");
			break;
		case PROCESS_STATUS_ACTIVE:
		default:
			// Add the process entry to the table
			$db->query("INSERT INTO `activeProcesses` (`pid`, `processName`, `unixStartTime`, `status`) VALUES ($pid, '$processName', " . time() . ", $status) ON DUPLICATE KEY UPDATE `status`='$status', `unixStartTime`=" . time() . ";");
			break;
	}
	
}

function checkLogFile($filename)
{
	// Ensure that the file specified exists and is writable.
	// If the file does not exist, we will attempt to create it
	
	if (!file_exists($filename))
	{
		// Attempt to create the file
		$fh = @fopen($filename, 'w');
		
		if (false === $fh)
		{
			return -1;
		}
		else
		{
			fclose($fh);
		}
	}
	
	if (!is_writable($filename))
	{
		return -2;
	}
	else
	{
		return 0;
	}
	
}

function createPIDFile($pidFileName, $pid)
{
	/*
	 *
	 * Input:
	 *	Name of the PID file: $pidFileName
	 *	Data to be written to the PID file: $pid
	 *
	 * Output:
	 *	Integer indicating success (0) or failure (1-4):
	 *		1: Unable to open file for writing
	 *		2: Unable to lock file
	 *		3: Write failure
	 *		4: Truncation failure
	 * 
	 */
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	if (DEBUG)
	{
		logger("FILENAME: $pidFileName, PID: $pid");
	}
	
	global $pidFileHandle;	// This is necesary because the flock used in the function is broken when the
							// file handle goes out of scope. Using a global variable ensures that the 
							// file handle will not go out of scope when the function returns.
	
	$pidFileHandle = @fopen($pidFileName, 'x');
	
	if (false === $pidFileHandle)
	{
		if (file_exists($pidFileName))
		{
		    $pidMessage = "The PID file '$pidFileName' exists, which means that another instance of this program is running or has crashed. If no other instance is running, remove the PID file and try again.";
			shutdown($pidMessage, true);
		}
		else
		{
			shutdown("Unable to open the PID file for writing. Exiting..", true);
		}
		exit;
	}
	else
	{
		if (flock($pidFileHandle, LOCK_EX | LOCK_NB))	// Do an exclusive lock
		{
			// First, empty the file. We don't want two PIDs in the same file
			if (ftruncate($pidFileHandle, 0))
			{
				if (fwrite($pidFileHandle, $pid))
				{
					logger("Created and locked the PID file.", LEVEL_STATUS);
					return 0;
				}
				else
				{
					// Write failed
					shutdown("Failed to write PID to the PID file. Exiting...", true);
				}
			}
			else
			{
				// Truncate failed
				shutdown("Failed to truncate the PID file.Exiting...", true);
			}
		}
		else
		{
			// Unable to lock file
			shutdown("Unable to lock the PID file. Exiting...", true);
		}
	}
}

function deletePIDFile($pidFileName)
{
	if (file_exists($pidFileName))
	{
		if (unlink($pidFileName))
		{
			logger("The PID file has been deleted successfully.", LEVEL_MINUTIA);
		}
		else
		{
			logger("Unable to delete PID file.", LEVEL_DATA_WARNING);
		}
	}
	else
	{
		logger("The specified PID file ($pidFileName) does not exists, and cannot be deleted.", LEVEL_DATA_WARNING);
	}
}

function createPIDFile_old($filename, $pid)
{
	// Create a PID file for a daemon script
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	if (false === ($fh = fopen($filename, 'w')))
	{
		return ERROR_CANNOT_OPEN_FILE;
	}
	if (false === fwrite($fh, $pid))
	{
		return ERROR_CANNOT_WRITE_TO_FILE;
	}
	if (false === fclose($fh))
	{
		return ERROR_CANNOT_CLOSE_FILE;
	}
	return 0;
}

function doesTableExist($tablename)
{
	// Determine if the requested table exists
	global $link;
	
	$checkExistingTableQuery = "SHOW TABLES LIKE '$tablename';";
	if (false === ($checkExistingTableResult = mysql_query($checkExistingTableQuery, $link)))
	{
		shutdown("Query failed: $checkExistingTableQuery. MySQL said: " . mysql_error($link));
	}
	if (!mysql_num_rows($checkExistingTableResult))
	{
		return false;
	}
	return true;
}

function errorHandler($errno, $errstr, $errfile, $errline)
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	switch ($errno)
	{
		case E_ALL:
			$error_note = "ERROR: $errno $errstr\n";
			$error_note .= "  Fatal error in line $errline of file $errfile";
			$error_note .= "Aborting...\n";
			logger($error_note);
			shutdown($error_note);
			break;
		case E_USER_WARNING:
			echo "WARNING: $errno $errstr\n";
			break;
		case E_USER_NOTICE:
			echo "NOTICE: $errno $errstr\n";
			break;
	 }
	 logger("Error handler was called. errno=$errno, errstr=$errstr, errfile=$errfile, errline=$errline.", LEVEL_PROGRAMMING_WARNING);
	 
}

function genericFilePermissionChecker($filename, $restrictionType)
{
	/*
	 * This function is a generic restriction checker.
	 * It should be accessed only via one of the following functions:
	 *   mayDownload()
	 *   mayStore()
	 *   mayUploadToDatabase()
	 *   mustDeleteIfExists()
	 */
	
	global $db;
	
	if (fileIsRestrictedInCompchecker($filename))
	{
	    logger("File '$filename' is restricted in compchecker", LEVEL_DEBUG);
	    return false;
	}
	else
	{	 	
        $db->query("SELECT `$restrictionType` FROM `" . TABLE_RESTRICTED_PROCESS_FILES . "` WHERE `filename` = '" . addslashes($filename) . "' LIMIT 1;");
	
    	if ($db->rowCount() == 0)
    	{
            // No restrictions exist, so the table may be loaded.
    	    return true;
    	}
    	
    	$row = $db->firstObject();
    	
    	if ($row->{$restrictionType} == 0)
    	{
    		return true;
    	}
    	else
    	{
    		// This file has been restricted
    		return false;
    	}
	}
}

function getTableName($filename)
{
    // Determine the name of the tables associated with this filename
    
    global $db;
    
    $db->query("SELECT `tablename` FROM `compchecker` WHERE `filename` = '" . $db->escape_string($filename) . "' LIMIT 1;");
    if (!$db->rowCount())
    {
        // No entry was found. Determine if the filename has an extension such as gz, gzip, or zip
        $positionOfPeriod = strrpos($filename, '.');
        if ($positionOfPeriod === false)
        {
            return false; // No file extension were found
        }
        else
        {
            $fileExtension = substr($filename, $positionOfPeriod+1);
            if ($fileExtension == "gz" || $fileExtension == "gzip" || $fileExtension == "zip")
            {
                $shortenedFileName = substr($filename, 0, strlen($filename) - strlen($fileExtension ) - 1);
                $db->query("SELECT `tablename` FROM `compchecker` WHERE `filename` = '" . $db->escape_string($shortenedFileName) . "' LIMIT 1;");
                if (!$db->rowCount())
                {
                    // No matching record was found
                    return false;
                }
                else
                {
                    return $db->firstField();
                }
            }
            else
            {
                return false;
            }
        }
    }
    else
    {
        return $db->firstField();
    }
}

function fileIsRestrictedInCompchecker($filename)
{
    logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
    
    // Check if the supplied filename is restricted in the compchecker table.
    global $db;
    
    $db->query("SELECT * FROM `compchecker` where `filename` = '" . $db->escape_string($filename) . "' AND `approved` != 0");
    logger("Compchecker Row Count: " . $db->rowCount(), LEVEL_DEBUG);
    if ($db->rowCount() != 0)
    {
        // The file is restricted in compchecker
        logger("The file '$filename' is listed as unapproved in the compchecker table.", LEVEL_INFORMATION);
        return true;
    }
    else
    {
        // Check if the file exists without an extension such as '.gz', '.gzip', or '.zip'
        $positionOfPeriod = strrpos($filename, '.');
        if ($positionOfPeriod === false)
        {
            return false; // No file extension were found
        }
        else
        {
            $fileExtension = substr($filename, $positionOfPeriod+1);
            if ($fileExtension == "gz" || $fileExtension == "gzip" || $fileExtension == 'zip')
            {
                // Test the database to see if the file is blocked, checking for a filename without this extension
                $shortenedFileName = substr($filename, 0, strlen($filename) - strlen($fileExtension ) - 1);
                $db->query("SELECT * FROM `compchecker` where `filename` = '" . $db->escape_string($shortenedFileName) . "' AND `approved` != 0");
                if ($db->rowCount() != 0)
                {
                    // The shortened filename is restricted in compchecker
                    logger("The file '$filename' is listed as unapproved in the compchecker table.", LEVEL_INFORMATION);
                    return true;
                }
            }
        }
        return false;
    }
}

function logger($message, $loglevel=1)
{
	// Write to the logfile.
	
	// Only create a log entry if the log level of this message is less than
	// or equal to the application's Log Level setting.
	if ($loglevel <= LOG_LEVEL)
	{
		$handle01 = fopen(GLOBAL_LOG_DIRECTORY . LOG_FILE_NAME, "a");
		fwrite($handle01, time() . ": " . $message . " \n");
		fclose($handle01);
		$message='';
		return;
	}
	else
	{
		return;
	}
}

function mayDownload($filename)
{
	/*
	 * Checks the filename against the 'restrictedProcessFiles' table to determine
	 * if the supplied filename should be downloaded from the vendor's server.
	 * At the time this function was created (2008-10-15), this applies only to 
	 * files retrieved by the Linkshare monitor.
	 */
	
	return genericFilePermissionChecker($filename, 'do_not_download');
}

function mayStore($filename)
{
	/*
	 * Checks if storage has been restricted for this filename. Normally a backup copy
	 * of all feedfiles is created. A copy will not be created if this file is restricted
	 * for storage.
	 */
	
	return genericFilePermissionChecker($filename, 'do_not_store');
}

function mayUploadToDatabase($filename)
{
	/*
	 * Checks if this file is allowed to be loaded into the database.
	 */
	
	return genericFilePermissionChecker($filename, 'do_not_upload_to_database');
}

function mustDeleteIfExists($filename)
{
	/*
	 * If a file is found with the 'delete_if_exists' flag, it should be 
	 * deleted. This will apply to the Commission Junction and Performics feed
	 * files, which are uploaded to the server by the feed aggregator.
	 */
	return !genericFilePermissionChecker($filename, 'delete_if_exists');	// We reverse the generic response
																			// because this request is reversed
																			// compared to the others.
}

function setProcessStatusDefines()
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	// Retrieve the process status ids from the database and create defines for them
	
	global $db;
	
	$db->query("SELECT `id`, `label` FROM `activeProcesses_status`");
	
	foreach ($db->objects() as $status)
	{
		define('PROCESS_STATUS_' . strtoupper($status->label), $status->id);
	}
}

function shutdown($note, $keepPID=false)
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	if(false===$keepPID)
	{
		logger("Shutdown: We have been instructed to remove the PID file.", LEVEL_DEBUG);
		
		if(defined('PID_FILE'))
		{
			logger("Shutdown: PID_FILE is defined.", LEVEL_DEBUG);
			if (file_exists(GLOBAL_PID_DIRECTORY . PID_FILE))
			{
				logger("Deleting PID file.", LEVEL_FILE_OPERATION);
				unlink(GLOBAL_PID_DIRECTORY . PID_FILE);
			}
			else
			{
				logger("Shutdown: PID_FILE ('" . GLOBAL_PID_DIRECTORY . PID_FILE . "') does not exist.", LEVEL_DEBUG);
			}
		}
		else
		{
			logger("Shutdown: PID_FILE is not defined.", LEVEL_DEBUG);
		}
	}
	else
	{
		logger("Shutdown: Keeping PID file.", LEVEL_DEBUG);
	}
	//email_crit_error($note);
	statusReport(PROCESS_NAME, 'stop', $note);
	logger($note, LEVEL_CRITICAL);
	echo "$note\n";
	exit(-1);
}

function statusReport($processName, $type, $note='')
{
    // Insert a status report into the status report table
    
    global $db;
    
    $statusTable = 'status_reports';

    // Sanitize the input
    $processName = addslashes($processName);
    $type = addslashes($type);
    $note = addslashes($note);

    $db->query("INSERT INTO `$statusTable` (`processName`, `type`, `note`, `timestamp`) VALUES ('$processName', '$type', '$note', UNIX_TIMESTAMP())");
}

function verifyLogFile()
{
	// Verify that the log file exists and is writable
	if (0 !== ($checkLogFileResult = checkLogFile(GLOBAL_LOG_DIRECTORY . LOG_FILE_NAME)))
	{
		switch ($checkLogFileResult)
		{
			case -1:
				$message = "The log file '" . LOG_FILE_NAME . "' does not exist and could not be created.";
				break;
			case -2:
				$message = "The log file '" . LOG_FILE_NAME . "' is not writable.";
				break;
			default:
				$message = "An unknown error was encountered while checking the log file '" . LOG_FILE_NAME . "'.";
				break;
		}
		shutdown($message, true);
	}
}

?>
