<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
// ini_set('error_reporting', E_ALL); 
// ini_set('session.gc_maxlifetime', 86400);

include("inc/db.php");
require_once("inc/sessions.php");
$sess = new SessionManager();
session_start();

include("inc/global_vars.php");
include("inc/functions.php");

// check is account->id is set, if not then assume user is not logged in correctly and redirect to login page
if(empty($_SESSION['account']['id'])){
	status_message('danger', 'Login Session Timeout');
	go($site['url'].'/index?c=session_timeout');
}

$account_details 			= account_details($_SESSION['account']['id']);

$a = $_GET['a'];

switch ($a)
{
	case "test":
		test();
		break;
	
	case "my_account_update":
		my_account_update();
		break;
		
	case "my_account_update_photo":
		my_account_update_photo();
		break;
		
	// lists
	case "list_add":
		list_add();
		break;
		
	case "list_delete":
		list_delete();
		break;
		
	case "list_validate":
		list_validate();
		break;
		
	case "list_clean":
		list_clean();
		break;
		
	// other
	case "set_status_message":
		set_status_message();
		break;
		
	case "ping_host":
		ping_host();
		break;

// default
				
	default:
		home();
		break;
}

function home()
{
	die('access denied to function name ' . $_GET['a']);
}

function ping_host()
{
	$data['ip'] = $_GET['ip'];
	$ping = ping($_GET['ip']);
	if($ping == ''){
		$data['status'] = 'offline';
	}else{
		$data['status'] = 'online';
	}
	
	echo json_encode($data);
}

function external_get_client_details()
{
	$id			= $_GET['id'];
	$data		= get_client_details($id);
	
	echo json_encode($data);
}

function test()
{
	echo '<h3>$_SESSION</h3>';
	echo '<pre>';
	print_r($_SESSION);
	echo '</pre>';
	echo '<hr>';
	echo '<h3>$_POST</h3>';
	echo '<pre>';
	print_r($_POST);
	echo '</pre>';
	echo '<hr>';
	echo '<h3>$_GET</h3>';
	echo '<pre>';
	print_r($_GET);
	echo '</pre>';
	echo '<hr>';
}

function my_account_update()
{
	global $whmcs, $site;
	
	$user_id 						= $_SESSION['account']['id'];
	
	$firstname 						= clean_string(addslashes($_POST['firstname']));
	$lastname 						= clean_string(addslashes($_POST['lastname']));
	$companyname 					= clean_string(addslashes($_POST['companyname']));
	$email 							= clean_string(addslashes($_POST['email']));
	$phonenumber 					= clean_string(addslashes($_POST['phonenumber']));
	$address_1 						= clean_string(addslashes($_POST['address1']));
	$address_2 						= clean_string(addslashes($_POST['address2']));
	$address_city 					= clean_string(addslashes($_POST['city']));
	$address_state 					= clean_string(addslashes($_POST['state']));
	$address_zip 					= clean_string(addslashes($_POST['postcode']));
	$address_country 				= clean_string(addslashes($_POST['country']));

	$postfields["username"] 			= $whmcs['username'];
	$postfields["password"] 			= $whmcs['password'];
	
	$postfields["action"] 			= "updateclient";
	$postfields["clientid"] 			= $user_id;
	$postfields["firstname"] 		= $firstname;
	$postfields["lastname"] 			= $lastname;
	$postfields["companyname"] 		= $companyname;
	$postfields["email"] 			= $email;
	$postfields["phonenumber"] 		= $phonenumber;
	$postfields["address1"] 			= $address_1;
	$postfields["address2"] 			= $address_2;
	$postfields["city"] 				= $address_city;
	$postfields["state"] 			= $address_state;
	$postfields["postcode"] 			= $address_zip;
	$postfields["country"] 			= $address_country;
	
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
		
	if($results["result"]=="success") {
		status_message('success', 'Your account details have been updated.');
	}else{
		status_message('danger', 'There was an error updating your account details.');
	}
	
	go($_SERVER['HTTP_REFERER']);
}

function my_account_update_photo()
{
	global $whmcs, $site;
	$user_id 					= $_SESSION['account']['id'];

	$fileName = $_FILES["file1"]["name"]; // The file name
	
	$fileName = str_replace('"', '', $fileName);
	$fileName = str_replace("'", '', $fileName);
	$fileName = str_replace(' ', '_', $fileName);
	$fileName = str_replace(array('!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '+', '+', ';', ':', '\\', '|', '~', '`', ',', '<', '>', '/', '?', '§', '±',), '', $fileName);
	// $fileName = $fileName . '.' . $fileExt;
	
	$fileTmpLoc = $_FILES["file1"]["tmp_name"]; // File in the PHP tmp folder
	$fileType = $_FILES["file1"]["type"]; // The type of file it is
	$fileSize = $_FILES["file1"]["size"]; // File size in bytes
	$fileErrorMsg = $_FILES["file1"]["error"]; // 0 for false... and 1 for true
	if (!$fileTmpLoc) { // if file not chosen
		echo "Please select a photo to upload first.";
		exit();
	}
	
	// check if folder exists for customer, if not create it and continue
	if (!file_exists('uploads/'.$user_id) && !is_dir('uploads/'.$user_id)) {
		mkdir('uploads/'.$user_id);
	} 
	
	// handle the uploaded file
	if(move_uploaded_file($fileTmpLoc, "uploads/".$user_id."/".$fileName)){
		
		// insert into the database
		mysql_query("UPDATE user_data SET `avatar` = '".$site['url']."/uploads/".$user_id."/".$fileName."' WHERE `user_id` = '".$user_id."' ") or die(mysql_error());		
		
		// report
		echo "<font color='#18B117'><b>Upload Complete</b></font>";
		
	}else{
		echo "ERROR: Oops, something went very wrong. Please try again or contact support for more help.";
		exit();
	}	
}

function set_status_message()
{
	$status 				= $_GET['status'];
	$message				= $_GET['message'];
	
	status_message($status, $message);
}

function does_user_own_list($id)
{
	$uid				= $_SESSION['account']['id'];
	
	$query = "SELECT id FROM `email_lists` WHERE `id` = '".$id."' AND `user_id` = '".$uid."' ";
	$result = mysql_query($query) or die(mysql_error());
	$match = mysql_num_rows($result);
	
	if($match == 0){
		return 'no';
	}else{
		return 'yes';
	}
}

// lists
function list_add()
{	
	global $whmcs, $site;
	
	$user_id				= $_SESSION['account']['id'];
	
	$name					= clean_string($_POST['name']);
	
	$fileName = $_FILES["file1"]["name"]; // The file name
	
	$fileName = str_replace('"', '', $fileName);
	$fileName = str_replace("'", '', $fileName);
	$fileName = str_replace(' ', '_', $fileName);
	$fileName = str_replace(array('!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '+', '+', ';', ':', '\\', '|', '~', '`', ',', '<', '>', '/', '?', '§', '±',), '', $fileName);
	// $fileName = $fileName . '.' . $fileExt;
	
	$fileTmpLoc = $_FILES["file1"]["tmp_name"]; // File in the PHP tmp folder
	$fileType = $_FILES["file1"]["type"]; // The type of file it is
	$fileSize = $_FILES["file1"]["size"]; // File size in bytes
	$fileErrorMsg = $_FILES["file1"]["error"]; // 0 for false... and 1 for true
	if (!$fileTmpLoc) { // if file not chosen
		echo "Please select a CSV or TXT file to upload.";
		exit();
	}
	
	// check if folder exists for customer, if not create it and continue
	if (!file_exists('list_uploads/'.$user_id) && !is_dir('list_uploads/'.$user_id)) {
		mkdir('list_uploads/'.$user_id);
	} 
	
	// handle the uploaded file
	if(move_uploaded_file($fileTmpLoc, "list_uploads/".$user_id."/".$fileName)){
		
		// get list size
		// $emails_total = shell_exec("cat list_uploads/".$user_id."/".$fileName." | wc -l");
		
		// insert into the database
		$input = mysql_query("INSERT INTO `email_lists` 
		(`added`, `user_id`, `name`, `filename`, `emails_total`)
		VALUE
		('".time()."', '".$user_id."', '".$name."', '".$fileName."', '0')") or die(mysql_error());
		
		// report
		echo "<font color='#18B117'><b>Upload Complete</b></font>";
		
	}else{
		echo "ERROR: Oops, something went very wrong. Please try again or contact support for more help.";
		exit();
	}
}

function server_update()
{
	$uid					= $_SESSION['account']['id'];
	$id						= clean_string($_GET['id']);
	$name					= clean_string($_POST['name']);
	$ip_address				= clean_string($_POST['ip_address']);
	$hostname				= clean_string($_POST['hostname']);
	$http_port				= clean_string($_POST['http_port']);
	$smtp_port				= clean_string($_POST['smtp_port']);
	$server_type			= clean_string($_POST['server_type']);
	$postmaster				= clean_string($_POST['postmaster']);
		
	mysql_query("UPDATE `servers` SET `name` = '".$name."' WHERE `id` = '".$id."' ") or die(mysql_errno());
	mysql_query("UPDATE `servers` SET `ip_address` = '".$ip_address."' WHERE `id` = '".$id."' ") or die(mysql_errno());
	mysql_query("UPDATE `servers` SET `hostname` = '".$hostname."' WHERE `id` = '".$id."' ") or die(mysql_errno());
	mysql_query("UPDATE `servers` SET `http_port` = '".$http_port."' WHERE `id` = '".$id."' ") or die(mysql_errno());
	mysql_query("UPDATE `servers` SET `smtp_port` = '".$smtp_port."' WHERE `id` = '".$id."' ") or die(mysql_errno());
	mysql_query("UPDATE `servers` SET `server_type` = '".$server_type."' WHERE `id` = '".$id."' ") or die(mysql_errno());
	mysql_query("UPDATE `servers` SET `postmaster` = '".$postmaster."' WHERE `id` = '".$id."' ") or die(mysql_errno());

	status_message('success','Server has been updated.');
	
	go($_SERVER['HTTP_REFERER']);
}

function list_delete()
{
	$uid				= $_SESSION['account']['id'];
	$id					= $_GET['id'];
	
	// does user own this list
	$list_owner = does_user_own_list($id);
	if($list_owner == 'no'){
		status_message('danger','You don\'t own this list.');
	}else{
		$query = "SELECT * FROM `email_lists` WHERE `id` = '".$id."' AND `user_id` = '".$_SESSION['account']['id']."' ";
		$result = mysql_query($query) or die(mysql_error());
		while($row = mysql_fetch_array($result)){
			$data['id']								= $row['id'];
			$data['filename']						= stripslashes($row['filename']);
		}
		
		shell_exec("rm -rf list_uploads/".$uid."/".$data['filename']);
		
		mysql_query("DELETE FROM `emails` WHERE `list_id` = '".$id."' ") or die(mysql_error());
		
		mysql_query("DELETE FROM `email_lists` WHERE `id` = '".$id."' ") or die(mysql_error());

		status_message('success','List has been deleted.');
	}
	
	go("dashboard?c=lists");
}

function list_validate(){
	global $account_details;
	$uid				= $_SESSION['account']['id'];
	$id					= $_GET['id'];
	
	$query = "SELECT `emails_total` FROM `email_lists` WHERE `id` = '".$id."' AND `user_id` = '".$uid."' ";
	$result = mysql_query($query) or die(mysql_error());
	while($row = mysql_fetch_array($result)){
		$data['emails_total']					= $row['emails_total'];
	}
	
	if($data['emails_total'] > $account_details['credits']){
		status_message('danger','Please purchase additional credits to validate this list.');
	}else{
		$input = mysql_query("INSERT INTO `jobs` 
		(`added`, `user_id`, `list_id`, `status`)
		VALUE
		('".time()."', '".$uid."', '".$id."', 'pending')") or die(mysql_error());
		
		$new_balance = $account_details['credits'] - $data['emails_total'];
		
		mysql_query("UPDATE `user_data` SET `credits` = '".$new_balance."' WHERE `user_id` = '".$uid."' ") or die(mysql_error());
		
		mysql_query("UPDATE `email_lists` SET `status` = 'validating' WHERE `id` = '".$id."' ") or die(mysql_error());
		
		mysql_query("UPDATE `email_lists` SET `job_starttime` = '".time()."' WHERE `id` = '".$id."' ") or die(mysql_error());
		
		status_message('success','The validation process will start shortly.');
	}
	
	go("dashboard?c=lists");
}

function list_clean(){
	global $account_details;
	$uid				= $_SESSION['account']['id'];
	$id					= $_GET['id'];
	
	$query = "SELECT `emails_total` FROM `email_lists` WHERE `id` = '".$id."' AND `user_id` = '".$uid."' ";
	$result = mysql_query($query) or die(mysql_error());
	while($row = mysql_fetch_array($result)){
		$data['emails_total']					= $row['emails_total'];
	}
	
	if(100 > $account_details['credits']){
		status_message('danger','Please purchase additional credits to clean this list.');
	}else{
		$input = mysql_query("INSERT INTO `jobs` 
		(`added`, `user_id`, `list_id`, `status`)
		VALUE
		('".time()."', '".$uid."', '".$id."', 'pending_clean')") or die(mysql_error());
		
		$new_balance = $account_details['credits'] - 100;
		
		mysql_query("UPDATE `user_data` SET `credits` = '".$new_balance."' WHERE `user_id` = '".$uid."' ") or die(mysql_error());
		
		mysql_query("UPDATE `email_lists` SET `status` = 'cleaning' WHERE `id` = '".$id."' ") or die(mysql_error());
		
		mysql_query("UPDATE `email_lists` SET `job_starttime` = '".time()."' WHERE `id` = '".$id."' ") or die(mysql_error());
		
		status_message('success','The cleaning process will start shortly.');
	}
	
	go("dashboard?c=lists");
}

?>