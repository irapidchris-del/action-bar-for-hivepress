=== Action Bar for HivePress ===
Contributors: chrisb
Tags: hivepress, mobile, navigation, bottom bar, app
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a customisable, app-style bottom navigation bar to HivePress websites, on any screen size you choose.

== Description ==

Action Bar for HivePress gives your marketplace a more app-like feel by adding a fixed bottom navigation bar.

Features:

* Up to five items per bar, each with a Font Awesome icon, an optional label, and a link. Drag to reorder them.
* Link choices for the homepage, listings, listing submission, vendors, account or login, messages, favourites, any HivePress account or extension page, any published WordPress page, the WooCommerce cart and orders (when WooCommerce is installed), or any custom URL.
* Optional prominent style per item, lifting it into a raised circle, ideal for one main action such as Add listing.
* A separate Vendor Bar shown to users with a published vendor profile instead of the standard User Bar.
* Full colour controls with a neutral light-grey palette out of the box, including bar, icon, label, active, prominent, and badge colours, each with a colour wheel and a typable hex box.
* A notification badge you can switch on per item, with a choice of counter for each: the unread message count, or the combined HivePress notification count that messages, bookings, and orders feed.
* Adjustable bar height between 44 and 120 pixels.
* Individual on and off toggles for small, medium and large screens, so you can run the bar on phones only, or right across desktop as well. On large screens the buttons gather into a centred dock rather than stretching across the window.
* Visibility controls to hide the bar on selected pages and on the WooCommerce cart and checkout.
* Labels can be positioned above or below the icons.
* An optional frosted glass effect, with adjustable opacity, blur strength and a soft top edge, that blurs the page scrolling behind the bar.
* An option to leave room for the home bar on iPhones without a home button, so it never overlaps your buttons.
* Your settings are kept if you delete the plugin, unless you tick the box that asks for them to be removed.

All settings are found under HivePress, Settings, Action Bar.

For developers: the bar can be altered with the filters `hivepress/v1/action_bar/items`, `hivepress/v1/action_bar/visible` and `hivepress/v1/action_bar/breakpoints`.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/action-bar-for-hivepress` directory, or install the plugin zip through the WordPress admin.
2. Activate the plugin through the Plugins screen. HivePress must be installed and active.
3. Configure the bar under HivePress, Settings, Action Bar.

Once installed, the plugin checks for new versions automatically and updates through the normal WordPress Plugins screen, just like a plugin from the WordPress.org directory.

== Frequently Asked Questions ==

= Which icons can I use? =

Each item has the same icon dropdown HivePress uses for its own attribute icons, showing around a thousand Font Awesome 5 solid icons as live previews that you can search by name. If you need an icon from another Font Awesome style or version (such as a brand or regular icon), set it with the `hivepress/v1/action_bar/items` filter, where you can supply full class names like `fab fa-whatsapp` or `far fa-heart`. Note that a theme or plugin which replaces or subsets Font Awesome can remove glyphs, so check your chosen icons still appear after such a change.

= How does the notification badge work? =

HivePress keeps two unread counters, and each item's Badge dropdown chooses which one to show. All notifications is the combined count from HivePress's own header, which extensions such as Messages, Bookings, and Marketplace add to (unread messages, unpaid bookings, booking requests, and pending orders). Unread messages is the narrower messages-only count, matching the badge on the account menu's Messages item; it needs the Messages extension with message storage enabled. Since the combined count already includes unread messages, a Messages item usually reads best with Unread messages while an Account item shows All notifications. The count is rendered fresh on every page view for signed-in users, so it clears as soon as the messages are read or the bookings are handled. If your caching plugin serves cached pages to signed-in users, exclude signed-in visitors from the cache so the count stays personal and current.

= How do I get the frosted glass look? =

Tick Glass effect in the Display section. The bar then becomes semi-transparent and blurs the page scrolling behind it. Three controls appear with it: Glass opacity decides how solid your bar colour stays, Glass blur decides how soft the page behind becomes, and Glass top edge draws the faint highlight that makes it read as glass. Pick a light bar colour on a light site and a dark one on a dark site, then check your icons and labels against your busiest page, because a very transparent bar can make them hard to read. Browsers that cannot blur, and visitors who have asked their device to reduce transparency, are shown the ordinary solid bar instead.

= Does deleting the plugin remove my settings? =

Not unless you ask it to. Deleting keeps your items and settings so you can reinstall and carry on. WordPress prints its own warning on the delete screen saying data will go, but it prints that for every plugin that has an uninstall routine at all, and it does not apply here. If you do want a clean sweep, tick Delete all data in the Removing the Plugin section first. Switching the plugin off never removes anything.

= The bar overlaps the bar at the bottom of my iPhone screen. What do I do? =

Tick "Room for the home bar" in the Display section. On iPhones without a home button there is a thin bar at the very bottom of the screen, and that option asks the browser to report how much room it needs so the action bar sits above it. It is safe to leave ticked whatever your theme does, and it has no effect on other phones.

= Where does the Account item link to? =

For signed-in users it links directly to the account settings page, and for signed-out visitors it links to the login page. The generic account URL in HivePress only forwards to the first account menu item, which changes with the user state and installed extensions, so a fixed destination is more predictable. Use the `hivepress/v1/action_bar/items` filter if you prefer a different target.

= Which breakpoints are used? =

The bar switches on how wide the browser window is, not on what device someone is using. By default that is 47.99em and below for small screens, 48em to 64em for medium ones, and 64.01em and above for large ones, which works out as roughly up to 767px, 768px to 1024px, and 1025px and wider on a site using the standard 16px base font size. They are set in em so they follow your theme's base font size and stay in step with HivePress's own grid. All three can be changed with the `hivepress/v1/action_bar/breakpoints` filter, using the keys `mobile_max`, `tablet_min`, `tablet_max` and `desktop_min`.

= Can I show the bar on desktop too? =

Yes. Tick "Large screens" in the Display section and the bar appears on laptops and desktop computers as well. Most sites leave this off, because visitors on a big screen already have your main menu and the bar takes up room at the bottom of every page, but it is worth turning on if you want the site to feel like an app everywhere. On large screens the buttons gather into a centred dock instead of stretching the full width, which is what they do on phones.

= How are updates delivered? =

The plugin includes an update checker that watches the official GitHub repository for new releases. When a newer version is published, WordPress shows the update on the Plugins and Dashboard, Updates screens, and you can install it with the usual one-click update. You can force an immediate check with the Check for updates link on the Plugins screen. No account, licence key, or extra configuration is required.

== Changelog ==

= 1.3.0 =
* The action bar can now be shown on desktop as well. Tick the new "Large screens" box in the Display section, alongside the existing small and medium screen toggles. It is off by default, so nothing changes unless you turn it on. Suggested by the community.
* On large screens the buttons gather into a centred dock rather than stretching the full width of the window, which looked stranded on a wide monitor.
* The support link on the Plugins screen is now labelled "Donate" with a small coffee cup icon, matching the wording WordPress itself uses.

= 1.2.0 =
* New frosted glass effect. Switch it on in the Display section to make the bar semi-transparent and blur whatever scrolls behind it, with adjustable opacity, blur strength and an optional soft light along the top edge.
* Deleting the plugin now keeps your settings unless you tick the new "Delete all data" box in the Removing the Plugin section. WordPress shows its own warning on the delete screen for every plugin, and it does not apply here unless that box is ticked.
* The unread messages badge option now only appears when the Messages extension is active, so the list no longer offers a counter that could never show anything.
* Checking for updates before any release has been published now says so plainly, instead of reporting it as a problem reaching GitHub.
* Added a quiet support link for anyone who would like to say thank you.

= 1.1.0 =
* Each item's notification badge can now show either of HivePress's two unread counters: the combined notification count (messages, bookings, and orders) or the unread message count alone. Items ticked in earlier versions keep the counter they were already showing.
* The plugin now registers with HivePress correctly even when its folder is renamed, for example after installing a source download from GitHub.
* The colour settings fall back to a plain hex box on HivePress versions older than 1.7.26, and the settings copy now notes that hex codes need all six digits.
* If every Vendor Bar item is removed, vendors now see the User Bar items instead of no bar at all.

= 1.0.0 =
* Initial release.
* Automatic updates from the official GitHub repository through the standard WordPress Plugins screen.
* Settings from the beta versions are migrated automatically to the new repeater-based item settings, and the beta per-item options are removed. Custom icon classes from the beta are no longer editable in the settings screen; set them with the `hivepress/v1/action_bar/items` filter instead.
