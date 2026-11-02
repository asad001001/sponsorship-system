<?php
/**
* Single message screen
*/
if(!isset($_GET['sp_id']) || empty($_GET['sp_id'])){
	esc_html_e( 'Sponsored Animal : N/A', 'my-plugin-textdomain' );
	exit();
}else{
	$SpncerId = absint( $_GET['sp_id'] );
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
	if(isset($_POST['post_message']) && check_admin_referer( 'hm_send_message', 'hm_nonce' ) && isset($_POST['post_text']) && isset($_POST['spon_id']) && !empty($_POST['post_text'])){
		$sponsorshipId = absint( $_POST['spon_id'] );
		$sponsorEmails = array();
		#save Message Post
		$my_post = array(
			'post_title' => sanitize_text_field( wp_unslash( $_POST['subject02'] ?? '' ) ),
			'post_content' => wp_kses_post( wp_unslash( $_POST['post_text'] ) ),
			'post_status' => 'publish',
			'post_type' => 'messages',
			'post_author' => get_current_user_id()
		);
		$the_post_id = wp_insert_post( $my_post );
		if($the_post_id){
			update_field('specify', $sponsorshipId, $the_post_id );
			update_field('sponsored_id', $SpncerId, $the_post_id );
		}
		
		   global $wpdb;
                            $t = $wpdb->prefix . 'sponsorships';
                    
                            $sql = $wpdb->prepare(
                                "SELECT *
                                 FROM {$t}
                                 WHERE animal_id = %d",
                                $sponsorshipId
                            );
                    
                            $query_results = $wpdb->get_results($sql);
                            //  print_r($query_results);
							

						
		
		#Send Emails to sponsors
		#Only for Monthly sponsors
// 		$args = array(
// 		    'post_type'  => 'sponsors',
// 			'post_status' => 'publish',
// 			'numberposts' => -1,
// 			 'post__in' => array( 43, 23, 65 )
// 		  //  'meta_query' => array(
// 		  //      array(
// 		  //          'key'     => 'specify',
// 		  //          'value'   => $sponsorshipId
// 		  //      ),
// 		  //      array(
// 		  //          'key'     => 'plan',
// 		  //          'value'   => 'Monthly sponsor'
// 		  //      ),
// 		  //  ),
// 		);
// // 		$args = array(
// //     'post__in' => array( 43, 23, 65 ), // An array of the specific post IDs you want
// //     'post_type' => 'any',              // Optional: Retrieve posts from all post types
// //     'posts_per_page' => -1,          // Optional: To ensure all matching posts are returned, not just a default limit
// //     'ignore_sticky_posts' => true    // Optional: Prevents sticky posts from being prepended
// // );
		
// 		$spons = get_posts($args);
		if(count($query_results) > 0){
		    
			foreach($query_results as $value){
				$id = $value->sponsor_id;
				$sponName = get_post_meta($id, 'name', TRUE);
				$sponEmail = get_post_meta($id, 'email', TRUE);
				if(!in_array($sponEmail, $sponsorEmails)){
					sendCancelEmail($sponEmail, $sponName, wp_kses_post( wp_unslash( $_POST['post_text'] ) ), sanitize_text_field( wp_unslash( $_POST['subject02'] ?? '' ) ));
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
	
	/**
	* Show success message
	* 
	*/
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
			<?php wp_nonce_field( 'hm_send_message', 'hm_nonce' ); ?>
			<input type="hidden" name="post_message" value="true"/>
			<input type="hidden" id="spon_id" name="spon_id" value="<?php echo esc_attr( $SpncerId ); ?>"/>
			<input type="hidden" id="spon_title" name="spon_title" value="<?php echo esc_attr( $spnShipTitle ); ?>"/>
	    	<strong>Subject Title:</strong><br />
	    	<input type="text" name="subject02" value="" style="width:80%;margin-bottom: 30px;margin-top: 5px;border: 1px solid #ccc;" />
	    	<?php

		    	$id = "post_text";
				$name = 'post_text';
				$content = esc_textarea( stripslashes( "" ) );
				$settings = array('tinymce' => true, 'textarea_name' => $name);
				wp_editor($content, $id, $settings);
	    	?>
	    	
			<div style="text-align: right; padding-top: 20px;">
				<a class="button-primary" href="<?php echo admin_url("admin.php?page=message-log" ).'&action=edit'; ?>">Cancel</a>
				<button type="submit" class="button-primary">Send Message</button>							
			</div>

		</form>

	</div>
	</div>

	<br />
	<!--Messages columns-->
	<div class="colContainer" id="col-container">
	    <div class="col-6">
	        <div class="col-wrap">
	            <table class="wp-list-table widefat striped table-view-list" cellspacing="0">
					
					<thead>
						<tr>
							<th>Messages</th>
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
								$the_subject = sanitize_text_field( wp_unslash( $_POST['subject02'] ?? '' ) );
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
									<td>
										<div class="msgDtail">
											<span class="md-date"><i class="icon-clock"></i><?php echo date('j/n/Y', strtotime($postMsg->post_date)); ?></span>
											<div class="fullMsg">
												<p><strong><?php echo $postMsg->post_title ?></strong></p>
												<!-- <h4>Complete Message</h4> -->
												<?php echo $message; ?>
											</div>
											<div class="msgFooter">
												<?php 
												$statusClass;
												if($msgPostStatus == 'publish') {
													$statusClass = 'published';
													$msgPostStatus = 'published';
												} else {
													$statusClass = 'not-published';
												} ?>
												<span class="md-status <?php echo $statusClass; ?>"><?php echo $msgPostStatus; ?></span>
												<span class="md-action"><a  href="#TB_inline?&width=100%&height=auto&inlineId=message-id-<?php echo $msgId; ?>" class="thickbox button-primary">Details</a></span>
											</div>
										</div>
									</td>
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
											<div class="leftSide"><p><strong>Subject Title</strong></p></div>
											<div class="rightSide"><?php echo $postMsg->post_title ?></div>
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
							
							   global $wpdb;
                            $t = $wpdb->prefix . 'sponsorships';
                    
                            $sql = $wpdb->prepare(
                                "SELECT *
                                 FROM {$t}
                                 WHERE animal_id = %d",
                                $SpncerId
                            );
                    
                            $query_results = $wpdb->get_results($sql);
                            //  print_r($query_results);
							

							$just_count = 0;
							foreach ($query_results as  $value ) {
							    
								$postSpn = get_post($value->sponsor_id);
								$spnId = $value->sponsor_id;
								// $spnPostStatus = get_post_status($spnId);
								$spnMeta = get_post_meta($value->sponsor_id);
								
								$spnName = "";
								if(isset($spnMeta['name'][0])){
									$spnName = $spnMeta['name'][0];
								}
								$spnEmail = "";
								if(isset($spnMeta['email'][0])){
									$spnEmail = $spnMeta['email'][0];
								}
								$spnAmount = "";
								if(isset($value->amount)){
									$spnAmount = $value->amount;
								}
								$paymentStatus = "";
								if(isset($value->sponsorship_type)){
									$paymentStatus = $value->sponsorship_type;
								}
								$mcCurrency = "GBP";
								if(isset($value->currency) && !empty($value->currency)){
									$mcCurrency = $value->currency;
								}
								$currencySign = "$";
								if(isset($currencySymbols[$mcCurrency]['symbol']) && !empty($currencySymbols[$mcCurrency]['symbol'])){
									$currencySign = $currencySymbols[$mcCurrency]['symbol'];
								}
								
								$plan = "";
								$subplan = $paymentStatus;

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
								//add_thickbox();
								?>
								
								<div class="popup-admin" id="sponsor-id-<?php echo $spnId; ?>" style="display:none;">

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
									<?php if(!empty($paymentStatus) && false){ ?>
								    <div class="new-pointer">
								    	<div class="leftSide"><p><strong>Payment Status</strong></p></div>
								    	<div class="rightSide"><p><?php echo $paymentStatus; ?></p></div>
									</div>
									<?php } ?>
								    <div class="new-pointer">
								    	<div class="leftSide"><p><strong>Type</strong></p></div>
								    	<div class="rightSide"><p><?php echo $paymentStatus; ?></p></div>
									</div>
								</div>
								
								<?php
								$just_count++;
							}

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