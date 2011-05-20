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

	$patterns = array();
	$rws = DB::f("select * from patterns");
	foreach($rws as $rw)
		$patterns[$rw["sysname"]][$rw["input_field"]][mb_strtolower($rw["input_value"])] = $rw["output_value"];

	$rws =DB::f("select * from offers where patterns_status=0");
	foreach($rws as $rw)
	{
		$rw_update = array("patterns_status"=>1);
		foreach($patterns[$rw["sysname"]] as $input_field => $rws)
		{
			if ($rw[$input_field])
			{
				$output_value = $rws[mb_strtolower($rw[$input_field])];
				if (!$output_value)
				{
					echo $rw["id"] . " - pattern not found: sysname=" . $rw["sysname"] . " input_field=" . $input_field . " value=" . $rw[$input_field] . "\n";
					continue 2;
				}
				$rw_update[$input_field . "_id"] = $output_value;
			}
		}

		$update_str = "";
		foreach($rw_update as $field => $value)
			$update_str .= ($update_str?",":"") . $field . "=:" . $field;

		$rw_update["id"] = $rw["id"];

		DB::q("update offers set " . $update_str . " where id=:id",$rw_update);
		echo $rw["id"] . " updated\n";
	}
}
catch (Exception $e)
{
	echo "Unspecified fatal exception: " . $e->getMessage() . "\nException occurs in file " . $e->getFile() . " on line " . $e->getLine() . "\n";
}
