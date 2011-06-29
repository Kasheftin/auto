<?php

class ParserDrom extends Parser
{
	protected $opts = array(
		"host" => "drom.ru",
		"marks_host" => "auto.drom.ru",
		"period" => 432000,
		"regions" => array("perm","ufa","chelyabinsk","tyumen","ekaterinburg"),
	);

	public function initializeState()
	{
		$this->state["marks"] = $this->loadMarks();

		foreach($this->opts["regions"] as $region)
		{
			$url = "http://" . $region . "." . $this->opts["host"] . "/auto/?s_currency=1&inomarka=1&go_search=2";
			$this->addToState(array("url"=>$url,"processed"=>0,"base_url"=>$url));
		}

		return $this;
	}

	public function parseOffer($rw,$str)
	{
		$data = $raw_data = array();

		if (preg_match("/banned\.php/",$str))
		{
			echo "IP Adress banned!\n";
			die();
		}

		if (preg_match("/<title[^<>]*>301 Moved Permanently/i",$str))
			return array("data"=>array("status"=>2),"success"=>"Offer not found");

		$data["raw_html"] = $str;

		$this->pp	-> reset()
				-> set($str)
				-> b()
					-> find("/<div[^<>]*class\s*=\s*[\"']?path[\"']?[^<>]*>/","/<\/div[^<>]*>/")
					-> split("/Продажа автомобилей/",2,1)
					-> find("/<a[^<>]*>/","/<\/a[^<>]*>/")
// Move mark value to the pages-parser
//					-> save($data["mark"],null,1)
				-> e()
				-> find("/<div[^<>]*class\s*=\s*[\"']?price[\"']?[^<>]*>/","/<p[^<>]*id\s*=\s*[\"']?ajax\_error\_container[^<>]*>/")
				-> findAll("/<p[^<>]*>/","/<\/p[^<>]*>/")
				-> b()
					-> selBI(0)
					-> split("/<br[^<>]*>/")
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
				-> b()
					-> selBI(1)
					-> split("/<\/span[^<>]*>/",2,1)
					-> save($data["details"],null,1)
				-> e();

		foreach($raw_data["info"]["param_name"] as $i => $ar)
		{
			$p_n = $raw_data["info"]["param_name"][$i][0];
			$p_v = $raw_data["info"]["param_value"][$i][0];
			if (!$p_n || !$p_v) continue;
			$data["info"][$p_n] = $p_v;
		}

		if ($data["info"]["Руль"] == "правый")
			$data["right_steering_wheel"] = 1;

		$data["status"] = 1;

		return array("data"=>$data,"success"=>"Offer $rw[id] has been parsed");
	}

	protected function loadMarks()
	{
		echo __METHOD__ . ": Start loading root url\n";
		$this->req	-> set(array("host"=>$this->opts["marks_host"],"url"=>"/"))
				-> req()
				-> saveContent($str)
				-> set("host",$this->opts["host"]);

		$this->pp	-> reset()
				-> set($str)
				-> DOMfind("/<div[^<>]*class\s*=\s*[\"']?selectCars[^<>]*>/","/<\/div[^<>]*>/","/<div[^<>]*>/")
				-> findAll("/<a[^<>]*>/","/<\/a[^<>]*>/")
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
		if (preg_match("/banned\.php/",$str))
		{
			echo "IP Adress banned!\n";
			die();
		}

		$data = $pages = array();

		$this->pp	-> reset()
				-> set($str)
				-> b()
					-> DOMfind("/<div[^<>]*class\s*=\s*[\"']?bread\_bit[\"']?[^<>]*>/","/<\/div>/","/<div[^<>]*>/")
					-> findAll("/\/page(\d+)\//")
					-> save($pages)
				-> e()
				-> DOMfind("/<table[^<>]*class\s*=\s*[\"']?newCatList?[^<>]*>/","/<\/table[^<>]*>/","/<table[^<>]*>/")
				-> DOMfindAll("/<tr[^<>]*>/","/<\/tr[^<>]*>/","/<tr[^<>]*>/")
				-> rmBI(0)
				-> each()
					-> split("/<\/td>/")
					-> b()
						-> selBI(0)
						-> find("/<a[^<>]*href\s*=\s*[\"']?([^<>\"']*)[\"']?[^<>]*>/")
						-> save($data["source_url"])
						-> find("/\/(\d+)\.html/")
						-> save($data["source_id"])
					-> e()
					-> b()
						-> selBI(0)
						-> save($data["date_raw"])
					-> e()
					-> b()
						-> selBI(1)
						-> find("/<img[^<>]*src\s*=\s*[\"']?([^<>\"']*)[\"']?[^<>]*>/")
						-> save($data["photo_url"])
					-> e()
					-> b()
						-> selBI(2)
						-> save($data["markmodel"])
					-> e()
					-> b()
						-> selBI(3)
						-> save($data["production_year"])
					-> e()
					-> b()
						-> selBI(4)
						-> save($data["engine"])
					-> e()
					-> b()
						-> selBI(5)
						-> split("/<br[^<>]*>/")
						-> b()
							-> selBI(0)
							-> save($data["engine_type"])
						-> e()
						-> b()
							-> selBI(1)
							-> save($data["transmission"])
						-> e()
						-> b()
							-> selBI(2)
							-> save($data["drive"])
						-> e()
					-> e()
					-> b()
						-> selBI(6)
						-> split("/,/",2,0)
						-> save($data["run"])
					-> e()
					-> b()
						-> selBI(7)
						-> find("/(damaged)/")
						-> replace("/damaged/",1)
						-> save($data["crushed"])
					-> e()
					-> b()
						-> selBI(8)
						-> split("/<br[^<>]*>/")
						-> b()
							-> selBI(0)
							-> replace("/<[^<>]*>/","")
							-> replace("/[^\d]/","")
							-> save($data["price_rub"])
						-> e()
						-> b()
							-> selBI(1)
							-> save($data["city"])
						-> e()
					-> e()
				-> endEach();

		$tmp_data = $data;
		$data = array();
		$min_dt = time();

		foreach($tmp_data["source_id"] as $i => $source_rw)
		{
			if (!$source_rw[0]) continue;
			foreach($tmp_data as $field_name => $rws)
				$data[$i][$field_name] = $rws[$i][0];

			if (preg_match("/^(\d+)-(\d+)$/",$data[$i]["date_raw"],$m))
			{
				$data[$i]["dt_published"] = mktime(0,0,0,$m[2],$m[1],date("Y"));
				if ($min_dt > $data[$i]["dt_published"]) $min_dt = $data[$i]["dt_published"];
			}

			if ($data[$i]["markmodel"])
				$data[$i]["mark"] = $this->findMark($data[$i]["markmodel"]);

			if ($data[$i]["photo_url"])
				$data[$i]["photo_exists"] = 1;

			$data[$i]["print_source_url"] = $data[$i]["source_url"];
		}

		$urls = array();
		foreach($pages as $i)
		{
			$tobj = $obj;
			$tobj["url"] = preg_replace("/\/auto\/\?/","/auto/page" . $i . "/?",$tobj["base_url"]);
			$urls[] = $tobj;
		}

		return array("data"=>$data,"urls"=>$urls,"success"=>"Page loaded","min_dt"=>$min_dt);
	}
}


