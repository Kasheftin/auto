<?php

include(dirname(__FILE__) . "/c_header.php");

try
{
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
		}
//		else DEBUG::log("region_id not found",$rw,"MAJOR");
		else echo "region_id not found, $rw[id] $rw[sysname] $rw[city] $rw[source_url]\n";
	}
}
catch (Exception $e)
{
	echo "Unspecified fatal exception: " . $e->getMessage() . "\nException occurs in file " . $e->getFile() . " on line " . $e->getLine() . "\n";
}
