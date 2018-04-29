<?php
/*
Plugin Name: Wordpress Bullhorn Plugin
Plugin URI:  http://graphikera.com/bullhorn-integration
Description: Integrate Bullhorn to your Wordpress website
Version:     0.1
Author:      Graphikera Technologies
Author URI:  http://www.graphikera.com
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain: graphikera
*/
ini_set("error_log",'bullhorn.log');
error_reporting(E_ERROR | E_PARSE);
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
define('TABLE_NAME','wbh_candidate');
//define ( 'RESUME_SUBMIT_URL','/wbh-submit-cv/');
define ('WBH_CLIENT_ID','wbh-client-id');
define ('WBH_CLIENT_SECRET','wbh-client-secret');
define ('WBH_REFRESH_TOKEN','wbh-refresh-token');
define ('WBH_ACCESS_TOKEN','wbh-access-token');
define ('WBH_COUNTRIES','wbh-countries');
define ('WBH_CATEGORIES','wbh-categories');
define ('WBH_BIZSECTORS','wbh-bizsectors');

$upload_dir = wp_upload_dir();
define('WBH_RESUME_DIR',$upload_dir['basedir'] . "/resume/");

require_once ('candidate.php');
require_once ('bullhorn-api.php');
require_once('util.php');

/*
 * Add Custom Menu
 */

function bh_options() {
	add_menu_page (
        'Bullhorn Options',
        'Bullhorn',
        'manage_options',
        'wp-bullhorn/bh-options.php',
        '',
        '',
        '5'
    );
}
add_action( 'admin_menu', 'bh_options' );

function wbh_install() {
	Candidate::create_table();
	update_option(WBH_CLIENT_ID,'5aefefcf-14fe-43ad-8af2-5ecc29b53d83');
	update_option(WBH_CLIENT_SECRET,'slzaXbSc6tZvCNgdPySzIowK0MJeuIRF');
	update_option(WBH_REFRESH_TOKEN,'0:0e25560e-a54b-41b4-b3a8-2c3e3a70bb04');
	$bh = new BullhornAPI();
	$countries = $bh->getCountries();
	update_option(WBH_COUNTRIES,$countries);
}
register_activation_hook( __FILE__, 'wbh_install' );

add_action('parse_request', 'get_candidate_handler');
function get_candidate_handler() {
	if($_SERVER["REQUEST_URI"] == '/get-candidate/') {
		$bh = new BullhornAPI();
		$can = $bh->getCandidate(3117);
		print_r($can);
		exit();
	}	

}

add_action('parse_request', 'refresh_bh_data_handler');
function refresh_bh_data_handler() {
	if($_SERVER["REQUEST_URI"] == '/refresh-bh-data/') {
		$bh = new BullhornAPI();
		foreach (array('Country','BusinessSector','Category') as $entity) {
			$values = $bh->getEntityValues($entity);
			$values = json_decode($values);
			
			echo "<h2>$entity</h2><table border='1'>";
			foreach($values->data as $c) {
				echo "<tr><td> $c->value</td><td>$c->label</td></tr>";
			}
			echo '</table>';
			if($entity=='Country') {
				//echo update_option(WBH_COUNTRIES,$values);
			}
			if($entity=='BusinessSector') {
				update_option(WBH_BIZSECTORS,$values);
			}
			if($entity=='Category') {
				update_option(WBH_CATEGORIES,$values);
			}
		}
	exit();
	}
}

add_action('parse_request', 'form_submission_handler');

function starts_with(  $haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
}

function form_submission_handler() {
	global $msg;
	//$msg = $_SERVER["REQUEST_URI"];
	//echo $_SERVER["REQUEST_URI"];exit;
	if($_SERVER["REQUEST_URI"] == '/job-seekers/' 
		|| starts_with($_SERVER["REQUEST_URI"],'/job-post/')) {
		if (empty($_POST) || empty($_POST['first_name'])) {
			return;
		}
		//echo"here";exit;
		$data = $_POST;

		$hascan = Candidate::objects(array('email'=>$data['email']));
		$bhcanId='';
		if(!empty($hascan)) {
		$dbcan = $hascan[0];
		$bhcanId = $dbcan->bh_reference;
		} else {
			$dbcan = new Candidate($data,true);
			$dbcan->save();
		}
		$ofile = process_upload('resume',$data);
		$bh = new BullhornAPI();
		$response = $bh->parseCandidate($ofile);
		//echo"<pre>";print_r($response);exit;
		//echo $response->code;exit;
		if($response->code == 200) {}else{
			error_log( $msg . '<br/>'.json_encode($data),0);
			$msg = '<div class="alert-error">Error while parsing your resume. Please upload .doc file only.</div>';
			return;
		} //else {

		$can = $response->body->candidate;
		$can->firstName = $data['first_name'];
		$can->lastName = $data['last_name'];
		$can->name = $data['first_name'] . ' ' . $data['last_name'];
		$can->mobile = $data['phone'];
		$can->email = $data['email'];
        if(strtotime($data['dob'])) {
		    $can->dateOfBirth = strtotime($data['dob'])*1000;
        }
		$can->ethnicity = $data['nationality'];
		$can->gender = $data['gender'];
		$can->occupation = $data['job_title'];
		$can->companyName = $data['company_name'];
		$can->customText2 = implode(",",$data['languages']);
		if($data['residence_country']) {
			$can->address->countryID = intval($data['residence_country']);
		}
		if($data['categories']) {
			$can->category->id = intval($data['categories'][0]);
		}
//		$can->categories->data = $data['categories'];
//		$can->businessSectors->data = $data['bizsectors'];

		//$can->description = json_encode($data['profile_summary ']);
		error_log("data submitted:".print_r($data,true));
		$response = $bh->processCandidate($response->body,$ofile,$data,$bhcanId);
		if(empty($response->body->changedEntityId)) {
			$msg = "Error while processing the candidate.". json_encode($can) ."  \n\nPlease Try again:" . $response;
			//echo $msg;
			error_log($msg,0);
			$attachments = array( $ofile['file'] );
			$headers = array();
			$headers[] = 'From: EOS Recruitment Website <info@eosmgmt.com>' . "\r\n";
			$headers[] = 'Content-Type: text/plain; charset=UTF-8';
			//wp_mail( 'husain@graphikera.com', 'Bullhorn Application Failed - '.$data['first_name'], $msg . '\n\n'. json_encode($data), $headers, $attachments );
			//wp_mail( 'info@eosmgmt.com', 'Bullhorn Application Failed - '.$data['first_name'], $msg , $headers, $attachments );
			$msg = "<div class='alert-error'>Error while processing your resume. Please Try again.</div>";
			return;
			//exit();
		} else {
			$canId = $response->body->changedEntityId;
			$changeType = $response->body->changeType;
			$dbcan->bh_reference = $canId;
			$dbcan->update();
			//$msg .= "Thank you. Your request has been processed.[$changeType - $entityId]";
		}
		if(!empty($data['job_code'])) {
			$result = $bh->applyJob($canId,$data['job_code']);
			if($result===false) {
				$msg = "Error while applying $canId for Job ". $data['job_code'];
				//echo $msg;
				error_log($msg,0);
                
				return;
				//exit();
			}
		}
		
		//email to admin
		$attachments = array( $ofile['file'] );
		$headers = array();
		$headers[] = 'From: Dawaam Website <info@dawaam.net>';
		$headers[] = 'Content-Type: text/plain; charset=UTF-8';
		$admin_email = get_option('admin_email');
		add_filter( 'wp_mail_content_type', 'wpdocs_set_html_mail_content_type' );
		$data_str = "Details submitted are as below<br>";
		$data_str .= "Name: ".$data['first_name']." ".$data['last_name']."<br>";
		$data_str .= "Email: ".$data['email']."<br>";
		$data_str .= "Phone: ".$data['phone']."<br>";
		$data_str .= "Company Name: ".$data['company_name']."<br>";
		$data_str .= "Job Title: ".$data['job_title']."<br>";
		$data_str .= "DOB: ".$data['dob']."<br>";
		$data_str .= "Gender: ".$data['gender']."<br>";
		/*$data_str .= "Languages: ";
		foreach($data['languages'] as $lan){
			$data_str .= $lan.", ";
			}
		$data_str = trim(trim($data_str,','))."<br>";*/
		$data_str .= "Name: ".$data['first_name']."<br>";
		
		wp_mail($admin_email, 'New CV Submitted successfully - '.$data['first_name'].' '.$data['last_name'], $msg . '<br/>'. $data_str, $headers, $attachments );
		remove_filter( 'wp_mail_content_type', 'wpdocs_set_html_mail_content_type' );
 
		//email to user
		$attachments = array( $ofile['file'] );
		$headers = array();
		$headers[] = 'From: Dawaam Website <info@dawaam.net>';
		$headers[] = 'Content-Type: text/plain; charset=UTF-8';
		$user_email = $data['email'];
		
		add_filter( 'wp_mail_content_type', 'wpdocs_set_html_mail_content_type' );
		wp_mail($user_email, 'Application Submitted successfully', $msg . '<br/>'. $data_str, $headers, $attachments );
		remove_filter( 'wp_mail_content_type', 'wpdocs_set_html_mail_content_type' );
 
		$msg = "<div class='alert'>Your CV has been submitted successfully.</div>";
//		}
	}

}

function wpdocs_set_html_mail_content_type() {
    return 'text/html';
}