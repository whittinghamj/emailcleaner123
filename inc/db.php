<?php

////////////////////////////////////////////////////////////////////////////////////////////////////
// mysql settings

$database['username']		= "root";
$database['password']		= "admin1372";
$database['database']		= "emails";
$database['hostname']		= "10.0.0.101";
////////////////////////////////////////////////////////////////////////////////////////////////////
// mysql connection

$db = new PDO('mysql:host='.$database['hostname'].';dbname='.$database['database'].';charset=utf8mb4', $database['username'], $database['password']);