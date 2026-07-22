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
* Link choices for the homepage, listings, listing submission, vendors, account or login, messages, favourites, any HivePress account or extension page, any published WordPress page, the WooCommerce cart and orders, or any custom URL.
* Optional prominent style per item, ideal for a raised central action such as Add listing.
* A separate Vendor Bar shown to users with a published vendor profile instead of the standard User Bar.
* Full colour controls with sensible light grey defaults, including bar, icon, label, active, prominent, and badge colours.
* A notification badge you can switch on per item, mirroring HivePress's own counter, including messages, bookings, and orders where those extensions are active.
* Adjustable bar height between 44 and 120 pixels.
* Individual on and off toggles for mobile and tablet devices.
* Visibility controls to hide the bar on selected pages and on the WooCommerce cart and checkout.
* Labels can be positioned above or below the icons.
* Safe area support so the bar clears native device UI on notched phones.

All settings are found under HivePress, Settings, Action Bar.

Developer filters: `hivepress/v1/action_bar/items`, `hivepress/v1/action_bar/visible`, `hivepress/v1/action_bar/breakpoints`.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/action-bar-for-hivepress` directory, or install the plugin zip through the WordPress admin.
2. Activate the plugin through the Plugins screen. HivePress must be installed and active.
3. Configure the bar under HivePress, Settings, Action Bar.

== Frequently Asked Questions ==

= Which icons can I use? =

Each item has an icon dropdown listing the Font Awesome 5 Free solid library bundled with HivePress, so you can pick any solid icon by name. If you need an icon from another Font Awesome style or version (such as a brand or regular icon), set it with the `hivepress/v1/action_bar/items` filter, where you can supply full class names like `fab fa-whatsapp` or `far fa-heart`.

= How does the notification badge work? =

Tick the Unread badge option on the items where you want it. The badge mirrors HivePress's own header counter: on most items it shows the combined notification count, which extensions such as Messages, Bookings, and Marketplace add to, while an item linked to Messages shows only the unread message count, matching the native account menu. The count is rendered fresh on every page view for signed-in users, so it clears as soon as the messages are read or the bookings are handled. If your caching plugin serves cached pages to signed-in users, exclude signed-in visitors from the cache so the count stays personal and current.

= The bar does not clear the home indicator on my iPhone. =

Enable the Safe area option in the Display section. It adds `viewport-fit=cover` to the viewport meta tag, which iOS requires before it reports safe area insets.

= Where does the Account item link to? =

For signed-in users it links directly to the account settings page, and for signed-out visitors it links to the login page. The generic account URL in HivePress only forwards to the first account menu item, which changes with the user state and installed extensions, so a fixed destination is more predictable. Use the `hivepress/v1/action_bar/items` filter if you prefer a different target.

= Which breakpoints are used? =

Mobile is 767px and below, tablet is 768px to 1024px. Both can be changed with the `hivepress/v1/action_bar/breakpoints` filter.

== Changelog ==

= 1.0.0 =
* Initial release.
* Settings from the beta versions are migrated automatically to the new repeater-based item settings, and the beta per-item options are removed. Custom icon classes from the beta are no longer editable in the settings screen; set them with the `hivepress/v1/action_bar/items` filter instead.
