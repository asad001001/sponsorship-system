<?php

add_action('wp_ajax_preview_email_html_callback', 'preview_email_html_callback');
// Handler for logged-out users (if needed)
add_action('wp_ajax_nopriv_preview_email_html_callback', 'preview_email_html_callback');

 function preview_email_html_callback () {
	if (!current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'No permission']);
	}

	$name    = isset($_POST['user_name']) ? sanitize_text_field(wp_unslash($_POST['user_name'])) : '';
	$body    = isset($_POST['email_body']) ? wp_kses_post(wp_unslash($_POST['email_body'])) : '';

	$html = get_email_html($name, $body); // same function we created earlier

	wp_send_json_success(['html' => $html]);
}

function get_email_html($name,$messageBody){
	 
	 return '<style type="text/css">
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
		<h4 style="margin: 0;font-size: 20px; color: #332f2f;">Message Details</h4>
		</td>
		</tr>
		</tbody>
	</table>
		<table class="borders" border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" style="font-family: sans-serif;color: #555; border-bottom: 0; margin-bottom: 15px; padding:0;">

		<tbody>	
			<tr>
				<td><p style="font-size:16px; margin: 15px 0 0;">Hi '.$name.'</p></td>
			</tr>
			<tr>
				<td><p style="font-size:16px; margin: 0 0 20px;">You have received a new message regarding to your sponsorship.</p></td>
			</tr>
			<tr>
				<td><div style="font-size:14px; border-left: 2px solid #dcdbdb; background:#efefef; border-radius: 5px; padding: 15px;">'.$messageBody.'<div></td>
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
}