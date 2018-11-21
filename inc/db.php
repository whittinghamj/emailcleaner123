<?php

////////////////////////////////////////////////////////////////////////////////////////////////////
// mysql settings

$database['username']		= "whittinghamj";
$database['password']		= "admin1372Dextor!#&@";
$database['database']		= "emailcleaner123";
$database['hostname']		= "64.71.170.18";
////////////////////////////////////////////////////////////////////////////////////////////////////
// mysql connection

$db = new PDO('mysql:host='.$database['hostname'].';dbname='.$database['database'].';charset=utf8mb4', $database['username'], $database['password']);