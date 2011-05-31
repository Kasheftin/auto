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

	$marks_by_name = $models_by_name = array();

	$rws = DB::q("select * from offers_marks");
	foreach($rws as $rw)
		$marks_by_name[$rw["name"]] = $rw["id"];

	$rws = DB::q("select * from offers_models");
	foreach($rws as $rw)
		$models_by_name[$rw["mark_id"]][$rw["name"]] = $rw["id"];

	$rws = DB::q("select * from offers where mark_id=0 or model_id=0");
	foreach($rws as $rw)
	{
		if (!$rw["mark"] || !$rw["markmodel"]) continue;
		if ($rw["mark_id"] == 0)
		{
			$rw["mark_id"] = (int)$marks_by_name[$rw["mark"]];
			if (!$rw["mark_id"])
			{
				$rw["mark_id"] = DB::q("insert into offers_marks(`name`) values(:name)",array("name"=>$rw["mark"]));
				$marks_by_name[$rw["mark"]] = $rw["mark_id"];
			}
		}

		$rw["model"] = trim(preg_replace("/^\s*" . $rw["mark"] . "\s*/","",$rw["markmodel"]));
		if (!$rw["model"]) continue;
		
		if ($rw["model_id"] == 0)
		{
			$rw["model_id"] = (int)$models_by_name[$rw["mark_id"]][$rw["model"]];
			if (!$rw["model_id"])
			{
				$rw["model_id"] = DB::q("insert into offers_models(`mark_id`,`name`) values(:mark_id,:name)",array("mark_id"=>$rw["mark_id"],"name"=>$rw["model"]));
				$models_by_name[$rw["mark_id"]][$rw["model"]] = $rw["model_id"];
			}
		}

		if ($rw["mark_id"] && $rw["model_id"] && $rw["model"])
		{
			DB::q("update offers set mark_id=:mark_id,model_id=:model_id,model=:model where id=:id",array("id"=>$rw["id"],"mark_id"=>$rw["mark_id"],"model_id"=>$rw["model_id"],"model"=>$rw["model"]));
		}
	}
}
catch (Exception $e)
{
	echo "Unspecified fatal exception: " . $e->getMessage() . "\nException occurs in file " . $e->getFile() . " on line " . $e->getLine() . "\n";
}
