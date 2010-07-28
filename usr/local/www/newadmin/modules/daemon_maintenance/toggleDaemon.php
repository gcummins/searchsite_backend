<?php

require_once "../includes/backend_requirements.php";

define("COMMAND_PROXY_DAEMON_HOST", "127.0.0.1");
define("COMMAND_PROXY_DAEMON_PORT", 19588);

// Determine how we have been called
if (isset($_GET) && isset($_GET['ak']))
{
	// Called via direct HTTP request
	// We will need to return the user to the calling page
	if (array_key_exists('module', $_GET))
	{
		$module = $_GET['module'];
	}
	else
	{
		$module = null;
	}
	
	$callingMethod = 'get';
}
elseif (isset($_POST) && isset($_POST['ak']))
{
	// Called via an AJAX backend connection
	
	$callingMethod = 'post';
}
else
{
	returnError(100, "Invalid Invocation Method. " . count($_POST) . " " . count($_GET));
	die();
}

if (array_key_exists('daemonid', $_REQUEST) && !empty($_REQUEST['daemonid']))
{
	$daemonId = (int)$_REQUEST['daemonid'];
}
else
{
	returnError(200, 'The daemon id must be provided.', true, null, 'ajax');
}

if (array_key_exists('action', $_REQUEST) && ($_REQUEST['action'] == 'on' || $_REQUEST['action'] == 'off'))
{
	$action = ($_REQUEST['action'] == 'on' ? 1 : 0);
}

// Take appropriate action here to enable or disable the daemon
//
switch ($action)
{
	case 1:
		$response = activateService($daemonId);
		break;
	case 0:
		$response = deactivateService($daemonId);
		break;
	default:
		$response = -1;
		break;
}

echo $response;
exit();


function activateService($daemonid)
{
	switch($daemonid)
	{
		case 1:
			$binName = 'dataloader';
			break;
		case 2:
			$binName = 'linkshare';
			break;
		default:
			$binName = 'dataloader';
			break;
	}

	$returnValue = "";
	
	// Commented, because I think this is causing hanging problems. -- GLC
	//set_time_limit(0);
	
	// Open the socket
	$fp = fsockopen(COMMAND_PROXY_DAEMON_HOST, COMMAND_PROXY_DAEMON_PORT);
	
	if (!$fp)
	{
		returnError(108, "Unable to connect to CommandProxy daemon on $daemonHost:$daemonPort");
		return -1;
	}
	else
	{
		// Send the command
		fputs($fp, "command=start|daemon=$binName\n") or returnError(109, "Unable to send data to daemon ('command=start|daemon=$binName').");
	}
	
	while (1)
	{
		// Command has been sent. Waiting for a response
		$response = trim(fgets($fp, 1024));
		if ($response == "FIN")
		{
			// Transmission is finished. Close connection.
			break;
		}
		elseif ($response == "ACK")
		{
			// This is just an acknowledgement that data was received. Do nothing.
		}
		else
		{
			// Received an informative response
			$returnValue .= $response;
		}
	}
	
	// Send a hangup signal
	fputs($fp, "quit\n");
	
	// Return control to the main body
	returnMessage(1001, "Activated daemon with ID '$daemonid'", false);
	return '200';
}

function deactivateService($daemonid)
{
	global $adminLink;
	
	// First, get the PID of the service
	$query = "SELECT pid FROM datafeeds.dl_daemon WHERE id=$daemonid;";
	$result = mysql_query($query, $adminLink) or returnError(902, $query, 'true', $adminLink);
	$row = mysql_fetch_object($result);
	
	$pid = $row->pid;
	
	// Determine if the process running at this PID is the correct one
	$commandToRun = "/bin/ps -p $pid -w -w";
	$arrCommandResults = array();
	exec($commandToRun, $arrCommandResults);
	if (2 == count($arrCommandResults))
	{
		switch($daemonid)
		{
			case 1:
				$binName = 'dataloader';
				break;
			case 2:
				$binName = 'linkshare';
				break;
			default:
				$binName = 'dataloader';
				break;
		}

		// Something is running at the PID in question. Determine if it is the process we want
		if (false !== stristr($arrCommandResults[1], $binName))
		{
			// The daemon is running at the PID found. Kill the process.

			$returnValue = "";
			
			// Commented, because I think this is causing hanging problems. -- GLC
			//set_time_limit(0);
			
			// Open the socket
			$fp = fsockopen(COMMAND_PROXY_DAEMON_HOST, COMMAND_PROXY_DAEMON_PORT);
			
			if (!$fp)
			{
				returnError(108, "Unable to connect to CommandProxy daemon on $daemonHost:$daemonPort");
				return;
			}
			else
			{
				// Send the command
				fputs($fp, "command=kill|daemon=$binName|pid=$pid\n") or returnError(109, "Unable to send data to daemon ('command=kill|daemon=$binName|pid=$pid').");
			}
			
			while (1)
			{
				// Command has been sent. Waiting for a response
				$response = trim(fgets($fp, 1024));
				if ($response == "FIN")
				{
					// Transmission is finished. Close connection.
					break;
				}
				elseif ($response == "ACK")
				{
					// This is just an acknowledgement that data was received. Do nothing.
				}
				else
				{
					// Received an informative response
					$returnValue .= $response;
				}
			}
			
			// Send a hangup signal
			fputs($fp, "quit\n");
			
			// Return control to the main body
			returnMessage(1001, "Deactivated daemon with ID '$daemonid'", false);
			return '100';
		}
	}
	else
	{
		// There is no process running with the specified ID
		returnError(108, 'No process is running at the ID specified (' . $pid . '). Count=' . count($arrCommandResults));
		return;
	}
}

?>