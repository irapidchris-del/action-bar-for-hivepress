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
/** @var \HivePress\Components\Action_Bar $action_bar_component */
$action_bar_component = hivepress()->action_bar; // @phpstan-ignore property.notFound (Component access is provided by the HivePress core magic method.)

// Set colour fields.
$action_bar_color_fields = [];

$action_bar_color_order = 10;

foreach ( [
	'background'           => [ esc_html__( 'Bar background', 'action-bar-for-hivepress' ), '#f5f5f5' ],
	'border'               => [ esc_html__( 'Bar border', 'action-bar-for-hivepress' ), '#dddddd' ],
	'icon'                 => [ esc_html__( 'Icon colour', 'action-bar-for-hivepress' ), '#5f5f5f' ],
	'label'                => [ esc_html__( 'Label colour', 'action-bar-for-hivepress' ), '#5f5f5f' ],
	'active'               => [ esc_html__( 'Active colour', 'action-bar-for-hivepress' ), '#111111', esc_html__( 'Applied to the item that matches the current page.', 'action-bar-for-hivepress' ) ],
	'prominent_background' => [ esc_html__( 'Prominent background', 'action-bar-for-hivepress' ), '#333333' ],
	'prominent_icon'       => [ esc_html__( 'Prominent icon colour', 'action-bar-for-hivepress' ), '#ffffff' ],
	'badge_background'     => [ esc_html__( 'Badge background', 'action-bar-for-hivepress' ), '#d63638' ],
	'badge_text'           => [ esc_html__( 'Badge text colour', 'action-bar-for-hivepress' ), '#ffffff' ],
] as $action_bar_color_name => $action_bar_color_args ) {
	$action_bar_color_field = [
		'label'      => $action_bar_color_args[0],
		'type'       => 'color',
		'default'    => $action_bar_color_args[1],

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
	'link'        => [
		'placeholder' => esc_html__( 'Link', 'action-bar-for-hivepress' ),
		'type'        => 'select',
		'options'     => $action_bar_component->get_link_options() + $action_bar_component->get_route_link_options() + $action_bar_component->get_page_options(),
		'_order'      => 10,
	],

	'icon'        => [
		'placeholder' => esc_html__( 'Icon', 'action-bar-for-hivepress' ),
		'type'        => 'select',
		'options'     => 'icons',
		'_order'      => 20,
	],

	'label'       => [
		'placeholder' => esc_html__( 'Label', 'action-bar-for-hivepress' ),
		'type'        => 'text',
		'max_length'  => 32,
		'_order'      => 40,
	],

	'url'         => [
		'placeholder' => esc_html__( 'Custom URL', 'action-bar-for-hivepress' ),
		'type'        => 'text',
		'max_length'  => 2048,
		'_order'      => 15,
	],

	'style'       => [
		'placeholder' => esc_html__( 'Style', 'action-bar-for-hivepress' ),
		'type'        => 'select',

		'options'     => [
			'default'   => esc_html__( 'Default', 'action-bar-for-hivepress' ),
			'prominent' => esc_html__( 'Prominent', 'action-bar-for-hivepress' ),
		],

		'_order'      => 60,
	],

	'badge'       => [
		'caption' => esc_html__( 'Unread badge', 'action-bar-for-hivepress' ),
		'type'    => 'checkbox',
		'_order'  => 70,
	],
];

// Set item sections.
$action_bar_item_sections = [];

foreach ( [
	'user'   => [
		'title'       => esc_html__( 'User Bar', 'action-bar-for-hivepress' ),
		'description' => esc_html__( 'Add up to 5 items shown to visitors and regular users. For each item, choose a page or link from the first dropdown, or select Custom URL and type a full address such as https://example.com/contact/ into the URL box. Then pick an icon and, if you like, add the label text shown on the button. The Prominent style lifts an item into a highlighted circle, ideal for one main action. Items without a link are not displayed.', 'action-bar-for-hivepress' ),
		'_order'      => 40,
	],

	'vendor' => [
		'title'       => esc_html__( 'Vendor Bar', 'action-bar-for-hivepress' ),
		'description' => esc_html__( 'Add up to 5 items shown to users with a published vendor profile when the vendor bar is enabled. The fields work the same way as the User Bar items above.', 'action-bar-for-hivepress' ),
		'_order'      => 50,
	],
] as $action_bar_name => $action_bar_section ) {
	$action_bar_section['fields'] = [
		'action_bar_' . $action_bar_name . '_items' => [
			'label'   => esc_html__( 'Items', 'action-bar-for-hivepress' ),
			'caption' => esc_html__( 'Add item', 'action-bar-for-hivepress' ),
			'type'    => 'repeater',
			'fields'  => $action_bar_item_fields,

			'attributes' => [
				'class' => [ 'hp-action-bar-items' ],
			],

			'default' => $action_bar_component->get_item_defaults( $action_bar_name ),
			'_order'  => 10,
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
					'description' => esc_html__( 'The action bar is a fixed bottom navigation bar that gives your website an app-like feel on smaller screens.', 'action-bar-for-hivepress' ),
					'_order'      => 10,

					'fields'      => [
						'action_bar_enable_mobile'     => [
							'label'   => esc_html__( 'Mobile devices', 'action-bar-for-hivepress' ),
							'caption' => esc_html__( 'Show the action bar on mobile devices', 'action-bar-for-hivepress' ),
							'type'    => 'checkbox',
							'default' => true,
							'_order'  => 10,
						],

						'action_bar_enable_tablet'     => [
							'label'   => esc_html__( 'Tablet devices', 'action-bar-for-hivepress' ),
							'caption' => esc_html__( 'Show the action bar on tablet devices', 'action-bar-for-hivepress' ),
							'type'    => 'checkbox',
							'_order'  => 20,
						],

						'action_bar_height'            => [
							'label'       => esc_html__( 'Bar height', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'The height of the action bar in pixels, excluding the safe area.', 'action-bar-for-hivepress' ),
							'type'        => 'number',
							'min_value'   => 44,
							'max_value'   => 120,
							'default'     => 56,
							'_order'      => 25,
						],

						'action_bar_label_position'    => [
							'label'       => esc_html__( 'Label position', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'Choose where item labels appear relative to the icons.', 'action-bar-for-hivepress' ),
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
							'description' => esc_html__( 'The font size of item labels in pixels.', 'action-bar-for-hivepress' ),
							'type'        => 'number',
							'min_value'   => 9,
							'max_value'   => 16,
							'default'     => 11,
							'_order'      => 32,
						],

						'action_bar_label_weight'      => [
							'label'       => esc_html__( 'Label weight', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'The font weight of item labels.', 'action-bar-for-hivepress' ),
							'type'        => 'select',

							'options'     => [
								'400' => esc_html__( 'Normal', 'action-bar-for-hivepress' ),
								'500' => esc_html__( 'Medium', 'action-bar-for-hivepress' ),
								'600' => esc_html__( 'Semi bold', 'action-bar-for-hivepress' ),
								'700' => esc_html__( 'Bold', 'action-bar-for-hivepress' ),
							],

							'default'     => '500',
							'_order'      => 34,
						],

						'action_bar_enable_badge'      => [
							'label'       => esc_html__( 'Notification badge', 'action-bar-for-hivepress' ),
							'caption'     => esc_html__( 'Allow the notification badge on items', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'Tick the Unread badge option on individual items to choose where the count appears. Items linked to Messages show the unread message count (requires the Messages extension with message storage enabled); other items show the combined HivePress notification count.', 'action-bar-for-hivepress' ),
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
							'label'       => esc_html__( 'Safe area', 'action-bar-for-hivepress' ),
							'caption'     => esc_html__( 'Extend the viewport for devices with a notch', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'Adds viewport-fit=cover to the viewport meta tag so the safe area padding takes effect on iOS. Leave this disabled if your theme already sets it.', 'action-bar-for-hivepress' ),
							'type'        => 'checkbox',
							'_order'      => 60,
						],
					],
				],

				'visibility' => [
					'title'  => esc_html__( 'Visibility', 'action-bar-for-hivepress' ),
					'_order' => 20,

					'fields' => [
						'action_bar_hidden_pages'  => [
							'label'       => esc_html__( 'Hide on pages', 'action-bar-for-hivepress' ),
							'description' => esc_html__( 'Select the pages where the action bar is hidden.', 'action-bar-for-hivepress' ),
							'type'        => 'select',
							'options'     => 'posts',
							'option_args' => [ 'post_type' => 'page' ],
							'multiple'    => true,
							'_order'      => 10,
						],

						'action_bar_hide_checkout' => [
							'label'   => esc_html__( 'Checkout pages', 'action-bar-for-hivepress' ),
							'caption' => esc_html__( 'Hide the action bar on the WooCommerce cart and checkout pages', 'action-bar-for-hivepress' ),
							'type'    => 'checkbox',
							'default' => true,
							'_order'  => 20,
						],
					],
				],

				'colors'     => [
					'title'       => esc_html__( 'Colours', 'action-bar-for-hivepress' ),
					'description' => esc_html__( 'Customise the action bar colours. Clear a field and save to restore its default.', 'action-bar-for-hivepress' ),
					'_order'      => 30,
					'fields'      => $action_bar_color_fields,
				],
			],
			$action_bar_item_sections
		),
	],
];
