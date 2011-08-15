<?php

include(dirname(__FILE__) . "/c_header.php");

try
{
	DEBUG::log("Apply patterns","MAJOR");

	$patterns = array();
	$rws = DB::f("select * from patterns");
	foreach($rws as $rw)
		$patterns[$rw["sysname"]][$rw["input_field"]][] = $rw;

	$rws = DB::q("select * from source_offers where patterns_status=0 and status=1");
	foreach($rws as $rw)
	{
		try
		{
			$rw_update = array("patterns_status"=>1);
			if (!$patterns[$rw["sysname"]]) continue;
			foreach($patterns[$rw["sysname"]] as $input_field => $p_rws)
			{
				$r_val = mb_strtolower($rw[$input_field]);

				if (!$r_val)
				{
					DEBUG::log("Required field " . $input_field . " is empty","ERROR");
					continue;
				}

				$output_value = 0;
				foreach($p_rws as $p_rw)
				{
					$p_val = mb_strtolower($p_rw["input_value"]);
					if ((($p_val == $r_val) && ($p_rw["type"] == "equal")) || (preg_match("/" . $p_val . "/",$r_val) && $p_rw["type"] == "match"))
					{
						$output_value = $p_rw["output_value"];
						break;
					}
				}

				if (!$output_value)
					throw new Exception("pattern not found for $input_field, value=" . $r_val);

				$rw_update[$input_field . "_id"] = $output_value;
			}

			$update_str = "";
			foreach($rw_update as $field => $value)
				$update_str .= ($update_str?",":"") . $field . "=:" . $field;

			$rw_update["id"] = $rw["id"];

			DB::q("update source_offers set " . $update_str . " where id=:id",$rw_update);
			DEBUG::log($rw["id"] . " - patterns updated","SUCCESS");
		}
		catch (Exception $e)
		{
			DEBUG::log($rw["id"] . " - " . $e->getMessage(),"ERROR");
		}
	}




	DEBUG::log("Fill regions","MAJOR");

	$regions_by_codes_num = $regions_by_codes = $regions_by_names = $regions_by_capitols = $regions_by_domains = array();
	$rws = DB::q("select * from regions");
	foreach($rws as $rw)
	{
		if (!$regions_by_codes[$rw["code"]]) $regions_by_codes[$rw["code"]] = $rw["id"];
		if (!$regions_by_codes_num[((int)$rw["code"])]) $regions_by_codes_num[((int)$rw["code"])] = $rw["id"];
		if (!$regions_by_names[$rw["name"]]) $regions_by_names[$rw["name"]] = $rw["id"];
		if (!$regions_by_capitols[$rw["capitol"]]) $regions_by_capitols[$rw["capitol"]] = $rw["id"];
	}

	$regions_tmp = array("perm"=>"Пермь","ufa"=>"Уфа","chelyabinsk"=>"Челябинск","tyumen"=>"Тюмень","ekaterinburg"=>"Екатеринбург");
	foreach($regions_tmp as $domain => $str)
		$regions_by_domains[$domain] = $regions_by_capitols[$str];

	$rws = DB::q("select * from source_offers where status=1 and region_id=0");
	foreach($rws as $rw)
	{
		if (!$rw["city"]) continue;

		$region_id = 0;

		switch ($rw["sysname"])
		{
			case "auto":
				if (preg_match("/\[(\d+)\]$/",$rw["city"],$m))
				{
					$region_id = $regions_by_codes[$m[1]];
					if (!$region_id)
						$region_id = $regions_by_codes_num[((int)$m[1])];
				}
				else
					$region_id = $regions_by_capitols[$rw["city"]];
				break;
			
			case "drom":
				if (preg_match("/^http:\/\/(.*?)\.drom\.ru/",$rw["source_url"],$m))
					$region_id = $regions_by_domains[$m[1]];
				break;

			case "irr":
				if (preg_match("/^http:\/\/(.*?)\.irr\.ru/",$rw["source_url"],$m))
					$region_id = $regions_by_domains[$m[1]];
				break;

			case "e1":
				if (in_array($rw["city"],array("Тюмень","Пермь","Челябинск","Екатеринбург")))
					$region_id = $regions_by_capitols[$rw["city"]];
				else
					$region_id = $regions_by_names[$rw["city"]];
				break;

			case "autochel":
				$region_id = $regions_by_capitols["Челябинск"];
				break;

			default: 
				throw new Exception("unknown sysname, " . print_r($rw,1));
		}

		if ($region_id)
		{
			DB::q("update source_offers set region_id=:region_id where id=:id",array("id"=>$rw["id"],"region_id"=>$region_id));
			DEBUG::log($rw["id"] . " - region " . $region_id . " isset","SUCCESS");
		}
		else DEBUG::log("region_id not found",$rw,"ERROR");
	}




	DEBUG::log("Fill cities","MAJOR");

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
			DEBUG::log($rw["id"] . " - city " . $rw["city_id"] . " isset","SUCCESS");
		}
		else 
			DEBUG::log($rw["id"] . " - city " . $rw["city"] . " failed to insert","ERROR");
	}




	DEBUG::log("Update marks and models","MAJOR");

	$marks_by_name = $models_by_name = array();

	$rws = DB::q("select * from brands");
	foreach($rws as $rw)
		$marks_by_name[$rw["name"]] = $rw["id"];

	$rws = DB::q("select * from models");
	foreach($rws as $rw)
		$models_by_name[$rw["brand_id"]][$rw["name"]] = $rw["id"];

	$rws = DB::q("select * from source_offers where status=1 and (mark_id=0 or model_id=0)");
	foreach($rws as $rw)
	{
		if (!$rw["mark"] || !$rw["markmodel"]) continue;
		if ($rw["mark_id"] == 0)
		{
			$rw["mark_id"] = (int)$marks_by_name[$rw["mark"]];
			if (!$rw["mark_id"])
			{
				$rw["mark_id"] = DB::q("insert into brands(`name`) values(:name)",array("name"=>$rw["mark"]));
				$marks_by_name[$rw["mark"]] = $rw["mark_id"];
			}
		}

		$rw["model"] = trim(mb_substr($rw["markmodel"],mb_strlen($rw["mark"])));

		if (!$rw["model"]) continue;
		
		if ($rw["model_id"] == 0)
		{
			$rw["model_id"] = (int)$models_by_name[$rw["mark_id"]][$rw["model"]];
			if (!$rw["model_id"])
			{
				$rw["model_id"] = DB::q("insert into models(`brand_id`,`name`) values(:mark_id,:name)",array("mark_id"=>$rw["mark_id"],"name"=>$rw["model"]));
				$models_by_name[$rw["mark_id"]][$rw["model"]] = $rw["model_id"];
			}
		}

		if ($rw["mark_id"] && $rw["model_id"] && $rw["model"])
		{
			DB::q("update source_offers set mark_id=:mark_id,model_id=:model_id,model=:model where id=:id",array("id"=>$rw["id"],"mark_id"=>$rw["mark_id"],"model_id"=>$rw["model_id"],"model"=>$rw["model"]));
			DEBUG::log($rw["id"] . " - mark and model updated, mark_id=" . $rw["mark_id"] . ", model_id=" . $rw["model_id"],"SUCCESS");
		}
		else
			DEBUG::log($rw["id"] . " - failed to update mark and model, " .$rw["mark"] . " " . $rw["model"],"ERROR");
	}




	DEBUG::log("Update times","MAJOR");
	DB::q("update source_offers set created_at=FROM_UNIXTIME(dt_added),updated_at=FROM_UNIXTIME(dt_last_found)");

}
catch (Exception $e)
{
	echo "Unspecified fatal exception: " . $e->getMessage() . "\nException occurs in file " . $e->getFile() . " on line " . $e->getLine() . "\n";
}

