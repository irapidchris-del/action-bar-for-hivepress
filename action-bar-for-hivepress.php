<?php
/**
 * Plugin Name: Action Bar for HivePress
 * Plugin URI: https://github.com/irapidchris-del/action-bar-for-hivepress
 * Description: Adds a customisable, app-style bottom navigation bar to HivePress websites on mobile and tablet devices.
 * Version: 1.0.0
 * Author: ChrisB
 * Author URI: https://community.hivepress.io/u/chrisb
 * Text Domain: action-bar-for-hivepress
 * Domain Path: /languages/
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: hivepress
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: false
 *
 * @package ActionBar
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Register the extension directory.
add_filter(
	'hivepress/v1/extensions',
	function( $extensions ) {
		$extensions[] = __DIR__;

		return $extensions;
	}
);

// Add a settings link on the Plugins screen.
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function( $links ) {
		if ( class_exists( '\HivePress\Core' ) ) {
			array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=hp_settings&tab=action_bar' ) ) . '">' . esc_html__( 'Settings', 'action-bar-for-hivepress' ) . '</a>' );
		}

		return $links;
	}
);

// Show a notice if HivePress is not active.
add_action(
	'admin_notices',
	function() {
		if ( ! class_exists( '\HivePress\Core' ) && current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Action Bar for HivePress requires the HivePress plugin to be installed and activated.', 'action-bar-for-hivepress' ) . '</p></div>';
		}
	}
);
