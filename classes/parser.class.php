<?php

abstract class parser
{
	protected $data = array();
	protected $opts = array();
	protected $state = array();

	protected $default_opts = array(
		"sleep_between_requests" => 1,
		"max_repeat" => 3,
		"save_raw_html" => 0,
	);

	abstract function parseOffer($rw,$str);
	abstract function parseOffers($obj,$str);
	abstract function initializeState();

	public function run()
	{
		$this->opts = array_merge($this->default_opts,$this->opts);
		if ($this->opts["reset"]) $this->resetStateFile();
		$this->loadStateFromFile();

		$this->req = new Req();
		$this->req -> set(array("method"=>"GET","host"=>$this->opts["host"],"auto_encode_to"=>"utf-8"));
		if ($this->opts["sleep_between_requests"]) $this->req->set("delay",$this->opts["sleep_between_requests"]);
		if ($this->opts["max_repeat"]) $this->req->set("auto_redirects_limit",$this->opts["max_repeat"]);
 
		$pp = new PageParser();
		$pp	-> setOpt("i",true)
			-> setOpt("beforeSaveFunc","strip_tags")
			-> setOpt("afterSaveFunc",create_function('$str','$str = preg_replace("/&nbsp;/"," ",$str); $str = preg_replace("/\s+/"," ",$str); return trim($str);'))
			-> setOpt("smartSaving",0);
		$this->pp = $pp;
		
		if ($this->opts["mode"] == "offer")
		{
			if ($this->opts["offer_id"])
				$rws = DB::f("select id,source_id,source_url from offers where id=:id and sysname=:sysname limit 0,1",array("id"=>$this->opts["offer_id"],"sysname"=>$this->opts["sysname"]));
			else
				$rws = DB::f("select id,source_id,source_url from offers where status=0 and sysname=:sysname",array("sysname"=>$this->opts["sysname"]));

			foreach($rws as $rw)
			{
				echo "\n";
				$ar = $this->loadOffer($rw);
				if ($ar["success"])
				{
					$ar = $this->parseOffer($rw,$ar["data"]);
					if ($ar["success"])
					{
						unset($ar["data"]["raw_html"]);
						$ar["data"]["source_id"] = $rw["source_id"];
						if (!$ar["data"]["status"]) $ar["data"]["status"] = 1;
						$ar = $this->saveOffer($ar["data"]);
						if ($ar["success"])
						{
							if ($ar["added"]) echo "Offer #" . $rw["id"] . " added\n";
							if ($ar["updated"]) echo "Offer #" . $rw["id"] . " updated\n";
						}
						else echo "Error: " . $ar["error"] . "\n";
					}
					else
						echo "Error parsing offer id=" . $rw["id"] . " source_url=" . $rw["source_url"] . ": " . $ar["error"] . "\n";
				}
				else
					echo "Error loading offer id=" . $rw["id"] . " source_url=" . $rw["source_url"] . ": " . $ar["error"] . "\n";
			}
		}
		elseif ($this->opts["mode"] == "pages")
		{
			if (!$this->state)
				$this->initializeState();

			$repeat_i = 0;

			while ($obj = $this->getNextState())
			{
				echo "\n";
				$ar = $this->loadPage($obj);
				if ($ar["success"])
				{
					$ar = $this->parseOffers($obj,$ar["data"]);
					if ($ar["success"])
					{
						echo count($ar["data"]) . " offers found\n";
						$this->saveOffers($ar["data"]);
						$this->addToState($ar["urls"]);
						$obj["processed"] = 1;
						$this->updateState($obj);
						$this->saveStateToFile();
					}
					else echo "Parsing offers error occurs: " . $ar["error"] . "\n";
					$repeat_i = 0;
				}
				else
				{
					echo "Error loading page url=" . $url . ", try " . $try_i . "\n";
					$repeat_i++;
					if ($repeat_i > $this->opts["max_repeat"]) throw new Exception("Max repeat reached\n");
				}
			}
		}
		else throw new Exception("Mode " . $this->opts["mode"] . " is not supported");

		return $this;
	}

	public function set()
	{
		$args = func_get_args();
		if (count($args) == 1 && is_array($args))
			foreach($args[0] as $i => $v)
				$this->opts[$i] = $v;
		elseif (count($args) == 2 && $args[0] && !is_array($args[0]))
			$this->opts[$args[0]] = $args[1];
		else throw new Exception("Incorrect method args sent to " . __CLASS__ . "::" . __METHOD__);
		return $this;
	}




	protected function loadOffer($rw)
	{
		echo "Loading offer id=" . $rw["id"] . " source_url=" . $rw["source_url"] . "\n";
		ob_flush();
		flush();
		$this->req	-> set($this->getHostAndUrl($rw["source_url"]))
				-> req()
				-> saveContent($str);
		return array("success"=>1,"data"=>$str);
	}

	protected function loadPage($obj)
	{
		echo "Loading url " . $obj["url"] . "\n";
		ob_flush();
		flush();
		$this->req	-> set($this->getHostAndUrl($obj["url"]))
				-> req()
				-> saveContent($str);
		return array("success"=>1,"data"=>$str);
	}

	protected function getHostAndUrl($str)
	{
		if (preg_match("/^http:\/\//",$str))
		{
			$ar = explode("/",preg_replace("/^http:\/\//","",$str),2);
			$opts = array("host"=>$ar[0],"url"=>"/" . $ar[1]);
		}
		else
			$opts = array("host"=>$this->opts["host"],"url"=>$str);
		return $opts;
	}

	protected function resetStateFile()
	{
		if ($this->opts["state_file"]) system("rm -f " . $this->opts["state_file"]);
		return $this;
	}

	protected function loadStateFromFile()
	{
		if ($this->opts["state_file"] && file_exists($this->opts["state_file"]))
		{
			$f = fopen($this->opts["state_file"],"rb");
			$str = fread($f,filesize($this->opts["state_file"]));
			fclose($f);
			$this->state = unserialize($str);
		}
		return $this;
	}

	protected function saveStateToFile()
	{
		if ($this->opts["state_file"])
		{
			$f = fopen($this->opts["state_file"],"w");
			fwrite($f,serialize($this->state));
			fclose($f);
		}
		return $this;
	}

	protected function addToState($var)
	{
		if ($var["url"])
		{
			$tmp_var = $var;
			$var = array();
			$var[0] = $tmp_var;
		}

		foreach($var as $obj)
		{
			$url = $obj["url"];
			unset($obj["url"]);
			if (!isset($this->state["urls"][$url]))
				$this->state["urls"][$url] = $obj;
		}

		return $this;
	}

	protected function updateState($obj)
	{
		$this->state["urls"][$obj["url"]] = $obj;
		return $this;
	}

	protected function getNextState()
	{
		foreach($this->state["urls"] as $url => $obj)
			if ($obj["processed"] != true)
			{
				$obj["url"] = $url;
				return $obj;
			}
		return null;
	}

	protected function saveOffers($rws)
	{
		$cnt_added = $cnt_updated = 0;
		foreach($rws as $rw)
		{
			$ar = $this->saveOffer($rw);
			if ($ar["success"])
			{
				if ($ar["added"]) $cnt_added++;
				if ($ar["updated"]) $cnt_updated++;
			}
			else
				echo $ar["error"];
		}
		echo $cnt_updated . " offers updated, " . $cnt_added . " offers added\n";
		return $this;
	}

	protected function saveOffer($rw)
	{
		if (!$this->offerExists($rw))
		{
			if ($this->addOffer($rw))
			{
				$this->updateOffer($rw);
				return array("success"=>1,"added"=>1);
			}
			else return array("error"=>"Error: failed adding offer source_id=" . $rw["source_id"]);
		}
		else
		{
			if ($this->updateOffer($rw)) return array("success"=>1,"updated"=>1);
			else return array("error"=>"Error: failed updating offer source_id=" . $rw["source_id"]);
		}
	}

	protected function offerExists($rw)
	{
		return (DB::f1("select id from offers where sysname=:sysname and source_id=:source_id",array("sysname"=>$this->opts["sysname"],"source_id"=>$rw["source_id"]))?1:0);
	}

	protected function addOffer($rw)
	{
		return DB::q("insert into offers(`sysname`,`source_id`,`dt_added`) values(:sysname,:source_id,:dt_added)",array("sysname"=>$this->opts["sysname"],"source_id"=>$rw["source_id"],"dt_added"=>time()));
	}

	protected function updateOffer($rw)
	{
		$rw["dt_last_found"] = time();
		$rw["sysname"] = $this->opts["sysname"];

		$update_fields = "source_url,dt_last_found,mark,markmodel,price_rub,production_year,engine,engine_type,right_steering_wheel,run,photo_exists,body_type,color,city,without_customs,available,details,details_where,package,info,contacts,photo_url,status,drive,vin,contact_person,phone";
		if ($this->opts["save_raw_html"]) $update_fields .= ",raw_html";
		$clear_fields = $update_fields . ",sysname,source_id";

		$q = "";
		$ar = explode(",",$update_fields);
		foreach($ar as $field)
			if (isset($rw[$field]))
				$q .= ($q?",":"") . $field . "=:" . $field;

		$ar = explode(",",$clear_fields);
		$tmp_rw = $rw;
		$rw = array();
		foreach($ar as $field)
			if (isset($tmp_rw[$field]))
			{
				if (is_array($tmp_rw[$field]))
					$rw[$field] = serialize($tmp_rw[$field]);
				else
					$rw[$field] = $tmp_rw[$field];
			}

		DB::q("update offers set " . $q . " where sysname=:sysname and source_id=:source_id",$rw);

		return 1;
	}
}

