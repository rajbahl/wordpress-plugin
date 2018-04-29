<?php
	include_once (ABSPATH . 'wp-admin/includes/upgrade.php');
	global $wpdb;
	if( !isset($wpdb) ) {
		include_once (ABSPATH . '/wp-config.php');
		include_once (ABSPATH . 'wp-includes/wp-db.php');
	}

class Candidate {
	public $id;
	public $initTime;
	public $updateTime;
	public $first_name;
	public $last_name;
	public $email;
	public $phone;
	public $residence_country;
	public $gender;
	public $nationality;
	public $job_function;
	public $languages;
	public $resume;
	public $bh_reference;
	public $status;

	private static $table_name;

	public static function init() {
		global $wpdb;
		self::$table_name = $wpdb->prefix . TABLE_NAME;	
	}
	public function __construct($data,$insert=false) {

		$this->first_name = $data['first_name'];
		$this->last_name = $data['last_name'];
		$this->email = $data['email'];
		$this->phone = $data['phone'];
		$this->residence_country = $data['residence_country'];
		$this->gender = $data['gender'];
		$this->nationality = $data['nationality'];
		$this->job_function = $data['job_function'];
		$this->updateTime = $data['update_time'];
		$this->initTime = $data['init_time'];
		$this->resume = $data['resume'];
		$this->languages = $data['languages'];
		$this->nationality = $data['nationality'];
		$this->status = $data['status'];
		$this->bh_reference = $data['bh_reference'];
		if($insert) {
			$this->initTime = date('Y-m-d H:i:s');
		}
	}

	public function update() {
		global $wpdb;
		$res = $wpdb->update(
			self::$table_name,
			array(
				'bh_reference' => $this->bh_reference,
				'status' => 1
			),
			array( 'id' => $this->id), 
			array ('%s','%d'),
			'%d'
		);
		
		if(false === $res) {
			echo 'Error updating Trans code:' . $wpdb->last_error;
			exit();
		}
	}
	
	public function save() {
		global $wpdb;
		$wpdb->insert(
			self::$table_name,
			array(
				'first_name' => ($this->first_name),
				'last_name' => ($this->last_name),
				'email' => ($this->email),
				'phone' => ($this->phone),
				'gender' => ($this->gender),
				'residence_country' => ($this->residence_country),
				'job_function' => ($this->job_function),
				'bh_reference' => ($this->bh_reference),
				'init_time' => ($this->initTime),
				'status' => 0

			),
			array ('%s','%s','%s','%f','%s','%s','%s','%s','%d')
		);
		$this->id = $wpdb->insert_id;

	}

	public static function objects($filter) {
		global $wpdb;
		$sql = "SELECT * FROM ". self::$table_name . " WHERE 1=1 ";

		if(isset($filter['id'])) {
			$sql .= " AND id =". intval($filter['id']);
		}
		if(isset($filter['reference'])) {
			$sql .= " AND reference = '" .$filter['reference']."'";
		}
		//echo $sql;
		$dbtxs = $wpdb->get_results($sql,ARRAY_A);
//		print_r($dbtxs);
		$txs = array();
		foreach ( $dbtxs as $dbtx )  {
			$tx = new Transaction($dbtx);
			$txs[] = $tx;
		}
		return $txs;
	}

	/*public static function get_form() {
		$form = 
		'<form action="'.RESUME_SUBMIT_URL.'" method="post">
			<table class="paymentform">
				<tr>
					<td><label>Name*: </label></td><td><input type="text" name="name" required="true" placeholder="Full Name"></td>
				</tr>
				<tr>
					<td><label>Email*: </label></td><td><input type="email" name="email" required="true" placeholder="Valid Email"></td>
				</tr>
				<tr>
					<td><label>Mobile*: </label></td><td><input type="text" name="phone" required="true" placeholder="Your Phone Number"></td>
				</tr>
				<tr>
					<td><label>Amount*: </label></td><td><input type="text" name="amount" required="true" placeholder="Amount to pay"></td>
				</tr>
				<tr>
					<td><label>Service: </label></td><td><select name="service"><option>Wedding-Champs-package</option></select></td>
				</tr>
				<tr>
					<td><label>Remarks*: </label></td><td><input type="text" name="remarks" required="true" placeholder="Reference / Invoice No."></td>
				</tr>
				<tr>
					<td><input type="submit" name="submit" value="submit"></td>
				</tr>
			</table>
			</form>';
			return $form;
	}*/
		
	public static function drop_table() {
		global $wpdb;
		$sql = "DROP TABLE ". self::$table_name .";";
		dbDelta( $sql );
	}	


	public static function create_table() {
		global $wpdb;
		if( !isset($wpdb) ) {
			include_once (ABSPATH . '/wp-config.php');
			include_once (ABSPATH . 'wp-includes/wp-db.php');
		}
		$table_name = self::$table_name;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		  id mediumint(9) NOT NULL AUTO_INCREMENT,
		  init_time datetime,
		  update_time timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		  first_name TINYTEXT NOT NULL,
		  last_name TINYTEXT NOT NULL,
		  email TINYTEXT NOT NULL,
		  phone VARCHAR(15) NOT NULL,
		  residence_country TINYTEXT,
		  gender TINYINT(1) UNSIGNED DEFAULT 1 NOT NULL,
		  status TINYINT(1) UNSIGNED DEFAULT 0 NOT NULL,
		  nationality tinytext,
		  job_function tinytext,
		  resume tinytext,
		  bh_reference tinytext,
		  languages tinytext,
		  UNIQUE KEY id (id)
		) $charset_collate;";

		dbDelta( $sql );
	}
}
Candidate::init();