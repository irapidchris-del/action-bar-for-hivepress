<?php
/**
 * Plugin Name: Action Bar for HivePress
 * Plugin URI: https://github.com/irapidchris-del/action-bar-for-hivepress
 * Description: Adds a customisable, app-style bottom navigation bar to HivePress websites, on any screen size you choose.
 * Version: 1.4.5
 * Author: ChrisB @ HivePress Community
 * Author URI: https://community.hivepress.io/u/chrisb/summary
 * Text Domain: action-bar-for-hivepress
 * Domain Path: /languages/
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: hivepress
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/irapidchris-del/action-bar-for-hivepress
 *
 * @package ActionBar
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

define( 'HPAB_VERSION', '1.4.5' );

// Set up updates from GitHub releases.
require_once __DIR__ . '/includes/updater.php';

ActionBar\Updater\bootstrap( __FILE__ );

/**
 * Registers the extension.
 *
 * Two registration forms exist and both have a failure mode. HivePress resolves
 * a bare directory path to `{dirname}/{dirname}.php`, so the string form fails
 * silently whenever the installed folder name differs from the main file name
 * (a source zip unpacks to `action-bar-for-hivepress-main`, for instance). The
 * array form always registers, but core's updater probe concatenates every
 * entry as a string, so an array entry makes it log a warning on each request
 * unless the probe has already been satisfied. So: the string form whenever the
 * folder name matches, and only for a renamed folder the array form, with the
 * probe run here first over the string entries so core's loop never reaches
 * the array. The filter is registered late so extensions that bundle the
 * updates package are already listed by the time that probe runs.
 *
 * @param array<string, mixed> $extensions Extension arguments.
 * @return array<string, mixed>
 */
function hpab_register_extension( $extensions ) {
	if ( file_exists( __DIR__ . '/' . basename( __DIR__ ) . '.php' ) ) {
		$extensions[] = __DIR__;

		return $extensions;
	}

	if ( ! isset( $extensions['updates'] ) ) {
		$path = '/vendor/hivepress/hivepress-updates';

		foreach ( $extensions as $dir ) {
			if ( is_string( $dir ) && file_exists( $dir . $path . '/hivepress-updates.php' ) ) {
				$extensions['updates'] = $dir . $path;

				break;
			}
		}
	}

	$extensions['action_bar_for_hivepress'] = [
		'name'    => 'Action Bar for HivePress',
		'version' => HPAB_VERSION,
		'path'    => __DIR__,
		'url'     => rtrim( plugin_dir_url( __FILE__ ), '/' ),
	];

	return $extensions;
}

add_filter( 'hivepress/v1/extensions', 'hpab_register_extension', 100 );

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

			// Dismissible, because an undismissable notice on every admin screen is admin hijacking even
			// when the thing it says is true. WordPress only hides it for the current page load, so the
			// warning returns until HivePress is actually activated.
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Action Bar for HivePress requires the HivePress plugin to be installed and activated.', 'action-bar-for-hivepress' ) . '</p></div>';
		}
	}
);

/**
 * The author's support page.
 *
 * One place, so the Plugins row and the View details popup can never drift apart.
 *
 * @return string
 */
function hpab_get_support_url() {
	return 'https://ko-fi.com/chrisbathivepresscommunity';
}

/**
 * Adds a quiet "Donate" link to this plugin's row meta.
 *
 * WordPress fires plugin_row_meta for EVERY plugin on the screen and joins the items with a pipe,
 * so without the basename test the link would appear on every row on the site.
 *
 * The markup is copied verbatim from the house spec in `releasing.md` rather than composed here:
 * every plugin's row has to look identical, and sessions have drifted before. The label is exactly
 * "Donate", which is also the wording WordPress uses in the details popup, and the icon is a
 * Dashicon rather than Font Awesome because Dashicons is the admin's own font and is always loaded
 * there. WordPress joins row-meta items with " | " itself, so this returns a bare anchor.
 *
 * @param array<string> $meta Row meta links.
 * @param string        $plugin_file Plugin file the row belongs to.
 * @return array<string>
 */
function hpab_add_row_meta( $meta, $plugin_file ) {
	if ( plugin_basename( __FILE__ ) === $plugin_file ) {
		$meta[] = '<a href="' . esc_url( hpab_get_support_url() ) . '" target="_blank" rel="noopener noreferrer">'
			. '<span class="dashicons dashicons-star-filled" style="font-size:14px;line-height:1.3;"></span> '
			. esc_html__( 'Donate', 'action-bar-for-hivepress' )
			. '</a>';
	}

	return $meta;
}

add_filter( 'plugin_row_meta', 'hpab_add_row_meta', 10, 2 );
