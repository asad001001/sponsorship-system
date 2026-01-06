<div class="wrap">
    <h1 class="wp-heading-inline">Non sponsored</h1>
    <a href="<?php echo admin_url('admin.php?page=sponsored'); ?>" class="page-title-action">Sponsored</a>
    <hr class="wp-header-end">
    <div class="main-content"> 
        <h3>Filter reports by:</h3>
        <form action="" method="post">
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
                    	
                        <?php 
                        	$curYear = date('Y');
                        	$years = range($curYear - 10, $curYear);
                        	$years = array_reverse($years);
                        	$selectYear = $curYear;
                        	if(isset($_POST['reporting_year'])){
								$selectYear = $_POST['reporting_year'];
							}
								
							// create range of required years and iterate over them
							$count = 1;
							foreach($years as $year) {
								$selected = '';
								if($selectYear == $year){
									$selected = "selected='selected'";
								}
								if($count == 1){
									echo "<option value='all' $selected>Select Year (All)</option>";
								}
							    echo "<option value='$year' $selected>$year</option>";
							    $count++;
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
                        $categories = get_terms( 'category', array(
                            'orderby' => 'name',
                            'order' => 'ASC',
                            'parent' => 37,
                            'exclude' => ['38']
                        ) ); 
                        $category_ids =[];
                    ?>
                    <select name="term_category" id="term_category"> 
                        <option value="">Select Post Category</option>
                        <?php
                        $selectCat = NULL;
                    	if(isset($_POST['term_category'])){
							$selectCat = $_POST['term_category'];
						}
                        if(!empty($categories)){ 
                            foreach($categories as $category){ 
                                $selectCatText = '';
								if($selectCat == $category->term_id){
									$selectCatText = "selected='selected'";
								}
                            ?>
                                <option value="<?php echo $category->term_id; ?>" <?php echo $selectCatText; ?>>
                                    <?php echo $category->name; ?>
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
});
</script>
<style>
	.actions{
		margin-top: 20px !important;
	}
</style>