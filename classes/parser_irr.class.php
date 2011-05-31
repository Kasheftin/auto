<?php

class ParserIrr extends Parser
{
	protected $opts = array(
		"host" => "irr.ru",
		"period" => 432000,
		"regions" => array("perm","ufa","chelyabinsk","tyumen","ekaterinburg"),
		"max_repeat" => 15,
	);

	public function initializeState()
	{
		foreach($this->opts["regions"] as $region)
		{
			$url = "http://" . $region . "." . $this->opts["host"] . "/cars/passenger/used/search/sort/date_create:desc/";
			$this->addToState(array("url"=>$url,"processed"=>0,"base_url"=>$url,"region"=>$region));
		}

		return $this;
	}

	public function parseOffer($rw,$str)
	{
		$data = $raw_data = array();

		if (preg_match("/<title[^<>]*>301 Moved Permanently/i",$str))
			return array("data"=>array("status"=>2),"success"=>"Offer not found");

		//$data["raw_html"] = $str;

		$this->pp	-> reset()
				-> set($str)
				-> b()
					-> find("/<div[^<>]*class\s*=\s*[\"']?w-title[^<>]*>/","/<strong[^<>]*>/","/<\/strong[^<>]*>/")
					-> b()
						-> find("/(\d+)\s+г.в./")
						-> save($data["production_year"],null,1)
					-> e()
					-> b()
						-> find("/([\d\.,]+\s+куб)/")
						-> save($data["engine"],null,1)
					-> e()
				-> e()
				-> b()
					-> DOMfind("/<table[^<>]*class\s*=\s*[\"']?customfields[^<>]*>/","/<\/table[^<>]*>/","/<table[^<>]*>/")
					-> DOMfindAll("/<tr>/","/<\/tr>/","/<tr[^<>]*>/")
					-> each()
						-> split("/<\/td[^<>]*>/")
						-> b()
							-> selBI(0)
							-> replace("/:/","")
							-> save($raw_data["info"]["param_name"])
						-> e()
						-> b()
							-> selBI(1)
							-> save($raw_data["info"]["param_value"])
						-> e()
					-> endEach()
				-> e()
				-> b()
					-> DOMfind("/<div[^<>]*class\s*=\s*[\"']?wysiwyg[^<>]*>/","/<\/div>/","/<div[^<>]*>/")
					-> save($data[details],null,1)
				-> e()
				-> b()
					-> DOMfind("/<div[^<>]*contacts-info[^<>]*>/","/<\/div[^<>]*>/","/<div[^<>]*>/")
					-> b()
						-> find("/<p[^<>]*class\s*=\s*[\"']?padsmall[^<>]*>/","/<\/p[^<>]*>/")
						-> save($data["contact_person"],null,1)
					-> e()
					-> b()
						-> findAll("/<li[^<>]*class\s*=\s*[\"']?ico-(m)?phone[^<>]*>/","/<\/li[^<>]*>/")
						-> save($data["phones"])
					-> e()
				-> e();

		foreach($raw_data["info"]["param_name"] as $i => $ar)
		{
			$p_n = $raw_data["info"]["param_name"][$i][0];
			$p_v = $raw_data["info"]["param_value"][$i][0];
			if (!$p_n || !$p_v) continue;
			$data["info"][$p_n] = $p_v;
		}

		if ($data["phones"][0])
			$data["phone"] = $this->preparePhone($data["phones"][0]);
		if ($data["phones"][1])
			$data["phone2"] = $this->preparePhone($data["phones"][1]);


		if ($data["info"]["Тип двигателя"])
			$data["engine_type"] = $data["info"]["Тип двигателя"];
		if ($data["info"]["Привод"])
			$data["drive"] = $data["info"]["Привод"];
		if ($data["info"]["Тип кузова"])
			$data["body_type"] = $data["info"]["Тип кузова"];
		if ($data["info"]["Руль"] == "правый")
			$data["right_steering_wheel"] = 1;

		if ($data["info"]["Состояние"] == "плохое" || $data["info"]["состояние"] == "на запчасти")
			$data["crashed"] = 1;

		return array("data"=>$data,"success"=>"Offer $rw[id] has been parsed");
	}

	public function parseOffers($obj,$str)
	{
		$data = $pages = array();

		$this->pp	-> reset()
				-> set($str)
				-> b()
					-> DOMfind("/<div[^<>]*class\s*=\s*[\"']?filter-pages[^<>]*>/","/<\/div>/","/<div[^<>]*>/")
					-> findAll("/<a[^<>]*>\s*(\d+)\s*<\/a[^<>]*>/")
					-> save($pages)
				-> e()
				-> DOMfind("/<table[^<>]*id\s*=\s*[\"']?adListTable[^<>]*>/","/<\/table[^<>]*>/","/<table[^<>]*>/")
				-> DOMfindAll("/<tr[^<>]*id\s*=\s*[\"']?(\d+)[\"']?[^<>]*>/","/<\/tr[^<>]*>/","/<tr[^<>]*>/")
				-> each()
					-> split("/<\/td>/")
					-> b()
						-> selBI(0)
						-> find("/toggleFav\((\d+),/")
						-> save($data["source_id"])
					-> e()
					-> b()
						-> selBI(2)
						-> find("/<span[^<>]*class\s*=\s*[\"']?RUR[^<>]*>([^<>]*)<\/span[^<>]*>/")
						-> replace("/[^\d]/","")
						-> save($data["price_rub"])
					-> e()
					-> b()
						-> selBI(3)
						-> find("/<img[^<>]*src\s*=\s*[\"']?([^<>\"']*)[\"']?[^<>]*>/")
						-> save($data["photo_url"])
					-> e()
					-> b()
						-> selBI(4)
						-> replace("/<[^<>]*>/","")
/*
						-> b()
							-> find("/^([^\,]+)/")
							-> save($data["markmodel"])
						-> e()
*/
						-> b()
							-> find("/([\d\.,]+\s*куб)/")
							-> save($data["engine"])
						-> e()
						-> b()
							-> find("/пробег:\s*(\d+)/")
							-> save($data["run"])
						-> e()
						-> b()
							-> find("/пробег:.*?,\s*([^,]+)/")
							-> save($data["transmission"])
						-> e()
					-> e()
					-> b()
						-> selBI(5)
						-> save($data["mark"])
					-> e()
					-> b()
						-> selBI(6)
						-> save($data["model"])
					-> e()
					-> b()
						-> selBI(7)
						-> save($data["date_raw"])
					-> e()
					-> b()
						-> selBI(8)
						-> split("/<br[^<>]*>/",2,0)
						-> save($data["city"])
					-> e()
				-> endEach();

		$tmp_data = $data;
		$data = array();

		foreach($tmp_data["source_id"] as $i => $source_rw)
		{
			if (!$source_rw[0]) continue;
			foreach($tmp_data as $field_name => $rws)
				$data[$i][$field_name] = $rws[$i][0];

			$data[$i]["source_url"] = "http://" . $obj["region"] . "." . $this->opts["host"] . "/advert/print/" . $data[$i]["source_id"] . "/";

			if ($data[$i]["photo_url"])
				$data[$i]["photo_exists"] = 1;

			$data[$i]["markmodel"] = $data[$i]["mark"] . " " . $data[$i]["model"];
		}

		$urls = array();
		foreach($pages as $i)
		{
			$tobj = $obj;
			$tobj["url"] = preg_replace("/\/search\//","/page" . $i . "/",$tobj["base_url"]);
			$urls[] = $tobj;
		}

		return array("data"=>$data,"urls"=>$urls,"success"=>"Page loaded");
	}
}


