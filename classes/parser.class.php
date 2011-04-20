<?php

abstract class parser
{
	protected $data = array();
	protected $opts = array();

	abstract function run();

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

	public function save(&$d)
	{
		$d = $this->data;
		return $this;
	}

	protected function saveData($rws)
	{
		$cnt_added = $cnt_updated = 0;

		foreach($rws as $rw)
		{
			if (!$this->offerExists($rw))
			{
				if ($this->addOffer($rw))
				{
					$this->updateOffer($rw);
					$cnt_added++;
				}
				else echo "Error: failed adding offer sysname=$rw[sysname] source_id=$rw[source_id]\n";
			}
			else
			{
				$this->updateOffer($rw);
				$cnt_updated++;
			}
		}
		echo "$cnt_updated updated, $cnt_added added\n";
	}

	protected function saveOffer($rw)
	{
		if (!$this->offerExists($rw))
		{
			if ($this->addOffer($rw))
			{
				$this->updateOffer($rw);
			}
		}
		else
		{
			$this->updateOffer($rw);
		}
		return $this;
	}

	protected function offerExists($rw)
	{
		$rw["sysname"] = $this->opts["sysname"];
		return (DB::f1("select id from offers where sysname=:sysname and source_id=:source_id",array("sysname"=>$rw["sysname"],"source_id"=>$rw["source_id"]))?1:0);
	}

	protected function addOffer($rw)
	{
		$rw["dt_added"] = time();
		$rw["sysname"] = $this->opts["sysname"];
		return DB::q("insert into offers(`sysname`,`source_id`,`dt_added`) values(:sysname,:source_id,:dt_added)",array("sysname"=>$rw["sysname"],"source_id"=>$rw["source_id"],"dt_added"=>$rw["dt_added"]));
	}

	protected function updateOffer($rw)
	{
		$rw["dt_last_found"] = time();
		$rw["sysname"] = $this->opts["sysname"];

		$update_fields = "source_url,dt_last_found,markmodel,price_rub,production_year,engine,engine_type,right_steering_wheel,run,photo_exists,body_type,color,city,without_customs,available,details,package,info,contacts,photo_url,raw_html,status";
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
				$rw[$field] = $tmp_rw[$field];
				
		DB::q("update offers set " . $q . " where sysname=:sysname and source_id=:source_id",$rw);

		return 1;
	}
}

