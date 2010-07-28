<?php
// Application path
$script_filename=__FILE__;
$pathPos = strrpos($script_filename, "/");
$app_path = substr($script_filename,0,$pathPos);
$app_path .= "/";
$statPath="/usr/bin/";
$dbDirPath="/var/db/mysql/datafeeds2/";

// Database Credentials
$dbUsername='datafeeds';
$dbPassword='';
$dbDatabase='datafeeds';
$dbServer='localhost';

// Tweak some php settings.
ini_set('mysql.default_socket', '/tmp/mysql.sock');
ini_set('error_reporting', E_ALL);
ini_set('error_log', "/usr/local/dataloader/dataloader.err");
ini_set('log_errors', '1');
ini_set('log_errors_max_len', '2048');
ini_set('display_errors', '1');
ini_set('magic_quotes_runtime', '0');

define('ADMIN_EMAIL', 'rvoelker@localhost');

// Performics Files: MD5 sums of first lines
/*$arrPerformicsMD5Sums = array(
			"bf47f9592d4422424976161a6305ab90",
			"fe09561887e52238715ff7493827368c",
			"b6717afb2da2be80ddb2fe442be6c64b"
			);*/
$arrPerformicsMD5Sums = array(
			"714c400fc26fecd965ef3bffc266fc45"
			);
?>
