<?php
/**
* Single message screen
*/

if(!isset($_GET['sp_id']) || empty($_GET['sp_id'])){
	esc_html_e( 'Sponsored Animal : N/A', 'my-plugin-textdomain' );
	exit();
}else{
	$SpncerId = $_GET['sp_id'];
	$spnShipPost = get_post($SpncerId);
	$spnShipTitle = $spnShipPost->post_title;
	$showMsg = "";
	
	/**
	* Get Dynamic currency symbols
	*/
	$currencySymbols = @file_get_contents( plugin_dir_path( __FILE__ ) . 'currency_symbols_by_code.json');
	$currencySymbols = @json_decode($currencySymbols, TRUE);

	
	/**
	* Submit message & save data & send emails if sponsors exists
	*/
	if(isset($_POST['post_message']) && isset($_POST['post_text']) && isset($_POST['spon_id']) && !empty($_POST['post_text'])){
		$sponsorshipId = trim($_POST['spon_id']);
		$sponsorEmails = array();
		#save Message Post
		$my_post = array(
			'post_title' => "",
			'post_content' => $_POST['post_text'],
			'post_status' => 'publish',
			'post_type' => 'messages',
			'post_author' => get_current_user_id()
		);
		$the_post_id = wp_insert_post( $my_post );
		if($the_post_id){
			update_field('specify', $sponsorshipId, $the_post_id );
			update_field('sponsored_id', $SpncerId, $the_post_id );
		}
		
		
		#Send Emails to sponsors
		$args = array(
		    'post_type'  => 'sponsors',
			'post_status' => 'publish',
			'numberposts' => -1,
		    'meta_query' => array(
		        array(
		            'key'     => 'specify',
		            'value'   => $sponsorshipId
		        ),
		    ),
		);
		
		$spons = get_posts($args);
		if(count($spons) > 0){
			foreach($spons as $key => $value){
				$id = $value->ID;
				$sponName = get_post_meta($id, 'name', TRUE);
				$sponEmail = get_post_meta($id, 'email', TRUE);
				if(!in_array($sponEmail, $sponsorEmails)){
					sendCancelEmail($sponEmail, $sponName, $_POST['post_text']);
				}
				$sponsorEmails[] = $sponEmail;
			}
		}
		if($sponsorEmails && count($sponsorEmails) > 0){
			$sponsorEmails = array_unique($sponsorEmails);
			$notified = implode(", ", $sponsorEmails);
			if($the_post_id){
				update_field('notified_emails', $notified, $the_post_id );
			}
		}
		$showMsg = "New message has been posted successfully.";
	}
	
	if(!empty($showMsg)){
		echo "<div class='notice notice-success is-dismissible'>
		    <p>$showMsg</p>
		</div>";
	}
?>

	<h1>
	    <?php esc_html_e( 'Sponsored Animal : ' . $spnShipTitle, 'my-plugin-textdomain' ); ?>
	</h1>

	<div class="message-box col-container">
	<div class="col-md-12">
		<form method="post" action="">
			<h3>Send New Message</h3>
			<input type="hidden" name="post_message" value="true"/>
			<input type="hidden" id="spon_id" name="spon_id" value="<?php echo $SpncerId; ?>"/>
			<input type="hidden" id="spon_title" name="spon_title" value="<?php echo $spnShipTitle; ?>"/>
	    	<?php
		    	$id = "post_text";
				$name = 'post_text';
				$content = esc_textarea( stripslashes( "" ) );
				$settings = array('tinymce' => true, 'textarea_name' => $name);
				wp_editor($content, $id, $settings);
	    	?>
	    	<!--<textarea style="width: 100%;" name="post_text" cols="10" rows="10"></textarea>-->
			<div style="text-align: right; padding-top: 20px;">
				<a class="button-primary" href="<?php echo admin_url("admin.php?page=message-log" ).'&action=edit'; ?>">Cancel</a>
				<button type="submit" class="button-primary">Send Message</button>							
			</div>

		</form>

	</div>
	</div>

	<br />
	<!--Messages columns-->
	<style>
		.col-6 {
			width:49;
			width:49.5%;
		}
		.colContainer {
			display: flex;
    		justify-content: space-between;
		}
	</style>
	<div class="colContainer" id="col-container">
	    <div class="col-6">
	        <div class="col-wrap">
	            <table class="wp-list-table widefat striped table-view-list" cellspacing="0">
					<thead>
						<tr>
							<th id="titls" width="25%" class="manage-column column-columnname" scope="col">Date</th> 
							<th id="titls" width="25%" class="manage-column column-columnname" scope="col">Message</th> 
							<th id="titls" width="25%" class="manage-column column-columnname" scope="col">Status</th> 
							<th id="titls" width="25%" class="manage-column column-columnname" scope="col">Action</th>
					   </tr>
					</thead>
					<tbody>
						<?php
							//get Messages
							$args = array(
							    'post_type'  => 'messages',
							    'orderby'     => 'ID',
								'order' 	  => 'DESC',
								'post_status' => 'publish',
								'numberposts' => 10,
							    'meta_query' => array(
							        array(
							            'key'     => 'specify',
							            'value'   => $SpncerId
							        ),
							    ),
							);
							$xoquery = new WP_Query( $args );
							$just_count = 0;
							while ($xoquery->have_posts()) {
								$xoquery->the_post();
								
								$postMsg = get_post(get_the_ID());
								$msgId = $postMsg->ID;
								$msgPostStatus = get_post_status($msgId);
								$msgMeta = get_post_meta($msgId);
								$notifiedEmails = "";
								if(isset($msgMeta['notified_emails'][0])){
									$notifiedEmails = $msgMeta['notified_emails'][0];
								}
								$the_content_full = apply_filters('the_content', get_the_content());
								$the_content = wp_strip_all_tags( $the_content_full );
								$message = !empty($the_content) ? substr($the_content, 0, 100) : "...";
								if ($just_count % 2 == 0) {
									$alternateclass = 'alternate';
								} else {
									$alternateclass = '';
								}
								?>
								<tr class="<?php echo $alternateclass; ?>" valign="top">
										
									<td class="manage-column column-columnname"><?php echo date('j/n/Y', strtotime($postMsg->post_date)); ?></td>
									<td class="manage-column column-columnname"><?php echo $message; ?></td>
									<td class="manage-column column-columnname"><?php echo $msgPostStatus; ?></td>
									<!--<td class="manage-column column-columnname"><a href="<?php echo admin_url("post.php?post=$msgId" ).'&action=edit'; ?>">Details</a></td>-->
									<td class="manage-column column-columnname"><a  href="#TB_inline?&width=100%&height=auto&inlineId=message-id-<?php echo $msgId; ?>" class="thickbox button-primary">Details</a></td>
						        </tr>
						        
						        <?php
								add_thickbox();
								?>
								<div class="popup-admin" id="message-id-<?php echo $msgId; ?>" style="display:none;height: auto !important;">
									
										<h2 class="thick-title">Message Details</h2>
										<div class="new-pointer">
											<div class="leftSide"><p><strong>Message Date</strong></p></div>
											<div class="rightSide"><p><?php echo date('j/n/Y', strtotime($postMsg->post_date)); ?></p></div>
										</div>
										<div class="new-pointer">
											<div class="leftSide"><p><strong>Full Message</strong></p></div>
											<div class="rightSide"><?php echo $the_content_full; ?></div>
										</div>
										<?php if($notifiedEmails){ ?>
										<div class="new-pointer">
											<div class="leftSide"><p><strong>Notified Emails</strong></p></div>
											<div class="rightSide"><p class="wordwrap"><?php echo $notifiedEmails; ?></p></div>
										</div>
										<?php } ?>
									
								</div>
								
								
								<?php
								$just_count++;
							}
							wp_reset_postdata();
						?>
						
					</tbody>
				</table>
	        </div>
	    </div>
	    <div class="col-6">
	        <div class="col-wrap">
	            <div class="form-wrap">
	                <table class="wp-list-table widefat striped table-view-list" cellspacing="0">
						<thead>
							<tr>
								<th id="titls" class="manage-column column-columnname" width="26%" scope="col">Sponsor</th> 
								<th id="titls" class="manage-column column-columnname" width="18%" scope="col">Date</th> 
								<th id="titls" class="manage-column column-columnname" width="18%" scope="col">Type</th> 
								<th id="titls" class="manage-column column-columnname" width="18%" scope="col">Amount</th> 
								<th id="titls" class="manage-column column-columnname" width="20%" scope="col">Action</th>
						   </tr>
						</thead>
						<tbody>
							<?php
							//get Messages
							$args = array(
							    'post_type'  => 'sponsors',
							    'orderby'     => 'ID',
								'order' 	  => 'DESC',
								'post_status' => 'publish',
								'numberposts' => 10,
							    'meta_query' => array(
							        array(
							            'key'     => 'specify',
							            'value'   => $SpncerId
							        ),
							    ),
							);
							$xoquery = new WP_Query( $args );
							$just_count = 0;
							while ($xoquery->have_posts()) {
								$xoquery->the_post();
								$postSpn = get_post(get_the_ID());
								$spnId = $postSpn->ID;
								$spnPostStatus = get_post_status($spnId);
								$spnMeta = get_post_meta($spnId);
								
								$spnName = "";
								if(isset($spnMeta['name'][0])){
									$spnName = $spnMeta['name'][0];
								}
								$spnEmail = "";
								if(isset($spnMeta['email'][0])){
									$spnEmail = $spnMeta['email'][0];
								}
								$spnAmount = "";
								if(isset($spnMeta['amount'][0])){
									$spnAmount = $spnMeta['amount'][0];
								}
								$paymentStatus = "";
								if(isset($spnMeta['payment_status'][0])){
									$paymentStatus = $spnMeta['payment_status'][0];
								}
								$mcCurrency = "GBP";
								if(isset($spnMeta['mc_currency'][0]) && !empty($spnMeta['mc_currency'][0])){
									$mcCurrency = $spnMeta['mc_currency'][0];
								}
								$currencySign = "$";
								if(isset($currencySymbols[$mcCurrency]['symbol']) && !empty($currencySymbols[$mcCurrency]['symbol'])){
									$currencySign = $currencySymbols[$mcCurrency]['symbol'];
								}
								
								$plan = "";
								$subplan = "";
								if(isset($spnMeta['plan'][0])){
									$plan = $spnMeta['plan'][0];
									// echo $plan;
									// die();
									if ($plan == 'One-time sponsor') {
										$subplan = 'One-time';
									} else {
										$subplan = 'Monthly';
									}
								}
								if ($just_count % 2 == 0) {
									$alternateclass = 'alternate';
								} else {
									$alternateclass = '';
								}
								?>
								<tr class="<?php echo $alternateclass; ?>" valign="top">
									<td class="manage-column column-columnname" style="text-transform: capitalize;"><?php echo $spnName; ?></td>
									<td class="manage-column column-columnname"><?php echo date('d/n/Y', strtotime($postSpn->post_date)); ?></td>
									<td class="manage-column column-columnname"><?php echo $subplan; ?></td>
									<td class="manage-column column-columnname">(<?php echo $currencySign; ?>) <?php echo $spnAmount; ?></td>
									<!--<td class="manage-column column-columnname"><a href="<?php echo admin_url("post.php?post=$spnId" ).'&action=edit'; ?>">Details</a></td>-->
									<td class="manage-column column-columnname"><a href="#TB_inline?&width=100%&height=auto&inlineId=sponsor-id-<?php echo $spnId; ?>" class="thickbox button-primary">Details</a></td>
						        </tr>
								<?php
								add_thickbox();
								?>
								
								<div class="popup-admin" id="sponsor-id-<?php echo $spnId; ?>" style="display:none;">
									<?php
										/*echo "<pre>";
										print_r($spnMeta);*/
									 ?>
									<h2 class="thick-title">Sponsor Details</h2>
								    <div class="new-pointer">
								    	<div class="leftSide"><p><strong>Name</strong></p></div>
								    	<div class="rightSide"><p><?php echo $spnName; ?></p></div>
									</div>
								    <div class="new-pointer">
								    	<div class="leftSide"><p><strong>Email</strong></p></div>
								    	<div class="rightSide"><p><?php echo $spnEmail; ?></p></div>
									</div>
								    <div class="new-pointer">
								    	<div class="leftSide"><p><strong>Amount(<?php echo $currencySign; ?>)</strong></p></div>
								    	<div class="rightSide"><p><?php echo $spnAmount; ?></p></div>
									</div>
									<?php if(!empty($paymentStatus)){ ?>
								    <div class="new-pointer">
								    	<div class="leftSide"><p><strong>Payment Status</strong></p></div>
								    	<div class="rightSide"><p><?php echo $paymentStatus; ?></p></div>
									</div>
									<?php } ?>
								    <div class="new-pointer">
								    	<div class="leftSide"><p><strong>Type</strong></p></div>
								    	<div class="rightSide"><p><?php echo $plan; ?></p></div>
									</div>
								</div>
								
								<?php
								$just_count++;
							}
							wp_reset_postdata();
						?>
						</tbody>
					</table>
	            </div>
	        </div>
	    </div>
	</div>
	<style>
		.wordwrap { 
		   white-space: pre-wrap;      /* CSS3 */   
		   white-space: -moz-pre-wrap; /* Firefox */   
		   white-space: -o-pre-wrap;   /* Opera 7 */    
		   word-wrap: break-word;      /* IE */
		}
	</style>
<?php } ?>
<?php
 	function sendCancelEmail($to_email, $name, $messageBody = ""){
 		$postData = array();
		$message = '';
		$message .= '<style type="text/css">
		body { width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; margin: 0; padding: 0; background: #eaeaea;font-family: sans-serif; color: #555; }
		img { outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }

		a img { border: none; }

		p { margin: 1em 0; }

		h4 {font-size: 20px; color: #332f2f !important; margin: 0;}

		table td { border-collapse: collapse;}

		table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }

		a { color: #000; }

		table.borders td {border: 1px solid #e6e4e4; padding-top: 4px; padding-bottom: 4px; padding-left: 8px; padding-right: 8px; }

		table.first-table {max-width: 100%; width: 600px; background: #fff; border: 1px solid #ccc;}

		table.first-table > tr > td { padding: 10px 20px;}

		.logo-style {padding: 20px;background-color: #fff;}

		</style>

		

		<center style="padding-top: 20px; padding-bottom: 20px;background: #fff;">

		<table cellpadding="20" cellspacing="0" border="0" class="first-table" style="max-width: 100%; width: 600px; background: #fff; border: 1px solid #ccc;">

		<tr>

		<td class="logo-style" style="padding: 20px;background-color: #fff;">

		<table style="" border="0" cellpadding="0" cellspacing="0" height="100%" width="100%">

		<tbody>

		<tr>

		<td><img src="'.get_stylesheet_directory_uri().'/images/tene-logo.png" alt="Tenerife Horse Rescue" style="display: block; margin: 0 auto; width: 75px;"></td>

		</tr>

		</tbody>

		</table>

		</td>

		</tr>

		<tr>

		<td style="padding: 10px 20px;">

		<table class="" border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" style="font-family: sans-serif;color: #555; border: 0; border-bottom: 1px solid #ccc;">

		<tbody>

		<tr>

		<td style="padding-top: 10px; padding-bottom: 10px; padding-left: 0; padding-right: 8px;">

		<h4 style="margin: 0;font-size: 20px; color: #332f2f;">Contact Details</h4>

		</td>

		</tr>

		</tbody>

		</table>

		<table class="borders" border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" style="font-family: sans-serif;color: #555; border: 1px solid #ccc; border-bottom: 0; margin-bottom: 15px;">

		<tbody>	
			<tr>
				<td>Hi '.$name.'</td>
			</tr>
			<tr>
				<td>You have received a new message regarding to your sponsorship.</td>
			</tr>
			<tr>
				<td>'.$messageBody.'</td>
			</tr>
		</tbody>

		</table>

		</td>

		</tr>

		<tr>

		<td style="padding: 10px 20px;">

		<table class="" border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" style="font-family: sans-serif;color: #555; border: 0; border-top: 1px solid #ccc;">

		<tr>

		<td>

		<table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" style="font-family: sans-serif;color: #555; font-size: 12px; color: #757575; ">

		<tbody>

		<tr>

		<td style="padding: 20px 0;">

		© 2021 - Tenerife Horse Rescue - All Rights Reserved

		</td>

		</tr>

		</tbody>

		</table>

		</td>

		</tr>

		</table>
		</td>
		</tr>
		</table>
		</center>';

		$headers = 'Reply-To: donotreply@tenerifehorse.com' . "\n" . 'Return-Path: donotreply@tenerifehorse.org' . "\n";   
		// More headers
		$headers .= 'From: Tenerife Horse <donotreply@tenerifehorse.com>' . "\n";
		$headers .= 'Bcc: bilal.azfar@hashehouse.com, akmal.khan@hashehouse.com, danish.shafique@hashehouse.com' . "\n";
		$headers .= "MIME-Version: 1.0 \n";
		$headers .= "Content-Type: text/html; charset=ISO-8859-1 \n";
		if($_SERVER['SERVER_NAME'] == 'wp.hashe.com'){
			if(strpos($to_email, 'hashehouse.com') !== false) {
				//Do send only hashe emails
			}else{
				$to_email = "muhammad.babar@hashehouse.com";
			}
		}
		$subject="Message Received";
		mail($to_email,$subject,$message,$headers);
	}

?>