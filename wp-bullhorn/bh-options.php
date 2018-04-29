<?php
require_once ('bullhorn-api.php');

if (isset($_POST['do-refresh-token'])) {
	$ret = BullhornAPI::grant();
	if($ret == true) {
		$msg = "<span style='color:#098209'>Auth Code updated successfully!</span>";
	} else {
		echo '<span style="color:#f00">'.$ret.'</span>';
	}
}
if (isset($_POST['do-job-duration'])) {
	update_option('job_duration',$_POST['job_duration']);
}

if (isset($_POST['do-update-values'])) {
	$bh = new BullhornAPI();
	foreach (array('BusinessSector','Category','Country') as $entity) {
		$values = $bh->getEntityValues($entity);
		$values = json_decode($values);
		
		$categoriesArr = array();
		$bizsectorArr = array();
		$countriesArr = array();
		
		//echo "<h2>$entity</h2>";
		foreach($values->data as $c) {
			if($entity=='Category') {
				$categoriesArr[$c->value] = $c->label;
				$term = term_exists($c->label, 'job-category');
				if ($term !== 0 && $term !== null) {
				  //echo "$c->label category exists!<br>";
				}else{
					wp_insert_term($c->label, 'job-category', array(
						'description' => $c->label
					));
					}
				//update_option(WBH_CATEGORIES,$values);
			}elseif($entity=='BusinessSector') {
				$bizsectorArr[$c->value] = $c->label;
				$term = term_exists($c->label, 'industry');
				if ($term !== 0 && $term !== null) {
				  //echo "$c->label category exists!<br>";
				}else{
					wp_insert_term($c->label, 'industry', array(
						'description' => $c->label
					));
					}
			}elseif($entity=='Country') {
				$countriesArr[$c->value] = $c->label;
			}
			//echo "<tr><td> $c->value</td><td>$c->label</td></tr>";
		}
		//echo '</table>';
		if($entity=='Country') {
			update_option('wbh_countries',$countriesArr);
		}
		if($entity=='BusinessSector') {
			update_option('wbh_bizsectors',$bizsectorArr);
		}
		if($entity=='Category') {
			update_option('wbh_categories',$categoriesArr);
		}
	}
}
if (isset($_POST['do-fetch-jobs'])) {
	$bh = new BullhornAPI();
	$loop = true;
	$counterVar = 1;
	
	while($loop == true){
	$start = ($counterVar - 1) * 50;
	$per_page = 50;
	
	$values = $bh->getJobsAndInsert($start,$per_page);
	$values = json_decode($values);
	//echo"<pre>";print_r($values);exit;
	$resCount = $values->count;
	if($resCount < $per_page){
		$loop = false;
		}
	foreach($values->data as $c) {
		//check and insert new job post
		$jobcode = $c->id;
		$args = array(
				'post_type' => 'job-post',
				'post_status' => array( 'publish'),
				'meta_query' => array(
					array(
						'key'     => 'job_code',
						'value'   => $jobcode,
						'compare' => '=',
					)
				),
				);
		//print_r($args);exit;
		$the_query = new WP_Query( $args );
		if ($the_query->have_posts() ) {/*
			//echo"here";exit;
			while ( $the_query->have_posts() ) {
				$the_query->the_post();
				$postarr = array();
				//echo"<pre>";print_r($c);
				$postarr = array(
							  'ID'           => get_the_ID(),
							  'post_title'   => $c->title,
							  'post_content' => $c->description,
						  );
				print_r($postarr);
				echo $post_id = wp_update_post( $postarr, true );	
				exit;	
				
			}
		
		*/}else{
			$postarr = array();
			$postarr['post_content'] = $c->description;
			$postarr['post_title'] = $c->title;
			$postarr['post_status'] = "publish";
			$postarr['post_type'] = "job-post";
			$post_id = wp_insert_post($postarr);
			if($post_id){
				$country = $c->address->countryID;
				$city = $c->address->city;
				$date_added = ($c->dateAdded/1000);
				if($country){
					$countries = get_option('wbh_countries');
					$country = $countries[$country];
					}
				update_post_meta($post_id,'job_code',$c->id);
				update_post_meta($post_id,'country',$country);
				update_post_meta($post_id,'city',$city);
				update_post_meta($post_id,'date_added',$date_added);
				//assigning categories and business sectors
				$taxCateories = array();
				$taxBS = array();
				
				if($c->categories->total > 0){
					foreach($c->categories->data as $cc){
						$cat = $bh->BHGetCategoryData("Category", $cc->id);
						$term = get_term_by('name', esc_attr($cat), 'job-category');
						if($term){
								$taxCateories[] = $term->term_id;
								}else{
									$termmm = wp_insert_term(
											  $cat, // the term 
											  'job-category'
											);
									$taxCateories[] = $termmm->term_id;		
									}
						}
					}else{
						$cat = "General";
						$term = get_term_by('name', esc_attr($cat), 'job-category');
						$taxCateories[] = $term->term_id;
						}
				if($c->businessSectors->total > 0){
					foreach($c->businessSectors->data as $cc){
						$bs = $bh->BHGetCategoryData("BusinessSector", $cc->id);
						$term = get_term_by('name', esc_attr($bs), 'industry');
						if($term){
								$taxBS[] = $term->term_id;
								}else{
									$termmm = wp_insert_term(
											  $bs, // the term 
											  'industry'
											);
									$taxBS[] = $termmm->term_id;		
									}
						}
					}else{
						$bs = "General";
						$term = get_term_by('name', esc_attr($bs), 'industry');
						$taxBS[] = $term->term_id;
						}
				if(trim($taxCateories[0]) == ""){
						$cat = "General";
						$term = get_term_by('name', esc_attr($cat), 'job-category');
						$taxCateories[0] = $term->term_id;
						}
					if(trim($taxBS[0]) == ""){
						$bs = "General";
						$term = get_term_by('name', esc_attr($bs), 'industry');
						$taxBS[0] = $term->term_id;
						}	
				$term_taxonomy_ids = wp_set_post_terms( $post_id, $taxCateories, 'job-category' );
				$term_taxonomy_ids = wp_set_post_terms( $post_id, $taxBS, 'industry' );
				//end assigning categories and business sectors
				}else{
					print_r($post_id);
					}
			}
		wp_reset_postdata();	
	//exit;
	}
	//echo $counterVar . "<br>";
	$counterVar++;
}
}
?>

<h1>Bullhorn Set up</h1>
<hr/>
<div>
	<form method="post">
	<h3>Display jobs by duration</h3>
    <p><input type="text" name="job_duration" id="job_duration" value="<?php echo get_option('job_duration'); ?>" /><br />
		<input type="submit" name="do-job-duration" value="Apply"></p>
	</form>
</div>
<hr />
<div>
	<form method="post">
		<h3>Bullhorn Auth Code</h3>
		<p><?php echo "$msg" ?></p>
		<p><input type="hidden" name="refresh_token" size="60" value="GRANT"></p>
		<p><input type="submit" name="do-refresh-token" value="Update Auth Code"></p>
	</form>
	<p>Click on this button if you are getting invalid grant error.</p>
</div>
<hr/>
<div>
	<form method="post">
	<h3>Update Business Sectors and Categories from Bullhorn</h3>
		<input type="submit" name="do-update-values" value="Click here">
	</form>
	<p>Click on this button if you have added / updated categories or business sectors in Bullhorn Staffing .</p>
</div>
<div>
	<form method="post">
	<h3>Import jobs from bullhorn</h3>
		<p><input type="submit" name="do-fetch-jobs" value="Fetch Jobs"></p>
	</form>
</div>



 