<?php
define('PROCESS_NAME', 'updatelinksharecount');

include "../config.inc.php";
include "../include/db.class.php";

$arrUpdateQueries = array();
$db->query("SELECT `tablename` FROM `compchecker` WHERE `file`=2 AND `norecs` = 1");
foreach ($db->objects() as $catalog)
{
    $arrUpdateQueries[] = "UPDATE `compchecker` SET `norecs` = (SELECT COUNT(*) FROM `" . $catalog->tablename . "`) WHERE `tablename` = '" . $catalog->tablename . "' LIMIT 1;";
    echo $catalog->tablename . "\n";
}

foreach ($arrUpdateQueries as $query)
{
    $db->query($query);
}

function logger($message)
{
    echo $message . "\n";
}