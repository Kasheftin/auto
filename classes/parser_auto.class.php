<?php

class ParserAuto extends Parser
{
	protected $opts = array(
		"host" => "cars.auto.ru",
		"pages_url" => "/list/?category_id=15&section_id=1&mark_id=0&currency_key=RUR&country_id=1&sort_by=2&output_format=1&submit=1",
		"sleep_between_requests" => 1,
		"max_repeat" => 3,
		"regions" => array(47,5,65,56,70),
		"save_raw_html" => 0,
	);

	public function run()
	{
		if ($this->opts["mode"] == "offer")
		{
			if ($this->opts["offer_id"])
				$rws = DB::f("select * from offers where id=:id limit 0,1",array("id"=>$this->opts["offer_id"]));
			else
				$rws = DB::f("select * from offers where status=0");

			foreach($rws as $rw)
			{
				$ar = $this->parse_offer($rw);
				if ($ar["success"])
				{
					if (!$this->opts["save_raw_html"]) $ar["data"]["raw_html"]="";
					if (isset($ar["data"]["package"]))
						$ar["data"]["package"] = serialize($ar["data"]["package"]);
					if (isset($ar["data"]["info"]))
						$ar["data"]["info"] = serialize($ar["data"]["info"]);
					if (isset($ar["data"]["contacts"]))
						$ar["data"]["contacts"] = serialize($ar["data"]["contacts"]);
					$ar["data"]["sysname"] = $rw["sysname"];
					$ar["data"]["source_id"] = $rw["source_id"];
					if (!isset($ar["data"]["status"]))
						$ar["data"]["status"] = 1;
					$this->saveOffer($ar["data"]);
				}
				else
					echo "Error parsing offer $rw[id] $rw[source_url]: " . $ar["error"] . "\n";

				if ($this->opts["sleep_between_requests"])
					sleep($this->opts["sleep_between_requests"]);
			}
		}
		else
		{
			$page = $this->opts["start_page"];
			$max_page = $repeat_i = 0;
			if (!$page) $page = 1;

			while (true)
			{
				$ar = $this->parse_page($page);

				if ($ar[max_page] && $ar[data] && count($ar[data]))
				{
					echo count($ar["data"]) . " offers found, max_page=" . $max_page . "\n";
					$this->saveData($ar["data"]);
					$max_page = $ar["max_page"];

					$page++;
					$repeat_i = 0;

					$f = fopen($this->opts["tmp_file"],"w");
					fwrite($f,serialize(array("start_page"=>$page)));
					fclose($f);
				}
				else
				{
					echo "Error: offers not found\n";
					$repeat_i++;
					if ($repeat_i > $this->opts["max_repeat"])
						die("Max repeat reached\n");
				}

				if ($max_page && $page > $max_page)
				{
					unlink($this->opts["tmp_file"]);
					break;
				}

				if ($this->opts["sleep_between_requests"])
					sleep($this->opts["sleep_between_requests"]);
			}
		}
		return $this;
	}


	public function parse_offer($rw)
	{
		$data = $raw_data = array();

		$ar = explode("/",preg_replace("/^(http:\/\/)?/","",$rw[source_url]),2);
		$url = "/" . $ar[1];

		echo "Start parsing id=$rw[id] url $url\n";

		$req = new Req();
		$req	-> set(array("method"=>"GET","host"=>$this->opts["host"],"url"=>$url))
			-> req()
			-> saveContent($str);

		if (preg_match("/<title[^<>]*>Ошибка 404/",$str))
			return array("data"=>array("status"=>2),"success"=>"Offer not found");

		$data["raw_html"] = $str;

		$pp = new PageParser();
		$pp	-> set($str)
			-> setOpt("i",true)
			-> setOpt("beforeSaveFunc","strip_tags")
			-> setOpt("afterSaveFunc",create_function('$str','$str = preg_replace("/&nbsp;/"," ",$str); $str = preg_replace("/\s+/"," ",$str); return trim($str);'))
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
				-> DOMFind("/<div[^<>]*id\s*=\s*[\"']?sale-details[\"']?[^<>]*>/","/<\/div[^<>]*>/","/<div[^<>]*>/")
				-> replace("/<h3[^<>]*>[\s\S]*?<\/h3[^<>]*>/","")
				-> save($data["details"])
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
				-> save($data["photo_url"])
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

		unset($data["contacts"]["E-mail"]);

		return array("data"=>$data,"success"=>"Offer $rw[id] has been parsed");
	}


	public function parse_page($page = 1)
	{
		$url = $this->opts["pages_url"];

		if (is_array($this->opts["regions"]))
		{
			foreach($this->opts["regions"] as $region_id)
				$url .= "&region[]=" . $region_id;
			$url .= "&region_id=" . reset($this->opts["regions"]);
		}

		if ($page > 1)
			$url .= "&_p=" . $page;

		echo "Start parsing url $url\n";
		ob_flush();
		flush();

		$req = new Req();
		$req	-> set(array("method"=>"GET","host"=>$this->opts["host"],"url"=>$url))
			-> req()
			-> saveContent($str);

		$data = $pages = array();

		$pp = new PageParser();
		$pp	-> set($str)
			-> setOpt("i",true)
			-> setOpt("beforeSaveFunc","strip_tags")
			-> DOMfind("/<div[^<>]*id\s*=\s*[\"']?cars\_sale[\"']?[^<>]*>/","/<\/div[^<>]*>/","/<div[^<>]*>/")
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
		}

		$max_page = 0;
		foreach($pages as $i)
			if ($i > $max_page) $max_page = $i;

		return array("data"=>$data,"max_page"=>$max_page);
	}
}


