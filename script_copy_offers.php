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

	$names = array("body_type","drive","engine_type","transmission");

	foreach($names as $name)
		$GLOBALS["offers_" . $name . "s"] = DB::f("select * from offers_" . $name . "s");

	DB::q("truncate table offers_actual");

	$rws = DB::q("select * from offers where status=1 and patterns_status=1 and dt_last_found>" . time() . "-86400*2");
	foreach($rws as $rw)
	{
		foreach($names as $name)
		{
			$rw[$name] = $GLOBALS["offers_" . $name . "s"][$rw[$name . "_id"]]["name"];
			unset($rw[$name . "_id"]);
			if (!$rw[$name]) $rw[$name] = "";
		}

		unset($rw["status"]);
		unset($rw["patterns_status"]);
		unset($rw["raw_html"]);
		unset($rw["mark"]);
		unset($rw["model"]);
		unset($rw["markmodel"]);

		foreach($rw as $name => $value)
			if (preg_match("/^\d+$/",$name))
				unset($rw[$name]);


		$str = "";
		foreach($rw as $name => $value)
			$str .= ($str?",":"") . ":" . $name;

		DB::q("insert into offers_actual values(" . $str . ")",$rw);
	}
}
catch (Exception $e)
{
	echo "Unspecified fatal exception: " . $e->getMessage() . "\nException occurs in file " . $e->getFile() . " on line " . $e->getLine() . "\n";
}
