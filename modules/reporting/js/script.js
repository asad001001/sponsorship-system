jQuery(document).ready(function(){
	
	////////submit form		
	jQuery("#submit_reporting").click(function(){

		var date_from 		= jQuery("#date_from").val();
		var date_to 		= jQuery("#date_to").val();
		var term_category 	= jQuery("#term_category").val();
		var plan_type 	= jQuery("#plan_type").val();
		var animal 			= jQuery("#animal").val();
		var admin_ajax_url 	= jQuery("#admin-ajax-url").val();

		var formData = "action=get_animals&date_from="+date_from+"&date_to="+date_to+"&term_category="+term_category+"&plan_type="+plan_type+"&animal="+animal;
		jQuery.ajax({
			type: "GET", 
			url: admin_ajax_url,
			data : formData,
			success: function(resp){
				//console.log(`resp`, resp);
				jQuery("#tbl_div").html("");
				jQuery("#tbl_div").html(resp);
			}
		});							

	});
	
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
});;