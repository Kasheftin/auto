<?php

return array(
	"db" => array(
		"connections" => array(
			"local" => array(
				"sys"		=> "mysql:host=localhost;dbname=auto",
				"user"		=> "auto",
				"pass"		=> "cM8JhMEWLcS39Xyn",
				"encoding"	=> "utf8",
				"errmode"	=> "exception",
				"errformat"	=> "html",
			),
			"production" => array(
				"sys"		=> "mysql:host=localhost;dbname=obzor_auto",
				"user"		=> "obzor_auto",
				"pass"		=> "test",
				"encoding"	=> "utf8",
				"errmode"	=> "exception",
				"errformat"	=> "html",
			),
		),
		"default_connection_id" => "local",
	),
);	
