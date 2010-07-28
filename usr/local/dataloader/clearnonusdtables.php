<?php
include "include/functions.php";
include "config.inc.php";

define('LOG_FILE_NAME', 'deletenonusd.log');
define('PROCESS_NAME', 'deletenonusd');

include GLOBAL_APP_PATH . "include/db.class.php";

for ($i=101; $i<=200; $i++)
{
    $db->query("DELETE FROM `al_02_$i` WHERE `Currency` != 'USD'");
}
?>