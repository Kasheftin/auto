<?php

class ParserAutochel extends Parser
{
	protected $opts = array(
		"host" => "autochel.ru",
		"pages_url" => "/car/search/motors/foreign/1.php?&order=DateUpdate&dir=desc",
		"period_days" => 31,
	);

	public function initializeState()
	{
		$this->state["cities"] = $this->loadCities();

		foreach($this->state["cities"] as $citycode)
		{
			$url = $this->opts["pages_url"] . "&period=" . $this->opts["period_days"] . "&list_col_pp=200&citycode=" . $citycode;
			$this->addToState(array("url"=>$url,"processed"=>0,"base_url"=>$url,"cookies"=>array("list_col_pp"=>200)));
		}

		return $this;
	}

	public function parseOffer($rw,$str)
	{
		$data = $raw_data = array();

		if (preg_match("/<b[^<>]*class\s*=\s*[\"']?hot[\"']?[^<>]*>Объявление не найдено/i",$str))
			return array("data"=>array("status"=>2),"success"=>"Offer not found");

		$data["raw_html"] = $str;

		$this->pp	-> reset()
				-> set($str)
				-> b()
					-> find("/<table[^<>]*>/","/<a[^<>]*>/","/<\/a[^<>]*>/")
					-> save($data["mark"],null,1)
				-> e()
				-> b()
					-> DOMFind("/<div[^<>]*class\s*=\s*[\"']?details\_fields[\"']?[^<>]*>/","/<\/div[^<>]*>/","/<div[^<>]*>/")
					-> DOMfindAll("/<div[^<>]*>/","/<\/div>/","/<div[^<>]*>/")
					-> each()
						-> split("/<\/span[^<>]*>/")
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
				-> split("/<div[^<>]*class\s*=\s*[\"']?details\_fields[\"']?[^<>]*>/",2,1)
				-> split("/<br[^<>]*>/",2,1)
				-> b()
					-> split("/<p[^<>]*class\s*=\s*[\"']?zag6[\"']?[^<>]*>\s*Комплектация/",2,1)
					-> DOMFind("/<table[^<>]*>/","/<\/table[^<>]*>/","/<table[^<>]*>/")
					-> DOMFindAll("/<div[^<>]*class\s*=\s*[\"']?options\_container[\"']?[^<>]*>/","/<\/div[^<>]*>/","/<div[^<>]*>/")
					-> each()
						-> b()
							-> find("/<span[^<>]*class\s*=\s*[\"']?options\_zag[\"']?[^<>]*>/","/<\/span[^<>]*>/")
							-> replace("/:/","")
							-> save($raw_data["package"]["param_name"])
						-> e()
						-> split("/<br[^<>]*>/")
						-> rmBI(0)
						-> save($raw_data["package"]["param_value"])
					-> endEach()
				-> e()
				-> b()
					-> split("/<(p|span)[^<>]*class\s*=\s*[\"']?zag6[\"']?[^<>]*>\s*Дополнительная информация/",2,1)
					-> DOMFind("/<p[^<>]*>/","/<\/p[^<>]*>/","/<p[^<>]*>/")
					-> save($data["details"],null,1)
				-> e()
				-> b()
					-> split("/<(p|span)[^<>]*class\s*=\s*[\"']?zag6[\"']?[^<>]*>\s*Контактные данные/",2,1)
					-> DOMFind("/<table[^<>]*>/","/<\/table[^<>]*>/","/<table[^<>]*>/")
					-> findAll("/(<span[^<>]*class\s*=\s*[\"']?auto\_field\_caption[\"']?[^<>]*>[^<>]*<\/span[^<>]*>[^<>]*<\/p[^<>]*>)/")
					-> each()
						-> split("/<\/span[^<>]*>/")
						-> b()
							-> selBI(0)
							-> replace("/:/","")
							-> save($raw_data["contacts"]["param_name"])
						-> e()
						-> b()
							-> selBI(1)
							-> save($raw_data["contacts"]["param_value"])
						-> e()
					-> endEach()
				-> e();

		foreach($raw_data["info"]["param_name"] as $i => $ar)
		{
			$p_n = $raw_data["info"]["param_name"][$i][0];
			$p_v = $raw_data["info"]["param_value"][$i][0];
			if (!$p_n || !$p_v) continue;
			$data["info"][$p_n] = $p_v;
		}
		foreach($raw_data["contacts"]["param_name"] as $i =>$ar)
		{
			$p_n = $raw_data["contacts"]["param_name"][$i][0];
			$p_v = $raw_data["contacts"]["param_value"][$i][0];
			if (!$p_n || !$p_v) continue;
			$data["contacts"][$p_n] = $p_v;
		}
		foreach($raw_data["package"]["param_name"] as $i => $ar)
		{
			$p_n = $raw_data["package"]["param_name"][$i][0];
			$p_v = $raw_data["package"]["param_value"][$i];
			$p_vv = array();
			foreach($p_v as $ii => $vv)
				if ($ii && $vv)
					$p_vv[$ii] = $vv;
			if (!$p_n || !$p_vv) continue;
			$data["package"][$p_n] = $p_vv;
		}

		$data["drive"] = $data["info"]["Привод"];
		$data["contact_person"] = $data["contacts"]["Контактное лицо"];

		$phone_raw = $data["contacts"]["Телефон"];
		$ar = explode(",",$phone_raw,2);
		$phone_raw = trim($ar[0]);
		$phone_raw = preg_replace("/+7/","",$phone_raw);
		$phone_raw = preg_replace("/[^\d]/","",$phone_raw);
		$phone_raw = preg_replace("/^8/","",$phone_raw);
		$data["phone"] = $phone_raw;

		return array("data"=>$data,"success"=>"Offer $rw[id] has been parsed");
	}


	protected function loadCities()
	{
		echo __METHOD__ . ": Start loading cities url\n";
		$this->req	-> b()
					-> set(array(
						"protocol" => "POST",
						"url" => "/service/source/db.location_inline",
						"accept" => "application/json, text/javascript, */*",
						"content-type" => "application/x-www-form-urlencoded; charset=UTF-8",
						"x-requested-with" => "XMLHttpRequest",
					))
					-> req(array(
						"level" => 4,
						"parent" => "0010010220000000000000",
						"impotant" => 1,
						"limit" => "null",
						"type_in" => "2,2,10,24",
						"type" => "subordinate_objects",
						"tree" => 0,
					))
					-> saveContent($str)
				-> e();
		
		$this->pp	-> reset()
				-> set($str)
				-> findAll("/[\"']id[\"']\s*:\s*[\"'](\d+)[\"']/")
				-> save($data);

		return $data;
	}


	public function parseOffers($obj,$str)
	{
		$data = $pages = array();

		$this->pp	-> reset()
				-> set($str)
				-> b()
					-> DOMfind("/<div[^<>]*class\s*=\s*[\"']?pageslink[\"']?[^<>]*>/","/<\/div>/","/<div[^<>]*>/")
					-> findAll("/\/(\d+)\.php/")
					-> save($pages)
				-> e()
				-> DOMfind("/<table[^<>]*class\s*=\s*[\"']?adv\_list[\"']?[^<>]*>/","/<\/table[^<>]*>/","/<table[^<>]*>/")
				-> DOMfindAll("/<tr[^<>]*id\s*=\s*[\"']?row\d+[^<>]*>/","/<\/tr[^<>]*>/","/<tr[^<>]*>/")
				-> each()
					-> split("/<\/td>/")
					-> b()
						-> selBI(0)
						-> b()
							-> find("/<a[^<>]*href\s*=\s*[\"']?([^<>\"']*)[\"']?[^<>]*>/")
							-> find("/\/details\/(.*?)\.php/")
							-> save($data["source_id"])
						-> e()
						-> replace("/<[^<>]*>/","")
						-> replace("/[^\d]/","")
						-> save($data["price_rub"])
					-> e()
					-> b()
						-> selBI(1)
						-> save($data["markmodel"])
					-> e()
					-> b()
						-> selBI(2)
						-> replace("/<[^<>]*>/","")
						-> replace("/[^\d]/","")
						-> save($data["production_year"])
					-> e()
					-> b()
						-> selBI(3)
						-> replace("/<[^<>]*>/","")
						-> replace("/[^\d]/","")
						-> save($data["run"])
					-> e()
					-> b()
						-> selBI(4)
						-> find("/title\s*=\s*[\"']?([^<>\"']*)[\"']?/")
						-> split("/,/")
						-> b()
							-> selBI(0)
							-> save($data["color"])
						-> e()
						-> b()
							-> selBI(1)
							-> save($data["body_type"])
						-> e()
					-> e()
					-> b()
						-> selBI(5)
						-> find("/<img[^<>]*src\s*=\s*[\"']?([^<>\"']*)[\"']?[^<>]*>/")
						-> save($data["photo_url"])
					-> e()
					-> b()
						-> selBI(6)
						-> find("/rudder\_right/")
						-> replace("/rudder\_right/",1)
						-> save($data["right_steering_wheel"])
					-> e()
					-> b()
						-> selBI(7)
						-> b()
							-> find("/<font[^<>]*>/","/<\/font[^<>]*>/")
							-> replace("/\//","")
							-> save($data["engine"])
						-> e()
						-> b()
							-> find("/<span[^<>]*>/","/<\/span[^<>]*>/")
							-> save($data["engine_type"])
						-> e()
					-> e()
					-> b()
						-> selBI(8)
						-> split("/<br[^<>]*>/",2,1)
						-> save($data["date_raw"])
					-> e()
					-> b()
						-> selBI(9)
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
			$data[$i]["source_url"] = "/car/motors/foreign/details/" . $data[$i]["source_id"] . ".php";

			if (preg_match("/^\d/",$data[$i]["date_raw"]))
				$data[$i]["dt_published"] = $this->parseDate($data[$i]["date_raw"]);

			if ($data[$i]["photo_url"])
				$data[$i]["photo_exists"] = 1;
		}

		$urls = array();
		foreach($pages as $i)
		{
			$tobj = $obj;
			$tobj["url"] = preg_replace("/\d+\.php/",$i . ".php",$tobj["base_url"]);
			$urls[] = $tobj;
		}

		return array("data"=>$data,"urls"=>$urls,"success"=>"Page loaded");
	}
}


