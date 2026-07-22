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
 */
final class Action_Bar extends Component {

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
		$defaults = [
			'user'   => [
				[
					'link'  => 'home',
					'icon'  => 'home',
					'label' => esc_html__( 'Home', 'action-bar-for-hivepress' ),
				],

				[
					'link'  => 'listings',
					'icon'  => 'search',
					'label' => esc_html__( 'Browse', 'action-bar-for-hivepress' ),
				],

				[
					'link'  => 'account',
					'icon'  => 'user',
					'label' => esc_html__( 'Account', 'action-bar-for-hivepress' ),
					'badge' => true,
				],
			],

			'vendor' => [
				[
					'link'  => 'home',
					'icon'  => 'home',
					'label' => esc_html__( 'Home', 'action-bar-for-hivepress' ),
				],

				[
					'link'  => 'listing_submit',
					'icon'  => 'plus',
					'label' => esc_html__( 'Add listing', 'action-bar-for-hivepress' ),
					'style' => 'prominent',
				],

				[
					'link'  => 'account',
					'icon'  => 'user',
					'label' => esc_html__( 'Account', 'action-bar-for-hivepress' ),
					'badge' => true,
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
		}

		return $options;
	}

	/**
	 * Gets the extension URL.
	 *
	 * @return string
	 */
	protected function get_extension_url() {
		return (string) hivepress()->get_url( 'action_bar_for_hivepress' ); // @phpstan-ignore method.notFound (get_url() is provided by the HivePress core magic method.)
	}

	/**
	 * Gets the extension version.
	 *
	 * @return string
	 */
	protected function get_extension_version() {
		return (string) hivepress()->get_version( 'action_bar_for_hivepress' ); // @phpstan-ignore method.notFound (get_version() is provided by the HivePress core magic method.)
	}

	/**
	 * Gets the extension path.
	 *
	 * @return string
	 */
	protected function get_extension_path() {
		return (string) hivepress()->get_path( 'action_bar_for_hivepress' ); // @phpstan-ignore method.notFound (get_path() is provided by the HivePress core magic method.)
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
	 * @param bool   $default Default value.
	 * @return bool
	 */
	protected function is_setting_enabled( $name, $default = false ) {
		$value = get_option( 'hp_action_bar_' . $name, null );

		if ( null === $value ) {
			return $default;
		}

		return (bool) $value;
	}

	/**
	 * Gets a colour setting value.
	 *
	 * @param string $name Colour name.
	 * @param string $default Default value.
	 * @return string
	 */
	protected function get_color( $name, $default ) {
		$color = sanitize_hex_color( (string) get_option( 'hp_action_bar_color_' . $name ) );

		return $color ? $color : $default;
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
				$is_vendor = (bool) \HivePress\Models\Vendor::query()->filter( // @phpstan-ignore staticMethod.notFound (query() is provided by the HivePress model magic method.)
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
	public function get_route_link_options() {
		$options = [];

		// Set candidate routes.
		$candidates = [
			'requests_view_page'              => esc_html__( 'Requests', 'action-bar-for-hivepress' ),
			'request_submit_page'             => esc_html__( 'Post a request', 'action-bar-for-hivepress' ),
			'membership_plans_view_page'      => esc_html__( 'Select plan', 'action-bar-for-hivepress' ),
			'vendor_register_page'            => esc_html__( 'Become a vendor', 'action-bar-for-hivepress' ),
			'listings_edit_page'              => esc_html__( 'My listings', 'action-bar-for-hivepress' ),
			'requests_edit_page'              => esc_html__( 'My requests', 'action-bar-for-hivepress' ),
			'offers_view_page'                => esc_html__( 'Offers', 'action-bar-for-hivepress' ),
			'bookings_view_page'              => esc_html__( 'Bookings', 'action-bar-for-hivepress' ),
			'search_alerts_view_page'         => esc_html__( 'Searches', 'action-bar-for-hivepress' ),
			'memberships_view_page'           => esc_html__( 'Memberships', 'action-bar-for-hivepress' ),
			'user_listing_packages_view_page' => esc_html__( 'Packages', 'action-bar-for-hivepress' ),
			'vendor_dashboard_page'           => esc_html__( 'Dashboard', 'action-bar-for-hivepress' ),
			'vendor_calendar_page'            => esc_html__( 'Calendar', 'action-bar-for-hivepress' ),
			'orders_edit_page'                => esc_html__( 'Received orders', 'action-bar-for-hivepress' ),
			'payouts_view_page'               => esc_html__( 'Payouts', 'action-bar-for-hivepress' ),
			'user_logout_page'                => esc_html__( 'Sign out', 'action-bar-for-hivepress' ),
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
				'post_type'   => 'page',
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			]
		);

		foreach ( $pages as $page ) {
			$options[ 'page_' . $page->ID ] = $page->post_title;
		}

		return $options;
	}

	/**
	 * Gets a route URL.
	 *
	 * @param string $name Route name.
	 * @return string
	 */
	protected function get_route_url( $name ) {
		return (string) hivepress()->router->get_url( $name ); // @phpstan-ignore property.notFound (Component access is provided by the HivePress core magic method.)
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
				if ( 0 === strpos( $link, 'route_' ) ) {
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

		// Get item rows.
		$rows = get_option( 'hp_action_bar_' . $bar . '_items', null );

		if ( is_null( $rows ) ) {
			$rows = $this->migrate_legacy_items( $bar );
		}

		if ( is_null( $rows ) ) {
			$rows = $this->get_item_defaults( $bar );
		}

		$rows = array_slice( array_filter( (array) $rows, 'is_array' ), 0, 5 );

		$items = [];

		foreach ( $rows as $row ) {

			// Get item link.
			$link = hp\get_array_value( $row, 'link' );

			if ( ! $link || ( ! isset( $this->get_link_options()[ $link ] ) && 0 !== strpos( (string) $link, 'page_' ) && 0 !== strpos( (string) $link, 'route_' ) ) ) {
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

			// Check item badge.
			$badge = $this->is_setting_enabled( 'enable_badge', true ) && hp\get_array_value( $row, 'badge' );

			// Get item badge count.
			$badge_count = 0;

			if ( $badge ) {
				$request = hivepress()->request; // @phpstan-ignore property.notFound (Component access is provided by the HivePress core magic method.)

				if ( 'messages' === $link ) {
					$badge_count = absint( $request->get_context( 'message_unread_count' ) );
				} else {
					$badge_count = absint( $request->get_context( 'notice_count' ) );
				}
			}

			$items[] = [
				'link'  => $link,
				'url'   => $url,
				'icon'  => $icon,
				'label' => $label,
				'style' => $style,
				'badge' => $badge,

				'badge_count' => $badge_count,
			];
		}

		/**
		 * Filters the action bar items.
		 *
		 * @hook hivepress/v1/action_bar/items
		 * @param array $items Item arguments.
		 * @param string $bar Bar name.
		 * @return array Item arguments.
		 */
		$this->items = (array) apply_filters( 'hivepress/v1/action_bar/items', $items, $bar );

		return $this->items;
	}

	/**
	 * Migrates item settings if required.
	 *
	 * @return void
	 */
	public function maybe_migrate_items() {
		foreach ( [ 'user', 'vendor' ] as $bar ) {
			if ( is_null( get_option( 'hp_action_bar_' . $bar . '_items', null ) ) ) {
				$this->migrate_legacy_items( $bar );
			}

			$this->normalize_items( $bar );
		}
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
				$row['badge'] = '1';

				$rows[ $index ] = $row;

				$changed = true;
			}
		}

		if ( $changed ) {
			update_option( 'hp_action_bar_' . $bar . '_items', $rows );
		}
	}

	/**
	 * Migrates item settings from the beta versions.
	 *
	 * @param string $bar Bar name.
	 * @return array<int, array<string, string>>|null
	 */
	protected function migrate_legacy_items( $bar ) {
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
		$visible = $this->is_setting_enabled( 'enable_mobile', true ) || $this->is_setting_enabled( 'enable_tablet' );

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
		 * @param bool $visible Visibility flag.
		 * @return bool Visibility flag.
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

		// Get height.
		$height = absint( get_option( 'hp_action_bar_height' ) );

		if ( $height < 44 || $height > 120 ) {
			$height = 56;
		}

		$styles = '.hp-action-bar{';

		// Get label size.
		$label_size = absint( get_option( 'hp_action_bar_label_size' ) );

		if ( $label_size < 9 || $label_size > 16 ) {
			$label_size = 11;
		}

		// Get label weight.
		$label_weight = absint( get_option( 'hp_action_bar_label_weight' ) );

		if ( ! in_array( $label_weight, [ 400, 500, 600, 700 ], true ) ) {
			$label_weight = 500;
		}

		$styles .= '--hp-action-bar-height:' . $height . 'px;';

		$styles .= '--hp-action-bar-label-size:' . $label_size . 'px;--hp-action-bar-label-weight:' . $label_weight . ';';

		foreach ( $colors as $name => $color ) {
			$styles .= '--hp-action-bar-' . $name . ':' . $color . ';';
		}

		$styles .= '}';

		/**
		 * Filters the responsive breakpoints.
		 *
		 * @hook hivepress/v1/action_bar/breakpoints
		 * @param array $breakpoints Breakpoint values in pixels.
		 * @return array Breakpoint values in pixels.
		 */
		$breakpoints = apply_filters(
			'hivepress/v1/action_bar/breakpoints',
			[
				'mobile_max' => 767,
				'tablet_min' => 768,
				'tablet_max' => 1024,
			]
		);

		// Set display styles.
		$display = '.hp-action-bar{display:flex;}body.hp-action-bar-visible{padding-bottom:calc(' . ( $height + 12 ) . 'px + env(safe-area-inset-bottom, 0px));}';

		if ( $this->is_setting_enabled( 'enable_mobile', true ) ) {
			$styles .= '@media (max-width:' . absint( hp\get_array_value( $breakpoints, 'mobile_max', 767 ) ) . 'px){' . $display . '}';
		}

		if ( $this->is_setting_enabled( 'enable_tablet' ) ) {
			$styles .= '@media (min-width:' . absint( hp\get_array_value( $breakpoints, 'tablet_min', 768 ) ) . 'px) and (max-width:' . absint( hp\get_array_value( $breakpoints, 'tablet_max', 1024 ) ) . 'px){' . $display . '}';
		}

		return $styles;
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'hp_settings' !== hp\get_array_value( $_GET, 'page' ) ) {
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
				$title = hp\get_array_value( (array) hivepress()->router->get_route( substr( $item['link'], 6 ) ), 'title' ); // @phpstan-ignore property.notFound (Component access is provided by the HivePress core magic method.)

				if ( is_string( $title ) && $title ) {
					$aria_label = $title;
				}
			}

			if ( ! $aria_label ) {
				$aria_label = hp\get_array_value( $this->get_link_options(), $item['link'], esc_html__( 'Menu item', 'action-bar-for-hivepress' ) );
			}

			// Render item.
			$output .= '<a href="' . esc_url( $item['url'] ) . '" class="' . esc_attr( implode( ' ', $item_classes ) ) . '" aria-label="' . esc_attr( $aria_label ) . '">';

			$output .= '<span class="hp-action-bar__icon"><i class="' . esc_attr( $item['icon'] ) . '" aria-hidden="true"></i>';

			if ( $item['badge'] ) {
				$badge_count = absint( hp\get_array_value( $item, 'badge_count' ) );

				$output .= '<span class="hp-action-bar__badge"' . ( $badge_count ? '' : ' hidden' ) . '>' . esc_html( $badge_count > 99 ? '99+' : (string) $badge_count ) . '</span>';
			}

			$output .= '</span>';

			if ( $item['label'] ) {
				$output .= '<span class="hp-action-bar__label">' . esc_html( $item['label'] ) . '</span>';
			}

			$output .= '</a>';
		}

		$output .= '</nav>';

		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

}
