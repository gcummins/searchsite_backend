<?php

/*
 * This script adds a filename to the restrictedFilesProcess table
 * in the datafeeds database, and will update the compchecker table to mark the file as 
 * unapproved.
 */

require_once "../includes/backend_requirements.php";

if (isset($_REQUEST['filename']) && !empty($_REQUEST['filename']))
{
        $filename = stripslashes($_REQUEST['filename']);
        
        // Add the file to the 
        $query = "INSERT INTO `restrictedProcessFiles`"
        	. " (`filename`, `do_not_download`, `do_not_store`, `do_not_upload_to_database`, `delete_if_exists`)"
        	. " VALUES ('" . $feedDatabase->escape_string($filename) . "', 1, 1, 1, 1)";
        	
        // Execute the query
        $feedDatabase->query($query);
        
        if ($feedDatabase->error)
        {
            echo "2";
            exit();
        }
        else
        {
            $feedDatabase->query("UPDATE `compchecker` SET `approved` = -1 WHERE `filename` = '" . $feedDatabase->escape_string($filename) . "'");
            if ($feedDatabase->error)
            {            
                echo "3";
            }
            else
            {
                echo "0";
            }
        }
}
else
{
    echo "1";
    exit();
}