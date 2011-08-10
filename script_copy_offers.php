<?php

include(dirname(__FILE__) . "/c_header.php");

try
{
	$names = array("sysname");

	foreach($names as $name)
	{
		$rws = DB::q("select * from " . $name . "s");
		foreach($rws as $rw)
				$GLOBALS[$name . "s"][$rw["name"]] = $rw["id"];
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
	foreach($rws as $rw)
	{
		foreach($names as $name)
		{
			$rw[$name . "_id"] = $GLOBALS[$name . "s"][$rw[$name]];
			unset($rw[$name]);
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

		$str = $fields_str = "";
		foreach($rw_insert as $name => $value)
		{
			$str .= ($str?",":"") . ":" . $name;
			$fields_str .= ($fields_str?",":"") . "`" . $name . "`";
		}

		DB::q("insert into offers(" . $fields_str . ") values(" . $str . ")",$rw_insert);
	}
}
catch (Exception $e)
{
	echo "Unspecified fatal exception: " . $e->getMessage() . "\nException occurs in file " . $e->getFile() . " on line " . $e->getLine() . "\n";
}
