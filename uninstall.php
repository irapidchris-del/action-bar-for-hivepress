<?php
/**
 * Uninstalls the plugin.
 *
 * @package ActionBar
 */

// Exit if accessed directly.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Delete plugin options.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'hp\\_action\\_bar\\_%'" );
