<?php
include('httpful.phar');
define('BH_ROOT_URL','https://auth.bullhornstaffing.com');
define('BH_GET_ACCESS_TOKEN',BH_ROOT_URL . '/oauth/token?grant_type=refresh_token&refresh_token=%s&client_id=%s&client_secret=%s&ttl=4320');
define('BH_LOGIN',BH_ROOT_URL . '/rest-services/login?version=*&access_token=%s');
	
class BullhornAPI {

	private $rest_uri;
	private $rest_token;

	private static $client_id;
	private static $client_secret;

	public static function init() {
		self::$client_id = get_option(WBH_CLIENT_ID);
		self::$client_secret = get_option(WBH_CLIENT_SECRET);
	}

	public function __construct() {
		$this->login();
	}

	public static function grant() {
	$url = sprintf(BH_ROOT_URL . "/oauth/authorize?client_id=%s&response_type=code&username=eos.api&password=Elephant12!&action=Login", self::$client_id);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Must be set to true so that PHP follows any "Location:" header
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$a = curl_exec($ch); // $a will contain all headers

	$url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); // This is what you need, it will return you the last effective URL
	$query = parse_url($url,PHP_URL_QUERY);
	$grant= array();
	parse_str($query,$grant);
	$auth_code = $grant['code'];
	$uri = sprintf(BH_ROOT_URL . '/oauth/token?grant_type=authorization_code&code=%s&client_id=%s&client_secret=%s',$auth_code,self::$client_id,self::$client_secret);
	$response = \Httpful\Request::post($uri)->send();
	$refresh_token = $response->body->refresh_token;
	if(isset($response->body->error)) {
		error_log("Bullhorn returned error - grant: ".$response,0);
		return $response->body->error;
	}
	update_option(WBH_REFRESH_TOKEN,$refresh_token);
	return true;
	}
	
	public static function getAccessToken() {

		$refresh_token = get_option(WBH_REFRESH_TOKEN);
		$uri = sprintf(BH_GET_ACCESS_TOKEN,$refresh_token,self::$client_id,self::$client_secret);
		//echo $uri;
		$response = \Httpful\Request::post($uri)->send();
		//echo '<br/>response:'.$response;
		if(isset($response->body->error)) {
			error_log('refresh:'.$response,0);
			$headers[] = 'From: EOS Recruitment Website <info@eosmgmt.com>';
			$headers[] = 'Content-Type: text/plain; charset=UTF-8';
			wp_mail( 'husain@graphikera.com', 'Bullhorn Grant Error', '<br/>'. json_encode($response), $headers);
			return false;
		}
		$refresh_token = $response->body->refresh_token;
		update_option(WBH_REFRESH_TOKEN,$refresh_token);
		$access_token = $response->body->access_token;
		return $access_token;
	}

	public function login() {
		if($this->rest_token == null) {
			$access_token = self::getAccessToken();
			$uri = sprintf(BH_LOGIN,$access_token);
			$response = \Httpful\Request::get($uri)->send();
			if(isset($response->body->error)) {
				echo "Bullhorn returned error during login: ".$response->body->error;
		        error_log('login:',$response,0);
				return false;
				//exit();
			}
			$this->rest_token = $response->body->BhRestToken;
			$this->rest_uri = $response->body->restUrl;
		}
		return true;
	}

	public function hasCandidate($email) {
		$uri = $this->rest_uri .'search/Candidate/?query=email:"'.$email.'"&fields=id,email&BhRestToken=' . $this->rest_token;
		$response = \Httpful\Request::get($uri)->send();
		error_log($response);
        
        if(isset($response->total) && $response->total >= 1) {
            return true;
            
        }
		return false;
    }
    
	public function getCandidate($canId) {
		$uri = $this->rest_uri ."entity/Candidate/$canId?fields=*&BhRestToken=" . $this->rest_token;
        $response = \Httpful\Request::get($uri)->send();
		print_r($response);
		return $response;
    }

	public function getEntityValues($entity) {
		$uri = $this->rest_uri ."options/$entity?count=300&BhRestToken=" . $this->rest_token;
        $response = \Httpful\Request::get($uri)->send();
		return json_encode($response->body);
    }
	
	public function getJobsAndInsert() {
		//$uri = $this->rest_uri ."options/JobOrder?count=300&BhRestToken=" . $this->rest_token;
		$uri = $this->rest_uri ."search/JobOrder?fields=id,title,address,owner,description,dateAdded,businessSectors,categories&count=300&query=isOpen:1&orderBy=dateAdded-&BhRestToken=" . $this->rest_token;
        $response = \Httpful\Request::get($uri)->send();
		return json_encode($response->body);
    }
	
function BHGetCategoryData($entity_type, $entity_id) {
    if($entity_type == "Category"){
		$categories = get_option('wbh_categories');
		return $categories[$entity_id];
	}elseif($entity_type == "BusinessSector"){
		$categories = get_option('wbh_bizsectors');
		return $categories[$entity_id];
	}
	
}
	

	public function getCountries() {
		$uri = $this->rest_uri ."options/Country?count=300&BhRestToken=" . $this->rest_token;
        $response = \Httpful\Request::get($uri)->send();
		return json_encode($response->body);
    }
    
	public function check() {
		return !empty($this->rest_uri);
	}

	public function parseCandidate($ofile) {
		global $msg;
		$ext = $ofile['ext'];
		$file = $ofile['file'];
		//$uri = $this->rest_uri ."resume/parseToCandidate?format=$ext&populateDescription=text&BhRestToken=" . $this->rest_token;
		$uri = $this->rest_uri ."resume/parseToCandidate?format=$ext&BhRestToken=" . $this->rest_token;
		$response = \Httpful\Request::post($uri)->attach(array('index' => $file))->send();
		//echo ($response);
		if(empty($response->body->candidate)) {
			$msg .= "Error parsing candidate CV:" . $ofile['ext'] . $response;
			//echo $msg;
			error_log($uri,0);
			//file_put_contents(WBH_RESUME_DIR . '/test.doc',file_get_contents($file));
			error_log($msg ,0);
			//exit();
			return null;
		}
		return $response;
	}

	public function processCandidate($body, $ofile,$data,$bhId) {
		global $msg;
        if(empty($bhId)) {
			$uri = $this->rest_uri ."entity/Candidate?BhRestToken=" . $this->rest_token;
		    $response = \Httpful\Request::put($uri)->body(json_encode($body->candidate))->send();
        } else {
			error_log('candidate already exists: '.$bhId);
			$uri = $this->rest_uri ."entity/Candidate/$bhId?BhRestToken=" . $this->rest_token;
		    $response = \Httpful\Request::post($uri)->body(json_encode($body->candidate))->send();
        }
        error_log('sample request: '.$uri);
        error_log(json_encode($body->candidate));

		if(empty($response->body->changedEntityId)) {
			$msg = 'Error while adding the candidate. Please Try again.' . $response;
			//echo $msg;
			error_log($msg . $response,0);
			return $response;
		}
		$return = $response; 
		$canId = $response->body->changedEntityId;
		$changeType = $response->body->changeType;
        
		$uri = $this->rest_uri ."file/Candidate/$canId/raw?externalID=Portfolio&fileType=SAMPLE&BhRestToken=" . $this->rest_token;

        $newfile = $ofile['file'];
        $filename = $ofile['filename'];
        $source_location = $newfile;
        $file = $newfile;
        $post = file_get_contents($newfile);
        $eol = "\r\n";
        $separator = ''.md5(microtime()).'';
        $requestBody = '';
        $requestBody .= '--'.$separator. $eol;
        $requestBody .= 'Content-Disposition: form-data; name="resume"; filename="'.$filename.'"'. $eol;
        $requestBody .= 'Content-Length: "'.strlen($post).'"'. $eol;
        $requestBody .= 'Content-Type: application/octet-stream'.$eol;
        $requestBody .= 'Content-Transfer-Encoding: binary'. $eol. $eol;
        $requestBody .= ''.$post.''. $eol;
        $requestBody .= '--'.$separator.'--'. $eol . $eol;

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $uri);
        curl_setopt($tuCurl, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($tuCurl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($tuCurl, CURLOPT_HTTPHEADER, array('Content-type: multipart/form-data; boundary='.$separator.''));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($tuCurl, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($tuCurl, CURLOPT_POSTFIELDS, $requestBody);
        $tuData = curl_exec($tuCurl);

        curl_close($tuCurl);

		if(empty($response2->fileId)) {
			//$msg = "Error uploading resume:$uri". $response2;
			//echo $msg;
			error_log("Error uploading the resume: $uri \n\n". $response2);
		}
		
		

		// Function field in Bullhorn portal
		$cat_array = $data['categories'];
		array_shift ($cat_array);
		$categories = implode(",",$cat_array);
		error_log('after shift:'.$cat_array . '   '.$categories);
		if(!empty($categories)) {
			$uri = $this->rest_uri ."entity/Candidate/$canId/categories/$categories?BhRestToken=" . $this->rest_token;
			error_log('categories:'.$uri);
			$response = \Httpful\Request::put($uri)->send();
			error_log($response);
			if(empty($response->body->changedEntityId)) {
				error_log('categories:'.$uri);
				error_log('Error associating categories:'.$response);
			}
		}

		// Function field in Bullhorn portal
		/* Hack to remove the Hospitality  */
		$hospitality = "1178715";
		if(!in_array($hospitality,$cat_array)) { 
			$uri = $this->rest_uri ."entity/Candidate/$canId/categories/$hospitality?BhRestToken=" . $this->rest_token;
			error_log('Remove association:'.$uri);
			$response = \Httpful\Request::delete($uri)->send();
			error_log($response);
			if(empty($response->body->changedEntityId)) {
				error_log('Error associating categories:'.$response);
			}
		}
		// Industry field in Bullhorn portal
		$bizsectors = implode(",",$data['bizsectors']);
		if(!empty($bizsectors)) {
			$uri = $this->rest_uri ."entity/Candidate/$canId/businessSectors/$bizsectors?BhRestToken=" . $this->rest_token;
			error_log('businesssectors:'.$uri);
			$response = \Httpful\Request::put($uri)->send();
			if(empty($response->body->changedEntityId)) {
				error_log('businesssectors:'.$uri);
				error_log('Error associating businessSectors:'.$response);
			}
		}
		return $return;
	}
	public function createCandidateWorkHistory($body) {
		$uri = $this->rest_uri ."entity/CandidateWorkHistory?BhRestToken=" . $this->rest_token;
		//echo json_encode($body);
		$response = \Httpful\Request::put($uri)->body(json_encode($body->candidateWorkHistory))->send();
		return $response;
	}

	public function applyJob($canId,$jobCode) {
		global $msg;
		$uri = $this->rest_uri ."entity/JobSubmission?BhRestToken=" . $this->rest_token;
		$req = '{
				"candidate": {"id":'. $canId.'},
				"jobOrder": {"id":'. $jobCode.'},
				"status": "New Lead",
				"dateWebResponse": '. time()*1000 . '
				}';
		//echo $req;
		$response = \Httpful\Request::put($uri)->body($req)->send();
		if(empty($response->body->changedEntityId)) {
			$msg = 'Error while applying job.' . $response;
			//echo $msg;
			//error_log($req,0);
			//error_log($msg,0);
            wp_mail( 'husain@graphikera.com', 'Apply job Failed - '.$data['first_name'], $msg . '\n\n'. json_encode($response), $headers, $attachments );
			return false;
		}
        
		$submitId = $response->body->changedEntityId;
		
		//Do not submit for Long list:
	/*
		$uri = $this->rest_uri ."entity/JobSubmission/$submitId?BhRestToken=" . $this->rest_token;
		$response = \Httpful\Request::post($uri)->body( // or 'Submitted'
			'{
				"status": "Long List",
				"dateAdded": '. time()*1000 . '
				}'
			)->send(); 
		if(empty($response->body->changedEntityId)) {
			$msg = 'Error while submitting job.' . $response;
			//echo $msg;
			//error_log($msg,0);
			return false;
		}
		*/

        $note = '{"action":"Long List A", "comments":"from web","personReference":{"id":"2998"}}';
		$uri = $this->rest_uri ."entity/Note?BhRestToken=" . $this->rest_token;
		$response = \Httpful\Request::put($uri)->body($note)->send();
		if(empty($response->body->changedEntityId)) {
			$msg = 'Error while applying job.' . $response;
			//echo $msg;
			//error_log($req,0);
			//error_log($msg,0);
            wp_mail( 'husain@graphikera.com', 'Error creating note Long list - '.$data['first_name'], $msg . '\n\n'. json_encode($response), $headers, $attachments );
			return true; // false; -- ignore long list errors
		}
        
        $noteId = $response->body->changedEntityId;
        $req = '{
            "note": { "id" : "'.$noteId.'"},
            "targetEntityID": "'. $jobCode.'",
            "targetEntityName": "JobOrder"}';
		$uri = $this->rest_uri ."entity/NoteEntity?BhRestToken=" . $this->rest_token;
		$response = \Httpful\Request::put($uri)->body($req)->send();
		if(empty($response->body->changedEntityId)) {
			$msg = 'Error while applying job.' . $response;
			//echo $msg;
			//error_log($req,0);
			//error_log($msg,0);
            wp_mail( 'husain@graphikera.com', 'Error setting Long List A for Job - '.$data['first_name'], $msg . '\n\n'. json_encode($response), $headers, $attachments );
			return true; // false; -- ignore long list errors
		}
        
		return true;
	}

}

BullhornAPI::init();
/*
$uri = "https://www.googleapis.com/freebase/v1/mqlread?query=%7B%22type%22:%22/music/artist%22%2C%22name%22:%22The%20Dead%20Weather%22%2C%22album%22:%5B%5D%7D";
$response = \Httpful\Request::get($uri)->send();
echo $response;
*/
add_action( 'add_meta_boxes', 'job_details_box' );
function job_details_box() {
    add_meta_box( 
        'job_details_box',
        __( 'Job Details', 'myplugin_textdomain' ),
        'job_details_box_content',
        'job-post',
        'normal',
        'high'
    );
}
function job_details_box_content(){
	  global $post;
	  wp_nonce_field( plugin_basename( __FILE__ ), 'job_details_box_content_nonce' );
	  $job_code = get_post_meta($post->ID,"job_code",true);
	  $country = get_post_meta($post->ID,"country",true);
	  $city = get_post_meta($post->ID,"city",true);
	  $date_added = get_post_meta($post->ID,"date_added",true);
	  ?>
      <p>
          <label for="job_code">Bullhorn Job Id</label><br />
          <input type="text" id="job_code" name="job_code" placeholder="Bullhorn Job Id" value="<?php echo $job_code; ?>" />
      </p>
      <p>
          <label for="country">Country</label><br />
          <input type="text" id="country" name="country" placeholder="Country" value="<?php echo $country; ?>" />
      </p>
      <p>
          <label for="city">City</label><br />
          <input type="text" id="city" name="city" placeholder="City" value="<?php echo $city; ?>" />
      </p>
      <p>
          <label for="date_added">Date Added</label><br />
          <input type="text" id="date_added" name="date_added" placeholder="Date Added" value="<?php echo $date_added; ?>" />
      </p>
	  <?php
	}
add_action( 'save_post', 'job_details_box_save' );
function job_details_box_save( $post_id ) {
  
  if ( 'job-post' == $_POST['post_type'] ) {
  if ( !current_user_can( 'edit_post', $post_id ) )
    return;
  }
  update_post_meta( $post_id, 'job_code', $_POST['job_code']);
  update_post_meta( $post_id, 'country', $_POST['country']);
  update_post_meta( $post_id, 'city', $_POST['city']);
  update_post_meta( $post_id, 'date_added', $_POST['date_added']);
}	