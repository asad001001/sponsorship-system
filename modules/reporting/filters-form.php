<div class="wrap">
    <h1 class="wp-heading-inline">Sponsored Reporting</h1>
    <!--<a href="<?php echo admin_url('admin.php?page=nonsponsored'); ?>" class="page-title-action">Non Sponsored</a>-->
    <hr class="wp-header-end">
    <div class="main-content"> 
        <h3>Filter reports by:</h3>
        <form action="" method="post"><!--id="reporting_form" onsubmit="return false;"-->
            <!--<input type="hidden" name="admin-ajax-url" id="admin-ajax-url" value="<?php echo admin_url('admin-ajax.php'); ?>">
            <input type="hidden" name="admin-url" id="admin-url" value="<?php echo admin_url('admin.php'); ?>">-->
            <div class="tablenav top">
                <div class="alignleft actions"style="padding:5px;">
                	<?php 
                		if(isset($_POST['custom_range'])){
							$slectedCheck = "checked='checked'";
						}else{
							$slectedCheck = '';
						}
                	?>
                	<input type="checkbox" name="custom_range" id="custom_range" value="1" <?php echo $slectedCheck; ?> /> Custom Range
                </div>
                <div class="alignleft actions">
                	<select name="reporting_year" id="reporting_year">
                    	<!--<option value="">Select Year</option>-->
                        <?php 
                        	$curYear = date('Y');
                        	$years = range($curYear - 10, $curYear);
                        	$years = array_reverse($years);
                        	$selectYear = $curYear;
                        	if(isset($_POST['reporting_year'])){
								$selectYear = $_POST['reporting_year'];
							}
							// create range of required years and iterate over them
							foreach($years as $year) {
								$selected = '';
								if($selectYear == $year){
									$selected = "selected='selected'";
								}
							    echo "<option value='$year' $selected>$year</option>";
							} 
                        ?>
                    </select>
                </div>
                <div class="alignleft actions">
                	<select name="reporting_month" id="reporting_month">
                    	<option value="">Select Month (All)</option>
                        <?php
                        	$selectMonth = NULL;
                        	if(isset($_POST['reporting_month'])){
								$selectMonth = $_POST['reporting_month'];
							}
							// create range of required years and iterate over them
							for($i = 1 ; $i <= 12; $i++){
								$selected = '';
								if($selectMonth == $i){
									$selected = "selected='selected'";
								}
							    echo "<option value='$i' $selected>" .date("F",strtotime(date("Y")."-".$i."-01")). "</option>";
							} 
                        ?>
                    </select>
                </div>
                <div class="alignleft actions" id="date_from_field" style="display: none;">
                    <label>Date From</label>
                    <input type="date" name="date_from" id="date_from" value="<?php echo (isset($_POST['date_from']) ? $_POST['date_from'] : '');  ?>">
                </div>
                <div class="alignleft actions" id="date_to_field" style="display: none;">
                    <label>Date To</label>
                    <input type="date" name="date_to" id="date_to" value="<?php echo (isset($_POST['date_to']) ? $_POST['date_to'] : '');  ?>">
                </div>
                
                <div class="alignleft actions">
                   
                    <?php 
                    $selectPlan = "";
                	if(isset($_POST['plan_type'])){
						$selectPlan = $_POST['plan_type'];
					}
                    ?>
                    <select name="plan_type" id="plan_type">
                    	<option value="" <?php echo ($selectPlan == ''? 'selected="selected"' : ''); ?>>Select Plan</option>
                    	<option value="one_time" <?php echo ($selectPlan == 'one_time'? 'selected="selected"' : ''); ?>>One Time Sponsor</option>
                    	<option value="monthly"  <?php echo ($selectPlan == 'monthly'? 'selected="selected"' : ''); ?>>Monthly Sponsor</option>
                    </select>
                </div>
                <?php
                	//Selected Animal
	            	$selectAnimal = NULL;
	            	if(isset($_POST['animal'])){
						$selectAnimal = $_POST['animal'];
					}
					
					global $wpdb;

                $table = $wpdb->prefix . 'sponsorships';
                
                $results = $wpdb->get_results(
                    "
                    SELECT DISTINCT animal_id, animal_name
                    FROM {$table}
                    WHERE animal_id IS NOT NULL
                    ORDER BY animal_name ASC
                    "
                );
//                 foreach ($results as $row) {
//     echo $row->animal_id . ' - ' . esc_html($row->animal_name) . '<br>';
// }

				?>
				
                <div class="alignleft actions" style="display: none;">
                	<input type="hidden" id="selected_animal" name="selected_animal" value="<?php echo $selectAnimal; ?>"/>
                </div>
                <div class="alignleft actions" id="sorted-dropdown">
                    <select name="animal" id="animal"> 
                        <option value="">Select Animal Name</option>
                        <?php 
                        
                        if(!empty($results)){
                            
                            foreach($results as $animal){ 
                                
                        	$selectedA = '';
							if($animal->animal_id == $selectAnimal){
								$selectedA = "selected='selected'";
							}
                        ?>
                                <option value="<?php echo $animal->animal_id; ?>" <?php echo $selectedA; ?>>
                                    <?php echo $animal->animal_name; ?>
                                </option>
                            <?php 
                            }
                        } ?>
                    </select>
                </div>
                <div class="alignleft actions">
                     <input class="button-primary" type="submit" name="submit_reporting" value="Search"><!-- id="submit_reporting"-->
                </div>
            </div>
        </form>
    </div>
</div>
<br />
<br />
<script>
	jQuery(document).ready(function(){
	
	jQuery('#custom_range').click(function(){
		if(jQuery(this).prop('checked')){
			jQuery("#reporting_month").hide();
			jQuery("#reporting_year").hide();
			jQuery("#date_from_field").show();
			jQuery("#date_to_field").show();
		}else{
			jQuery("#reporting_month").show();
			jQuery("#reporting_year").show();
			jQuery("#date_from_field").hide();
			jQuery("#date_to_field").hide();
		}
	});
	
	if(jQuery('#custom_range').prop('checked')){
		jQuery("#reporting_month").hide();
		jQuery("#reporting_year").hide();
		jQuery("#date_from_field").show();
		jQuery("#date_to_field").show();
	}else{
		jQuery("#reporting_month").show();
		jQuery("#reporting_year").show();
		jQuery("#date_from_field").hide();
		jQuery("#date_to_field").hide();
	}
	////////submit form ends
	jQuery("#term_category").change(function(){
		var term_category 	= jQuery(this).val();
		console.log(term_category);
		var animal 	= jQuery("#selected_animal").val();
		var formData = "action=get_animals_dropdown&animal="+animal+"&animal_cat="+term_category;
		jQuery.ajax({
			type: "POST", 
			url: "<?php echo admin_url('admin-ajax.php'); ?>",
			data : formData,
			success: function(resp){
				console.log(`resp`, resp);
				jQuery("#sorted-dropdown").html(resp);
			}
		});	
	});	
});
</script>
<style>
	.actions{
		margin-top: 20px !important;
	}
</style>