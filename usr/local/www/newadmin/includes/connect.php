<?php
include_once "log.php";

// Make sure this script is not called directly
if (!defined('APP_NAME'))
{
	die("This script cannot be accessed directly.");
}

// Connect to the dealhunting database
if (!$dealhuntingLink = mysql_connect(DATABASE_HOST, DATABASE_USER, DATABASE_PASSWORD))
{
	die("Unable to connect to the database server. MySQL said: <p><b>&nbsp;" . mysql_error() . "</b></p>Please check the configuration file (configuration.php) to verify that the connection settings are correct.");
}
if (!mysql_select_db(DATABASE_NAME, $dealhuntingLink))
{
	die("<p>Unable to connect to the database <em>'dealhunting'</em>.</p><p> Please check the configuration file (configuration.php) to verify that the database name is correct, and that the database user has appropriate privileges to this database.</p>");
}

// Connect to the administrative database
if (!$adminLink = mysql_connect(ADMINPANEL_DB_SERVER, ADMINPANEL_DB_USERNAME, ADMINPANEL_DB_PASSWORD, TRUE))
{
	die("Unable to connect to the database server. MySQL said: <p><b>&nbsp;" . mysql_error() . "</b></p>Please check the configuration file (configuration.php) to verify that the connection settings are correct.");
}
if (!mysql_select_db(ADMINPANEL_DB_NAME, $adminLink))
{
	die("<p>Unable to connect to the database <em>'" . ADMINPANEL_DB_NAME . "'</em>.</p><p> Please check the configuration file (configuration.php) to verify that the database name is correct, and that the database user has appropriate privileges to this database.</p>");
}

?>
