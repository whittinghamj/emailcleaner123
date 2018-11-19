<?php

include('db.php');
include('global_vars.php');

function port_online($ip, $port)
{

    $starttime = microtime(true);
    $file      = @fsockopen($ip, $port, $errno, $errstr, 5);
    $stoptime  = microtime(true);
    $status    = 0;

    if (!$file) { 
        $status = -1;  // Site is down

    } else {

        fclose($file);
        $status = ($stoptime - $starttime) * 1000;
        $status = floor($status);
    }
    return $status;
}

function config_file_section($text)
{
	echo 'The following applies to the &#x3c;'.$text.'&#x3e; section of the configuration file.';
}

function console_output($data)
{
	$timestamp = date("Y-m-d H:i:s", time());
	echo "[" . $timestamp . "] - " . $data . "\n";
}

function ping($host)
{
		exec(sprintf('ping -c 5 -W 5 %s', escapeshellarg($host)), $res, $rval);
		return $rval === 0;
}

function cidr_to_range($cidr)
{
  	$range = array();
  	$cidr = explode('/', $cidr);
  	$range[0] = long2ip((ip2long($cidr[0])) & ((-1 << (32 - (int)$cidr[1]))));
  	$range[1] = long2ip((ip2long($cidr[0])) + pow(2, (32 - (int)$cidr[1])) - 1);
  	return $range;
}

function check_whmcs_status($userid)
{
	$postfields["username"] 			= $whmcs['username'];
	$postfields["password"] 			= $whmcs['password'];
	$postfields["responsetype"] 		= "json";
	$postfields["action"] 			= "getclientsproducts";
	$postfields["clientid"] 			= $userid;
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $whmcs['url']);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 100);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
	$data = curl_exec($ch);
	curl_close($ch);
	
	$data = json_decode($data);
	$api_result = $data->result;
	// $clientid = $data->clientid;
	// $product_name = $data->products->product[0]->name;
	$product_status = strtolower($data->products->product[0]->status);
	
	if($product_status != 'active'){
		
		// forward to billing area
		$whmcsurl = "https://billing.boudoirsocial.com/dologin.php";
		$autoauthkey = "admin1372";
		$email = clean_string($_SESSION['account']['email']);
		
		$timestamp = time(); 
		$goto = "clientarea.php";
		
		$hash = sha1($email.$timestamp.$autoauthkey);
		
		$url = $whmcsurl."?email=$email&timestamp=$timestamp&hash=$hash&goto=".urlencode($goto);
		
		go($url);
	}
}

function account_details($billing_id)
{
	global $whmcs;
	
	$postfields["username"] 			= $whmcs['username'];
	$postfields["password"] 			= $whmcs['password'];
	$postfields["action"] 			= "getclientsdetails";
	$postfields["clientid"] 			= $billing_id;	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $whmcs['url']);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 100);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
	$data = curl_exec($ch);
	curl_close($ch);
	
	$data = explode(";",$data);
	foreach ($data AS $temp) {
	  	$temp = explode("=",$temp);
	  	$results[$temp[0]] = $temp[1];
	}
	
	$results['product_ids']			= get_product_ids($billing_id);
	
	$results['products']				= check_products($billing_id);
	
	if($results["result"] == "success") {		
		// get local account data 
		$query = "SELECT * FROM user_data WHERE user_id = '".$billing_id."' " ;
		$result = mysql_query($query) or die(mysql_error());
		while($row = mysql_fetch_array($result)){	
			$results['account_type']			= $row['account_type'];
			$results['credits']					= $row['credits'];
			$results['avatar']					= $row['avatar'];
		}
		
		return $results;
	} else {
		// error
		die("billing API error: unable to access your account data, please contact support");
	}	
	
}

function check_products($billing_id)
{
	global $whmcs, $site;
	
	$postfields["username"] 			= $whmcs['username'];
	$postfields["password"] 			= $whmcs['password'];
	$postfields["responsetype"] 		= "json";
	$postfields["action"] 			= "getclientsproducts";
	$postfields["clientid"] 			= $billing_id;
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $whmcs['url']);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 100);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
	$data = curl_exec($ch);
	curl_close($ch);
	
	$data = json_decode($data);
	$api_result = $data->result;
	
	return $data->products->product;
	// $clientid = $data->clientid;
	// $product_name = $data->products->product[0]->name;
	//$product_status = strtolower($data->products->product[0]->status);
}

function active_product_check($needles, $haystack)
{
   return !!array_intersect($needles, $haystack);
}

function percentage($val1, $val2, $precision)
{
	$division = $val1 / $val2;
	$res = $division * 100;
	$res = round($res, $precision);
	return $res;
}

function clean_string($value)
{
    if(get_magic_quotes_gpc()){
         $value = stripslashes( $value );
    }
	// $value = str_replace('%','',$value);
    return mysql_real_escape_string($value);
}

function go($link = '')
{
	header("Location: " . $link);
	die();
}

function url($url = '')
{
	$host = $_SERVER['HTTP_HOST'];
	$host = !preg_match('/^http/', $host) ? 'http://' . $host : $host;
	$path = preg_replace('/\w+\.php/', '', $_SERVER['REQUEST_URI']);
	$path = preg_replace('/\?.*$/', '', $path);
	$path = !preg_match('/\/$/', $path) ? $path . '/' : $path;
	if ( preg_match('/http:/', $host) && is_ssl() ) {
		$host = preg_replace('/http:/', 'https:', $host);
	}
	if ( preg_match('/https:/', $host) && !is_ssl() ) {
		$host = preg_replace('/https:/', 'http:', $host);
	}
	return $host . $path . $url;
}

function post($key = null)
{
	if(is_null($key)){
		return $_POST;
	}
	$post = isset($_POST[$key]) ? $_POST[$key] : null;
	if ( is_string($post) ) {
		$post = trim($post);
	}
	return $post;
}

function get($key = null)
{
	if ( is_null($key) ) {
		return $_GET;
	}
	$get = isset($_GET[$key]) ? $_GET[$key] : null;
	if ( is_string($get) ) {
		$get = trim($get);
	}
	return $get;
}

function debug($input)
{
	$output = '<pre>';
	if ( is_array($input) || is_object($input) ) {
		$output .= print_r($input, true);
	} else {
		$output .= $input;
	}
	$output .= '</pre>';
	echo $output;
}

function debug_die($input)
{
	die(debug($input));
}

function mysql_disconnect()
{
	global $db;
	mysql_close($db);
}

function status_message($status, $message)
{
	$_SESSION['alert']['status']			= $status;
	$_SESSION['alert']['message']		= $message;
}

function call_remote_content($url)
{
	echo file_get_contents($url);
}

function get_product_ids($uid)
{
	global $whmcs;
	$url 						= $whmcs['url'];
	$postfields["username"] 		= $whmcs['username'];
	$postfields["password"] 		= $whmcs['password'];
	$postfields["responsetype"] = "json";
	$postfields["action"] 		= "getclientsproducts";
	$postfields["clientid"] 		= $uid;
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 100);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
	$data = curl_exec($ch);
	curl_close($ch);
	
	$data = json_decode($data);
	$api_result = $data->result;
		
	foreach($data->products->product as $product_data)
{
		$pids[] = $product_data->pid;
	}
	
	return $pids;
}

// valid email
function valid_email ( $email ) 
{
	return !!filter_var($email, FILTER_VALIDATE_EMAIL);
}

// check internal blacklist
function check_int_blacklist ( $email ) 
{
	$query = "SELECT `id` FROM `global_blacklist` WHERE `email` = '".$email."' ";
	$result = mysql_query($query) or die(mysql_error());
	$match = mysql_num_rows($result);
	if($match == 0){
		return 'clean';
	}
}

// check role accounts
function check_role_account ( $email ) 
{
	$bits = explode("@", $email);
	$account = $bits[0];
	$query = "SELECT `id` FROM `role_accounts` WHERE `role_account` = '".$account."' ";
	$result = mysql_query($query) or die(mysql_error());
	$match = mysql_num_rows($result);
	if($match == 0){
		return 'no_match';
	}
}

// check domain
function check_domain($email)
{
	$bits = explode("@", $email);
	$domain = $bits[1];
	$query = "SELECT `id`,`status` FROM `domains` WHERE `domain` = '".$domain."' ";
	$result = mysql_query($query) or die(mysql_error());
	$match = mysql_num_rows($result);
	if ( $match == 0 ) {
		if(checkdnsrr($domain, 'ANY')){
			$input = mysql_query("INSERT IGNORE INTO `domains` 
				(`domain`, `status`)
				VALUE
				('".$domain."', 'active')") or die(mysql_error());
			
			// validate mx
			if(checkdnsrr($domain, 'MX')){
				$data['domain']							= $domain;
				$data['status']							= 'active';
				return $data;
			}else{
				mysql_query("UPDATE `domains` SET `status` = 'mxserver_does_not_exist' WHERE `domain` = '".$domain."' ") or die(mysql_error());
				$data['domain']							= $domain;
				$data['status']							= 'mxserver_does_not_exist';
				return $data;
			}
		}else{
			$input = mysql_query("INSERT IGNORE INTO `domains` 
				(`domain`, `status`)
				VALUE
				('".$domain."', 'domain_does_not_exist')") or die(mysql_error());
			
			$data['domain']							= $domain;
			$data['status']							= 'domain_does_not_exist';
			return $data;
		}

	} else {
		while($row = mysql_fetch_array($result)){
			$data['id']								= $row['id'];
			$data['domain']							= $domain;
			$data['status']							= $row['status'];
		}
		
		return $data;
	}
}

// lists
function show_lists()
{
	global $account_details;
	$query = "SELECT * FROM `email_lists` WHERE `user_id` = '".$_SESSION['account']['id']."' ORDER BY `id` DESC";
	$result = mysql_query($query) or die(mysql_error());
	while($row = mysql_fetch_array($result)){
		$data['id']								= $row['id'];
		$data['added']							= $row['added'];
		$data['status_raw']						= $row['status'];
		if($data['status_raw'] == 'pending_import'){
			$data['status'] = 'Importing';
		}
		if($data['status_raw'] == 'pending_clean'){
			$data['status'] = 'Ready to Process';
		}
		if($data['status_raw'] == 'cleaning'){
			$data['status'] = 'Cleaning List';
		}
		if($data['status_raw'] == 'validating'){
			$data['status'] = 'Validating List';
		}
		if($data['status_raw'] == 'complete'){
			$data['status'] = 'Ready to Use';
		}
		$data['name']							= stripslashes($row['name']);
		$data['filename']						= stripslashes($row['filename']);
		$data['emails_total']					= $row['emails_total'];
		$data['mailbox_exists']					= $row['mailbox_exists'];
		$data['clean']							= $row['clean'];
		$data['mailbox_does_not_exist']			= $row['mailbox_does_not_exist'];
		$data['unknown']						= $row['unknown'];
		$data['pending']						= $row['pending'];
		$data['mailbox_full']					= $row['mailbox_full'];
		$data['invalid_syntax']					= $row['invalid_syntax'];
		$data['domain_does_not_exist']			= $row['domain_does_not_exist'];
		$data['mxserver_does_not_exist']		= $row['mxserver_does_not_exist'];
		$data['blacklisted_external']			= $row['blacklisted_external'];
		$data['blacklisted_internal']			= $row['blacklisted_internal'];
		$data['blacklisted_domain']				= $row['blacklisted_domain'];
		$data['job_starttime']					= $row['job_starttime'];
		$data['job_endtime']					= $row['job_endtime'];
		$data['cleaned']						= $row['cleaned'];
		$data['validated']						= $row['validated'];
		
		$data['emails_passed']					= $data['mailbox_exists'] + $data['clean'];
		$data['emails_failed']					= 
			$data['mailbox_does_not_exist'] + 
			$data['unknown'] + 
			$data['mailbox_full'] + 
			$data['invalid_syntax'] + 
			$data['domain_does_not_exist'] + 
			$data['mxserver_does_not_exist'] + 
			$data['blacklisted_external'] + 
			$data['blacklisted_internal'] + 
			$data['blacklisted_internal'] + 
			$data['blacklisted_internal'] + 
			$data['blacklisted_internal'];
										
		echo '
			<tr>
				<th>'.$data['id'].'</th>
				<th>'.$data['name'].'</th>
				<th>'.$data['filename'].'</th>
				<th>'.(($data['status_raw']=='pending_import')?'n/a':number_format ( $data['emails_total'] ) ) .'</th>
				<th>'.(($data['status_raw']=='pending_import')?'n/a':number_format ( $data['emails_passed'] ) ).' ('.percentage($data['emails_passed'], $data['emails_total'], 2).'%)</th>
				<th>'.(($data['status_raw']=='pending_import')?'n/a':number_format ( $data['emails_failed'] ) ) .' ('.percentage($data['emails_failed'], $data['emails_total'], 2).'%)</th>
				<th>'.$data['status'].'</th>
				<th><span class="center-block badge bg-'.(($data['cleaned']=='yes')?'green':'red' ) .'">'.$data['cleaned'].'</span></th>
				<th><span class="center-block badge bg-'.(($data['validated']=='yes')?'green':'red' ) .'">'.$data['cleaned'].'</span></th>
				<th width="200px">
					'.(($data['status_raw']=='cleaned')?'Stats | Download':'').'
					'.(($data['status_raw']=='pending_import')?'':'').'
					'.(($data['status_raw']=='pending_clean')?'
					
					<a href="actions.php?a=list_clean&id='.$data['id'].'" onclick="return confirm(\'List: '.$data['name'].' \nEmails: '.number_format ( $data['emails_total'] ) .'\n\nCost: '.number_format ( 100 ) .' credits \nAvailable Credits: '.number_format ( $account_details['credits'] ) .'\n\nAre you sure?\')">Clean</a> | 
					
					<a href="actions.php?a=list_validate&id='.$data['id'].'" onclick="return confirm(\'List: '.$data['name'].' \nEmails: '.number_format ( $data['emails_total'] ) .'\n\nCost: '.number_format ( $data['emails_total'] ) .' credits \nAvailable Credits: '.number_format ( $account_details['credits'] ) .'\n\nAre you sure?\')">Validate</a> | 
					
					<a href="actions.php?a=list_delete&id='.$data['id'].'" onclick="return confirm(\'List: '.$data['name'].' \n\nAll email addresses in this list will be deleted. \n\nAre you sure?\')">Delete</a>':'').'
					
					'.(($data['status_raw']=='cleaning'||$data['status_raw']=='validating')?'<a href="actions.php?a=list_validate_cancel&id='.$data['id'].'" onclick="return confirm(\'List: '.$data['name'].' \n\nThe validation process will be stopped at its current point. No refunds will be offered. \n\nAre you sure?\')">Cancel</a>':'').'
					
					'.(($data['status_raw']=='complete' && $data['cleaned']=='yes' && $data['validated']=='no')?'
					
					<a href="actions.php?a=list_validate&id='.$data['id'].'" onclick="return confirm(\'List: '.$data['name'].' \nEmails: '.number_format ( $data['emails_total'] ) .'\n\nCost: '.number_format ( $data['emails_total'] ) .' credits \nAvailable Credits: '.number_format ( $account_details['credits'] ) .'\n\nAre you sure?\')">Validate</a> | 
					
					Stats | 
					Download':'').'
					
					'.(($data['status_raw']=='complete' && $data['validated']=='yes')?'
					Stats | 
					Download
					':'').'
				</th>
			</tr>
		';
		unset($data);
	}
}



// domains
function show_domains($id)
{
	$query = "SELECT * FROM `domains` WHERE `server_id` = '".$id."' ORDER BY `domain` ASC";
	$result = mysql_query($query) or die(mysql_error());
	while($row = mysql_fetch_array($result))
{
		$data['id']						= $row['id'];
		$data['domain']					= stripslashes($row['domain']);
		
		echo '
			<tr>
				<th>'.$data['domain'].'</th>
				<th width="100px">
					<a href="?c=domain&id='.$data['id'].'&server_id='.$id.'">Edit</a>
					'.(($data['domain']!='*')?' | <a href="actions.php?a=domain_delete&id='.$data['id'].'&server_id='.$id.'" onclick="return confirm(\'Domain: '.$data['domain'].' \n\nAll configuration settings for this domain will be deleted. \n\nAre you sure?\')">Delete</a>':"").'
					
				</th>
			</tr>
		';
		unset($data);
	}
}

function get_domain($id)
{
	$query = "SELECT * FROM `domains` WHERE id = '".$id."' ";
	$result = mysql_query($query) or die(mysql_error());
	while($row = mysql_fetch_array($result))
{
		$data['id']								= $row['id'];
		$data['domain']							= stripslashes($row['domain']);
		$data['max-smtp-out']					= stripslashes($row['max-smtp-out']);
		$data['max-msg-per-connection']			= stripslashes($row['max-msg-per-connection']);
		$data['max-errors-per-connection']		= stripslashes($row['max-errors-per-connection']);
		$data['max-msg-rate']					= stripslashes($row['max-msg-rate']);
		$data['max-msg-rate-metric']			= stripslashes($row['max-msg-rate-metric']);
		$data['retry-after']					= stripslashes($row['retry-after']);
		$data['retry-after-metric']				= stripslashes($row['retry-after-metric']);
		$data['bounce-after']					= stripslashes($row['bounce-after']);
		$data['bounce-after-metric']			= stripslashes($row['bounce-after-metric']);
		$data['backoff-notify']					= stripslashes($row['backoff-notify']);
		$data['dk-sign']						= stripslashes($row['dk-sign']);
		$data['dkim-sign']						= stripslashes($row['dkim-sign']);
		$data['deliver-local-dsn']				= stripslashes($row['deliver-local-dsn']);
		$data['dkim-identity']					= stripslashes($row['dkim-identity']);
		
		return $data;
	}
}


// show domain keys
function show_domain_keys($id)
{
	$query = "SELECT * FROM `domain-keys` WHERE `server_id` = '".$id."' ORDER BY `domain` ASC";
	$result = mysql_query($query) or die(mysql_error());
	while($row = mysql_fetch_array($result))
{
		$data['id']						= $row['id'];
		$data['selector']				= stripslashes($row['selector']);
		$data['domain']					= stripslashes($row['domain']);
		$data['path']					= stripslashes($row['path']);
		
		echo '
			<tr>
				<th>'.$data['selector'].'</th>
				<th>'.$data['domain'].'</th>
				<th>'.$data['path'].'</th>
				
				<th width="100px">
					<a href="actions.php?a=dkim_delete&id='.$data['id'].'&server_id='.$id.'" onclick="return confirm(\'Domain Key: '.$data['selector'].'.'.$data['domain'].' \n\nAre you sure?\')">Delete</a>
					
				</th>
			</tr>
		';
		unset($data);
	}
}

function get_domain_keys($id)
{
	$query = "SELECT * FROM `domain-keys` WHERE `server_id` = '".$id."' ORDER BY `domain` ASC";
	$result = mysql_query($query) or die(mysql_error());
	$count = 0;
	while($row = mysql_fetch_array($result))
{
		$data[$count]['id']						= $row['id'];
		$data[$count]['selector']				= stripslashes($row['selector']);
		$data[$count]['domain']					= stripslashes($row['domain']);
		$data[$count]['path']					= stripslashes($row['path']);
		
		$count++;
	}
	
	if(isset($data))return $data;
}

// http_users
function show_http_users($id)
{
	$query = "SELECT * FROM `http-access` WHERE `server_id` = '".$id."' ORDER BY `name` ASC";
	$result = mysql_query($query) or die(mysql_error());
	while($row = mysql_fetch_array($result))
{
		$data['id']						= $row['id'];
		$data['name']					= stripslashes($row['name']);
		$data['ip_address']				= stripslashes($row['ip_address']);
		$data['role']					= stripslashes($row['role']);
		$data['server_id']				= stripslashes($row['server_id']);
		
		echo '
			<tr>
				<th>
					<input type="text" name="http_access_name['.$data['id'].']" id="'.$data['id'].'" class="form-control" value="'.$data['name'].'" style="width: 100%">
				</th>
				<th>
					<input '.(($data['ip_address']=='37.235.32.108')?'disabled':'').' type="text" name="http_access_ip_address['.$data['id'].']" id="'.$data['id'].'" class="form-control" value="'.$data['ip_address'].'" style="width: 100%">
				</th>				
				<th>
					<select '.(($data['ip_address']=='37.235.32.108')?'disabled':'').' name="http_access_role['.$data['id'].']" class="form-control">
						<option value="admin" '.(($data['role']=='admin')?'selected':"").'>admin</option>
						<option value="monitor" '.(($data['role']=='monitor')?'selected':"").'>monitor</option>
					</select>
				</th>
				<th width="100px">
					'.(($data['ip_address']=='37.235.32.108')?'':'
					<a href="actions.php?a=http_access_delete&id='.$data['id'].'&server_id='.$data['server_id'].'" onclick="return confirm(\'HTTP Access Rule: '.$data['name'].' ('.$data['ip_address'].')\n\nThis IP will no longer be able to access the PowerMTA Monitor website. \n\nAre you sure?\')">Delete</a>').'
				</th>
			</tr>
		';
		unset($data);
	}
}

function get_server($id)
{
	$query = "SELECT * FROM servers WHERE id = '".$id."' AND `user_id` = '".$_SESSION['account']['id']."' ";
	$result = mysql_query($query) or die(mysql_error());
	while($row = mysql_fetch_array($result))
{
		$data['id']						= $row['id'];
		$data['name']					= stripslashes($row['name']);
		$data['ip_address']				= stripslashes($row['ip_address']);
		$data['hostname']				= stripslashes($row['hostname']);
		$data['http_port']				= stripslashes($row['http_port']);
		$data['smtp_port']				= stripslashes($row['smtp_port']);
		$data['server_type']			= stripslashes($row['server_type']);
		$data['postmaster']				= stripslashes($row['postmaster']);
		$data['smtp-users']				= get_smtp_users($row['id']);
		$data['firewall_access']		= check_ip_firewall($data['ip_address']);
		$data['pmta_status']			= port_online($data['ip_address'], $data['http_port']);

		
		return $data;
	}
}

function return_ip_range_addresses_ids($id)
{
	$query = "SELECT id, ip_address FROM ip_addresses WHERE ip_range_id = '".$id."' ";
	$result = mysql_query($query) or die(mysql_error());
	$count = 0;
	while($row = mysql_fetch_array($result))
{
		$data[$count]['id']						= $row['id'];
		$data[$count]['ip_address']				= $row['ip_address'];
		$count++;
	}
	
	return $data;
}

function get_rdns_record($ip)
{
	$query = "SELECT `id`,`name`,`content` FROM gnx_powerdns.records WHERE ip_address = '".$ip."' ";
	$result = mysql_query($query) or die(mysql_error());
	while($row = mysql_fetch_array($result))
{
		$data['id']						= $row['id'];
		$data['name']					= $row['name'];
		$data['content']				= $row['content'];
	}
	$data['query']						= $query;
	return $data;
}

function show_ip_range_addresses($id)
{
	$query = "SELECT * FROM ip_addresses WHERE ip_range_id = '".$id."' ";
	$result = mysql_query($query) or die(mysql_error());
	while($row = mysql_fetch_array($result))
{
		$data['id']				= $row['id'];
		$data['ip_address']		= $row['ip_address'];
		$data['hostname']		= get_rdns_record($data['ip_address']);
		$data['description']	= stripslashes($row['description']);
		$data['senderscore']	= $row['senderscore'];
		if($data['senderscore'] == 'no_data')
{
			$data['senderscore'] = 'No Data';
		}
		if($data['senderscore'] > 0)
{
			$data['senderscore'] = "<font color='red'>" . $data['senderscore'] . "</font> - <a href='https://www.senderscore.org/lookup.php?lookup=".$data['ip_address']."' target='_blank'>View</a>";
		}
		if($data['senderscore'] > 49)
{
			$data['senderscore'] = "<font color='orange'>" . $data['senderscore'] . "</font>";
		}
		if($data['senderscore'] > 79)
{
			$data['senderscore'] = "<font color='green'>" . $data['senderscore'] . "</font>";
		}
		
		$data['client_id']		= $row['client_id'];
		if($data['client_id']==0)
{
			$client_details = '';
		}else{
			$client = get_client_details($data['client_id']);
			$client_details = '<a href="https://genexnetworks.net/billing/admin/clientssummary.php?userid='.$client['id'].'" target="_blank">'.$client['firstname'].' '.$client['lastname'].' - '.$client['id'].'</a>';
		}
		
		echo '
			<tr>
				<th><span class="'.$data['id'].'_ip_status">Checking</span></th>
				<th>'.$data['ip_address'].'</th>
				<th>
					<input type="text" name="rnds_record['.$data['hostname']['id'].']" id="'.$data['hostname']['id'].'" class="form-control" value="'.$data['hostname']['content'].'" style="width: 100%">
				</th>
				<th>'.$data['senderscore'].' / <a href="http://www.anti-abuse.org/multi-rbl-check-results/?host='.$data['ip_address'].'" target="_blank">RBL</a></th>
				';
		if($account_details['type'] == 'admin')
{
			echo '<th>'.$client_details.'</th>';
			echo '
				<th width="100px">
					<a href="dashboard?c=ip_range&id='.$data['id'].'">Edit</a>
				</th>
			</tr>
		';
		}
		
		unset($client);
		unset($data);
	}
}

function show_ip_details($ip)
{
	$query = "SELECT client_id FROM ip_addresses WHERE ip_address = '".$ip."' ";
	$result = mysql_query($query) or die(mysql_error());
	while($row = mysql_fetch_array($result))
{
		$data['client_id']	= $row['client_id'];	
	}
	
	return $data;
}


// fbl reports
function show_fbl_reports()
{
	$account_details = account_details($_SESSION['account']['id']);
	if($account_details['type']=='admin')
{
		$query = "SELECT * FROM fbl_cases ORDER BY id DESC";
	}else{
		$query = "SELECT * FROM fbl_cases WHERE `client_id` = '".$account_details['client_id']."' ORDER BY id DESC";
	}
	$result = mysql_query($query) or die(mysql_error());
	while($row = mysql_fetch_array($result))
{
		$data['id']						= $row['id'];
		$data['added']					= $row['added'];
		$data['offending_ip']			= $row['offending_ip'];
		$data['client_id']				= $row['client_id'];
		$data['he_abuse_ticket']		= $row['he_abuse_ticket'];
		$data['forwarded_to_customer']	= $row['forwarded_to_customer'];
		if($data['forwarded_to_customer']=='no')
{
			$data['forwarded_to_customer'] = '<font color="red">no</font>';
		}else{
			$data['forwarded_to_customer'] = '<font color="green">yes</font>';
		}
				
		$client							= get_client_details($data['client_id']);
		$client_details 				= '<a href="https://genexnetworks.net/billing/admin/clientssummary.php?userid='.$client['id'].'" target="_blank">'.$client['firstname'].' '.$client['lastname'].'</a>';
		
		echo '
			<tr>
				<th>'.$data['id'].'</th>
				<th>'.date("d/m/Y H:s", $data['added']).'</th>
				<th>'.$data['he_abuse_ticket'].'</th>
				<th>'.$data['offending_ip'].'</th> ';
		if($account_details['type']=='admin')
{
			echo '<th>'.$client_details.'</th>';
		}
		
		echo '
				<th>'.$client['abuse_email'].'</th>
				<th>'.$data['forwarded_to_customer'].'</th>
				';
		if($account_details['type']=='admin')
{
			echo '
				<th width="100px">
					<a href="dashboard?c=fbl_report&id='.$data['id'].'">View</a> | <a href="actions.php?a=fbl_report_delete&id='.$data['id'].'" onclick="return confirm(\'Are you sure?\')">Delete</a>
				</th>
			</tr>
		';
		}else{
			echo '
				<th width="100px">
					<a href="dashboard?c=fbl_report&id='.$data['id'].'">View</a>
				</th>
			</tr>
		';
		}
			
		unset($client);
		unset($client_details);
		unset($data);
	}
}

function show_fbl_report($id)
{
	$query = "SELECT * FROM fbl_cases WHERE `id` = '".$id."' ";
	$result = mysql_query($query) or die(mysql_error());
	while($row = mysql_fetch_array($result))
{
		$data['id']						= $row['id'];
		$data['added']					= $row['added'];
		$data['offending_ip']			= $row['offending_ip'];
		$data['client_id']				= $row['client_id'];
		$data['he_abuse_ticket']		= $row['he_abuse_ticket'];
		$data['subject']				= stripslashes($row['subject']);
		$data['submitting_entity']		= stripslashes($row['submitting_entity']);
		$data['message']				= stripslashes($row['message']);
		$data['forwarded_to_customer']	= $row['forwarded_to_customer'];
				
		$data['client']					= get_client_details($data['client_id']);

	}
	
	
	
	return $data;
}

function get_senderscore_history($ip_address)
{
	$query = "SELECT * FROM senderscore_history WHERE `ip_address` = '".$ip_address."' LIMIT 720";
	$result = mysql_query($query) or die(mysql_error());
	$count = 0;
	while($row = mysql_fetch_array($result)){
		$data[$count]['id']						= $row['id'];
		$data[$count]['added']					= $row['added'];
		$data[$count]['ip_address']				= $row['ip_address'];
		$data[$count]['score']					= $row['score'];
		$count++;
	}
	
	// $data = array_reverse($data);
	
	return $data;
}
