<?php
/**
 * Quick Actions: the save-listing and create-search-alert buttons in the bar.
 *
 * Both are ACTIONS rather than links. The bar already offers "Favourites" and "Searches" as links
 * that take somebody to a page listing what they saved earlier; these two let them save the thing
 * they are looking at without leaving it, which is the whole point of a bar on a phone.
 *
 * They reach the bar two ways. An owner can place either one as an item in a bar, with its own
 * position, icon, label, style and badge (the quick_* link values, resolved by
 * Hpab_Action_Bar::get_bar_items() through build_placed_item()). Otherwise the Quick Actions
 * switches append them after the bar's own items, which is how 1.8.x sites keep working with no
 * change. A bar that has its own item of a kind never gets the appended one as well.
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

use HivePress\Helpers as hp;
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
	 * The appended items built for this request, keyed by handle, or null before they are built.
	 *
	 * @var array|null
	 */
	protected $items = null;

	/**
	 * The items an owner placed in the bar, built for this request, keyed by handle.
	 *
	 * @var array
	 */
	protected $placed = [];

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
	 * Gets the link values an owner can place in a bar, mapped to the action each one builds.
	 *
	 * Stored as the row's `link`, like every other destination, so the rows keep their shape.
	 *
	 * @return array<string, string>
	 */
	public function get_link_types() {
		return [
			'quick_favorite'     => 'favorite',
			'quick_search_alert' => 'search_alert',
		];
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
	 * Checks for the extension one action needs.
	 *
	 * @param string $type Action type: favorite or search_alert.
	 * @return bool
	 */
	public function has_extension( $type ) {
		if ( 'favorite' === $type ) {
			return $this->has_favorites();
		}

		if ( 'search_alert' === $type ) {
			return $this->has_search_alerts();
		}

		return false;
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
	 * An appended item is skipped when the bar already has its own item of that kind, whether it
	 * resolved on this page or not: placing one is the owner choosing where it goes, and a second
	 * copy would show the visitor two of the same button.
	 *
	 * @param array  $items Item arguments.
	 * @param string $bar Bar name.
	 * @return array
	 */
	public function add_items( $items, $bar ) {
		$items = (array) $items;
		$taken = [];

		foreach ( $items as $item ) {
			if ( is_array( $item ) && ! empty( $item['quick_action'] ) ) {
				$taken[ (string) $item['quick_action'] ] = true;
			}
		}

		$types = $this->get_link_types();

		foreach ( (array) get_option( 'hp_action_bar_' . sanitize_key( (string) $bar ) . '_items', [] ) as $row ) {
			$link = is_array( $row ) && isset( $row['link'] ) ? (string) $row['link'] : '';

			if ( isset( $types[ $link ] ) ) {
				$taken[ $types[ $link ] ] = true;
			}
		}

		foreach ( $this->get_items() as $item ) {
			if ( ! isset( $taken[ $item['type'] ] ) ) {
				$items[] = $item['bar'];
			}
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
	 * Builds the appended items once per request.
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
	 * Builds the appended items for a route.
	 *
	 * Public and route-driven so a runtime check can exercise it without a real HTTP request.
	 *
	 * @param string $route Route name.
	 * @return array
	 */
	public function build_items( $route ) {
		$items = [];

		foreach ( [
			'favorite'     => 'action_bar_item_favorite',
			'search_alert' => 'action_bar_item_search_alert',
		] as $type => $setting ) {
			if ( ! $this->is_item_enabled( $setting ) ) {
				continue;
			}

			$item = $this->build_action( $type, $route );

			if ( $item ) {
				$items[ $item['handle'] ] = $item;
			}
		}

		return $items;
	}

	/**
	 * Builds an item an owner placed in a bar, for the current page.
	 *
	 * @param string $link Stored link value, quick_favorite or quick_search_alert.
	 * @param string $icon Icon class string from the row, or empty for the default pair.
	 * @param string $label Label from the row, or empty for the default pair.
	 * @param string $route Route name, or null for the current one (a runtime check passes one).
	 * @return array|null Item in the bar's own shape, or null where the action cannot work.
	 */
	public function build_placed_item( $link, $icon = '', $label = '', $route = null ) {
		$type = hp\get_array_value( $this->get_link_types(), (string) $link );

		if ( ! $type ) {
			return null;
		}

		if ( is_null( $route ) ) {
			$route = (string) hivepress()->router->get_current_route_name();
		}

		$item = $this->build_action( $type, (string) $route, (string) $icon, (string) $label );

		if ( ! $item ) {
			return null;
		}

		$this->placed[ $item['handle'] ] = $item;

		return $item['bar'];
	}

	/**
	 * Builds one action where it can work: Save on a Listing page, Alert me on a filtered search.
	 *
	 * @param string $type Action type: favorite or search_alert.
	 * @param string $route Route name.
	 * @param string $icon Icon class string, or empty for the default pair.
	 * @param string $label Label, or empty for the default pair.
	 * @return array|null
	 */
	protected function build_action( $type, $route, $icon = '', $label = '' ) {
		$item = null;

		// Save: the Listing page, through the Favorites extension.
		if ( 'favorite' === $type && 'listing_view_page' === $route && $this->has_favorites() ) {
			$item = $this->build_favorite_item( hivepress()->request->get_context( 'listing' ), $icon, $label );
		}

		/*
		 * Alert: search result pages, through the Search Alerts extension. The Requests search route
		 * is named too; it is harmless when that extension is not installed. is_search() is the gate
		 * because an alert for "everything" is not worth offering.
		 */
		if ( 'search_alert' === $type && in_array( $route, [ 'listings_view_page', 'requests_view_page' ], true ) && $this->has_search_alerts() && is_search() ) {
			$item = $this->build_search_alert_item( $icon, $label );
		}

		if ( ! $item ) {
			return null;
		}

		$item['type'] = $type;

		// Marks the item for add_items(), so a bar never gets a second one of the same kind.
		$item['bar']['quick_action'] = $type;

		return $item;
	}

	/**
	 * Builds the save item, reusing the Favorites extension's state and REST action so the heart on
	 * the page and this item can never disagree.
	 *
	 * @param mixed  $listing Listing object from the request context.
	 * @param string $icon Icon class string, or empty for the default pair.
	 * @param string $label Label, or empty for the default pair.
	 * @return array|null
	 */
	protected function build_favorite_item( $listing, $icon = '', $label = '' ) {
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
			$this->get_state_labels( $label, [ __( 'Save', 'action-bar-for-hivepress' ), __( 'Saved', 'action-bar-for-hivepress' ) ] ),
			$icon ? $this->get_state_icons( $icon ) : [ 'far fa-heart', 'fas fa-heart' ]
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
	 * @param string $icon Icon class string, or empty for the default pair.
	 * @param string $label Label, or empty for the default pair.
	 * @return array|null
	 */
	protected function build_search_alert_item( $icon = '', $label = '' ) {
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
			$this->get_state_labels( $label, [ __( 'Alert me', 'action-bar-for-hivepress' ), __( 'Alert set', 'action-bar-for-hivepress' ) ] ),
			$icon ? $this->get_state_icons( $icon ) : [ 'far fa-bell', 'fas fa-bell-slash' ]
		);
	}

	/**
	 * Gets the off and on labels for an item.
	 *
	 * An owner's own label reads the same in both states; the state still shows in the icon, the
	 * active colour and aria-pressed. A label typed exactly as the default keeps the default pair.
	 *
	 * @param string   $label Owner's label, or empty.
	 * @param string[] $defaults Default off and on labels.
	 * @return string[]
	 */
	protected function get_state_labels( $label, $defaults ) {
		$label = trim( (string) $label );

		if ( '' === $label || $label === $defaults[0] ) {
			return $defaults;
		}

		return [ $label, $label ];
	}

	/**
	 * Gets the off and on icons for a chosen icon.
	 *
	 * Where Font Awesome Free draws the icon in both the regular and the solid style (a heart, a
	 * bell, a bookmark), off is the outline and on is the filled one. Anything else keeps the same
	 * glyph in both states and the active colour marks the state. Only an outline the library
	 * actually has is used: FAFH::svg() returns nothing for a style an icon lacks, which would draw
	 * an empty button.
	 *
	 * @param string $icon Icon class string, such as "fas fa-heart".
	 * @return string[]
	 */
	protected function get_state_icons( $icon ) {
		$icon = (string) $icon;

		if ( class_exists( 'FAFH' ) ) {
			$name = (string) \FAFH::parse( $icon )[0];

			if ( '' !== $name && \FAFH::has( $name, 'regular' ) && \FAFH::has( $name, 'solid' ) ) {
				return [ 'far fa-' . $name, 'fas fa-' . $name ];
			}
		}

		return [ $icon, $icon ];
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
	 * later, on init), so that handle is either enqueued by now or never will be, and the bar has
	 * already resolved its items, placed ones included. A bar that is switched off, hidden on this
	 * page or empty enqueues nothing, and then neither does this.
	 */
	public function enqueue_scripts() {
		if ( ! wp_script_is( 'hivepress-action-bar-frontend', 'enqueued' ) ) {
			return;
		}

		$data = [];

		// Placed items last: an appended item skipped for the bar can share a placed item's handle,
		// and the placed one is what is on the page.
		foreach ( array_merge( $this->get_items(), $this->placed ) as $handle => $item ) {
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
