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

/*
 * Descriptions here and below are deliberately short: one or two sentences with only what an admin
 * needs to decide. The backend stylesheet caps their line length so they wrap readably.
 */
foreach ( [
	'background'           => [ esc_html__( 'Bar background', 'action-bar-for-hivepress' ), '#f5f5f5', esc_html__( 'Fills the whole bar. With the glass effect on, this colour tints the blur.', 'action-bar-for-hivepress' ) ],
	'border'               => [ esc_html__( 'Bar border', 'action-bar-for-hivepress' ), '#dddddd', esc_html__( 'The hairline along the top of the bar.', 'action-bar-for-hivepress' ) ],
	'icon'                 => [ esc_html__( 'Icon colour', 'action-bar-for-hivepress' ), '#5f5f5f', esc_html__( 'Every icon except items using the Prominent style.', 'action-bar-for-hivepress' ) ],
	'icon_background'      => [ esc_html__( 'Icon background', 'action-bar-for-hivepress' ), '', esc_html__( 'Optional rounded backdrop behind each icon. Leave empty for none.', 'action-bar-for-hivepress' ) ],
	'label'                => [ esc_html__( 'Label colour', 'action-bar-for-hivepress' ), '#5f5f5f', esc_html__( 'The text under or above each icon.', 'action-bar-for-hivepress' ) ],
	'active'               => [ esc_html__( 'Active colour', 'action-bar-for-hivepress' ), '#111111', esc_html__( 'Highlights the item matching the page being viewed. Pages that match no item highlight nothing.', 'action-bar-for-hivepress' ) ],
	'prominent_background' => [ esc_html__( 'Prominent background', 'action-bar-for-hivepress' ), '#333333', esc_html__( 'The raised circle behind an item set to the Prominent style.', 'action-bar-for-hivepress' ) ],
	'prominent_icon'       => [ esc_html__( 'Prominent icon colour', 'action-bar-for-hivepress' ), '#ffffff', esc_html__( 'The icon inside the Prominent circle. Pick a strong contrast with its background.', 'action-bar-for-hivepress' ) ],
	'badge_background'     => [ esc_html__( 'Badge background', 'action-bar-for-hivepress' ), '#d63638', esc_html__( 'The unread counter bubble, shown only when the count is above zero.', 'action-bar-for-hivepress' ) ],
	'badge_text'           => [ esc_html__( 'Badge text colour', 'action-bar-for-hivepress' ), '#ffffff', esc_html__( 'The number inside the bubble.', 'action-bar-for-hivepress' ) ],
] as $action_bar_color_name => $action_bar_color_args ) {
	$action_bar_color_field = [
		'label'      => $action_bar_color_args[0],
		'type'       => $action_bar_color_type,
		'max_length' => 7,

		'attributes' => [
			'class' => [ 'hp-action-bar-color-picker' ],
		],

		'_order'     => $action_bar_color_order,
	];

	// A colour with no default, such as the icon background, must not seed the picker: an empty
	// value means "off" rather than "back to a colour".
	if ( '' !== $action_bar_color_args[1] ) {
		$action_bar_color_field['default'] = $action_bar_color_args[1];

		$action_bar_color_field['attributes']['data-default-color'] = $action_bar_color_args[1];
	}

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

	/*
	 * A plain array of options rather than the "icons" preset, so Font Awesome 6/7 names and brand
	 * icons can be offered alongside HivePress's bundled list. The preset would have switched the
	 * select2 icon preview on by itself; with an array that attribute has to be set by hand.
	 */
	'icon'  => [
		'placeholder' => esc_html__( 'Icon', 'action-bar-for-hivepress' ),
		'type'        => 'select',
		'options'     => $action_bar_component->get_icon_options(),

		'attributes'  => [
			'data-template' => 'icon',
		],

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
	'guest'  => [
		'title'       => esc_html__( 'Logged-Out Bar', 'action-bar-for-hivepress' ),
		'description' => esc_html__( 'Up to 5 items shown to logged-out visitors when the logged-out bar is enabled. The Sign in pop-up link opens HivePress\'s own login window. If every row here is empty or broken, logged-out visitors see the User Bar items instead.', 'action-bar-for-hivepress' ),
		'_order'      => 35,
	],

	'user'   => [
		'title'       => esc_html__( 'User Bar', 'action-bar-for-hivepress' ),
		'description' => esc_html__( 'Add up to 5 items. This bar is shown to everyone unless the logged-out or vendor bars replace it for those visitors. Pick a destination, an icon and an optional label per row, and drag rows to reorder; the top row is the leftmost button. The Address box only applies to Custom URL. The Badge dropdown picks which unread counter shows on an item, and a stored choice survives even while its plugin is switched off. Items with no destination, or whose destination no longer exists, are left out.', 'action-bar-for-hivepress' ),
		'_order'      => 40,
	],

	'vendor' => [
		'title'       => esc_html__( 'Vendor Bar', 'action-bar-for-hivepress' ),
		'description' => esc_html__( 'Up to 5 items shown to users with a published vendor profile when the vendor bar is enabled. If every row here is empty or broken, vendors see the User Bar items instead.', 'action-bar-for-hivepress' ),
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
					'description' => esc_html__( 'The action bar is a fixed strip of buttons at the bottom of the screen. It appears based on browser width, not device: mobile is up to 767px, tablet 768px to 1024px, desktop anything wider. Untick all three and it never appears.', 'action-bar-for-hivepress' ),
					'_order'      => 10,

					'fields'      => [
						'action_bar_enable_mobile'       => [
							'label'   => esc_html__( 'Mobile', 'action-bar-for-hivepress' ),
							'caption' => esc_html__( 'Show the action bar on screens up to 767px wide', 'action-bar-for-hivepress' ),
							'type'    => 'checkbox',
							'default' => true,
							'_order'  => 10,
						],

						'action_bar_enable_tablet'       => [
							'label'   => esc_html__( 'Tablet', 'action-bar-for-hivepress' ),
							'caption' => esc_html__( 'Show the action bar on screens 768px to 1024px wide', 'action-bar-for-hivepress' ),
							'type'    => 'checkbox',
							'_order'  => 20,
						],

						'action_bar_enable_desktop'      => [
							'label'       => esc_html__( 'Desktop', 'action-bar-for-hivepress' ),
							'caption'     => esc_html__( 'Show the action bar on screens wider than 1024px', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'Most sites leave this off, since desktop visitors already have the main menu. On wide screens the buttons gather into a centred dock.', 'action-bar-for-hivepress' ),
							'type'        => 'checkbox',
							'_order'      => 22,
						],

						'action_bar_height'              => [
							'label'       => esc_html__( 'Bar height (px)', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'Minimum height, 44 to 120; 56 suits most sites. The bar grows if labels need more room.', 'action-bar-for-hivepress' ),
							'type'        => 'number',
							'min_value'   => 44,
							'max_value'   => 120,
							'default'     => 56,
							'_order'      => 25,
						],

						'action_bar_icon_size'           => [
							'label'       => esc_html__( 'Icon size (px)', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'Size of the item icons, 14 to 32; 20 suits most sites. The Prominent circle scales with it.', 'action-bar-for-hivepress' ),
							'type'        => 'number',
							'min_value'   => 14,
							'max_value'   => 32,
							'default'     => 20,
							'_order'      => 26,
						],

						'action_bar_icon_weight'         => [
							'label'       => esc_html__( 'Icon weight', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'Draws the icons slightly heavier. Normal leaves them exactly as designed.', 'action-bar-for-hivepress' ),
							'type'        => 'select',

							'options'     => [
								'normal'   => esc_html__( 'Normal', 'action-bar-for-hivepress' ),
								'semibold' => esc_html__( 'Semibold', 'action-bar-for-hivepress' ),
								'bold'     => esc_html__( 'Bold', 'action-bar-for-hivepress' ),
							],

							'default'     => 'normal',
							'_order'      => 27,
						],

						'action_bar_label_position'      => [
							'label'       => esc_html__( 'Label position', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'Whether labels sit under or above the icons.', 'action-bar-for-hivepress' ),
							'type'        => 'select',

							'options'     => [
								'below' => esc_html__( 'Below icons', 'action-bar-for-hivepress' ),
								'above' => esc_html__( 'Above icons', 'action-bar-for-hivepress' ),
							],

							'default'     => 'below',
							'_order'      => 30,
						],

						'action_bar_label_size'          => [
							'label'       => esc_html__( 'Label size (px)', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'Label text size, 9 to 16; 11 suits most sites.', 'action-bar-for-hivepress' ),
							'type'        => 'number',
							'min_value'   => 9,
							'max_value'   => 16,
							'default'     => 11,
							'_order'      => 32,
						],

						'action_bar_label_weight'        => [
							'label'       => esc_html__( 'Label weight', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'Thickness of the label text. Bold can be hard to read at small sizes.', 'action-bar-for-hivepress' ),
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

						'action_bar_radius_top_left'     => [
							'label'       => esc_html__( 'Corner radius: top left (px)', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'Rounds this corner of the bar, 0 to 40. Each corner is set separately.', 'action-bar-for-hivepress' ),
							'type'        => 'number',
							'min_value'   => 0,
							'max_value'   => 40,
							'default'     => 0,
							'_order'      => 36,
						],

						'action_bar_radius_top_right'    => [
							'label'     => esc_html__( 'Corner radius: top right (px)', 'action-bar-for-hivepress' ),
							'type'      => 'number',
							'min_value' => 0,
							'max_value' => 40,
							'default'   => 0,
							'_order'    => 37,
						],

						'action_bar_radius_bottom_left'  => [
							'label'     => esc_html__( 'Corner radius: bottom left (px)', 'action-bar-for-hivepress' ),
							'type'      => 'number',
							'min_value' => 0,
							'max_value' => 40,
							'default'   => 0,
							'_order'    => 38,
						],

						'action_bar_radius_bottom_right' => [
							'label'     => esc_html__( 'Corner radius: bottom right (px)', 'action-bar-for-hivepress' ),
							'type'      => 'number',
							'min_value' => 0,
							'max_value' => 40,
							'default'   => 0,
							'_order'    => 39,
						],

						'action_bar_enable_guest_bar'    => [
							'label'       => esc_html__( 'Logged-out bar', 'action-bar-for-hivepress' ),
							'caption'     => esc_html__( 'Show different items to logged-out visitors', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'If enabled, logged-out visitors see the Logged-Out Bar items instead of the User Bar items. Tick to reveal its section below.', 'action-bar-for-hivepress' ),
							'type'        => 'checkbox',
							'_order'      => 45,
						],

						'action_bar_enable_vendor_bar'   => [
							'label'       => esc_html__( 'Vendor bar', 'action-bar-for-hivepress' ),
							'caption'     => esc_html__( 'Show different items to vendors', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'If enabled, users with a published vendor profile see the Vendor Bar items instead of the User Bar items. Tick to reveal its section below.', 'action-bar-for-hivepress' ),
							'type'        => 'checkbox',
							'_order'      => 50,
						],

						'action_bar_safe_area'           => [
							'label'       => esc_html__( 'Room for the home bar', 'action-bar-for-hivepress' ),
							'caption'     => esc_html__( 'Leave space below the bar on iPhones without a home button', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'Stops the iPhone home indicator overlapping your buttons. Safe to leave ticked; other phones are unaffected.', 'action-bar-for-hivepress' ),
							'type'        => 'checkbox',
							'_order'      => 60,
						],

						'action_bar_glass'               => [
							'label'       => esc_html__( 'Glass effect', 'action-bar-for-hivepress' ),
							'caption'     => esc_html__( 'Blur the page showing through the bar', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'The frosted look used by phone apps. Browsers that cannot blur, and visitors who reduce transparency, keep the solid bar.', 'action-bar-for-hivepress' ),
							'type'        => 'checkbox',
							'_order'      => 62,
						],

						'action_bar_glass_opacity'       => [
							'label'       => esc_html__( 'Glass opacity (%)', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'How solid the bar colour stays over the blur, 10 to 100; 72 suits most sites.', 'action-bar-for-hivepress' ),
							'type'        => 'number',
							'min_value'   => 10,
							'max_value'   => 100,
							'default'     => 72,
							'_parent'     => 'action_bar_glass',
							'_order'      => 63,
						],

						'action_bar_glass_blur'          => [
							'label'       => esc_html__( 'Glass blur (px)', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'Blur strength, 0 to 40; 20 gives the frosted look.', 'action-bar-for-hivepress' ),
							'type'        => 'number',
							'min_value'   => 0,
							'max_value'   => 40,
							'default'     => 20,
							'_parent'     => 'action_bar_glass',
							'_order'      => 64,
						],

						'action_bar_glass_highlight'     => [
							'label'       => esc_html__( 'Glass top edge', 'action-bar-for-hivepress' ),
							'caption'     => esc_html__( 'Add a soft light along the top edge', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'A faint highlight that makes the glass read as glass. Most visible on darker bars.', 'action-bar-for-hivepress' ),
							'type'        => 'checkbox',
							'default'     => true,
							'_parent'     => 'action_bar_glass',
							'_order'      => 65,
						],
					],
				],

				'visibility' => [
					'title'       => esc_html__( 'Visibility', 'action-bar-for-hivepress' ),
					'description' => esc_html__( 'Choose where the action bar stays hidden.', 'action-bar-for-hivepress' ),
					'_order'      => 20,

					'fields'      => [
						'action_bar_hidden_pages'  => [
							'label'       => esc_html__( 'Hide on pages', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'WordPress pages only; it cannot hide the bar on listings, vendor profiles or search results.', 'action-bar-for-hivepress' ),
							'type'        => 'select',
							'options'     => 'posts',
							'option_args' => [ 'post_type' => 'page' ],
							'multiple'    => true,
							'_order'      => 10,
						],

						'action_bar_hide_checkout' => [
							'label'       => esc_html__( 'Checkout pages', 'action-bar-for-hivepress' ),
							'caption'     => esc_html__( 'Hide the action bar on the WooCommerce cart and checkout pages', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'Only applies when WooCommerce is installed. Hide other payment pages with Hide on pages above.', 'action-bar-for-hivepress' ),
							'type'        => 'checkbox',
							'default'     => true,
							'_order'      => 20,
						],
					],
				],

				'colors'     => [
					'title'       => esc_html__( 'Colours', 'action-bar-for-hivepress' ),
					'description' => esc_html__( 'Use the colour wheel or type a hex code such as #f5f5f5. Clear a field and save to restore its default.', 'action-bar-for-hivepress' ),
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
					'description' => esc_html__( 'Deleting this plugin keeps your items and settings unless you tick the box below, whatever the WordPress delete screen says. Deactivating never removes anything.', 'action-bar-for-hivepress' ),
					'_order'      => 60,

					'fields'      => [
						'action_bar_delete_data' => [
							'label'       => esc_html__( 'Delete all data', 'action-bar-for-hivepress' ),
							'caption'     => esc_html__( 'Delete everything when this plugin is deleted', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'With this ticked, deleting the plugin removes every bar item, colour and setting, with no way back. Leave unticked to keep them for a reinstall.', 'action-bar-for-hivepress' ),
							'type'        => 'checkbox',
							'_order'      => 10,
						],
					],
				],
			]
		),
	],
];
