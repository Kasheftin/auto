<?php

class ParserAuto extends Parser
{
	protected $opts = array(
		"host" => "cars.auto.ru",
		"pages_url" => "/list/?category_id=15&section_id=1&mark_id=0&currency_key=RUR&country_id=1&sort_by=2&output_format=1&submit=1",
		"regions" => array(47,5,65,56,70),
	);

	public function initializeState()
	{
		$this->state["marks"] = $this->loadMarks();

		$url = $this->opts["pages_url"];
		if (is_array($this->opts["regions"]))
		{
			foreach($this->opts["regions"] as $region_id)
				$url .= "&region[]=" . $region_id;
			$url .= "&region_id=" . reset($this->opts["regions"]);
		}
		$this->addToState(array("url"=>$url,"processed"=>0,"base_url"=>$url));

		return $this;
	}

	public function parseOffer($rw,$str)
	{
		$data = $raw_data = array();

		if (preg_match("/<title[^<>]*>Ошибка 404/i",$str))
			return array("data"=>array("status"=>2),"success"=>"Offer not found");

		$data["raw_html"] = $str;

		$this->pp	-> reset()
				-> set($str)
				-> b()
					-> DOMFind("/<dl[^<>]*class\s*=\s*[\"']?sale-info[\"']?[^<>]*>/","/<\/dl[^<>]*>/","/<dl[^<>]*>/")
					-> split("/<\/dd[^<>]*>/")
					-> each()
						-> b()
							-> find("/<dt[^<>]*>([\s\S]*?)<\/dt[^<>]*>/")
							-> replace("/:/","")
							-> save($raw_data["info"]["param_name"])
						-> e()
						-> b()
							-> split("/<dd[^<>]*>/",2,1)
							-> save($raw_data["info"]["param_value"])
						-> e()
					-> endEach()
				-> e()
				-> b()
					-> DOMFind("/<div[^<>]*id\s*=\s*[\"']?sale-package[\"']?[^<>]*>/","/<\/div[^<>]*>/","/<div[^<>]*>/")
					-> findAll("/<li[^<>]*>([\s\S]*?)<\/li[^<>]*>/")
					-> save($data["package"])
				-> e()
				-> b()
					-> find("/<div[^<>]*id\s*=\s*[\"']?sale-details[\"']?[^<>]*>\s*<h3[^<>]*>\s*Дополнительная информация[^<>]*<\/h3[^<>]*>/","/<\/div[^<>]*>/")
					-> save($data["details"],null,1)
				-> e()
				-> b()
					-> find("/<div[^<>]*id\s*=\s*[\"']?sale-details[\"']?[^<>]*>\s*<h3[^<>]*>\s*Место осмотра[^<>]*<\/h3[^<>]*>/","/<\/div[^<>]*>/")
					-> save($data["details_where"],null,1)
				-> e()
				-> b()
					-> DOMFind("/<div[^<>]*id\s*=\s*[\"']?sale-contact[\"']?[^<>]*>/","/<\/div[^<>]*>/","/<div[^<>]*>/")
					-> replace("/<h3[^<>]*>[\s\S]*?<\/h3[^<>]*>/","")
					-> split("/<dl[^<>]*>/",2,1)
					-> split("/<\/dd[^<>]*>/")
					-> each()
						-> b()
							-> find("/<dt[^<>]*>([\s\S]*?)<\/dt[^<>]*>/")
							-> replace("/:/","")
							-> save($raw_data["contacts"]["param_name"])
						-> e()
						-> b()
							-> split("/<dd[^<>]*>/",2,1)
							-> save($raw_data["contacts"]["param_value"])
						-> e()
					-> endEach()
				-> e()
				-> b()
					-> DOMFind("/<div[^<>]*id\s*=\s*[\"']?sale-photo[\"']?[^<>]*>/","/<\/div[^<>]*>/","/<div[^<>]*>/")
					-> find("/<img[^<>]*src\s*=\s*[\"']?([^<>\"']*)[\"']?[^<>]*>/")
					-> save($data["photo_url"],null,1)
				-> e();

		if (preg_match("/i-no-photo/",$data["photo_url"]))
			$data["photo_url"]="";

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

		$data["drive"] = $data["info"]["Привод"];
		if (!preg_match("/не указан/",$data["info"]["VIN"]))
			$data["vin"] = $data["info"]["VIN"];
		$data["contact_person"] = $data["contacts"]["Контактное лицо"];
		if (preg_match("/\(\d+\)[\d\s-]+/",$data["contacts"]["Телефон"],$m))
			$data["phone"] = preg_replace("/[^\d]/","",$m[0]);

		unset($data["contacts"]["E-mail"]);

		return array("data"=>$data,"success"=>"Offer $rw[id] has been parsed");
	}


	protected function loadMarks()
	{
		echo __METHOD__ . ": Start loading root url\n";
		$this->req	-> set("url","/")
				-> req()
				-> saveContent($str);

		$this->pp	-> reset()
				-> set($str)
				-> findAll("/<table[^<>]*class\s*=\s*[\"']?list[\"']?[^<>]*>/","/<\/table[^<>]*>/")
				-> findAll("/<div[^<>]*class\s*=\s*[\"']?cell-1[\"']?[^<>]*>/","/<\/div>/")
				-> find("/<a[^<>]*>/","/<\/a[^<>]*>/")
				-> rm("/^$/")
				-> save($data);
		return $data;
	}


	protected function findMark($str)
	{
		$str = trim($str);
		$ar = explode(" ",$str);
		for ($i = 0; $i < count($ar); $i++)
		{
			$substr = "";
			for ($j = 0; $j <= $i; $j++)
				$substr .= $ar[$j];
			foreach($this->state["marks"] as $mark)
				if ($substr == $mark)
					return $mark;
		}
		return null;
	}


	public function parseOffers($obj,$str)
	{
		$data = $pages = array();

		$this->pp	-> reset()
				-> set($str)
				-> b()
					-> DOMfind("/<div[^<>]*class\s*=\s*[\"']?container[\"']?[^<>]*>/","/<\/div>/","/<div[^<>]*>/")
					-> findAll("/<a[^<>]*>\s*(\d+)\s*<\/a[^<>]*>/")
					-> save($pages)
				-> e()
				-> find("/<table[^<>]*class\s*=\s*[\"']?list[\"']?[^<>]*>/","/<\/table[^<>]*>/")
				-> split("/<\/tr[^<>]*>/")
				-> rmBI(0)
				-> each()
					-> split("/<\/td>/")
					-> b()
						-> selBI(0)
						-> b()
							-> find("/<a[^<>]*href\s*=\s*[\"']?([^<>\"']*)[\"']?[^<>]*>/")
							-> b()
								-> find("/\/sale\/(.*?)\.html/")
								-> save($data["source_id"])
							-> e()
							-> save($data["source_url"])
						-> e()
						-> save($data["markmodel"],"trim")
					-> e()
					-> b()
						-> selBI(1)
						-> replace("/<[^<>]*>/","")
						-> replace("/[^0-9]/","")
						-> save($data["price_rub"])
					-> e()
					-> b()
						-> selBI(2)
						-> replace("/<[^<>]*>/","")
						-> replace("/[^0-9]/","")
						-> save($data["production_year"])
					-> e()
					-> b()
						-> selBI(3)
						-> save($data["engine"])
					-> e()
					-> b()
						-> selBI(4)
						-> find("/title\s*=\s*[\"']?([^<>\"']*)[\"']?/")
						-> save($data["engine_type"])
					-> e()
					-> b()
						-> selBI(4)
						-> find("/\/(rul)\.gif/")
						-> replace("/rul/",1)
						-> save($data["right_steering_wheel"])
					-> e()
					-> b()
						-> selBI(5)
						-> replace("/<[^<>]*>/","")
						-> replace("/[^0-9]/","")
						-> save($data["run"])
					-> e()
					-> b()
						-> selBI(6)
						-> find("/(photo)\.gif/")
						-> replace("/photo/",1)
						-> save($data["photo_exists"])
					-> e()
					-> b()
						-> selBI(7)
						-> find("/title\s*=\s*[\"']?([^<>\"']*)[\"']?/")
						-> save($data["body_type"])
					-> e()
					-> b()
						-> selBI(8)
						-> find("/title\s*=\s*[\"']?([^<>\"']*)[\"']?/")
						-> save($data["color"])
					-> e()
					-> b()
						-> selBI(9)
						-> save($data["city"])
					-> e()
					-> b()
						-> selBI(10)
						-> find("/(pogran)\.gif/")
						-> replace("/pogran/",1)
						-> save($data["without_customs"])
					-> e()
					-> b()
						-> selBI(11)
						-> find("/(Есть)/")
						-> replace("/Есть/",1)
						-> save($data["available"])
					-> e()
				-> endEach();

		$tmp_data = $data;
		$data = array();
		foreach($tmp_data["source_id"] as $i => $source_rw)
		{
			if (!$source_rw[0]) continue;
			foreach($tmp_data as $field_name => $rws)
				$data[$i][$field_name] = $rws[$i][0];
			$data[$i]["mark"] = $this->findMark($data[$i]["markmodel"]);
			if (preg_match("/требует ремонта/",$data[$i]["body_type"]))
			{
				$data[$i]["crashed"] = 1;
				$data[$i]["body_type"] = trim(preg_replace("/требует ремонта/","",$data[$i]["body_type"]));
			}
		}

		$urls = array();
		foreach($pages as $i)
		{
			$tobj = $obj;
			$tobj["url"] = $tobj["base_url"] . "&_p=" . $i;
			$urls[] = $tobj;
		}

		return array("data"=>$data,"urls"=>$urls,"success"=>"Page loaded");
	}
}


