<?php

include(dirname(__FILE__) . "/c_header.php");

try
{
	DB::q("update source_offers set created_at=FROM_UNIXTIME(dt_added),updated_at=FROM_UNIXTIME(dt_last_found)");
}
catch (Exception $e)
{
	echo "Unspecified fatal exception: " . $e->getMessage() . "\nException occurs in file " . $e->getFile() . " on line " . $e->getLine() . "\n";
}
