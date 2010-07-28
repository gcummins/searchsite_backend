<?php

/*
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '1yaaya5');
define('DB_NAME', 'datafeeds2');
*/

if (false !== ($link = mysql_connect(DATABASE_HOST, DATABASE_USER, DATABASE_PASSWORD)))
{
	if (!mysql_select_db(DATABASE_NAME))
	{
		die("Unable to connect to the database 'allsites'. MySQL said: " . mysql_error());
	}
}
else
{
	die("Unable to connect to the database server. MySQL said: " . mysql_error());
}

?>
