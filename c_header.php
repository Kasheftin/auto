<?php

ini_set("memory_limit","64M");
error_reporting(E_ERROR);
set_time_limit(0);
mb_internal_encoding("UTF-8");

//require_once(dirname(__FILE__) . "/classes/debug/debug.class.php");
require_once(dirname(__FILE__) . "/classes/req/req.class.php");
require_once(dirname(__FILE__) . "/classes/req/reqexception.class.php");
require_once(dirname(__FILE__) . "/classes/pageparser/pageparser.class.php");
require_once(dirname(__FILE__) . "/classes/db/db.class.php");
require_once(dirname(__FILE__) . "/classes/parser.class.php");

$CONFIG = require_once(dirname(__FILE__) . "/config.php");

DB::setConfig($CONFIG["db"]);
//DEBUG::setConfig($CONFIG["debug"]);

