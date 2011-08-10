<?php

include(dirname(__FILE__) . "/c_header.php");

try
{
	$cities_by_names = array();
	$rws = DB::q("select * from cities");
	foreach($rws as $rw)
	{
		$cities_by_names[$rw["name"]] = $rw["id"];
	}
	$rws = DB::q("select id,city from source_offers where status=1 and city_id=0");
	foreach($rws as $rw)
	{
		$rw["city"] = trim(preg_replace("/\[.*$/","",$rw["city"]));
		$rw["city_id"] = $cities_by_names[$rw["city"]];
		if (!$rw["city_id"])
		{
			$rw["city_id"] = DB::q("insert into cities(`name`) values(:name)",array("name"=>$rw["city"]));
		}
		if ($rw["city_id"])
		{
			$cities_by_names[$rw["city"]] = $rw["city_id"];
			DB::q("update source_offers set city_id=:city_id where id=:id",array("city_id"=>$rw["city_id"],"id"=>$rw["id"]));
		}
	}
}
catch (Exception $e)
{
	echo "Unspecified fatal exception: " . $e->getMessage() . "\nException occurs in file " . $e->getFile() . " on line " . $e->getLine() . "\n";
}
