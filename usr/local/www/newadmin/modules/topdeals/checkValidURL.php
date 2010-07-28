<?php

/****

 This script checks a URL to determine if the resource exists.

 Return Values:
  0 : The URL is valid
  1 : No URL was provided
  2 : The URL is invalid

 ****/

require_once "../includes/backend_requirements.php";

if (isset($_REQUEST['url']) && !empty($_REQUEST['url']))
{
	$url = htmlentities(urldecode($_REQUEST['url']));
	$valid = @fopen("$url", 'r');
	
	if ($valid)
	{
		echo "0";
	}
	else
	{
		echo "2";
	}
	exit();
}
else
{
	echo "1";
	exit();
}
?>