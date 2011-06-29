<?php

include(dirname(__FILE__) . "/c_header.php");

try
{
	$patterns = array();
	$rws = DB::f("select * from patterns");
	foreach($rws as $rw)
		$patterns[$rw["sysname"]][$rw["input_field"]][] = $rw;

	$rws = DB::q("select * from source_offers where patterns_status=0 and status=1");
	foreach($rws as $rw)
	{
		try
		{
			$rw_update = array("patterns_status"=>1);
			if (!$patterns[$rw["sysname"]]) continue;
			foreach($patterns[$rw["sysname"]] as $input_field => $p_rws)
			{
				$r_val = mb_strtolower($rw[$input_field]);

				if (!$r_val)
				{
					echo "required field " . $input_field . " is empty\n";
					continue;
				}

				$output_value = 0;
				foreach($p_rws as $p_rw)
				{
					$p_val = mb_strtolower($p_rw["input_value"]);
					if ((($p_val == $r_val) && ($p_rw["type"] == "equal")) || (preg_match("/" . $p_val . "/",$r_val) && $p_rw["type"] == "match"))
					{
						$output_value = $p_rw["output_value"];
						break;
					}
				}

				if (!$output_value)
					throw new Exception("pattern not found for $input_field, value=" . $r_val);

				$rw_update[$input_field . "_id"] = $output_value;
			}

			$update_str = "";
			foreach($rw_update as $field => $value)
				$update_str .= ($update_str?",":"") . $field . "=:" . $field;

			$rw_update["id"] = $rw["id"];

			DB::q("update source_offers set " . $update_str . " where id=:id",$rw_update);
			echo $rw["id"] . " - updated\n";
		}
		catch (Exception $e)
		{
			echo "Error: #" . $rw["id"] . " - " . $e->getMessage() . "\n";
		}
	}
}
catch (Exception $e)
{
	echo "Unspecified fatal exception: " . $e->getMessage() . "\nException occurs in file " . $e->getFile() . " on line " . $e->getLine() . "\n";
}
