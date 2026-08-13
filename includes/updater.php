<?php
/**
 * GitHub release updater.
 *
 * The plugin is distributed via GitHub releases rather than wordpress.org, so
 * update checks go through the native `update_plugins_{$hostname}` API
 * introduced in WordPress 5.8, keyed off the Update URI header in the main
 * plugin file. The update package is the release asset named `*.zip`, which
 * must contain a single `action-bar-for-hivepress` directory.
 *
 * @package ActionBar
 */

namespace ActionBar\Updater;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

const UPDATE_REPO = 'irapidchris-del/action-bar-for-hivepress';

const UPDATE_SLUG = 'action-bar-for-hivepress';

const UPDATE_CACHE_KEY = 'action_bar_for_hivepress_release';

/**
 * Stores and returns the main plugin file path.
 *
 * @param string|null $set Plugin file path.
 * @return string
 */
function plugin_file( $set = null ) {
	static $file = '';

	if ( null !== $set ) {
		$file = $set;
	}

	return $file;
}

/**
 * Registers the update hooks.
 *
 * @param string $file Main plugin file path.
 * @return void
 */
function bootstrap( $file ) {
	plugin_file( $file );

	$basename = plugin_basename( $file );

	add_filter( 'update_plugins_github.com', __NAMESPACE__ . '\\check_for_update', 10, 3 );
	add_filter( 'plugins_api', __NAMESPACE__ . '\\get_plugin_information', 10, 3 );

	add_filter( 'plugin_action_links_' . $basename, __NAMESPACE__ . '\\add_update_check_link' );
	add_filter( 'network_admin_plugin_action_links_' . $basename, __NAMESPACE__ . '\\add_update_check_link' );

	add_action( 'admin_init', __NAMESPACE__ . '\\handle_update_check' );
	add_action( 'admin_notices', __NAMESPACE__ . '\\show_update_check_notice' );
	add_action( 'network_admin_notices', __NAMESPACE__ . '\\show_update_check_notice' );

	add_filter( 'upgrader_source_selection', __NAMESPACE__ . '\\fix_update_directory', 10, 4 );
}

/**
 * Gets the installed plugin version.
 *
 * @return string
 */
function get_version() {
	static $version = null;

	if ( null === $version ) {
		$data = get_file_data( plugin_file(), [ 'Version' => 'Version' ] );

		$version = $data['Version'];
	}

	return $version;
}

/**
 * Gets the latest GitHub release details, cached for 6 hours.
 *
 * @param bool $force Bypass the cache.
 * @return array<string, string>|null
 */
function get_latest_release( $force = false ) {
	$release = $force ? false : get_site_transient( UPDATE_CACHE_KEY );

	if ( ! is_array( $release ) ) {
		$release = fetch_latest_release();

		// Failures are cached briefly so the API is not queried repeatedly. A "no releases yet" answer is
		// a real answer rather than a failure, so it is cached for the full period like a success.
		set_site_transient( UPDATE_CACHE_KEY, $release, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	}

	// The no-releases sentinel is an answer, but it is not a release.
	if ( isset( $release['none'] ) ) {
		return null;
	}

	return $release ? $release : null;
}

/**
 * Checks whether the last lookup found a reachable repository with no published releases.
 *
 * @return bool
 */
function has_no_releases() {
	$release = get_site_transient( UPDATE_CACHE_KEY );

	return is_array( $release ) && isset( $release['none'] );
}

/**
 * Fetches the latest release details from the GitHub API.
 *
 * Draft and pre-release entries are excluded by the endpoint itself, so
 * publishing a pre-release never triggers an update notice.
 *
 * @return array<string, string>
 */
function fetch_latest_release() {
	$response = wp_remote_get(
		'https://api.github.com/repos/' . UPDATE_REPO . '/releases/latest',
		[
			'timeout'    => 10,
			'headers'    => [ 'Accept' => 'application/vnd.github+json' ],

			// Without an explicit user agent WordPress sends "WordPress/{version}; {site url}", which
			// hands GitHub the site's address and its exact WordPress version on every check. GitHub
			// only requires the header to identify something, so the slug and version tell it nothing.
			'user-agent' => UPDATE_SLUG . '/' . get_version(),
		]
	);

	if ( is_wp_error( $response ) ) {
		return [];
	}

	// A 404 is an answer, not a failure to get one: it is what every repository returns between
	// creation and its first release, so it must not be reported as a connectivity problem.
	if ( 404 === (int) wp_remote_retrieve_response_code( $response ) ) {
		return [ 'none' => '1' ];
	}

	if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return [];
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $data ) ) {
		return [];
	}

	// The version is read from the release tag, with or without a "v" prefix.
	$version = ltrim( (string) ( isset( $data['tag_name'] ) ? $data['tag_name'] : '' ), 'vV' );

	if ( ! $version ) {
		return [];
	}

	// The update package is the first release asset named `*.zip`.
	$package = '';

	foreach ( (array) ( isset( $data['assets'] ) ? $data['assets'] : [] ) as $asset ) {
		$name = strtolower( (string) ( isset( $asset['name'] ) ? $asset['name'] : '' ) );

		if ( '.zip' === substr( $name, -4 ) && ! empty( $asset['browser_download_url'] ) ) {
			$package = (string) $asset['browser_download_url'];

			break;
		}
	}

	if ( ! $package ) {
		return [];
	}

	return [
		'version'   => $version,
		'package'   => $package,
		'url'       => (string) ( isset( $data['html_url'] ) ? $data['html_url'] : 'https://github.com/' . UPDATE_REPO ),
		'notes'     => (string) ( isset( $data['body'] ) ? $data['body'] : '' ),
		'published' => (string) ( isset( $data['published_at'] ) ? $data['published_at'] : '' ),
	];
}

/**
 * Provides the update details to the WordPress update system.
 *
 * WordPress matches the plugin to this filter via the Update URI header
 * hostname and compares the versions itself, filing the result under either
 * the available updates or the up-to-date list.
 *
 * @param array<string, mixed>|false $update Update data.
 * @param array<string, string>      $plugin_data Plugin headers.
 * @param string                     $plugin_file Plugin basename.
 * @return array<string, mixed>|false
 */
function check_for_update( $update, $plugin_data, $plugin_file ) {
	if ( plugin_basename( plugin_file() ) !== $plugin_file ) {
		return $update;
	}

	$release = get_latest_release();

	if ( ! $release ) {
		return $update;
	}

	return [
		'id'      => 'https://github.com/' . UPDATE_REPO,
		'slug'    => UPDATE_SLUG,
		'plugin'  => $plugin_file,
		'version' => $release['version'],
		'url'     => $release['url'],
		'package' => $release['package'],
	];
}

/**
 * Provides the plugin details for the update information popup.
 *
 * Without this the "View version x.x.x details" link on the Plugins screen
 * would open an empty modal, since the plugin is not on wordpress.org.
 *
 * @param object|array|false $result Result object.
 * @param string             $action API action.
 * @param object             $args API arguments.
 * @return object|array|false
 */
function get_plugin_information( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || ! is_object( $args ) || UPDATE_SLUG !== ( isset( $args->slug ) ? $args->slug : '' ) ) {
		return $result;
	}

	$release = get_latest_release();

	if ( ! $release ) {
		return $result;
	}

	$plugin_data = get_file_data(
		plugin_file(),
		[
			'Name'        => 'Plugin Name',
			'Description' => 'Description',
			'Author'      => 'Author',
			'AuthorURI'   => 'Author URI',
			'RequiresWP'  => 'Requires at least',
			'RequiresPHP' => 'Requires PHP',
		]
	);

	return (object) [
		'name'          => $plugin_data['Name'],
		'slug'          => UPDATE_SLUG,
		'version'       => $release['version'],
		'author'        => '<a href="' . esc_url( $plugin_data['AuthorURI'] ) . '">' . esc_html( $plugin_data['Author'] ) . '</a>',
		'homepage'      => 'https://github.com/' . UPDATE_REPO,
		'requires'      => $plugin_data['RequiresWP'],
		'requires_php'  => $plugin_data['RequiresPHP'],
		'last_updated'  => $release['published'],
		'download_link' => $release['package'],

		// WordPress renders this by itself as "Donate to this plugin" in the View details popup, so the
		// third placement of the support link costs one line.
		'donate_link'   => function_exists( 'hpab_get_support_url' ) ? hpab_get_support_url() : '',
		'sections'      => [
			'description' => wpautop( esc_html( $plugin_data['Description'] ) ),
			'changelog'   => $release['notes'] ? wpautop( esc_html( $release['notes'] ) ) : '<p>' . esc_html__( 'See the GitHub releases page for the changelog.', 'action-bar-for-hivepress' ) . '</p>',
		],
	];
}

/**
 * Adds the manual update check link to the plugin row.
 *
 * @param array<string> $links Plugin action links.
 * @return array<string>
 */
function add_update_check_link( $links ) {
	if ( current_user_can( 'update_plugins' ) ) {
		$links[] = '<a href="' . esc_url( wp_nonce_url( self_admin_url( 'plugins.php?action_bar_check_updates=1' ), 'action_bar_check_updates' ) ) . '">' . esc_html__( 'Check for updates', 'action-bar-for-hivepress' ) . '</a>';
	}

	return $links;
}

/**
 * Handles the manual update check.
 *
 * Refreshes the cached release, re-runs the update check and redirects back
 * to the Plugins screen with the result.
 *
 * @return void
 */
function handle_update_check() {
	if ( ! isset( $_GET['action_bar_check_updates'] ) || ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	check_admin_referer( 'action_bar_check_updates' );

	$release = get_latest_release( true );

	wp_clean_plugins_cache();
	wp_update_plugins();

	$status = 'none';

	if ( ! $release ) {

		// A reachable repository with nothing published yet is not an error, and saying so stops a new
		// owner hunting a network fault that does not exist.
		$status = has_no_releases() ? 'norelease' : 'error';
	} elseif ( version_compare( $release['version'], get_version(), '>' ) ) {
		$status = 'available';
	}

	wp_safe_redirect( add_query_arg( 'action_bar_checked', $status, self_admin_url( 'plugins.php' ) ) );

	exit;
}

/**
 * Shows the manual update check result.
 *
 * @return void
 */
function show_update_check_notice() {

	// No nonce is checked here because this only reads the result flag that handle_update_check() put in
	// its own redirect after verifying a nonce. The value selects one of four fixed messages and is
	// never used to act, and the capability check still applies.
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['action_bar_checked'] ) || ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	$status = sanitize_key( wp_unslash( $_GET['action_bar_checked'] ) );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( 'available' === $status ) {
		$release = get_latest_release();

		/* translators: %s: version number of the new release. */
		$message = sprintf( __( 'A new version of Action Bar for HivePress (%s) is available.', 'action-bar-for-hivepress' ), $release ? $release['version'] : '' );
		$class   = 'notice-success';
	} elseif ( 'none' === $status ) {
		$message = __( 'Action Bar for HivePress is up to date.', 'action-bar-for-hivepress' );
		$class   = 'notice-success';
	} elseif ( 'norelease' === $status ) {
		$message = __( 'Action Bar for HivePress has no releases published yet, so there is nothing to update to. This is normal for a new install and nothing is wrong.', 'action-bar-for-hivepress' );
		$class   = 'notice-info';
	} elseif ( 'error' === $status ) {
		$message = __( 'Could not reach GitHub to check for updates. Please try again later.', 'action-bar-for-hivepress' );
		$class   = 'notice-error';
	} else {
		return;
	}

	echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
}

/**
 * Keeps updates installing into the current plugin directory.
 *
 * The extracted release folder is renamed to match the directory the plugin
 * is installed in, so an update can never end up in a differently named
 * folder even if the release zip is packaged unexpectedly.
 *
 * @param string               $source Extracted update source.
 * @param string               $remote_source Remote source directory.
 * @param object               $upgrader Upgrader instance.
 * @param array<string, mixed> $hook_extra Extra hook arguments.
 * @return string|\WP_Error
 */
function fix_update_directory( $source, $remote_source, $upgrader, $hook_extra = [] ) {
	global $wp_filesystem;

	if ( plugin_basename( plugin_file() ) !== ( isset( $hook_extra['plugin'] ) ? $hook_extra['plugin'] : '' ) || ! $wp_filesystem ) {
		return $source;
	}

	$directory = dirname( plugin_basename( plugin_file() ) );

	if ( '.' === $directory ) {
		return $source;
	}

	$target = trailingslashit( $remote_source ) . $directory . '/';

	if ( trailingslashit( $source ) === $target ) {
		return $source;
	}

	if ( ! $wp_filesystem->move( untrailingslashit( $source ), untrailingslashit( $target ) ) ) {
		return new \WP_Error( 'action_bar_rename_failed', __( 'Could not rename the update directory.', 'action-bar-for-hivepress' ) );
	}

	return $target;
}
