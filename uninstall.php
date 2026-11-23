<?php
/**
 * Uninstall WP Site Hardening.
 * Deleting the plugin removes its settings. Deactivating it leaves
 * them in place, so re-activating restores the same login slug.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wp_site_hardening_settings' );
delete_option( 'wsh_flush_rewrite_rules' );
