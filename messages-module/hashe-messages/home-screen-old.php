    <h1>
        <?php esc_html_e( 'Sponsorships', 'my-plugin-textdomain' ); ?>
    </h1> 

    <?php 
	// the query
	$wpb_all_query = new WP_Query(
		array(
			'post_type'=>'post', 
			'post_status'=>'publish',
    		'orderby' => 'ID',
			'order' => 'DESC',
			'meta_query' => array(
				array(
					'key' => 'sponsored_yet',
					'value' => true
				)
			),
			'posts_per_page'=>-1
		)
	); 
	?>
 
	<?php if ( $wpb_all_query->have_posts() ) : ?>
	<table class="wp-list-table widefat striped table-view-list" cellspacing="0">
		<thead>
			<tr>
				<th id="titls" class="manage-column column-columnname" scope="col">Images</th> 
				<th id="titls" class="manage-column column-columnname" scope="col">Sponsored Animal</th> 
				<th id="titls" class="manage-column column-columnname" scope="col">Date</th> 
				<th id="titls" class="manage-column column-columnname" scope="col">Status</th> 
		       
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
						'numberposts' => 1,
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
		            <td class="manage-column column-columnname"><?php echo !empty($the_content) ? substr($the_content, 0, 250) : "..."; ?></td>
		            <td class="manage-column column-columnname"><a class="button-primary" href="<?php echo admin_url( 'admin.php?page=message-log' ).'&sp_id='.get_the_ID(); ?>">Details</a></td>
	         </tr>
         <?php 
			$just_count++;
			endwhile; ?>
		</tbody>
	</table>

   <?php wp_reset_postdata();


	/*$args = array(
	    'post_type'  => 'messages',
	    'meta_query' => array(
	        array(
	            'key'     => 'specify',
	            'value'   => 4907
	        ),
	    ),
	);
	$xoquery = new WP_Query( $args );
	while ($xoquery->have_posts()) {
		$xoquery->the_post();
		echo get_the_title(). '<br>';
		
	}
	wp_reset_postdata();*/
    ?>
	<?php else : ?>
	    <p><?php _e( 'Sorry, no posts matched your criteria.' ); ?></p>
	<?php endif; ?>