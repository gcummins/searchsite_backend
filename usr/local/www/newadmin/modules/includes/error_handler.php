<?php

$arrErrorDescriptions = array(
	100 => "Invalid Invocation Method",
	102 => "Request Data Unavailable",
	108 => "Daemon Error",
	200 => "Missing Parameter",
	201 => "Invalid Input",
	202 => "Object Exists",
	301 => "Permission Denied",
	401 => "Unwritable Path",
	402 => "File Exists",
	403 => "File Open Failed",
	404 => "File Write Failed",
	405 => "File Delete Failed",
	800 => "Unclassified Error",
	902 => "Database Query Failure",
	1000 => "", // A successful operation, no tag needed.
	1001 => "Update",
	1002 => "Create",
	1101 => "Delete",
	9999 => "Unfinished Code"
);

function getErrorDescription($code)
{
	global $arrErrorDescriptions;
	
	if (array_key_exists($code, $arrErrorDescriptions))
	{
		return $arrErrorDescriptions[$code];
	}
	else
	{
		return "Unknown Type";
	}
}

// Info-message handler for modules
function returnMessage($code, $message, $display=true)
{
	global $module;
	
	// Log the message
	logNotice(getErrorDescription($code), $message, $_SERVER['REQUEST_URI'], $module);
	
	if ($display)
	{
		// Set the session variables to that the message will be displayed to the user
		$_SESSION['sysmessage']	= $message;
		$_SESSION['sysmtype']	= 'info';
	}

	return true;
}

// Error handler for modules
function returnError($code, $message, $fatal=true, $link=false, $iCallingMethod=null)
{
	global $callingMethod, $module;
	
	// Notify the administrator of the error
	if ($fatal && ADMINPANEL_NOTIFY_FATAL_ERROR)
	{
		$adminSubject = "Fatal Control Panel Error";
		$adminMessage = "An error occured in the administrative control panel.\n\nSeverity:\tfatal\nMessage:\t";
		$sendAdminMessage = true;
	}
	elseif (!$fatal && ADMINPANEL_NOTIFY_NONFATAL_ERROR)
	{
		$adminSubject = "Non-fatal Control Panel Error";
		$adminMessage = "An error occurred in the administrative control panel.\n\nSeverity:\tminor\nMessage:\t";
		$sendAdminMessage = true;
	}
	else
	{
		$sendAdminMessage = false;
	}	
	
	// Determine how we have been called
	if ($iCallingMethod == 'ajax')
	{
		$callingMethod = 'ajax';
	}
	elseif (array_key_exists('callmethod', $_REQUEST) && $_REQUEST['callmethod'] == 'ajax')
	{
		$callingMethod = 'ajax';
	}
	elseif (isset($_GET) && count($_GET))
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
	elseif (isset($_POST) && count($_POST))
	{
		// Called via an AJAX backend connection
		
		$callingMethod = 'post';
	}
	if ($code >=900 && $code < 1000)
	{
		// This is a MySQL error message
		
		if ($callingMethod != 'ajax')
		{
			switch ($code)
			{
				case 902:
					$outputMessage = "<u>Query failed:</u><br /><em> " . str_replace('Last Query:', '<br /><br />Last Query:', $message) . "</em><br /><br />";
					if ($link !== false)
					{
						$outputMessage .= "<u>MySQL said:</u><br /> ";
						if (gettype($link) == "object")	// This is a database object (see includes/db.class.php)
						{
							$outputMessage .= $link->errorMessage;
						}
						else	// This is a standard MySQL resource
						{
							$outputMessage .= mysql_error($link);
						}
					}
					break;
				default:
					if (gettype($link) == "object")	// This is a database object (see includes/db.class.php)
					{
						$outputMessage = $link->errorMessage;
					}
					else	// This is a standard MySQL resource
					{
						$outputMessage = "MySQL Error: " . mysql_error($link);
					}
					break;
			}
		}
		else
		{
			switch ($code)
			{
				case 902:
					if (gettype($link) == "object")
					{
						$outputMesasge = $link->errorMessage;
					}
					else
					{
						$outputMessage = "Query failed:<br />$message<br /><br />MySQL said:<br />" . mysql_error($link);
					}
					break;
				default:
					if (gettype($link) == "object")
					{
						$outputMessage = $link->errorMessage;
					}
					else
					{
						$outputMessage = "The MySQL operation failed. MySQL said:<br />" . mysql_error($link);
					}
					break;
			}
		}
	}
	else
	{
		$outputMessage = "code: $code; " . $message;
	}
	
	// Log the error message
	logError(getErrorDescription($code), $outputMessage, $_SERVER['REQUEST_URI'], $module);

	// Send an email to the administrator
	if ($sendAdminMessage)
	{
		mail(GLOBAL_ADMIN_EMAIL, $adminSubject, strip_tags($adminMessage . $outputMessage) . "\n", "From: \"Admin Panel Error Handler\" <www@" . $_SERVER['SERVER_NAME'] . ">");
	}
	
	if (isset($callingMethod) && $callingMethod != 'ajax')
	{
		if (array_key_exists('paging_spage', $_REQUEST) && !empty($_REQUEST['paging_spage']))
		{
			$startPage = (int)$_REQUEST['paging_spage'];
		}
		else
		{
			$startPage = 1;
		}
		
		// Output the error in a way that it can be styled on-screen
		if (headers_sent())
		{
			$_SESSION['sysmessage'] = $outputMessage;
			// Attempt a javascript redirect
			?>
			<noscript>
			<h3>An error has occurred</h3>
			<p>You appear to have Javascript disabled. This application will not function properly without Javascript. Please enable it, or upgrade to a newer browser.</p>
			<p>To continue anyway and view the error, please <a href="<?php echo ADMINPANEL_WEB_PATH; ?>/index.php?module=$module&sysmtype=error&sysmessage=<?php echo urlencode($outputMessage); ?>">click here.</a></p>
			</noscript>
			<script type="text/Javascript" language="Javascript">
			location.href="<?php echo ADMINPANEL_WEB_PATH; ?>/index.php?sysmtype=error&sysmessage=<?php echo urlencode($outputMessage); ?>&paging_spage=<?php echo $startPage; ?>"; 
			</script>
			<?php
			$fatal = false;
		}
		else
		{
			header("Location: " . ADMINPANEL_WEB_PATH . "/index.php?sysmtype=error&sysmessage=" . urlencode($outputMessage) . "&paging_spage=$startPage");
			exit();
		}

		if ($fatal)
		{
			ADMINPANEL_DEBUG and print "DEBUG MESSAGE: \$fatal = true";
			exit();
		}
	}
	else
	{
		// Output the error in JSON format. This will be used most often for AJAX functions
		$output = '{"error": 
		{
			"error_number": ' . $code . ',
			"message": "' . $outputMessage . '"
		} }';
		echo $output;
		if ($fatal)
		{
			exit();
		}
	}
}

?>
