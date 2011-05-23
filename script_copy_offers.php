<?php

ini_set("memory_limit","64M");
error_reporting(E_ERROR);
set_time_limit(0);
mb_internal_encoding("UTF-8");

require_once(dirname(__FILE__) . "/classes/db/db.class.php");

$CONFIG = require_once("config.php");

try
{
	DB::setConfig($CONFIG["db"]);
	DB::q("truncate table offers_actual");
	DB::q("insert ignore into offers_actual select * from offers where status=1 and patterns_status=1 and dt_last_found>" . time() . "-86400*2");
}
catch (Exception $e)
{
	echo "Unspecified fatal exception: " . $e->getMessage() . "\nException occurs in file " . $e->getFile() . " on line " . $e->getLine() . "\n";
}
