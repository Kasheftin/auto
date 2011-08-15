<?php

include(dirname(__FILE__) . "/c_header.php");

// Скрипт копирует офферы из source_offers в offers.
// Берет source_offers, NOW() - dt_last_found < 86400, которые готовы для загрузки (скрипты заполнили все необходимые параметры).
// Загружает в offers, делает update для существующих и insert для новых.
// Мы обновляем именно офферы за последний день, а не с id>max_id in offers, потому что в середине базы могут существовать офферы, которые еще не прогнались через нужные скрипты.

try
{
	$names = array("sysname");

	foreach($names as $name)
	{
		$rws = DB::q("select * from " . $name . "s");
		foreach($rws as $rw)
				$GLOBALS[$name . "s"][$rw["name"]] = $rw["id"];
	}

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

	$rws = DB::q("select * from source_offers where status=1 and patterns_status=1 and mark_id>0 and model_id>0 and region_id>0 and city_id>0 and updated_at!='' and created_at!='' and dt_last_found>" . time() . "-86400");
	foreach($rws as $rw)
	{	
		$rw2 = DB::f1("select id,updated_at from offers where id=:id limit 0,1",array("id"=>$rw["id"]));
		if ($rw2["updated_at"] == $rw["updated_at"])
		{
			DEBUG::log($rw["id"] . " - already updated","MAJOR");
			continue;
		}
		if ($rw2["id"] != $rw["id"])
		{
			$offer_id = DB::q("insert into offers(`id`) values(:id)",array("id"=>$rw["id"]));
			if (!$offer_id)
			{
				DEBUG::log("Failed inserting row to offers",$rw,"ERROR");
				continue;
			}
		}

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

		$str_insert = $str_update = $fields_str = "";
		foreach($rw_insert as $name => $value)
		{
			$str_insert .= ($str_insert?",":"") . ":" . $name;
			$fields_str .= ($fields_str?",":"") . "`" . $name . "`";
			if ($name != "id")
				$str_update .= ($str_update?",":"") . "`" . $name . "`=:" . $name;
		}

		DB::q("update offers set " . $str_update . " where id=:id",$rw_insert);
	}
}
catch (Exception $e)
{
	echo "Unspecified fatal exception: " . $e->getMessage() . "\nException occurs in file " . $e->getFile() . " on line " . $e->getLine() . "\n";
}
