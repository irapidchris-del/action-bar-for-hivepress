<?php
/**
 * Uninstall routine.
 *
 * Runs when the plugin is deleted from the Plugins screen, never on deactivation, so switching the
 * plugin off temporarily loses nothing at all.
 *
 * **Deleting the plugin keeps the owner's settings by default.** Someone who deletes the plugin by
 * accident, or removes it to install a clean copy, gets their bar back when they reinstall.
 * Destruction is opt-in, through the "Delete all data" checkbox in the Removing the Plugin section
 * of the settings page, and is never a surprise.
 *
 * There is no way to ask at delete time. The confirmation form in wp-admin/plugins.php:398-410 is
 * hard-coded with no do_action or apply_filters inside it, so a checkbox cannot be added to that
 * screen; the setting has to live on our own page. Worse, WordPress prints "(will also delete its
 * data)" on that screen whenever an uninstall.php exists at all (wp-admin/plugins.php:376-380),
 * whatever the file actually does, so the setting's own description tells the owner that the core
 * warning does not apply unless they ticked the box.
 *
 * The cached GitHub release lookup goes either way, because it is regenerable runtime junk rather
 * than anything the owner made. The updater schedules a background release refresh, unscheduled below.
 *
 * @package ActionBar
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Exit unless WordPress is genuinely uninstalling this plugin.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Read the owner's choice first, before anything is touched.
$hpab_delete_all = (bool) get_option( 'hp_action_bar_delete_data' );

/*
 * -------------------------------------------------------------------------------------------------
 * Always cleaned, whichever way the setting is set.
 * -------------------------------------------------------------------------------------------------
 */

// The updater's cached release lookup. A site transient lives under its own prefix, so neither the
// option sweep below nor a plain delete_option() would ever reach it.
delete_site_transient( 'action_bar_for_hivepress_release' );

/*
 * The updater's other two site transients and its background job, which used to be left behind.
 *
 * All three are regenerable runtime state belonging to the update check, not the owner's
 * configuration, so they go unconditionally alongside the release cache above. Core's daily sweep
 * clears expired site transients within about a day on single-site, which is why this read as
 * harmless; on multisite they live in wp_sitemeta and are only purged when something asks for
 * them, so on a network they simply stay. The scheduled refresh is worse than debris: it is a job
 * whose callback no longer exists.
 *
 * Unscheduled from both places it can be, because the refresh is queued through HivePress's
 * scheduler (Action Scheduler) when HivePress is present and through WP-Cron when it is not.
 */
delete_site_transient( 'action_bar_for_hivepress_release_reason' );
delete_site_transient( 'action_bar_for_hivepress_release_rate_limit' );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'action_bar_for_hivepress_release_refresh', [], 'hivepress' );
	as_unschedule_all_actions( 'action_bar_for_hivepress_release_refresh' );
}

wp_clear_scheduled_hook( 'action_bar_for_hivepress_release_refresh' );

// Any ordinary transient the plugin has ever set. Nothing writes one today, but a transient is
// stored as "_transient_{name}" plus a separate "_transient_timeout_{name}" row, so the prefix sweep
// used for options below cannot match them: it anchors on "hp_action_bar" at the start of the name.
// Leaving a timeout row behind with no value row is the classic orphan.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off cleanup of wildcard option names, which no WordPress API can enumerate.
$hpab_transients = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		'_transient_' . $wpdb->esc_like( 'hp_action_bar' ) . '%',
		'_transient_timeout_' . $wpdb->esc_like( 'hp_action_bar' ) . '%'
	)
);

foreach ( (array) $hpab_transients as $hpab_transient_name ) {
	delete_option( $hpab_transient_name );
}

/*
 * -------------------------------------------------------------------------------------------------
 * Everything below happens only when the owner asked for it.
 * -------------------------------------------------------------------------------------------------
 */

if ( $hpab_delete_all ) {

	// Delete the options. They are matched on the plugin's prefix because several are dynamic: one per
	// colour, one per bar of repeater items. This runs once, while the plugin is being deleted, so
	// there is nothing worth caching.
	//
	// The "delete all data" option itself is excluded here and removed at the very end. If this run
	// fails part-way through, the flag is still set, so a second attempt finishes the job. Sweeping it
	// away first would silently flip the site back to "retain" with half the settings already gone.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off cleanup of wildcard option names, which no WordPress API can enumerate.
	$hpab_option_names = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name != %s",
			$wpdb->esc_like( 'hp_action_bar' ) . '%',
			'hp_action_bar_delete_data'
		)
	);

	foreach ( (array) $hpab_option_names as $hpab_option_name ) {

		// Use the options API so persistent object caches are invalidated too.
		delete_option( $hpab_option_name );
	}

	// Last, and only once everything above has succeeded.
	delete_option( 'hp_action_bar_delete_data' );
}
