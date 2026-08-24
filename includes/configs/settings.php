<?php
/**
 * Settings configuration.
 *
 * @package HivePress\Configs
 */

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Get the action bar component.
/** @var \HivePress\Components\Hpab_Action_Bar $action_bar_component */
$action_bar_component = hivepress()->hpab_action_bar;

// Set colour fields.
$action_bar_color_fields = [];

$action_bar_color_order = 10;

// The colour field only exists in HivePress 1.7.26 and later, so fall back to a plain hex box on older cores.
$action_bar_color_type = class_exists( '\HivePress\Fields\Color' ) ? 'color' : 'text';

foreach ( [
	'background'           => [ esc_html__( 'Bar background', 'action-bar-for-hivepress' ), '#f5f5f5', esc_html__( 'Fills the whole bar. With the glass effect switched on, this is the colour tinting the blur.', 'action-bar-for-hivepress' ) ],
	'border'               => [ esc_html__( 'Bar border', 'action-bar-for-hivepress' ), '#dddddd', esc_html__( 'The hairline along the very top of the bar, separating it from the page.', 'action-bar-for-hivepress' ) ],
	'icon'                 => [ esc_html__( 'Icon colour', 'action-bar-for-hivepress' ), '#5f5f5f', esc_html__( 'The icons on every item except any item using the Prominent style.', 'action-bar-for-hivepress' ) ],
	'label'                => [ esc_html__( 'Label colour', 'action-bar-for-hivepress' ), '#5f5f5f', esc_html__( 'The text under, or above, each icon. Items with no label are not affected.', 'action-bar-for-hivepress' ) ],
	'active'               => [ esc_html__( 'Active colour', 'action-bar-for-hivepress' ), '#111111', esc_html__( 'Applied to the icon and label of the item whose address exactly matches the page being viewed. Many pages, such as an individual listing, match no item, and nothing is highlighted then. An item using the Prominent style keeps its own icon colour, and only its label changes.', 'action-bar-for-hivepress' ) ],
	'prominent_background' => [ esc_html__( 'Prominent background', 'action-bar-for-hivepress' ), '#333333', esc_html__( 'The raised circle behind an item set to the Prominent style. Nothing changes if no item uses it.', 'action-bar-for-hivepress' ) ],
	'prominent_icon'       => [ esc_html__( 'Prominent icon colour', 'action-bar-for-hivepress' ), '#ffffff', esc_html__( 'The icon inside the Prominent circle. Pick something that contrasts strongly with the Prominent background colour.', 'action-bar-for-hivepress' ) ],
	'badge_background'     => [ esc_html__( 'Badge background', 'action-bar-for-hivepress' ), '#d63638', esc_html__( 'The small counter bubble, shown only on items given a badge and only when the count is above zero.', 'action-bar-for-hivepress' ) ],
	'badge_text'           => [ esc_html__( 'Badge text colour', 'action-bar-for-hivepress' ), '#ffffff', esc_html__( 'The number inside that bubble.', 'action-bar-for-hivepress' ) ],
] as $action_bar_color_name => $action_bar_color_args ) {
	$action_bar_color_field = [
		'label'      => $action_bar_color_args[0],
		'type'       => $action_bar_color_type,
		'default'    => $action_bar_color_args[1],
		'max_length' => 7,

		'attributes' => [
			'class'              => [ 'hp-action-bar-color-picker' ],
			'data-default-color' => $action_bar_color_args[1],
		],

		'_order'     => $action_bar_color_order,
	];

	if ( isset( $action_bar_color_args[2] ) ) {
		$action_bar_color_field['description'] = $action_bar_color_args[2];
	}

	$action_bar_color_fields[ 'action_bar_color_' . $action_bar_color_name ] = $action_bar_color_field;

	$action_bar_color_order += 10;
}

// Set item fields.
$action_bar_item_fields = [
	'link'  => [
		'placeholder' => esc_html__( 'Link', 'action-bar-for-hivepress' ),
		'type'        => 'select',
		'options'     => $action_bar_component->get_link_options() + $action_bar_component->get_route_link_options() + $action_bar_component->get_page_options(),
		'_order'      => 10,
	],

	'icon'  => [
		'placeholder' => esc_html__( 'Icon', 'action-bar-for-hivepress' ),
		'type'        => 'select',
		'options'     => 'icons',
		'_order'      => 20,
	],

	'label' => [
		'placeholder' => esc_html__( 'Label', 'action-bar-for-hivepress' ),
		'type'        => 'text',
		'max_length'  => 32,
		'_order'      => 40,
	],

	/*
	 * A `url` field rather than `text`. Text sanitises with sanitize_text_field(), which strips
	 * percent-encoded octets outright, so https://example.com/a%20b silently stored as
	 * https://example.com/ab and any address carrying a space or an accent was quietly broken.
	 * The URL field sanitises with esc_url_raw() and keeps them (verified 2026-08-13).
	 */
	'url'   => [

		// Named "Address" rather than "Custom URL", which is already the name of an option in the
		// dropdown one column to the left. Two different controls cannot share a name on the same row.
		'placeholder' => esc_html__( 'Address for Custom URL', 'action-bar-for-hivepress' ),
		'type'        => 'url',
		'max_length'  => 2048,
		'_order'      => 15,
	],

	'style' => [
		'placeholder' => esc_html__( 'Style', 'action-bar-for-hivepress' ),
		'type'        => 'select',

		'options'     => [
			'default'   => esc_html__( 'Default', 'action-bar-for-hivepress' ),
			'prominent' => esc_html__( 'Prominent', 'action-bar-for-hivepress' ),
		],

		'_order'      => 60,
	],

	'badge' => [
		'placeholder' => esc_html__( 'No badge', 'action-bar-for-hivepress' ),
		'type'        => 'select',
		'options'     => $action_bar_component->get_badge_options(),
		'_order'      => 70,
	],
];

// Set item sections.
$action_bar_item_sections = [];

foreach ( [
	'user'   => [
		'title'       => esc_html__( 'User Bar', 'action-bar-for-hivepress' ),
		'description' => esc_html__( 'Add up to 5 items, shown to everyone who visits your site. If you switch the vendor bar on below, vendors see the Vendor Bar items instead of these. For each item, choose a destination from the first dropdown, then pick an icon and, if you like, the label text shown under it. Drag the handle on a row to move it: the top row becomes the leftmost button, and the items share the width of the bar evenly, so three or four read more comfortably on a narrow phone than five. The Address box is used only when you choose Custom URL, and is ignored for every other choice. The Prominent style lifts an item into a highlighted circle, ideal for one main action. The Badge dropdown picks which unread counter appears on the item, if any. An item is left out of the bar if it has no destination, if you chose Custom URL and left the Address box empty, or if its destination no longer exists, for example a page you have unpublished or one belonging to an extension you have switched off. Icons come from the Font Awesome set HivePress bundles, so if another plugin or your theme replaces or trims that set, check your chosen icons still appear.', 'action-bar-for-hivepress' ),
		'_order'      => 40,
	],

	'vendor' => [
		'title'       => esc_html__( 'Vendor Bar', 'action-bar-for-hivepress' ),
		'description' => esc_html__( 'Add up to 5 items shown to users with a published vendor profile when the vendor bar is enabled. The fields work the same way as the User Bar items above. If none of these items can be shown, for example when the rows are empty or point somewhere that no longer exists, vendors see the User Bar items instead so they are never left without navigation.', 'action-bar-for-hivepress' ),
		'_order'      => 50,
	],
] as $action_bar_name => $action_bar_section ) {
	$action_bar_section['fields'] = [
		'action_bar_' . $action_bar_name . '_items' => [
			'label'      => esc_html__( 'Items', 'action-bar-for-hivepress' ),
			'caption'    => esc_html__( 'Add item', 'action-bar-for-hivepress' ),
			'type'       => 'repeater',

			/*
			 * Widened per bar with the values this bar already stores, so a choice whose source is
			 * temporarily away - a deactivated extension's page, an unpublished page, a beta-era
			 * custom icon - still renders as the selected option and round-trips through the save,
			 * instead of falling back to the placeholder and being silently erased. Without this,
			 * the dropdowns built above only offer what exists right now, and one Save while an
			 * extension was switched off wiped the stored choice for good.
			 */
			'fields'     => $action_bar_component->add_stored_item_options( $action_bar_item_fields, $action_bar_name ),

			'attributes' => [
				'class' => [ 'hp-action-bar-items' ],
			],

			'default'    => $action_bar_component->get_item_defaults( $action_bar_name ),
			'_order'     => 10,
		],
	];

	$action_bar_item_sections[ $action_bar_name . '_items' ] = $action_bar_section;
}

return [
	'action_bar' => [
		'title'    => esc_html__( 'Action Bar', 'action-bar-for-hivepress' ),
		'_order'   => 1000,

		'sections' => array_merge(
			[
				'display'    => [
					'title'       => esc_html__( 'Display', 'action-bar-for-hivepress' ),
					'description' => esc_html__( 'The action bar is a fixed strip of buttons pinned to the bottom of the screen, giving your website an app-like feel. It appears based on how wide the browser window is rather than what device someone is using: small screens are up to 767px wide, medium screens are 768px to 1024px, and large screens are anything wider. Tick the sizes you want it on, and if you untick all three it never appears at all.', 'action-bar-for-hivepress' ),
					'_order'      => 10,

					'fields'      => [
						'action_bar_enable_mobile'     => [
							'label'   => esc_html__( 'Small screens', 'action-bar-for-hivepress' ),
							'caption' => esc_html__( 'Show the action bar on screens up to 767px wide', 'action-bar-for-hivepress' ),
							'type'    => 'checkbox',
							'default' => true,
							'_order'  => 10,
						],

						'action_bar_enable_tablet'     => [
							'label'   => esc_html__( 'Medium screens', 'action-bar-for-hivepress' ),
							'caption' => esc_html__( 'Show the action bar on screens 768px to 1024px wide', 'action-bar-for-hivepress' ),
							'type'    => 'checkbox',
							'_order'  => 20,
						],

						'action_bar_enable_desktop'    => [
							'label'       => esc_html__( 'Large screens', 'action-bar-for-hivepress' ),
							'caption'     => esc_html__( 'Show the action bar on screens wider than 1024px', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'Adds the bar on laptops and desktop computers as well. Most sites leave this off, because visitors on a large screen already have your main menu and the bar takes up room at the bottom of every page. It is worth turning on if your site is meant to feel like an app on every screen. The buttons spread out to fill the width, so three or four look better than five.', 'action-bar-for-hivepress' ),
							'type'        => 'checkbox',
							'_order'      => 22,
						],

						'action_bar_height'            => [
							'label'       => esc_html__( 'Bar height (px)', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'How tall the bar is, in pixels. This is a minimum rather than a fixed height: if your labels are large the bar grows to fit them. Between 44 and 120; 56 suits most sites. On iPhones without a home button, extra space is added below this automatically.', 'action-bar-for-hivepress' ),
							'type'        => 'number',
							'min_value'   => 44,
							'max_value'   => 120,
							'default'     => 56,
							'_order'      => 25,
						],

						'action_bar_label_position'    => [
							'label'       => esc_html__( 'Label position', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'Choose whether item labels sit under the icons or above them. With labels above, an item using the Prominent style keeps its coloured circle but no longer sits raised above the bar.', 'action-bar-for-hivepress' ),
							'type'        => 'select',

							'options'     => [
								'below' => esc_html__( 'Below icons', 'action-bar-for-hivepress' ),
								'above' => esc_html__( 'Above icons', 'action-bar-for-hivepress' ),
							],

							'default'     => 'below',
							'_order'      => 30,
						],

						'action_bar_label_size'        => [
							'label'       => esc_html__( 'Label size (px)', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'How big the text under each icon is. Between 9 and 16; 11 suits most sites. Larger text makes the bar taller.', 'action-bar-for-hivepress' ),
							'type'        => 'number',
							'min_value'   => 9,
							'max_value'   => 16,
							'default'     => 11,
							'_order'      => 32,
						],

						'action_bar_label_weight'      => [
							'label'       => esc_html__( 'Label weight', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'How thick the label text looks. Medium is a good middle ground; Bold can be hard to read at small sizes.', 'action-bar-for-hivepress' ),
							'type'        => 'select',

							'options'     => [
								'400' => esc_html__( 'Normal', 'action-bar-for-hivepress' ),
								'500' => esc_html__( 'Medium', 'action-bar-for-hivepress' ),
								'600' => esc_html__( 'Semibold', 'action-bar-for-hivepress' ),
								'700' => esc_html__( 'Bold', 'action-bar-for-hivepress' ),
							],

							'default'     => '500',
							'_order'      => 34,
						],

						// The three counter names in this description are quoted word for word from
						// get_badge_sources(), because the Badge dropdown it explains is built from that list.
						// The two drifted apart once already: 1.4.0 renamed "All notifications" to "Account
						// activity (HivePress)" and added "Unread notifications", but this paragraph was left
						// describing the old pair until 1.4.5, so the screen contradicted the control sitting
						// directly beneath it. Rename or add a counter there and this paragraph must change too.
						// The conditions each sentence gives are the gates in get_badge_options(), which are the
						// only thing deciding whether an option is offered: until 1.4.5 this paragraph claimed the
						// message counter was hidden without message storage, when storage only makes it read zero.
						'action_bar_enable_badge'      => [
							'label'       => esc_html__( 'Notification badge', 'action-bar-for-hivepress' ),
							'caption'     => esc_html__( 'Allow the notification badge on items', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'Each item has its own Badge dropdown. Account activity (HivePress) shows HivePress\'s own combined unread count, the same number as the one beside your name in the site header, which the Messages, Bookings and Marketplace extensions add to. With none of those three installed it stays at zero. Unread messages counts messages only, and appears in the dropdown whenever the Messages extension is active. It stays at zero unless "Store messages in the database" is ticked under HivePress, Settings, Messages, because with that box unticked messages are emailed rather than stored, so there is nothing to count. Unread notifications counts only unread notifications from Notifications for HivePress, and appears in the dropdown only when that plugin is active. Untick this box to hide every badge at once.', 'action-bar-for-hivepress' ),
							'type'        => 'checkbox',
							'default'     => true,
							'_order'      => 40,
						],

						'action_bar_enable_vendor_bar' => [
							'label'       => esc_html__( 'Vendor bar', 'action-bar-for-hivepress' ),
							'caption'     => esc_html__( 'Show different items to vendors', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'If enabled, users with a published vendor profile see the Vendor Bar items instead of the User Bar items. Tick to reveal the Vendor Bar section below.', 'action-bar-for-hivepress' ),
							'type'        => 'checkbox',
							'_order'      => 50,
						],

						'action_bar_safe_area'         => [
							'label'       => esc_html__( 'Room for the home bar', 'action-bar-for-hivepress' ),
							'caption'     => esc_html__( 'Leave space below the bar on iPhones without a home button', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'On iPhones without a home button there is a thin bar at the very bottom of the screen that can overlap your buttons. Ticking this asks the browser to report how much room to leave, and the action bar then sits above it. It is safe to leave ticked whatever your theme does, and it has no effect on other phones.', 'action-bar-for-hivepress' ),
							'type'        => 'checkbox',
							'_order'      => 60,
						],

						'action_bar_glass'             => [
							'label'       => esc_html__( 'Glass effect', 'action-bar-for-hivepress' ),
							'caption'     => esc_html__( 'Blur the page showing through the bar', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'Makes the bar semi-transparent and blurs whatever scrolls behind it, the frosted look used by phone apps. Pick a light bar colour for a light site and a dark one for a dark site, then check that your icons and labels are still easy to read against your busiest page. Older browsers that cannot blur keep the solid bar instead, and visitors who have asked their device to reduce transparency also see the solid bar.', 'action-bar-for-hivepress' ),
							'type'        => 'checkbox',
							'_order'      => 62,
						],

						'action_bar_glass_opacity'     => [
							'label'       => esc_html__( 'Glass opacity (%)', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'How solid the bar colour stays over the blur. Lower shows more of the page, higher keeps labels easier to read. Between 10 and 100; 72 suits most sites, and at 100 the bar is solid so the effect no longer shows.', 'action-bar-for-hivepress' ),
							'type'        => 'number',
							'min_value'   => 10,
							'max_value'   => 100,
							'default'     => 72,
							'_parent'     => 'action_bar_glass',
							'_order'      => 63,
						],

						'action_bar_glass_blur'        => [
							'label'       => esc_html__( 'Glass blur (px)', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'How strongly the page behind the bar is blurred. Between 0 and 40; 20 gives the frosted look. At 0 the page stays sharp behind the tint, though colours showing through still look slightly richer.', 'action-bar-for-hivepress' ),
							'type'        => 'number',
							'min_value'   => 0,
							'max_value'   => 40,
							'default'     => 20,
							'_parent'     => 'action_bar_glass',
							'_order'      => 64,
						],

						'action_bar_glass_highlight'   => [
							'label'       => esc_html__( 'Glass top edge', 'action-bar-for-hivepress' ),
							'caption'     => esc_html__( 'Add a soft light along the top edge', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'Draws a faint white highlight across the very top of the bar, which is what makes frosted glass read as glass rather than as a flat tint. It shows up best on a dark or mid-toned bar; on a near-white bar it will not be visible either way.', 'action-bar-for-hivepress' ),
							'type'        => 'checkbox',
							'default'     => true,
							'_parent'     => 'action_bar_glass',
							'_order'      => 65,
						],
					],
				],

				'visibility' => [
					'title'       => esc_html__( 'Visibility', 'action-bar-for-hivepress' ),
					'description' => esc_html__( 'Choose where the action bar stays hidden, for example pages that need the visitor\'s full attention, or the steps where someone is paying.', 'action-bar-for-hivepress' ),
					'_order'      => 20,

					'fields'      => [
						'action_bar_hidden_pages'  => [
							'label'       => esc_html__( 'Hide on pages', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'The bar is hidden on the pages you pick here. This list covers WordPress pages only, so it cannot be used to hide the bar on listings, vendor profiles or search results.', 'action-bar-for-hivepress' ),
							'type'        => 'select',
							'options'     => 'posts',
							'option_args' => [ 'post_type' => 'page' ],
							'multiple'    => true,
							'_order'      => 10,
						],

						'action_bar_hide_checkout' => [
							'label'       => esc_html__( 'Checkout pages', 'action-bar-for-hivepress' ),
							'caption'     => esc_html__( 'Hide the action bar on the WooCommerce cart and checkout pages', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'This only has an effect if WooCommerce is installed. Payment or booking pages provided by other extensions are not covered, so hide those with Hide on pages above.', 'action-bar-for-hivepress' ),
							'type'        => 'checkbox',
							'default'     => true,
							'_order'      => 20,
						],
					],
				],

				'colors'     => [
					'title'       => esc_html__( 'Colours', 'action-bar-for-hivepress' ),
					'description' => esc_html__( 'Customise the action bar colours. Use the colour wheel, or type a hex code such as #f5f5f5. Short codes like #fff are expanded for you, and the # is added if you leave it off. Clear a field and save to put that colour back to its default.', 'action-bar-for-hivepress' ),
					'_order'      => 30,
					'fields'      => $action_bar_color_fields,
				],
			],
			$action_bar_item_sections,
			[

				/*
				 * The section description exists to answer a question WordPress itself creates. Its delete
				 * screen prints "(will also delete its data)" whenever an uninstall.php is present at all
				 * (wp-admin/plugins.php:376-380), whatever that file does, and ours keeps everything unless
				 * this box is ticked. Without a note here an owner reads the core warning and reasonably
				 * concludes their settings are going.
				 */
				'removal' => [
					'title'       => esc_html__( 'Removing the Plugin', 'action-bar-for-hivepress' ),
					'description' => esc_html__( 'Your bar items and settings are kept if you delete this plugin, so you can reinstall it and carry on where you left off. WordPress shows its own warning on the delete screen saying the data goes too, but that warning is the same for every plugin and does not apply here unless you tick the box below. Switching the plugin off never removes anything.', 'action-bar-for-hivepress' ),
					'_order'      => 60,

					'fields'      => [
						'action_bar_delete_data' => [
							'label'       => esc_html__( 'Delete all data', 'action-bar-for-hivepress' ),
							'caption'     => esc_html__( 'Delete everything when this plugin is deleted', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'Leave this unticked unless you are certain. With it ticked, deleting the plugin also removes every item you have added to the User Bar and the Vendor Bar, your colours, and every other setting on this page. WordPress will still ask you to confirm the deletion itself, but that confirmation says nothing about this box, so the removal happens with no second chance. It cannot be undone, so write down anything you want to keep first. Deleting the plugin with this unticked keeps all of it.', 'action-bar-for-hivepress' ),
							'type'        => 'checkbox',
							'_order'      => 10,
						],
					],
				],
			]
		),
	],
];
