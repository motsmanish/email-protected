<?php

global $wp_version, $Email_Protected, $error, $is_iphone;

/**
 * WP Shake JS
 */
if ( ! function_exists( 'wp_shake_js' ) ) {
	function wp_shake_js() {
		global $is_iphone;
		if ( $is_iphone ) {
			return;
		}
		?>
		<script type="text/javascript">
		addLoadEvent = function(func){if(typeof jQuery!="undefined")jQuery(document).ready(func);else if(typeof wpOnload!='function'){wpOnload=func;}else{var oldonload=wpOnload;wpOnload=function(){oldonload();func();}}};
		function s(id,pos){g(id).left=pos+'px';}
		function g(id){return document.getElementById(id).style;}
		function shake(id,a,d){c=a.shift();s(id,c);if(a.length>0){setTimeout(function(){shake(id,a,d);},d);}else{try{g(id).position='static';wp_attempt_focus();}catch(e){}}}
		addLoadEvent(function(){ var p=new Array(15,30,15,0,-15,-30,-15,0);p=p.concat(p.concat(p));var i=document.forms[0].id;g(i).position='relative';shake(i,p,20);});
		</script>
		<?php
	}
}

nocache_headers();
header( 'Content-Type: ' . get_bloginfo( 'html_type' ) . '; charset=' . get_bloginfo( 'charset' ) );

// Set a cookie now to see if they are supported by the browser.
setcookie( TEST_COOKIE, 'WP Cookie check', 0, COOKIEPATH, COOKIE_DOMAIN );
if ( SITECOOKIEPATH != COOKIEPATH ) {
	setcookie( TEST_COOKIE, 'WP Cookie check', 0, SITECOOKIEPATH, COOKIE_DOMAIN );
}

// If cookies are disabled we can't log in even with a valid password.
if ( isset( $_POST['testcookie'] ) && empty( $_COOKIE[ TEST_COOKIE ] ) ) {
	$Email_Protected->errors->add( 'test_cookie', __( "<strong>ERROR</strong>: Cookies are blocked or not supported by your browser. You must <a href='http://www.google.com/cookies.html'>enable cookies</a> to use WordPress.", 'email-protected' ) );
}

// Shake it!
$shake_error_codes = array( 'empty_password', 'incorrect_password' );
if ( $Email_Protected->errors->get_error_code() && in_array( $Email_Protected->errors->get_error_code(), $shake_error_codes ) ) {
	add_action( 'email_protected_login_head', 'wp_shake_js', 12 );
}

// Obey privacy setting
add_action( 'email_protected_login_head', 'noindex' );

?>
<?php get_header('email-protected'); ?>
<div id="login">
						<?php do_action('email_protected_login_messages'); ?>
						<?php do_action('email_protected_before_login_form'); ?>

						<form name="loginform" id="email_login_form" action="<?php echo esc_url($Email_Protected->login_url()); ?>" method="post">
								<p>
										<label for="email_protected_email_field">Your Email<br />
												<input type="text" name="email_protected_pwd" id="email_protected_email_field" class="input" value="" size="20" tabindex="20" /></label>
								</p>
								<p class="submit">
										<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e('Log In', 'email-protected'); ?>" tabindex="100" />
										<input type="hidden" name="testcookie" value="1" />
										<input type="hidden" name="email-protected" value="login" />
										<input type="hidden" name="redirect_to" value="<?php echo esc_attr($_REQUEST['redirect_to']); ?>" />
								</p>
						</form>

						<?php do_action('email_protected_after_login_form'); ?>

				</div>
<?php do_action('login_footer'); ?>
<div class="clear"></div>
</body>
</html>