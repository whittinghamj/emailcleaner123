<?php
 
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL);

$file = $argv[1];

// MySQL Settings
$database['username']   = "whittinghamj";
$database['password']   = "admin1372Dextor!#&@";
$database['database']   = "emailcleaner123";
$database['hostname']   = "64.71.170.18"; // local
////////////////////////////////////////////////////////////////////////////////////////////////////
// MySQL Connection

echo "Connecting to MySQL";

$db = new PDO('mysql:host='.$database['hostname'].';dbname='.$database['database'].';charset=utf8mb4', $database['username'], $database['password']);

echo "... done. \n";

$banned_domains = array('facebook.com', 'gov.com');
$line_breaks = array("\r\n", "\n", "\r");
 
$count = 0;
$handle = @fopen($file, "r");

echo "Reading $file ... done.\n";
if ($handle) {
    while (($buffer = fgets($handle, 4096)) !== false) {
        $buffer = str_replace($line_breaks, "", $buffer);
         
        if($count > 5)
        {   
            $bits = explode("@", $buffer);
 
            $data['email']      = $buffer;
            $data['domain']     = $bits[1];
 
            if (!in_array($data['domain'], $banned_domains))
            {

            	$insert = $db->exec("INSERT IGNORE INTO `emails` 
                (`added`, `email`, `domain`)
                VALUE
                ('".time()."', '".$data['email']."', '".$data['domain']."')");
        
                 
                if($insert)
                {
                    echo number_format($count) . ") Added ".$data['email']." \n";
                }
            }           
        }
        $count++;           
    }
    fclose($handle);
}

exec("rm -rf $file");
 
echo "\n\n";
