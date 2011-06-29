<?php

include(dirname(__FILE__) . "/c_header.php");

try
{
	$names = array("body_type","drive","engine_type","transmission");

	foreach($names as $name)
	{
		$rws = DB::q("select * from offers_" . $name . "s");
		foreach($rws as $rw)
				$GLOBALS["offers_" . $name . "s"][$rw["id"]] = $rw;
	}

	DB::q("truncate table offers");

	$fields = array();
	$rws = DB::q("show columns from offers");
	foreach($rws as $rw)
		$fields[$rw["Field"]] = 1;

	$fields_map = array(
		"mark_id" => "brand_id",
		"year" => "production_year",
		"engine_volume" => "engine",
		"print_source_url" => "source_url",
	);

	$rws = DB::q("select * from source_offers where status=1 and patterns_status=1 and mark_id>0 and model_id>0 and region_id>0 and dt_last_found>" . time() . "-86400");
//	$rws = DB::q("select * from source_offers where id=3");
	foreach($rws as $rw)
	{
		foreach($names as $name)
		{
			$rw[$name] = $GLOBALS["offers_" . $name . "s"][$rw[$name . "_id"]]["name"];
			unset($rw[$name . "_id"]);
			if (!$rw[$name]) $rw[$name] = "";
		}

		if ($rw["without_customs"]) $rw["crashed"] = 1;

		$rw_insert = array();

		foreach($rw as $i => $v)
		{
			if (isset($fields_map[$i])) $i_to = $fields_map[$i];
			else $i_to = $i;

			if ($fields[$i_to])
				$rw_insert[$i_to] = $v;
		}

		$str = "";
		foreach($rw_insert as $name => $value)
			$str .= ($str?",":"") . ":" . $name;

		DB::q("insert into offers values(" . $str . ")",$rw_insert);
	}
}
catch (Exception $e)
{
	echo "Unspecified fatal exception: " . $e->getMessage() . "\nException occurs in file " . $e->getFile() . " on line " . $e->getLine() . "\n";
}
