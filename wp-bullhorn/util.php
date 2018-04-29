<?php

function process_upload($param) {
	
	// Undefined | Multiple Files | $_FILES Corruption Attack
	// If this request falls under any of them, treat it invalid.
	if (
		!isset($_FILES[$param]['error']) ||
		is_array($_FILES[$param]['error'])
	) {
		throw new RuntimeException('Invalid parameters.');
	}
	// Check $_FILES[$param]['error'] value.
	switch ($_FILES[$param]['error']) {
		case UPLOAD_ERR_OK:
			break;
		case UPLOAD_ERR_NO_FILE:
			throw new RuntimeException('No file sent.');
		case UPLOAD_ERR_INI_SIZE:
		case UPLOAD_ERR_FORM_SIZE:
			throw new RuntimeException('Exceeded filesize limit.');
		default:
			throw new RuntimeException('Unknown errors.');
	}

	// You should also check filesize here. 
	if ($_FILES[$param]['size'] > 1000000) {
		throw new RuntimeException('Exceeded filesize limit.');
	}

	// DO NOT TRUST $_FILES[$param]['mime'] VALUE !!
	// Check MIME Type by yourself.
	$filename = $_FILES[$param]['name'];
	$ext = pathinfo($filename, PATHINFO_EXTENSION);
	//echo $ext . '<br/>';
	if (false === array_search($ext,array('pdf','doc','docx'),true)) {
		throw new RuntimeException('Invalid file format.');
	}

	return array('file'=>$_FILES[$param]['tmp_name'],'ext'=>$ext);

}

/*$finfo = new finfo(FILEINFO_MIME_TYPE);
		if (false === $ext = array_search(
			$finfo->file($_FILES[$param]['tmp_name']),
			array(
				'pdf' => 'application/pdf',
				'doc' => 'application/msword',
				'docx' => 'application/msword'
			),
			true
		)) {
			throw new RuntimeException('Invalid file format.');
		}*/