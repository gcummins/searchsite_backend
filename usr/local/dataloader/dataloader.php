<?php

// Custom Built By
// BATEA Business Solutions, Inc.
// P.O. Box 1022
// Norman OK, 73070
// support@bateainc.com
// Copyright (C) 2005 - 2010,  BATEA Business Solutions, Inc.
// -------------------------------------------------------------
// | This file should not be edited, deleted, or moved without |
// | checking with BATEA Business Solutions, Inc. If this code |
// | is altered it could cause the application to not run      |
// | correctly which could result in a service interuption.    |
// |                                                           |
// | File Created: Aug 2006                                    |
// | By: mslack, rvoelker, gcummins                            |
// -------------------------------------------------------------

/*
	Notes.
	This script has to share a group with the mysql daemon data dir and be able to read, write, and delete
	a file in that location.  This is for the proper operation of processfeed().

	This script should also have its own UID and username with the 'shell' field in /etc/passwd pointing
	to /sbin/nologin or something similar.

	Add query at the end of each datapro() run:
	 update compchecker set reindex=0 where df_lines=0

	Modification 2008-03-28
	Create temporary tables using this script.  IE: tmp_al_01_1, tmp_al_02_1, etc...
	The script feedFieldMap.php will copy the tmp tables into a standard format in a permanent table.
	Added control table tmp_compchecker. This is used for the tmp tables mentioned above.

	Modification 2008-08-31
	Previously this script checked only for the existance of a PID file to determine whether to run.
	If the PID file was found, the script immediately exited. This caused problems in the event of a 
	server crash, because a stale PID file had to be manually removed, and then the script had to be
	restarted. An additional check was added to determine if the PID file referenced a running process.
	If not, we delete the stale PID file and run this script.

	Modification 20008-09-25
	Removed MD5 checksums of files in favor of file size checks to determine if a file upload has
	completed. MD5 sums are slow and resource-intensive.

	Modification 2008-10-10
	Moved the feed processing functions (previously named datapro1(), datapro2() and datapro3() ) to 
	separate files. This allows more modularization, and this script needs only to include those functions
	when required.

	Modification 2008-10-13
	Log levels have been added. Each potential log entry is tagged with a level. The administrator can
	set the log level to determine how verbose the log file should be. For debugging, the level should be set
	to 10. For normal operation, four or five will be sufficient.

*/

define('LOG_FILE_NAME', 'dataloader.log');
define('PROCESS_NAME', 'dataloader');

include "include/functions.php";
include "config.inc.php";

define('PID_FILE', DATALOADER_PID_FILE);

// Filesitter Process Levels
$arrProcesses = array(
	0 => 'upload',		// File exists in the upload directory. Wait for the upload to complete.
	1 => 'copy',		// File upload is complete, move it to the working directory and decompress if needed.
	2 => 'identify',	// Determine the feed source from which this file originated.
	3 => 'switch',		// Hand the file off to the appropriate feed handler.
	4 => 'load',		// Load the file contents into the database.
	5 => 'format',		// CJ Only: Split the file into multiple vendors.
	6 => 'complete',	// Processing has completed for this file.
	20 => 'invalid'		// Incomplete or invalid file. Move to the 'unrecognized' directory.
	);

declare(ticks=1);			// Global callback for the signal handler.
require_once('dataloader.inc');

// Speed Check class
include "include/speedcheck.php";

// Verify that the log file exists and is writable
verifyLogFile();

// Detach from terminal and fork as a daemon.
$pid = pcntl_fork();
if ($pid == -1)
{
	exit("Error:  Could not fork daemon.\n");
}
else if ($pid)
{
	exit();	// Child forked.
}

// Detatch from the controlling terminal
if (!posix_setsid()) exit("Error: Could not enter daemon mode. \n");

// Include the MySQL class file, which also opens a connection to the database server.
$mysqlClassFilename = GLOBAL_APP_PATH . "include/db.class.php";
if (file_exists($mysqlClassFilename))
{
	include_once "$mysqlClassFilename";
}
else
{
	die("Unable to access the MySQL connection script: '$mysqlClassFilename'. Please correct the location of this script and restart dataloader.\n");
}

// Load up system config file.
$sys_cfg = array();
$sys_cfg = setConfig();
checkEnvironment();

// Renice to something mellow.
pcntl_setpriority($dNice,posix_getpid(),PRIO_PROCESS);

// Check for existing PID or create a PID file.
$pid = getmypid();

$pidFileHandle = null;	// This variable will be used via 'global' in the createPIDFile function.
						// This is necesary because the flock used in the function is broken when the
						// file handle goes out of scope. Using a global variable ensures that the 
						// file handle will not go out of scope when the function returns.
createPIDFile(GLOBAL_PID_DIRECTORY . DATALOADER_PID_FILE, $pid);

$rightNow=time();

// Update the dl_daemon table with the current PID
$db->query("UPDATE `$dbDatabase`.`dl_daemon` SET pid = '$pid', start = $rightNow WHERE `id`=1");

// Notify other daemons that this script is running
setProcessStatusDefines();
changeProcessStatus(PROCESS_NAME, PROCESS_STATUS_ACTIVE, $pid);

$old_error_handler = set_error_handler("dataloaderErrorHandler");

// Set up the signal handler
pcntl_signal(SIGTERM, "sig_handler");
pcntl_signal(SIGHUP, "sig_handler");
pcntl_signal(SIGINT, "sig_handler");
pcntl_signal(SIGUSR1, "sig_handler");
pcntl_signal(SIGUSR2, "sig_handler");

logger("Dataloader started at " . strftime("%c"), LEVEL_STATUS);
statusReport(PROCESS_NAME, 'start');
umask($dl_umask);

$chkFilStat = 0;
$holdOff=0;

cleanupAfterCrash();

while(true)
{
	if (isset($db))
	{
		unset($db);				// Disconnect from the database before loopHold()
	}
	
	loopHold();					// Check to see if a SIGUSRx came in.  (suspend/resume operations)
	// Connect to the database
	$db = new DatabaseConnection(DATABASE_HOST, DATABASE_USER, DATABASE_PASSWORD, DATABASE_NAME);
	if (false === mayThisScriptRun())
	{
		continue; 				// Skip to the next iteration of this loop
	}
	scheduleCheck();			// This function will stay on a sleep loop until we are scheduled to run.
	checkForNewFile();			// See if new files are coming in.  F/S process 0
	checkUploadComplete();		// Make sure files are done uploading. F/S process 1
	moveAndDecompressFiles();	// Move files to storage and work dir to unzip if needed.  F/S process 2
	identifyFiles();			// Determine which affiliate the file came from.  F/S process 3
	loadFeedIntoDatabase();		// Transfer delimited files to db tables.
	
	if (rand(1,100) == 100)		// Run once per every 100 loops (on average)
	{
	    removeBlockedCatalogs();    // If any catalogs are listed as 'blocked' by an administrator, this function will remove them.
		cleanFilesitter();
	}
}

/////////////////////////////////////////////////////////////////////////////////
// Functions.
//
function checkEnvironment()
{
	// Check for proper working dirs and create them if needed.
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $storage, $work, $upload, $unrec, $daemonFiles, $appath;
	clearstatcache();

	$arrRequiredDirectories = array(
				$appath			=> 'Application',
				$daemonFiles	=> 'PID',
				$storage		=> 'Storage',
				$work			=> 'Work',
				$upload			=> 'Upload',
				$unrec			=> 'Unrecognized Files'
				);
				
	// Cycle through each directory. Check for existance, and try to create the directory if it does not exist.
	foreach ($arrRequiredDirectories as $directory=>$title)
	{
		if (!is_dir(rtrim($directory)))
		{
			// Attempt to create it
			logger("The $title directory '$directory' does not exist. Attempting to create...", LEVEL_FILE_OPERATION);
			if (false === mkdir(rtrim($directory)))
			{
				shutdown("$title Directory '$directory' could not be created.");
			}
			else
			{
				logger("The $title directory '$directory' has been created.", LEVEL_STATUS);
			}
		}
		else
		{
			logger("The $title directory '$directory' exists.");
		}
	}
}

function checkForNewFile()
{
	// Initialize the file by adding an entry to the filesitter table. The script will then wait until
	// the upload (Process 0) is complete before copying the file to a working directory (Process 1).
	
	// Check to see if there is anything new in the upload directory. New entries in filesitter
	// are created here. Filesitter process is 0 ("upload").
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $upload, $dbDatabase, $db;
	
	$filelist=array();
	$filelist=getFileList($upload);
	if(empty($filelist))
	{
		return FALSE;
	}
	
	logger("Preparing to cycle through each file.", LEVEL_MINUTIA);
	foreach($filelist as $filename)
	{
		// No longer used, for speed reasons. --gcummins 081026
		//logger("Calculating MD5 hash for: ".$filename);
		//$md5Hash=md5_file($upload.$filename);
		//logger("Done calculating MD5 hash for: ".$filename);
		
		// Only process the file if it is not restricted for upload
		if (mayUploadToDatabase($filename))
		{
			// Get the file size
			$fileSize = getFileSize($upload.$filename);
			
			// Get the file modification time
			$filemTime = filemtime($upload.$filename);
			if (FALSE == $filemTime)
			{
			    $filemTime = time()-86400;
			}
			
			logger("Getting file information from filesitter for '$filename.'", LEVEL_INFORMATION);
			$selectQuery = "SELECT `id`, `original` FROM `".$dbDatabase."`.`filesitter` WHERE `original`='" . addslashes($filename) . "' ";
			logger("Query is: $selectQuery", LEVEL_DEBUG);
			$db->query($selectQuery);
	
			if ($db->rowCount() == 0)
			{
				$rightNow=time();
				logger("Adding file '$filename' to filesitter.", LEVEL_DATABASE_OPERATION);
				
				// Removed the MD5 hash in favor of checking the filesize. --gcummins 081026
				$insertQuery = "INSERT INTO `".$dbDatabase."`.`filesitter`"
					. " (`original`, `file`, `time`, `filestart`, `size`, `modifiedtime`, `process`)"
					. " VALUES ('" . addslashes($filename) . "', '" . addslashes($filename) . "', $rightNow, $rightNow, '$fileSize', $filemTime, 0);";
				$db->query($insertQuery);
				logger("Done adding file '$filename' to filesitter.", LEVEL_INFORMATION);
			}
		}
		else
		{
			logger("The file '$filename' may not be uploaded according to administrator restriction.", LEVEL_INFORMATION);
			if (mustDeleteIfExists($filename))
			{
			    logger("Attempting to delete the file.", LEVEL_FILE_OPERATION);
			    $unlinkResult = unlink($upload.$filename);
			    if ($unlinkResult)
			    {
			        logger("File was deleted successfully.", LEVEL_INFORMATION);
			    }
			    else
			    {
			        logger("The file could not be deleted.", LEVEL_DATA_WARNING);
			    }
			}
		}
	}
	return true;
}

function checkUploadComplete()
{
	// Check if the upload (Process 0) has completed.
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $upload, $ul_wait_time, $dbDatabase, $db;

	$filelist=array();
	$fsList=array();

	$filelist=getFileList($upload);
	if(empty($filelist))
	{
		return false;		// No files in u/l dir.
	}
	
	// Find the number of files in the directory
	$numberOfFiles = count($filelist);
	
	// Create a grammatically correct string based on the number of files
	if ($numberOfFiles == 1)
	{
		$fileCountString = "1 file exists";
	}
	else
	{
		$fileCountString = "$numberOfFiles files exist";
	}
	
	logger("$fileCountString in the upload directory.", LEVEL_INFORMATION);
	foreach($filelist as $file)
	{
		if (mayUploadToDatabase($file))
		{
			// Removed md5 check in favor of a faster file size check. --gcummins 081026
			logger("Retrieving '$file' information from filesitter", LEVEL_INFORMATION);
			$selectQuery = "SELECT * FROM `$dbDatabase`.`filesitter` where `original`='" . addslashes($file) . "' LIMIT 1";
			$db->query($selectQuery);
			
			$fsFile = $db->firstArray();
	
			
			if ($db->rowCount() == 0)
			{
				logger("The file '$file' exists in the upload directory, but no entry exists in the filesitter table.", LEVEL_DATA_WARNING);
				
				// Skip to the next file in the list
				continue;
			}

			// Get the file size
			$thisFileSize=getFileSize($upload.$file);
			
			// Get the file modification time
			$filemTime = filemtime($upload.$file);
			if ($filemTime == false)
			{
			    $filemTime = time();
			}
			
			// Check the file size and mtime for comparison
			if (($fsFile['filestart']+$ul_wait_time)<time() && $fsFile['size']==$thisFileSize)
			{
				// Time exceeded and hashes match. The file appears to have been fully
				// uploaded to our server.
				logger("Time exceeded and file sizes match. Filesitter process updates to \"" . getProcessName(1) . "\" on file: ".$file, LEVEL_STATUS);
	
				logger("Updating filesitter with filesize $thisFileSize for: " . $upload.$file, LEVEL_DATABASE_OPERATION);
				$rightNow=time();
				$updateQuery = "UPDATE `$dbDatabase`.`filesitter` SET"
					. " `process`=1,"
					. " `size`='$thisFileSize',"
					. " `modifiedtime`=$filemTime,"
					. " `fileend`=$rightNow"
					. " WHERE `id`=".$fsFile['id'];
				$db->query($updateQuery);
			}
			
			else if(($fsFile['filestart']+$ul_wait_time)<time() && $fsFile['size']!=$thisFileSize)
			{
				// Time not exceeded and hashes don't match. The file is still in the
				// process of being uploaded to our server.
				logger("Time not exceeded and file sizes do not match for file: ".$file."(" . $fsFile['size'] . ", $thisFileSize). Filesitter process stays at \"" . getProcessName(0) . "\".", LEVEL_STATUS);
				
				// Get an MD5 sum of the file
				//$md5Hash=md5_file($upload.$fil);
				logger("Updating filesitter with filesize ".$thisFileSize." for: ".$upload.$file, LEVEL_DATABASE_OPERATION);
				$rightNow=time();
				$updateQuery = "UPDATE `".$dbDatabase."`.`filesitter` SET"
					. " `filestart`=$rightNow,"
					. " `size`='$thisFileSize',"
					. " `modifiedtime`=$filemTime"
					. " WHERE `id`='".$fsFile['id']."' LIMIT 1;";
				$db->query($updateQuery);
			}
			// TODO: May need to add a check for incoming feeds that seem to hang forever.
			// Should we add an arbitrary time limit or file size?
		}
		else
		{
			logger("File '$file' ignored because of administrative restriction.", LEVEL_INFORMATION);
		}
	}
	return true;
}

function cleanFilesitter()
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	// Determine if there are any entries in filesitter without matching files in the directories.
	// If so, delete the entry from filesitter.
	
	global $db, $work, $upload;
	
	$removedFilesCounter = 0;
	
	// Get a list of files in filesitter
	$db->query("SELECT `id`, `original`, `file` FROM `filesitter`");
	
	if ($db->rowCount() == 0)
	{
		return; // Nothing to do
	}
	
	foreach ($db->objects() as $file)
	{		
		if (file_exists(stripslashes($upload.$file->original)))
		{
			continue;
		}
		
		if (file_exists(stripslashes($work.$file->original)))
		{
			continue;
		}
		
	    if (file_exists(stripslashes($work.$file->file)))
		{
			continue;
		}
		
		if (substr(stripslashes($file->original), -3) == '.gz' && file_exists($work . substr(stripslashes($file->original), 0, -3)))
		{
			continue;
		}
		
		// By reaching this point we have determined that the file does not exist. It will
		// be removed from the filesitter table.
		$db->query("DELETE FROM `filesitter` WHERE `id`=" . $file->id);
		$removedFilesCounter++;
	}
	
	logger("Orphaned records removed: $removedFilesCounter", LEVEL_INFORMATION);
}

function cleanupAfterCrash()
{
	// Do some cleanup in case we crashed last time
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $db, $work;
	
	logger("Performing cleanup.", LEVEL_STATUS);
	
	// 1. Remove any SQL outfiles that exist
	foreach (array(DATALOADER_COMMISSIONJUNCTION_OUTFILE, DATALOADER_PERFORMICS_OUTFILE, DATALOADER_LINKSHARE_OUTFILE) as $outfile)
	{
		if (file_exists($outfile))
		{
			logger("The SQL file '$outfile' exists. Attempting to delete...", LEVEL_INFORMATION);
			if (is_writable($outfile))
			{
				if (@unlink($outfile))
				{
					logger("'$outfile' has been deleted.", LEVEL_FILE_OPERATION);
				}
				else
				{
					logger("Failed to delete '$outfile'.", LEVEL_DATA_WARNING);
				}
			}
			else
			{
				logger("The file'$outfile' cannot be deleted.", LEVEL_DATA_WARNING);
			}
		}
	}
	// Check each of the files in the $work directory.
	// If a matching filename exists in filesitter, reset the process to '2'.
	// If no matching filename exists, delete the file.
	// Finally, delete all entries from filesitter where process is less than 2
	$db->query("SELECT `file`, `process` FROM `filesitter`");
	$arrFilesInFilesitter = array();
	foreach ($db->objects() as $fileInFilesitter)
	{
		$arrFilesInFilesitter[] = $fileInFilesitter;
	}
	
	if ($filesInWorkDirectory = getFileList($work))
	{
		foreach ($filesInWorkDirectory as $fileInWorkDirectory)
		{
			if (in_array($fileInWorkDirectory, $arrFilesInFilesitter))
			{
				// The file exists in filesitter
				logger("Updating filesitter record for \"$fileInWorkDirectory\" to process 2.", LEVEL_DATABASE_OPERATION);
				$db->query("UPDATE `filesitter` SET `process`=2 WHERE `file`='" . addslashes($fileInWorkDirectory) . "' LIMIT 1;");
			}
			else
			{
				logger("There is no entry in filesitter for '$fileInWorkDirectory. Deleting...", LEVEL_FILE_OPERATION);
				if (!unlink($work.$fileInWorkDirectory))
				{
					logger("Failed to delete file {$work}{$fileInWorkDirectory}.", LEVEL_DATA_WARNING);
				}
			}
		}
	}
	
	logger("Deleting all records from filesitter where the process is not '2.'");
	$db->query("DELETE FROM `filesitter` WHERE `process` != 2;");
	
	unset($arrFilesInFilesitter, $fileInWorkDirectory, $fileInFilesitter);
}

function dataloaderErrorHandler($errno, $errstr, $errfile, $errline)
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	switch ($errno)
	{
		case E_ALL:
			$error_note = "ERROR: $errno $errstr\n";
			$error_note .= "  Fatal error in line $errline of file $errfile";
			$error_note .= "Aborting...\n";
			logger($error_note, LEVEL_CRITICAL);
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

function email_crit_error($errmsg)
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	mail(ADMIN_EMAIL, 'Dataloader Critical Error', $errmsg);
	return;
}

function getFileList($path)
{
	// Get a plain file listing.
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	clearstatcache();
	$dir_list=array();
	$file='';
	if ($handle = opendir($path))
	{
		while (false !== ($file = readdir($handle)))
		{
			// if ($file != "." && $file != "..") // Commented in favor of the next line
			if ('.' != substr($file, 0, 1)) // Make sure the filename does not start with a '.'
			{
				if(is_file($path.$file)) array_unshift($dir_list, $file);
			}
		}
		closedir($handle);
	}
	if(count($dir_list>0))
	{
		return $dir_list;
	}
	else
	{
		return false;
	}
}

function getFileSize($fil)
{
	// This function is needed because filesize() may fail on some systems where 
	// the filesize is larger than 2GB. The stat command should always work.
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	// Note that the switches for 'stat' differ between Linux and FreeBSD
	global $os, $statPath;
	$os=trim($os);
	switch($os)	{
	case "linux":
		$size=(string) exec($statPath.'stat -c "%s" '. escapeshellarg($fil));
		return $size;
		break;
	case "bsd":
		$size=(string) exec($statPath.'stat -f "%z" '. escapeshellarg($fil));
		return $size;
		break;
	default:
		clearstatcache();
		$size = filesize($fil);
		return $size;
		break;
	}
}

function getProcessName($processId)
{
	// Return the process name corresponding with the ID provided
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $arrProcesses;
	
	if (array_key_exists($processId, $arrProcesses))
	{
		return $arrProcesses[$processId];
	}
	else
	{
		return "unknown";
	}
}

function getSchedule()
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $startRunFlag, $endRunFlag, $db;
	
	$db->query("SELECT * FROM `dl_daemon` WHERE `id`=1");
	
	if ($db->rowCount())
	{
		$sch_Cfg = $db->firstArray();
		
		$contRunFlag=$sch_Cfg['cont'];
		if($sch_Cfg['schstarthour'] > 0 || $sch_Cfg['schstartmin'] > 0)
		{
			$startRunFlag = mktime($sch_Cfg['schstarthour'], $sch_Cfg['schstartmin'], 0);
		}
		else
		{
			$contRunFlag = 1;
		}
		if($sch_Cfg['schendhour'] > 0 || $sch_Cfg['schendmin'] > 0)
		{
			$endRunFlag = mktime($sch_Cfg['schendhour'], $sch_Cfg['schendmin'],0);
		}
		else
		{
			$contRunFlag = 1;
		}
	}
	if($startRunFlag > $endRunFlag)
	{
		$endRunFlag += 86400;		// Crossed day boundary.
	}
	return $contRunFlag;
}

function gzipTest($file)
{
	// Determine if a file is gzipped
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	// Test the filename extension first.
	global $head_exec;

	/*
	 * I removed the following test because some files from Google/Performics are now arriving compressed
	 * without an appropriate extension. We now test for an appropriate extension in moveAndDecompressFiles(). -- gcummins
	 */
	// If the file is not named with an appropriate extension, we know it is not gzipped
	//if(!preg_match('/\.gz$/i',$file))
	//{
	//	return false;
	//}

	// Check file for gzip signature - first two bytes of any gzip file
	// are 31 and 139 (byte 0 and byte 1).
	$handle=fopen($file, "r");
	$loByte=fread($handle,1);		// dec 31
	$hiByte=fread($handle,1);		// dec 139
	fclose($handle);

	if(($loByte==chr(31)) && ($hiByte==chr(139)))
	{
		return TRUE;	// This is a gzipped file.
	}
	return FALSE;	// This file is not gzipped.
}

function identifyFiles()
{
	// File identification (Process 2).
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $work, $dbDatabase, $arrPerformicsMD5Sums, $db;

	$query = "SELECT * FROM `".$dbDatabase."`.`filesitter` WHERE `process`=2";
	$db->query($query);
	
	if($db->rowCount() == 0)
	{
		logger("No files were found in filesitter with process=2", LEVEL_INFORMATION);
		return false;
	}
	else
	{
		logger($db->rowCount() . " files were found with process=2", LEVEL_INFORMATION);
	}
	
	foreach ($db->arrays() as $fsQueue)
	{
		logger("Opening file '" . $fsQueue['file'] . "' to retrieve identity information.", LEVEL_FILE_OPERATION);
		if (file_exists(stripslashes($work.$fsQueue['file'])))
		{
			if(false === ($hndl=fopen(stripslashes($work.$fsQueue['file']), "r" )))
			{
				shutdown("Error while opening file in " . __FUNCTION__ . ": ".stripslashes($work.$fsQueue['file']));
			}
		}
		else
		{
			logger("The file '" . stripslashes($work.$fsQueue['file']) . "' has been deleted. Removing from filesitter.", LEVEL_DATA_WARNING);
			$db->query("DELETE FROM `$dbDatabase`.`filesitter` WHERE `id`=" . $fsQueue['id'] . " LIMIT 1");
			
			// Skip to the next file
			continue;
		}
		
		// Retrieve the first line of data
		$firstline=fgets($hndl);
		
		// Close the file
		fclose($hndl);
		if (strstr($fsQueue['file'],"464063_524")) // Old-style Commission Junction filenames start with this string 
		{
			// COMMISSION JUNCTION
			logger("File: ".$fsQueue['file']." is in Commission Junction format, revision 1.  Filesitter process goes to \"" . getProcessname(3) . "\".", LEVEL_STATUS);
			$updateQuery = "UPDATE `$dbDatabase`.`filesitter` SET `feedfile`=1, `feedRevision`=1, `process`=3 WHERE `id` = ".$fsQueue['id'];
			$db->query($updateQuery);
			break;
		}
	    else if(strstr($fsQueue['file'], "_276372_mp.txt"))
		{
			// LINKSHARE
			logger("File: ".$fsQueue['file']." is from LinkShare.  Filesitter process goes to \"" . getProcessName(3) . "\".", LEVEL_STATUS);
			$updateQuery = "UPDATE `".$dbDatabase."`.`filesitter` SET `feedfile`=2, `feedRevision`=1, `process`=3 WHERE `id` = ".$fsQueue['id'];
			$db->query($updateQuery);
			break;
		}
		else if (checkCommissionJunctionFileNames($fsQueue['file']))
        {
            // COMMISSION JUNCTION, version May 2010
            logger("File: " . $fsQueue['file'] . " is in Commission Junction format, revision 2. Filesitter process goes to \"" . getProcessname(3) . "\".", LEVEL_STATUS);
            $updateQuery = "UPDATE `$dbDatabase`.`filesitter` SET `feedfile`=1, `feedRevision`=2, `process`=3 WHERE `id` = ".$fsQueue['id'];
            $db->query($updateQuery);
            break;
        }
		else if (in_array(md5($firstline), $arrPerformicsMD5Sums))
		{
			// PERFORMICS
			logger("File: ".$fsQueue['file']." is from Performics.  Filesitter process goes to \"" . getProcessName(3) . "\".", LEVEL_STATUS);
			$updateQuery = "UPDATE `".$dbDatabase."`.`filesitter` SET `feedfile`=3, `feedRevision`=1, `process`=3 WHERE `id` = ".$fsQueue['id'];
			$db->query($updateQuery);
			break;
		}
		else
		{
			logger("File: ".$fsQueue['file']." is unidentified. Filesitter process goes to \"" . getProcessName(3) . "\".", LEVEL_DATA_WARNING);
			$updateQuery = "UPDATE `$dbDatabase`.`filesitter` SET `feedfile`=999, `process`=3 WHERE `id` = ".$fsQueue['id'];
			$db->query($updateQuery);
			
			// Notify the administrator that an invalid file was found
			$subject = "Invalid Feed File";
			$message = "An invalid feed file was found in the uploads directory:\n\n\t" . $fsQueue['file'] . "\n\nThe file has been moved to the 'unrecognized_files' directory.";
			mail(ADMIN_EMAIL, $subject, $message);
		}
	}
	return true;
}

function checkCommissionJunctionFileNames($filename)
{
    /*
     * This function compares the provided filename to a list of approved Commission Junction filenames. If a
     * match is found, we will return TRUE, otherwise FALSE.
     */
        
    // This is the standard format. If all of the publishers followed Commission Junction rules, all CJ feed files would 
    // match this test.
    if (stristr($filename, "product_catalog"))
    {
        return true;
    }
    
    if (stristr($filename, "product_feed"))
    {
        return true;
    }
    
    if (stristr($filename, "_products.txt"))
    {
        return true;
    }
    
    if (stristr($filename, "feed.txt"))
    {
        return true;
    }

    if (stristr($filename, "feeds.txt"))
    {
        return true;
    }
    if (stristr($filename, "_catalog.txt"))
    {
        return true;
    }
    
    // Check against a database table containing unique filenames
    global $db;
    $db->query("SELECT * FROM `filename_identification` WHERE `filename` LIKE '" . $db->escape_string($filename) . "%' AND `feed_id`=1 LIMIT 1;");
    if ($db->rowCount())
    {
        // A match was found in the database
        return true;
    }
    
    // If we reach this point, no matches were found
    return false;
}

function loadFeedIntoDatabase()
{
	// Feed switch (process 3).
	// Call the appropriate function based on the feed type.	
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $dbDatabase, $db;

	$query = "SELECT `feedfile`, `process` FROM `$dbDatabase`.`filesitter` WHERE `feedfile`>0 AND `process`=3";
	$db->query($query);

	$loadDbNumFiles = $db->rowCount();
	if($loadDbNumFiles == 0)
	{
		logger("There are no filesitter entries with feedfile>0 and process=3", LEVEL_INFORMATION);
		return;
	}
	elseif ($loadDbNumFiles == 1)
	{
		logger("There is $loadDbNumFiles filesitter entry with feedfile>0 and process=3,", LEVEL_INFORMATION);
	}
	else
	{
		logger("There are $loadDbNumFiles filesitter entries with feedfile>0 and process=3,", LEVEL_INFORMATION);
	}
	
	foreach ($db->arrays() as $fsQueue)
	{
		switch ($fsQueue['feedfile'] )
		{
			case 1:
				logger("Calling processfeed() for a Commission Junction file.", LEVEL_INFORMATION);
				processfeed('commissionjunction');
				break;
			case 2:
				logger("Calling processfeed() for a Linkshare file.", LEVEL_INFORMATION);
				processfeed('linkshare');
				break;
			case 3:
				logger("Calling processfeed() for a Performics file.", LEVEL_INFORMATION);
				processfeed('performics');
				break;
			case 999:
				logger("Nomatch file (\$fsQueue['feedfile'] = " . $fsQueue['feedfile'] . "). Call nomatch().", LEVEL_DATA_WARNING);
				nomatch();
				break;
			default:
				break;
		}
	}
	
    if ($loadDbNumFiles)
    {
        $noteString = ($loadDbNumFiles > 1) ? "Processed $loadDbNumFiles files" : "Processed $loadDbNumFiles file";
	    statusReport(PROCESS_NAME, 'info', $noteString);
    }
	return true;
}

function loopHold()
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);

	global $holdOff;

	while(true)
	{
		sleep(DATALOADER_SLEEP_TIME);
		
		if(!$holdOff)
		{
			// $holdOff will only be true if we have received SIGUSR1. This script will go into a holding
			// state until we receive SIGUSR2. See sig_handler() for more information.
			break;
		}
	}
}

function mayThisScriptRun()
{
	// We will check two things in this script.
	// First, is this script blocked from running via an entry in activeProcesses?
	// Second, is the feedFieldMap process currently running?
	
	// If either condition is true, this script may not run and we will return false.
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $db;
	
	// First check: Is this process set to 'blocked'?
	$db->query("SELECT `status` FROM `activeProcesses` WHERE `processName` = '" . PROCESS_NAME . "' LIMIT 1;");
	if ($db->rowCount())
	{
		$row = $db->firstObject();
		if ($row->status == PROCESS_STATUS_BLOCKED)
		{
			logger("The dataloader process is blocked from running via an entry in activeProcesses.", LEVEL_STATUS);
			return false;
		}
	}
	
	// Second check: Is the feedFieldMap process running?
	$db->query("SELECT `pid` FROM `activeProcesses` WHERE `processName`='" . FEEDMAPPING_PROCESS_NAME . "' LIMIT 1;");
	if ($db->rowCount())
	{
		logger("The '" . FEEDMAPPING_PROCESS_NAME . "' process is currently running (according to activeProcesses), so this script will sleep.", LEVEL_STATUS);
		return false;
	}
	
	return true;
}

function moveAndDecompressFiles()
{
	// Copy the file to a working directory (Process 1).
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $upload, $storage, $work, $unrec, $gunzip_exec, $dbDatabase, $db;
	
	$fsList=array();
	$ret_var="";
	
	logger("Getting file information from filesitter where process=1", LEVEL_INFORMATION);
	$selectQuery = "SELECT * FROM `$dbDatabase`.`filesitter` WHERE `process`=1";
	$db->query($selectQuery);
	
	if ($db->rowCount() == 0)
	{
		logger("No files with process=1 were found.", LEVEL_INFORMATION);
		return false;
	}
	logger("Processing files...", LEVEL_INFORMATION);
	foreach ($db->arrays() as $fsList)
	{
	    $workingFileIsCompressed = false;
	    $workingFileHasAppropriateExtension = true;
	    $workingFileCompressionType = "none";
	    
		if (DATALOADER_SAVE_COPIES_OF_FEEDS)
		{
			if ((DATALOADER_MAX_FILESIZE_TO_COPY == 0 || $fsList['size'] <= DATALOADER_MAX_FILESIZE_TO_COPY )
				 && mayStore(stripslashes($fsList['original'])))
			{
				// Determine whether to copy the file via PHP's copy() or exec() + Unix cp
				// PHP copy() seems to have trouble with files larger than 512 MB
				// For safety, we will use an exec() call for any file larger than 400MB
				if ($fsList['size'] > 419430400)
				{
					// Copy the file to the backup location using exec and Unix cp
					logger("Attempting an exec() call to copy file to storage location '$storage'.", LEVEL_FILE_OPERATION);
					$lastline = exec(GLOBAL_PATH_TO_COPY_COMMAND . " " . escapeshellarg(stripslashes($upload.$fsList['original'])) . " " . escapeshellarg(stripslashes($storage.$fsList['original'])), $arrOutput, $returnCode);
					if ($returnCode != 0)
					{
						logger("Failed to copy " . stripslashes($upload.$fsList['original']) . " (error code '$returnCode'). The last line of output was: \"$lastline\"", LEVEL_FILE_OPERATION);
						$deleteQuery = "DELETE FROM `$dbDatabase`.`filesitter` WHERE `id`='".$fsList['id']."'";
						$db->query($deleteQuery);
					}
					else
					{
						logger("Copied ".$upload.$fsList['original']." to ".$storage.$fsList['original'], LEVEL_INFORMATION);
					}
				}
				else
				{
					// Copy the file to the backup location
					logger("Attempting to copy " . $upload.$fsList['original'] . " to storage directory '$storage'.", LEVEL_FILE_OPERATION);
					if(false === copy(stripslashes($upload.$fsList['original']), stripslashes($storage.$fsList['original'])))
					{
						logger("Could not copy file to ".stripslashes($storage.$fsList['original']) . ". Removing from filesitter.", LEVEL_DATA_WARNING);
						$deleteQuery = "DELETE FROM `$dbDatabase`.`filesitter` WHERE `id`='".$fsList['id']."'";
						$db->query($deleteQuery);
					}
					else
					{
						logger("Copied ".$upload.$fsList['original']." to ".$storage.$fsList['original'], LEVEL_INFORMATION);
					}
				}
			}
			else
			{
				logger("File is too large to copy.", LEVEL_STATUS);
			}
		}
		else
		{
			logger("Will not create copies of feed files because of a restriction in the configuration settings.", LEVEL_INFORMATION);
		}
			
		// Determine if the file is compressed. First, check for ZIP compression.
		logger("Testing the file for ZIP compression.", LEVEL_INFORMATION);
		$ziphandle = zip_open($upload.$fsList['original']);
		if (!is_resource($ziphandle))
		{
		    // Determine if GZIP compression was used.
		    logger("Testing for gzip file using gzip signature.", LEVEL_INFORMATION);
    		if(gzipTest(stripslashes($upload.$fsList['original'])))
    		{
    		    $workingFileIsCompressed = true;
    		    
                // Determine if the file has an appropriate extension
    		    if (strtolower(substr($upload.$fsList['original'], -3)) != '.gz' && strtolower(substr($upload.$fsList['original'], -5)) != '.gzip')
                {
                    $workingFileHasAppropriateExtension = false;    
                }
                $workingFileCompressionType = "gzip";
    		}
		}
		else
		{
		    zip_close($ziphandle);
		    $workingFileIsCompressed = true;
		    $workingFileCompressionType = "zip";
		}
		
		// Move the file to the working directory. Use an exec() + mv call if the file is larger than 400 MB
		$moveWasSuccessful = false;
		if ($fsList['size'] > 419430400)
		{
			// Use an exec call
			logger("Attempting an exec() call to move '" . $upload.$fsList['original'] . "' to the working folder.", LEVEL_FILE_OPERATION);
			if ($workingFileIsCompressed && !$workingFileHasAppropriateExtension)
			{
			    // Add a proper suffix (extension) to indicate a compressed file
			    $lastline = exec(GLOBAL_PATH_TO_MOVE_COMMAND . " " . escapeshellarg(stripslashes($upload.$fsList['original'])) . " " . escapeshellarg(stripslashes($work.$fsList['original'] . ".gz")), $arrOutput, $returnCode);
			}
			else
			{
			    $lastline = exec(GLOBAL_PATH_TO_MOVE_COMMAND . " " . escapeshellarg(stripslashes($upload.$fsList['original'])) . " " . escapeshellarg(stripslashes($work.$fsList['original'] . "")), $arrOutput, $returnCode);
			}
			if ($returnCode != 0)
			{
				logger("Failed to move " . $upload.$fsList['original'] . " to work folder $work. (error code '$returnCode'). The last line of output was '$lastline.'", LEVEL_FILE_OPERATION);
				$db->query("DELETE FROM `$dbDatabase`.`filesitter` WHERE `id`='" . $fsList['id'] . "'");
				continue;
			}
			else
			{
				$moveWasSuccessful = true;
			}
		}
		else
		{
			// Move the file via PHP's rename() function
			logger("Attempting to move '" . $upload.$fsList['original'] . "' to the working folder.", LEVEL_FILE_OPERATION);
			
			if ($workingFileIsCompressed && $workingFileCompressionType == "gzip" && !$workingFileHasAppropriateExtension)
			{
			    // Add an appropriate suffix (extension) to denote a compressed file
			    logger("Adding proper suffix to compressed file.", LEVEL_DATA_WARNING);
			    $renameResult = rename(stripslashes($upload.$fsList['original']), stripslashes($work.$fsList['original'] . '.gz'));
			}
			else
			{
                $renameResult = rename(stripslashes($upload.$fsList['original']), stripslashes($work.$fsList['original']));
			}
			if (false === $renameResult)
			{
				logger("Could not move file: ".$work.$fsList['original'], LEVEL_DATA_WARNING);
				$db->query("DELETE FROM `$dbDatabase`.`filesitter` WHERE `id`='" . $fsList['id'] . "'");
				continue;
				
			}
			else
			{
				$moveWasSuccessful = true;
				logger("Copied " . $upload.$fsList['original'] . " to " . $work.$fsList['original'], LEVEL_INFORMATION);
			}
			
		}
		
		if ($workingFileIsCompressed && $workingFileCompressionType == "gzip" && !$workingFileHasAppropriateExtension)
		{
	        // Modify the database to reflect the filename change
	        $db->query("UPDATE `$dbDatabase`.`filesitter` SET `file`='".addslashes($fsList['original']) . ".gz' WHERE `original`='" . addslashes($fsList['original']) . "' LIMIT 1");
	        
    	    // Modify the existing variables to reflect the filename change
    	    logger("Updating \$fslist['file'] to add .gz extension.");
	        $fsList['file'] = $fsList['original'] . '.gz';
		}

		// From this point forward, all operations should take place against $fsList['file'] rather than $fsList['original'].
		// The two file names may be different, and errors will occur if we try to operate on the wrong one.
		
		if (false === $moveWasSuccessful)
		{
			logger("Could not move file: ".$work.$fsList['file'], LEVEL_DATA_WARNING);
		}
		else
		{
			logger("Moved ".$upload.$fsList['original']." to ".$work.$fsList['file'], LEVEL_FILE_OPERATION);
	
			//if(gzipTest(stripslashes($work.$fsList['original'])))
			if ($workingFileIsCompressed)
			{
			    $decompressionWasSuccessful = false;
                switch ($workingFileCompressionType)
                {
                    case "gzip":
        				logger("File: ".$work.$fsList['file']." is a gzip file. Gunzipping.", LEVEL_FILE_OPERATION);
        				$gunzip_command_lastline = exec($gunzip_exec.'gunzip -f -q '.escapeshellarg(stripslashes($work.$fsList['file'])), $gunzip_command_ouput, $ret_var);
        				$fileSitterFile = preg_replace("/\.gz$/i", "", stripslashes($fsList['file']));
        				if($ret_var != '0')
        				{
        					logger("Gunzipping of '" . $work.$fsList['file'] . "' failed with error code: $ret_var.", LEVEL_DATA_WARNING);
        					logger("Full output from gunzip was : " . implode('---', $gunzip_command_ouput), 2);
        					
        					logger("Removing: ".$fsList['file']." from filesitter.", LEVEL_DATABASE_OPERATION);
        					$deleteQuery = "DELETE FROM `".$dbDatabase."`.`filesitter` WHERE `id`='".$fsList['id']."'";
        					$db->query($deleteQuery);
        					
        					logger("Attempting to move file to the \"unrecognized\" directory.", LEVEL_FILE_OPERATION);
        					if(!rename(stripslashes($work.$fsList['file']), stripslashes($unrec.$fsList['original'])))
        					{
        						logger("Could not gunzip ".$work.$fsList['file']." or move file.", LEVEL_DATA_WARNING);
        					}
        				}
        				else
        				{
        					logger("Gunzip completed for: $fileSitterFile", LEVEL_STATUS);
        					$decompressionWasSuccessful = true;
        				}
        				break;
                    case "zip":
                        logger("File: ".$work.$fsList['file']." is a ZIP file. Unzipping.", LEVEL_FILE_OPERATION);

                        // Open the ZIP archive to extract the name of the file contained within.
                        $ziphandle = zip_open($work.$fsList['file']);
                        $unzippedFileName = zip_entry_name(zip_read($ziphandle));
                        zip_close($ziphandle);
                        logger("The unzipped filename will be: $unzippedFileName ", LEVEL_INFORMATION);

                        // Unzip the file
                        $unzip_command_lastline = exec("/usr/local/bin/unzip -o " . escapeshellarg(stripslashes($work.$fsList['file'])) . " -d $work", $unzip_command_ouput, $ret_var);
                        $fileSitterFile = $unzippedFileName;

                        // Check for an error
                        if ($ret_var != '0')
                        {
                            logger ("Unzipping of '" . $work.$fsList['file'] . "' failed with error code: $ret_var.", LEVEL_DATA_WARNING);
                            logger("Full output from unzip was : " . implode(' ', $unzip_command_ouput), LEVEL_STATUS);

                            logger("Removing: ".$fsList['file']." from filesitter.", LEVEL_DATABASE_OPERATION);
        					$deleteQuery = "DELETE FROM `".$dbDatabase."`.`filesitter` WHERE `id`='".$fsList['id']."'";
        					$db->query($deleteQuery);
        					
        					logger("Attempting to move file to the \"unrecognized\" directory.", LEVEL_FILE_OPERATION);
        					if(!rename(stripslashes($work.$fsList['file']), stripslashes($unrec.$fsList['original'])))
        					{
        						logger("Could not unzip ".$work.$fsList['file']." or move file.", LEVEL_DATA_WARNING);
        					}
                        }
                        else
                        {
                	        // Remove the original compressed file
                	        if (file_exists(stripslashes($work.$fsList['file'])))
                	        {
                	            logger("Removing compressed file '" .$work.$fsList['file'] . "'." , LEVEL_FILE_OPERATION);
                                unlink(escapeshellarg(stripslashes($work.$fsList['file'])));
                	        }
                            logger("Unzip completed for $fileSitterFile", LEVEL_STATUS);
                            
                            // Update filesitter with the decompressed filename
                            $db->query("UPDATE `$dbDatabase`.`filesitter` SET `file` ='" . $db->escape_string($unzippedFileName) . "', `file`='" . $db->escape_string($unzippedFileName) . "' WHERE `original`='" . addslashes($fsList['original']) . "' LIMIT 1");
                            
                    	    // Modify the existing variables to reflect the filename change
                    	    logger("Updating \$fslist['file'] to reflect the new filename.");
                	        $fsList['file'] = $unzippedFileName;
                	        
                	        $decompressionWasSuccessful = true;
                        }
                        break;
                    default:
                        logger("File is identified as compressed, but is neither GZIP or ZIP. Compression identification string is '$workingFileCompressionType.'", LEVEL_PROGRAMMING_WARNING);
                        break;
                }
			}
			else
			{
				$fileSitterFile=$fsList['file'];
				logger("File: ".$fileSitterFile." is not compressed. Continuing. . .", LEVEL_INFORMATION);
			}
			
			if ($decompressionWasSuccessful)
			{
    			$postSize=getFileSize($work.$fileSitterFile);
    			
    			logger("Updating filesize in filesitter for \"".$work.$fileSitterFile."\" (". number_format($postSize) . " bytes)...", LEVEL_DATABASE_OPERATION);
    			$updateQuery = "UPDATE `$dbDatabase`.`filesitter` SET `postsize`='$postSize', `file`='" . addslashes($fileSitterFile) . "', `storage`='". addslashes($fsList['file']) ."', `process`=2 WHERE `id`=".$fsList['id'];
    			$db->query($updateQuery);
    			
    			logger("Changing filemode to 666 for: " . $work . $fileSitterFile, LEVEL_FILE_OPERATION);
    			chmod($work.$fileSitterFile, 0666);
    			
    			logger("Filesitter fields (postsize, file, process) have been updated. Process goes to \"" . getProcessName(2) . ".\"", LEVEL_INFORMATION);
			}
		}
	}
	return true;
}

function nomatch()
{
	// The uploaded file does not match any of the known feed sources
	
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $work, $unrec, $storage, $dbDatabase, $db;
	
	$selectQuery = "SELECT * FROM `filesitter` WHERE `feedfile`=999 AND `process`=3";
	$db->query($selectQuery);
	
	foreach ($db->arrays() as $a_row)
	{
		if ( substr( $a_row['original'], -3 ) == ".gz")
		{
			$varfile = substr( $a_row['file'], 0, ( strlen( $a_row['original'] ) - 3 ) );
		}
		else if (substr( $a_row['original'], -4 ) == ".zip")
		{
		    $varfile = substr( $a_row['file'], 0, ( strlen( $a_row['original'] ) - 4 ) );
		}
		else
		{
			$varfile = $a_row['original'];
		}
		
		logger("Deleting nomatch file '$varfile' from working directory.", LEVEL_FILE_OPERATION);
		unlink($work.$varfile);

		if (DATALOADER_SAVE_COPIES_OF_FEEDS && file_exists($storage.$a_row['storage']))
		{
			logger("Moving backup copy of file to the unrecognized directory.", LEVEL_FILE_OPERATION);
			rename($storage.$a_row['storage'], $unrec.$a_row['storage'] );
		}
		$updateQuery = "UPDATE `filesitter` SET `process` = 10 WHERE `id` = ".$a_row['id'];
		$db->query($updateQuery);
		
		logger("Inserting log entry.", LEVEL_INFORMATION);
		$logfileFromFilesitterQuery="INSERT INTO `$dbDatabase`.`logfile`
			(`original`, `file`, `time`, `filestart`, `fileend`, `datastart`, `dataend`, `serial`,
			`size`, `postsize`, `process`, `feedfile`, `storage`, `datalines`, `notes`, `compcheckerid`)
			SELECT `original`, `file`, `time`, `filestart`, `fileend`, `datastart`, `dataend`, `serial`,
			`size`, `postsize`, `process`, `feedfile`, `storage`, `datalines`, `notes`, `compcheckerid`
			FROM `$dbDatabase`.`filesitter` WHERE `id` = ".$a_row['id'];
		$db->query($logfileFromFilesitterQuery);
		
		logger("Deleting filesitter entry: ".$a_row['id'] . ". (Function: " . __FUNCTION__ . ")", LEVEL_DATABASE_OPERATION);
		$deleteQuery = "DELETE FROM `filesitter` WHERE id = ".$a_row['id'];
		$db->query($deleteQuery);
	}
}

function processfeed($source)
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	switch ($source)
	{
		case 'commissionjunction':
			include_once "process/commissionjunction.php";
			processCommissionJunctionFeed();
			break;
		case 'linkshare':
			include_once "process/linkshare.php";
			processLinkshareFeed();
			break;
		case 'performics':
			include_once "process/performics.php";
			processPerformicsFeed();
			break;
		default:
			logger("File does not appear to match any feed pattern.", LEVEL_DATA_WARNING);
			break;
	}
}

function scheduleCheck()
{
	// Schedule function to tell daemon to not work at a certain time.
	// If the cont flag in dl_daemon is true, operate in continuous mode.

	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $sleepScheduleInterval, $startRunFlag, $endRunFlag, $db;
	
	static $logTgl_1=0;
	$logTgl_2=1;
	if($sleepScheduleInterval < 5) $sleepScheduleInterval=30;
	
	$lp1=true;
	while($lp1)
	{
		$contRunFlag=getSchedule();
		if($contRunFlag != 0) break;
		if( ($startRunFlag<time()) && (time()<$endRunFlag) ) break;
		if($logTgl_2==1)
		{
			logger("Suspending operations at " . strftime("%c"), LEVEL_INFORMATION);
	  		logger("Restarting operations at " . strftime("%c",$startRunFlag), LEVEL_INFORMATION);
			$logTgl_2=0;
		}
		sleep($sleepScheduleInterval);
		$db->ping("Checking MySQL connection from function " . __FUNCTION__ . ".");
	}
}

function setConfig()
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $db;
	global $sys_cfg, $storage, $work, $logEnable, $upload, $unrec, $daemonFiles, $appath;
	global $dNice, $email, $holdtime, $loopSleepTime, $ul_wait_time, $gunzip_exec,
	$head_exec, $dl_umask, $os, $dbTmpDir;
	
	$sys_cfg = array();
	$selectQuery = "SELECT * FROM `dl_daemon` WHERE `id`=1";
	$db->query($selectQuery);
	
	if($db->rowCount() == 0)
	{
		shutdown("Configuration data does not exist in the dl_daemon table of the selected database. Exiting...");
	}
	
	$sys_cfg = $db->firstArray();

	// Daemon globals
	$dNice					= $sys_cfg['nice'];
	$email					= $sys_cfg['email'];
	$holdtime				= $sys_cfg['holdtime'];
	$loopSleepTime			= $sys_cfg['loopsleeptime'];
	$sleepScheduleInterval	= $sys_cfg['downtime'];
	$ul_wait_time			= $sys_cfg['ulWaitTime'];
	$logEnable				= $sys_cfg['logtype'];
	$os						= $sys_cfg['osType'];

	// File globals
	$basepath		= rtrim($sys_cfg['basepath'], '/').'/';
	$appath			= rtrim($sys_cfg['appath'], '/') . '/';
	$storage		= rtrim($sys_cfg['storage'], '/') . '/';
	$work			= rtrim($sys_cfg['work'], '/').'/';
	$upload			= rtrim($sys_cfg['upload'], '/').'/';
	$unrec			= rtrim($sys_cfg['unrec'], '/').'/';
	$daemonFiles	= rtrim($sys_cfg['daemon'], '/').'/';
	$gunzip_exec	= rtrim($sys_cfg['gunzipExec'], '/').'/' ;
	$head_exec		= rtrim($sys_cfg['headExec'], '/').'/';
	$dbTmpDir		= rtrim($sys_cfg['databaseDir'], '/').'/';
	$dl_umask		= $sys_cfg['umask'];
	
	return $sys_cfg;
}

function sizeconverter($filesize)
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
    if ( $filesize < 1024 ) {
        $conv = $filesize." bytes";
    } elseif ( $filesize >= 1024 && $filesize < 1048576 ) {
        $conv = round( abs( $filesize / 1024 ), 2 )." KB";
    } elseif ( $filesize >= 1048576 && $filesize < 1073741824 ) {
        $conv = round( abs( $filesize / 1048576 ), 2 )." MB";
    } elseif ( $filesize >= 1073741824 ) {
        $conv = round( abs( $filesize / 1073741824 ), 2 )." GB";
    }
    return $conv;
}

// Table-creation functions

function cj_table($dbName, $tablename, $revision=1)
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	/*
	 *  As of May, 2010, feeds from Commission Junction can arrive in two different formats.
	 *  This function will return the correct table structure for a Commission Junction feed
	 *  based on the revision number.
	 */
	
	switch ($revision)
	{
	    case 2:
            $cjTable = "
        	  CREATE TABLE IF NOT EXISTS `$dbName`.`$tablename` (
        	  `ProgramName` varchar(100) NOT NULL default '',
        	  `ProgramURL` varchar(2000) NOT NULL default '',
        	  `CatalogName` varchar(130) NOT NULL default '',
        	  `LastUpdated` varchar(255) NOT NULL default '',
        	  `Name` varchar(255) NOT NULL default '',
        	  `Keywords` VARCHAR(500) NOT NULL,
        	  `Description` VARCHAR(3000) NOT NULL,
        	  `SKU` varchar(100) NOT NULL default '',
        	  `Manufacturer` varchar(250) NOT NULL default '',
        	  `ManufacturerID` varchar(64) NOT NULL default '',
        	  `UPC` varchar(15) NOT NULL default '',
        	  `ISBN` varchar(64) NOT NULL default '',
        	  `Currency` varchar(3) NOT NULL default 'USD',
        	  `SalePrice` decimal(11,2) NULL,
        	  `Price` decimal(11,2) NULL,
        	  `RetailPrice` decimal(11,2) NULL,
        	  `FromPrice` varchar(255) NOT NULL default '',
        	  `BuyURL` varchar(2000) NOT NULL default '',
        	  `ImpressionURL` varchar(2000) NOT NULL default '',
        	  `ImageURL` varchar(2000) NOT NULL default '',
        	  `AdvertiserCategory` varchar(300) NOT NULL default '',
        	  `ThirdPartyID` varchar(64) NOT NULL default '',
        	  `ThirdPartyCategory` varchar(300) NOT NULL default '',
        	  `Author` varchar(130) NOT NULL default '',
        	  `Artist` varchar(130) NOT NULL default '',
        	  `Title` varchar(130) NOT NULL default '',
        	  `Publisher` varchar(130) NOT NULL default '',
        	  `Label` varchar(130) NOT NULL default '',
        	  `Format` varchar(64) NOT NULL default '',
        	  `Special` varchar(3) NOT NULL default '',
        	  `Gift` varchar(3) NOT NULL default '',
        	  `PromotionalText` varchar(300) NOT NULL default '',
        	  `StartDate` DATETIME NULL,
        	  `EndDate` DATETIME NULL,
        	  `Offline` varchar(3) NOT NULL default '',
        	  `Online` varchar(3) NOT NULL default '',
        	  `Instock` varchar(3) NOT NULL default '',
        	  `Condition` varchar(11) NOT NULL default '',
        	  `Warranty` varchar(300) NOT NULL default '',
        	  `StandardShippingCost` decimal(10,2) NOT NULL default '0.00', 
        	  INDEX (ProgramName))
        	  ENGINE=MyISAM DEFAULT CHARSET=latin1";
	        break;
	    case 1:
	    default:
	        $cjTable = "
        	  CREATE TABLE IF NOT EXISTS `$dbName`.`$tablename` (
        	  `ProgramName` varchar(100) NOT NULL default '',
        	  `ProgramURL` varchar(2000) NOT NULL default '',
        	  `LastUpdated` varchar(255) NOT NULL default '',
        	  `Name` varchar(255) NOT NULL default '',
        	  `Keywords` VARCHAR(500) NOT NULL,
        	  `Description` VARCHAR(3000) NOT NULL,
        	  `SKU` varchar(100) NOT NULL default '',
        	  `Manufacturer` varchar(250) NOT NULL default '',
        	  `ManufacturerID` varchar(64) NOT NULL default '',
        	  `UPC` varchar(15) NOT NULL default '',
        	  `ISBN` varchar(64) NOT NULL default '',
        	  `Currency` varchar(3) NOT NULL default 'USD',
        	  `SalePrice` decimal(11,2) NULL,
        	  `Price` decimal(11,2) NULL,
        	  `RetailPrice` decimal(11,2) NULL,
        	  `FromPrice` varchar(255) NOT NULL default '',
        	  `BuyURL` varchar(2000) NOT NULL default '',
        	  `ImpressionURL` varchar(2000) NOT NULL default '',
        	  `ImageURL` varchar(2000) NOT NULL default '',
        	  `AdvertiserCategory` varchar(300) NOT NULL default '',
        	  `ThirdPartyID` varchar(64) NOT NULL default '',
        	  `ThirdPartyCategory` varchar(300) NOT NULL default '',
        	  `Author` varchar(130) NOT NULL default '',
        	  `Artist` varchar(130) NOT NULL default '',
        	  `Title` varchar(130) NOT NULL default '',
        	  `Publisher` varchar(130) NOT NULL default '',
        	  `Label` varchar(130) NOT NULL default '',
        	  `Format` varchar(64) NOT NULL default '',
        	  `Special` varchar(3) NOT NULL default '',
        	  `Gift` varchar(3) NOT NULL default '',
        	  `PromotionalText` varchar(300) NOT NULL default '',
        	  `StartDate` DATETIME NULL,
        	  `EndDate` DATETIME NULL,
        	  `Offline` varchar(3) NOT NULL default '',
        	  `Online` varchar(3) NOT NULL default '',
        	  INDEX (ProgramName))
        	  ENGINE=MyISAM DEFAULT CHARSET=latin1";
	        break;
	}
	
	return $cjTable;
}

function linkshare_table($dbName, $tablename)
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	// SQL to create a Linkshare table
	$lsTable = "
	  CREATE TABLE IF NOT EXISTS `$dbName`.`$tablename` (
		`id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`ProductID` varchar(255) NOT NULL default '',
		`ProductName` varchar(255) NOT NULL default '',
		`Sku Number` varchar(255) NOT NULL default '',
		`PrimaryCategory` varchar(255) NOT NULL default '',
		`SecondaryCategories` varchar(255) NOT NULL default '',
		`ProductURL` varchar(255) NOT NULL default '',
		`ProductImageURL` varchar(255) NOT NULL default '',
		`BuyURL` varchar(255) NOT NULL default '',
		`ShortProductDescription` text NOT NULL,
		`LongProductDescription` varchar(255) NOT NULL default '',
		`Discount` varchar(255) NOT NULL default '',
		`DiscountType` varchar(255) NOT NULL default '',
		`SalePrice` decimal(11,2) NOT NULL,
		`RetailPrice` decimal(11,2) NOT NULL,
		`BeginDate` varchar(255) NOT NULL default '',
		`EndDate` varchar(255) NOT NULL default '',
		`Brand` varchar(255) NOT NULL default '',
		`Shipping` varchar(255) NOT NULL default '',
		`Keywords` varchar(255) NOT NULL default '',
		`ManufacturerPartNumber` varchar(255) NOT NULL default '',
		`ManufacturerName` varchar(255) NOT NULL default '',
		`ShippingInformation` varchar(255) NOT NULL default '',
		`Availability` varchar(255) NOT NULL default '',
		`UniversalPricingCode` varchar(255) NOT NULL default '',
		`ClassID` varchar(255) NOT NULL default '',
		`Currency` varchar(255) NOT NULL default '',
		`M1` varchar(255) NOT NULL default '',
		`Pixel` varchar(255) NOT NULL default ''
		) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
	
	  return $lsTable;
}

function performics_table($dbName, $tablename)
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);	// SQL to create a Performics table
	$performicsTable = "
	  CREATE TABLE IF NOT EXISTS `$dbName`.`$tablename` (
	  `id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	  `Product_ID` varchar(100),
	  `Product_name` text NOT NULL,
	  `Product_URL` text NOT NULL,
	  `Buy_URL` text,
	  `Image_URL` text,
	  `Category` text,
	  `Category_ID` text,
	  `PFX_Category` int,
	  `Brief_desc` varchar(200) NOT NULL default '',
	  `Short_desc` varchar(256),
	  `Interim_desc` varchar(512),
	  `Long_desc` text,
	  `Product_Keyword` text,
	  `Brand` text,
	  `Manufacturer` text,
	  `Manf_ID` text,
	  `Manufacture_model` text,
	  `UPC` varchar(30),
	  `Platform` varchar(50),
	  `Media_type_desc` text,
	  `Merchandise_type` text,
	  `Price` decimal(11,2) NOT NULL default '0.00',
	  `Sale_price` decimal(11,2),
	  `Variable_Commission` varchar(3),
	  `Sub_FeedID` varchar(255),
	  `In_Stock` varchar(3),
	  `Inventory` int UNSIGNED,
	  `Remove_date` varchar(10),
	  `Rew_points` int UNSIGNED,
	  `Publisher_Specific` varchar(3),
	  `Ship_avail` varchar(50),
	  `Ship_Cost` decimal(11,2),
	  `Shipping_is_absolute` varchar(3),
	  `Shipping_weight` varchar(50),
	  `Ship_needs` text,
	  `Ship_promo_text` text,
	  `Product_promo_text` text,
	  `Daily_specials_indicator` varchar(3),
	  `Gift_boxing` varchar(3),
	  `Gift_wrapping` varchar(3),
	  `Gift_messaging` varchar(3),
	  `Product_container_name` text,
	  `Cross_selling_reference` text,
	  `Alt_image_prompt` text,
	  `Alt_image_URL` text,
	  `Age_range_min` text,
	  `Age_range_max` text,
	  `ISBN` int UNSIGNED,
	  `Title` text,
	  `Publisher` text,
	  `Author` text,
	  `Genre` text,
	  `Media` text,
	  `Material` text,
	  `Permutation_color` text,
	  `Permutation_size` text,
	  `Permutation_weight` text,
	  `Permutation_item_price` text,
	  `Permutation_sale_price` text,
	  `Permutation_inventory_status` text,
	  `Permutation` text,
	  `Permutation_SKU` text,
	  `BaseProductID` int UNSIGNED,
	  `Option1_Value` text,
	  `Option2_Value` text,
	  `Option3_Value` text,
	  `Option4_Value` text,
	  `Option5_Value` text,
	  `Option6_Value` text,
	  `Option7_Value` text,
	  `Option8_Value` text,
	  `Option9_Value` text,
	  `Option10_Value` text,
	  `Option11_Value` text,
	  `Option12_Value` text,
	  `Option13_Value` text,
	  `Option14_Value` text,
	  `Option15_Value` text,
	  `Option16_Value` text,
	  `Option17_Value` text,
	  `Option18_Value` text,
	  `Option19_Value` text,
	  `Option20_Value` text)
	  ENGINE=MyISAM DEFAULT CHARSET=latin1";
	
	return $performicsTable;
}

function sig_handler($signo)
{
	// Dataloader-specific signal handling
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $db, $holdOff, $daemonFiles;
	// This is what gets called when a signal is caught.
	switch ($signo)
	{
		case SIGHUP:
			// Reread config file(s), etc.
			logger("Caught SIGHUP");
			// $sigHUP = true;
			setConfig();
			logger("Configuration reset");
			return;
			break;
		case SIGUSR1:
			// Suspend program
			logger("Caught SIGUSR1 - Suspending.", LEVEL_STATUS);
			if (defined('PROCESS_NAME') && defined('PROCESS_STATUS_SUSPENDED'))
			{
				changeProcessStatus(PROCESS_NAME, PROCESS_STATUS_SUSPENDED);
			}
			statusReport(PROCESS_NAME, 'suspend');
			$sigHndl=fopen($daemonFiles.'dl_suspend.sem', 'w');
			fwrite($sigHndl, "suspend\n");
			fclose($sigHndl);
			$holdOff = true;
			logger("Closing database connection.", LEVEL_STATUS);
			unset($db);
			break;
		case SIGUSR2:
			// Resume program
			logger("Caught SIGUSR2 - Resuming.");
			logger("Re-opening database connection.", LEVEL_STATUS);
			$db = new DatabaseConnection(DATABASE_HOST, DATABASE_USER, DATABASE_PASSWORD, DATABASE_NAME);
			if (defined('PROCESS_NAME') && defined('PROCESS_STATUS_ACTIVE'))
			{
				changeProcessStatus(PROCESS_NAME, PROCESS_STATUS_ACTIVE);
			}
			statusReport(PROCESS_NAME, 'resume');
			unlink($daemonFiles.'dl_suspend.sem');
			$holdOff = false;
			break;
		case SIGTERM:
			// Handle shutdown tasks
			$signalName = "SIGTERM";
		case SIGINT:
			// Handle SIGINT - do the same as SIGTERM.
			$signalName = "SIGTERM";
		default:
			// Handle all other signals
			if (!isset($signalName))
			{
				$signalName = "UNKNOWN ($signo)";
			}

			if (defined('PROCESS_NAME'))
			{
				changeProcessStatus(PROCESS_NAME, 'end');
			}
			
			logger("Caught $signalName - Shutting down.");
			shutdown("Dataloader stopped on $signalName.");
			break;
	}
}

function removeBlockedCatalogs()
{
    // This function will accept a filename and remove all content from the database
    // that was created from that file.
    
    logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
    
    global $db;
    
    // Check the restrictedProcessFiles table for blocked catalogs
    $db->query("SELECT `filename` FROM `restrictedProcessFiles` WHERE `do_not_upload_to_database` = 1 OR `delete_if_exists` = 1");
    
    if ($db->rowCount() == 0)
    {
        // No restricted files were found
        return;
    }
    $arrFileNames = $db->arrays();
    
    foreach ($arrFileNames as $restrictedFile)
    {
        $tablename = getTableName($restrictedFile['filename']);
        if ($tablename == false || empty($tablename))
        {
            continue;
        }
        $tmpTablename = 'tmp_' . $tablename;
        
        logger("Removing all data related to '" . $restrictedFile['filename']. "' from the database.", LEVEL_DATABASE_OPERATION);
        
        $db->query("DROP TABLE IF EXISTS $tablename;");
        $db->query("DROP TABLE IF EXISTS $tmpTablename;");
        $db->query("DELETE FROM `tablenameMap` WHERE `tablename` = '$tablename'");
        $db->query("DELETE FROM `compchecker` WHERE `tablename` = '$tablename'");
        $db->query("DELETE FROM `sphinxTableList` WHERE `tablename` = '$tablename'");
    }
}

?>
