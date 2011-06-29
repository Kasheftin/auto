<?php

include(dirname(__FILE__) . "/c_header.php");

try
{
	$compare_fields = array("brand_id","model_id","production_year","phone");

	$rws = DB::q("select * from offers");
	foreach($rws as $rw)
	{
		$str = "";
		$rw_search = array();

		foreach($compare_fields as $field)
		{
			$str .= ($str?" and ":"") . $field . "=:" . $field;
			$rw_search[$field] = $rw[$field];
		}

		$ids = "";
		$rws2 = DB::f("select id from offers where " . $str . " order by id",$rw_search);
		foreach($rws2 as $rw2)
			if ($rw2["id"] != $rw["id"])
				$ids .= ($ids?",":"") . $rw2["id"];

		if ($ids != $rw["cluster_ids"])
		{
			echo $rw["id"] . " - " . $ids . "\n";
			DB::q("update offers set cluster_ids=:cluster_ids where id=:id",array("cluster_ids"=>$ids,"id"=>$rw["id"]));
		}
	}
}
catch (Exception $e)
{
	echo "Unspecified fatal exception: " . $e->getMessage() . "\nException occurs in file " . $e->getFile() . " on line " . $e->getLine() . "\n";
}
