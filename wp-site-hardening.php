<?php
/**
 * Plugin Name: WP Site Hardening
 * Plugin URI: https://github.com/hklcf/wp-site-hardening
 * Description: Hardening for any WordPress site: disables XML-RPC, disables feeds, fully disables the REST API by default, and hides wp-login.php behind a randomly generated login slug. Switch the REST API to "authenticated only" to allow logged-in users and API-key requests (e.g. via WP API Key Authentication).
 * Version: 1.0.3
 * Author: HKLCF
 * Author URI: https://github.com/hklcf
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-site-hardening
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WSH_VERSION', '1.0.3' );
define( 'WSH_OPTION', 'wp_site_hardening_settings' );

/* ------------------------------------------------------------------ */
/* Settings                                                            */
/* ------------------------------------------------------------------ */

/**
 * Default settings.
 *
 * rest_mode:
 *   - 'fully_disabled' (default, as instructed): every REST request
 *     gets 403, including authenticated ones. The REST API is fully
 *     closed until the site owner switches it back on.
 *   - 'authenticated_only': anonymous requests to /wp-json/ get 401.
 *     Logged-in users and requests authenticated by another plugin
 *     keep working. This is the mode to use with WP API Key
 *     Authentication: that plugin runs determine_current_user at
 *     priority 20, this plugin checks at priority 30, so a valid API
 *     key is recognised as authenticated before this check runs.
 *     Pick this mode when you need API publishing, and switch back to
 *     fully_disabled when you do not.
 *   - 'off': leave the REST API as WordPress default.
 */
function wsh_default_settings() {
	return array(
		'disable_xmlrpc'    => 1,
		'disable_feeds'     => 1,
		'rest_mode'         => 'fully_disabled',
		'hide_login'        => 1,
		'login_slug'        => '', // Empty = generate a random slug on activation.
		'feed_status'       => 301, // 301 redirect to home, or 404.
	);
}

/**
 * Generate a random login slug, e.g. login-a1b2c3d4e5.
 * Never a fixed default: every install gets a different slug, so the
 * login URL is not guessable from the plugin name or README.
 */
function wsh_generate_login_slug() {
	if ( function_exists( 'random_bytes' ) ) {
		try {
			return 'login-' . bin2hex( random_bytes( 5 ) );
		} catch ( Exception $e ) {
			// Fall through to wp_generate_password() below.
		}
	}
	$rand = function_exists( 'wp_generate_password' ) ? wp_generate_password( 10, false, false ) : '';
	$rand = strtolower( preg_replace( '/[^a-z0-9]/', '', (string) $rand ) );
	if ( strlen( $rand ) < 6 ) {
		$rand = substr( md5( uniqid( 'wsh', true ) ), 0, 10 );
	}
	return 'login-' . $rand;
}

function wsh_get_settings() {
	$saved = get_option( WSH_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return array_merge( wsh_default_settings(), $saved );
}

/**
 * Validate a login slug. Lowercase letters, digits, hyphen, underscore.
 * Must be 3-100 chars, must not be a reserved WordPress slug.
 *
 * @return string Sanitised slug, or '' if invalid / reserved.
 */
function wsh_sanitize_login_slug( $slug ) {
	$slug = strtolower( trim( (string) $slug ) );
	$slug = trim( $slug, "/ \t\n\r\0\x0B" );
	// Strip anything that is not a-z, 0-9, hyphen or underscore.
	$slug = preg_replace( '/[^a-z0-9\-_]/', '', $slug );
	if ( ! is_string( $slug ) || '' === $slug ) {
		return '';
	}
	if ( strlen( $slug ) < 3 || strlen( $slug ) > 100 ) {
		return '';
	}
	$reserved = array(
		'wp-admin', 'wp-login', 'wp-login.php', 'login', 'admin',
		'wp-json', 'wp-content', 'wp-includes', 'feed', 'comments',
		'register', 'lostpassword', 'dashboard', 'xmlrpc', 'xmlrpc.php',
	);
	if ( in_array( $slug, $reserved, true ) ) {
		return '';
	}
	return $slug;
}

function wsh_get_login_slug() {
	$settings = wsh_get_settings();
	if ( empty( $settings['hide_login'] ) ) {
		return '';
	}
	// Emergency escape hatch: define this in wp-config.php to restore
	// the normal wp-login.php URL without touching the database.
	// define( 'WP_SITE_HARDENING_DISABLE_HIDE_LOGIN', true );
	if ( defined( 'WP_SITE_HARDENING_DISABLE_HIDE_LOGIN' ) && WP_SITE_HARDENING_DISABLE_HIDE_LOGIN ) {
		return '';
	}
	$slug = wsh_sanitize_login_slug( isset( $settings['login_slug'] ) ? $settings['login_slug'] : '' );
	if ( '' === $slug ) {
		// No slug stored yet (e.g. installed by copying files instead
		// of activating, or an old empty setting): generate one now,
		// save it once, and use it from then on. Never generate a
		// fresh slug on every request, or the login URL would move.
		$slug = wsh_generate_login_slug();
		$settings['login_slug'] = $slug;
		update_option( WSH_OPTION, $settings );
	}
	return $slug;
}

function wsh_hide_login_active() {
	return '' !== wsh_get_login_slug();
}

function wsh_login_url() {
	$slug = wsh_get_login_slug();
	if ( '' === $slug ) {
		return wp_login_url();
	}
	return home_url( '/' . $slug . '/' );
}

/* ------------------------------------------------------------------ */
/* Activation / deactivation                                           */
/* ------------------------------------------------------------------ */

function wsh_activate() {
	$defaults = wsh_default_settings();
	// Generate the random login slug once, at activation.
	$defaults['login_slug'] = wsh_generate_login_slug();
	if ( false === get_option( WSH_OPTION ) ) {
		add_option( WSH_OPTION, $defaults );
	} else {
		$saved = get_option( WSH_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		if ( empty( $saved['login_slug'] ) ) {
			$saved['login_slug'] = $defaults['login_slug'];
			update_option( WSH_OPTION, $saved );
		}
	}
	// Register the rewrite rule once, then flush so the custom login
	// slug works immediately.
	wsh_add_rewrite_rules();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wsh_activate' );

function wsh_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wsh_deactivate' );

/* ------------------------------------------------------------------ */
/* 1. Disable XML-RPC                                                  */
/* ------------------------------------------------------------------ */

function wsh_xmlrpc_is_disabled() {
	$settings = wsh_get_settings();
	return ! empty( $settings['disable_xmlrpc'] );
}

add_filter( 'xmlrpc_enabled', function ( $enabled ) {
	return wsh_xmlrpc_is_disabled() ? false : $enabled;
} );

add_filter( 'xmlrpc_methods', function ( $methods ) {
	return wsh_xmlrpc_is_disabled() ? array() : $methods;
} );

// Hard block: xmlrpc.php requested directly gets 403, even if another
// plugin re-enables the filter. Runs on init, which fires inside
// xmlrpc.php after wp-load.php has loaded plugins.
add_action( 'init', function () {
	if ( ! wsh_xmlrpc_is_disabled() ) {
		return;
	}
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	$uri = wp_unslash( $_SERVER['REQUEST_URI'] );
	if ( false !== strpos( $uri, 'xmlrpc.php' ) ) {
		status_header( 403 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo 'XML-RPC is disabled.';
		exit;
	}
}, 1 );

// Remove discovery links and the X-Pingback header.
add_action( 'init', function () {
	if ( ! wsh_xmlrpc_is_disabled() ) {
		return;
	}
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'pingback_link' );
} );

add_filter( 'wp_headers', function ( $headers ) {
	if ( wsh_xmlrpc_is_disabled() && isset( $headers['X-Pingback'] ) ) {
		unset( $headers['X-Pingback'] );
	}
	return $headers;
} );

// Pingbacks ride on XML-RPC; disabling the method above already blocks
// them, this stops WordPress advertising pingback support.
add_filter( 'pre_option_default_ping_status', function ( $value ) {
	return wsh_xmlrpc_is_disabled() ? 'closed' : $value;
} );
add_filter( 'pre_option_default_pingback_flag', function ( $value ) {
	return wsh_xmlrpc_is_disabled() ? '0' : $value;
} );

/* ------------------------------------------------------------------ */
/* 2. Disable feeds (/feed/ and /comments/feed/)                       */
/* ------------------------------------------------------------------ */

function wsh_feeds_disabled() {
	$settings = wsh_get_settings();
	return ! empty( $settings['disable_feeds'] );
}

function wsh_disable_feed() {
	if ( ! wsh_feeds_disabled() ) {
		return;
	}
	$settings = wsh_get_settings();
	$status   = ( isset( $settings['feed_status'] ) && 404 === (int) $settings['feed_status'] ) ? 404 : 301;
	if ( 404 === $status ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		// Load the theme 404 template if there is one.
		if ( function_exists( 'get_404_template' ) ) {
			$template = get_404_template();
			if ( $template ) {
				include $template;
			}
		}
		exit;
	}
	wp_redirect( home_url( '/' ), 301 );
	exit;
}

// Every feed type, including the comments feed.
add_action( 'do_feed', 'wsh_disable_feed', 1 );
add_action( 'do_feed_rdf', 'wsh_disable_feed', 1 );
add_action( 'do_feed_rss', 'wsh_disable_feed', 1 );
add_action( 'do_feed_rss2', 'wsh_disable_feed', 1 );
add_action( 'do_feed_atom', 'wsh_disable_feed', 1 );
add_action( 'do_feed_rss2_comments', 'wsh_disable_feed', 1 );
add_action( 'do_feed_atom_comments', 'wsh_disable_feed', 1 );

// Catch /?feed=rss2 and other feed query strings that do not go
// through do_feed.
add_action( 'template_redirect', function () {
	if ( wsh_feeds_disabled() && is_feed() ) {
		wsh_disable_feed();
	}
}, 1 );

// Remove feed links from <head>.
add_action( 'init', function () {
	if ( ! wsh_feeds_disabled() ) {
		return;
	}
	remove_action( 'wp_head', 'feed_links', 2 );
	remove_action( 'wp_head', 'feed_links_extra', 3 );
} );

/* ------------------------------------------------------------------ */
/* 3. Disable the REST API (/wp-json/) by default                     */
/*                                                                     */
/* Default (fully_disabled): every REST request gets 403, including    */
/* authenticated ones. Switch to authenticated_only when you need to   */
/* use the API, then switch back.                                      */
/*                                                                     */
/* Works with WP API Key Authentication: that plugin runs              */
/* determine_current_user at priority 20, this plugin checks at        */
/* priority 30, so in authenticated_only mode a valid API key counts   */
/* as authenticated and passes, while anonymous requests get 401.      */
/* ------------------------------------------------------------------ */

function wsh_rest_mode() {
	$settings = wsh_get_settings();
	$mode     = isset( $settings['rest_mode'] ) ? $settings['rest_mode'] : 'fully_disabled';
	if ( ! in_array( $mode, array( 'authenticated_only', 'fully_disabled', 'off' ), true ) ) {
		return 'fully_disabled';
	}
	return $mode;
}

add_filter( 'rest_authentication_errors', function ( $result ) {
	// Another plugin (or core) already rejected the request.
	if ( ! empty( $result ) ) {
		return $result;
	}
	$mode = wsh_rest_mode();
	if ( 'off' === $mode ) {
		return $result;
	}
	if ( 'fully_disabled' === $mode ) {
		return new WP_Error(
			'rest_disabled',
			'The REST API is disabled on this site.',
			array( 'status' => 403 )
		);
	}
	// authenticated_only.
	if ( is_user_logged_in() || get_current_user_id() ) {
		return $result;
	}
	return new WP_Error(
		'rest_not_logged_in',
		'The REST API is restricted to authenticated requests.',
		array( 'status' => 401 )
	);
}, 30 );

// Hide the REST API link from <head> and from HTTP headers, so
// /wp-json/ is not advertised to anonymous visitors.
add_action( 'init', function () {
	remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
	remove_action( 'template_redirect', 'rest_output_link_header', 11 );
} );

/* ------------------------------------------------------------------ */
/* 4. Custom admin login URL                                           */
/*                                                                     */
/* After activation the login page lives at a randomly generated
 * slug, e.g.:
 *   https://example.com/login-a1b2c3d4e5/
 * The slug is shown on Settings > WP Site Hardening. Bookmark it.
 * Direct requests to /wp-login.php and /wp-admin/ for logged-out
 * users are sent to the home page (404-style, no hint that a login
 * page exists). */
/*                                                                     */
/* Recovery if the slug is lost: add to wp-config.php                  */
/*   define( 'WP_SITE_HARDENING_DISABLE_HIDE_LOGIN', true );          */
/* then wp-login.php works normally again. Remove the line after       */
/* fixing the slug in Settings > WP Site Hardening.                   */
/* ------------------------------------------------------------------ */

function wsh_add_rewrite_rules() {
	$slug = wsh_get_login_slug();
	if ( '' === $slug ) {
		return;
	}
	add_rewrite_rule( '^' . preg_quote( $slug, '/' ) . '/?$', 'index.php?wsh_login=1', 'top' );
}
add_action( 'init', 'wsh_add_rewrite_rules' );

// Deferred rewrite flush. Flushing in the settings save handler does
// NOT work: init has already run for that request, so the rewrite
// rules in memory still reflect the OLD settings — turning hide-login
// off flushes the old slug rule back into the database, and turning
// it on flushes without the new rule. Instead, save sets a flag and
// the next init (which runs with the NEW settings) flushes. The same
// handler also cleans up a stale slug rule left in the database by
// 1.0.0/1.0.1 when hide-login is off.
add_action( 'init', function () {
	$flag = get_option( 'wsh_flush_rewrite_rules' );
	if ( $flag ) {
		delete_option( 'wsh_flush_rewrite_rules' );
		flush_rewrite_rules();
		return;
	}
	if ( wsh_hide_login_active() ) {
		return;
	}
	$rules = get_option( 'rewrite_rules' );
	if ( ! is_array( $rules ) ) {
		return;
	}
	foreach ( $rules as $regex => $query ) {
		if ( false !== strpos( (string) $query, 'wsh_login' ) ) {
			flush_rewrite_rules();
			return;
		}
	}
}, 20 );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'wsh_login';
	return $vars;
} );

// WordPress canonical redirect would otherwise bounce /<slug> back to
// the home page. Turn it off for the login slug only.
add_filter( 'redirect_canonical', function ( $redirect_url ) {
	if ( 1 === (int) get_query_var( 'wsh_login' ) ) {
		return false;
	}
	return $redirect_url;
} );

// Load wp-login.php when the custom slug is requested.
//
// wp-login.php is written to run as a top-level script: its functions
// (login_header(), login_footer()) read $action, $error and
// $interim_login as globals, and its main code uses $user_login and
// $error before setting them on a plain GET request. If it is included
// inside a closure, all of those variables become local to the closure
// instead, so login_header() sees a stale global $action (on
// a live site it showed up as "delete_attachment") and the login
// form shows "Undefined variable $user_login / $error" warnings with
// the warning HTML injected into the username field. Declaring the
// variables global here, and initialising the two that core only sets
// on POST, makes the included file behave exactly as if it had been
// loaded directly.
function wsh_load_login_page() {
	if ( 1 !== (int) get_query_var( 'wsh_login' ) ) {
		return;
	}
	global $action, $error, $interim_login, $user_login;
	$action        = 'login';
	$error         = '';
	$interim_login = false;
	$user_login    = '';
	// Mark this load so the direct-access block below lets it through.
	$GLOBALS['wsh_via_slug'] = true;
	require_once ABSPATH . 'wp-login.php';
	exit;
}
add_action( 'template_redirect', 'wsh_load_login_page' );

// Block direct wp-login.php access. This fires for a direct
// /wp-login.php request (SCRIPT_NAME is wp-login.php). It does NOT fire
// for a slug request, because that request goes through index.php and
// its init already ran before template_redirect loads wp-login.php —
// and even if login_init fires again, $GLOBALS['wsh_via_slug'] is set.
function wsh_block_direct_wp_login() {
	if ( ! wsh_hide_login_active() ) {
		return;
	}
	if ( ! empty( $GLOBALS['wsh_via_slug'] ) ) {
		return;
	}
	if ( empty( $_SERVER['SCRIPT_NAME'] ) || false === strpos( $_SERVER['SCRIPT_NAME'], 'wp-login.php' ) ) {
		return;
	}
	// Password-protected posts submit here. The form action is already
	// rewritten to the slug by the site_url filter below, but allow the
	// direct URL as a fallback so protected posts never break.
	if ( isset( $_GET['action'] ) && 'postpass' === $_GET['action'] ) {
		return;
	}
	// Do not reveal that a login page exists at another URL.
	wp_redirect( home_url( '/' ), 302 );
	exit;
}
add_action( 'login_init', 'wsh_block_direct_wp_login' );
add_action( 'init', function () {
	// init fires before login_init inside wp-login.php, so this is the
	// earliest reliable block for a direct request.
	if ( ! empty( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] ) {
		wsh_block_direct_wp_login();
	}
}, 1 );

// Rewrite every URL WordPress generates for the login page.
add_filter( 'login_url', function ( $login_url, $redirect, $force_reauth ) {
	if ( ! wsh_hide_login_active() ) {
		return $login_url;
	}
	$url = wsh_login_url();
	$args = array();
	if ( '' !== $redirect ) {
		$args['redirect_to'] = $redirect;
	}
	if ( $force_reauth ) {
		$args['reauth'] = '1';
	}
	if ( ! empty( $args ) ) {
		$url = add_query_arg( $args, $url );
	}
	return $url;
}, 10, 3 );

add_filter( 'logout_url', function ( $logout_url, $redirect ) {
	if ( ! wsh_hide_login_active() ) {
		return $logout_url;
	}
	$url = add_query_arg( 'action', 'logout', wsh_login_url() );
	// Keep the nonce WordPress generated in the original URL.
	$parts = wp_parse_url( $logout_url );
	if ( ! empty( $parts['query'] ) ) {
		parse_str( $parts['query'], $query );
		if ( ! empty( $query['_wpnonce'] ) ) {
			$url = add_query_arg( '_wpnonce', $query['_wpnonce'], $url );
		}
	}
	if ( '' !== $redirect ) {
		$url = add_query_arg( 'redirect_to', $redirect, $url );
	}
	return $url;
}, 10, 2 );

add_filter( 'lostpassword_url', function ( $lostpassword_url, $redirect = '' ) {
	if ( ! wsh_hide_login_active() ) {
		return $lostpassword_url;
	}
	$url = add_query_arg( 'action', 'lostpassword', wsh_login_url() );
	if ( '' !== $redirect ) {
		$url = add_query_arg( 'redirect_to', $redirect, $url );
	}
	return $url;
}, 10, 2 );

add_filter( 'register_url', function ( $register_url ) {
	if ( ! wsh_hide_login_active() ) {
		return $register_url;
	}
	return add_query_arg( 'action', 'register', wsh_login_url() );
} );

// Password-reset emails and other core code build wp-login.php URLs
// through site_url(). Rewrite those to the slug, preserving the query
// string (action=rp&key=... etc). home_url() does not run through this
// filter, so there is no loop.
add_filter( 'site_url', function ( $url, $path, $scheme ) {
	if ( ! wsh_hide_login_active() ) {
		return $url;
	}
	if ( ! is_string( $path ) || 0 !== strpos( $path, 'wp-login.php' ) ) {
		return $url;
	}
	$slug_url = wsh_login_url();
	$query    = '';
	if ( false !== strpos( $path, '?' ) ) {
		$query = substr( $path, strpos( $path, '?' ) + 1 );
	}
	// Also preserve a query string already on the built URL.
	$url_query = wp_parse_url( $url, PHP_URL_QUERY );
	if ( '' === $query && ! empty( $url_query ) ) {
		$query = $url_query;
	}
	if ( '' !== $query ) {
		$slug_url .= ( false === strpos( $slug_url, '?' ) ? '?' : '&' ) . $query;
	}
	return $slug_url;
}, 10, 3 );

/* ------------------------------------------------------------------ */
/* Settings page: Settings > WP Site Hardening                        */
/* ------------------------------------------------------------------ */

add_action( 'admin_menu', function () {
	add_options_page(
		'WP Site Hardening',
		'WP Site Hardening',
		'manage_options',
		'wp-site-hardening',
		'wsh_render_admin_page'
	);
} );

function wsh_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.' ) );
	}
	$settings = wsh_get_settings();
	$notice   = '';

	if ( ( isset( $_POST['wsh_save'] ) || isset( $_POST['wsh_regenerate_slug'] ) ) && check_admin_referer( 'wsh_settings_nonce' ) ) {
		$new = array(
			'disable_xmlrpc' => isset( $_POST['wsh_disable_xmlrpc'] ) ? 1 : 0,
			'disable_feeds'  => isset( $_POST['wsh_disable_feeds'] ) ? 1 : 0,
			'hide_login'     => isset( $_POST['wsh_hide_login'] ) ? 1 : 0,
			'rest_mode'      => 'fully_disabled',
			'feed_status'    => 301,
			'login_slug'     => $settings['login_slug'],
		);
		if ( isset( $_POST['wsh_rest_mode'] ) ) {
			$mode = sanitize_key( wp_unslash( $_POST['wsh_rest_mode'] ) );
			if ( in_array( $mode, array( 'authenticated_only', 'fully_disabled', 'off' ), true ) ) {
				$new['rest_mode'] = $mode;
			}
		}
		if ( isset( $_POST['wsh_feed_status'] ) && '404' === $_POST['wsh_feed_status'] ) {
			$new['feed_status'] = 404;
		}
		if ( isset( $_POST['wsh_regenerate_slug'] ) ) {
			$new['login_slug'] = wsh_generate_login_slug();
			$notice = 'New random login slug generated. Bookmark the new login URL below — the old slug stops working immediately.';
		} elseif ( isset( $_POST['wsh_login_slug'] ) ) {
			$posted = sanitize_text_field( wp_unslash( $_POST['wsh_login_slug'] ) );
			if ( '' === trim( $posted ) && ! empty( $_POST['wsh_hide_login'] ) ) {
				// Empty field = generate a fresh random slug, not an error.
				$new['login_slug'] = wsh_generate_login_slug();
				$notice = 'Login slug was empty, so a new random slug was generated. Bookmark it below.';
			} else {
				$slug = wsh_sanitize_login_slug( $posted );
				if ( '' === $slug && ! empty( $_POST['wsh_hide_login'] ) ) {
					$notice = 'Login slug was empty or reserved, so Hide Login was left on with the previous slug. Pick a slug with 3–100 lowercase letters, digits, hyphens or underscores.';
					$new['login_slug'] = $settings['login_slug'];
				} else {
					$new['login_slug'] = $slug;
				}
			}
		}
		update_option( WSH_OPTION, $new );
		$settings = wsh_get_settings();
		// Do NOT flush here: init already ran with the old settings,
		// so a flush now would save the old slug rule (see the
		// deferred flush on init). Set the flag instead.
		update_option( 'wsh_flush_rewrite_rules', 1 );
		if ( '' === $notice ) {
			$notice = 'Settings saved.';
		}
	}

	$slug      = wsh_get_login_slug();
	$login_url = '' !== $slug ? wsh_login_url() : wp_login_url();

	echo '<div class="wrap"><h1>WP Site Hardening</h1>';
	if ( '' !== $notice ) {
		echo '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>';
	}
	if ( '' !== $slug ) {
		echo '<div class="notice notice-success"><p><strong>Your login URL is now:</strong> <code>' . esc_html( $login_url ) . '</code> — bookmark it. The old <code>/wp-login.php</code> and <code>/wp-admin/</code> (when logged out) no longer lead to a login page.</p></div>';
	}
	echo '<p>Hardening for this site. Works with <a href="https://github.com/hklcf/wp-api-key-authentication" target="_blank" rel="noopener">WP API Key Authentication</a> (same author, download it from GitHub) — switch the REST API below to “authenticated only” to let a valid API key through. If you lose the custom login slug, add <code>define( \'WP_SITE_HARDENING_DISABLE_HIDE_LOGIN\', true );</code> to <code>wp-config.php</code> and the normal login URL works again.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'wsh_settings_nonce' );
	echo '<table class="form-table">';

	echo '<tr><th scope="row">XML-RPC</th><td><label><input type="checkbox" name="wsh_disable_xmlrpc" value="1"' . checked( $settings['disable_xmlrpc'], 1, false ) . ' /> Disable XML-RPC (<code>/xmlrpc.php</code>)</label>';
	echo '<p class="description">Blocks XML-RPC authentication and methods, removes the RSD / WLW manifest links and the X-Pingback header, and returns 403 for direct xmlrpc.php requests. Jetpack, old mobile apps and pingbacks will stop working.</p></td></tr>';

	echo '<tr><th scope="row">Feeds</th><td><label><input type="checkbox" name="wsh_disable_feeds" value="1"' . checked( $settings['disable_feeds'], 1, false ) . ' /> Disable feeds (<code>/feed/</code> and <code>/comments/feed/</code>)</label>';
	echo '<p class="description">Removes feed links from &lt;head&gt; and redirects feed requests to the home page.</p>';
	echo '<label><input type="radio" name="wsh_feed_status" value="301"' . checked( (int) $settings['feed_status'], 301, false ) . ' /> Redirect to home page (301)</label><br />';
	echo '<label><input type="radio" name="wsh_feed_status" value="404"' . checked( (int) $settings['feed_status'], 404, false ) . ' /> Return 404</label></td></tr>';

	echo '<tr><th scope="row">REST API (<code>/wp-json/</code>)</th><td>';
	echo '<label><input type="radio" name="wsh_rest_mode" value="fully_disabled"' . checked( $settings['rest_mode'], 'fully_disabled', false ) . ' /> Fully disable for everyone (default)</label><br />';
	echo '<label><input type="radio" name="wsh_rest_mode" value="authenticated_only"' . checked( $settings['rest_mode'], 'authenticated_only', false ) . ' /> Restrict to authenticated requests — use with an API key</label><br />';
	echo '<label><input type="radio" name="wsh_rest_mode" value="off"' . checked( $settings['rest_mode'], 'off', false ) . ' /> Off — leave the REST API as it is</label>';
	echo '<p class="description"><strong>Fully disable (default):</strong> every REST request gets 403, including authenticated ones. The API stays closed until you switch it back on here.<br /><strong>Authenticated only:</strong> anonymous visitors hitting <code>/wp-json/</code> get 401, logged-in users and API-key requests keep working. Use this mode with <a href="https://github.com/hklcf/wp-api-key-authentication" target="_blank" rel="noopener">WP API Key Authentication</a> (same author, download it from GitHub): a valid API key counts as authenticated, so API publishing works while anonymous access stays closed. Switch back to Fully disable when you no longer need the API.</p></td></tr>';

	echo '<tr><th scope="row">Custom login URL</th><td><label><input type="checkbox" name="wsh_hide_login" value="1"' . checked( $settings['hide_login'], 1, false ) . ' /> Hide wp-login.php behind a custom slug</label><br /><br />';
	echo '<label for="wsh_login_slug">Login slug</label> <input type="text" name="wsh_login_slug" id="wsh_login_slug" value="' . esc_attr( $settings['login_slug'] ) . '" class="regular-text code" /> ';
	submit_button( 'Generate new random slug', 'secondary', 'wsh_regenerate_slug', false );
	echo '<p class="description">The slug is randomly generated on activation (e.g. <code>login-a1b2c3d4e5</code>) — there is no fixed default, so it cannot be guessed from the README. Leave the field blank to generate a fresh random slug on save, or type your own. Login page becomes <code>' . esc_html( home_url( '/' ) ) . '<span id="wsh-slug-preview">' . esc_html( $settings['login_slug'] ) . '</span>/</code>. Use 3–100 lowercase letters, digits, hyphens or underscores. Do not use <code>login</code>, <code>admin</code>, <code>wp-admin</code> or <code>wp-json</code> on their own. Changing the slug logs nobody out, but the old slug stops working immediately.</p></td></tr>';

	echo '</table>';
	submit_button( 'Save Settings', 'primary', 'wsh_save' );
	echo '</form></div>';
}
