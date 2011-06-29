<?php

include(dirname(__FILE__) . "/c_header.php");

$path = dirname(__FILE__) . "/regions.txt";
$f = fopen($path,"rb");
$str = fread($f,filesize($path));
fclose($f);

$ar = explode("\n",$str);
foreach($ar as $str)
{
	$arr = explode("\t",$str);
	DB::q("insert ignore into regions(`id`,`name`,`capitol`,`code`) values(:id,:name,:capitol,:code)",array("id"=>$arr[0],"name"=>$arr[1],"capitol"=>$arr[2],"code"=>$arr[0]));
}


