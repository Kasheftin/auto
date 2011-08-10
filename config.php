<?php

return array(
	"db" => array(
		"connections" => array(
			"local" => array(
				"sys"		=> "mysql:host=localhost;dbname=perekup_production",
				"user"		=> "root",
				"pass"		=> "nr3724fh",
				"encoding"	=> "utf8",
				"errmode"	=> "exception",
				"errformat"	=> "html",
			),
			"production" => array(
				"sys"		=> "mysql:host=localhost;dbname=perekup_production",
				"user"		=> "root",
				"pass"		=> "",
				"encoding"	=> "utf8",
				"errmode"	=> "exception",
				"errformat"	=> "html",
			),
		),
		"default_connection_id" => "local",
	),
	"debug" => array(
		"mode" => "SHORT|IMPORTANT|MAJOR|SQL",
		"realtime" => 1,
	),
);	
