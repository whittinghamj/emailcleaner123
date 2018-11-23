<?php

// error_reporting(E_ALL);
// ini_set('display_errors', 1);
// ini_set('error_reporting', E_ALL); 

$base = dirname(__FILE__).'/';

include($base.'../inc/db.php');
include($base.'../inc/functions.php');
include($base.'../inc/class.phpclicolors.php');

$colors = new Colors();

$task = $argv[1];

if($task == 'clean_multi')
{
	$records                = $argv[2];
	$threads 				= $argv[3];
	
	require $base.'../inc/cron.helper.php';
	if ( ( $pid = cronHelper::lock() ) !== FALSE ) 
	{
		
		console_output("Spawning ".$threads." children.");
		
		$pids = array();
				
		for ( $i = 0; $i < $threads; $i++ ) 
		{
			
			$pids[$i] = pcntl_fork();

			if ( !$pids[$i] ) 
			{
				
				include($base.'../inc/db.php');
				
				// get the latest job
				$query      = "SELECT * FROM `jobs` WHERE `status` = 'pending_clean' ORDER BY `id` LIMIT 1";
				$result     = mysql_query($query) or die(mysql_error());
				$number_of_jobs = mysql_num_rows($result);
				if($number_of_jobs == 0){
					console_output('No pending jobs.');
				}else{

					while($row = mysql_fetch_array($result)){
						$data['id']                     = $row['id'];
						$data['user_id']                = $row['user_id'];
						$data['list_id']                = $row['list_id'];
					}

					$query_1        = "SELECT `id`,`email` FROM `emails` WHERE `list_id` = '".$data['list_id']."' AND `status` = 'pending' ORDER BY RAND() LIMIT ".$records;
					$result_1       = mysql_query($query_1) or die(mysql_error());
					$count          = mysql_num_rows($result_1);
					if ( $count == 0 ){
						mysql_query("UPDATE `email_lists` SET `job_endtime` = '".time()."' WHERE `id` = '".$data['list_id']."' ") or die(mysql_error());
						mysql_query("UPDATE `email_lists` SET `status` = 'complete' WHERE `id` = '".$data['list_id']."' ") or die(mysql_error());
						mysql_query("UPDATE `email_lists` SET `cleaned` = 'yes' WHERE `id` = '".$data['list_id']."' ") or die(mysql_error());

						mysql_query("UPDATE `jobs` SET `status` = 'complete' WHERE `id` = '".$data['id']."' ") or die(mysql_error());

						console_output('Job has finished.');
					}
					while($row_1 = mysql_fetch_array($result_1)){
						$data['emails'][$count]['id']       = $row_1['id'];
						$data['emails'][$count]['email']    = $row_1['email'];

						$bits = explode("@", $row_1['email']);
						$data['emails'][$count]['domain'] = $bits[1];

						$count++;
					}

					console_output('User ID: '.$data['user_id']);
					console_output('Job ID: '.$data['id']);
					console_output('List ID: '.$data['list_id']);
					console_output("Segment Size: ".number_format(count($data['emails'])));

					$records = count($data['emails']);

					console_output("Starting Job Run");

					console_output(" ");

					$count = count ( $data['emails'] );

					foreach($data['emails'] as $email){
						// run internal tests first
						// check role account
						if ( check_role_account ( $email['email'] == 'no_match' ) ) {

						} else {
							console_output(
								$colors->getColoredString(
									number_format($count) . ') ' . $email['email']." failed ROLE ACCOUNT check.", 
								"red", "black"));
							mysql_query("UPDATE `emails` SET `status` = 'roleaccount' WHERE `id` = '".$email['id']."' ") or die(mysql_error());
							continue;
						}

						// validate email syntax
						if ( valid_email ( $email['email'] ) ) {

						} else {
							console_output(
								$colors->getColoredString(
									number_format($count) . ') ' . $email['email']." failed SYNTAX check.", 
								"red", "black"));
							mysql_query("UPDATE `emails` SET `status` = 'invalid_syntax' WHERE `id` = '".$email['id']."' ") or die(mysql_error());
							continue;
						}

						// check internal blacklist
						if(check_int_blacklist($email['email']) == 'clean'){

						}else{
							console_output(
								$colors->getColoredString(
									number_format($count) . ') ' . $email['email']." failed BLACKLIST check.", 
								"red", "black"));
							mysql_query("UPDATE `emails` SET `status` = 'blacklisted_internal' WHERE `id` = '".$email['id']."' ") or die(mysql_error());
							continue;
						}

						// domain check
						$domain_check = check_domain ( $email['email'] );
						if ( $domain_check['status'] == 'mxserver_does_not_exist' ) {
							console_output(
								$colors->getColoredString(
									number_format($count) . ') ' . $email['email']." failed MX check.", 
								"red", "black"));
							mysql_query("UPDATE `emails` SET `status` = 'mxserver_does_not_exist' WHERE `id` = '".$email['id']."' ") or die(mysql_error());
							continue;
						}
						if ( $domain_check['status'] == 'domain_does_not_exist' ) {
							console_output(
								$colors->getColoredString(
									number_format($count) . ') ' . $email['email']." failed DOMAIN check.", 
								"red", "black"));
							mysql_query("UPDATE `emails` SET `status` = 'domain_does_not_exist' WHERE `id` = '".$email['id']."' ") or die(mysql_error());
							continue;
						}

						console_output(
							$colors->getColoredString(
								number_format($count) . ') ' . $email['email']." PASSED ALL CHECKS.", 
							"green", "black"));

						mysql_query("UPDATE `emails` SET `status` = 'clean' WHERE `id` = '".$email['id']."' ") or die(mysql_error());	

						$count = $count - 1;
					}
				}
				
				mysql_disconnect();
				
				exit();
			}
		}
		
		for ( $i = 0; $i < $threads; $i++ ) 
		{
			pcntl_waitpid($pids[$i], $status, WUNTRACED);
		}
	}else{
		console_output(
			$colors->getColoredString(
				"Script already running", 
			"pink", "black"));
	}
}

if($task == 'domain_checker')
{
	// load cron lock helper
	require $base.'../inc/cron.helper.php';
	if ( ( $pid = cronHelper::lock() ) !== FALSE ) {
		$records                = $argv[2];
		$search_records         = $records;

		$query = $db->query("SELECT * FROM `email_domains` WHERE `last_checked` = '0' ORDER BY `id` LIMIT ".$search_records);
    	$rows = $query->fetchAll(PDO::FETCH_ASSOC);

    	$count = 0;
		
		foreach($rows as $row){
			$data[$count]['id']                    = $row['id'];
			$data[$count]['domain']                = $row['domain'];
			$data[$count]['status']                = $row['status'];
			
			if(checkdnsrr($data[$count]['domain'], 'ANY')){
				// domain exists, lets set to active then check MX records
				$update = $db->exec("UPDATE `email_domains` SET `status` = 'active' WHERE `id` = '".$data[$count]['id']."' ");

				// validate mx
				if(checkdnsrr($data[$count]['domain'], 'MX')){
					// do nothing, its active and has a valid MX record
					console_output(
						$colors->getColoredString(
							number_format($count) . ') "' . $data[$count]['domain'] . '" is active and has valid MX record.', 
						"green", "black"));
				}else{
					console_output(
						$colors->getColoredString(
							number_format($count) . ') "' . $data[$count]['domain'] . '" is active but has no MX record.', 
						"blue", "black"));
					$update = $db->exec("UPDATE `email_domains` SET `status` = 'mxserver_does_not_exist' WHERE `id` = '".$data[$count]['id']."' ");

				}
			}else{
				// domain does not exist
				console_output(
						$colors->getColoredString(
							number_format($count) . ') "' . $data[$count]['domain'] . '" is not active.', 
						"red", "black"));
				$update = $db->exec("UPDATE `email_domains` SET `status` = 'domain_does_not_exist' WHERE `id` = '".$data[$count]['id']."' ") or die(mysql_error());
			}

			$update = $db->exec("UPDATE `email_domains` SET `last_checked` = '".time()."' WHERE `id` = '".$data[$count]['id']."' ") or die(mysql_error());
			
			$count++;
		}
	}
}

if($task == 'wash_emails')
{
	$records                = $argv[2];
	$threads 				= $argv[3];
	
	console_output("Spawning ".$threads." children.");
	
	$pids = array();
			
	for ( $i = 0; $i < $threads; $i++ ) 
	{
		$pids[$i] = pcntl_fork();

		if ( !$pids[$i] ) 
		{
			include($base.'../inc/db.php');
			
			$records                = $argv[2];
			$search_records         = $records;
			$random_start_point		= rand(000000,999999);

			$query = $db->query("SELECT `domain` FROM `email_domains` WHERE  `status` = 'active' ");
	    	$domains_array = $query->fetchAll(PDO::FETCH_ASSOC);

	    	foreach($domains_array as $domain){
	    		$domains[] = $domain['domain'];
	    	}

			$query = $db->query("SELECT `id`,`email` FROM `emails` WHERE  `last_checked` IS NULL LIMIT ".$random_start_point.",".$search_records);
	    	$rows = $query->fetchAll(PDO::FETCH_ASSOC);

	    	$count = 1;
			
			foreach($rows as $row){
				$data[$count]['id']                    	= $row['id'];
				$data[$count]['email']                	= $row['email'];
				$bits 									= explode("@", $row['email']);
				$data[$count]['domain']                	= $bits[1];

				if (in_array($data[$count]['domain'], $domains)) {
					// domain is active, lets mark checked_dns and checked_mx
					$update = $db->exec("UPDATE `emails` SET `checked_dns` = '1' WHERE `id` = '".$data[$count]['id']."' ");
					$update = $db->exec("UPDATE `emails` SET `checked_mx` = '1' WHERE `id` = '".$data[$count]['id']."' ");

					console_output(
						$colors->getColoredString(
							number_format($count) . ') "' . $data[$count]['email'] . '" passed DNS and MX checks.', 
						"green", "black"));
				}else{
					console_output(
						$colors->getColoredString(
							number_format($count) . ') "' . $data[$count]['email'] . '" FAILED DNS and MX checks.', 
						"red", "black"));
				}

				$update = $db->exec("UPDATE `emails` SET `last_checked` = '".time()."' WHERE `id` = '".$data[$count]['id']."' ");

				$count++;
			}
			
			exit();
		}
	}
	
	for ( $i = 0; $i < $threads; $i++ ) 
	{
		pcntl_waitpid($pids[$i], $status, WUNTRACED);
	}
}

if($task == 'domain_checker_multi')
{
	$records                = $argv[2];
	$threads 				= $argv[3];
	
	// require $base.'../inc/cron.helper.php';
	// if ( ( $pid = cronHelper::lock() ) !== FALSE ) 
	// {
	console_output("Spawning ".$threads." children.");
	
	$pids = array();
			
	for ( $i = 0; $i < $threads; $i++ ) 
	{
		$pids[$i] = pcntl_fork();

		if ( !$pids[$i] ) 
		{
			
			include($base.'../inc/db.php');
			
			$records                = $argv[2];
			$search_records         = $records;

			$query = $db->query("SELECT * FROM `email_domains` WHERE  `last_checked` IS NULL OR  `last_checked` = '0' ORDER BY RAND() LIMIT ".$search_records);
	    	$rows = $query->fetchAll(PDO::FETCH_ASSOC);

	    	$count = 1;
			
			foreach($rows as $row){
				$data[$count]['id']                    = $row['id'];
				$data[$count]['domain']                = $row['domain'];
				$data[$count]['status']                = $row['status'];
				
				if(checkdnsrr($data[$count]['domain'], 'ANY')){
					// domain exists, lets set to active then check MX records
					$update = $db->exec("UPDATE `email_domains` SET `status` = 'active' WHERE `id` = '".$data[$count]['id']."' ");

					// validate mx
					if(checkdnsrr($data[$count]['domain'], 'MX')){
						// do nothing, its active and has a valid MX record
						console_output(
							$colors->getColoredString(
								number_format($count) . ') "' . $data[$count]['domain'] . '" is active and has valid MX record.', 
							"green", "black"));
					}else{
						console_output(
							$colors->getColoredString(
								number_format($count) . ') "' . $data[$count]['domain'] . '" is active but has no MX record.', 
							"blue", "black"));
						$update = $db->exec("UPDATE `email_domains` SET `status` = 'mxserver_does_not_exist' WHERE `id` = '".$data[$count]['id']."' ");

					}
				}else{
					// domain does not exist
					console_output(
							$colors->getColoredString(
								number_format($count) . ') "' . $data[$count]['domain'] . '" is not active.', 
							"red", "black"));
					$update = $db->exec("UPDATE `email_domains` SET `status` = 'domain_does_not_exist' WHERE `id` = '".$data[$count]['id']."' ");
				}

				$update = $db->exec("UPDATE `email_domains` SET `last_checked` = '".time()."' WHERE `id` = '".$data[$count]['id']."' ");
				
				$count++;
			}
			
			exit();
		}
	}
	
	for ( $i = 0; $i < $threads; $i++ ) 
	{
		pcntl_waitpid($pids[$i], $status, WUNTRACED);
	}
	// }else{
	// 	console_output(
	// 		$colors->getColoredString(
	// 			"Script already running", 
	// 		"pink", "black"));
	// }
}

if($task == 'check_role_accounts')
{
	$records                = $argv[2];
	$threads 				= $argv[3];
	
	console_output("Spawning ".$threads." children.");
	
	$pids = array();
			
	for ( $i = 0; $i < $threads; $i++ ) 
	{
		$pids[$i] = pcntl_fork();

		if ( !$pids[$i] ) 
		{
			include($base.'../inc/db.php');

			// get list of role accounts
			$query = $db->query("SELECT * FROM `role_accounts` ");
	    	$role_accounts_bits = $query->fetchAll(PDO::FETCH_ASSOC);

	    	foreach($role_accounts_bits as $bits)
	    	{
	    		$role_accounts[] = $bits['role_account'];
	    	}
			
			$records                = $argv[2];
			$search_records         = $records;

			$query = $db->query("SELECT * FROM `emails` WHERE  `checked` = '0' LIMIT ".$search_records);
	    	$rows = $query->fetchAll(PDO::FETCH_ASSOC);

	    	$count = 1;
			
			foreach($rows as $row){
				$data[$count]['id']                    	= $row['id'];
				$data[$count]['email']                	= $row['email'];
				$data[$count]['bits']                	= explode("@", $row['email']);
				$data[$count]['user']					= $data[$count]['bits'][0];
				$data[$count]['domain']					= $data[$count]['bits'][1];
				
				if(in_array($data[$count]['email'], $role_accounts)){
					// match found in role accounts, flag email as bad

					console_output(
						$colors->getColoredString(
							number_format($count) . ') "' . $data[$count]['email'] . '" is a role account.', 
						"red", "black"));

					$update = $db->exec("UPDATE `emails` SET `checked_role_account` = 'failed' WHERE `id` = '".$data[$count]['id']."' ");
				}else{

					console_output(
						$colors->getColoredString(
							number_format($count) . ') "' . $data[$count]['email'] . '" is not a role account.', 
						"green", "black"));

					$update = $db->exec("UPDATE `emails` SET `checked_role_account` = 'passwd' WHERE `id` = '".$data[$count]['id']."' ");
				}

				$update = $db->exec("UPDATE `emails` SET `checked` = '".time()."' WHERE `id` = '".$data[$count]['id']."' ");
				
				$count++;
			}
			
			exit();
		}
	}
	
	for ( $i = 0; $i < $threads; $i++ ) 
	{
		pcntl_waitpid($pids[$i], $status, WUNTRACED);
	}
}

if($task == 'bulk_import_bounces')
{
	$sql="
	LOAD DATA LOCAL INFILE 'file.csv' IGNORE INTO TABLE `global_blacklist` 
	FIELDS 
		TERMINATED BY ',' 
		ENCLOSED BY '\"' 
	LINES 
		TERMINATED BY '\n' 
	(@col1, @cal2) 
	SET `email` = @col1, `reason` = @col2
	";

	$result = mysql_query($sql) or die(mysql_error());
}

if($task == 'update_totals')
{
	require $base.'../inc/cron.helper.php';
	if ( ( $pid = cronHelper::lock() ) !== FALSE ) {
		$query 		= "SELECT `status` AS 'email_status' FROM `emails` GROUP BY `status` ";
		$result		= mysql_query($query) or die(mysql_error());
		while($row = mysql_fetch_array($result)){
			$status_values[]			= $row['email_status'];
		}

		$query_1 		= "SELECT * FROM `email_lists` ORDER BY `id` ";
		$result_1 		= mysql_query($query_1) or die(mysql_error());
		while($row_1 = mysql_fetch_array($result_1)){
			$data['id'] 				= $row_1['id'];
			$data['name'] 				= stripslashes($row_1['name']);

			console_output('List ID: '.$data['id']);
			console_output('List Name: '.$data['name']);

			foreach ( $status_values as $status_value ) {


				$query_2 					= "SELECT `id` FROM `emails` WHERE `list_id` = '".$data['id']."' AND `status` = '".$status_value."' ";
				$result_2 					= mysql_query($query_2) or die(mysql_error());
				$total						= mysql_num_rows($result_2);

				$query_3					= "UPDATE `email_lists` SET `".$status_value."` = '".$total."' WHERE `id` = '".$data['id']."' ";
				mysql_query($query_3) or die(mysql_error());

				console_output($status_value . ' ' . $total);
			}

			console_output("==============================");
		}
	}
}

if($task == 'get_domains')
{

	$records                = $argv[3];
	$threads 				= $argv[2];

	console_output("Spawning ".$threads." children with ".$records." jobs each.");

	$pids = array();
				
	for ( $i = 0; $i < $threads; $i++ ) 
	{
		$pids[$i] = pcntl_fork();

		if ( !$pids[$i] ) 
		{
			
			include($base.'../inc/db.php');
			
			$records                = $argv[3];
			$random_start_point		= rand(000000,999999);
			
			$query = $db->query("SELECT `domain` FROM `emails` WHERE `domain_added_to_list` = '0' LIMIT ".$random_start_point.",".$records);
		    $domains_array = $query->fetchAll(PDO::FETCH_ASSOC);
			
			foreach($domains_array as $domain){
	    		$domains[] = $domain['domain'];
	    	}
			
			$count = count( $domains );
			
			foreach ( $domains as $domain ) {
				$insert = $db->exec("INSERT IGNORE INTO `email_domains` 
					(`domain`)
					VALUE
					('".$domain."')");

				console_output(number_format($count) . ") ".$domain);

				$count = $count - 1;
			}

			exit();
		}
	}

	for ( $i = 0; $i < $threads; $i++ ) 
	{
		pcntl_waitpid($pids[$i], $status, WUNTRACED);
	}
}

if($task == 'get_domains_2')
{
	$records                = $argv[2];
	require $base.'../inc/cron.helper.php';
	if ( ( $pid = cronHelper::lock() ) !== FALSE ) {
		$query 		= "SELECT `id` FROM `emails` WHERE `domain` = '' ";
		$result		= mysql_query($query) or die(mysql_error());
		$remaining_records_to_process		= mysql_num_rows($result);
		
		console_output("Remaining records to process: ".number_format ( $remaining_records_to_process ) );
		
		console_output("Getting ".number_format ( $records ) ." email addresses.");
		
		$query 		= "SELECT `id`,`email`,`domain` FROM `emails` WHERE `domain` = '' ORDER BY `id` LIMIT ".$records;
		$result		= mysql_query($query) or die(mysql_error());
		$count		= mysql_num_rows($result);
		while($row = mysql_fetch_array($result)){
			$id					= $row['id'];
			$bits				= explode("@", $row['email']);
			$domains[]		= $bits[1];			
		}
		
		echo "\n";
		
		console_output("Done getting email addresses.");
		
		console_output("Total Domains: " . number_format ( count ( $domains ) ) ) ;
		$domains = array_unique($domains);
		console_output("Unique Domains: " . number_format ( count ( $domains ) ) ) ;
		
		$count = count( $domains );
		
		foreach ( $domains as $domain ) {
			mysql_query("UPDATE `emails` SET `domain` = '".$domain."' WHERE `email` LIKE '%@".$domain."' AND `domain` = '';") or die(mysql_error());
			
			$input = mysql_query("INSERT IGNORE INTO `domains` 
				(`domain`, `status`, `last_checked`)
				VALUE
				('".$domain."', 'pending', '1')") or die(mysql_error());

			console_output ( number_format ( $count ) . ") ".$domain);

			$count = $count - 1;
		}
	}
}

if($task == 'import_multi')
{
	$threads 				= $argv[2];
	
	console_output("Spawning ".$threads." children.");
	
	for ($i=0; $i<$runs; $i++) {
        for ($j=0; $j<$count; $j++) {
            $pipe[$j] = popen("php -q run apt_update_process ".$slaves[$j], 'w');
        }
        
        // wait for them to finish
        for ($j=0; $j<$count; ++$j) {
            pclose($pipe[$j]);
        }
    }
}

// close the msyql / pdo connection
$db=null;

console_output("Finished.");