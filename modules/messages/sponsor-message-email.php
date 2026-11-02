<?php

 	function sendCancelEmail($to_email, $name, $messageBody = "", $subject = ""){
 	    
 		$postData = array();
		$message = '';
		$message .= get_email_html($name,$messageBody);

		// $headers = 'Reply-To: donotreply@tenerifehorserescue.com' . "\n" . 'Return-Path: donotreply@tenerifehorse.org' . "\n";   
		// // More headers
		// $headers .= 'From: Tenerife Horse Rescue <donotreply@tenerifehorserescue.com>' . "\n";
		// //$headers .= 'Cc: mamoonrashid@gmail.com' . "\n";
		// $headers .= 'Bcc: muhammad.babar@hashehouse.com, akmal.khan@hashehouse.com' . "\n";
		// $headers .= "MIME-Version: 1.0 \n";
		// $headers .= "Content-Type: text/html; charset=ISO-8859-1 \n";

		$headers = array(
			'From: Tenerife Horse Rescue <donotreply@tenerifehorserescue.com>', 
			'Reply-To: donotreply@tenerifehorserescue.com',
			'BCC: muhammad.babar@hashehouse.com',
			'MIME-Version: 1.0',
			'Content-Type: text/html; charset=UTF-8'
		);




		if($_SERVER['SERVER_NAME'] == 'wp.hashe.com'){
			if(strpos($to_email, 'hashehouse.com') !== false) {
				//Do send only hashe emails
			}else{
				$to_email = "asad.ali@hashehouse.com";
			}
		}
		if ($subject != "") {
		$subject = $subject;
		} else {
		$subject = 'You have received a new message';
		}
		$to_email = trim($to_email);
		$test = wp_mail($to_email,$subject,$message,$headers);
		//wp_mail($to_email,$subject,$message,$headers);
	}

?>