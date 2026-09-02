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

			// The live preview panel. Priority 20, after HivePress has registered the tab's own
			// sections, so the panel can be moved to the front of the list it is already in.
			add_action( 'admin_init', [ $this, 'register_preview_section' ], 20 );
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
			'guest'  => [
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
					'link'  => 'auth_modal',
					'icon'  => 'sign-in-alt',
					'label' => __( 'Sign in', 'action-bar-for-hivepress' ),
				],
			],

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

			// The logged-in user's public profile: their vendor page when they have one, otherwise
			// the core /user/ page when profile display is enabled. Logged-out visitors never see it.
			'my_profile'     => esc_html__( 'My profile', 'action-bar-for-hivepress' ),

			// Opens HivePress's own sign-in pop-up for logged-out visitors instead of leaving the
			// page. Hidden from logged-in users, who have nothing to sign in to.
			'auth_modal'     => esc_html__( 'Sign in pop-up', 'action-bar-for-hivepress' ),
			'messages'       => esc_html__( 'Messages', 'action-bar-for-hivepress' ),
			'favorites'      => esc_html__( 'Favourites', 'action-bar-for-hivepress' ),
			'custom'         => esc_html__( 'Custom URL', 'action-bar-for-hivepress' ),
		];

		// The Notifications for HivePress bell, offered only while that plugin is active so the
		// choice can never be a dead button. A stored value survives deactivation through
		// add_stored_item_options(), it just is not offered fresh.
		if ( $this->get_notification_component() ) {
			$options['notification_bell'] = esc_html__( 'Notifications bell', 'action-bar-for-hivepress' );
		}

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

		/*
		 * Only offer the combined counter while at least one extension that feeds it is active.
		 * Messages, Bookings and Marketplace are the three that add into notice_count, so with all
		 * of them away the option would sit on the screen showing zero for ever. Evaluated here at
		 * runtime, every time the settings config is built, so activation order never matters and
		 * nothing about extension state is ever stored.
		 */
		if ( ! hivepress()->get_version( 'messages' ) && ! hivepress()->get_version( 'bookings' ) && ! hivepress()->get_version( 'marketplace' ) ) {
			unset( $options['notices'] );
		}

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
	 * Gets the Font Awesome brand icon names the plugin understands.
	 *
	 * Brand glyphs live in the separate "brands" family, so they need `fab` rather than the `fas`
	 * class every other icon gets - `fas fa-stripe` renders an empty box. get_bar_items() consults
	 * this list to emit the right family, and get_icon_options() uses it to label the choices.
	 * Keys are bare Font Awesome names, valid in versions 5 through 7.
	 *
	 * @return array<int, string>
	 */
	public function get_brand_icons() {
		return [
			'airbnb',
			'amazon',
			'android',
			'app-store',
			'apple',
			'apple-pay',
			'behance',
			'bitcoin',
			'bluesky',
			'btc',
			'cc-amex',
			'cc-apple-pay',
			'cc-diners-club',
			'cc-discover',
			'cc-jcb',
			'cc-mastercard',
			'cc-paypal',
			'cc-stripe',
			'cc-visa',
			'discord',
			'dribbble',
			'ebay',
			'ethereum',
			'etsy',
			'facebook',
			'facebook-f',
			'facebook-messenger',
			'github',
			'google',
			'google-pay',
			'google-play',
			'instagram',
			'kickstarter',
			'linkedin',
			'linkedin-in',
			'mastodon',
			'medium',
			'microsoft',
			'patreon',
			'paypal',
			'pinterest',
			'reddit',
			'shopify',
			'skype',
			'slack',
			'snapchat',
			'soundcloud',
			'spotify',
			'stripe',
			'stripe-s',
			'telegram',
			'threads',
			'tiktok',
			'tumblr',
			'twitch',
			'twitter',
			'viber',
			'vimeo',
			'whatsapp',
			'windows',
			'wordpress',
			'x-twitter',
			'youtube',
		];
	}

	/**
	 * Gets the icon choices for the item dropdowns.
	 *
	 * HivePress's bundled "icons" list is the Font Awesome 5 solid set, so on its own it offers
	 * neither the names version 6 and 7 introduced nor any brand icon at all. Both are appended
	 * here. The additions render only when the site loads a Font Awesome build that has them:
	 * HivePress itself ships the version 5 solid font only, so version 6/7 names and every brand
	 * glyph need the newer font, which many themes and page builders already load. Brand entries
	 * are labelled so an admin can tell them apart, and the whole list is re-sorted so additions
	 * sit alphabetically among the originals rather than in a clump at the end.
	 *
	 * @return array<string, string>
	 */
	public function get_icon_options() {
		// Every Font Awesome 7.1.0 Free icon, brands included, from the shared
		// FAFH library rather than core's list plus the two arrays below.
		// \FAFH::choices() is already sorted by label, and its keys are canonical
		// FA7 names, so a value saved under an older FA5 name still resolves
		// when it is rendered.
		if ( class_exists( 'FAFH' ) ) {
			return \FAFH::choices();
		}

		// Fallback for a site where the library failed to load: exactly the
		// pre-FAFH list, rather than silently losing the brand and FA6/7 names.
		$options = (array) hivepress()->get_config( 'icons' );

		// Names added in Font Awesome 6 and 7 (solid family). Bare names, like the bundled list.
		$additions = [
			'arrow-right-from-bracket',
			'arrow-right-to-bracket',
			'arrow-up-right-from-square',
			'bag-shopping',
			'basket-shopping',
			'bars-staggered',
			'bell-concierge',
			'bolt-lightning',
			'book-open-reader',
			'box-archive',
			'burger',
			'cake-candles',
			'calendar-days',
			'cart-shopping',
			'chart-column',
			'chart-simple',
			'circle-check',
			'circle-dollar-to-slot',
			'circle-info',
			'circle-question',
			'circle-user',
			'circle-xmark',
			'clock-rotate-left',
			'comment-sms',
			'envelope-circle-check',
			'face-smile',
			'gauge',
			'gauge-high',
			'gear',
			'gears',
			'hand-holding-dollar',
			'hands-clapping',
			'heart-circle-check',
			'house',
			'house-chimney',
			'image-portrait',
			'list-check',
			'location-dot',
			'magnifying-glass',
			'magnifying-glass-location',
			'map-location-dot',
			'message',
			'mobile-screen',
			'money-bill-transfer',
			'money-check-dollar',
			'pen-to-square',
			'rectangle-list',
			'right-from-bracket',
			'right-to-bracket',
			'shield-halved',
			'shop',
			'sliders',
			'square-plus',
			'star-half-stroke',
			'table-cells',
			'ticket-simple',
			'truck-fast',
			'user-gear',
			'user-large',
			'users-gear',
			'wand-magic-sparkles',
		];

		foreach ( $additions as $name ) {
			if ( ! isset( $options[ $name ] ) ) {
				$options[ $name ] = $name;
			}
		}

		foreach ( $this->get_brand_icons() as $name ) {
			if ( ! isset( $options[ $name ] ) ) {
				/* translators: %s: icon name. */
				$options[ $name ] = sprintf( esc_html__( '%s (brand)', 'action-bar-for-hivepress' ), $name );
			}
		}

		ksort( $options );

		return $options;
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

		// The bell keeps its name while Notifications for HivePress is away, so the stored choice
		// survives a temporary deactivation of that plugin exactly as the section promises.
		if ( 'notification_bell' === $link ) {
			return esc_html__( 'Notifications bell', 'action-bar-for-hivepress' );
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

			case 'my_profile':
				if ( is_user_logged_in() ) {

					// The vendor page when the user has one, because that is the profile their
					// customers see. Vendor IDs are post IDs, so the permalink is the page.
					$vendor_id = 0;

					if ( class_exists( '\HivePress\Models\Vendor' ) ) {
						$vendor_id = (int) \HivePress\Models\Vendor::query()->filter(
							[
								'status' => 'publish',
								'user'   => get_current_user_id(),
							]
						)->get_first_id();
					}

					if ( $vendor_id ) {
						$url = (string) get_permalink( $vendor_id );
					} elseif ( get_option( 'hp_user_enable_display' ) ) {

						// Core's public /user/ page, which exists only while profile display is
						// switched on under HivePress, Settings, Users. With it off the item is
						// dropped rather than pointing somewhere that redirects away.
						$url = (string) hivepress()->router->get_url( 'user_view_page', [ 'username' => wp_get_current_user()->user_login ] );
					}
				}

				break;

			case 'auth_modal':
				// The real login page, so the item works with scripting off or on a theme with no
				// footer modals. The frontend script upgrades the click to open HivePress's own
				// #user_login_modal pop-up, the same way core's "Sign In" menu link does. Logged-in
				// users get no URL, which drops the item from their bar.
				if ( ! is_user_logged_in() ) {
					$url = $this->get_route_url( 'user_login_page' );
				}

				break;

			case 'notification_bell':
				// The bell needs its plugin and a signed-in user; without either the item is dropped.
				if ( is_user_logged_in() && $this->get_notification_component() ) {
					$url = $this->get_route_url( 'notifications_view_page' );
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

		// Get bar name. Logged-out visitors get their own bar only once it is switched on, so a site
		// that never touches the new setting behaves exactly as before: everyone sees the User Bar
		// unless they are a vendor with the vendor bar enabled.
		$bar = 'user';

		if ( ! is_user_logged_in() ) {
			if ( $this->is_setting_enabled( 'enable_guest_bar' ) ) {
				$bar = 'guest';
			}
		} elseif ( $this->is_setting_enabled( 'enable_vendor_bar' ) && $this->is_vendor() ) {
			$bar = 'vendor';
		}

		$items = $this->get_bar_items( $bar );

		// Removing every row from a special bar stores an empty option rather than no option, which would
		// otherwise leave those visitors with no navigation at all, so an empty vendor or logged-out bar
		// falls back to the items everyone else sees.
		if ( ! $items && 'user' !== $bar ) {
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
					'bell'        => false,
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

		$found_bell = false;

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

			// The bell item follows the bell's own icon setting when no icon is picked on the row,
			// so the bar and the header show the same bell without configuring it twice.
			if ( ! $icon && 'notification_bell' === $link ) {
				$component = $this->get_notification_component();

				if ( $component && method_exists( $component, 'get_bell_icon' ) ) {
					$icon = strtolower( trim( (string) preg_replace( '/[^a-zA-Z0-9\- ]/', '', (string) $component->get_bell_icon() ) ) );
				}
			}

			if ( $icon && false === strpos( $icon, ' ' ) ) {

				// Brand glyphs live in Font Awesome's separate brands family: `fas fa-stripe` is an
				// empty box, `fab fa-stripe` is the logo. Everything else keeps the solid family.
				$icon = ( in_array( $icon, $this->get_brand_icons(), true ) ? 'fab fa-' : 'fas fa-' ) . $icon;
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

			// Only one bell renders however many rows ask for it: the notifications script manages a
			// single panel, and two would fight over it.
			$bell = 'notification_bell' === $link;

			if ( $bell && $found_bell ) {
				continue;
			}

			// Get item badge source.
			$badge = hp\get_array_value( $row, 'badge' );

			// Rows saved before the badge became a choice of counter store a boolean tick, which used to mean
			// the message count on Messages items and the combined count everywhere else.
			if ( $badge && ! isset( $this->get_badge_sources()[ $badge ] ) ) {
				$badge = 'messages' === $link ? 'messages' : 'notices';
			}

			/*
			 * Safety net for sites that unticked the old "Notification badge" box and have not run
			 * the 1.5.0 migration yet, which happens on the next wp-admin visit. The migration
			 * clears the per-item badges and deletes the option, after which this reads its default
			 * of true and never fires again.
			 */
			if ( ! $this->is_setting_enabled( 'enable_badge', true ) ) {
				$badge = '';
			}

			// The bell carries its own live count, so a second counter on the same button would
			// show the same number twice or, worse, two different numbers.
			if ( $bell ) {
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

			if ( $bell ) {
				$found_bell = true;
			}

			$items[] = [
				'link'        => $link,
				'url'         => $url,
				'icon'        => $icon,
				'label'       => $label,
				'style'       => $style,
				'badge'       => $badge,

				'badge_count' => $badge_count,
				'bell'        => $bell,
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
		// 1.1.0 (boolean badge ticks become a named counter), '2' re-runs only the badge-checkbox
		// retirement added in 1.5.0, '3' is current.
		$migrated = (string) get_option( 'hp_action_bar_migrated' );

		if ( '3' === $migrated ) {
			return;
		}

		if ( '2' !== $migrated ) {
			foreach ( [ 'user', 'vendor' ] as $bar ) {
				if ( ! $migrated && is_null( get_option( 'hp_action_bar_' . $bar . '_items', null ) ) ) {
					$this->migrate_legacy_items( $bar );
				}

				$this->normalize_items( $bar );
			}
		}

		$this->migrate_badge_setting();

		update_option( 'hp_action_bar_migrated', '3' );
	}

	/**
	 * Retires the old "Notification badge" checkbox (1.5.0).
	 *
	 * The checkbox duplicated the per-item Badge dropdown: with badges chosen per item, a second
	 * switch that silences all of them at once is just a way for the two controls to disagree. An
	 * admin who had unticked it meant "no badges anywhere", so that intent is preserved by clearing
	 * the badge choice on every stored row - set to an empty string rather than unset, because
	 * normalize_items() treats a missing badge key on account and message items as pre-1.1.0 data
	 * and would quietly put the badge back. A ticked or never-saved box needs no rewrite: the
	 * dropdowns already say what shows. The option is deleted either way.
	 *
	 * @return void
	 */
	protected function migrate_badge_setting() {
		$value = get_option( 'hp_action_bar_enable_badge', null );

		if ( ! is_null( $value ) && ! $value ) {
			foreach ( [ 'guest', 'user', 'vendor' ] as $bar ) {
				$rows = get_option( 'hp_action_bar_' . $bar . '_items', null );

				if ( ! is_array( $rows ) ) {
					continue;
				}

				$changed = false;

				foreach ( $rows as $index => $row ) {
					if ( is_array( $row ) && hp\get_array_value( $row, 'badge' ) ) {
						$row['badge'] = '';

						$rows[ $index ] = $row;

						$changed = true;
					}
				}

				if ( $changed ) {
					update_option( 'hp_action_bar_' . $bar . '_items', $rows );
				}
			}
		}

		delete_option( 'hp_action_bar_enable_badge' );
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
		$icon_size  = $this->get_number_setting( 'icon_size', 20, 14, 32 );

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
		 *
		 * --hp-action-bar-offset is the height ONLY where the bar is actually on screen, and 0
		 * everywhere else. The height alone is not enough to clear the bar with, because the bar is
		 * displayed per breakpoint (mobile only by default) while the body class is present at every
		 * width: a neighbour reading only the height pushes its own pop-ups up by 56px on a desktop
		 * that has no bar. Read this one, with the height as a nested fallback for an older copy of
		 * this plugin that does not publish it:
		 *
		 *   bottom: calc(1rem + var(--hp-action-bar-offset, var(--hp-action-bar-height, 56px)));
		 */
		$styles = ':root{--hp-action-bar-height:' . $height . 'px;--hp-action-bar-offset:0px;}';

		$styles .= '.hp-action-bar{';

		$styles .= '--hp-action-bar-height:' . $height . 'px;';

		$styles .= '--hp-action-bar-label-size:' . $label_size . 'px;--hp-action-bar-label-weight:' . $label_weight . ';';

		$styles .= '--hp-action-bar-icon-size:' . $icon_size . 'px;';

		foreach ( $colors as $name => $color ) {
			$styles .= '--hp-action-bar-' . $name . ':' . $color . ';';
		}

		/*
		 * The optional icon backdrop. No default: left unset, icons sit straight on the bar as they
		 * always have, so the variable is only emitted for a saved, valid colour. The stylesheet
		 * falls back to transparent.
		 */
		$icon_background = sanitize_hex_color( (string) get_option( 'hp_action_bar_color_icon_background' ) );

		if ( $icon_background ) {
			$styles .= '--hp-action-bar-icon-background:' . $icon_background . ';';
		}

		/*
		 * Icon weight. Font Awesome's free solid face has one weight, so "bolder" glyphs are drawn
		 * by stroking the glyph outline in its own colour: currentColor keeps the stroke in step
		 * with the icon and active colours, and paint-order keeps the fill on top so the shape
		 * never hollows out. Emitted only off the default, leaving untouched sites byte-identical.
		 */
		$icon_weight = (string) get_option( 'hp_action_bar_icon_weight', 'normal' );

		$icon_strokes = [
			'semibold' => '0.3px',
			'bold'     => '0.5px',
		];

		if ( isset( $icon_strokes[ $icon_weight ] ) ) {
			$styles .= '--hp-action-bar-icon-stroke:' . $icon_strokes[ $icon_weight ] . ';';
		}

		/*
		 * Corner radii, one per corner rather than a single linked value, so a bar can be rounded
		 * only where it meets the page. Emitted in CSS's own order (top-left, top-right,
		 * bottom-right, bottom-left) and only when a corner is actually rounded.
		 */
		$radii = [
			$this->get_number_setting( 'radius_top_left', 0, 0, 40 ),
			$this->get_number_setting( 'radius_top_right', 0, 0, 40 ),
			$this->get_number_setting( 'radius_bottom_right', 0, 0, 40 ),
			$this->get_number_setting( 'radius_bottom_left', 0, 0, 40 ),
		];

		if ( array_sum( $radii ) > 0 ) {
			$styles .= 'border-radius:' . implode(
				' ',
				array_map(
					function( $radius ) {
						return $radius . 'px';
					},
					$radii
				)
			) . ';';
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

		// Set display styles. The offset is published in the same breakpoints that display the bar,
		// so it answers "is the bar in the way here?" rather than "is the bar installed?".
		$display = '.hp-action-bar{display:flex;}:root{--hp-action-bar-offset:calc(' . $height . 'px + env(safe-area-inset-bottom, 0px));}body.hp-action-bar-visible{padding-bottom:calc(' . ( $height + 12 ) . 'px + env(safe-area-inset-bottom, 0px));}';

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
			$desktop = $display . '.hp-action-bar{justify-content:center;}.hp-action-bar .hp-action-bar__item,.hp-action-bar .hp-action-bar__bell{flex:0 1 auto;padding-left:2rem;padding-right:2rem;}.hp-action-bar .hp-action-bar__bell .hp-action-bar__item{padding-left:0;padding-right:0;}';

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
	 * Builds the markup for one action-bar icon.
	 *
	 * Prefers FAFH's inline SVG: a few hundred bytes instead of a ~234 KB
	 * stylesheet and webfont, and no `fas`/`fa-solid` class for the Font
	 * Awesome 5 that HivePress core enqueues to match and draw a second time
	 * through ::before. Falls back to the class markup if the library is
	 * missing, so a broken include degrades to the previous behaviour.
	 *
	 * @param string $icon Stored icon value, a full class string such as
	 *                     "fas fa-bell" or "fab fa-stripe".
	 * @return string
	 */
	protected function render_icon( $icon ) {
		$icon = (string) $icon;

		if ( '' === $icon ) {
			return '';
		}

		if ( class_exists( 'FAFH' ) ) {
			// \FAFH::svg() parses the class string itself, in both the version-5
			// and version-6/7 spellings, so the stored value needs no migration.
			$svg = \FAFH::svg( $icon );

			if ( $svg ) {
				return '<i class="fafh-icon" aria-hidden="true">' . $svg . '</i>';
			}
		}

		$this->enqueue_font_awesome();

		return '<i class="' . esc_attr( $icon ) . '" aria-hidden="true"></i>';
	}

	/**
	 * Enqueues the Font Awesome webfont, for wp-admin icon pickers.
	 *
	 * Delegates to FAFH, which owns the bundled copy. The front end does NOT want
	 * this: render_icon() draws inline SVG there, which is the whole point of the
	 * library. The body below is only the fallback for a site where FAFH failed to
	 * load, and it keeps the shared handle so one copy still serves every sibling.
	 *
	 * @return void
	 */
	protected function enqueue_font_awesome() {
		// The webfont now lives inside FAFH and is only wanted in wp-admin, for
		// the picker previews; the front end draws inline SVG. FAFH also loads
		// the sheet that makes brand icons preview correctly, which core cannot
		// do on its own (its option template hardcodes the solid family).
		if ( class_exists( 'FAFH' ) ) {
			\FAFH::enqueue_admin();
		}
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

		// The bar always renders configurable icons, so the full icon set loads with it.
		$this->enqueue_font_awesome();

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
			$this->get_extension_url() . '/assets/js/frontend.js',
			[],
			$this->get_asset_version( 'assets/js/frontend.js' ),
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
	 * Whether the settings tab currently being rendered carries this plugin's own fields.
	 *
	 * Answered from the fields HivePress has actually registered for this request, never from
	 * $_GET['tab']. The address cannot be trusted: get_settings_tab() falls back to the FIRST tab
	 * whenever "tab" is absent (reference/hivepress/includes/components/class-admin.php:607-622),
	 * and the bare admin.php?page=hp_settings link in the HivePress menu is exactly that case, so
	 * reading the address would miss this plugin's own tab on any site where it sorts first - which
	 * is what the 'action_bar' === $tab test this replaced in 1.5.5 did.
	 *
	 * register_settings() builds the sections and fields for one tab only and calls
	 * add_settings_field() with the prefixed option name (class-admin.php:287-325), so
	 * $wp_settings_fields['hp_settings'] holds hp_action_bar_* keys on this plugin's tab and on no
	 * other. It is the server-side twin of the [name^="hp_action_bar_"] gate the script uses.
	 *
	 * Timing is the only thing to get right. HivePress registers on admin_init priority 10, and
	 * this runs from admin_enqueue_scripts, which wp-admin fires later, from admin-header.php.
	 * Called any earlier it would return false and this tab would silently lose its assets, so
	 * re-test the tab if the hook is ever moved.
	 *
	 * Full rule: resources/hivepress-settings.md, "The tab IS knowable server-side: ask the
	 * registered fields" (2026-08-30).
	 *
	 * @return bool
	 */
	protected function is_settings_tab() {
		if ( ! isset( $GLOBALS['wp_settings_fields']['hp_settings'] ) || ! is_array( $GLOBALS['wp_settings_fields']['hp_settings'] ) ) {
			return false;
		}

		foreach ( $GLOBALS['wp_settings_fields']['hp_settings'] as $hpab_section ) {
			foreach ( array_keys( (array) $hpab_section ) as $hpab_field ) {
				if ( 0 === strpos( (string) $hpab_field, 'hp_action_bar_' ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Enqueues the backend assets.
	 *
	 * @return void
	 */
	public function enqueue_backend_assets() {

		// No nonce applies here: this value only decides whether this screen is the one that needs
		// our assets, it is never used to write anything, and it is run through sanitize_key().
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = sanitize_key( wp_unslash( (string) hp\get_array_value( $_GET, 'page' ) ) );

		if ( 'hp_settings' !== $page ) {
			return;
		}

		if ( ! $this->is_settings_tab() ) {
			return;
		}

		// Enqueue styles. The full Font Awesome build makes the icon dropdown previews match what
		// the front end will draw, version 6/7 names and brands included.
		wp_enqueue_style( 'wp-color-picker' );

		$this->enqueue_font_awesome();

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

		/*
		 * The shared chrome's wording.
		 *
		 * These three strings are deliberately identical in every sibling plugin: an owner moving
		 * between two of these tabs must find the same controls saying the same things
		 * (resources/hivepress-settings.md, "The settings anchor nav"). Until 1.5.5 the nav had no
		 * visible label at all, only a hardcoded English "Sections" aria-label that no translator
		 * could reach. The colon in the first one is part of the wording: it reads as a lead-in to
		 * the links that follow it, not as a heading over them.
		 */
		wp_localize_script(
			'hivepress-action-bar-backend',
			'hpabBackendData',
			[
				'labels' => [
					'jumpTo'    => esc_html__( 'Jump to a section:', 'action-bar-for-hivepress' ),
					'save'      => esc_html__( 'Save Changes', 'action-bar-for-hivepress' ),
					'backToTop' => esc_html__( 'Back to top', 'action-bar-for-hivepress' ),
					'newItem'   => esc_html__( 'New item', 'action-bar-for-hivepress' ),
					'collapse'  => esc_html__( 'Collapse', 'action-bar-for-hivepress' ),
					'expand'    => esc_html__( 'Expand', 'action-bar-for-hivepress' ),
				],
			]
		);

		/*
		 * The live preview draws the bar with the FRONT-END stylesheet, on purpose. An imitation
		 * written for the admin would drift from the real thing the first time a front-end rule
		 * changed; the real sheet cannot. backend.min.css undoes only the `position: fixed` so the
		 * bar sits inside its stage.
		 */
		wp_enqueue_style(
			'hivepress-action-bar-frontend',
			$this->get_extension_url() . '/assets/css/frontend.min.css',
			[],
			$this->get_asset_version( 'assets/css/frontend.min.css' )
		);

		wp_enqueue_script(
			'hivepress-action-bar-preview',
			$this->get_extension_url() . '/assets/js/admin-preview.js',
			[ 'jquery', 'wp-color-picker', 'hivepress-action-bar-backend' ],
			$this->get_asset_version( 'assets/js/admin-preview.js' ),
			true
		);

		wp_localize_script(
			'hivepress-action-bar-preview',
			'hpabPreviewData',
			[
				// Illustrative, not read from the site: a badge with nothing in it would show
				// nothing, and the owner is here to see what the badge colours do.
				'badgeCount' => 3,

				'labels'     => [
					'hiddenOnMobile'  => esc_html__( 'The bar is switched off on mobile. Tick Mobile under Display to show it.', 'action-bar-for-hivepress' ),
					'hiddenOnTablet'  => esc_html__( 'The bar is switched off on tablets. Tick Tablet under Display to show it.', 'action-bar-for-hivepress' ),
					'hiddenOnDesktop' => esc_html__( 'The bar is switched off on desktop. Tick Desktop under Display to show it.', 'action-bar-for-hivepress' ),
				],
			]
		);
	}

	/**
	 * Registers the live preview panel on this plugin's settings tab.
	 *
	 * Modelled line for line on Account Menu Enhancer's register_preview_section(): HivePress
	 * renders a tab through do_settings_sections(), so a panel is a settings section with a render
	 * callback, and moving it to the front of the list is a plain reorder of the array WordPress
	 * reads later in the same request.
	 *
	 * @return void
	 */
	public function register_preview_section() {
		global $pagenow;

		// HivePress registers its settings on options.php as well, so that a save has the field
		// list to validate against. Nothing is rendered on that request.
		if ( 'admin.php' !== $pagenow ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'hp_settings' !== sanitize_key( (string) hp\get_array_value( $_GET, 'page' ) ) ) {
			return;
		}

		if ( ! $this->is_settings_tab() ) {
			return;
		}

		add_settings_section( 'hpab_preview', '', [ $this, 'render_preview_section' ], 'hp_settings' );

		if ( ! isset( $GLOBALS['wp_settings_sections']['hp_settings']['hpab_preview'] ) ) {
			return;
		}

		$sections = $GLOBALS['wp_settings_sections']['hp_settings'];
		$preview  = $sections['hpab_preview'];

		unset( $sections['hpab_preview'] );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reordering our own entry in the settings section list, which is the documented way sections are held and has no setter.
		$GLOBALS['wp_settings_sections']['hp_settings'] = array_merge( [ 'hpab_preview' => $preview ], $sections );
	}

	/**
	 * Renders the live preview panel: one stage per bar, filled by admin-preview.js.
	 *
	 * @return void
	 */
	public function render_preview_section() {
		echo '<div class="hpab-preview">';

		// The resize handle. A separator role with a value, so a screen reader can operate it with
		// the arrow keys the script listens for; the pointer does the rest.
		echo '<div class="hpab-preview__resizer" role="separator" aria-orientation="vertical" tabindex="0" aria-label="' . esc_attr__( 'Resize the preview: drag, or use the arrow keys. Double-click to reset.', 'action-bar-for-hivepress' ) . '" title="' . esc_attr__( 'Drag to resize. Double-click to reset.', 'action-bar-for-hivepress' ) . '"></div>';

		echo '<div class="hpab-preview__inner">';
		echo '<h2 class="hpab-preview__title">' . esc_html__( 'Live preview', 'action-bar-for-hivepress' ) . '</h2>';

		// The side panel shows the phone. The wider viewports open a slide-in dialog, because a
		// 1280px bar scaled into a 320px column shows nothing anyone can read.
		echo '<div class="hpab-preview__modes" role="group" aria-label="' . esc_attr__( 'Preview size', 'action-bar-for-hivepress' ) . '">';
		echo '<span class="hpab-preview__mode hpab-preview__mode--current" aria-current="true">' . esc_html__( 'Mobile', 'action-bar-for-hivepress' ) . '</span>';
		echo '<button type="button" class="hpab-preview__mode" data-hpab-open="tablet">' . esc_html__( 'Tablet', 'action-bar-for-hivepress' ) . '</button>';
		echo '<button type="button" class="hpab-preview__mode" data-hpab-open="desktop">' . esc_html__( 'Desktop', 'action-bar-for-hivepress' ) . '</button>';
		echo '</div>';

		$this->render_preview_panel( 'guest', esc_html__( 'Logged-out bar', 'action-bar-for-hivepress' ) );
		$this->render_preview_panel( 'user', esc_html__( 'User bar', 'action-bar-for-hivepress' ) );
		$this->render_preview_panel( 'vendor', esc_html__( 'Vendor bar', 'action-bar-for-hivepress' ) );

		echo '<p class="description hpab-preview__description">' . esc_html__( 'How each bar will look with the settings on this page, following every change as you make it. The bars are drawn at the width of this panel, so drag its edge to see them wider, or choose Tablet or Desktop to see them at those exact widths. The badge number and the highlighted first item are examples, not live figures. Nothing is stored until you press Save Changes.', 'action-bar-for-hivepress' ) . '</p>';
		echo '</div></div>';

		$this->render_preview_dialog();
	}

	/**
	 * Renders a stage: the device the bar is laid out in, and the note shown instead of it when the
	 * bar is switched off for the viewport being previewed.
	 *
	 * @param string $title Accessible name for the bar.
	 * @return void
	 */
	protected function render_preview_stage( $title ) {
		echo '<div class="hpab-preview__stage">';
		echo '<div class="hpab-preview__device"><nav class="hp-action-bar hpab-preview__bar" aria-label="' . esc_attr( $title ) . '"></nav></div>';
		echo '<p class="hpab-preview__note" hidden></p>';
		echo '</div>';
	}

	/**
	 * Renders the slide-in dialog the tablet and desktop previews open in.
	 *
	 * Printed once, hidden, at the end of the preview section; admin-preview.js fills its stages
	 * from the same form values as the side panel. The shape is Email Studio's preview panel so the
	 * two plugins' previews behave the same way: a backdrop that closes it, a dialog on the right,
	 * a device switch in its head, Escape to close, focus handed back to the button that opened it.
	 *
	 * @return void
	 */
	protected function render_preview_dialog() {
		echo '<div class="hpab-dialog" id="hpab-preview-dialog" hidden>';
		echo '<div class="hpab-dialog__backdrop" data-hpab-close></div>';
		echo '<div class="hpab-dialog__dialog" role="dialog" aria-modal="true" aria-labelledby="hpab-preview-dialog-title" data-device="tablet">';

		echo '<div class="hpab-dialog__head">';
		echo '<div><h2 class="hpab-dialog__title" id="hpab-preview-dialog-title">' . esc_html__( 'Live preview', 'action-bar-for-hivepress' ) . '</h2>';
		echo '<p class="hpab-dialog__subtitle">' . esc_html__( 'Drawn at the real width for this size. The badge number and the highlighted first item are examples.', 'action-bar-for-hivepress' ) . '</p></div>';
		echo '<div class="hpab-dialog__group" role="group" aria-label="' . esc_attr__( 'Preview size', 'action-bar-for-hivepress' ) . '">';
		// Mobile lives in the side panel, so choosing it here closes the dialog - the same switch as
		// the panel's, read the same way, rather than a control that does nothing in this context.
		echo '<button type="button" class="button hpab-dialog__device" data-device="mobile" aria-pressed="false">' . esc_html__( 'Mobile', 'action-bar-for-hivepress' ) . '</button>';
		echo '<button type="button" class="button hpab-dialog__device" data-device="tablet" aria-pressed="false">' . esc_html__( 'Tablet', 'action-bar-for-hivepress' ) . '</button>';
		echo '<button type="button" class="button hpab-dialog__device" data-device="desktop" aria-pressed="false">' . esc_html__( 'Desktop', 'action-bar-for-hivepress' ) . '</button>';
		echo '</div>';
		echo '<button type="button" class="hpab-dialog__close" data-hpab-close aria-label="' . esc_attr__( 'Close preview', 'action-bar-for-hivepress' ) . '"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>';
		echo '</div>';

		echo '<div class="hpab-dialog__stage">';

		foreach ( [
			'guest'  => esc_html__( 'Logged-out bar', 'action-bar-for-hivepress' ),
			'user'   => esc_html__( 'User bar', 'action-bar-for-hivepress' ),
			'vendor' => esc_html__( 'Vendor bar', 'action-bar-for-hivepress' ),
		] as $bar => $title ) {
			echo '<div class="hpab-dialog__panel hpab-preview__panel" data-bar="' . esc_attr( $bar ) . '" data-context="dialog">';
			echo '<h3 class="hpab-dialog__panel-title">' . esc_html( $title ) . '</h3>';
			$this->render_preview_stage( $title );
			echo '</div>';
		}

		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Renders one collapsible preview panel.
	 *
	 * @param string $bar   Bar key: guest, user or vendor.
	 * @param string $title Panel title.
	 * @return void
	 */
	protected function render_preview_panel( $bar, $title ) {
		$id = 'hpab-preview-panel-' . $bar;

		echo '<div class="hpab-preview__panel" data-bar="' . esc_attr( $bar ) . '">';
		echo '<button type="button" class="hpab-preview__header" aria-expanded="true" aria-controls="' . esc_attr( $id ) . '">';
		echo '<span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>';
		echo '<span class="hpab-preview__panel-title">' . esc_html( $title ) . '</span>';
		echo '</button>';
		echo '<div class="hpab-preview__body" id="' . esc_attr( $id ) . '">';
		$this->render_preview_stage( $title );
		echo '</div>';
		echo '</div>';
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
	 * Renders the notifications bell as an action bar item.
	 *
	 * The markup deliberately mirrors what Notifications for HivePress renders in the site header -
	 * the same wrapper, toggle, panel and body classes and the same data-component hooks - so that
	 * plugin's own script runs the bell: it binds the toggle, fetches and draws the dropdown,
	 * keeps the count live and closes on outside clicks, and none of that logic is duplicated
	 * here. This plugin's stylesheet then restyles the toggle as a bar item and opens the panel
	 * ABOVE the bar, anchored to the bell, since a dropdown from a bar glued to the bottom of the
	 * screen would open off-screen.
	 *
	 * The full wrapper is rendered whether or not the header bell is switched on. Notifications
	 * for HivePress initialises every bell instance on the page (each with its own panel, all
	 * sharing one unread count), so the header bell and this one work side by side; 1.5.0's
	 * adopt-the-header-bell dance existed only because that script used to bind a single
	 * instance, and it went with that limitation. Without scripting the toggle is a plain link
	 * to the notifications page.
	 *
	 * @param array<string, mixed> $item Item arguments.
	 * @return string
	 */
	protected function render_bell_item( $item ) {
		$component = $this->get_notification_component();

		if ( ! $component ) {
			return '';
		}

		// Get the unread count, drawn in the same <small> element the header bell uses so the
		// notifications script updates this one too as it polls.
		$count = absint( $component->get_unread_count( get_current_user_id() ) );

		$aria_label = $item['label'] ? $item['label'] : esc_attr__( 'Notifications', 'action-bar-for-hivepress' );

		if ( $count ) {
			/* translators: 1: item name, 2: number of unread items. */
			$aria_label = sprintf( _x( '%1$s, %2$s unread', 'action bar item', 'action-bar-for-hivepress' ), $aria_label, number_format_i18n( $count ) );
		}

		$inner = '<span class="hp-action-bar__icon">' . $this->render_icon( $item['icon'] ) . '</span>';

		if ( $count ) {
			$inner .= '<small>' . esc_html( number_format_i18n( $count ) ) . '</small>';
		}

		if ( $item['label'] ) {
			$inner .= '<span class="hp-action-bar__label">' . esc_html( $item['label'] ) . '</span>';
		}

		$item_classes = 'hp-action-bar__item hp-action-bar__item--' . $item['style'];

		$output = '<div class="hp-action-bar__bell">';

		$output .= '<div class="hp-notification-bell" data-component="notification-bell">';

		$output .= '<a href="' . esc_url( $item['url'] ) . '" class="' . esc_attr( $item_classes ) . ' hp-notification-bell__toggle" aria-haspopup="true" aria-expanded="false" aria-label="' . esc_attr( $aria_label ) . '">' . $inner . '</a>';

		// The panel matches the header bell's own byte for byte below the wrapper, filled in on
		// first open by the notifications script.
		$output .= '<div class="hp-notification-bell__panel" hidden>';
		$output .= '<div class="hp-notification-bell__header"><span>' . esc_html__( 'Notifications', 'action-bar-for-hivepress' ) . '</span>';
		$output .= '<a href="' . esc_url( $item['url'] ) . '">' . esc_html__( 'See all', 'action-bar-for-hivepress' ) . '</a></div>';
		$output .= '<div class="hp-notification-bell__body" data-component="notification-bell-body"><div class="hp-notification-bell__loading">' . esc_html__( 'Loading…', 'action-bar-for-hivepress' ) . '</div></div>';
		$output .= '</div>';

		$output .= '</div>';

		$output .= '</div>';

		return $output;
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

			// The notifications bell is its own control rather than a plain link.
			if ( ! empty( $item['bell'] ) ) {
				$output .= $this->render_bell_item( $item );

				continue;
			}

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

			// Mark the sign-in item so the frontend script can upgrade its click to HivePress's own
			// login pop-up. The href stays the real login page, which is what a visitor without
			// scripting, or on a theme that renders no footer modals, falls back to.
			$modal_attribute = 'auth_modal' === $item['link'] ? ' data-hpab-auth-modal' : '';

			// Render item.
			$output .= '<a href="' . esc_url( $item['url'] ) . '" class="' . esc_attr( implode( ' ', $item_classes ) ) . '"' . $badge_attribute . $modal_attribute . ' aria-label="' . esc_attr( $aria_label ) . '">';

			$output .= '<span class="hp-action-bar__icon">' . $this->render_icon( $item['icon'] );

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
