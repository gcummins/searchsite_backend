<?php

// Custom Built By
// BATEA Business Solutions, Inc.
// P.O. Box 1022
// Norman OK, 73070
// support@bateainc.com
// (405) 869-0808

// -------------------------------------------------------------
// | This file should not be edited, deleted, or moved without |
// | checking with BATEA Business Solutions, Inc. If this code |
// | is altered it could cause the application run incorrectly |
// | resulting in a service interruption.                      |
// |                                                           |
// |                                    By: Robert Voelker     |
// -------------------------------------------------------------


// Include the configuration settings file
//include_once "include/global_configuration.php";
include "config.inc.php";

define('LOG_FILE_NAME', 'linkshare_monitor.log');
define('PID_FILE', LINKSHARE_PID_FILE);

// Include the generic functions
include_once GLOBAL_APP_PATH . "include/functions.php";

// Set some configuration options
define('DEBUG_LOG', true);	// Enable extra debugging information

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

logger("Forking application into daemon mode.", LEVEL_STATUS);
if ( pcntl_fork() )
{
	exit;
}
posix_setsid();
if ( pcntl_fork() )
{
	exit;
}

$pid = getmypid();
$pidFileHandle = null;
createPIDFile(GLOBAL_PID_DIRECTORY . PID_FILE, $pid);

/*
 * The ownership change is not really necessary, and hinders us in certain areas (like 
 * PID file management). This has been commented out unless a good reason can be shown
 * that we need to change the script ownership. --gcummins, 20090304
 */
//$pw = posix_getpwnam('www');
//posix_setuid($pw['uid']);
//posix_setgid($pw['gid']);

// Connect to the database
include_once("include/db.class.php");

$varstarttime = time();

declare(ticks=1);
pcntl_signal(SIGTERM, "sig_handler");
pcntl_signal(SIGINT, "sig_handler");
pcntl_signal(SIGQUIT, "sig_handler");

list($username, $password, $database, $server, $storage, $work, $upload, $unrec, $unc, $admin_email) = getConfig();

$linkshareFeedId = getLinkshareFeedId();

logger("Updating table `dl_daemon` with current PID", LEVEL_DATABASE_OPERATION);
$db->query("UPDATE `dl_daemon` SET `start`=$varstarttime, `pid`=$pid, `wait`=0 WHERE `id`=$linkshareFeedId LIMIT 1");
logger("Table update complete.", LEVEL_INFORMATION);
// Close the database connection
unset($db);

// Loop forever
while (true)
{
	logger("Reconnecting to the database.", LEVEL_DATABASE_OPERATION);
	$db = new DatabaseConnection(DATABASE_HOST, DATABASE_USER, DATABASE_PASSWORD, DATABASE_NAME);
	
	// Create the linkshare table if it does not already exist
	logger("Creating table `linkshare` if it does not exist.");
	$createTableQuery = "CREATE TABLE IF NOT EXISTS `linkshare` (
		`id` bigint(20) NOT NULL auto_increment,
		`al_filename` varchar(150) NOT NULL default '',
		`al_datetime` varchar(12) NOT NULL default '0',
		`al_lastcheck` bigint(20) NOT NULL default '0',
		`al_lastrun` bigint(20) NOT NULL default '0',
		`al_process` tinyint(4) NOT NULL default '0',
		PRIMARY KEY  (`id`)
		)";
	$db->query($createTableQuery);

	logger("Connecting to FTP host '" . LINKSHARE_FTP_HOST . "' to get file list.");
	if (false === ($conn_id = ftp_connect(LINKSHARE_FTP_HOST)))
	{
		logger("FTP connection to the host '" . LINKSHARE_FTP_HOST . "' failed. Will retry after " . LINKSHARE_SECONDS_BETWEEN_FTP_LOGINS . " seconds.", LEVEL_DATA_WARNING);
		unset($db);
		sleep(LINKSHARE_SECONDS_BETWEEN_FTP_LOGINS);
		continue; // Restart the loop at the beginning
	}
	
	if (false === ftp_login($conn_id, LINKSHARE_FTP_USERNAME, LINKSHARE_FTP_PASSWORD))
	{
		shutdown("An FTP connection to '" . LINKSHARE_FTP_HOST . "' was established, but login failed. Please check the login credentials in the dl_daemon table.", LEVEL_DATA_WARNING);
	}
	$buff = ftp_rawlist($conn_id, '/');
	ftp_close($conn_id);
	logger("Closed FTP connection.", LEVEL_INFORMATION);
	
	if (is_array($buff) && count($buff))
	{
		foreach ($buff as $file)
		{
			logger("Processing file string '$file'");
			if(ereg("([-dl][rwxstST-]+).* ([0-9]*) ([a-zA-Z0-9]+).* ([a-zA-Z0-9]+).* ([0-9]*) ([a-zA-Z]+[0-9: ]*[0-9])[ ]+(([0-9]{2}:[0-9]{2})|[0-9]{4}) (.+)", $file, $regs))
			{
				if ( strstr( $regs[9], "txt" ) ) // This is a product file.
				{
					$var_file_name = substr($regs[9], 0, ( strlen($regs[9]) - 4 ));
					if ( $regs[7] < 2000 )
					{
						logger("\$regs[7] is less than 2000");
						$var_year = date("Y",strtotime($regs[6]));
						$var_month = date("m",strtotime($regs[6]));
						$var_day = date("d",strtotime($regs[6]));
						$var_hour = substr($regs[7],0,2);
						$var_minute = substr($regs[7],3,2);
						$var_datetime = $var_year.$var_month.$var_day.$var_hour.$var_minute;
						
						// Get the file details from the linkshare table
						$detailQuery = "SELECT * FROM `linkshare` WHERE `al_filename` = '$var_file_name' LIMIT 1";
						$db->query($detailQuery);
						
						if ($db->rowCount() == 0)
						{
							logger("Inserting $var_file_name data into table `linkshare`.", LEVEL_DATABASE_OPERATION);
							$insertQuery = "INSERT INTO `linkshare` (`al_filename`, `al_datetime`,"
								. " `al_lastcheck`, `al_process`) VALUES"
								. " ( '$var_file_name', '$var_datetime', '" . time(). "', '1' )";
							$db->query($insertQuery);
						}
						else
						{
							$linkshareRecordSet = $db->firstObject();
							logger("Filename: " . $linkshareRecordSet->al_filename, LEVEL_INFORMATION);
							$var_process = 0;
							if ( substr($linkshareRecordSet->al_datetime,0,4) != $var_year )
							{
								$var_process = 1;
							}
							if ( substr($linkshareRecordSet->al_datetime,4,2) != $var_month )
							{
								$var_process = 1;
							}
							if ( substr($linkshareRecordSet->al_datetime,6,2) != $var_day )
							{
								$var_process = 1;
							}
							if ( substr($linkshareRecordSet->al_datetime,8,2) != $var_hour )
							{
								$var_process = 1;
							}
							if ( substr($linkshareRecordSet->al_datetime,10,2) != $var_minute )
							{
								$var_process = 1;
							}
								
							if ( $var_process == 1 )
							{
								logger("Updating table `linkshare`, setting al_process=1", LEVEL_DATABASE_OPERATION);
								$updateQuery = "UPDATE `linkshare` SET `al_process`=1, `al_datetime`='$var_datetime', `al_lastcheck`='" . time() . "' WHERE `id`=" . $linkshareRecordSet->id;
								$db->query($updateQuery);
							}
							else
							{
								logger("Updating table linkshare, setting al_lastcheck=" . time(), LEVEL_DATABASE_OPERATION);
								$updateQuery = "UPDATE `linkshare` SET `al_lastcheck` = '".time()."' WHERE `id`=" . $linkshareRecordSet->id;
								$db->query($updateQuery); 
							}
						}
					}
					else
					{
						$var_year = date("Y",strtotime($regs[6]));
						$var_month = date("m",strtotime($regs[6]));
						$var_day = date("d",strtotime($regs[6]));
						$var_datetime = $var_year.$var_month.$var_day."0000";
						$selectQuery = "SELECT * FROM `linkshare` WHERE `al_filename` = '$var_file_name' LIMIT 1";
						$db->query($selectQuery);
						
						if ($db->rowCount() == 0)
						{
							logger("Insert $var_file_name data into table `linkshare`.");
							$insertQuery = "INSERT INTO `linkshare` (`al_filename`, `al_datetime`, `al_lastcheck`, `al_process`) VALUES ( '$var_file_name', '$var_datetime', '".time()."', '1' )";
							$db->query($insertQuery);
						}
						else
						{
							$linkshareRecordSet = $db->firstObject();
							$var_process = 0;
							if ( substr($linkshareRecordSet->al_datetime,0,4) != $var_year )
							{
								$var_process = 1;
							}
							if ( substr($linkshareRecordSet->al_datetime,4,2) != $var_month )
							{
								$var_process = 1;
							}
							if ( substr($linkshareRecordSet->al_datetime,6,2) != $var_day )
							{
								$var_process = 1;
							}
							if ( $var_process == 1 )
							{
								logger("Updating table `linkshare`, setting al_process=1");
								$updateQuery = "UPDATE `linkshare` SET `al_process` = 1, `al_datetime` = '$var_datetime', `al_lastcheck`` = '".time()."' WHERE `id`=" . $linkshareRecordSet->id;
							}
							else
							{
								logger("Updating table `linkshare`, setting al_lastcheck=" . time());
								$updateQuery = "UPDATE `linkshare` SET `al_lastcheck` = '".time()."' WHERE `id`=" . $linkshareRecordSet->id;
							}
							$db->query($updateQuery);
						}
					}
				}
				else
				{
					logger("File string '$file' does not contain 'txt' at \$regs[9].");
				}
			}
			else
			{
				logger("File string '$file' does not match the required regex pattern.");
			}
		}
		logger("Connecting to FTP host '" . LINKSHARE_FTP_HOST . "' to retrieve files.");
		if (false === ($conn_id = ftp_connect(LINKSHARE_FTP_HOST)))
		{
			logger("FTP connection to the host '" . LINKSHARE_FTP_HOST . "' failed. Will retry after " . LINKSHARE_SECONDS_BETWEEN_FTP_LOGINS . " seconds.", LEVEL_DATA_WARNING);
			unset($db);
			sleep(LINKSHARE_SECONDS_BETWEEN_FTP_LOGINS);
			continue; // Restart the loop at the beginning
		}
		else
		{
			if (false === (ftp_login($conn_id, LINKSHARE_FTP_USERNAME, LINKSHARE_FTP_PASSWORD)))
			{
				shutdown("An FTP connection to '" . LINKSHARE_FTP_HOST . "' was established, but login failed. Please check the login credentials in the dl_daemon table.", LEVEL_DATA_WARNING);
			}
			
			$db->query("SELECT `id`, `al_filename` FROM `linkshare` WHERE `al_process` = 1");
			
			if ($db->rowCount() == 1)
			{
				$countMessage = "There is 1 file to process.";
			}
			else
			{
				$countMessage = "There are " . $db->rowCount() . " files to process.";
			}
			logger($countMessage, LEVEL_INFORMATION);
			
			if ($db->rowCount() != 0)
			{
				//while ( $b_row = mysql_fetch_array( $b_result ) )
				foreach ($db->objects() as $fileRow)
				{
					$server_file = $fileRow->al_filename;
					$local_file = LINKSHARE_FTP_UPLOAD_PATH . "/" . $fileRow->al_filename;
					
					if (mayDownload($server_file))
					{
						logger("Retrieving file '$server_file.'", LEVEL_INFORMATION);
						if (false === ftp_get($conn_id, $local_file, $server_file, FTP_BINARY))
						{
							logger("Error retrieving file '$server_file' from host '" . LINKSHARE_FTP_HOST . ".' Deleting...", LEVEL_DATA_WARNING);
							if (file_exists($local_file))
							{
								if (false !== unlink($local_file))
								{
									logger("The file '$local_file' was deleted successfully.", LEVEL_INFORMATION);
								}
								else
								{
									logger("The file '$local_file' could not be deleted. Please remove this file manually.", LEVEL_DATA_WARNING);
								}
							}
							
						}
						else
						{
							$db->query("UPDATE `linkshare` SET `al_lastrun` = ".time().", `al_process` = 0 WHERE `id`=" . $fileRow->id);
						}
					}
					else
					{
						logger("Skipping file '$server_file' because of administrative restriction.", LEVEL_INFORMATION);
					}
				}
			}
			ftp_close($conn_id);
		}
		unset($buff);
	}
	else
	{
		logger("There are no files in the FTP buffer to be processed.", LEVEL_DATA_WARNING);
		
	}
	unset($db);
	
	logger("Sleeping for " . LINKSHARE_SECONDS_BETWEEN_FTP_LOGINS . " seconds", LEVEL_INFORMATION);
	sleep (LINKSHARE_SECONDS_BETWEEN_FTP_LOGINS);
}

logger("Exiting script.", LEVEL_INFORMATION);

function email_crit_error($errmsg)
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	mail($admin_email, 'Linkshare Monitor Critical Error', $errmsg);
	return;
}

function getConfig()
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
		
	logger("Reading config file...", LEVEL_FILE_OPERATION);
	$config_file = LINKSHARE_CONFIG_FILE;

	if (false === ($fp = fopen( $config_file, "r" )))
	{
		shutdown("Unable to open the configuration file '$config_file' for reading.");
	}
	
	while (!feof($fp))
	{
		$line = fgets( $fp, 500 );
		if ( substr($line, 0, 8 ) == "username" )
		{
			$username = substr( $line, ( strpos( $line, "\"", 0 ) + 1 ), ( ( strpos( $line, "\"", ( strpos( $line, "\"", 0 ) + 1 ) ) - strpos( $line, "\"", 0 ) ) - 1 ) );
		}
		if ( substr($line, 0, 8 ) == "password" )
		{
			$password = substr( $line, ( strpos( $line, "\"", 0 ) + 1 ), ( ( strpos( $line, "\"", ( strpos( $line, "\"", 0 ) + 1 ) ) - strpos( $line, "\"", 0 ) ) - 1 ) );
		}
		if ( substr($line, 0, 8 ) == "database" )
		{
			$database = substr( $line, ( strpos( $line, "\"", 0 ) + 1 ), ( ( strpos( $line, "\"", ( strpos( $line, "\"", 0 ) + 1 ) ) - strpos( $line, "\"", 0 ) ) - 1 ) );
		}
		if ( substr($line, 0, 6 ) == "server" )
		{
			$server = substr( $line, ( strpos( $line, "\"", 0 ) + 1 ), ( ( strpos( $line, "\"", ( strpos( $line, "\"", 0 ) + 1 ) ) - strpos( $line, "\"", 0 ) ) - 1 ) );
		}
		if ( substr($line, 0, 7 ) == "storage" )
		{
			$storage = substr( $line, ( strpos( $line, "\"", 0 ) + 1 ), ( ( strpos( $line, "\"", ( strpos( $line, "\"", 0 ) + 1 ) ) - strpos( $line, "\"", 0 ) ) - 1 ) );
		}
		if ( substr($line, 0, 4 ) == "work" )
		{
			$work = substr( $line, ( strpos( $line, "\"", 0 ) + 1 ), ( ( strpos( $line, "\"", ( strpos( $line, "\"", 0 ) + 1 ) ) - strpos( $line, "\"", 0 ) ) - 1 ) );
		}
		if ( substr($line, 0, 6 ) == "upload" )
		{
			$upload = substr( $line, ( strpos( $line, "\"", 0 ) + 1 ), ( ( strpos( $line, "\"", ( strpos( $line, "\"", 0 ) + 1 ) ) - strpos( $line, "\"", 0 ) ) - 1 ) );
		}
		if ( substr($line, 0, 5 ) == "unrec" )
		{
			$unrec = substr( $line, ( strpos( $line, "\"", 0 ) + 1 ), ( ( strpos( $line, "\"", ( strpos( $line, "\"", 0 ) + 1 ) ) - strpos( $line, "\"", 0 ) ) - 1 ) );
		}
		if ( substr($line, 0, 3 ) == "unc" )
		{
			$unc = substr( $line, ( strpos( $line, "\"", 0 ) + 1 ), ( ( strpos( $line, "\"", ( strpos( $line, "\"", 0 ) + 1 ) ) - strpos( $line, "\"", 0 ) ) - 1 ) );
		}
		if ( substr($line, 0, 11) == "admin_email" )
		{
			$admin_email = substr( $line, ( strpos( $line, "\"", 0 ) + 1), ( ( strpos( $line, "\"", ( strpos ( $line, "\"", 0 ) + 1 ) ) - strpos( $line, "\"", 0 ) ) - 1 ) );
		}
	}
    fclose( $fp );
    
    return array($username, $password, $database, $server, $storage, $work, $upload, $unrec, $unc, $admin_email);
}

function getLinkshareFeedId()
{
	logger("In " .__FUNCTION__ . "()", LEVEL_MINUTIA);
	
	global $db;
	
	logger("Retrieving the Linkshare feed ID.", LEVEL_DATABASE_OPERATION);
	$db->query("SELECT `id` FROM `feeds` WHERE `name`='Linkshare' OR `name`='LinkShare' LIMIT 1;");
	
	if ($db->rowCount() == 0)
	{
		shutdown("The Linkshare feed ID could not be retrieved from the database.");
	}
	
	return $db->firstField();
	
}

function sig_handler($signal)
{
	// Linkshare-specific signal handling
	logger("In " . __FUNCTION__ . "()", LEVEL_MINUTIA);
	
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
		case SIGPIPE:
			$signalName = "SIGPIPE";
			$quit = true;
			break;
		default:
			$signalName = "UNKNOWN ($signal)";
			$quit = false;
			break;			
	}
	if ($quit)
	{
				
		// Stop the world. We want to get off.
		shutdown("LinkShare monitor is stopping on signal: $signalName", false);
	}
	else
	{
		logger("LinkShare received the following unhandled signal: $signalName", LEVEL_STATUS);
	}
}
?>
