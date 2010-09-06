<?php
/*
Plugin Name: Multilang Contactform
Plugin URI: http://blog.digitaldonkey.de
Description: Translatable Contacform Plugin. Based Ryan Duff and Peter Westwood WP-ContactForm (http://blog.ftwr.co.uk/wordpress/) on WP Contact Form is a drop in form for users to contact you. It can be implemented on a page or a post. It currently works with WordPress 2.0+
Author: Thorsten Krug
Author URI: http://donkeymedia.eu
Version: 1.0
*/

load_plugin_textdomain('mlcf',$path = 'wp-content/plugins/multilang-contactform');

//Grab some default user info
$mlcf_auto_email = get_profile('user_email');
$mlcf_auto_ID = get_profile('ID');
$mlcf_auto_first_name = get_usermeta($mlcf_auto_ID, 'first_name');
$mlcf_auto_last_name = get_usermeta($mlcf_auto_ID, 'last_name');
$mlcf_auto_name = $mlcf_auto_first_name.' '.$mlcf_auto_last_name;
if ($_POST && empty($_POST['mlcf_email'])) {
	$_POST['mlcf_email'] = $mlcf_auto_email;
}
if ($_POST && empty($_POST['mlcf_name'])) {
	$_POST['mlcf_name'] = $mlcf_auto_name;
}

/* Declare strings that change depending on input. This also resets them so errors clear on resubmission. */
$mlcf_strings = array(
	'name' => '<div class="contactright"><input type="text" name="mlcf_name" id="mlcf_name" size="30" maxlength="50" value="' . $_POST['mlcf_name'] . '" /></div>',
	'email' => '<div class="contactright"><input type="text" name="mlcf_email" id="mlcf_email" size="30" maxlength="50" value="' . $_POST['mlcf_email'] . '" /></div>',
	'subject' => '<div class="contactright"><input type="text" name="mlcf_subject" id="mlcf_subject" size="30" maxlength="50" value="' . $_POST['mlcf_subject'] . '" /></div>',
	'www' => '<div class="contactright"><input type="text" name="mlcf_www" id="mlcf_www" size="30" maxlength="50" value="' . $_POST['mlcf_www'] . '" /></div>',
	'message' => '<div class="contactright"><textarea name="mlcf_message" id="mlcf_message" cols="35" rows="8" >' . $_POST['mlcf_message'] . '</textarea></div>',
	'error' => '');

function mlcf_is_malicious($input) {
	$is_malicious = false;
	$bad_inputs = array("\r", "\n", "mime-version", "content-type", "cc:", "to:");
	foreach($bad_inputs as $bad_input) {
		if(strpos(strtolower($input), strtolower($bad_input)) !== false) {
			$is_malicious = true; break;
		}
	}
	return $is_malicious;
}

/* This function checks for errors on input and changes $mlcf_strings if there are any errors. Shortcircuits if there has not been a submission */
function mlcf_check_input() {
	if(!(isset($_POST['mlcf_stage']))) {return false;} // Shortcircuit.

	$_POST['mlcf_name'] = htmlentities(stripslashes(trim($_POST['mlcf_name'])));
	$_POST['mlcf_email'] = htmlentities(stripslashes(trim($_POST['mlcf_email'])));
	$_POST['mlcf_subject'] = htmlentities(stripslashes(trim($_POST['mlcf_subject'])));
	$_POST['mlcf_website'] = htmlentities(stripslashes(trim($_POST['mlcf_website'])));
	$_POST['mlcf_message'] = htmlentities(stripslashes(trim($_POST['mlcf_message'])));

	global $mlcf_strings;
	$ok = true;

	if(empty($_POST['mlcf_name']))
	{
		$ok = false; $reason = 'empty';
		$mlcf_strings['name'] = '<div class="contactright"><input type="text" name="mlcf_name" id="mlcf_name" size="30" maxlength="50" value="' . $_POST['mlcf_name'] . '" class="contacterror" /><div class="contactalert">' . __(get_option('mlcf_field_required'), 'mlcf') . '</div></div>';
	}

  if(!is_email($_POST['mlcf_email']))
    {
	    $ok = false; $reason = 'empty';
	    $mlcf_strings['email'] = '<div class="contactright"><input type="text" name="mlcf_email" id="mlcf_email" size="30" maxlength="50" value="' . $_POST['mlcf_email'] . '" class="contacterror" /><div class="contactalert">' . __(get_option('mlcf_field_required'), 'mlcf') . '</div></div>';
	}
  if (! mlcf_check_email($_POST['mlcf_email'])){
	    $ok = false; $reason = 'wrong_mail';
	    $mlcf_strings['email'] = '<div class="contactright"><input type="text" name="mlcf_email" id="mlcf_email" size="30" maxlength="50" value="' . $_POST['mlcf_email'] . '" class="contacterror" /><div class="contactalert">' . __(get_option('mlcf_error_wrong_mail'), 'mlcf') . '</div></div>';
  }
  if(empty($_POST['mlcf_subject']))
    {
	    $ok = false; $reason = 'empty';
	    $mlcf_strings['subject'] = '<div class="contactright"><input type="text" name="mlcf_subject" id="mlcf_subject" size="30" maxlength="50" value="'. $_POST['mlcf_subject'] .'" class="contacterror" /><div class="contactalert">' . __(get_option('mlcf_field_required'), 'mlcf') . '</div></div>';
	} 
	if(empty($_POST['mlcf_message']))
    {
	    $ok = false; $reason = 'empty';
	    $mlcf_strings['message'] = '<div class="contactright"><textarea name="mlcf_message" id="mlcf_message" cols="35" rows="8" class="contacterror">' . $_POST['mlcf_message'] . '</textarea><div class="contactalert">' . __(get_option('mlcf_field_required'), 'mlcf') . '</div></div>';
	}

	if(mlcf_is_malicious($_POST['mlcf_name']) || mlcf_is_malicious($_POST['mlcf_email'])) {
		$ok = false; $reason = 'malicious';
	}

	if($ok == true)
	{
		return true;
	}
	else {
		if($reason == 'malicious') {
			$mlcf_strings['error'] = '<div class="contacterror">You can not use any of the following in the Name or Email fields: a linebreak, or the phrases "mime-version", "content-type", "cc:" or "to:".</div>';
		} elseif($reason == 'empty') {
			$mlcf_strings['error'] = '<div class="contacterror">' . stripslashes(get_option('mlcf_error_message')) . '</div>';
		}elseif($reason == 'wrong_mail') {
			$mlcf_strings['error'] = '<div class="contacterror">' . stripslashes(get_option('mlcf_error_message')) . '</div>';
		}
		return false;
	}
}
/* Check for a valid emal Adress */
function mlcf_check_email($email) {

  $nonascii      = "\x80-\xff"; # Non-ASCII-Chars are not allowed

  $nqtext        = "[^\\\\$nonascii\015\012\"]";
  $qchar         = "\\\\[^$nonascii]";

  $protocol      = '(?:mailto:)';

  $normuser      = '[a-zA-Z0-9][a-zA-Z0-9_.-]*';
  $quotedstring  = "\"(?:$nqtext|$qchar)+\"";
  $user_part     = "(?:$normuser|$quotedstring)";

  $dom_mainpart  = '[a-zA-Z0-9][a-zA-Z0-9._-]*\\.';
  $dom_subpart   = '(?:[a-zA-Z0-9][a-zA-Z0-9._-]*\\.)*';
  $dom_tldpart   = '[a-zA-Z]{2,5}';
  $domain_part   = "$dom_subpart$dom_mainpart$dom_tldpart";

  $regex         = "$protocol?$user_part\@$domain_part";
  
  return preg_match("/^$regex$/",$email);
}
/*Wrapper function which calls the form.*/
function mlcf_callback( $content ) {
	global $mlcf_strings;

	/* Run the input check. */		
	if(false === strpos($content, '<!--contact form-->')) {
		return $content;
	}
  
  // If the input check returns true (ie. there has been a submission & input is ok)
    if(mlcf_check_input()) 
    {
    
            $recipient = get_option('mlcf_email');
						$success_message = get_option('mlcf_success_message');
						$success_message = stripslashes($success_message);

            $name = utf8_decode(html_entity_decode($_POST['mlcf_name']));
            $email = utf8_decode(html_entity_decode($_POST['mlcf_email']));
            $subject = utf8_decode(html_entity_decode($_POST['mlcf_subject']))." ".get_option('mlcf_subject');
            $website = utf8_decode(html_entity_decode($_POST['mlcf_www']));
            $text = utf8_decode(html_entity_decode($_POST['mlcf_message']));

      			$headers  = "MIME-Version: 1.0\n";
						$headers .= "From: $name <$email>\n";
						$headers .= "Content-Type: text/plain; charset=\"" . get_settings('blog_charset') . "\"\n";
		        $header2  = "Reply-To:".$email."\n";
		        $header2 .= "From: webmail@donkeymedia.eu\r\n";
            
             $message = "e-Mail from ".get_bloginfo("name")." Contact Form: \n\n";
             $message .= wordwrap($text, 80, "\n") . "\n\n";
             $message .= "_____________________________________________\n\n";
             $message .= "Name: " . $name . "\n";
             $message .= "email: " . $email . "\n";
             $message .= "Subject: " . $subject . "\n";
             $message .= "Website: " . $website . "\n";
             $message .= "IP: " . mlcf_getip();
             $message .= "\n_____________________________________________\n";
            
            mail($recipient,utf8_decode($subject),$message,$header2);
            $results = '<div style="font-weight: bold;">' . $success_message . '</div>';
            echo $results;
    }
    else // Else show the form. If there are errors the strings will have updated during running the inputcheck.
    {
        $form = '<div class="contactform">
        ' . $mlcf_strings['error'] . '
        	<form action="' . get_permalink() . '" method="post">
        		<div class="contactleft">
        		  <label for="mlcf_name">' . __(get_option('mlcf_field_name'), 'mlcf') . '</label>
        		</div>' . $mlcf_strings['name']  . '
        		
        		<div class="contactleft">
        		  <label for="mlcf_email">' . __(get_option('mlcf_field_email'), 'mlcf') . '</label>
        		</div>' . $mlcf_strings['email'] . '
        		
        		<div class="contactleft">
        		  <label for="mlcf_subject">' . __(get_option('mlcf_field_subject'), 'mlcf') . '</label>
        		</div>' . $mlcf_strings['subject'] . '

            <div class="contactleft">
              <label for="mlcf_message">' . __(get_option('mlcf_field_message'), 'mlcf') . '</label>
            </div>' . $mlcf_strings['message'] . '        		

        		<div class="contactleft">
        		  <label for="mlcf_www">' . __(get_option('mlcf_field_www'), 'mlcf') . '</label>
        		</div>' . $mlcf_strings['www'] . '
            
	          <div class="contacrequired">' . __(get_option('mlcf_field_required'), 'mlcf') . '
	          </div>
	          <div class="contactright">
               <input class="contactsubmit" type="submit" name="Submit" value="' . __(get_option('mlcf_field_submit'), 'mlcf') . '" id="contactsubmit" />
               <input type="hidden" name="mlcf_stage" value="process" />
            </div>
        	</form>
        </div>';
        return str_replace('<!--contact form-->', $form, $content);
    }
}


/*Can't use WP's function here, so lets use our own*/
function mlcf_getip() {
	if (isset($_SERVER))
	{
 		if (isset($_SERVER["HTTP_X_FORWARDED_FOR"]))
 		{
  			$ip_addr = $_SERVER["HTTP_X_FORWARDED_FOR"];
 		}
 		elseif (isset($_SERVER["HTTP_CLIENT_IP"]))
 		{
  			$ip_addr = $_SERVER["HTTP_CLIENT_IP"];
 		}
 		else
 		{
 			$ip_addr = $_SERVER["REMOTE_ADDR"];
 		}
	}
	else
	{
 		if ( getenv( 'HTTP_X_FORWARDED_FOR' ) )
 		{
  			$ip_addr = getenv( 'HTTP_X_FORWARDED_FOR' );
 		}
 		elseif ( getenv( 'HTTP_CLIENT_IP' ) )
 		{
  			$ip_addr = getenv( 'HTTP_CLIENT_IP' );
 		}
 		else
 		{
  			$ip_addr = getenv( 'REMOTE_ADDR' );
 		}
	}
return $ip_addr;
}

/* OPTIONS PAGE */
function mlcf_add_options_page() {
		add_options_page(__('Contact Form Options', 'mlcf'), __('ML-Contact-Form', 'mlcf'), 'manage_options', 'mlcf_plugin_options' ,'mlcf_plugin_options');
	}
function mlcf_plugin_options() {

  if (!current_user_can('manage_options'))  {
    wp_die( __('You do not have sufficient permissions to access this page ???') );
  }
  require_once('ml-contactform-options.php');
}

/* UNINSTALL */
register_deactivation_hook( __FILE__, 'mclf_deactivate' );

function mclf_deactivate(){
  if(get_option( 'mlcf_delete_Options')){
    delete_option('mlcf_email');
    delete_option('mlcf_subject');
    delete_option('mlcf_success_message');
    delete_option('mlcf_error_message');
    delete_option('mlcf_error_wrong_mail');
    delete_option('mlcf_field_name');
    delete_option('mlcf_field_email');
    delete_option('mlcf_field_subject');
    delete_option('mlcf_field_www');
    delete_option('mlcf_field_message');
    delete_option('mlcf_field_required');
    delete_option('mlcf_field_submit');
   }
}

/* Action calls for all functions */
add_action('admin_menu', 'mlcf_add_options_page');
add_filter('the_content', 'mlcf_callback', 7);

?>