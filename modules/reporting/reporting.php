<?php
/*

*/

if(is_admin())
{
    new Paulund_Wp_List_Table();
}
/**
 * Paulund_Wp_List_Table class will create the page to load the table
 */
class Paulund_Wp_List_Table
{
    /**
     * Constructor will create the menu item
     */
    public function __construct()
    {
    	global $wpdb;
        add_action( 'admin_menu', array($this, 'add_menu_example_list_table_page' ));
		
        add_action('wp_ajax_get_animals',array($this,'get_animals'));
        add_action('wp_ajax_nopriv_get_animals',array( $this,'get_animals') );
        
        add_action('wp_ajax_get_animals_dropdown',array($this,'get_animals_dropdown'));
        add_action('wp_ajax_nopriv_get_animals_dropdown',array( $this,'get_animals_dropdown') );
        /*add_action('wp_ajax_save_state',array($this,'save_state'));
        add_action('wp_ajax_nopriv_save_state',array( $this,'save_state') );

        wp_enqueue_script('jqueryValidate',plugin_dir_url( __FILE__ ) . 'js/jquery.validate.js');
        wp_enqueue_script('jqueryValidate');*/
        //include 'mapshortcode.php';
    }
	public function get_state($slug)
    {
		global $wpdb;
		$sql    = $wpdb->prepare( "SELECT * FROM scg_state WHERE is_deleted='N' AND is_active='Y' AND LOWER(state_abbr) = %s", $slug );
		$result = $wpdb->get_results( $sql, 'ARRAY_A' );
		$row = array();
		if(!empty($result)){
			$row = $result[0];
		}
		return $row;
    }
    public function get_state_data()
    {
		global $wpdb;
    	$state = explode( '-', sanitize_text_field( wp_unslash( $_REQUEST['id'] ?? '' ) ) );
    	$state_abbre  = strtolower( $state[1] ?? '' );

		$result = $this->get_state($state_abbre);
		if(!empty($result)){
			$resp['status'] = true;
			$resp['row'] 	= $result;			
		}else{
			$resp['status'] = false;
			$resp['msg'] 	= "No record found!";
		}
		echo json_encode($resp); exit;
    }     
    public function save_state()
    {
		global $wpdb;
    	
    	$input_arr = array(
    		"color" 		=> esc_sql($_REQUEST['color']),
    		"hover_color" 	=> esc_sql($_REQUEST['hover_color']),
    		"link" 			=> esc_sql($_REQUEST['url']),
    		"open_window" 	=> esc_sql($_REQUEST['open_window'])
		);
    	$wpdb->update( "scg_state", $input_arr, array( 'state_id' => absint( $_REQUEST['edit_id'] ?? 0 ) ) );

		$resp['status'] = true;
		$resp['msg'] 	= "Record has been updated successfully!";
		echo json_encode($resp); exit;
    }    
    public function add_menu_example_list_table_page()
    {
        add_menu_page( 'Sponsored', 'Reporting', 'manage_options', 'sponsored', array($this, 'list_table_page'),'dashicons-location-alt' );
        add_submenu_page( 'sponsored','Non Sponsored', 'Non Sponsored', 'manage_options', 'nonsponsored', array($this, 'list_non_sponsored') );
    }
    /**
     * Display the list table page
     *
     * @return Void
     */
    public function list_table_page()
    {
		
	    wp_enqueue_script('my-script','https://canvasjs.com/assets/script/canvasjs.min.js');
	    wp_enqueue_script('my-script',plugin_dir_url( __FILE__ ) . 'js/script.js');
        //wp_enqueue_script('my-script');
        
        require_once(plugin_dir_path( __FILE__ ).'filters-form.php');    
	    
        ?>
			<div class="wrap" style="margin-top: 40px;">
            	<?php $this->get_animals(); ?>
			</div>
        <?php
    }
    
    public function list_non_sponsored()
    {
        require_once(plugin_dir_path( __FILE__ ).'filters-form-non-spon.php');    
	    
        ?>
			<div class="wrap" style="margin-top: 40px;">
            	<?php $this->get_animals_non(); ?>
			</div>
        <?php
    }
    /**
	* Sponsored animals
	* 
	* @return
	*/
    public function get_animals(){
    	/**
		* Get Dynamic currency symbols
		*/
		$currencySymbols = @file_get_contents( plugin_dir_path( __FILE__ ) . 'currency_symbols_by_code.json');
		$currencySymbols = @json_decode($currencySymbols, TRUE);
		
        $term_post_ids = [];
        $args = array(
            'orderby'     => 'date',
            'order'       => 'DESC',            
            'status'   => 'publish',
            'post_type'   => 'sponsors',
            'numberposts'   => -1
        );
        /***Date check ***/
        $dataPoints = array();
        
        $term_category = '';
        
        if(isset($_POST['submit_reporting'])){
			$reqData = $_POST;
		}else{
			$reqData = $_REQUEST;
		}
        
        // if(isset($reqData['term_category']) && $reqData['term_category']){
        //     $term_category = esc_sql($reqData['term_category']); 
        // }
        $plan_type = '';
        if(isset($reqData['plan_type']) && !empty($reqData['plan_type'])){
            $plan_type = $reqData['plan_type']; 
        }
        
        if(isset($reqData['animal']) && $reqData['animal']){
			$animal = esc_sql($reqData['animal']) ;
		}
		
		/**
		* Date functionality
		*/
		$date_from = '';
        $date_to = '';
		if(isset($reqData['custom_range']) && $reqData['custom_range']){
			if(isset($reqData['date_from']) && $reqData['date_from']){
	            $date_from = esc_sql($reqData['date_from']) ;
	        }
	        if(isset($reqData['date_to']) && $reqData['date_to']){
	            $date_to = esc_sql($reqData['date_to']) ;
	        }
			
		}else{
		    
			$reportingYear = NULL;
			$reportingMonth = NULL;
			if(isset($reqData['reporting_year']) && $reqData['reporting_year']){
				$reportingYear = esc_sql($reqData['reporting_year']);
			}
			if(isset($reqData['reporting_month']) && $reqData['reporting_month']){
				$reportingMonth = esc_sql($reqData['reporting_month']);
			}
			
			if(empty($reportingYear)){
				$reportingYear = date('Y');
			}
			if(!empty($reportingMonth) && ($reportingMonth > 0 || $reportingMonth <= 12)){
				$date_from = date("Y-m-d",strtotime($reportingYear . "-" . $reportingMonth ."-01"));
				$date_to = date("Y-m-t", strtotime($date_from));
			}else{
				//To get last date of the year
				$date_to_any = date("Y-m-d",strtotime($reportingYear . "-" . "12-01"));
				
				$date_from = date("Y-m-d",strtotime($reportingYear . "-" . "01-01"));
				$date_to = date("Y-m-t", strtotime($date_to_any));
			}
			
			
		}
		
		
		
		
		global $wpdb;

$table = $wpdb->prefix . 'sponsorships';

// From your code
// $plan_type, $animal, $date_from, $date_to

$where   = [];
$params  = [];

/**
 * Date filter (assuming start_date is your sponsorship date column)
 * If you store date in another column (created_at / last_payment_at) replace it below.
 */
if (!empty($date_from) && !empty($date_to)) {
    $where[]  = "DATE(updated_at) BETWEEN %s AND %s";
    $params[] = $date_from;
    $params[] = $date_to;
}

/**
 * Plan type filter
 * (your column seems: sponsorship_type)
 */
if (!empty($plan_type)) {
    $where[]  = "sponsorship_type = %s";
    $params[] = $plan_type;
}

/**
 * Animal filter
 * Support both:
 * - if numeric => animal_id match
 * - else => animal_name LIKE
 */
if (!empty($animal)) {
    if (is_numeric($animal)) {
        $where[]  = "animal_id = %d";
        $params[] = (int) $animal;
    } else {
        $where[]  = "animal_name LIKE %s";
        $params[] = '%' . $wpdb->esc_like($animal) . '%';
    }
}

/**
 * OPTIONAL: only active
 */
// $where[]  = "status = %s";
// $params[] = "active";

$sql = "
    SELECT *
    FROM {$table}
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

// $sql .= "
//     GROUP BY animal_id
//     ORDER BY animal_name ASC
// ";
$results = !empty($params)
    ? $wpdb->get_results($wpdb->prepare($sql, $params),ARRAY_A)
    : $wpdb->get_results($sql,ARRAY_A);

// $results now contains distinct animals according to filters
// foreach ($results as $row) {
//     echo $row->animal_name . ' - ' . esc_html($row->updated_at) . "<br>";
// }
		
		
		
        
//         if($date_from !='' && $date_to ==''){
//             $date_from_arr = explode("-",$date_from);
//             $args['date_query'] = array(
//                 array(
//                     'after'     => array(
//                         'year'  => $date_from_arr[0],
//                         'month' => $date_from_arr[1],
//                         'day'   => $date_from_arr[2],                        
//                     ),
//                     'inclusive' => true,
//                 )
//             );
//         }elseif($date_to !='' && $date_from =='' ){
//             $date_to_arr = explode("-",$date_to);
//             $args['date_query'] = array(
//                 array(
//                     'before'    =>  array(
//                         'year'  => $date_to_arr[0],
//                         'month' => $date_to_arr[1],
//                         'day'   => $date_to_arr[2],                        
//                     ),
//                     'inclusive' => true,
//                 )
//             );
//         }elseif($date_to !='' && $date_from !='' ){
//             $date_from_arr = explode("-",$date_from);
//             $date_to_arr = explode("-",$date_to);
//             $args['date_query'] = array(
//                 array(
//                     'after'     => array(
//                         'year'  => $date_from_arr[0],
//                         'month' => $date_from_arr[1],
//                         'day'   => $date_from_arr[2],                        
//                     ),
//                     'before'    => array(
//                         'year'  => $date_to_arr[0],
//                         'month' => $date_to_arr[1],
//                         'day'   => $date_to_arr[2],                        
//                     ),
//                     'inclusive' => true,
//                 )
//             );
//         }
        
//         $meta_query = array();
//         #Plan check
//         if(!empty($plan_type)){ 
//         	$plan_type = esc_sql($plan_type) ;
//         	//$plan_type_old = str_replace("sponsor", "donation", $plan_type);
//             $meta_query[] = array(
//                 'key'     => 'plan',
//                 'value'   => $plan_type,
//                 'compare' => '=',
//             );
//             /*$meta_query[] = array( 
//                 'key'     => 'plan',
//                 'value'   => $plan_type_old,
//                 'compare' => '=',
//             );*/
//         }else{
// 			$meta_query[] = array(
//                 'key'     => 'plan',
//                 'value'   => array('Monthly sponsor', 'One-time sponsor'),
//                 'compare' => 'IN',
//             );
// 		}
        
//         /***Category check end***/
//         $animal_ids = array();
//         if(!empty($term_category)){
//             $animal_ids = get_posts(array(
// 			    'post_type'   => "post", // get all posts.
// 			    'numberposts'   => -1, // get all posts.
// 			    'tax_query'     => array(
// 			        array(
// 			            'taxonomy'  => 'category',
// 			            'field'     => 'term_id',
// 			            'terms'     => $term_category,
// 			        ),
// 			    ),
// 			    'fields'        => 'ids', // Only get post IDs
// 			));
//         }
// 		$specifyIds = array();
        
//         if($animal_ids){
// 			$specifyIds = $animal_ids;
// 		}
// 		if(!empty($animal)){
// 			$specifyIds = $animal;
// 		}
		
        
//         if($specifyIds){
//             $meta_query[] = array(
//                 'key'     => 'specify',
//                 'value'   => $specifyIds,
//                 'compare' => 'IN',
//             );             
//         }
        
//         if($meta_query){
// 			$args['meta_query'] = array(
// 				"relation" => "OR",
// 				$meta_query
// 			);
// 		}
		
//         $posts = get_posts( $args );
		$currencyTotal = array();



    // get data from sponsorship		
    //     global $wpdb;

    // $t = $wpdb->prefix . 'sponsorships';

    // $s      = trim((string) sp_get_request('s', ''));
    // $s_a      = trim((string) sp_get_request('s', ''));
    // $status = (string) sp_get_request('status', '');
    // $type   = (string) sp_get_request('type', '');
    // $paged  = max(1, (int) sp_get_request('paged', 1));
    // $per    = 20000;
    // $offset = ($paged - 1) * $per;

    // $where  = "WHERE 1=1";
    // $params = [];

    // if ($status !== '') { $where .= " AND status=%s"; $params[] = $status; }
    // if ($type !== '')   { $where .= " AND sponsorship_type=%s"; $params[] = $type; }
    // if ($s !== '') {
        
    //     $meta_like = '%' . $wpdb->esc_like($s) . '%';

    //     $sponsor_ids_meta = $wpdb->get_col(
    //       $wpdb->prepare(
    //         "
    //         SELECT DISTINCT p.ID
    //         FROM {$wpdb->posts} p
    //         INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
    //         WHERE p.post_type = %s
    //           AND p.post_status IN ('publish','private')
    //          AND post_title LIKE %s
    //         ",
    //         'sponsors',
    //         $meta_like
    //       )
    //     );
    //     $sponsor_ids = [];
        
    //     $sponsor_ids = array_values(array_unique(array_merge($sponsor_ids, $sponsor_ids_meta)));

        
    //     if ( ! empty($sponsor_ids) ) {
    //         $in_placeholders = implode(',', array_fill(0, count($sponsor_ids), '%d'));
        
    //         $where .= " AND sponsor_id IN ($in_placeholders)";
        
    //         // merge numeric ids into params
    //         $params = array_merge($params, array_map('intval', $sponsor_ids));
    //     }

    //     // $like = '%' . $wpdb->esc_like($s) . '%';
    //     // $where .= " AND (CAST(sponsor_id AS CHAR) LIKE %s)";
    //     // $params = array_merge($params, [$like]);
    // }
    //  // animal name 
    // if ($s_a !== '') {
    //     // $like = '%' . $wpdb->esc_like($s) . '%';
    //     // $where .= " AND (txn_id LIKE %s OR subscr_id LIKE %s OR item_number LIKE %s OR CAST(animal_id AS CHAR) LIKE %s OR CAST(sponsor_id AS CHAR) LIKE %s)";
    //     // $params = array_merge($params, [$like,$like,$like,$like,$like]);
        
    //     $meta_like = '%' . $wpdb->esc_like($s_a) . '%';

    //     $sponsor_ids_meta = $wpdb->get_col(
    //       $wpdb->prepare(
    //         "
    //         SELECT DISTINCT p.ID
    //         FROM {$wpdb->posts} p
    //         INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
    //         WHERE p.post_type = %s
    //           AND p.post_status IN ('publish','private')
    //          AND post_title LIKE %s
    //         ",
    //         'animals',
    //         $meta_like
    //       )
    //     );
    //     $animal_ids = [];
        
    //     $animal_ids = array_values(array_unique(array_merge($animal_ids, $sponsor_ids_meta)));

        
    //     if ( ! empty($animal_ids) ) {
    //         $in_placeholders = implode(',', array_fill(0, count($animal_ids), '%d'));
        
    //         $where .= " AND animal_id IN ($in_placeholders)";
        
    //         // merge numeric ids into params
    //         $params = array_merge($params, array_map('intval', $animal_ids));
    //     }
    // }

    // $sql_count = $wpdb->prepare("SELECT COUNT(*) FROM {$t} {$where}", $params);
    // $total = (int) $wpdb->get_var($sql_count);

    // $sql = "SELECT * FROM {$t} {$where} ORDER BY COALESCE(last_payment_at, start_date, updated_at) DESC LIMIT %d OFFSET %d";
    // $params2 = array_merge($params, [$per, $offset]);
    $rows = $results; //$wpdb->get_results($wpdb->prepare($sql, $params2), ARRAY_A);

    // $base_url = remove_query_arg('paged');
    
        ?>
        
        <div id="tbl_div">
        	<div>Total records found (<?php echo count($rows); ?>)</div><br />
            <table class="wp-list-table widefat fixed striped table-view-list sponsors">
                <tr> 
                    <th class="manage-column column-title column-primary sortable desc">
                        <!-- <a href="<?php echo site_url() ?>/wp-admin/edit.php?post_type=sponsors&amp;orderby=title&amp;order=asc"> -->
                        <a href="#">
                            <span>Date</span>
                            <!--<span class="sorting-indicator"></span>-->
                        </a>
                    </th>
                    <th class="manage-column column-title column-primary sortable desc">
                        <!-- <a href="<?php echo site_url() ?>/wp-admin/edit.php?post_type=sponsors&amp;orderby=title&amp;order=asc"> -->
                        <a href="#">
                            <span>Name</span>
                            <!--<span class="sorting-indicator"></span>-->
                        </a>
                    </th>
                    <th class="manage-column column-title column-primary sortable desc">
                        <a href="#">
                            <span>Email</span>
                            <!--<span class="sorting-indicator"></span>-->
                        </a>
                    </th>
                    <th class="manage-column column-title column-primary sortable desc">
                        <a href="#">
                            <span>Plan</span>
                            <!--<span class="sorting-indicator"></span>-->
                        </a>
                    </th>
                    <th class="manage-column column-title column-primary sortable desc">
                        <a href="#">
                            <span>Animal Name</span>
                            <!--<span class="sorting-indicator"></span>-->
                        </a>
                    </th>
                    <th class="manage-column column-title column-primary sortable desc">
                        <a href="#">
                            <span>Amount</span>
                            <!--<span class="sorting-indicator"></span>-->
                        </a>
                    </th>
                </tr>
                <?php if(!empty($rows)){ 
                    
                    $sum = 0;
                    $currencyTotal = array();
                    $monthlyTotal = array();
                    foreach($rows as $post){
                    	
                        //$meta = get_post_meta( $post->ID );
                        $amount = $post['amount']; //get_post_meta( $post->ID, 'amount', true );
                        $amount = (int)$amount;
                        if($amount <= 0){
							continue;
						}
                        
                        $name = get_post_meta( $post['sponsor_id'], 'name', true );
                        $email = get_post_meta( $post['sponsor_id'], 'email', true );
                        $plan = $post['sponsorship_type']; //get_post_meta( $post->ID, 'plan', true );
                        // $specify = get_post_meta( $post->ID, 'specify', true );
                        
                        $mcCurrency = "GBP";
                        $currencyMeta = $post['currency']; //get_post_meta( $post->ID, 'mc_currency', true );
						if(!empty($currencyMeta)){
							$mcCurrency = $currencyMeta;
						}
						$currencySign = "$";
						if(isset($currencySymbols[$mcCurrency]['symbol']) && !empty($currencySymbols[$mcCurrency]['symbol'])){
							$currencySign = $currencySymbols[$mcCurrency]['symbol'];
						}
						
						#Currency Total
						if(isset($currencyTotal[$mcCurrency])){
							$currencyTotal[$mcCurrency] += $amount;
						}else{
							$currencyTotal[$mcCurrency] = $amount;
						}
						
						#Monthly Total
						$postedAt = $post['updated_at'];
						$getMonth = date('F', strtotime($postedAt));
						if(isset($monthlyTotal[$getMonth][$mcCurrency])){
							$monthlyTotal[$getMonth][$mcCurrency] += $amount;
						}else{
							$monthlyTotal[$getMonth][$mcCurrency] = $amount;
						}
						
						
                        /*if(isset($_REQUEST['term_category']) && $_REQUEST['term_category']){
                            $term_lists = get_the_terms($specify, 'category');
                            $continueGlag = false;
                            if(!empty($term_lists)){
                                foreach($term_lists as $term_list){
                                    if($term_category != $term_list->term_id){
                                        $continueGlag = true;
                                    }                                                  
                                }                        
                            }
                            if($continueGlag){
                                continue;
                            }
                        }*/
                        $sum = $sum + $amount;
                        ?>
                        <tr> 
                            <td><?php echo $postedAt;//$post->ID ?></td>
                            <td><?php echo $name;//$post->ID ?></td>
                            <td><?php echo $email; ?></td>
                            <td><?php echo $plan; ?></td> 
                            <td><?php echo get_the_title( $post['animal_id'] ); ?></td>
                            <td><?php echo $currencySign . " " . $amount; ?></td>
                        </tr>                            
                    <?php } ?>
                    
                    	<?php 
                    		#Data for graph
                    		$dataPoints = array();
                    		if($currencyTotal){
								foreach($currencyTotal as $key => $value){
									$currencyCode = $key;
									$currencySignTotal = "$";
									if(isset($currencySymbols[$currencyCode]['symbol']) && !empty($currencySymbols[$currencyCode]['symbol'])){
										$currencySignTotal = $currencySymbols[$currencyCode]['symbol'];
									}
									//$dataPoints[] = array("label"=> $key, "symbol"=> $currencySignTotal, "y"=> $value);
									?>
									<tr> 
			                            <td colspan="5"><strong><?php echo $key; ?> Total</strong></td>
			                            <td><strong><?php echo $currencySignTotal; ?><?php echo $value; ?></strong></td>
			                        </tr>
									<?php
									
									$dataPoint = array(
										"type" => "line",
										"axisYType" => "secondary",
										"indexLabel" => "{y}",
										"name" => $currencyCode . "($currencySignTotal)",
										"showInLegend" => true,
										"markerSize" => 0,
										//"yValueFormatString" => "$#,###k"
									);
									foreach($monthlyTotal as $month_key => $month_val){
										if(isset($month_val[$currencyCode])){
											$totalc = $month_val[$currencyCode];
										}else{
											$totalc = 0;
										}
										$dataPoint['dataPoints'][] = array("label"=> $month_key, "y"=> $totalc);
									}
									
									$dataPoints[] = $dataPoint;
								}
								
							}
                    	?>
                <?php }else{ ?>
                    <tr> 
                        <td colspan="5"><strong>No Record Found</strong></td>
                    </tr>
                <?php } ?>
            </table>
        </div>
        <br />
        <div id="chartContainer" style="height: 400px; width: 100%;"></div>
        <?php
        	//Sample Code here
         ?>
			
        <script>
			window.onload = function () {

			var chart = new CanvasJS.Chart("chartContainer", {
				title: {
					text: "Currency Trends"
				},
				axisX: {
					valueFormatString: "MMM YYYY"
				},
				axisY2: {
					title: "Amount",
					prefix: "",
					suffix: ""
				},
				toolTip: {
					shared: true
				},
				legend: {
					cursor: "pointer",
					verticalAlign: "bottom",
					horizontalAlign: "center",
					dockInsidePlotArea: false,
					itemclick: toogleDataSeries
				},
				data: <?php echo json_encode($dataPoints);?>
			});
			chart.render();

			function toogleDataSeries(e){
				if (typeof(e.dataSeries.visible) === "undefined" || e.dataSeries.visible) {
					e.dataSeries.visible = false;
				} else{
					e.dataSeries.visible = true;
				}
				chart.render();
			}

			}
			</script>
        
        <?php
        /*if(isset($_REQUEST['action']) && $_REQUEST['action']){
            exit;
        }*/        
    }
	
	/**
	* Non sponsored animals
	* 
	* @return
	*/
    public function get_animals_non(){
        $term_post_ids = [];
        $args = array(
            'orderby'     => 'date',
            'order'       => 'DESC',            
            'status'   => 'publish',
            'post_type'   => 'post',
            'numberposts'   => -1,
            'meta_query' => array(
            	'relation' => 'OR',
		        array(
		            'key'     => 'sponsored_yet',
		            'compare' => '!=',
		            'value' => 1
		        )
		    ),
        );
        /***Date check ***/
        $dataPoints = array(); 
        
        $term_category = '';
        
        if(isset($_POST['submit_reporting'])){
			$reqData = $_POST;
		}else{
			$reqData = $_REQUEST;
		}
        
        if(isset($reqData['term_category']) && $reqData['term_category']){
            $term_category = esc_sql($reqData['term_category']); 
        }else{
			$parent_cat_arg = array('hide_empty' => FALSE, 'parent' => 37, 'fields' => 'ids' );
			$term_category = get_terms('category',$parent_cat_arg);//category name
		}
		
		/***Category check end***/
        if($term_category){
            $args['tax_query'] = array(
		        array(
		            'taxonomy'  => 'category',
		            'field'     => 'term_id',
		            'terms'     => $term_category,
		        ),
		    );
        }
		
		/**
		* Date functionality
		*/
		$date_from = '';
        $date_to = '';
		if(isset($reqData['custom_range']) && $reqData['custom_range'])
		{
			if(isset($reqData['date_from']) && $reqData['date_from']){
	            $date_from = esc_sql($reqData['date_from']) ;
	        }
	        if(isset($reqData['date_to']) && $reqData['date_to']){
	            $date_to = esc_sql($reqData['date_to']) ;
	        }
			
		}else
		{
			$reportingYear = NULL;
			$reportingMonth = NULL;
			if(isset($reqData['reporting_year']) && $reqData['reporting_year']){
				$reportingYear = esc_sql($reqData['reporting_year']);
			}
			if(isset($reqData['reporting_month']) && $reqData['reporting_month']){
				$reportingMonth = esc_sql($reqData['reporting_month']);
			}
			
			if($reportingYear != "all"){
				if(empty($reportingYear)){
					$reportingYear = date('Y');
				}
				if(!empty($reportingMonth) && ($reportingMonth > 0 || $reportingMonth <= 12)){
					$date_from = date("Y-m-d",strtotime($reportingYear . "-" . $reportingMonth ."-01"));
					$date_to = date("Y-m-t", strtotime($date_from));
				}else{
					//To get last date of the year
					$date_to_any = date("Y-m-d",strtotime($reportingYear . "-" . "12-01"));
					
					$date_from = date("Y-m-d",strtotime($reportingYear . "-" . "01-01"));
					$date_to = date("Y-m-t", strtotime($date_to_any));
				}
			}
			
		}
        
        if($date_from !='' && $date_to =='')
        {
            $date_from_arr = explode("-",$date_from);
            $args['date_query'] = array(
                array(
                    'after'     => array(
                        'year'  => $date_from_arr[0],
                        'month' => $date_from_arr[1],
                        'day'   => $date_from_arr[2],                        
                    ),
                    'inclusive' => true,
                )
            );
        }elseif($date_to !='' && $date_from =='' )
        {
            $date_to_arr = explode("-",$date_to);
            $args['date_query'] = array(
                array(
                    'before'    =>  array(
                        'year'  => $date_to_arr[0],
                        'month' => $date_to_arr[1],
                        'day'   => $date_to_arr[2],                        
                    ),
                    'inclusive' => true,
                )
            );
        }elseif($date_to !='' && $date_from !='' )
		{
            $date_from_arr = explode("-",$date_from);
            $date_to_arr = explode("-",$date_to);
            $args['date_query'] = array(
                array(
                    'after'     => array(
                        'year'  => $date_from_arr[0],
                        'month' => $date_from_arr[1],
                        'day'   => $date_from_arr[2],                        
                    ),
                    'before'    => array(
                        'year'  => $date_to_arr[0],
                        'month' => $date_to_arr[1],
                        'day'   => $date_to_arr[2],                        
                    ),
                    'inclusive' => true,
                )
            );
        }else{
			//Do nothing
		}
        $posts = get_posts( $args );
		$currencyTotal = array();

        ?>
        
        <div id="tbl_div">
        	<div>Total records found (<?php echo count($posts); ?>)</div><br />
            <table class="wp-list-table widefat fixed striped table-view-list sponsors">
                <tr> 
                    <th class="manage-column column-title column-primary sortable desc">
                        <!-- <a href="<?php echo site_url() ?>/wp-admin/edit.php?post_type=sponsors&amp;orderby=title&amp;order=asc"> -->
                        <a href="#">
                            <span>Date</span>
                            <!--<span class="sorting-indicator"></span>-->
                        </a>
                    </th>
                    <th class="manage-column column-title column-primary sortable desc">
                        <a href="#">
                            <span>Categories</span>
                            <!--<span class="sorting-indicator"></span>-->
                        </a>
                    </th>
                    <th class="manage-column column-title column-primary sortable desc">
                        <a href="#">
                            <span>Animal Name</span>
                            <!--<span class="sorting-indicator"></span>-->
                        </a>
                    </th>
                </tr>
                <?php if(!empty($posts)){ 
                    $sum = 0;
                    $currencyTotal = array();
                    $monthlyTotal = array();
                    foreach($posts as $post){
						#Monthly Total
						$postedAt = $post->post_date;
						$getMonth = date('F', strtotime($postedAt));
						$animalName = $post->post_title;
						$categoryPosts = get_the_category($post->ID);
						$categories = "";
						foreach($categoryPosts as $key => $category){
							if(!empty($categories)){
								$categories .= ", " . $category->name;
							}else{
								$categories = $category->name;
							}
						}
						$sponsored_yet = get_post_meta($post->ID, 'sponsored_yet');
                        ?>
                        <tr> 
                            <td><?php echo $postedAt;//$post->ID ?></td>
                            <td><?php echo $categories; ?></td>
                            <td><?php echo $animalName; ?></td>
                        </tr>                          
                    <?php } ?>
                <?php }else{ ?>
                    <tr> 
                        <td colspan="5"><strong>No Record Found</strong></td>
                    </tr>
                <?php } ?>
            </table>
        </div>
        
        <?php
        /*if(isset($_REQUEST['action']) && $_REQUEST['action']){
            exit;
        }*/        
    }

	/**
	* Description: Prepare dropdown menu for animals list
	* @animal_cat
	* @animal
	* @return
	*/
    public function get_animals_dropdown(){
    	//Post Params
    	//animal
    	//animal_cat
    	if(isset($_POST['animal_cat']) && !empty($_POST['animal_cat'])){
			$selectCat = $_POST['animal_cat'];
		}else{
			$parent_cat_arg = array('hide_empty' => TRUE, 'parent' => 37, 'fields' => 'ids' );
			$selectCat = get_terms('category',$parent_cat_arg);//category name
		}
		
		$animals_args = array(
            'post_type' => 'post',
            'numberposts' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'category',
                    'field'    => 'term_id',
                    'terms'    => $selectCat
                )
            ),
            'meta_query' => array( 
            	"relation" => "OR",
            	array(
	                'key'     => 'sponsored_yet',
	                'value'   => 0,
	                'compare' => '!=',
	            ),
            	array(
	                'key'     => 'sponsored_yet',
	                'value'   => 1,
	                'compare' => '=',
	            )
            ),
        );
        $animalss = get_posts( $animals_args );        
        $animalDropdown = '<select name="animal" id="animal">';
		$animalDropdown .= '<option value="">Select Animal Name</option>';
        if(!empty($animalss)){
        	$selectAnimal = NULL;
        	if(isset($_POST["animal"])){
				$selectAnimal = $_POST["animal"];
			}
            foreach($animalss as $animal){ 
	        	$selectedA = '';
				if($animal->ID == $selectAnimal){
					$selectedA = "selected=\'selected\'";
				}
				$animalDropdown .= '<option value="'.$animal->ID.'" '.$selectedA.'>' . $animal->post_title . '</option>';
		    }
	    }
	    $animalDropdown .= '</select>';
	    echo $animalDropdown;exit();
	}
	
    public function add_map()
    {
		
		       
        ?>
			<input type="hidden" name="admin-ajax-url" id="admin-ajax-url" value="<?php echo admin_url('admin-ajax.php'); ?>" />
			<input type="hidden" name="admin-url" id="admin-url" value="<?php echo admin_url('admin.php'); ?>" />
            <div class="wrap">
                <h2>Non Sponsored</h2>  
                <?php /* ?>           
				<form method="post" name="createsvgmap" id="createuser" class="validate">
					<input type="hidden" name="edit_id" id="edit_id" value="<?php echo (!empty($row) ? $row['state_id']:''); ?>">
					<input type="hidden" name="action" id="action" value="save_state">
					<table class="form-table">
						<tbody>
							<tr class="form-field form-required">
								<th scope="row">
									<label>State <span class="description">(required)</span></label>
								</th>
								<td>
									<input type="text" name="state_abbre" id="state_abbre" class="required" readonly value="<?php echo (!empty($row) ? $row['state_name']:''); ?>">
								</td>
							</tr>
							<tr class="form-field form-required">
								<th scope="row">
									<label>Color <span class="description">(required)</span></label>
								</th>
								<td>
									<input type="text" name="color" id="color" class="required cp-basic" value="<?php echo (!empty($row) ? $row['color']:'6699cc'); ?>">
								</td>
							</tr>
							<tr class="form-field">
								<th scope="row">
									<label>Hover Color <span class="description">(required)</span></label>
								</th>
								<td>
									<input type="text" name="hover_color" id="hover_color" class="required cp-basic" value="<?php echo (!empty($row) ? $row['hover_color']:'367cb3'); ?>">
								</td>
							</tr>
							<tr class="form-field">
								<th scope="row"><label>Website</label></th>
								<td>
									<input type="url" name="url" id="url" class="required" value="<?php echo (!empty($row) ? $row['link']:''); ?>">
								</td>
							</tr>
							<tr>
								<th scope="row">&nbsp;</th>
								<td>
									<input type="checkbox" name="open_window" id="open_window" value="1" <?php echo (!empty($row) ? 'checked':''); ?>>
									<label for="send_user_notification">Open link in new tab.</label>
								</td>
							</tr>
						</tbody>
					</table>
					<p class="submit">
						<input type="button" name="saveState" id="saveState" class="button button-primary" value="Save">
					</p>
				</form>
                <?php */ ?>   
            </div>
        <?php
    }
}

?>