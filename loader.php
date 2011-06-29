<?php

include(dirname(__FILE__) . "/c_header.php");

try
{
	$opts = array();

	if ($argv && is_array($argv))
		foreach($argv as $str)
		{
			$ar = explode("=",$str);
			$_REQUEST[$ar[0]] = $ar[1];
		}

	if ($_REQUEST && is_array($_REQUEST))
		foreach($_REQUEST as $field => $value)
			if (in_array($field,array("sysname","mode","offer_id","source_id","reset","state_p1")))
				$opts[$field] = $value;

	$opts["source_id"] = (int)$opts["source_id"];
	$opts["offer_id"] = (int)$opts["offer_id"];
	$opts["state_file"] = dirname(__FILE__) . "/tmp/" . $opts["sysname"] . "_" . $opts["mode"];

	if (!$opts["sysname"]) throw new Exception("Sysname not specified");
	if (!$opts["mode"]) throw new Exception("Mode not specified");
 	if (!in_array($opts["sysname"],array("auto","autochel","drom","e1","irr"))) throw new Exception("Unsupported sysname " . $opts["sysname"]);
	if (!in_array($opts["mode"],array("pages","offer"))) throw new Exception("Unsupported mode " . $opts["mode"]);

	require_once(dirname(__FILE__) . "/classes/parser_" . $opts["sysname"] . ".class.php");

	$parsername = "parser" . $opts["sysname"];
	$parser = new $parsername();

	$parser->set($opts)->run();
}
catch (Exception $e)
{
	echo "Unspecified fatal exception: " . $e->getMessage() . "\nException occurs in file " . $e->getFile() . " on line " . $e->getLine() . "\n";
}
