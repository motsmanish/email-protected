<?php

/*
Plugin Name: Email Protected
Plugin URI: 
Description: A very simple way to quickly password protect your WordPress site with added user's email id. Please note: This plugin does not restrict access to uploaded files and images and does not work on WP Engine or with some caching setups.
Version: 999
Author: Manish Motwani
Text Domain: email-protected
Author URI: https://github.com/motsmanish/email-protected
License: GPLv2
*/

/**
 * @todo Use wp_hash_password() ?
 * @todo Remember me
 */

define( 'email_protected_SUBDIR', '/' . str_replace( basename( __FILE__ ), '', plugin_basename( __FILE__ ) ) );
define( 'email_protected_URL', plugins_url( email_protected_SUBDIR ) );
define( 'email_protected_DIR', plugin_dir_path( __FILE__ ) );

global $Email_Protected;
$Email_Protected = new Password_Protected();

class Password_Protected {

	var $version = '999';
	var $admin   = null;
	var $errors  = null;

	/**
	 * Constructor
	 */
	public function __construct() {

		$this->errors = new WP_Error();

		register_activation_hook( __FILE__, array( &$this, 'install' ) );

		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );

		add_filter( 'email_protected_is_active', array( $this, 'allow_ip_addresses' ) );

		add_action( 'init', array( $this, 'disable_caching' ), 1 );
		add_action( 'init', array( $this, 'maybe_process_logout' ), 1 );
		add_action( 'init', array( $this, 'maybe_process_login' ), 1 );
		add_action( 'wp', array( $this, 'disable_feeds' ) );
		add_action( 'template_redirect', array( $this, 'maybe_show_login' ), -1 );
		add_filter( 'pre_option_email_protected_status', array( $this, 'allow_feeds' ) );
		add_filter( 'pre_option_email_protected_status', array( $this, 'allow_administrators' ) );
		add_filter( 'pre_option_email_protected_status', array( $this, 'allow_users' ) );
		add_action( 'email_protected_login_messages', array( $this, 'login_messages' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'load_theme_stylesheet' ), 5 );

		add_shortcode( 'email_protected_logout_link', array( $this, 'logout_link_shortcode' ) );

		if ( is_admin() ) {
			include_once( dirname( __FILE__ ) . '/admin/admin.php' );
			$this->admin = new Email_Protected_Admin();
		}

	}

	/**
	 * I18n
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain( 'email-protected', false, basename( dirname( __FILE__ ) ) . '/languages' );

	}

	/**
	 * Disable Page Caching
	 */
	public function disable_caching() {

		if ( $this->is_active() && ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}	

	}

	/**
	 * Is Active?
	 *
	 * @return  boolean  Is password protection active?
	 */
	public function is_active() {

		global $wp_query;

		// Always allow access to robots.txt
		if ( isset( $wp_query ) && is_robots() ) {
			return false;
		}

		if ( (bool) get_option( 'email_protected_status' ) ) {
			$is_active = true;
		} else {
			$is_active = false;
		}

		$is_active = apply_filters( 'email_protected_is_active', $is_active );

		if ( isset( $_GET['email-protected'] ) ) {
			$is_active = true;
		}

		return $is_active;

	}

	/**
	 * Disable Feeds
	 *
	 * @todo  An option/filter to prevent disabling of feeds.
	 */
	public function disable_feeds() {

		if ( $this->is_active() ) {
			add_action( 'do_feed', array( $this, 'disable_feed' ), 1 );
			add_action( 'do_feed_rdf', array( $this, 'disable_feed' ), 1 );
			add_action( 'do_feed_rss', array( $this, 'disable_feed' ), 1 );
			add_action( 'do_feed_rss2', array( $this, 'disable_feed' ), 1 );
			add_action( 'do_feed_atom', array( $this, 'disable_feed' ), 1 );
		}

	}

	/**
	 * Disable Feed
	 *
	 * @todo  Make Translatable
	 */
	public function disable_feed() {

		wp_die( sprintf( __( 'Feeds are not available for this site. Please visit the <a href="%s">website</a>.', 'email-protected' ), get_bloginfo( 'url' ) ) );

	}

	/**
	 * Allow Feeds
	 *
	 * @param   boolean  $bool  Allow feeds.
	 * @return  boolean         True/false.
	 */
	public function allow_feeds( $bool ) {

		if ( is_feed() && (bool) get_option( 'email_protected_feeds' ) ) {
			return 0;
		}

		return $bool;

	}

	/**
	 * Allow Administrators
	 *
	 * @param   boolean  $bool  Allow administrators.
	 * @return  boolean         True/false.
	 */
	public function allow_administrators( $bool ) {

		if ( ! is_admin() && current_user_can( 'manage_options' ) && (bool) get_option( 'email_protected_administrators' ) ) {
			return 0;
		}

		return $bool;

	}

	/**
	 * Allow Users
	 *
	 * @param   boolean  $bool  Allow administrators.
	 * @return  boolean         True/false.
	 */
	public function allow_users( $bool ) {

		if ( ! is_admin() && is_user_logged_in() && (bool) get_option( 'email_protected_users' ) ) {
			return 0;
		}

		return $bool;

	}

	/**
	 * Allow IP Addresses
	 *
	 * If user has a valid email address, return false to disable password protection.
	 *
	 * @param   boolean  $bool  Allow IP addresses.
	 * @return  boolean         True/false.
	 */
	public function allow_ip_addresses( $bool ) {

		$ip_addresses = $this->get_allowed_ip_addresses();

		if ( in_array( $_SERVER['REMOTE_ADDR'], $ip_addresses ) ) {
			$bool = false;
		}

		return $bool;

	}

	/**
	 * Get Allowed IP Addresses
	 *
	 * @return  array  IP addresses.
	 */
	public function get_allowed_ip_addresses() {

		return explode( "\n", get_option( 'email_protected_allowed_ip_addresses' ) );

	}

	/**
	 * Encrypt Password
	 *
	 * @param  string  $password  Password.
	 * @return string             Encrypted password.
	 */
	public function encrypt_password( $password ) {

		return md5( $password );

	}

	/**
	 * Maybe Process Logout
	 */
	public function maybe_process_logout() {

		if ( isset( $_REQUEST['email-protected'] ) && $_REQUEST['email-protected'] == 'logout' ) {

			$this->logout();

			if ( isset( $_REQUEST['redirect_to'] ) ) {
				$redirect_to = esc_url_raw( $_REQUEST['redirect_to'], array( 'http', 'https' ) );
			} else {
				$redirect_to = home_url( '/' );
			}

			$this->safe_redirect( $redirect_to );
			exit();

		}

	}

	/**
	 * Maybe Process Login
	 */
	public function maybe_process_login() {

		if ( $this->is_active() && isset( $_REQUEST['email_protected_pwd'] ) ) {
			$email = $_REQUEST['email_protected_pwd'];
			global $wpdb;
			$query = $wpdb->prepare( "SELECT * FROM $wpdb->users where user_email = '%s' limit 1", $email );
			$response = $wpdb->get_results( $query );

			$response = empty( $response ) ? false : json_decode(json_encode($response), true);
			$email_allowed = (isset($response[0]['ID']) && $response[0]['user_email'] != get_option( 'admin_email' ));
			// If correct password...
			if ( ( $email_allowed ) ) {

				$this->set_auth_cookie();
				$redirect_to = isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '';
				$redirect_to = apply_filters( 'email_protected_login_redirect', $redirect_to );

				if ( ! empty( $redirect_to ) ) {
					$this->safe_redirect( $redirect_to );
					exit;
				}

			} else {

				// ... otherwise incorrect password
				$this->clear_auth_cookie();
				$this->errors->add( 'incorrect_password', __( 'Incorrect Password', 'email-protected' ) );

			}

		}

	}

	/**
	 * Is User Logged In?
	 *
	 * @return  boolean
	 */
	public function is_user_logged_in() {

		return $this->is_active() && $this->validate_auth_cookie();

	}

	/**
	 * Maybe Show Login
	 */
	public function maybe_show_login() {

		// Don't show login if not enabled
		if ( ! $this->is_active() ) {
			return;
		}

		// Logged in
		if ( $this->is_user_logged_in() ) {
			return;
		}

		// Show login form
		if ( isset( $_REQUEST['email-protected'] ) && 'login' == $_REQUEST['email-protected'] ) {

			$default_theme_file = locate_template( array( 'email-protected-login.php' ) );

			if ( empty( $default_theme_file ) ) {
				$default_theme_file = dirname( __FILE__ ) . '/theme/email-protected-login.php';
			}

			$theme_file = apply_filters( 'email_protected_theme_file', $default_theme_file );
			if ( ! file_exists( $theme_file ) ) {
				$theme_file = $default_theme_file;
			}

			load_template( $theme_file );
			exit();

		} else {

			$redirect_to = add_query_arg( 'email-protected', 'login', home_url() );

			// URL to redirect back to after login
			$redirect_to_url = apply_filters( 'email_protected_login_redirect_url', ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
			if ( ! empty( $redirect_to_url ) ) {
				$redirect_to = add_query_arg( 'redirect_to', urlencode( $redirect_to_url ), $redirect_to );
			}

			wp_redirect( $redirect_to );
			exit();

		}
	}

	/**
	 * Get Site ID
	 *
	 * @return  string  Site ID.
	 */
	public function get_site_id() {

		global $blog_id;
		return 'bid_' . apply_filters( 'email_protected_blog_id', $blog_id );

	}

	/**
	 * Login URL
	 *
	 * @return  string  Login URL.
	 */
	public function login_url() {

		return add_query_arg( 'email-protected', 'login', home_url( '/' ) );

	}

	/**
	 * Logout
	 */
	public function logout() {

		$this->clear_auth_cookie();
		do_action( 'email_protected_logout' );

	}

	/**
	 * Logout URL
	 *
	 * @param   string  $redirect_to  Optional. Redirect URL.
	 * @return  string                Logout URL.
	 */
	public function logout_url( $redirect_to = '' ) {

		$query = array(
			'email-protected' => 'logout',
			'redirect_to'        => esc_url_raw( $redirect_to )
		);

		if ( empty( $query['redirect_to'] ) ) {
			unset( $query['redirect_to'] );
		}

		return add_query_arg( $query, home_url() );

	}

	/**
	 * Logout Link
	 *
	 * @param   array   $args  Link args.
	 * @return  string         HTML link tag.
	 */
	public function logout_link( $args = null ) {

		// Only show if user is logged in
		if ( ! $this->is_user_logged_in() ) {
			return '';
		}

		$args = wp_parse_args( $args, array(
			'redirect_to' => '',
			'text'        => __( 'Logout', 'email-protected' )
		) );

		if ( empty( $args['text'] ) ) {
			$args['text'] = __( 'Logout', 'email-protected' );
		}

		return sprintf( '<a href="%s">%s</a>', esc_url( $this->logout_url( $args['redirect_to'] ) ), esc_html( $args['text'] ) );

	}

	/**
	 * Logout Link Shortcode
	 *
	 * @param   array   $args  Link args.
	 * @return  string         HTML link tag.
	 */
	public function logout_link_shortcode( $atts, $content = null ) {

		$atts = shortcode_atts( array(
			'redirect_to' => '',
			'text'        => $content
		), $atts, 'logout_link_shortcode' );

		return $this->logout_link( $atts );

	}

	/**
	 * Get Hashed Password
	 *
	 * @return  string  Hashed password.
	 */
	public function get_hashed_password() {

		return md5( get_option( 'email_protected_password' ) . wp_salt() );

	}

	/**
	 * Validate Auth Cookie
	 *
	 * @param   string   $cookie  Cookie string.
	 * @param   string   $scheme  Cookie scheme.
	 * @return  boolean           Validation successful?
	 */
	public function validate_auth_cookie( $cookie = '', $scheme = '' ) {

		if ( ! $cookie_elements = $this->parse_auth_cookie( $cookie, $scheme ) ) {
			do_action( 'email_protected_auth_cookie_malformed', $cookie, $scheme );
			return false;
		}

		extract( $cookie_elements, EXTR_OVERWRITE );

		$expired = $expiration;

		// Allow a grace period for POST and AJAX requests
		if ( defined( 'DOING_AJAX' ) || 'POST' == $_SERVER['REQUEST_METHOD'] ) {
			$expired += 3600;
		}

		// Quick check to see if an honest cookie has expired
		if ( $expired < current_time( 'timestamp' ) ) {
			do_action('email_protected_auth_cookie_expired', $cookie_elements);
			return false;
		}

		$key = md5( $this->get_site_id() . $this->get_hashed_password() . '|' . $expiration );
		$hash = hash_hmac( 'md5', $this->get_site_id() . '|' . $expiration, $key);

		if ( $hmac != $hash ) {
			do_action( 'email_protected_auth_cookie_bad_hash', $cookie_elements );
			return false;
		}

		if ( $expiration < current_time( 'timestamp' ) ) { // AJAX/POST grace period set above
			$GLOBALS['login_grace_period'] = 1;
		}

		return true;

	}

	/**
	 * Generate Auth Cookie
	 *
	 * @param   int     $expiration  Expiration time in seconds.
	 * @param   string  $scheme      Cookie scheme.
	 * @return  string               Cookie.
	 */
	public function generate_auth_cookie( $expiration, $scheme = 'auth' ) {

		$key = md5( $this->get_site_id() . $this->get_hashed_password() . '|' . $expiration );
		$hash = hash_hmac( 'md5', $this->get_site_id() . '|' . $expiration, $key );
		$cookie = $this->get_site_id() . '|' . $expiration . '|' . $hash;

		return $cookie;

	}

	/**
	 * Parse Auth Cookie
	 *
	 * @param   string  $cookie  Cookie string.
	 * @param   string  $scheme  Cookie scheme.
	 * @return  string           Cookie string.
	 */
	public function parse_auth_cookie( $cookie = '', $scheme = '' ) {

		if ( empty( $cookie ) ) {
			$cookie_name = $this->cookie_name();
	
			if ( empty( $_COOKIE[$cookie_name] ) ) {
				return false;
			}
			$cookie = $_COOKIE[$cookie_name];
		}

		$cookie_elements = explode( '|', $cookie );
		if ( count( $cookie_elements ) != 3 ) {
			return false;
		}

		list( $site_id, $expiration, $hmac ) = $cookie_elements;

		return compact( 'site_id', 'expiration', 'hmac', 'scheme' );

	}

	/**
	 * Set Auth Cookie
	 *
	 * @todo
	 *
	 * @param  boolean  $remember  Remember logged in.
	 * @param  string   $secure    Secure cookie.
	 */
	public function set_auth_cookie( $remember = false, $secure = '') {

		if ( $remember ) {
			$expiration = $expire = current_time( 'timestamp' ) + apply_filters( 'email_protected_auth_cookie_expiration', 1209600, $remember );
		} else {
			$expiration = current_time( 'timestamp' ) + apply_filters( 'email_protected_auth_cookie_expiration', 172800, $remember );
			$expire = 0;
		}

		if ( '' === $secure ) {
			$secure = is_ssl();
		}

		$secure_email_protected_cookie = apply_filters( 'email_protected_secure_email_protected_cookie', false, $secure );
		$email_protected_cookie = $this->generate_auth_cookie( $expiration, 'password_protected' );

		setcookie( $this->cookie_name(), $email_protected_cookie, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure_email_protected_cookie, true );
		if ( COOKIEPATH != SITECOOKIEPATH ) {
			setcookie( $this->cookie_name(), $email_protected_cookie, $expire, SITECOOKIEPATH, COOKIE_DOMAIN, $secure_email_protected_cookie, true );
		}

	}

	/**
	 * Clear Auth Cookie
	 */
	public function clear_auth_cookie() {

		setcookie( $this->cookie_name(), ' ', current_time( 'timestamp' ) - 31536000, COOKIEPATH, COOKIE_DOMAIN );
		setcookie( $this->cookie_name(), ' ', current_time( 'timestamp' ) - 31536000, SITECOOKIEPATH, COOKIE_DOMAIN );

	}

	/**
	 * Cookie Name
	 *
	 * @return  string  Cookie name.
	 */
	public function cookie_name() {

		return $this->get_site_id() . '_email_protected_auth';

	}

	/**
	 * Install
	 */
	public function install() {
		update_option( 'email_protected_version', $this->version );
		update_option( 'email_protected_administrators', 1 );
		update_option( 'email_protected_status', 1 );
	}

	/**
	 * Login Messages
	 * Outputs messages and errors in the login template.
	 */
	public function login_messages() {

		// Add message
		$message = apply_filters( 'email_protected_login_message', '' );
		if ( ! empty( $message ) ) {
			echo $message . "\n";
		}

		if ( $this->errors->get_error_code() ) {

			$errors = '';
			$messages = '';

			foreach ( $this->errors->get_error_codes() as $code ) {
				$severity = $this->errors->get_error_data( $code );
				foreach ( $this->errors->get_error_messages( $code ) as $error ) {
					if ( 'message' == $severity ) {
						$messages .= '	' . $error . "<br />\n";
					} else {
						$errors .= '	' . $error . "<br />\n";
					}
				}
			}

			if ( ! empty( $errors ) ) {
				echo '<div id="login_error">' . apply_filters( 'email_protected_login_errors', $errors ) . "</div>\n";
			}
			if ( ! empty( $messages ) ) {
				echo '<p class="message">' . apply_filters( 'email_protected_login_messages', $messages ) . "</p>\n";
			}

		}

	}

	/**
	 * Load Theme Stylesheet
	 *
	 * Check wether a 'email-protected-login.css' stylesheet exists in your theme
	 * and if so loads it.
	 * 
	 * Works with child themes.
	 *
	 * Possible to specify a different file in the theme folder via the
	 * 'email_protected_stylesheet_file' filter (allows for theme subfolders).
	 */
	public function load_theme_stylesheet() {

		$filename = apply_filters( 'email_protected_stylesheet_file', 'email-protected-login.css' );

		$located = locate_template( $filename );

		if ( ! empty( $located ) ) {

			$stylesheet_directory = trailingslashit( get_stylesheet_directory() );
			$template_directory = trailingslashit( get_template_directory() );
			
			if ( $stylesheet_directory == substr( $located, 0, strlen( $stylesheet_directory ) ) ) {
				wp_enqueue_style( 'email-protected-login', get_stylesheet_directory_uri() . '/' . $filename );
			} else if ( $template_directory == substr( $located, 0, strlen( $template_directory ) ) ) {
				wp_enqueue_style( 'email-protected-login', get_template_directory_uri() . '/' . $filename );
			}

		}

	}

	/**
	 * Safe Redirect
	 *
	 * Ensure the redirect is to the same site or pluggable list of allowed domains.
	 * If invalid will redirect to ...
	 * Based on the WordPress wp_safe_redirect() function.
	 */
	public function safe_redirect( $location, $status = 302 ) {

		$location = wp_sanitize_redirect( $location );
		$location = wp_validate_redirect( $location, home_url() );

		wp_redirect( $location, $status );

	}

	/**
	 * Is Plugin Supported?
	 *
	 * Check to see if there are any known reasons why this plugin may not work in
	 * the user's hosting environment.
	 *
	 * @return  boolean
	 */
	static function is_plugin_supported() {

		// WP Engine
		if ( class_exists( 'WPE_API', false ) ) {
			return new WP_Error( 'email_protected_SUPPORT', __( 'The Email Protected plugin does not work with WP Engine hosting. Please disable it.', 'email-protected' ) );
		}

		return true;

	}

}
