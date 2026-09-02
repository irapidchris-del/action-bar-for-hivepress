/**
 * Action Bar for HivePress - live preview.
 *
 * Draws each bar (logged-out, user, vendor) in a panel to the right of the settings, following
 * every change as it is made and storing nothing until Save. The stage uses the REAL front-end
 * stylesheet (enqueued on this tab for the purpose) with the bar's `position: fixed` undone by
 * backend.min.css, so what is drawn is the bar itself rather than an imitation of it - the same
 * CSS variables get_inline_styles() emits are set on the preview from the form's current values.
 *
 * Dummy data, on purpose: the badge count and the "current page" highlight are illustrations of
 * what those settings do, not readings from the site. The description under the panel says so.
 *
 * Icons are emitted as `<i class="fa-solid fa-NAME">` and drawn by the shared icon library's
 * admin script, which watches the document and swaps them for inline SVG; brands resolve from the
 * library's own index, so no list of brand names lives here.
 */

/* global hpabPreviewData */

( function () {
	'use strict';

	if ( ! window.jQuery ) {
		return;
	}

	var BARS = [ 'guest', 'user', 'vendor' ];

	var OPTIONS = {
		height: 'hp_action_bar_height',
		iconSize: 'hp_action_bar_icon_size',
		iconWeight: 'hp_action_bar_icon_weight',
		labelPosition: 'hp_action_bar_label_position',
		labelSize: 'hp_action_bar_label_size',
		labelWeight: 'hp_action_bar_label_weight',
		glass: 'hp_action_bar_glass',
		glassOpacity: 'hp_action_bar_glass_opacity',
		glassBlur: 'hp_action_bar_glass_blur',
		glassHighlight: 'hp_action_bar_glass_highlight',
		enableBadge: 'hp_action_bar_enable_badge',
		enableGuest: 'hp_action_bar_enable_guest_bar',
		enableVendor: 'hp_action_bar_enable_vendor_bar',
	};

	var COLOURS = {
		'background': '#f5f5f5',
		'border': '#dddddd',
		'icon': '#5f5f5f',
		'label': '#5f5f5f',
		'active': '#111111',
		'prominent-background': '#333333',
		'prominent-icon': '#ffffff',
		'badge-background': '#d63638',
		'badge-text': '#ffffff',
	};

	var STORE = 'hpabPreviewPanels';

	window.jQuery( function ( $ ) {
		var root = document.querySelector( '.hpab-preview' );

		if ( ! root ) {
			return;
		}

		var data = window.hpabPreviewData || {},
			labels = data.labels || {},
			repaintTimer = null;

		var panels = {};

		Array.prototype.forEach.call( root.querySelectorAll( '.hpab-preview__panel' ), function ( element ) {
			panels[ element.getAttribute( 'data-bar' ) ] = {
				element: element,
				header: element.querySelector( '.hpab-preview__header' ),
				body: element.querySelector( '.hpab-preview__body' ),
				bar: element.querySelector( '.hp-action-bar' ),
			};
		} );

		function input( option ) {
			return document.querySelector( '[name="' + option + '"]' );
		}

		function value( option ) {
			var field = input( option );

			if ( ! field ) {
				return '';
			}

			if ( 'checkbox' === field.type ) {
				return field.checked ? '1' : '';
			}

			return ( field.value || '' ).trim();
		}

		function number( option, fallback, min, max ) {
			var n = parseInt( value( option ), 10 );

			if ( isNaN( n ) ) {
				return fallback;
			}

			return Math.min( max, Math.max( min, n ) );
		}

		function hex( raw ) {
			raw = ( raw || '' ).trim();

			if ( /^#[0-9a-fA-F]{3}$/.test( raw ) ) {
				return '#' + raw[ 1 ] + raw[ 1 ] + raw[ 2 ] + raw[ 2 ] + raw[ 3 ] + raw[ 3 ];
			}

			return /^#[0-9a-fA-F]{6}$/.test( raw ) ? raw : '';
		}

		function rgba( colour, alpha ) {
			var h = hex( colour );

			if ( ! h ) {
				return '';
			}

			return 'rgba(' + parseInt( h.slice( 1, 3 ), 16 ) + ',' + parseInt( h.slice( 3, 5 ), 16 ) + ',' + parseInt( h.slice( 5, 7 ), 16 ) + ',' + alpha + ')';
		}

		function iconName( raw ) {
			raw = ( raw || '' ).trim().toLowerCase();

			// Stored values may carry a family prefix from before the icon library; the name is
			// the last fa- token either way.
			var tokens = raw.split( /\s+/ ), name = '';

			tokens.forEach( function ( token ) {
				if ( 0 === token.indexOf( 'fa-' ) && ! /^fa-(fw|solid|regular|brands|lg|xs|sm|\dx)$/.test( token ) ) {
					name = token.slice( 3 );
				} else if ( -1 === token.indexOf( '-' ) || /^[a-z0-9-]+$/.test( token ) && 'fas' !== token && 'fab' !== token && 'far' !== token ) {
					name = name || token;
				}
			} );

			return /^[a-z0-9-]+$/.test( name ) ? name : '';
		}

		/**
		 * Reads one bar's items from its repeater, in row order, exactly as get_bar_items() would
		 * except for the URL (a preview links nowhere) and the badge count (illustrative).
		 */
		function barItems( bar ) {
			var repeaters = document.querySelectorAll( 'div[data-component="repeater"]' ),
				rows = [];

			for ( var i = 0; i < repeaters.length; i++ ) {
				if ( repeaters[ i ].querySelector( '[name^="hp_action_bar_' + bar + '_items["]' ) ) {
					rows = Array.prototype.slice.call( repeaters[ i ].querySelectorAll( 'tbody > tr' ) );

					break;
				}
			}

			var items = [], foundBell = false;

			rows.forEach( function ( row ) {
				if ( items.length >= 5 ) {
					return;
				}

				var link = row.querySelector( 'select[name$="[link]"]' ),
					icon = row.querySelector( 'select[name$="[icon]"]' ),
					label = row.querySelector( 'input[name$="[label]"]' ),
					style = row.querySelector( 'select[name$="[style]"]' ),
					badge = row.querySelector( 'select[name$="[badge]"]' );

				if ( ! link || ! link.value ) {
					return;
				}

				var bell = 'notification_bell' === link.value;

				if ( bell && foundBell ) {
					return;
				}

				foundBell = foundBell || bell;

				var option = link.options[ link.selectedIndex ];

				items.push( {
					link: link.value,
					linkText: option ? option.text : '',
					icon: iconName( icon ? icon.value : '' ) || 'circle',
					label: label ? label.value.trim() : '',
					style: style && 'prominent' === style.value ? 'prominent' : 'default',
					badge: ! bell && badge && badge.value && '1' === ( input( OPTIONS.enableBadge ) ? value( OPTIONS.enableBadge ) : '1' ) ? badge.value : '',
					bell: bell,
				} );
			} );

			return items;
		}

		function styleBar( nav ) {
			var height = number( OPTIONS.height, 56, 44, 120 ),
				weight = parseInt( value( OPTIONS.labelWeight ), 10 );

			if ( -1 === [ 400, 500, 600, 700 ].indexOf( weight ) ) {
				weight = 500;
			}

			nav.style.cssText = '';
			nav.style.setProperty( '--hp-action-bar-height', height + 'px' );
			nav.style.setProperty( '--hp-action-bar-label-size', number( OPTIONS.labelSize, 11, 9, 16 ) + 'px' );
			nav.style.setProperty( '--hp-action-bar-label-weight', String( weight ) );
			nav.style.setProperty( '--hp-action-bar-icon-size', number( OPTIONS.iconSize, 20, 14, 32 ) + 'px' );

			Object.keys( COLOURS ).forEach( function ( name ) {
				var chosen = hex( value( 'hp_action_bar_color_' + name.replace( /-/g, '_' ) ) );

				nav.style.setProperty( '--hp-action-bar-' + name, chosen || COLOURS[ name ] );
			} );

			var iconBackground = hex( value( 'hp_action_bar_color_icon_background' ) );

			if ( iconBackground ) {
				nav.style.setProperty( '--hp-action-bar-icon-background', iconBackground );
			}

			var strokes = { semibold: '0.3px', bold: '0.5px' },
				stroke = strokes[ value( OPTIONS.iconWeight ) ];

			if ( stroke ) {
				nav.style.setProperty( '--hp-action-bar-icon-stroke', stroke );
			}

			var radii = [ 'top_left', 'top_right', 'bottom_right', 'bottom_left' ].map( function ( corner ) {
				return number( 'hp_action_bar_radius_' + corner, 0, 0, 40 );
			} );

			if ( radii.some( function ( r ) { return r > 0; } ) ) {
				nav.style.borderRadius = radii.map( function ( r ) { return r + 'px'; } ).join( ' ' );
			}

			nav.className = 'hp-action-bar hpab-preview__bar';

			if ( 'above' === value( OPTIONS.labelPosition ) ) {
				nav.classList.add( 'hp-action-bar--labels-above' );
			}

			if ( value( OPTIONS.glass ) ) {
				nav.classList.add( 'hp-action-bar--glass' );

				var tint = rgba( hex( value( 'hp_action_bar_color_background' ) ) || COLOURS.background, number( OPTIONS.glassOpacity, 72, 10, 100 ) / 100 );

				if ( tint ) {
					nav.style.setProperty( '--hp-action-bar-glass-background', tint );
				}

				nav.style.setProperty( '--hp-action-bar-glass-blur', number( OPTIONS.glassBlur, 20, 0, 40 ) + 'px' );

				// The highlight defaults to on, matching is_setting_enabled( 'glass_highlight', true ).
				var highlight = input( OPTIONS.glassHighlight );

				if ( ! highlight || highlight.checked ) {
					nav.classList.add( 'hp-action-bar--glass-edge' );
				}
			}
		}

		function drawBar( panel, bar ) {
			var nav = panel.bar,
				items = barItems( bar );

			while ( nav.firstChild ) {
				nav.removeChild( nav.firstChild );
			}

			styleBar( nav );

			items.forEach( function ( item, index ) {
				var a = document.createElement( 'a' ),
					iconWrap = document.createElement( 'span' ),
					icon = document.createElement( 'i' );

				a.href = '#';
				a.className = 'hp-action-bar__item hp-action-bar__item--' + item.style;
				a.setAttribute( 'aria-label', item.label || item.linkText );
				a.addEventListener( 'click', function ( event ) {
					event.preventDefault();
				} );

				// The first item stands in for "the page being viewed", so the active colour has
				// something to show.
				if ( 0 === index && 'prominent' !== item.style ) {
					a.classList.add( 'hp-action-bar__item--active' );
				}

				iconWrap.className = 'hp-action-bar__icon';
				icon.className = 'fa-solid fa-' + item.icon;
				icon.setAttribute( 'aria-hidden', 'true' );
				iconWrap.appendChild( icon );

				if ( item.badge ) {
					var badge = document.createElement( 'span' );

					badge.className = 'hp-action-bar__badge';
					badge.textContent = String( data.badgeCount || 3 );
					iconWrap.appendChild( badge );
				}

				a.appendChild( iconWrap );

				if ( item.bell ) {
					var count = document.createElement( 'small' );

					count.textContent = String( data.badgeCount || 3 );
					a.appendChild( count );
				}

				if ( item.label ) {
					var label = document.createElement( 'span' );

					label.className = 'hp-action-bar__label';
					label.textContent = item.label;
					a.appendChild( label );
				}

				if ( item.bell ) {
					var wrap = document.createElement( 'div' );

					wrap.className = 'hp-action-bar__bell';
					a.classList.add( 'hp-notification-bell__toggle' );
					wrap.appendChild( a );
					nav.appendChild( wrap );
				} else {
					nav.appendChild( a );
				}
			} );

			panel.element.classList.toggle( 'hpab-preview__panel--empty', ! items.length );
		}

		function paint() {
			var guestOn = !! value( OPTIONS.enableGuest ),
				vendorOn = !! value( OPTIONS.enableVendor );

			BARS.forEach( function ( bar ) {
				var panel = panels[ bar ];

				if ( ! panel ) {
					return;
				}

				// A bar that is switched off is not drawn at all: its section is hidden on the
				// form, and a preview of something nobody will see would only confuse.
				var shown = 'guest' === bar ? guestOn : ( 'vendor' === bar ? vendorOn : true );

				panel.element.hidden = ! shown;

				if ( shown ) {
					drawBar( panel, bar );
				}
			} );
		}

		function repaint() {
			window.clearTimeout( repaintTimer );
			repaintTimer = window.setTimeout( paint, 50 );
		}

		function readStore() {
			try {
				return JSON.parse( window.localStorage.getItem( STORE ) ) || {};
			} catch ( error ) {
				return {};
			}
		}

		function writeStore( store ) {
			try {
				window.localStorage.setItem( STORE, JSON.stringify( store ) );
			} catch ( error ) {
				// Storage blocked; the panels still fold, they just forget on the next load.
			}
		}

		function setOpen( bar, open, remember ) {
			var panel = panels[ bar ],
				chevron = panel.header.querySelector( '.dashicons' );

			panel.element.classList.toggle( 'hpab-preview__panel--collapsed', ! open );
			panel.header.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			panel.body.hidden = ! open;

			if ( chevron ) {
				chevron.className = 'dashicons ' + ( open ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2' );
			}

			if ( remember ) {
				var store = readStore();

				store[ bar ] = open ? 1 : 0;
				writeStore( store );
			}
		}

		var remembered = readStore();

		BARS.forEach( function ( bar ) {
			var panel = panels[ bar ];

			if ( ! panel ) {
				return;
			}

			panel.header.addEventListener( 'click', function () {
				setOpen( bar, 'false' === panel.header.getAttribute( 'aria-expanded' ), true );
			} );

			setOpen( bar, 'undefined' !== typeof remembered[ bar ] ? !! remembered[ bar ] : true, false );
		} );

		// jQuery rather than addEventListener: the colour picker fires irischange through jQuery
		// only, and repeater rows are cloned after load.
		$( document ).on( 'input change irischange', '[name^="hp_action_bar_"]', repaint );
		$( document ).on( 'click', '.wp-picker-clear, div[data-component="repeater"] [data-remove], div[data-component="repeater"] [data-add]', function () {
			window.setTimeout( repaint, 60 );
		} );
		$( document ).on( 'sortupdate', 'div[data-component="repeater"] tbody', repaint );

		paint();
	} );
}() );
