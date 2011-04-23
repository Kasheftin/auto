<?php

class ParserE1 extends Parser
{
	protected $opts = array(
		"host" => "www.e1.ru",
		"pages_url" => "/auto/sale/";
		"period" => 432000,
	);

	public function initializeState()
	{
		$this->req	-> set("url",$this->opts["pages_url"])
				-> req()
				-> saveContent($str);

		preg_match_all("/<a[^<>]*href\s*=\s*[\"']?(\/auto\/sale\/index\.php\?m=(\d+))[\"']?[^<>]*>([\s\S]*?)<\/a[^<>]*>/",$str,$ar);

		foreach($ar[1] as $i => $url)
		{
			$url .= "&t=4&o=2";
			$this->addToState(array("url"=>$url,"processed"=>0,"base_url"=>$url,"mark"=>$ar[3][$i]));
		}

		return $this;
	}

	public function parseOffers($obj,$str)
	{

	



		{
			$req = new Req();
			$req	-> set(array("method"=>"GET","host"=>$this->opts["host"],"url"=>"/auto/sale/"))
				-> req()
				-> saveContent($str);

			$str = iconv("windows-1251","utf-8",$str);
			preg_match_all("/<a[^<>]*href\s*=\s*[\"']?(\/auto\/sale\/index\.php\?m=(\d+))[\"']?[^<>]*>([\s\S]*?)<\/a[^<>]*>/",$str,$marks_pages);

			foreach($marks_pages[1] as $i => $url)
			{
				if ($this->opts["start_mark_index"] && !$this->opts["start_mark_index_switch"] && $marks_pages[2][$i] != $this->opts["start_mark_index"]) continue;

				$page = 0;
				if (!$this->opts["start_mark_index_switch"] && $this->opts["start_page"])
					$page = $this->opts["start_page"];

				$max_page = $repeat_i = 0;
				$min_dt = time();

				$this->opts["start_mark_index_switch"] = 1;

				$data = array();

				while (true)
				{
					if ($this->opts["sleep_between_requests"])
						sleep($this->opts["sleep_between_requests"]);

					$ar = $this->parse_page($url . "&t=4&o=2&p=" . ((int)$page));

					if ($ar["data"] && count($ar["data"]))
					{
						echo count($ar["data"]) . " offers found, max_page=" . $max_page . ", min_date=" . date("Y-m-d",$ar["min_dt"]) . "\n";
						foreach($ar["data"] as &$rw)
							$rw["mark"] = $marks_pages[3][$i];
						$this->saveData($ar["data"]);

						$f = fopen($this->opts["tmp_file"],"w");
						fwrite($f,serialize(array("start_page"=>$page,"start_mark_index"=>$marks_pages[2][$i])));
						fclose($f);
	
						$max_page = $ar["max_page"];
						$min_dt = $ar["min_dt"];
						$page++;
						$repeat_i = 0;

						if ($page > $max_page)
						{
							echo "All pages loaded\n";
							break;
						}

						if ($min_dt < time() - $this->opts["period"])
						{
							echo "Min published date reached\n";
							break;
						}
					}
					else
					{
						echo "Error: offers not found\n";
						$repeat_i++;
						if ($repeat_i > $this->opts["max_repeat"])
							die("Max repeat reached\n");
					}
				}
			}

			unlink($this->opts["tmp_file"]);
		}
		return $this;
	}

	protected function parse_page($url)
	{
		echo "Start parsing $url\n";
		ob_flush();
		flush();

		$req = new Req();
		$req	-> set(array("method"=>"GET","host"=>$this->opts["host"],"url"=>$url))
			-> req()
			-> saveContent($str);

		$str = iconv("windows-1251","utf-8",$str);

		$pp = $this->createDefaultPP($str);
		$pp	-> b()
				-> findAll("/<a[^<>]*class\s*=\s*[\"']?text\_orange[^<>]*>(\d+)<\/a[^<>]*>/")
				-> save($pages)
			-> e()

// This doesn't work if there're no pages
//			-> find("/<a[^<>]*class\s*=\s*[\"']?text\_orange[^<>]*>/","/<\/table[^<>]*>/","/<\/tr[^<>]*>/","/<\/table[^<>]*>/")
// Instead try this:
			-> find("/<font[^<>]*>\s*Предложения по автомобилям/","/<\/table[^<>]*>/","/<\/tr[^<>]*>/","/<\/table[^<>]*>/")

			-> split("/<\/tr[^<>]*>/")
			-> rmBI(0)
			-> each()
				-> split("/<\/td[^<>]*>/")
				-> b()
					-> selBI(0)
					-> find("/<a[^<>]*href\s*=\s*[\"']?(\d+)\.html[^<>]*>/")
					-> save($data["source_id"])
				-> e()
				-> b()
					-> selBI(0)
					-> replace("/<[^<>]*>/","")
					-> replace("/[^\d]/","")
					-> save($data["price_rub"])
				-> e()
				-> b()
					-> selBI(1)
					-> save($data["production_year"])
				-> e()
				-> b()
					-> selBI(2)
					-> find("/(camera)\.gif/")
					-> replace("/camera/",1)
					-> save($data["photo_exists"])
				-> e()
				-> b()
					-> selBI(3)
					-> save($data["date_published"])
				-> e()
				-> b()
					-> selBI(4)
					-> save($data["engine"])
				-> e()
				-> b()
					-> selBI(5)
					-> find("/title\s*=\s*[\"']?([^<>\"']*)[\"']?/")
					-> save($data["engine_type"])
				-> e()
				-> b()
					-> selBI(6)
					-> replace("/<[^<>]*>/","")
					-> replace("/[^0-9]/","")
					-> save($data["run"])
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
					-> find("/(wheel)\_2/")
					-> replace("/wheel/",1)
					-> save($data["right_steering_wheel"])
				-> e()
				-> b()
					-> selBI(10)
					-> save($data["city"])
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
			$ar = explode(".",$data[$i]["date_published"]);
			$data[$i]["dt_published"] = mktime(0,0,0,$ar[1],$ar[0],$ar[2]);
			if ($data[$i]["dt_published"] < $min_dt) $min_dt = $data[$i]["dt_published"];
			$data[$i]["source_url"] = "/auto/sale/" . $data[$i]["source_id"] . ".html";
		}

		$max_page = 0;
		foreach($pages as $i)
			if ($i > $max_page) $max_page = $i;

		return array("data"=>$data,"max_page"=>$max_page,"min_dt"=>$min_dt,"success"=>1);
	}
}

