<?php
/*
Author URI: http://www.hashe.com
*/

 /**
     * Returns the number of posts a particular metakey is used for.
     * Optionally qualified by a specific value or values in an array of the meta key.
     *
     * @param string            $key
     * @param null|string|array $value
     *
     * @return mixed
     */
    function get_meta_count( $key, $value = null) {
        global $wpdb;

        $where = get_meta_where($key, $value);
        $count = $wpdb->get_row("SELECT COUNT(*) AS THE_COUNT FROM $wpdb->postmeta WHERE $where");
        return $count->THE_COUNT;
    }

// auto title messages
function wpa65253_time_title( $title ) {
    global $post;
    // global $post_type;
    // if ('messages' == $post_type) {
	   if ( isset( $post->ID ) ) :
	       if ( empty( $_POST['post_title'] ) && 'messages' == get_post_type( $post->ID ) )
	            $title = date('Y-m-d H:i:s');
	 endif;
	 // }
    return $title;
}
add_filter ( 'title_save_pre', 'wpa65253_time_title' );



function my_admin_page_contents() {
    global $pagenow;
    #Email function for message
    require_once( plugin_dir_path( __FILE__ ) . 'sponsor-message-email.php' );
    
    if ( ! empty( $_GET['sp_id'] ) ) {
		$postId = absint( $_GET['sp_id'] );
		require_once( plugin_dir_path( __FILE__ ) . 'message-single-screen.php' );
    } else {
		require_once( plugin_dir_path( __FILE__ ) . 'home-screen.php' );
	}
}

function my_acf_load_value( $value, $post_id, $field ) {

	$query_vars = $_SERVER['QUERY_STRING'];
	$query_string_arr = [];
	parse_str($query_vars,$query_string_arr );
	$sp_id = (isset($query_string_arr['sp_id']) ? $query_string_arr['sp_id'] : "" );
	$value = $sp_id;

   return $value;
}

// Apply to fields named "hero_text".
add_filter('acf/load_value/name=sponsored_id', 'my_acf_load_value', 10, 3);