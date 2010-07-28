<?php

// Make sure this script is not called directly
if (!defined('APP_NAME'))
{
	die("This script cannot be accessed directly.");
}

// Error handler for index.php

function handle_error($message, $isFatal=true, $type='generic', $link=false)
{
	switch ($type)
	{
		case 'mysql':
			echo "<p><u>Query failed:</u> <br /><em>$message</em>.</p><p><u>MySQL said:</u><br/><b>";
			if ($link)
			{
				echo mysql_error($link);
			}
			else
			{
				echo mysql_error();
			}
			echo "</b></p>";
			break;
		default:
			echo "<b>$message</b>";
			break;
	}
	
	if ($isFatal)
	{
		die();
	}
}

?>