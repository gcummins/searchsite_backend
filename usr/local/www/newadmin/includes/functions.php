<?php

// Generic, multi-purpose functions

function dhError($message, $redirectFunction='')
{
	// This function is a simple wrapper for the popupMessage function. 
	// It sets the message type to "error."
	dhMessage($message, "error", $redirectFunction);
}

function dhMessage($message, $type="information", $redirectFunction='', $autoHideMessage=true)
{
	// Display a message onscreen. Bypasses error logging and page redirects.
	?><script type="text/javascript" language="Javascript">
	jsDhMessage('<?php echo addslashes($message); ?>', '<?php echo $type; ?>', <?php echo ($autoHideMessage) ? 'true' : 'false'; ?>);
	</script>
	<?php
	if (!empty($redirectFunction))
	{
		eval($redirectFunction . '();');
	}
}

/**
 * Retrieve a value from the specified array (defaults to $_REQUEST)
 *
 * @param string $parameterName
 * @param bool $fatal
 * @param array|null $arrLocation
 * @param mixed $defaultValue
 * @return mixed
 */
function getParameter($parameterName, $fatal=true, $arrLocation=null, $defaultValue=false)
{
	if ($arrLocation == null)
	{
		$arrLocation = $_REQUEST;
	}
	
	if (array_key_exists($parameterName, $arrLocation) && !empty($arrLocation[$parameterName]))
	{
		return $arrLocation[$parameterName];
	}
	else
	{
		if ($fatal)
		{
			dhError("Required parameter is missing: $parameterName");
			exit;
		}
		else
		{
			return $defaultValue;
		}
	}
}

function getTask()
{
	if (isset($_REQUEST['task']) && !empty($_REQUEST['task']))
	{
		return mysql_real_escape_string($_REQUEST['task']);
	}
	else
	{
		return false;
	}
}

function logger($message, $level)
{
	// This function is used only by the database class file (db.class.php)
	// We will send error number 902, because all messages received here will be database-related.
	
	returnError(902, $message, true);
}

/**
 * Format a date string or timestamp into a natural language phrase
 *
 * @param string $date
 * @param bool $is_timestamp=false
 * @return string
 */
function naturalLanguageTimeString( $date, $is_timestamp=false )
{
	// Create a natural language time string
	if (!$is_timestamp)
	{
		// Convert date to a Unix timestamp
		$uts = strtotime($date);
	}
	else
	{
		$uts = $date;
	}
	
	$elapsed = time() - $uts;
	
	if ($elapsed < 60)
	{
		return "<1 minute ago";
	}
	else if ($elapsed < 105)
	{
		return "1 minute ago";
	}
	else if ($elapsed < 3600)
	{
		return round($elapsed/60) . " minutes ago";
	}
	else if ($elapsed < 3600*24)
	{
		return date('h:i a', $uts);
	}
	else if ($elapsed < 3600*48)
	{
		return "yesterday";
	}
	else if ($elapsed < 3600*24*7)
	{
		return date('l', $uts);
	}
	else
	{
		return date('n/j/Y', $uts);
	}
}

function predump($iArray, $force=false)
{
	// Display the contents of an array is an
	// easy-to-read manner
	
	if ($force || ADMINPANEL_DEBUG) // Only display if specifically forced, or if we are in debugging mode.
	{
		echo "<pre>";
		print_r($iArray);
		echo "</pre>";
	}
}

function returnToMainPage($startPage=null, $startRecord=null)
{
	// Perform this redirect to prevent users from resubmitting data
	// upon page refresh
	global $module;
	
	// Ensure that all session variables are written before the redirect is accomplished
	session_write_close();
	
	// Redirect the user
	$link = ADMINPANEL_WEB_PATH . "?module=$module";
	if (!empty($startPage) && is_int($startPage))
	{
		$link .= "&paging_spage=" . (int)$startPage;
	}
	if (!empty($startRecord) && is_int($startRecord))
	{
		$link .= "&paging_startrec=" . (int)$startRecord;
	}
	?>
	<script type="text/javascript">
	location.href="<?php echo $link; ?>";
	</script>
	<?php	
}

/**
 * Redirect the browser via Javascript to the page specified in the parameter 
 *
 * @param string[optional] $urlParameters=null
 */
function returnToPage($urlParameters=null)
{
	if (empty($urlParameters))
	{
		returnToMainPage();
		return;
	}
	else
	{
		global $module;
		
		$link = ADMINPANEL_WEB_PATH . "?module=$module";
		if (substr($urlParameters, 0, 1) == '&')
		{
			$link .= $urlParameters;
		}
		else
		{
			$link .= "&$urlParameters";
		}
		?>
		<script type="text/javascript">
		location.href="<?php echo $link; ?>";
		</script>
		<?php
		return;		
	}
}

function getStartPage()
{
	if (isset($_REQUEST['paging_spage']))
	{
		return (int)$_REQUEST['paging_spage'];
	}
	else
	{
		return 1;
	}
}

function getModuleName($moduleId, $requestType=null)
{
	// Look up the provided module ID in the database
	// and return the associated name
	
	global $adminLink;
	
	$query = "SELECT `name` FROM `modules` WHERE `id`=$moduleId LIMIT 1;";
	if (false === ($result = mysql_query($query, $adminLink)))
	{
		if ($requestType == 'ajax')
		{
			returnError(902, $query, true, $adminLink, $requestType);
			return false;
		}
		else
		{
			returnError(902, $query, false, $adminLink);
		}
	}
	
	if (mysql_num_rows($result))
	{
		$row = mysql_fetch_object($result);
		return $row->name;
	}
	else
	{
		return false;
	}
}

function validate_mysql_username($dbUsername)
{
	/*
	 * MySQL usernames must meet the following criteria
	 *  - Must be at most 16 characters in length
	 *  - May contain only alphanumeric characters and an underscore
	 *  - Must begin with a letter
	 */
	
	if (strlen($dbUsername) > 16)
	{
		return false;
	}
	
	if (eregi('[^a-z0-9_]', $dbUsername))
	{
		return false;
	}
	else
	{
		if (eregi('[A-Za-z]+', substr($dbUsername,0, 1)))
		{
			return true;
		}
		else
		{
			return false;
		}
	}
}

function validate_mysql_db_name($dbName)
{
	/*
	 * Database names must meet the following critera:
	 *  - Must be at most 64 bytes in length
	 *  - May contain only alphanumeric characters and an underscore
	 *  - Must begin with a letter
	 */
	 
	if (function_exists('mb_strlen'))
	{
		if (mb_strlen($dbName) > 64)
		{
			return false;
		}
	}
	else if (strlen($dbName) > 64)
	{
		return false;
	} 
	
	if (eregi('[^a-z0-9_]', $dbName))
	{
		return false;
	}
	else
	{
		if (eregi('[A-Za-z]+', substr($dbName,0, 1)))
		{
			return true;
		}
		else
		{
			return false;
		}
	}
}
?>