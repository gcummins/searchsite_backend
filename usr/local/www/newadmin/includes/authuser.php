<?php

// If there is no current session, start one
$sessionId = session_id();
if (empty($sessionId))
{
	session_start();
}

if (!array_key_exists('userid', $_SESSION) || empty($_SESSION['userid']))
{
	// Determine if a module, page, or task were specified
	$queryString = "";
	if (array_key_exists('module', $_REQUEST) && is_int($_REQUEST['module']))
	{
		$queryString .= "module=" . (int)$_REQUEST['module'];
		
		if (array_key_exists('page', $_REQUEST))
		{
			$queryString .= "&page=" . $_REQUEST['page'];
		}
	}

	if (array_key_exists('task', $_REQUEST))
	{
		if (!empty($queryString))
		{
			$queryString .= "&";
		}
		$queryString .= "task=" . $_REQUEST['task'];
	}
	
	$url = ADMINPANEL_WEB_PATH . "/login.php";
	
	if (!empty($queryString))
	{
		$url .= "?$queryString";
	}
	
	header("Location: $url");
	exit();
}

?>