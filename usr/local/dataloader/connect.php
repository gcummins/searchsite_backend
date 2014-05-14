<?php

/*
define('DB_HOST', 'localhost');
define('DB_USER', 'mysql_user');
define('DB_PASSWORD', 'pass');
define('DB_NAME', 'your_db');
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
