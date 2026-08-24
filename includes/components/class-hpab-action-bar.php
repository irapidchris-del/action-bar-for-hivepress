<?php
/**
 * Action bar component.
 *
 * @package HivePress\Components
 */

namespace HivePress\Components;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Renders the mobile action bar.
 *
 * The class and file names are both prefixed because HivePress globs
 * `includes/components/*.php` across every extension and loads one class per
 * file name, so an unprefixed name silently loses to any other plugin shipping
 * the same one.
 */
final class Hpab_Action_Bar extends Component {

	/**
	 * Resolved action bar items.
	 *
	 * @var array<int, array<string, mixed>>|null
	 */
	protected $items;

	/**
	 * Class constructor.
	 *
	 * @param array<string, mixed> $args Component arguments.
	 */
	public function __construct( $args = [] ) {

		if ( is_admin() ) {

			// Migrate item settings.
			add_action( 'admin_init', [ $this, 'maybe_migrate_items' ], 1 );

			// Enqueue backend assets.
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_backend_assets' ] );
		} else {

			// Enqueue frontend assets.
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );

			// Alter body classes.
			add_filter( 'body_class', [ $this, 'alter_body_classes' ] );

			// Render action bar.
			add_action( 'wp_footer', [ $this, 'render_action_bar' ] );
		}

		parent::__construct( $args );
	}

	/**
	 * Gets the default item settings.
	 *
	 * @param string $bar Bar name.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_item_defaults( $bar ) {
		/*
		 * These labels are plain __() rather than esc_html__() on purpose. The array is handed to the
		 * repeater field as its `default`, so the first save writes these exact strings into the option,
		 * and render_action_bar() escapes the stored label again on output. Escaping here would store the
		 * escaped form and then escape it a second time, so a translation containing an apostrophe or an
		 * ampersand would show its entity on screen for good. Escape late, once, at output.
		 */
		$defaults = [
			'user'   => [
				[
					'link'  => 'home',
					'icon'  => 'home',
					'label' => __( 'Home', 'action-bar-for-hivepress' ),
				],

				[
					'link'  => 'listings',
					'icon'  => 'search',
					'label' => __( 'Browse', 'action-bar-for-hivepress' ),
				],

				[
					'link'  => 'account',
					'icon'  => 'user',
					'label' => __( 'Account', 'action-bar-for-hivepress' ),
					'badge' => 'notices',
				],
			],

			'vendor' => [
				[
					'link'  => 'home',
					'icon'  => 'home',
					'label' => __( 'Home', 'action-bar-for-hivepress' ),
				],

				[
					'link'  => 'listing_submit',
					'icon'  => 'plus',
					'label' => __( 'Add listing', 'action-bar-for-hivepress' ),
					'style' => 'prominent',
				],

				[
					'link'  => 'account',
					'icon'  => 'user',
					'label' => __( 'Account', 'action-bar-for-hivepress' ),
					'badge' => 'notices',
				],
			],
		];

		return hp\get_array_value( $defaults, $bar, [] );
	}

	/**
	 * Gets the item link options.
	 *
	 * @return array<string, string>
	 */
	public function get_link_options() {
		$options = [
			'home'           => esc_html__( 'Homepage', 'action-bar-for-hivepress' ),
			'listings'       => esc_html__( 'Listings', 'action-bar-for-hivepress' ),
			'listing_submit' => esc_html__( 'Add listing', 'action-bar-for-hivepress' ),
			'vendors'        => esc_html__( 'Vendors', 'action-bar-for-hivepress' ),
			'account'        => esc_html__( 'Account or login', 'action-bar-for-hivepress' ),
			'messages'       => esc_html__( 'Messages', 'action-bar-for-hivepress' ),
			'favorites'      => esc_html__( 'Favourites', 'action-bar-for-hivepress' ),
			'custom'         => esc_html__( 'Custom URL', 'action-bar-for-hivepress' ),
		];

		// Add the WooCommerce options.
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$options['wc_orders'] = esc_html__( 'Placed orders', 'action-bar-for-hivepress' );
			$options['wc_cart']   = esc_html__( 'Cart', 'action-bar-for-hivepress' );

			/*
			 * Then the rest of the account area. Subscriptions, downloads, saved cards and anything
			 * another plugin adds are all account endpoints, and an owner building a bar had no way
			 * to point at them - only Orders and Cart were offered.
			 *
			 * Read from the registered endpoints rather than from wc_get_account_menu_items(),
			 * because that list is filtered and plugins commonly add their item only for somebody
			 * who has something to see: asked in wp-admin on behalf of an administrator, it leaves
			 * those items out entirely.
			 */
			foreach ( $this->get_wc_account_endpoints() as $endpoint => $label ) {
				$options[ 'wcep_' . $endpoint ] = $label;
			}
		}

		return $options;
	}

	/**
	 * Gets the badge counter options.
	 *
	 * HivePress keeps two unread counters: the messages-only count in the
	 * `message_unread_count` request context, and the combined count in
	 * `notice_count`, which Messages, Bookings and Marketplace all add into.
	 * The combined count already includes unread messages.
	 *
	 * The third counter this returns is not one of those: unread notifications come from the
	 * separate Notifications for HivePress plugin. So this returns up to three options, not two,
	 * and get_badge_sources() below is the full list.
	 *
	 * @return array<string, string>
	 */
	public function get_badge_options() {
		$options = $this->get_badge_sources();

		// Only offer the message counter when Messages is active, because nothing else sets that context
		// and the option would otherwise be a choice that silently shows nothing.
		if ( ! hivepress()->get_version( 'messages' ) ) {
			unset( $options['messages'] );
		}

		// Same reasoning for unread notifications: without the plugin that counts them the option
		// would sit on the screen showing nothing for ever.
		if ( ! $this->get_notification_component() ) {
			unset( $options['notifications'] );
		}

		return $options;
	}

	/**
	 * Gets the Notifications for HivePress component, when it is there.
	 *
	 * Assigned and then tested rather than checked with isset(): HivePress's Core defines no
	 * __isset(), so isset( hivepress()->x ) is always false even for a component that exists and
	 * works, and a guard written that way disables the feature on every site.
	 *
	 * @return object|null
	 */
	protected function get_notification_component() {
		$component = hivepress()->hpnf_notification;

		return $component ? $component : null;
	}

	/**
	 * Gets every badge counter the plugin understands.
	 *
	 * Kept separate from get_badge_options(), which is the narrower list offered on the settings
	 * screen. Validation and migration must use this one: judging a stored value against the offered
	 * list would rewrite an admin's choice of message counter the moment the Messages extension was
	 * deactivated, and that rewrite is permanent.
	 *
	 * @return array<string, string>
	 */
	public function get_badge_sources() {
		return [
			// "Account activity" rather than the old "All notifications": once the real unread
			// notification count is on the list below, two options both called notifications is a
			// choice nobody can make correctly. This one has never counted notifications -- it is
			// HivePress's own combined counter, which Messages, Bookings and Marketplace add into.
			'notices'       => esc_html__( 'Account activity (HivePress)', 'action-bar-for-hivepress' ),
			'messages'      => esc_html__( 'Unread messages', 'action-bar-for-hivepress' ),
			'notifications' => esc_html__( 'Unread notifications', 'action-bar-for-hivepress' ),
		];
	}

	/**
	 * Gets the extension URL.
	 *
	 * @return string
	 */
	protected function get_extension_url() {
		return (string) hivepress()->get_url( 'action_bar_for_hivepress' );
	}

	/**
	 * Gets the extension version.
	 *
	 * @return string
	 */
	protected function get_extension_version() {
		return (string) hivepress()->get_version( 'action_bar_for_hivepress' );
	}

	/**
	 * Gets the extension path.
	 *
	 * @return string
	 */
	protected function get_extension_path() {
		return (string) hivepress()->get_path( 'action_bar_for_hivepress' );
	}

	/**
	 * Gets a cache safe asset version.
	 *
	 * @param string $path Relative asset path.
	 * @return string
	 */
	protected function get_asset_version( $path ) {
		$version = $this->get_extension_version();

		$file = $this->get_extension_path() . '/' . $path;

		if ( file_exists( $file ) ) {
			$version .= '.' . (string) filemtime( $file );
		}

		return $version;
	}

	/**
	 * Checks if a boolean setting is enabled.
	 *
	 * @param string $name Setting name.
	 * @param bool   $fallback Value to use when the option has never been saved.
	 * @return bool
	 */
	protected function is_setting_enabled( $name, $fallback = false ) {
		$value = get_option( 'hp_action_bar_' . $name, null );

		// An absent option means the default still applies, while a stored empty string is a deliberately unticked box.
		if ( null === $value ) {
			return $fallback;
		}

		return (bool) $value;
	}

	/**
	 * Gets a colour setting value.
	 *
	 * @param string $name Colour name.
	 * @param string $fallback Value to use when the option is empty or not a valid hex colour.
	 * @return string
	 */
	protected function get_color( $name, $fallback ) {
		$color = sanitize_hex_color( (string) get_option( 'hp_action_bar_color_' . $name ) );

		return $color ? $color : $fallback;
	}

	/**
	 * Checks if the glass effect is switched on.
	 *
	 * @return bool
	 */
	protected function is_glass_enabled() {
		return $this->is_setting_enabled( 'glass' );
	}

	/**
	 * Gets a numeric setting, clamped to its range.
	 *
	 * @param string $name Setting name.
	 * @param int    $fallback Value to use when the option is absent or not a number.
	 * @param int    $min Lowest allowed value.
	 * @param int    $max Highest allowed value.
	 * @return int
	 */
	protected function get_number_setting( $name, $fallback, $min, $max ) {
		$value = get_option( 'hp_action_bar_' . $name, null );

		// A cleared number field stores an empty string, which is not numeric, so the default applies. An
		// explicit 0 is numeric and must survive, which is why this tests is_numeric rather than truthiness.
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		return (int) max( $min, min( $max, (int) $value ) );
	}

	/**
	 * Converts a hex colour to an rgba() value.
	 *
	 * @param string $hex Hex colour, with or without the leading hash.
	 * @param float  $alpha Alpha channel between 0 and 1.
	 * @return string Empty string when the colour cannot be parsed.
	 */
	protected function get_rgba_color( $hex, $alpha ) {
		$hex = ltrim( (string) $hex, '#' );

		// sanitize_hex_color() accepts the three-digit form, so expand it before reading pairs.
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return '';
		}

		return 'rgba(' . hexdec( substr( $hex, 0, 2 ) ) . ',' . hexdec( substr( $hex, 2, 2 ) ) . ',' . hexdec( substr( $hex, 4, 2 ) ) . ',' . round( $alpha, 2 ) . ')';
	}

	/**
	 * Checks if the current user is a vendor.
	 *
	 * @return bool
	 */
	protected function is_vendor() {
		static $is_vendor = null;

		if ( null === $is_vendor ) {
			$is_vendor = false;

			if ( is_user_logged_in() && class_exists( '\HivePress\Models\Vendor' ) ) {
				$is_vendor = (bool) \HivePress\Models\Vendor::query()->filter(
					[
						'status' => 'publish',
						'user'   => get_current_user_id(),
					]
				)->get_first_id();
			}
		}

		return $is_vendor;
	}

	/**
	 * Gets the route link options.
	 *
	 * @return array<string, string>
	 */
	/**
	 * Gets the WooCommerce account endpoints an owner can point a bar item at.
	 *
	 * Anything that is a page somebody can sit on. The endpoints behind a single order, a password
	 * reset or an action on a saved card are not, so they are named and left out.
	 *
	 * @return array Endpoint slugs mapped to a readable label.
	 */
	protected function get_wc_account_endpoints() {
		if ( ! function_exists( 'WC' ) || ! WC()->query || ! method_exists( WC()->query, 'get_query_vars' ) ) {
			return [];
		}

		/**
		 * Filters the account endpoints never offered as bar items.
		 *
		 * @hook hpab/account_endpoint_exclusions
		 * @param {array} $excluded Endpoint slugs.
		 * @return {array} Endpoint slugs.
		 */
		$excluded = (array) apply_filters(
			'hpab/account_endpoint_exclusions',
			[
				'order-pay',
				'order-received',
				'view-order',
				'lost-password',
				'add-payment-method',
				'delete-payment-method',
				'set-default-payment-method',
				'view-subscription',
				'subscription-payment-method',

				// Already offered above under their own names.
				'orders',
				'customer-logout',
			]
		);

		// The menu is only consulted for its wording, which reads better than a slug where it has it.
		$labels = function_exists( 'wc_get_account_menu_items' ) ? (array) wc_get_account_menu_items() : [];

		$endpoints = [];

		foreach ( array_keys( (array) WC()->query->get_query_vars() ) as $endpoint ) {
			$endpoint = (string) $endpoint;

			if ( ! $endpoint || in_array( $endpoint, $excluded, true ) ) {
				continue;
			}

			$label = isset( $labels[ $endpoint ] ) ? wp_strip_all_tags( (string) $labels[ $endpoint ] ) : ucwords( str_replace( [ '-', '_' ], ' ', $endpoint ) );

			/* translators: %s: menu item label. */
			$endpoints[ $endpoint ] = sprintf( esc_html__( '%s (WooCommerce)', 'action-bar-for-hivepress' ), $label );
		}

		return $endpoints;
	}

	/**
	 * Gets the route link options for the item dropdown.
	 *
	 * @return array<string, string>
	 */
	public function get_route_link_options() {
		$options = [];

		// Set candidate routes.
		$candidates = [
			'requests_view_page'         => esc_html__( 'Requests', 'action-bar-for-hivepress' ),
			'request_submit_page'        => esc_html__( 'Post a request', 'action-bar-for-hivepress' ),
			'membership_plans_view_page' => esc_html__( 'Select plan', 'action-bar-for-hivepress' ),
			'vendor_register_page'       => esc_html__( 'Become a vendor', 'action-bar-for-hivepress' ),
			'listings_edit_page'         => esc_html__( 'My listings', 'action-bar-for-hivepress' ),
			'requests_edit_page'         => esc_html__( 'My requests', 'action-bar-for-hivepress' ),
			'offers_view_page'           => esc_html__( 'Offers', 'action-bar-for-hivepress' ),
			'bookings_view_page'         => esc_html__( 'Bookings', 'action-bar-for-hivepress' ),
			'search_alerts_view_page'    => esc_html__( 'Searches', 'action-bar-for-hivepress' ),
			'memberships_view_page'      => esc_html__( 'Memberships', 'action-bar-for-hivepress' ),
			'vendor_dashboard_page'      => esc_html__( 'Dashboard', 'action-bar-for-hivepress' ),
			'vendor_calendar_page'       => esc_html__( 'Calendar', 'action-bar-for-hivepress' ),
			'orders_edit_page'           => esc_html__( 'Received orders', 'action-bar-for-hivepress' ),
			'payouts_view_page'          => esc_html__( 'Payouts', 'action-bar-for-hivepress' ),
			'user_logout_page'           => esc_html__( 'Sign out', 'action-bar-for-hivepress' ),
		];

		foreach ( $candidates as $route => $label ) {
			if ( $this->get_route_url( $route ) ) {
				$options[ 'route_' . $route ] = $label;
			}
		}

		// Add the account pages registered by active extensions.
		if ( class_exists( '\HivePress\Menus\User_Account' ) ) {
			$covered = [ 'user_account_page', 'user_edit_settings_page', 'user_login_page', 'messages_thread_page', 'listings_favorite_page' ];

			try {
				$menu_items = ( new \HivePress\Menus\User_Account() )->get_items();
			} catch ( \Throwable $throwable ) {

				// Some extensions register route title callbacks that are unsafe in the admin area, so fall back to the static candidates.
				$menu_items = [];
			}

			foreach ( $menu_items as $item ) {
				$route = hp\get_array_value( $item, 'route' );
				$label = hp\get_array_value( $item, 'label' );

				if ( $route && is_string( $label ) && $label && ! isset( $options[ 'route_' . $route ] ) && ! in_array( $route, $covered, true ) ) {
					$options[ 'route_' . $route ] = $label;
				}
			}
		}

		/*
		 * Then the account pages that the menu above cannot see.
		 *
		 * Reading the account menu only finds an extension's page if that extension registers its
		 * menu item in the admin, and several do not: this plugin's own Notifications extension adds
		 * its item inside `if ( ! is_admin() )`, which is a perfectly reasonable thing for a
		 * front-end-only menu to do, and the effect was that the settings dropdown offered every
		 * account page except Notifications. Routes carry no such guard - a controller registers them
		 * everywhere - so scanning them finds the pages the menu misses, whoever wrote the extension.
		 *
		 * Do not "simplify" this back to the menu alone. The menu is still read first because an
		 * extension may give its item a nicer label than its route title, and this fills the gaps.
		 */
		foreach ( $this->get_account_route_options() as $key => $label ) {
			if ( ! isset( $options[ $key ] ) ) {
				$options[ $key ] = $label;
			}
		}

		return $options;
	}

	/**
	 * Gets the link options for account pages, read from the routes themselves.
	 *
	 * Routes are assembled exactly as the core router assembles them, because the router keeps its
	 * own copy behind a protected method. Building them again is cheap - controllers return static
	 * configuration arrays - and re-applying the documented filter keeps any route an extension
	 * renames or removes in step with what the site actually serves.
	 *
	 * @return array<string, string>
	 */
	protected function get_account_route_options() {
		$routes = [];

		/*
		 * Core::get_controllers() is served by Core::__call(), which builds and returns the object
		 * list for any get_<directory>() name. PHPStan cannot see through that, hence the ignore -
		 * which has to be the LAST line before the call, so keep this explanation above it.
		 */
		// @phpstan-ignore-next-line
		foreach ( hivepress()->get_controllers() as $controller ) {
			$routes = hp\merge_arrays( $routes, $controller->get_routes() );
		}

		/** This filter is documented in HivePress core, includes/components/class-router.php. */
		$routes = apply_filters( 'hivepress/v1/routes', $routes );

		/*
		 * Two kinds of route are skipped.
		 *
		 * The first are pages the settings screen already offers under a plainer name, which would
		 * otherwise appear twice.
		 *
		 * The second are the one-shot pages a visitor reaches by clicking a link in an email -
		 * resetting a password, confirming an address. They are built on the account page and so look
		 * exactly like account pages to this scan, but "Email Verified" is not somewhere anybody
		 * navigates to, and offering it as a menu destination would produce a permanently broken item.
		 */
		$covered = [
			'user_account_page',
			'user_edit_settings_page',
			'user_login_page',
			'messages_thread_page',
			'listings_favorite_page',
			'user_password_reset_page',
			'user_email_verify_page',
		];

		/**
		 * Filters the account routes left out of the item link dropdown.
		 *
		 * @hook hpab/account_route_exclusions
		 * @param {array} $routes Route names.
		 * @return {array} Route names.
		 */
		$covered = (array) apply_filters( 'hpab/account_route_exclusions', $covered );

		$options = [];

		foreach ( $routes as $name => $route ) {
			if ( ! is_array( $route ) || in_array( $name, $covered, true ) ) {
				continue;
			}

			// Only the account pages themselves. A deeper page, such as notification settings, is
			// based on its own parent rather than on the account page, and is not a menu destination.
			if ( 'user_account_page' !== hp\get_array_value( $route, 'base' ) ) {
				continue;
			}

			// Endpoints and form handlers are not pages anybody can link a menu item to.
			if ( hp\get_array_value( $route, 'rest' ) || 'GET' !== strtoupper( (string) ( hp\get_array_value( $route, 'method' ) ? $route['method'] : 'GET' ) ) ) {
				continue;
			}

			// A path with a parameter in it needs a specific record, which a fixed menu item has not got.
			if ( false !== strpos( (string) hp\get_array_value( $route, 'path' ), '(?P<' ) ) {
				continue;
			}

			$title = hp\get_array_value( $route, 'title' );

			/*
			 * Callable titles are left alone on purpose. They are written for the front end and read
			 * the current request - one of them fetching the listing being viewed is what made the
			 * account-menu call above need its try/catch. There is nothing to show in the admin, so
			 * the option is skipped rather than risking a fatal on the settings screen.
			 */
			if ( ! is_string( $title ) || ! $title ) {
				continue;
			}

			$options[ 'route_' . $name ] = $title;
		}

		return $options;
	}

	/**
	 * Gets the page link options.
	 *
	 * @return array<string, string>
	 */
	public function get_page_options() {
		$options = [];

		$pages = get_posts(
			[
				'post_type'              => 'page',
				'post_status'            => 'publish',
				'numberposts'            => -1,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'suppress_filters'       => false,
			]
		);

		foreach ( $pages as $page ) {
			/*
			 * A page with no title would otherwise be a blank line in the dropdown that an admin can
			 * select but cannot identify - a real site had one, and picking it silently produced a
			 * menu item pointing at /8332-2/. WordPress falls back to the auto-generated slug for the
			 * URL, so show that same slug here and the two at least agree.
			 */
			$title = trim( (string) $page->post_title );

			if ( ! $title ) {
				/* translators: %s: page slug. */
				$title = sprintf( esc_html__( '(no title) - /%s/', 'action-bar-for-hivepress' ), $page->post_name );
			}

			$options[ 'page_' . $page->ID ] = $title;
		}

		return $options;
	}

	/**
	 * Adds the stored item values back into the settings dropdown options.
	 *
	 * The link, icon and badge dropdowns are built from what exists right now: WooCommerce
	 * destinations only while WooCommerce is active, extension pages only while their extension is
	 * active, pages only while they are published. The front end deliberately preserves a stored
	 * value whose source has gone away (see get_badge_sources()), but a select can only round-trip
	 * a value it renders as an option: with the option missing, the browser falls back to the
	 * placeholder, the next Save posts an empty string, and the stored choice is erased for good -
	 * deactivating Bookings for an afternoon was enough to silently lose every Bookings item on the
	 * next save of this tab. So each bar's dropdowns are widened with the values that bar already
	 * stores, labelled as currently unavailable, and the choice survives the save exactly as the
	 * section description promises. This runs when the settings config is built, which HivePress
	 * does on the save request as well as the render, so validation accepts the value too. Widened
	 * per bar rather than once for both, so one bar's leftover is never offered as a fresh choice
	 * on the other.
	 *
	 * @param array<string, array<string, mixed>> $fields Repeater field arguments keyed by field name.
	 * @param string                              $bar Bar name.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_stored_item_options( $fields, $bar ) {
		$rows = array_filter( (array) get_option( 'hp_action_bar_' . $bar . '_items', [] ), 'is_array' );

		foreach ( $rows as $row ) {

			// Keep the stored link selectable.
			$link = hp\get_array_value( $row, 'link' );

			if ( is_string( $link ) && $link && ! isset( $fields['link']['options'][ $link ] ) ) {
				$label = $this->get_unavailable_link_label( $link );

				if ( $label ) {
					/* translators: %s: option name. */
					$fields['link']['options'][ $link ] = sprintf( esc_html__( '%s (currently unavailable)', 'action-bar-for-hivepress' ), $label );
				}
			}

			// Keep the stored badge counter selectable. Only counters the plugin understands are
			// preserved: get_bar_items() rewrites anything else to a named counter on display, so an
			// unrecognised leftover has nothing to come back for.
			$badge = hp\get_array_value( $row, 'badge' );

			if ( is_string( $badge ) && $badge && ! isset( $fields['badge']['options'][ $badge ] ) ) {
				$source = hp\get_array_value( $this->get_badge_sources(), $badge );

				if ( $source ) {
					/* translators: %s: option name. */
					$fields['badge']['options'][ $badge ] = sprintf( esc_html__( '%s (currently unavailable)', 'action-bar-for-hivepress' ), $source );
				}
			}

			// Keep the stored icon selectable. Icons from the beta's free-text box, such as
			// "far fa-heart", are not in the bundled list, and a slug can drop out of that list when
			// the bundled set changes; both still render on the front end, so both must survive a save.
			$icon = hp\get_array_value( $row, 'icon' );

			if ( is_string( $icon ) && $icon ) {
				/*
				 * The icon options ship as the name of a HivePress options preset, which core only
				 * resolves into a list when the field is built. Appending needs the real list, so it
				 * is resolved here the same way core resolves it - but assigned back only when this
				 * icon is actually missing, and then together with the select2 icon preview
				 * attribute that the preset name would otherwise have switched on.
				 */
				$icon_options = is_array( $fields['icon']['options'] ) ? $fields['icon']['options'] : (array) hivepress()->get_config( (string) $fields['icon']['options'] );

				if ( ! isset( $icon_options[ $icon ] ) ) {
					/* translators: %s: icon name. */
					$icon_options[ $icon ] = sprintf( esc_html__( '%s (custom)', 'action-bar-for-hivepress' ), $icon );

					$fields['icon']['options'] = $icon_options;

					$fields['icon']['attributes']['data-template'] = 'icon';
				}
			}
		}

		return $fields;
	}

	/**
	 * Builds a readable label for a stored link whose destination is unavailable.
	 *
	 * Only destinations the front end knows how to resolve are named here - the same set
	 * get_bar_items() accepts - so a corrupted value cannot re-enter the dropdown through this path.
	 *
	 * @param string $link Stored link value.
	 * @return string Empty string when the value is not one the plugin can preserve.
	 */
	protected function get_unavailable_link_label( $link ) {

		// A page keeps its title through draft and trash, so name it while it can still come back.
		if ( 0 === strpos( $link, 'page_' ) ) {
			$page_id = absint( substr( $link, 5 ) );

			$page = $page_id ? get_post( $page_id ) : null;

			$title = $page ? trim( (string) $page->post_title ) : '';

			if ( ! $title ) {
				/* translators: %s: page ID. */
				$title = sprintf( esc_html__( 'Page #%s', 'action-bar-for-hivepress' ), $page_id );
			}

			return $title;
		}

		// The two named WooCommerce destinations keep the labels they had while WooCommerce was active.
		if ( 'wc_orders' === $link ) {
			return esc_html__( 'Placed orders', 'action-bar-for-hivepress' );
		}

		if ( 'wc_cart' === $link ) {
			return esc_html__( 'Cart', 'action-bar-for-hivepress' );
		}

		// Routes and account endpoints have no readable name while their extension is away, so the
		// slug is dressed up instead: "route_bookings_view_page" reads as "Bookings View Page".
		$prefixes = [
			'route_' => 6,
			'wcep_'  => 5,
		];

		foreach ( $prefixes as $prefix => $length ) {
			if ( 0 === strpos( $link, $prefix ) ) {
				return ucwords( str_replace( [ '-', '_' ], ' ', substr( $link, $length ) ) );
			}
		}

		return '';
	}

	/**
	 * Gets a route URL.
	 *
	 * @param string $name Route name.
	 * @return string
	 */
	protected function get_route_url( $name ) {
		return (string) hivepress()->router->get_url( $name );
	}

	/**
	 * Gets an item URL.
	 *
	 * @param string $link Link type.
	 * @param mixed  $custom_url Custom URL.
	 * @return string
	 */
	protected function get_item_url( $link, $custom_url ) {
		$url = '';

		switch ( $link ) {
			case 'home':
				$url = home_url( '/' );

				break;

			case 'listings':
				$url = $this->get_route_url( 'listings_view_page' );

				break;

			case 'listing_submit':
				$url = $this->get_route_url( 'listing_submit_page' );

				break;

			case 'vendors':
				$url = $this->get_route_url( 'vendors_view_page' );

				break;

			case 'account':
				if ( is_user_logged_in() ) {

					// The account route only forwards to the first account menu item, which varies by user state and installed extensions, so link to the stable settings page instead.
					$url = $this->get_route_url( 'user_edit_settings_page' );
				} else {
					$url = $this->get_route_url( 'user_login_page' );
				}

				break;

			case 'messages':
				$url = $this->get_route_url( 'messages_thread_page' );

				break;

			case 'favorites':
				$url = $this->get_route_url( 'listings_favorite_page' );

				break;

			case 'wc_orders':
				if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
					$url = (string) wc_get_account_endpoint_url( 'orders' );
				}

				break;

			case 'wc_cart':
				if ( function_exists( 'wc_get_cart_url' ) ) {
					$url = (string) wc_get_cart_url();
				}

				break;

			case 'custom':
				$url = esc_url_raw( (string) $custom_url );

				break;

			default:
				if ( 0 === strpos( $link, 'wcep_' ) ) {

					// Any other WooCommerce account endpoint, stored as wcep_{endpoint}.
					if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
						$url = (string) wc_get_account_endpoint_url( substr( $link, 5 ) );
					}
				} elseif ( 0 === strpos( $link, 'route_' ) ) {
					$url = $this->get_route_url( substr( $link, 6 ) );
				} elseif ( 0 === strpos( $link, 'page_' ) ) {
					$page_id = absint( substr( $link, 5 ) );

					if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
						$url = (string) get_permalink( $page_id );
					}
				}

				break;
		}

		return $url;
	}

	/**
	 * Gets the action bar items.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_items() {
		if ( ! is_null( $this->items ) ) {
			return $this->items;
		}

		// Get bar name.
		$bar = 'user';

		if ( $this->is_setting_enabled( 'enable_vendor_bar' ) && $this->is_vendor() ) {
			$bar = 'vendor';
		}

		$items = $this->get_bar_items( $bar );

		// Removing every vendor row stores an empty option rather than no option, which would otherwise leave
		// vendors with no navigation at all, so an empty vendor bar falls back to the items everyone else sees.
		if ( ! $items && 'vendor' === $bar ) {
			$bar = 'user';

			$items = $this->get_bar_items( $bar );
		}

		/**
		 * Filters the action bar items.
		 *
		 * @hook hivepress/v1/action_bar/items
		 * @param {array} $items Item arguments.
		 * @param {string} $bar Bar name.
		 * @return {array} Item arguments.
		 */
		$items = (array) apply_filters( 'hivepress/v1/action_bar/items', $items, $bar );

		// Normalize the item structure so developer-added items never trigger warnings when rendered.
		$this->items = [];

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$this->items[] = array_merge(
				[
					'link'        => '',
					'url'         => '',
					'icon'        => 'fas fa-circle',
					'label'       => '',
					'style'       => 'default',
					'badge'       => false,
					'badge_count' => 0,
				],
				$item
			);
		}

		return $this->items;
	}

	/**
	 * Gets the resolved items for one bar.
	 *
	 * @param string $bar Bar name.
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_bar_items( $bar ) {

		// Get item rows.
		$rows = get_option( 'hp_action_bar_' . $bar . '_items', null );

		// Fall back to the beta options without persisting them (the admin-side migration handles the rewrite).
		if ( is_null( $rows ) && ! get_option( 'hp_action_bar_migrated' ) ) {
			$rows = $this->get_legacy_items( $bar );
		}

		if ( is_null( $rows ) ) {
			$rows = $this->get_item_defaults( $bar );
		}

		$rows = array_filter( (array) $rows, 'is_array' );

		$items = [];

		foreach ( $rows as $row ) {

			// Keep at most five valid items.
			if ( count( $items ) >= 5 ) {
				break;
			}

			// Get item link.
			$link = hp\get_array_value( $row, 'link' );

			if ( ! $link || ( ! isset( $this->get_link_options()[ $link ] ) && 0 !== strpos( (string) $link, 'page_' ) && 0 !== strpos( (string) $link, 'route_' ) && 0 !== strpos( (string) $link, 'wcep_' ) ) ) {
				continue;
			}

			// Get item URL.
			$url = $this->get_item_url( $link, hp\get_array_value( $row, 'url' ) );

			if ( ! $url ) {
				continue;
			}

			// Get item icon.
			$icon = (string) hp\get_array_value( $row, 'icon' );

			$icon = strtolower( trim( (string) preg_replace( '/[^a-zA-Z0-9\- ]/', '', $icon ) ) );

			if ( $icon && false === strpos( $icon, ' ' ) ) {
				$icon = 'fas fa-' . $icon;
			}

			if ( ! $icon ) {
				$icon = 'fas fa-circle';
			}

			// Get item label.
			$label = sanitize_text_field( (string) hp\get_array_value( $row, 'label' ) );

			// Get item style.
			$style = hp\get_array_value( $row, 'style' );

			if ( 'prominent' !== $style ) {
				$style = 'default';
			}

			// Get item badge source.
			$badge = hp\get_array_value( $row, 'badge' );

			// Rows saved before the badge became a choice of counter store a boolean tick, which used to mean
			// the message count on Messages items and the combined count everywhere else.
			if ( $badge && ! isset( $this->get_badge_sources()[ $badge ] ) ) {
				$badge = 'messages' === $link ? 'messages' : 'notices';
			}

			if ( ! $this->is_setting_enabled( 'enable_badge', true ) ) {
				$badge = '';
			}

			// Get item badge count.
			$badge_count = 0;

			if ( $badge ) {
				$request = hivepress()->request;

				if ( 'messages' === $badge ) {
					$badge_count = absint( $request->get_context( 'message_unread_count' ) );
				} elseif ( 'notifications' === $badge ) {
					$component = $this->get_notification_component();

					// Falls back to zero rather than to the combined counter when the plugin is
					// gone: a badge that silently starts counting something else is worse than a
					// badge that stops.
					$badge_count = $component ? absint( $component->get_unread_count( get_current_user_id() ) ) : 0;
				} else {
					$badge_count = absint( $request->get_context( 'notice_count' ) );
				}
			}

			$items[] = [
				'link'        => $link,
				'url'         => $url,
				'icon'        => $icon,
				'label'       => $label,
				'style'       => $style,
				'badge'       => $badge,

				'badge_count' => $badge_count,
			];
		}

		return $items;
	}

	/**
	 * Migrates item settings if required.
	 *
	 * @return void
	 */
	public function maybe_migrate_items() {

		// The flag is versioned: absent runs everything, '1' re-runs only the row normalisation added in
		// 1.1.0 (boolean badge ticks become a named counter), '2' is current.
		$migrated = (string) get_option( 'hp_action_bar_migrated' );

		if ( '2' === $migrated ) {
			return;
		}

		foreach ( [ 'user', 'vendor' ] as $bar ) {
			if ( ! $migrated && is_null( get_option( 'hp_action_bar_' . $bar . '_items', null ) ) ) {
				$this->migrate_legacy_items( $bar );
			}

			$this->normalize_items( $bar );
		}

		update_option( 'hp_action_bar_migrated', '2' );
	}

	/**
	 * Normalizes stored item rows from earlier versions.
	 *
	 * @param string $bar Bar name.
	 * @return void
	 */
	protected function normalize_items( $bar ) {
		$rows = get_option( 'hp_action_bar_' . $bar . '_items', null );

		if ( ! is_array( $rows ) ) {
			return;
		}

		$changed = false;

		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$icon = hp\get_array_value( $row, 'icon' );

			if ( is_string( $icon ) && 0 === strpos( $icon, 'fas fa-' ) ) {
				$row['icon'] = substr( $icon, 7 );

				$rows[ $index ] = $row;

				$changed = true;
			}

			// Keep the badge on account and message items saved before the per-item option existed.
			if ( ! array_key_exists( 'badge', $row ) && in_array( hp\get_array_value( $row, 'link' ), [ 'account', 'messages' ], true ) ) {
				$row['badge'] = 'messages' === hp\get_array_value( $row, 'link' ) ? 'messages' : 'notices';

				$rows[ $index ] = $row;

				$changed = true;
			}

			// Rewrite a boolean badge tick from 1.1.0's predecessor as the counter it used to show, so the
			// settings screen select and the front end agree.
			$badge = hp\get_array_value( $row, 'badge' );

			if ( $badge && ! isset( $this->get_badge_sources()[ $badge ] ) ) {
				$row['badge'] = 'messages' === hp\get_array_value( $row, 'link' ) ? 'messages' : 'notices';

				$rows[ $index ] = $row;

				$changed = true;
			}
		}

		if ( $changed ) {
			update_option( 'hp_action_bar_' . $bar . '_items', $rows );
		}
	}

	/**
	 * Reads the beta item options without modifying them.
	 *
	 * @param string $bar Bar name.
	 * @return array<int, array<string, string>>|null
	 */
	protected function get_legacy_items( $bar ) {
		$found = false;

		$rows = [];

		for ( $index = 1; $index <= 5; $index++ ) {
			$prefix = 'hp_action_bar_' . $bar . '_item_' . $index . '_';

			// Get item link.
			$link = get_option( $prefix . 'link', null );

			if ( ! is_null( $link ) ) {
				$found = true;
			}

			// Add item row.
			if ( $link ) {

				// Get item icon.
				$icon = (string) get_option( $prefix . 'icon' );

				if ( ! $icon ) {
					$icon = (string) get_option( $prefix . 'icon_custom' );
				}

				$rows[] = [
					'link'  => (string) $link,
					'icon'  => $icon,
					'label' => (string) get_option( $prefix . 'label' ),
					'url'   => (string) get_option( $prefix . 'url' ),
					'style' => (string) get_option( $prefix . 'style' ),
				];
			}
		}

		if ( ! $found ) {
			return null;
		}

		return $rows;
	}

	/**
	 * Migrates item settings from the beta versions.
	 *
	 * @param string $bar Bar name.
	 * @return array<int, array<string, string>>|null
	 */
	protected function migrate_legacy_items( $bar ) {
		$rows = $this->get_legacy_items( $bar );

		if ( is_null( $rows ) ) {
			return null;
		}

		// Add the new option.
		update_option( 'hp_action_bar_' . $bar . '_items', $rows );

		// Delete the old options.
		foreach ( [ 'link', 'icon', 'icon_custom', 'label', 'url', 'style' ] as $key ) {
			for ( $index = 1; $index <= 5; $index++ ) {
				delete_option( 'hp_action_bar_' . $bar . '_item_' . $index . '_' . $key );
			}
		}

		return $rows;
	}

	/**
	 * Checks if the action bar is visible.
	 *
	 * @return bool
	 */
	protected function is_action_bar_visible() {
		$visible = $this->is_setting_enabled( 'enable_mobile', true ) || $this->is_setting_enabled( 'enable_tablet' ) || $this->is_setting_enabled( 'enable_desktop' );

		// Check hidden pages.
		if ( $visible ) {
			$page_ids = array_filter( array_map( 'absint', (array) get_option( 'hp_action_bar_hidden_pages', [] ) ) );

			if ( $page_ids && is_page( $page_ids ) ) {
				$visible = false;
			}
		}

		// Check checkout pages.
		if ( $visible && $this->is_setting_enabled( 'hide_checkout', true ) && function_exists( 'is_checkout' ) && ( is_checkout() || is_cart() ) ) {
			$visible = false;
		}

		/**
		 * Filters the action bar visibility.
		 *
		 * @hook hivepress/v1/action_bar/visible
		 * @param {bool} $visible Visibility flag.
		 * @return {bool} Visibility flag.
		 */
		return (bool) apply_filters( 'hivepress/v1/action_bar/visible', $visible );
	}

	/**
	 * Gets the inline styles.
	 *
	 * @return string
	 */
	protected function get_inline_styles() {

		// Get colours.
		$colors = [
			'background'           => $this->get_color( 'background', '#f5f5f5' ),
			'border'               => $this->get_color( 'border', '#dddddd' ),
			'icon'                 => $this->get_color( 'icon', '#5f5f5f' ),
			'label'                => $this->get_color( 'label', '#5f5f5f' ),
			'active'               => $this->get_color( 'active', '#111111' ),
			'prominent-background' => $this->get_color( 'prominent_background', '#333333' ),
			'prominent-icon'       => $this->get_color( 'prominent_icon', '#ffffff' ),
			'badge-background'     => $this->get_color( 'badge_background', '#d63638' ),
			'badge-text'           => $this->get_color( 'badge_text', '#ffffff' ),
		];

		// Get dimensions.
		$height     = $this->get_number_setting( 'height', 56, 44, 120 );
		$label_size = $this->get_number_setting( 'label_size', 11, 9, 16 );

		// Get label weight. This one is a select rather than a range, so an unrecognised value resets.
		$label_weight = absint( get_option( 'hp_action_bar_label_weight' ) );

		if ( ! in_array( $label_weight, [ 400, 500, 600, 700 ], true ) ) {
			$label_weight = 500;
		}

		/*
		 * The height goes on :root as well as on the bar itself. Scoped only to .hp-action-bar it is
		 * unreadable from anywhere else, so anything that needs to sit above the bar - a cookie
		 * notice, a chat launcher, the pop-ups from Notifications for HivePress - has to measure the
		 * element in JavaScript instead of reading one value in CSS. Emitting it here costs one
		 * declaration and makes "clear the bar" a one-line rule for everybody.
		 */
		$styles = ':root{--hp-action-bar-height:' . $height . 'px;}';

		$styles .= '.hp-action-bar{';

		$styles .= '--hp-action-bar-height:' . $height . 'px;';

		$styles .= '--hp-action-bar-label-size:' . $label_size . 'px;--hp-action-bar-label-weight:' . $label_weight . ';';

		foreach ( $colors as $name => $color ) {
			$styles .= '--hp-action-bar-' . $name . ':' . $color . ';';
		}

		// Set the glass values. The translucent background is emitted as its own property rather than
		// replacing the solid one, so a browser without backdrop-filter support keeps the opaque bar
		// instead of rendering unreadable text over the page.
		if ( $this->is_glass_enabled() ) {
			$tint = $this->get_rgba_color( $colors['background'], $this->get_number_setting( 'glass_opacity', 72, 10, 100 ) / 100 );

			if ( $tint ) {
				$styles .= '--hp-action-bar-glass-background:' . $tint . ';';
			}

			$styles .= '--hp-action-bar-glass-blur:' . $this->get_number_setting( 'glass_blur', 20, 0, 40 ) . 'px;';
		}

		$styles .= '}';

		/**
		 * Filters the responsive breakpoints.
		 *
		 * @hook hivepress/v1/action_bar/breakpoints
		 * @param {array} $breakpoints Breakpoint values as CSS lengths.
		 * @return {array} Breakpoint values as CSS lengths.
		 */
		$breakpoints = apply_filters(
			'hivepress/v1/action_bar/breakpoints',
			[

				/*
				 * em, not px, so the bar switches at exactly the width HivePress's own grid switches at.
				 * grid.min.css breaks at min-width 48em and 64em, which are 768px and 1024px only while
				 * the root font size is the default 16px; a theme that changes it would otherwise leave
				 * the columns reflowing at one width and the bar appearing at another.
				 */
				'mobile_max'  => '47.99em',
				'tablet_min'  => '48em',
				'tablet_max'  => '64em',

				// Desktop starts just past where tablet stops, so the two never overlap and a width
				// cannot match both queries at once.
				'desktop_min' => '64.01em',
			]
		);

		// Set display styles.
		$display = '.hp-action-bar{display:flex;}body.hp-action-bar-visible{padding-bottom:calc(' . ( $height + 12 ) . 'px + env(safe-area-inset-bottom, 0px));}';

		if ( $this->is_setting_enabled( 'enable_mobile', true ) ) {
			$styles .= '@media (max-width:' . $this->get_css_length( hp\get_array_value( $breakpoints, 'mobile_max' ), '47.99em' ) . '){' . $display . '}';
		}

		if ( $this->is_setting_enabled( 'enable_tablet' ) ) {
			$styles .= '@media (min-width:' . $this->get_css_length( hp\get_array_value( $breakpoints, 'tablet_min' ), '48em' ) . ') and (max-width:' . $this->get_css_length( hp\get_array_value( $breakpoints, 'tablet_max' ), '64em' ) . '){' . $display . '}';
		}

		if ( $this->is_setting_enabled( 'enable_desktop' ) ) {
			/*
			 * On a wide screen the items must stop stretching. Left as they are, `flex: 1 1 0` divides
			 * the full width between them, so three items on a 1920px monitor sit 640px apart with the
			 * outer two pinned to the far corners: measured, and it reads as broken rather than
			 * deliberate. Sizing them to their content and centring the row gives the compact dock a
			 * desktop visitor expects. Written at two-class specificity so it cannot depend on source
			 * order against the stylesheet, and emitted here rather than in the CSS file so it always
			 * uses the same breakpoint as the display rule above, even when that is filtered.
			 */
			$desktop = $display . '.hp-action-bar{justify-content:center;}.hp-action-bar .hp-action-bar__item{flex:0 1 auto;padding-left:2rem;padding-right:2rem;}';

			$styles .= '@media (min-width:' . $this->get_css_length( hp\get_array_value( $breakpoints, 'desktop_min' ), '64.01em' ) . '){' . $desktop . '}';
		}

		return $styles;
	}

	/**
	 * Sanitises a CSS length for a media query.
	 *
	 * Filtered values reach a stylesheet, so only a bare number with an optional px, em or rem unit is
	 * accepted. A plain number is treated as pixels, which keeps any filter written against the
	 * earlier integer-only signature working.
	 *
	 * @param mixed  $value Filtered value.
	 * @param string $fallback Value to use when the filtered one is not a usable length.
	 * @return string
	 */
	protected function get_css_length( $value, $fallback ) {
		$value = trim( (string) $value );

		if ( preg_match( '/^\d+(\.\d+)?$/', $value ) ) {
			return $value . 'px';
		}

		if ( preg_match( '/^\d+(\.\d+)?(px|em|rem)$/', $value ) ) {
			return $value;
		}

		return $fallback;
	}

	/**
	 * Enqueues the frontend assets.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		if ( ! $this->is_action_bar_visible() || ! $this->get_items() ) {
			return;
		}

		// Enqueue styles.
		wp_enqueue_style(
			'hivepress-action-bar-frontend',
			$this->get_extension_url() . '/assets/css/frontend.min.css',
			[],
			$this->get_asset_version( 'assets/css/frontend.min.css' )
		);

		wp_add_inline_style( 'hivepress-action-bar-frontend', $this->get_inline_styles() );

		// Enqueue scripts.
		wp_enqueue_script(
			'hivepress-action-bar-frontend',
			$this->get_extension_url() . '/assets/js/frontend.min.js',
			[],
			$this->get_asset_version( 'assets/js/frontend.min.js' ),
			true
		);

		wp_localize_script(
			'hivepress-action-bar-frontend',
			'hivepressActionBarFrontendData',
			[
				'safeArea' => $this->is_setting_enabled( 'safe_area' ),
			]
		);
	}

	/**
	 * Enqueues the backend assets.
	 *
	 * @return void
	 */
	public function enqueue_backend_assets() {

		// No nonce applies here: these two values only decide whether this screen is the one that needs
		// our assets, they are never used to write anything, and both are run through sanitize_key().
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$page = sanitize_key( wp_unslash( (string) hp\get_array_value( $_GET, 'page' ) ) );
		$tab  = sanitize_key( wp_unslash( (string) hp\get_array_value( $_GET, 'tab' ) ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( 'hp_settings' !== $page || 'action_bar' !== $tab ) {
			return;
		}

		// Enqueue styles.
		wp_enqueue_style( 'wp-color-picker' );

		wp_enqueue_style(
			'hivepress-action-bar-backend',
			$this->get_extension_url() . '/assets/css/backend.min.css',
			[],
			$this->get_asset_version( 'assets/css/backend.min.css' )
		);

		// Enqueue scripts.
		wp_enqueue_script(
			'hivepress-action-bar-backend',
			$this->get_extension_url() . '/assets/js/backend.min.js',
			[ 'jquery', 'wp-color-picker' ],
			$this->get_asset_version( 'assets/js/backend.min.js' ),
			true
		);
	}

	/**
	 * Alters the body classes.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function alter_body_classes( $classes ) {
		if ( $this->is_action_bar_visible() && $this->get_items() ) {
			$classes[] = 'hp-action-bar-visible';
		}

		return $classes;
	}

	/**
	 * Renders the action bar.
	 *
	 * @return void
	 */
	public function render_action_bar() {
		if ( ! $this->is_action_bar_visible() ) {
			return;
		}

		// Get items.
		$items = $this->get_items();

		if ( ! $items ) {
			return;
		}

		// Set bar classes.
		$classes = [ 'hp-action-bar' ];

		if ( 'above' === get_option( 'hp_action_bar_label_position' ) ) {
			$classes[] = 'hp-action-bar--labels-above';
		}

		if ( $this->is_glass_enabled() ) {
			$classes[] = 'hp-action-bar--glass';

			if ( $this->is_setting_enabled( 'glass_highlight', true ) ) {
				$classes[] = 'hp-action-bar--glass-edge';
			}
		}

		$output = '<nav class="' . esc_attr( implode( ' ', $classes ) ) . '" aria-label="' . esc_attr__( 'Mobile navigation', 'action-bar-for-hivepress' ) . '">';

		foreach ( $items as $item ) {

			// Set item classes.
			$item_classes = [ 'hp-action-bar__item', 'hp-action-bar__item--' . $item['style'] ];

			// Set item label.
			$aria_label = $item['label'];

			if ( ! $aria_label && 0 === strpos( $item['link'], 'page_' ) ) {
				$aria_label = get_the_title( absint( substr( $item['link'], 5 ) ) );
			}

			if ( ! $aria_label && 0 === strpos( $item['link'], 'route_' ) ) {
				// Route titles can be callables that are unsafe outside their own page context, so only string titles are used.
				$title = hp\get_array_value( (array) hivepress()->router->get_route( substr( $item['link'], 6 ) ), 'title' );

				if ( is_string( $title ) && $title ) {
					$aria_label = $title;
				}
			}

			if ( ! $aria_label ) {
				$aria_label = hp\get_array_value( $this->get_link_options(), $item['link'], esc_attr__( 'Menu item', 'action-bar-for-hivepress' ) );
			}

			// Announce the unread count to assistive technology, since the anchor aria-label hides the badge text.
			if ( $item['badge'] ) {
				$aria_badge_count = absint( hp\get_array_value( $item, 'badge_count' ) );

				if ( $aria_badge_count ) {
					/* translators: 1: item name, 2: number of unread items. */
					$aria_label = sprintf( _x( '%1$s, %2$s unread', 'action bar item', 'action-bar-for-hivepress' ), $aria_label, number_format_i18n( $aria_badge_count ) );
				}
			}

			/*
			 * The badge source is written onto the item so a script can find the badges it is
			 * entitled to update. Notifications for HivePress keeps its count live as it polls, and
			 * without this it would have to guess which item to write to - and would sooner or later
			 * overwrite an unread-messages badge with a notification count.
			 */
			$badge_attribute = $item['badge'] ? ' data-badge="' . esc_attr( $item['badge'] ) . '"' : '';

			// Render item.
			$output .= '<a href="' . esc_url( $item['url'] ) . '" class="' . esc_attr( implode( ' ', $item_classes ) ) . '"' . $badge_attribute . ' aria-label="' . esc_attr( $aria_label ) . '">';

			$output .= '<span class="hp-action-bar__icon"><i class="' . esc_attr( $item['icon'] ) . '" aria-hidden="true"></i>';

			if ( $item['badge'] ) {
				$badge_count = absint( hp\get_array_value( $item, 'badge_count' ) );

				$badge_label = $badge_count > 99 ? number_format_i18n( 99 ) . '+' : number_format_i18n( $badge_count );

				$output .= '<span class="hp-action-bar__badge"' . ( $badge_count ? '' : ' hidden' ) . '>' . esc_html( $badge_label ) . '</span>';
			}

			$output .= '</span>';

			if ( $item['label'] ) {
				$output .= '<span class="hp-action-bar__label">' . esc_html( $item['label'] ) . '</span>';
			}

			$output .= '</a>';
		}

		$output .= '</nav>';

		// Every value interpolated into $output above was escaped as it was appended: esc_url() on the
		// href, esc_attr() on the classes, icon and aria-label, and esc_html() on the label and badge
		// text. The sniff cannot see that across statements, so the whole string is echoed once here.
		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
