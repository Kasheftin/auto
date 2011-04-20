<?php

ini_set("memory_limit","64M");
error_reporting(E_ERROR);
set_time_limit(0);

require_once(dirname(__FILE__) . "/classes/req/req.class.php");
require_once(dirname(__FILE__) . "/classes/req/reqexception.class.php");
require_once(dirname(__FILE__) . "/classes/pageparser/pageparser.class.php");
require_once(dirname(__FILE__) . "/classes/db/db.class.php");
require_once(dirname(__FILE__) . "/classes/parser.class.php");

$CONFIG = require_once("config.php");

try
{
	$sysname = $_REQUEST["sysname"];
	$mode = $_REQUEST["mode"];
	$start_page = (int)$_REQUEST["start_page"];
	$offer_id = (int)$_REQUEST["offer_id"];

	if (!$sysname)
	{
		$sysname = $argv[1];
		$mode = $argv[2];
		if ($mode == "offer")
			$offer_id = (int)$argv[3];
		else
			$start_page = (int)$argv[3];
	}

	$tmp_file = dirname(__FILE__) . "/tmp/"  . $sysname . "_" . $mode;	

	if (!$sysname) throw new Exception("Sysname not specified");
	if (!$mode) throw new Exception("Mode not specified");
 	if (strpos(",auto,autochel,drom,e1,",",$sysname,") === false) throw new Exception("Unsupported sysname <strong>$sysname</strong>");
	if (strpos(",pages,pages_full,offer,",",$mode,") === false) throw new Exception("Unsupported mode <strong>$mode</strong>"); 

	$opts = array(
		"sysname"=>$sysname,
		"mode"=>$mode,
		"start_page"=>$start_page,
		"tmp_file"=>$tmp_file,
		"offer_id"=>$offer_id,
	);

	if (file_exists($tmp_file))
	{
		$f = fopen($tmp_file,"rb");
		$str = fread($f,filesize($tmp_file));
		fclose($f);
		$opts = array_merge($opts,unserialize($str));
	}
	
	DB::setConfig($CONFIG["db"]);

	require_once(dirname(__FILE__) . "/classes/parser_" . $sysname . ".class.php");

	$parsername = "parser" . $sysname;
	$parser = new $parsername();


	$parser->set($opts)->run();
	
}
catch (Exception $e)
{
	echo "Unspecified fatal exception: " . $e->getMessage() . "<br />\nException occurs in file " . $e->getFile() . " on line " . $e->getLine() . "<br />\n<br />\n";
}
