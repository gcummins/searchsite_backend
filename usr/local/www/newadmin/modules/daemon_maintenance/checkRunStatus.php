<?php

/*
 Algorithm to determine if a given script is running at a specified process identifier (PID).

Input:
	PID file name
	Script file name

Output:
	{0 | 1 | 2 | 3}
	
	0: Process is running at the 


Case 1:
	The PID file does not exist (or is not readable)
		return FALSE

Case 2:
	The PID file does not contain a valid PID
		return FALSE

Case 3:
	The PID in the PID file is not currently running
		return FALSE

Case 4:
	There is a process running at the PID, but it does not match the script file name based on a string comparison
		return FALSE

Case 5:
	There is a process running at the PID, and the it matches the script file name based on a string comparison
		return TRUE
*/

define('RUN_STATUS_RUNNING', 0);
define('RUN_STATUS_NOPIDFILE', 1);
define('RUN_STATUS_INVALIDPID', 2);
define('RUN_STATUS_PIDNOTEXISTS', 3);
define('RUN_STATUS_NOMATCH', 4);

define('PATH_TO_PS', '/bin/ps');
/*
$scriptFileName = 'dataloader.php';
$pidFileName = '/usr/local/dataloader/run/dataloader.pids';

$runStatus = checkRunStatus($scriptFileName, $pidFileName);
$defaultFailureMessage = "The process '$scriptFileName' is not running.\n"; 

switch ($runStatus)
{
	case 0:
		echo "The process '$scriptFileName' is running.\n";
		break;
	case 1:
		echo "The PID file does not exist or is not readable.\n$defaultFailureMessage";
		break;
	case 2:
		echo "The PID file does not contain a valid PID.\n$defaultFailureMessage";
		break;
	case 3:
		echo "The specified PID does not exist.\n$defaultFailureMessage";
		break;
	case 4:
		echo "The process running at the specified PID does not match the given script name.\n$defaultFailureMessage";
		break;
	default:
		echo $defaultFailureMessage;
		break;
}
*/
function checkRunStatus($scriptFileName, $pidFileName)
{
	// Case 1 from the description above.
	if (!file_exists($pidFileName) || !is_readable($pidFileName))
	{
		return RUN_STATUS_NOPIDFILE;
	}
	else
	{
		// Case 2 from the description above
		
		// First, extract the contents of the PID file
		$arrFileContents = file($pidFileName);
		
		if (!count($arrFileContents))
		{
			return RUN_STATUS_INVALIDPID;
		}
		
		$line = $arrFileContents[0];
		$counter = 0;
		
		while (empty($line))
		{
			$counter++;
			if ($counter <= count($arrFileContents))
			{
				// We checked all lines in the file, and they were all empty
				return RUN_STATUS_INVALIDPID;
			}
			$line = trim($arrFileContents[$counter]);
		}

		if (strlen($line) > 6)
		{
			return RUN_STATUS_INVALIDPID; // I have never seen a server with more than 999,999 processes, have you?
		}
		
		if ((int)$line > 999999 || (int)$line < 0) // Must be a itneger value between 0 and 999,999
		{
			return RUN_STATUS_INVALIDPID;
		}
		
		$pid = (int)$line; 
		
		unset($line, $counter, $arrFileContents);
	}
	
	// Case 3 from the description above. This section is FreeBSD-specific
	// because of the format options of the 'ps' command.
	if (false === ($lastline = exec(PATH_TO_PS . ' -ax -p ' . $pid . ' -o pid,command', $arrPsOutput, $return_var)))
	{
		return 999;
	}
	
	
	// Search all the lines of the ps output to find one that starts with the PID in the PID file.
	$counter = 0;
	$line = trim($arrPsOutput[$counter]);
	
	while (substr($line, 0, strlen($pid)) != $pid)
	{
		$counter++;
		
		if ($counter >= count($arrPsOutput))
		{
			// We searched through all the lines and did not find one that started with the PID
			return RUN_STATUS_PIDNOTEXISTS;
		}
		
		$line = trim($arrPsOutput[$counter]);
	}
	
	// Case 4 from the description above.
	// We found a process with the PID list in the PID file. Now determine if the process matches the script name
	
	// First, trim the PID from the front and remove and spaces or extra characters
	$line = trim(substr($line, strlen($pid)));
	
	// Next, see if the script file name exists in this string. This is a somewhat inexact check. For example,
	// if a user happens to be editing the script, we could see a process like:
	//
	// $ vi <scriptname>
	//
	// However, since we are also checking the PID against the PID file, the likelihood of such a match
	// at the same PID is rather low.
	// Note that we cannot just check that the string is EQUAL to the script file name, because the script may be
	// run in the following manners:
	//
	// $ ./<scriptname
	// $ /path/to/<scriptname>
	// $ php <scriptname>
	// $ /path/to/php <scriptname>
	// $ /path/to/php /path/to/<scriptname>
	if (false === strpos($line, $scriptFileName))
	{
		return RUN_STATUS_NOMATCH;
	}

	// Default return condition
	return 0;
}


?>