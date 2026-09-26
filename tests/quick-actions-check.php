<?php
/**
 * Runtime check for the Quick Actions items, run against a real HivePress install with WP-CLI.
 *
 * Covers the Save item and the search-alert item, which moved here from Follow Vendors for
 * HivePress in 1.8.0. Phases, chosen with HPAB_PHASE:
 *
 *   HPAB_PHASE=seed  wp eval-file tests/quick-actions-check.php                    creates the fixtures, prints their IDs
 *   HPAB_PHASE=check wp eval-file tests/quick-actions-check.php --user=<id>        every assertion
 *   HPAB_PHASE=clean wp eval-file tests/quick-actions-check.php                    removes everything it made
 *
 * The check phase needs --user= because a signed-in item is only built for a user who was signed in
 * before WordPress loaded. Nothing here sends an email or calls out.
 *
 * The counter lives inside hpab_check() as a static: wp eval-file includes this file inside a
 * function, so a file-scope counter read through `global` binds to a different variable and the
 * summary would always say everything passed.
 *
 * @package ActionBar\Tests
 */

// phpcs:ignoreFile -- test harness, excluded from the ruleset.

use HivePress\Models;

function hpab_check( $label, $condition = true, $detail = '' ) {
	static $fails = 0;

	if ( is_null( $label ) ) {
		return $fails;
	}

	if ( $condition ) {
		echo "  PASS  {$label}\n";
	} else {
		$fails++;
		echo "  FAIL  {$label}" . ( '' !== (string) $detail ? "  [{$detail}]" : '' ) . "\n";
	}
}

function hpab_fixture() {
	$fixture = get_option( 'hpab_qa_fixture' );

	return is_array( $fixture ) ? $fixture : [];
}

$phase = getenv( 'HPAB_PHASE' ) ? getenv( 'HPAB_PHASE' ) : 'check';

/*
--------------------------------------------------------------------------
Seed.
--------------------------------------------------------------------------
*/

if ( 'seed' === $phase ) {
	$user_id = wp_insert_user(
		[
			'user_login'   => 'hpab_qa_user',
			'user_email'   => 'hpab_qa_user@example.com',
			'user_pass'    => wp_generate_password( 24 ),
			'display_name' => 'HPAB Quick Actions',
			'role'         => 'contributor',
		]
	);

	if ( is_wp_error( $user_id ) ) {
		echo "seed failed (already seeded? run HPAB_PHASE=clean first)\n";
		exit( 1 );
	}

	$vendor_id = wp_insert_post(
		[
			'post_type'   => 'hp_vendor',
			'post_status' => 'publish',
			'post_title'  => 'HPAB QA Vendor',
			'post_name'   => 'hpab-qa-vendor',
			'post_author' => $user_id,
		]
	);

	$listing_id = wp_insert_post(
		[
			'post_type'   => 'hp_listing',
			'post_status' => 'publish',
			'post_title'  => 'HPAB QA Listing',
			'post_name'   => 'hpab-qa-listing',
			'post_author' => $user_id,
			'post_parent' => $vendor_id,
		]
	);

	$draft_id = wp_insert_post(
		[
			'post_type'   => 'hp_listing',
			'post_status' => 'draft',
			'post_title'  => 'HPAB QA Draft',
			'post_author' => $user_id,
			'post_parent' => $vendor_id,
		]
	);

	update_option(
		'hpab_qa_fixture',
		[
			'user'    => (int) $user_id,
			'vendor'  => (int) $vendor_id,
			'listing' => (int) $listing_id,
			'draft'   => (int) $draft_id,
		]
	);

	echo "USER_ID={$user_id}\n";
	echo "VENDOR_ID={$vendor_id}\n";
	echo "LISTING_ID={$listing_id}\n";

	exit( 0 );
}

/*
--------------------------------------------------------------------------
Clean.
--------------------------------------------------------------------------
*/

if ( 'clean' === $phase ) {
	$fixture = hpab_fixture();

	foreach ( [ 'listing', 'draft', 'vendor' ] as $key ) {
		if ( ! empty( $fixture[ $key ] ) ) {
			wp_delete_post( (int) $fixture[ $key ], true );
		}
	}

	if ( ! empty( $fixture['user'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( (int) $fixture['user'] );
	}

	delete_option( 'hpab_qa_fixture' );
	delete_option( 'hp_action_bar_item_favorite' );
	delete_option( 'hp_action_bar_item_search_alert' );

	echo "cleaned\n";

	exit( 0 );
}

/*
--------------------------------------------------------------------------
Check.
--------------------------------------------------------------------------
*/

$fixture = hpab_fixture();

if ( ! $fixture ) {
	echo "run HPAB_PHASE=seed first\n";
	exit( 1 );
}

$qa = hivepress()->hpab_quick_actions;

echo "=== Wiring ===\n";
hpab_check( 'the deliberately false control fails', false, 'control' );
hpab_check( 'the component is loaded', (bool) $qa );
hpab_check( 'the items filter is registered', false !== has_filter( 'hivepress/v1/action_bar/items', [ $qa, 'add_items' ] ) );
hpab_check( 'the enqueue hook is registered', false !== has_action( 'wp_enqueue_scripts', [ $qa, 'enqueue_scripts' ] ) );

$has_fav    = $qa->has_favorites();
$has_alerts = $qa->has_search_alerts();

hpab_check( 'Favorites detected', $has_fav );
hpab_check( 'Search Alerts detected', $has_alerts );

echo "\n=== The Save item ===\n";

$listing = Models\Listing::query()->get_by_id( (int) $fixture['listing'] );

hivepress()->request->set_context( 'listing', $listing );

$items = $qa->build_items( 'listing_view_page' );

if ( $has_fav ) {
	$handle = 'hpab-favorite-' . (int) $fixture['listing'];

	hpab_check( 'a Listing page produces one save item', 1 === count( $items ) && isset( $items[ $handle ] ), implode( ',', array_keys( $items ) ) );

	$item = isset( $items[ $handle ] ) ? $items[ $handle ] : null;

	if ( $item ) {
		hpab_check( 'its href is a bare fragment the bar will not highlight', '#' . $handle === $item['bar']['url'] && '' === $item['bar']['link'] );
		hpab_check( 'it reads Save with the outline heart while not saved', 'Save' === $item['bar']['label'] && 'far fa-heart' === $item['bar']['icon'] );
		hpab_check( 'the script data carries both states', [ 'Save', 'Saved' ] === $item['data']['labels'] && [ 'far fa-heart', 'fas fa-heart' ] === $item['data']['icons'] );
		hpab_check( "it posts to the Favorites extension's own action", false !== strpos( $item['data']['url'], '/listings/' . (int) $fixture['listing'] . '/favorite/' ), $item['data']['url'] );
		hpab_check( 'it is not active for a user who has not saved it', empty( $item['data']['active'] ) );
		hpab_check( 'icon pairs are present with the icon library, empty without', ! class_exists( 'FAFH' ) ? [ '', '' ] === $item['data']['paths'] : ( 2 === count( $item['data']['paths'] ) && false !== strpos( $item['data']['paths'][0], '|' ) ) );
	}

	// A saved Listing must render its "on" state from the server, so a slow phone never shows
	// "Save" for something already saved.
	hivepress()->request->set_context( 'favorite_ids', [ (int) $fixture['listing'] ] );

	$saved = $qa->build_items( 'listing_view_page' );

	hpab_check( 'a saved Listing renders Saved with the solid heart', isset( $saved[ $handle ] ) && 'Saved' === $saved[ $handle ]['bar']['label'] && 'fas fa-heart' === $saved[ $handle ]['bar']['icon'] );

	hivepress()->request->set_context( 'favorite_ids', [] );

	// An unpublished Listing has no public page to save from.
	$draft = Models\Listing::query()->get_by_id( (int) $fixture['draft'] );

	hivepress()->request->set_context( 'listing', $draft );
	hpab_check( 'an unpublished Listing gets no save item', 0 === count( $qa->build_items( 'listing_view_page' ) ) );
	hivepress()->request->set_context( 'listing', $listing );

	update_option( 'hp_action_bar_item_favorite', '' );
	hpab_check( 'the Save setting unticked removes it', 0 === count( $qa->build_items( 'listing_view_page' ) ) );
	delete_option( 'hp_action_bar_item_favorite' );

	hpab_check( 'an absent setting reads as on', 1 === count( $qa->build_items( 'listing_view_page' ) ) );
} else {
	hpab_check( 'without Favorites a Listing page gets no save item', 0 === count( $items ) );
}

echo "\n=== The search-alert item ===\n";

// Not a search: an alert for "everything" is not worth offering.
hpab_check( 'a search route with no search query gives no alert item', 0 === count( $qa->build_items( 'listings_view_page' ) ) );

echo "\n=== Routes that must produce nothing ===\n";
hpab_check( 'an unrelated route gives nothing', 0 === count( $qa->build_items( 'user_account_page' ) ) );
hpab_check( 'the Listing EDIT route gives nothing, despite the listing context', 0 === count( $qa->build_items( 'listing_edit_page' ) ) );
hpab_check( 'the Vendor page gives nothing (that item belongs to another plugin)', 0 === count( $qa->build_items( 'vendor_view_page' ) ) );

echo "\n=== add_items() ===\n";

$owner_rows = [ [ 'link' => 'home', 'url' => '/', 'icon' => 'fas fa-home', 'label' => 'Home' ] ];
$appended   = $qa->add_items( $owner_rows, 'user' );

hpab_check( "the owner's rows stay first and untouched", isset( $appended[0]['link'] ) && 'home' === $appended[0]['link'] );
hpab_check( 'the route items are appended after them', count( $appended ) === 1 + count( $qa->get_items() ) );

add_filter(
	'hpab/quick_action_items',
	function ( $items, $route, $bar ) {
		$items[] = [ 'label' => 'extra:' . $route . ':' . $bar ];

		return $items;
	},
	10,
	3
);

$filtered = $qa->add_items( $owner_rows, 'user' );

hpab_check( 'the hpab/quick_action_items filter runs last', 'extra:' === substr( (string) end( $filtered )['label'], 0, 6 ) );

$fails = hpab_check( null );

// The control failure above is deliberate, so one failure is a pass.
echo "\n----------------------------------------\n";

if ( 1 === $fails ) {
	echo "RESULT: ALL CHECKS PASSED (plus the one deliberate control failure)\n";
} else {
	echo 'RESULT: ' . ( $fails - 1 ) . " REAL FAILURE(S)\n";
}
