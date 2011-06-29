<?php

class ParserE1 extends Parser
{
	protected $opts = array(
		"host" => "www.e1.ru",
		"pages_url" => "/auto/sale/",
		"period" => 432000,
		"default_page_encoding" => "windows-1251",
	);

	public function initializeState()
	{
		echo "\nInitializing: loading url=" . $this->opts["pages_url"] . "\n";
		$this->req	-> set("url",$this->opts["pages_url"])
				-> req()
				-> saveContent($str);

		preg_match_all("/<a[^<>]*href\s*=\s*[\"']?(\/auto\/sale\/index\.php\?m=(\d+))[\"']?[^<>]*>([\s\S]*?)<\/a[^<>]*>/",$str,$ar);

		foreach($ar[1] as $i => $url)
		{
			$mark = $ar[3][$i];
			if ($this->opts["state_p1"] && $this->opts["state_p1"] != $mark) continue;
			$url .= "&t=4&o=2";
			$this->addToState(array("url"=>$url,"processed"=>0,"base_url"=>$url,"mark"=>$mark));
		}

		return $this;
	}

	public function parseOffer($rw,$str)
	{
		$data = $data_raw = array();

		if (preg_match("/<title[^<>]*>404 Not Found/i",$str))
			return array("data"=>array("status"=>2),"success"=>"Offer not found");

		$data["raw_html"] = $str;

		$this->pp	-> reset()
				-> set($str)
				-> b()
					-> DOMFind("/<table[^<>]*>/","/<\/table[^<>]*>/","/<table[^<>]*>/")
					-> b()
						-> find("/<td[^<>]*>/","/<\/td[^<>]*>/")
						-> save($data["markmodel"],null,1)
					-> e()
					-> findAll("/<table[^<>]*>/","/<\/table[^<>]*>/")
					-> findAll("/<tr[^<>]*>/","/<\/tr[^<>]*>/")
					-> each()
						-> split("/<\/td[^<>]*>/")
						-> b()
							-> selBI(0)
							-> replace("/:\s*$/","")
							-> save($raw_data["info"]["param_name"])
						-> e()
						-> b()
							-> selBI(1)
							-> save($raw_data["info"]["param_value"])
						-> e()
					-> endEach()
				-> e()
				-> b()
					-> find("/<b[^<>]*>\s*Комплектация/","/<\/table[^<>]*>/")
					-> findAll("/<td[^<>]*>/","/<\/td[^<>]*>/")
					-> save($data["package"])
				-> e()
				-> b()
					-> find("/<b[^<>]*>\s*Дополнительные сведения:/","/<\/table[^<>]*>/")
					-> save($data["details"],null,1)
				-> e()
				-> b()
					-> find("/<b[^<>]*>\s*Фотографии/","/<\/table[^<>]*>/")
					-> find("/<img[^<>]*src\s*=\s*[\"']?([^<>\"']*)[\"']?[^<>]*>/")
					-> save($data["photo_url"],null,1)
				-> e()
				-> b()
					-> find("/<b[^<>]*>\s*Контактная информация/","/<\/table>/")
					-> findAll("/<tr[^<>]*>/","/<\/tr>/")
					-> each()
						-> split("/<\/td>/")
						-> b()
							-> selBI(0)
							-> replace("/:\s*$/","")
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

		if ($data["info"]["Таможня"] && $data["info"]["Таможня"] != "Растаможен" && $data["info"]["Таможня"] != "Не нуждается")
			$data["without_customs"] = 1;
		else
			$data["without_customs"] = 0;
		
		if ($data["info"]["Привод"])
			$data["drive"] = $data["info"]["Привод"];
		if ($data["info"]["Тип кузова"])
			$data["body_type"] = $data["info"]["Тип кузова"];
		if ($data["info"]["Тип двигателя"])
			$data["engine_type"] = $data["info"]["Тип двигателя"];
		if ($data["info"]["Объем двигателя"])
			$data["engine"] = $data["info"]["Объем двигателя"];
		if ($data["info"]["Цвет"])
			$data["color"] = $data["info"]["Цвет"];
		if ($data["info"]["Пробег"])
			$data["run"] = $data["info"]["Пробег"];
		if ($data["info"]["КПП"])
			$data["transmission"] = mb_substr($data["info"]["КПП"],0,1);
		if ($data["info"]["Город"])
			$data["city"] = $data["info"]["Город"];

		if (!preg_match("/не указан/",$data["info"]["VIN"]))
			$data["vin"] = $data["info"]["VIN"];
		$data["contact_person"] = $data["contacts"]["Продавец"];

		$phone_raw = $data["contacts"]["Телефон"];
		$ar = explode(",",$phone_raw,2);
		$phone_raw = trim($ar[0]);
		$phone_raw = preg_replace("/[^\d]/","",$phone_raw);
		$phone_raw = preg_replace("/^8/","",$phone_raw);
		$data["phone"] = $phone_raw;

		unset($data["contacts"]["Адрес e-mail"]);

		return array("data"=>$data,"success"=>"Offer $rw[id] has been parsed");
	}

	public function parseOffers($obj,$str)
	{
		$data = $pages = array();

		$this->pp	-> reset()
				-> set($str)
				-> b()
					-> findAll("/<a[^<>]*class\s*=\s*[\"']?text\_orange[^<>]*>(\d+)<\/a[^<>]*>/")
					-> save($pages)
				-> e()
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
						-> save($data["price_raw"])
						-> b()
							-> find("/(р)/")
							-> replace("/р/",1)
							-> save($data["price_type_rub"])
						-> e()
						-> b()
							-> find("/(&euro;)/")
							-> replace("/&euro;/",1)
							-> save($data["price_type_eur"])
						-> e()
						-> replace("/[^\d]/","")
						-> save($data["price"])
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
						-> replace("/<[^<>]*>/","")
						-> b()
							-> find("/(\d+)/")
							-> save($data["engine"])
						-> e()
						-> b()
							-> find("/\d+(.*)$/")
							-> save($data["transmission"])
						-> e()
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

			$data[$i]["source_url"] = "/auto/sale/print/" . $data[$i]["source_id"] . ".html";
			$data[$i]["mark"] = $obj["mark"];

			$data[$i]["price_rub"] = $data[$i]["price_usd"] = $data[$i]["price_rub"] = 0;

			if ($data[$i]["price_type_eur"])
				$data[$i]["price_eur"] = $data[$i]["price"];
			else
				$data[$i]["price_rub"] = $data[$i]["price"];

			if (preg_match("/аварийный или битый/",$data[$i]["body_type"]))
			{
				$data[$i]["crashed"] = 1;
				$data[$i]["body_type"] = trim(preg_replace("/аварийный или битый/","",$data[$i]["body_type"]));
			}

			unset($data[$i]["price"]);
			unset($data[$i]["price_type_rub"]);
			unset($data[$i]["price_type_eur"]);
			unset($data[$i]["price_type_usd"]);

			$data[$i]["print_source_url"] = "http://" . $this->opts["host"] . preg_replace("/\/print\//","/",$data[$i]["source_url"]);
		}

		$urls = array();
		foreach($pages as $i)
		{
			$tobj = $obj;
			$tobj["url"] = $tobj["base_url"] . "&p=" . ($i-1);
			$urls[] = $tobj;
		}

		return array("data"=>$data,"urls"=>$urls,"success"=>"Page loaded","min_dt"=>$min_dt);
	}
}

