<?php

// This script contains common code required by all backend scripts
// which are not bootstrapped via index.php

@session_start(); // Suppress output in case the session is already started

define('APP_NAME', 'DH Admin');

// All of the scripts that backends will require
//include_once "../../configuration.php";
include_once "../../newconfig.php";
include_once "error_handler.php";
include_once "../../includes/functions.php";
include_once "../../includes/connect.php";
include_once "../../includes/permissions.php";
include_once "../../includes/db.class.php";

$dealhuntingDatabase = new DatabaseConnection(DEALHUNTING_DB_HOST, DEALHUNTING_DB_USERNAME, DEALHUNTING_DB_PASSWORD, DEALHUNTING_DB_NAME);
$feedDatabase = new DatabaseConnection(DATABASE_HOST, DATABASE_USER, DATABASE_PASSWORD, DATABASE_NAME);

?>