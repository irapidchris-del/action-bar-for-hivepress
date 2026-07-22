<?php
/**
 * Uninstalls the plugin.
 *
 * @package ActionBar
 */

// Exit if accessed directly.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

/**
 * Deletes the plugin options from the current site.
 *
 * @return void
 */
function action_bar_for_hivepress_delete_options() {
	global $wpdb;

	$option_names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'hp\\_action\\_bar\\_%'" );

	foreach ( $option_names as $option_name ) {

		// Use the options API so persistent object caches are invalidated too.
		delete_option( $option_name );
	}
}

if ( is_multisite() ) {
	$site_ids = get_sites(
		[
			'fields' => 'ids',
			'number' => 0,
		]
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );

		action_bar_for_hivepress_delete_options();

		restore_current_blog();
	}
} else {
	action_bar_for_hivepress_delete_options();
}
