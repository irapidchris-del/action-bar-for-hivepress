=== Action Bar for HivePress ===
Contributors: chrisb
Tags: hivepress, mobile, navigation, bottom bar, app
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.5.6
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
* A notification badge you can switch on per item, with a choice of counter for each: Account activity (HivePress), the combined count that messages, bookings, and orders feed; Unread messages; or Unread notifications from Notifications for HivePress.
* Adjustable bar height between 44 and 120 pixels.
* Individual on and off toggles for mobile, tablet and desktop screens, so you can run the bar on phones only, or right across desktop as well. On large screens the buttons gather into a centred dock rather than stretching across the window.
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

Each item has the same icon dropdown HivePress uses for its own attribute icons, showing around a thousand Font Awesome 5 solid icons as live previews that you can search by name. If you need a newer Font Awesome 6 or 7 name, or a brand icon, set it with the `hivepress/v1/action_bar/items` filter, where you can supply full class names like `fab fa-whatsapp` or `fa-solid fa-plane-up`. The plugin includes the solid and brand fonts, so those two styles are the ones that render; the regular, light, thin and duotone styles are not included. Note that a theme or plugin which replaces or subsets Font Awesome can remove glyphs, so check your chosen icons still appear after such a change.

= How does the notification badge work? =

Each item's Badge dropdown chooses which unread counter appears on that item, and there are three to choose from. Account activity (HivePress) is the combined count from HivePress's own header, which extensions such as Messages, Bookings, and Marketplace add to (unread messages, unpaid bookings, booking requests, and pending orders); it does not include notifications from Notifications for HivePress. Unread messages is the narrower messages-only count, matching the badge on the account menu's Messages item; it is offered whenever the Messages extension is active, and reads zero unless "Store messages in the database" is ticked under HivePress, Settings, Messages. Unread notifications is the unread count from Notifications for HivePress, which is what puts a working notification bell on the bar; it is offered only while that plugin is active, and an item set to it shows nothing rather than quietly counting something else if that plugin is later removed. Since the combined count already includes unread messages, a Messages item usually reads best with Unread messages, a Notifications item with Unread notifications, and an Account item with Account activity (HivePress). The count is rendered fresh on every page view for signed-in users, so it clears as soon as the messages are read or the bookings are handled. If your caching plugin serves cached pages to signed-in users, exclude signed-in visitors from the cache so the count stays personal and current.

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

= 1.5.6 =
* Housekeeping only: nothing on your settings screen has moved, changed or
  behaves differently. The settings-screen code this extension shares with the
  others in the family was reformatted to match them line for line, so that a
  fix made to one of them can be checked against all of them in one go.

= 1.5.5 =
* The settings tab now carries the same controls as the other extensions in this family: the quick
  links stay in view as you scroll and say "Jump to a section:", a Save Changes tab sits on the
  right edge of the screen wherever you are on the page (a bar across the bottom on a phone), and a
  back-to-top button appears once you have scrolled down. Whichever of these extensions you have
  installed, you see one set of controls, in the same places.
* Fixed: if another extension in this family also added quick links to a settings tab, you could
  have ended up with two rows of them stacked on top of each other. The extensions can now see each
  other's controls and only one set is drawn, whichever extension gets there first.
* Fixed: the section headings keep the ids WordPress already gives them, so a link or a bookmark
  pointing at a section carries on working.
* The quick links are also drawn correctly now when you reach the settings from the HivePress menu
  without picking a tab first.

= 1.5.4 =
* Changed: a code comment that described a different function had been left stranded above
  one that documents itself, so a developer reading the file saw the wrong description.
  Comments only. Nothing about how the plugin works has changed.

= 1.5.3 =
* Changed: outline icon styles now render as outlines. An icon set to an outline style
  previously appeared filled in, because only the solid style was included with the plugin and
  your browser quietly used that instead.

= 1.5.2 =
* Changed: the icon library is now included with the plugin instead of being loaded from a
  third-party server, which is faster and keeps every request on your own site. Your icons and
  settings are unaffected.

= 1.5.1 =
* Fixed: the notifications panel opened from the bar's bell now floats directly above the bell,
  centred on it, instead of appearing at the far right of the screen. Near a screen edge it
  shifts just enough to stay fully on screen.
* Fixed: the bar's bell and the header bell now work side by side. The bar renders its own
  complete bell, and Notifications for HivePress runs every bell on the page, so enabling the
  header bell no longer leaves one of the two dead. Needs Notifications for HivePress with
  multi-bell support; on an older copy of that plugin the header bell keeps working and the bar's
  bell acts as a plain link to the notifications page.
* Fixed: on the settings screen, the Address and Label boxes on each bar item row are now the
  same length.

= 1.5.0 =
* Added: a third bar for logged-out visitors, switched on with its own toggle, alongside the User
  and Vendor bars. Until it is enabled, logged-out visitors keep seeing the User Bar as before.
* Added: a Sign in pop-up link choice that opens HivePress's own login window for logged-out
  visitors, falling back to the login page when the pop-up is unavailable.
* Added: a My profile link choice pointing at the signed-in user's public vendor page, or their
  HivePress user profile when profile display is enabled.
* Added: the Notifications for HivePress bell can now sit on the bar itself, opening its dropdown
  panel above the bar. Offered while that plugin is active; the header bell is reused when it is
  switched on, so both never fight over one panel.
* Added: icon size, icon weight, an optional icon background colour, and separate corner radius
  controls for each corner of the bar.
* Added: Font Awesome 6 and 7 icon names, including brand icons such as Stripe and PayPal, with
  the full Font Awesome stylesheet loaded once and shared with this author's other plugins.
* Added: quick links at the top of the settings tab that jump to each section, with dividers
  between sections and shorter, easier-to-scan descriptions.
* Changed: the screen size options are named Mobile, Tablet and Desktop again.
* Changed: the counter Badge dropdown only offers counters whose plugin is active, judged at the
  moment the screen is built, and stored choices still survive a temporary deactivation.
* Removed: the separate Notification badge checkbox, which duplicated the per-item Badge dropdown.
  A site that had unticked it keeps its intent: the migration clears every stored badge choice.

= 1.4.6 =
* Fixed: no PHP warning on a renamed install folder. If the plugin folder had been renamed, which
  is what downloading the source as a zip produces, HivePress raised "Array to string conversion"
  once per request on sites with no paid HivePress extension.
* Fixed: the bar no longer highlights the wrong item when the address carries a query string. On
  Plain permalinks every page has one, so using the search box lit Home and left Browse dark, and
  screen readers were told Home was the current page. The most specific matching item now wins, so
  a plain address still highlights Home as before.
* Fixed: other plugins can now place their own pop-ups above the bar correctly. The bar publishes
  its height as `--hp-action-bar-offset`, which is set only at the screen widths where the bar is
  actually on screen, so nothing has to guess the breakpoints. Notifications for HivePress and
  Social Proof for HivePress both use it.
* Fixed: the bar's front-end script now ships as readable source rather than a minified file, so
  the behaviour above can be read and checked.
* Fixed: deleting the plugin now also clears the update check's own leftovers and cancels its
  background update check.

= 1.4.5 =
* Fixed: the Notification badge section on the settings screen still named the counters the way
  they were named before 1.4.0, so its explanation disagreed with the Badge dropdown sitting
  directly beneath it. It called "Account activity (HivePress)" by its old name, "All
  notifications", and said nothing at all about "Unread notifications", which that same release
  added. The wording now matches the three choices actually offered and says when each one
  appears. The feature list and the badge question in this readme have been corrected the same way.
* Fixed: the same explanation said the "Unread messages" counter is offered only when "Store
  messages in the database" is ticked under HivePress, Settings, Messages. It is offered
  whenever the Messages extension is active; that setting decides whether the number is ever
  anything but zero, not whether the counter is on the list. Corrected in both places.

= 1.4.4 =
* Fixed: saving the settings tab erased an item's destination, badge or custom icon whenever its
  source was temporarily unavailable - a page moved to draft, a page belonging to a deactivated
  extension, a badge counter whose extension was switched off, or an icon saved in the beta's
  free-text box. Such values now stay selected in the dropdown, marked "(currently unavailable)",
  and carry on working when their source returns, as the section description always promised.

= 1.4.3 =
* Maintenance: regenerated the translation template so the 1.4.2 "(no title)" page label is
  translatable; no code changes.

= 1.4.2 =
* Added: Notifications, and any other account page an extension adds, now appear in the item link
  dropdown. The list was built from the account menu, which some extensions only register on the
  front end; it now reads the routes as well, so nothing is missed.
* Fixed: a published page with no title showed as a blank, unidentifiable choice in the link
  dropdown. It now shows as "(no title)" with the slug the link would point at.

= 1.4.1 =
* **Added - the rest of the WooCommerce account area** to the list of things a bar item can point
  at. Only Placed orders and Cart were offered before; Subscriptions, Downloads, Addresses, Payment
  methods and Account details are all there now, along with any account page another plugin adds.
  They are read from the registered endpoints rather than from the account menu, because that menu
  is built for whoever is looking and leaves out anything they personally have none of.

= 1.4.0 =
* New - "Unread notifications" as a badge counter, showing the real unread count from Notifications
  for HivePress. Together with the Notifications page, which the bar already offers as a link, this
  is what lets you put the notification bell on the bar.
* The existing "All notifications" counter is now called "Account activity (HivePress)". It has
  never counted notifications from the Notifications plugin: it is HivePress's own combined counter,
  which Messages, Bookings and Marketplace add into. Nothing about what it counts has changed, and
  any bar item already using it keeps working exactly as before.
* The new counter is only offered where Notifications for HivePress is active, and a bar item set to
  it shows nothing rather than quietly counting something else if that plugin is later removed.
* The bar's height is now published as a `--hp-action-bar-height` CSS variable on the page root, not
  just on the bar itself, so anything that needs to sit above the bar can read one value instead of
  measuring the element. Notifications for HivePress 1.2.0 uses it to keep its pop-ups clear of the
  bar on phones.
* Each bar item now carries its badge source as a `data-badge` attribute, so a plugin that owns a
  counter can keep it up to date without reloading, and without any risk of writing its number into
  somebody else's badge.
* Fixed - "View details" is back on the Plugins screen. WordPress only offers that link for a
  plugin that has told it about itself, and this one stayed quiet whenever there was nothing to
  update to, which is almost always. The details popup, its changelog and the donate link inside
  it were all unreachable from the Plugins screen as a result.
* Fixed - checking for updates no longer holds up an admin page. The check ran while WordPress was
  building the Plugins screen, so on a site with several of these extensions one page load made one
  request to GitHub after another and could sit there for many seconds, once, before behaving
  normally again for hours. The check now runs in the background moments later. Pressing Check for
  updates still asks GitHub straight away, because you are waiting for that answer.

= 1.3.3 =
* Checking for updates no longer reports "Could not reach GitHub" when nothing is wrong. GitHub allows a server only a limited number of anonymous update checks each hour, shared by every plugin on the site and, on shared hosting, by every other site on the same server. Running out is ordinary, but it was reported as though the site could not reach GitHub at all. Update checks now read the release from github.com, which sets no such limit, so the message no longer appears. If the limit is ever reached by some other route, the notice now says so plainly instead of blaming your connection.
* A failed update check no longer hides an update that is genuinely waiting. The last successful answer is kept until a later check succeeds, so a pending update stays on the Plugins screen instead of disappearing for an hour.

= 1.3.2 =
* Fixed: the author shown on the Plugins screen now reads "ChrisB @ HivePress Community", matching every other extension in the range.
* Removed: the thank-you line under the settings form. The "Donate" link on the Plugins screen and in the plugin details popup is the only place the ask appears now, so it never interrupts you while you are configuring the plugin.

= 1.3.1 =
* The Donate link on the Plugins screen now uses a star icon instead of a coffee cup, which reads more clearly at that size.

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
