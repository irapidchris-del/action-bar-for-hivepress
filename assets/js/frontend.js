/**
 * Action Bar for HivePress: viewport fit, body padding and the current item.
 *
 * This file used to ship only as frontend.min.js, with no source anywhere. That
 * is why the active-item bug below survived so long: nobody reading the plugin
 * could see the rule, only the compressed form of it. Every sibling plugin
 * ships readable JavaScript, so this one does too now. There is no build step
 * and no minified twin to keep in step; edit this file.
 */

(function () {
	'use strict';

	var data = window.hivepressActionBarFrontendData || {},
		bar = document.querySelector('.hp-action-bar');

	if (!bar) {
		return;
	}

	/* ---- viewport fit ---------------------------------------------------- */

	// The bar sits in the home-indicator area on a notched phone, so the page
	// has to opt into the full viewport before env(safe-area-inset-*) reports
	// anything but zero.
	if (data.safeArea) {
		var meta = document.querySelector('meta[name="viewport"]');

		if (meta) {
			var content = meta.getAttribute('content') || '';

			if (content.indexOf('viewport-fit') === -1) {
				meta.setAttribute('content', content ? content + ',viewport-fit=cover' : 'viewport-fit=cover');
			}
		} else if (document.head) {
			meta = document.createElement('meta');
			meta.setAttribute('name', 'viewport');
			meta.setAttribute('content', 'width=device-width, initial-scale=1, viewport-fit=cover');
			document.head.appendChild(meta);
		}
	}

	/* ---- body padding ---------------------------------------------------- */

	// The CSS reserves room for the bar per breakpoint; this keeps the reserved
	// room honest when the bar's real height differs from the configured one
	// (a wrapped label, a system font, a prominent item).
	function syncPadding() {
		if (!document.body) {
			return;
		}

		if (window.getComputedStyle(bar).display === 'none') {
			document.body.style.paddingBottom = '';
		} else {
			document.body.style.paddingBottom = bar.offsetHeight + 'px';
		}
	}

	syncPadding();
	window.addEventListener('resize', syncPadding);
	window.addEventListener('orientationchange', syncPadding);

	if (window.ResizeObserver) {
		new window.ResizeObserver(syncPadding).observe(bar);
	}

	/* ---- current item ---------------------------------------------------- */

	/**
	 * Reads a query string into a plain object.
	 *
	 * @param {string} search Query string, with or without the leading "?".
	 * @return {Object} Decoded parameters.
	 */
	function parseQuery(search) {
		var out = {},
			raw = String(search || ''),
			parts,
			i,
			eq,
			key,
			value;

		if (raw.charAt(0) === '?') {
			raw = raw.slice(1);
		}

		if (!raw) {
			return out;
		}

		parts = raw.split('&');

		for (i = 0; i < parts.length; i++) {
			if (!parts[i]) {
				continue;
			}

			eq = parts[i].indexOf('=');
			key = eq === -1 ? parts[i] : parts[i].slice(0, eq);
			value = eq === -1 ? '' : parts[i].slice(eq + 1);

			try {
				key = decodeURIComponent(key.replace(/\+/g, ' '));
				value = decodeURIComponent(value.replace(/\+/g, ' '));
			} catch (e) {
				// A malformed escape is compared as it was written.
			}

			out[key] = value;
		}

		return out;
	}

	/*
	 * Which item is the current page.
	 *
	 * The rule this replaces skipped its own query-string check whenever the
	 * ITEM's href had no query string, so Home matched any URL sharing its path
	 * no matter what came after the "?". On Plain permalinks that is every page
	 * on the site: a visitor using the main search box landed on the listings
	 * archive with Home lit and announced to a screen reader as
	 * aria-current="page" while Browse stayed dark, and because the loop stopped
	 * at the first match, the genuinely current item could never win even when
	 * its href matched character for character.
	 *
	 * So: an item matches when its path matches AND every query parameter the
	 * item itself asks for is present in the location with the same value. The
	 * item asking for the MOST parameters wins, because that is the most
	 * specific description of where we actually are. Ties keep document order.
	 *
	 * Worked through, on Plain permalinks at /?post_type=hp_listing&s=sofa:
	 *   Home   "/"                       matches, specificity 0
	 *   Browse "/?post_type=hp_listing"  matches, specificity 1  <- wins
	 * and at /?utm_source=news:
	 *   Home   "/"                       matches, specificity 0  <- wins
	 * which is the case a stricter "the query strings must be identical" rule
	 * would have got wrong, leaving nothing lit at all.
	 */
	var path = window.location.pathname.replace(/\/+$/, '') || '/',
		locationQuery = parseQuery(window.location.search),
		best = null,
		bestScore = -1;

	Array.prototype.forEach.call(bar.querySelectorAll('.hp-action-bar__item'), function (item) {
		var href = item.getAttribute('href');

		if (!href || href.charAt(0) === '#') {
			return;
		}

		// Resolving through an anchor gives the browser's own idea of the
		// host, path and query, so a relative href is handled for free.
		var link = document.createElement('a');

		link.href = href;

		if (link.host !== window.location.host) {
			return;
		}

		if ((link.pathname.replace(/\/+$/, '') || '/') !== path) {
			return;
		}

		var itemQuery = parseQuery(link.search),
			score = 0,
			key;

		for (key in itemQuery) {
			if (!Object.prototype.hasOwnProperty.call(itemQuery, key)) {
				continue;
			}

			if (!Object.prototype.hasOwnProperty.call(locationQuery, key) || locationQuery[key] !== itemQuery[key]) {
				return;
			}

			score++;
		}

		if (score > bestScore) {
			bestScore = score;
			best = item;
		}
	});

	if (best) {
		best.classList.add('hp-action-bar__item--active');
		best.setAttribute('aria-current', 'page');
	}
})();
