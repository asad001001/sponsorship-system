<?php

/**
 * Message Log helpers (uses custom DB tables)
 * Tables expected:
 *  - {$wpdb->prefix}sponsorships
 *  - {$wpdb->prefix}sponsorship_transactions
 */
if ( ! function_exists('hm_msglog_get_sponsor_ids_by_animal') ) {
    function hm_msglog_get_sponsor_ids_by_animal( $animal_id, $statuses = ['active','pending'] ) {
        global $wpdb;
        $animal_id = (int) $animal_id;
        if ( ! $animal_id ) return [];
        $t = $wpdb->prefix . 'sponsorships';

        $in  = implode(',', array_fill(0, count($statuses), '%s'));
        $sql = $wpdb->prepare(
            "SELECT DISTINCT sponsor_id
             FROM {$t}
             WHERE animal_id=%d AND status IN ($in)",
            array_merge([$animal_id], $statuses)
        );

        $ids = $wpdb->get_col($sql);
        return array_map('intval', (array) $ids);
    }

    function hm_msglog_get_monthly_sponsor_ids( $statuses = ['active','pending'] ) {
        global $wpdb;
        $t = $wpdb->prefix . 'sponsorships';

        $in  = implode(',', array_fill(0, count($statuses), '%s'));
        $sql = $wpdb->prepare(
            "SELECT DISTINCT sponsor_id
             FROM {$t}
             WHERE sponsorship_type=%s AND status IN ($in)",
            array_merge(['monthly','one_time'], $statuses)
        );

        $ids = $wpdb->get_col($sql);
        return array_map('intval', (array) $ids);
    }
}


	/**
	* Submit message & save data & send emails if sponsors exists
	*/
	
	if(isset($_POST['post_message_all']) && check_admin_referer( 'hm_send_message', 'hm_nonce' ) && isset($_POST['post_text']) && !empty($_POST['post_text'])){
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
			update_field('specify_all', "yes", $the_post_id );
			update_field('sponsored_id', "all", $the_post_id );
		}
		
		
		#Send Emails to sponsors
		// Fetch sponsors from sponsorships table (instead of sponsor ACF/meta plan/specify fields)
$sponsor_ids = [];
if ( ! empty($sponsorshipId) ) {
    // $sponsorshipId here is animal post ID
    $sponsor_ids = hm_msglog_get_sponsor_ids_by_animal( (int) $sponsorshipId );
} else {
    // "Send to all" = all monthly sponsors
    $sponsor_ids = hm_msglog_get_monthly_sponsor_ids();
}

$spons = [];
if ( ! empty($sponsor_ids) ) {
    $spons = get_posts([
        'post_type'      => 'sponsors',
        'posts_per_page' => -1,
        'post__in'       => $sponsor_ids,
        'orderby'        => 'post__in',
    ]);
}

		if(count($spons) > 0){
			foreach($spons as $key => $value){
				$id = $value->ID;
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
			$notified = implode(",", $sponsorEmails);
			if($the_post_id){
				update_field('notified_emails', $notified, $the_post_id );
			}
		}
		$showMsg = "New message has been posted successfully to all sponsors.";
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
        <?php esc_html_e( 'Message Logs', 'my-plugin-textdomain' ); ?>
    </h1> 
	
	<a class="button-primary" id="message_all_btn" href="javascript:;" onclick="jQuery('#message_box').show();jQuery(this).hide();">Message to All Sponsors</a>
	
	<div class="message-box col-container" style="display: none;" id="message_box">
		<div class="col-md-12">
			<form method="post" action="">
				<h3>Send New Message</h3>
				<?php wp_nonce_field( 'hm_send_message', 'hm_nonce' ); ?>
				<input type="hidden" name="post_message_all" value="true"/>
				<input type="hidden" id="spon_id" name="spon_id" value=""/>
				<input type="hidden" id="spon_title" name="spon_title" value=""/>
		    	<strong>Subject Title:</strong><br />
	    	    <input type="text" name="subject02" value="" style="width:80%;margin-bottom: 30px;margin-top: 5px;border: 1px solid #ccc;" />
		    	<?php
			    	$id = "post_text";
					$name = 'post_text';
					$content = esc_textarea( stripslashes( "" ) );
					$settings = array('tinymce' => true, 'textarea_name' => $name);
					wp_editor($content, $id, $settings);
		    	?>
		    	<!--<textarea style="width: 100%;" name="post_text" cols="10" rows="10"></textarea>-->
				<div style="text-align: right; padding-top: 20px;">
					<a class="button-primary" onclick="jQuery('#message_box').hide();jQuery('#message_all_btn').show();" href="javascript:;">Cancel</a>
					<button type="submit" class="button-primary">Send Message</button>
					<button
                    	type="button"
                    	class="button button-secondary preview-email-btn"
                    	data-name="<?php echo esc_attr('Jhon Doe'); ?>"
                    	data-body="<?php echo esc_attr($messageBody); ?>"
                    >
                    	Preview
                    </button>
				</div>
			</form>
		</div>
	</div>
	<script> window.SP_EMAIL_PREVIEW = { ajaxUrl: "<?php echo esc_js(admin_url('admin-ajax.php')); ?>", nonce: "<?php echo esc_js(wp_create_nonce('sp_email_preview')); ?>" }; </script>
<script>
jQuery(function ($) {

    const $modal  = $('#emailPreviewModal');
    const $frame  = $('#emailPreviewFrame');
    const $closeB = $('#closeEmailPreview');

    function openModal(html) {
        $modal.show();

        const frameEl = $frame[0];
        const doc = frameEl.contentWindow.document;
        doc.open();
        doc.write(html);
        doc.close();
    }

    function closeModal() {
        $modal.hide();
    }

    // Preview button click
    $(document).on('click', '.preview-email-btn', function (e) {
        e.preventDefault();

        const name = $(this).data('name') || '';
        const body = $(this).data('body') || '';

        let bodydata = '';
        if (typeof tinymce !== 'undefined' && tinymce.get('post_text')) {
            bodydata = tinymce.get('post_text').getContent();
        }

        $.ajax({
            url: window.SP_EMAIL_PREVIEW.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'preview_email_html_callback',
                user_name: name,
                email_body: bodydata,
                nonce: window.SP_EMAIL_PREVIEW.nonce
            },
            success: function (res) {
                if (res && res.data && res.data.html) {
                    openModal(res.data.html);
                } else {
                    alert('Preview failed');
                }
            },
            error: function (xhr) {
                alert('AJAX error');
                console.error(xhr);
            }
        });
    });

    // Close button
    $closeB.on('click', closeModal);

    // Click outside modal to close
    $modal.on('click', function (e) {
        if ($(e.target).is($modal)) {
            closeModal();
        }
    });

});
</script>


    
	<br />
	<br />
	
    <?php 
	

	// the query
	$wpb_all_query = new WP_Query(
    array(
        'post_type'      => 'animals',
        'post_status'    => 'publish',
        'orderby'        => 'ID',
        'order'          => 'DESC',
        'posts_per_page' => -1
    )
);
?>
 <div id="emailPreviewModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:99999;">
	<div style="background:#fff; width:90%; max-width:900px; margin:5vh auto; border-radius:8px; overflow:hidden;">
		<div style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid #ddd;">
			<strong>Email Preview</strong>
			<button type="button" class="button" id="closeEmailPreview">Close</button>
		</div>

		<div style="padding:0; height:75vh; overflow:auto;">
			<iframe id="emailPreviewFrame" style="width:100%; height:75vh; border:0;"></iframe>
		</div>
	</div>
</div>

 	<div class="colContainer" id="col-container">
	    <div class="col-6">
	        <div class="col-wrap">
	            <!--Table-->
	            <?php if ( $wpb_all_query->have_posts() ) : ?>
				<table class="wp-list-table widefat striped table-view-list" cellspacing="0">
					<thead>
						<tr>
							<th id="titls" class="manage-column column-columnname" scope="col">Images</th> 
							<th id="titls" class="manage-column column-columnname" scope="col">Sponsored Animal</th> 
							<th id="titls" class="manage-column column-columnname" scope="col">Date</th> 
							<th id="titls" class="manage-column column-columnname" scope="col">Status</th> 
							<th id="titls" class="manage-column column-columnname" scope="col">Subject Title</th> 
					       	<th id="messages" class="manage-column column-columnname" scope="col">Last Message</th> 
					       	<th id="messages" class="manage-column column-columnname" scope="col">Action</th> 
					   </tr>
					</thead>
					<tbody>
						<?php 
						$just_count = 0;
						while ( $wpb_all_query->have_posts() ) : $wpb_all_query->the_post();
						if ($just_count % 2 == 0) {
							$alternateclass = 'alternate';
						} else {
							$alternateclass = '';
						}
						?>
							<tr class="<?php echo $alternateclass; ?>" valign="top">
								<td class="manage-column column-columnname"> <?php the_post_thumbnail(array(50, 50)) ?> </td>
								<td class="manage-column column-columnname"><?php the_title(); ?></td>
								<td class="manage-column column-columnname"><?php the_date(); ?></td>
								<td class="manage-column column-columnname">Active</td>
				            	<?php 
				            	$args = array(
								    'post_type'   => 'messages',
								    'orderby'     => 'ID',
									'order' 	  => 'DESC',
									'post_status' => 'publish',
									'numberposts' => -1,
								    'meta_query'  => array(
								        array(
								            'key'     => 'specify',
								            'value'   => get_the_ID()
								        ),
								    ),
								);
								$messagePosts = get_posts( $args );
								if(isset($messagePosts[0])){
									$msgPost = $messagePosts[0];
				            		$the_content = apply_filters('the_content', $msgPost->post_content); 
				            		$the_content = wp_strip_all_tags( $the_content );
								}else{
				            		$the_content = ""; 
								}
				            	?>
					            <td class="manage-column column-columnname"><?php echo $msgPost->post_title ?></td>
					            <td class="manage-column column-columnname"><?php echo !empty($the_content) ? substr($the_content, 0, 250) : "..."; ?></td>
					            <td class="manage-column column-columnname"><a class="button-primary" href="<?php echo admin_url( 'admin.php?page=message-log' ).'&sp_id='.get_the_ID(); ?>">Details</a></td>
				         </tr>
			        <?php 
						$just_count++;
						endwhile; 
						wp_reset_postdata();
					?>
					</tbody>
				</table>
				
				<?php else : ?>
				    <p><?php _e( 'Sorry, no posts matched your criteria.' ); ?></p>
				<?php endif; ?>
	        </div>
	    </div>
	    <div class="col-6">
	        <div class="col-wrap">
	            <div class="form-wrap">
	                <!--Table-->
	                <?php
	                ?>
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
							            'key'     => 'specify_all',
							            'value'   => 'yes'
							        ),
							    ),
							);
							$xoquery = new WP_Query( $args );
			                 if ( $xoquery->have_posts() ) : 
							
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
									<td>
										<div class="msgDtail">
											<span class="md-date"><i class="icon-clock"></i><?php echo date('j/n/Y', strtotime($postMsg->post_date)); ?></span>
											<div class="fullMsg">
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
						
						<?php else : ?>
						    <tr><td colspan="6">
						    	<?php _e( 'Sorry, no posts matched your criteria.' ); ?>
						    </td></tr>
						<?php endif; ?>
						</tbody>
					</table>
					
	            </div>
	        </div>
	    </div>
	</div>
 
	<!--Messages columns-->
	