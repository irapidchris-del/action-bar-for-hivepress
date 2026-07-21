=== Action Bar for HivePress ===
Contributors: chrisb
Tags: hivepress, mobile, navigation, bottom bar, app
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a customisable, app-style bottom navigation bar to HivePress websites on mobile and tablet devices.

== Description ==

Action Bar for HivePress turns your marketplace into a more app-like experience on smaller screens by adding a fixed bottom navigation bar.

Features:

* Up to five items per bar, managed with sortable repeater rows, each with a Font Awesome icon, an optional label, and a link.
* The Vendor Bar settings stay hidden until the vendor bar is enabled, keeping the settings page tidy.
* Link choices for the homepage, listings, listing submission, vendors, account or login, messages, favourites, or any custom URL.
* Optional prominent style per item, ideal for a raised central action such as Add listing.
* A separate Vendor Bar shown to users with a published vendor profile, alongside the standard User Bar.
* Full colour controls with sensible light grey defaults, including bar, icon, label, active, prominent, and badge colours.
* An unread notification badge powered by the Messages extension, fetched in a page cache safe way.
* Adjustable bar height between 44 and 120 pixels.
* Individual on and off toggles for mobile and tablet devices.
* Visibility controls to hide the bar on selected pages and on the WooCommerce cart and checkout.
* Labels can be positioned above or below the icons.
* Safe area support so the bar clears native device UI on notched phones.

All settings are found under HivePress, Settings, Action Bar.

Developer filters: `hivepress/v1/action_bar/items`, `hivepress/v1/action_bar/visible`, `hivepress/v1/action_bar/breakpoints`, and `hivepress/v1/action_bar/badge_count`.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/action-bar-for-hivepress` directory, or install the plugin zip through the WordPress admin.
2. Activate the plugin through the Plugins screen. HivePress must be installed and active.
3. Configure the bar under HivePress, Settings, Action Bar.

== Frequently Asked Questions ==

= Which icons can I use? =

The icon dropdown offers a curated set from the Font Awesome 5 Free solid library bundled with HivePress. You can also enter any Font Awesome classes in the custom icon field, for example `fas fa-rocket`. If your site loads a different Font Awesome version, use class names valid for that version.

= Why does the unread badge load after the page? =

The badge count is fetched with a small request after the page loads. This keeps the count accurate for each user even when full page caching is active.

= The bar does not clear the home indicator on my iPhone. =

Enable the Safe area option in the Display section. It adds `viewport-fit=cover` to the viewport meta tag, which iOS requires before it reports safe area insets.

= Where does the Account item link to? =

For signed-in users it links directly to the account settings page, and for signed-out visitors it links to the login page. The generic account URL in HivePress only forwards to the first account menu item, which changes with the user state and installed extensions, so a fixed destination is more predictable. Use the `hivepress/v1/action_bar/items` filter if you prefer a different target.

= Which breakpoints are used? =

Mobile is 767px and below, tablet is 768px to 1024px. Both can be changed with the `hivepress/v1/action_bar/breakpoints` filter.

== Changelog ==

= 1.0.0 =
* Initial release.
