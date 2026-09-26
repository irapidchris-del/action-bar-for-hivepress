<?php
/**
 * Quick Actions: the save-listing and create-search-alert buttons in the bar.
 *
 * Both are ACTIONS rather than links. The bar already offers "Favourites" and "Searches" as links
 * that take somebody to a page listing what they saved earlier; these two let them save the thing
 * they are looking at without leaving it, which is the whole point of a bar on a phone.
 *
 * Nothing here implements saving or alerting. The save item reuses the Favorites extension's own
 * REST action and its `favorite_ids` request context, and the alert item posts to the Search Alerts
 * extension's own update action with the current search in the query string, so the button in the
 * bar and the control already on the page can never disagree or double up. With neither extension
 * installed this component adds nothing at all.
 *
 * Registered on `init`, never at construction: class_exists() on a HivePress class autoloads it and
 * runs its static init(), which translates, and doing that at plugins_loaded logs "translation
 * loading was triggered too early" on every request of a translated site.
 *
 * @package ActionBar
 */

namespace HivePress\Components;

use HivePress\Models;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Save and search-alert items.
 *
 * @class Hpab_Quick_Actions
 */
final class Hpab_Quick_Actions extends Component {

	/**
	 * The items built for this request, keyed by handle, or null before they are built.
	 *
	 * @var array|null
	 */
	protected $items = null;

	/**
	 * Class constructor.
	 *
	 * @param array $args Component arguments.
	 */
	public function __construct( $args = [] ) {
		if ( ! is_admin() ) {
			add_action( 'init', [ $this, 'register' ], 50 );
		}

		parent::__construct( $args );
	}

	/**
	 * Registers the items once HivePress and its extensions have loaded.
	 */
	public function register() {
		add_filter( 'hivepress/v1/action_bar/items', [ $this, 'add_items' ], 10, 2 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Checks for the Favorites extension.
	 *
	 * @return bool
	 */
	public function has_favorites() {
		return class_exists( '\HivePress\Components\Favorite' );
	}

	/**
	 * Checks for the Search Alerts extension.
	 *
	 * @return bool
	 */
	public function has_search_alerts() {
		return class_exists( '\HivePress\Components\Search_Alert' );
	}

	/**
	 * Reads one of the two on/off settings.
	 *
	 * @param string $name Setting name.
	 * @return bool
	 */
	protected function is_item_enabled( $name ) {
		$value = get_option( 'hp_' . $name, null );

		// An option that has never been saved reads as its default; a stored '' is a real "off"
		// (resources/hivepress-settings.md, the stored-'' trap).
		if ( is_null( $value ) || false === $value ) {
			return true;
		}

		return (bool) $value;
	}

	/**
	 * Adds the items to the bar.
	 *
	 * @param array  $items Item arguments.
	 * @param string $bar Bar name.
	 * @return array
	 */
	public function add_items( $items, $bar ) {
		$items = (array) $items;

		foreach ( $this->get_items() as $item ) {
			$items[] = $item['bar'];
		}

		/**
		 * Filters the quick-action items appended to the bar, after they are built.
		 *
		 * @hook hpab/quick_action_items
		 * @param {array} $items Item arguments, in the bar's own shape.
		 * @param {string} $route Current route name.
		 * @param {string} $bar Bar name.
		 * @return {array} Item arguments.
		 */
		return (array) apply_filters( 'hpab/quick_action_items', $items, (string) hivepress()->router->get_current_route_name(), (string) $bar );
	}

	/**
	 * Builds the items once per request.
	 *
	 * Built once because the enqueue decision (wp_enqueue_scripts) and the filter (wp_footer) must
	 * agree about which items exist.
	 *
	 * @return array
	 */
	public function get_items() {
		if ( ! is_null( $this->items ) ) {
			return $this->items;
		}

		$this->items = $this->build_items( (string) hivepress()->router->get_current_route_name() );

		return $this->items;
	}

	/**
	 * Builds the items for a route.
	 *
	 * Public and route-driven so a runtime check can exercise it without a real HTTP request.
	 *
	 * @param string $route Route name.
	 * @return array
	 */
	public function build_items( $route ) {
		$items = [];

		// Save: the Listing page, through the Favorites extension.
		if ( 'listing_view_page' === $route && $this->is_item_enabled( 'action_bar_item_favorite' ) && $this->has_favorites() ) {
			$item = $this->build_favorite_item( hivepress()->request->get_context( 'listing' ) );

			if ( $item ) {
				$items[ $item['handle'] ] = $item;
			}
		}

		/*
		 * Alert: search result pages, through the Search Alerts extension. The Requests search route
		 * is named too; it is harmless when that extension is not installed. is_search() is the gate
		 * because an alert for "everything" is not worth offering.
		 */
		if ( in_array( $route, [ 'listings_view_page', 'requests_view_page' ], true ) && $this->is_item_enabled( 'action_bar_item_search_alert' ) && $this->has_search_alerts() && is_search() ) {
			$item = $this->build_search_alert_item();

			if ( $item ) {
				$items[ $item['handle'] ] = $item;
			}
		}

		return $items;
	}

	/**
	 * Builds the save item, reusing the Favorites extension's state and REST action so the heart on
	 * the page and this item can never disagree.
	 *
	 * @param mixed $listing Listing object from the request context.
	 * @return array|null
	 */
	protected function build_favorite_item( $listing ) {
		if ( ! $listing instanceof Models\Listing || 'publish' !== $listing->get_status() ) {
			return null;
		}

		$url = hivepress()->router->get_url( 'listing_favorite_action', [ 'listing_id' => $listing->get_id() ] );

		if ( ! $url ) {
			return null;
		}

		$active = is_user_logged_in() && in_array( absint( $listing->get_id() ), array_map( 'absint', (array) hivepress()->request->get_context( 'favorite_ids', [] ) ), true );

		return $this->build_item(
			'hpab-favorite-' . $listing->get_id(),
			$url,
			$active,
			[ esc_html__( 'Save', 'action-bar-for-hivepress' ), esc_html__( 'Saved', 'action-bar-for-hivepress' ) ],
			[ 'far fa-heart', 'fas fa-heart' ]
		);
	}

	/**
	 * Builds the search-alert item.
	 *
	 * Line for line the logic of the extension's own toggle (hivepress-search-alerts/includes/
	 * blocks/class-search-alert-toggle.php:51-96): the current search validated into its filter
	 * form, the non-empty values sorted, the key derived from them, and the POST target the
	 * extension's own update action with the search in its query string. That action creates the
	 * alert or deletes an identical one, and the extension's own hourly cron sends the email.
	 * Nothing here touches the email path.
	 *
	 * @return array|null
	 */
	protected function build_search_alert_item() {
		$search_alert = hivepress()->search_alert;

		if ( ! $search_alert ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the extension's own toggle passes $_GET exactly like this; its filter form validates and sanitises every value, and the alert key is derived from the result, so any difference in input would make this item and the sidebar toggle disagree.
		$form = $search_alert->get_model_form( $_GET );

		if ( ! $form ) {
			return null;
		}

		$params = array_filter(
			$form->get_values(),
			function ( $param ) {
				return ! is_null( $param );
			}
		);

		ksort( $params );

		$key  = (string) $search_alert->get_alert_key( $params );
		$keys = (array) hivepress()->request->get_context( 'search_alert_keys', [] );

		$active = is_user_logged_in() && in_array( $key, $keys, true );

		// The extension caps a user at ten alerts and hides its own toggle at the cap unless this
		// search is already one of them. Offering a button that can only fail would be worse than
		// not offering one.
		if ( is_user_logged_in() && ! $active && count( $keys ) >= 10 ) {
			return null;
		}

		$url = hivepress()->router->get_url( 'search_alert_update_action' );

		if ( ! $url ) {
			return null;
		}

		return $this->build_item(
			'hpab-alert-' . md5( $key ),
			add_query_arg( $params, $url ),
			$active,
			[ esc_html__( 'Alert me', 'action-bar-for-hivepress' ), esc_html__( 'Alert set', 'action-bar-for-hivepress' ) ],
			[ 'far fa-bell', 'fas fa-bell-slash' ]
		);
	}

	/**
	 * Builds one toggle item in the bar's own shape.
	 *
	 * A signed-out visitor gets `link => 'auth_modal'` with the real login page as the href: the bar
	 * renders that as data-hpab-auth-modal and its own script upgrades the click to HivePress's
	 * sign-in pop-up, so the button is never a dead end and never posts as nobody.
	 *
	 * @param string   $handle Item handle.
	 * @param string   $url POST target.
	 * @param bool     $active Whether the toggle is already on.
	 * @param string[] $labels Off and on labels.
	 * @param string[] $icons Off and on icon classes.
	 * @return array
	 */
	protected function build_item( $handle, $url, $active, $labels, $icons ) {
		$state = $active ? 1 : 0;

		if ( ! is_user_logged_in() ) {
			return [
				'handle' => $handle,
				'data'   => null,
				'bar'    => [
					'link'  => 'auth_modal',
					'url'   => hivepress()->router->get_return_url( 'user_login_page' ),
					'icon'  => $icons[0],
					'label' => $labels[0],
					'style' => 'default',
				],
			];
		}

		return [
			'handle' => $handle,
			'data'   => [
				'url'    => $url,
				'active' => (bool) $active,
				'labels' => array_values( $labels ),
				'icons'  => array_values( $icons ),
				'paths'  => $this->get_icon_paths( $icons ),
			],
			'bar'    => [
				'link'  => '',
				'url'   => '#' . $handle,
				'icon'  => $icons[ $state ],
				'label' => $labels[ $state ],
				'style' => 'default',
			],
		];
	}

	/**
	 * Gets the inline-SVG pairs for two icons, when the shared icon library is loaded.
	 *
	 * The bar draws its icons as inline SVG through FAFH, so a class swap cannot change the glyph on
	 * the page; the script rebuilds the <svg> from a "viewBox|path" pair instead. The leading
	 * backslash is load-bearing: inside the HivePress\Components namespace a bare FAFH:: would
	 * resolve to a class that does not exist.
	 *
	 * @param string[] $icons Icon class strings.
	 * @return string[] Pairs, or empty strings when unavailable.
	 */
	protected function get_icon_paths( $icons ) {
		$paths = [];

		foreach ( $icons as $icon ) {
			$pair = '';

			if ( class_exists( 'FAFH' ) ) {
				$pair = (string) \FAFH::pair( $icon );
			}

			$paths[] = $pair;
		}

		return $paths;
	}

	/**
	 * Enqueues the toggle script, only when there is a signed-in item for it to drive.
	 *
	 * Runs after the bar's own enqueue (both on wp_enqueue_scripts at 10; this filter was registered
	 * later, on init), so that handle is either enqueued by now or never will be. A bar that is
	 * switched off, hidden on this page or empty enqueues nothing, and then neither does this.
	 */
	public function enqueue_scripts() {
		if ( ! wp_script_is( 'hivepress-action-bar-frontend', 'enqueued' ) ) {
			return;
		}

		$data = [];

		foreach ( $this->get_items() as $handle => $item ) {
			if ( ! empty( $item['data'] ) ) {
				$data[ $handle ] = $item['data'];
			}
		}

		if ( ! $data ) {
			return;
		}

		$asset = 'assets/js/quick-actions.js';
		$file  = (string) hivepress()->get_path( 'action_bar_for_hivepress' ) . '/' . $asset;

		wp_enqueue_script(
			'hpab-quick-actions',
			(string) hivepress()->get_url( 'action_bar_for_hivepress' ) . '/' . $asset,
			[ 'hivepress-action-bar-frontend' ],
			HPAB_VERSION . ( file_exists( $file ) ? '.' . (string) filemtime( $file ) : '' ),
			true
		);

		// Nested one level: wp_localize_script() casts every top-level scalar to a string, so a bare
		// boolean would arrive as "" or "1".
		wp_localize_script(
			'hpab-quick-actions',
			'hpabQuickActions',
			[
				'items' => $data,
				'nonce' => wp_create_nonce( 'wp_rest' ),
			]
		);
	}
}
