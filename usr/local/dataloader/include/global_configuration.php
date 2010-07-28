<?php

// Global settings
define('GLOBAL_DATABASE_DIRECTORY', '/var/db/mysql/datafeeds2/');

// Dataloader daemon settings


// Linkshare daemon settings
define('LINKSHARE_CONFIG_FILE', "/usr/local/dataloader/linkshare_config.ini");
define('LINKSHARE_SECONDS_BETWEEN_FTP_LOGINS', 1800);
define('LINKSHARE_PID_FILE', "/usr/local/dataloader/run/linkshare_monitor.pid");
define('LINKSHARE_FTP_HOST', 'aftp.linksynergy.com');
define('LINKSHARE_FTP_USERNAME', 'dealhunting');
define('LINKSHARE_FTP_PASSWORD', 'Rolpv43Ln');
define('LINKSHARE_FTP_UPLOAD_PATH', '/usr/local/dataloader/upload_loc');

// Feed Field Mapping daemon settings
define("FEEDMAPPING_DO_NOT_REINDEX_FILENAME", GLOBAL_APP_PATH."run/do_not_reindex");
define('FEEDMAPPING_TEMPORARY_TABLE_PREFIX', 'tmp_');
define('FEEDMAPPING_COMMISSIONJUNCTION_OUTFILE', '/var/db/mysql/datafeeds2/commissionjunctionoutfile.txt');
define('FEEDMAPPING_LINKSHARE_OUTFILE', '/var/db/mysql/datafeeds2/linkshareoutfile.txt');
define('FEEDMAPPING_PERFORMICS_OUTFILE', '/var/db/mysql/datafeeds2/performicsoutfile.txt');
define('FEEDMAPPING_MAX_RECORDS_PER_BLOCK', 50000);
define('FEEDMAPPING_LOGFILE', 'feedFieldMap.log');
define('FEEDMAPPING_SLEEP_TIME_WHEN_BLOCKED', 10); // Time (in seconds) to sleep when the script is blocked from running
define('FEEDMAPPING_PROCESS_NAME', 'feedFieldMap');

// Frontend management settings
define('FRONTEND_GENERATOR_LOGFILE', 'fegenerator.log');
define('FRONTEND_SLEEP_TIME_BETWEEN_LOOPS', 120); // Time (in seconds) to sleep between check loops.
define('FRONTEND_SLEEP_TIME_AFTER_FILE_GENERATION', 24); // Time (in hours) to sleep after files were successfully generated.
define('FRONTEND_READY_TO_TRANSFER_FILENAME', 'ready_to_transfer');

// Search Site settings
define('SEARCHSITE_CONTROL_DIRECTORY', GLOBAL_APP_PATH . 'searchsite_dbcreation/');
define('SEARCHSITE_LOGFILE', 'searchsiteFeedMap.log');
define('SEARCHSITE_DO_NOT_REINDEX_FILENAME', GLOBAL_APP_PATH."run/searchsite_do_not_reindex");
define('SEARCHSITE_DATABASE_ONE', 'searchsiteone');
define('SEARCHSITE_DATABASE_TWO', 'searchsitetwo');
define('SEARCHSITE_OUTFILE', '/var/db/mysql/datafeeds2/searchsiteoutfile.txt');
define('SEARCHSITE_SPHINX_PORT', 3314);
define('SEARCHSITE_ONLINEDBNAME_FILE', GLOBAL_APP_PATH.'searchsiteOnlineDatabase.txt');
define('SEARCHSITE_STATISTICS_DATABASE_NAME', 'searchsite_stats');
define('SEARCHSITE_STATISTICS_DATABASE_HOST', 'localhost');
define('SEARCHSITE_STATISTICS_DATABASE_USERNAME', 'rvoelker');
define('SEARCHSITE_STATISTICS_DATABASE_PASSWORD', 'rv99105');

// Image Caching daemon settings
define('IMAGECACHE_SERVER', 'images.dealhunting.com:7070');
define('IMAGECACHE_DATABASE_HOST', 'localhost');
define('IMAGECACHE_DATABASE_USER', 'rvoelker');
define('IMAGECACHE_DATABASE_PASSWORD', 'rv99105');
define('IMAGECACHE_DATABASE_NAME', 'datafeeds');
define('IMAGECACHE_DATABASE_LIST_TABLENAME', 'cachedImages');
define('IMAGECACHE_OUTPUT_DIRECTORY', '/usr/local/www/images.dealhunting.com/');
define('IMAGECACHE_NUMBER_OF_IMAGES_PER_CYCLE', 1000);
define('IMAGECACHE_TABLE_STATS', 'stats_imagecache');
define('IMAGECACHE_LOG_FILE', GLOBAL_APP_PATH . 'imagecache.log');
define('IMAGECACHE_PID_FILE', GLOBAL_PID_DIRECTORY . 'cacheImages.pid');

// These settings control the level of each type of error message. Please be sure you understand what
// you are doing before changing these.
define('LEVEL_CRITICAL', 1);			// Critical, script stopping errors
define('LEVEL_FILE_OPERATION', 3);		// File operations
define('LEVEL_PROGRAMMING_WARNING', 4);	// Warnings which may indicate a programming error
define('LEVEL_DATA_WARNING', 5);		// Warnings which may indicate a data error
define('LEVEL_DATABASE_OPERATION', 6);	// An operation that changes data in the database
define('LEVEL_STATUS', 7);				// A status report about the flow of information
define('LEVEL_INFORMATION', 8);			// Informative messages
define('LEVEL_MINUTIA', 10);			// Every action in the script
define('LEVEL_DEBUG', 11);				// Debuging information

?>
