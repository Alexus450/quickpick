=== QuickPick ===
Contributors: alexus450
Donate link: http://paypal.me/gt330/10usd
Tags: recently edited, quick access, admin, homepage, productivity
Requires at least: 5.0
Requires PHP: 5.6
Tested up to: 7.1
Stable tag: 1.0.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

QuickPick is a tiny WordPress plugin that will help you save time on finding recently edited posts or pages.

== Description ==

QuickPick displays your last 5 edited posts or pages in a convenient dropdown menu, allowing you to quickly jump to content you've been working on. Perfect for content creators, editors, and developers who frequently edit multiple posts or pages.

**Key Features:**
* Quick access to recently edited posts and pages
* Admin toolbar menu so you can jump between recent items from any screen
* Clean, intuitive dropdown interface
* Homepage link integration for pages
* Set any page as homepage with one click (AJAX-powered)
* Settings page with item count and visibility options
* Enable QuickPick for supported custom post types
* Lightweight and fast
* No configuration required

**Perfect for:**
* Content creators who work on multiple posts
* Editors managing various pages
* Developers who frequently edit content
* Anyone who wants to save time navigating WordPress admin 


== Installation ==

1. Install QuickPick either via the Plugins > Add New page in your WordPress Dashboard, or by downloading the plugin and uploading the file in Plugins > Add New.

2. After activating QuickPick, a "QuickPick" button will appear in the Post/Page admin list views, and a QuickPick menu will appear in the admin toolbar.

3. Click the button or the toolbar menu to see your recently edited content and quickly jump to edit any item.

== Frequently Asked Questions ==

= Does this plugin work with custom post types? =

Yes. From version 1.0.5 you can enable QuickPick for supported post types in Settings > QuickPick.

= How many items does QuickPick show? =

QuickPick displays the last 5 edited posts or pages by default. You can change this in Settings > QuickPick.

= Does QuickPick affect site performance? =

No. QuickPick is lightweight. It loads in the admin area, and on the front end only when the admin toolbar is showing.

= Is QuickPick compatible with WordPress multisite? =

Yes, QuickPick works with both single-site and multisite WordPress installations.

== Screenshots ==

1. Posts list with the QuickPick button
2. Pages list with the QuickPick button
3. Pages list with the button showing homepage link

== Changelog ==

= 1.0.7 =
* Added a QuickPick menu to the WordPress admin toolbar
* Added a setting to show or hide the admin toolbar menu
* "Only my content" now filters by the last user who saved the item, not the original author
* Recent items show untitled fallbacks, post status, and the site date/time format
* Added a Settings link on the Plugins screen
* Plugin options are removed on uninstall
* Redesigned the settings page
* Compatibility with WordPress version 7.1

= 1.0.6 =
* Compatibility with WordPress version 7.0

= 1.0.5 =
* Added QuickPick settings page under Settings > QuickPick
* Added options for item limit, "only my content", and enabled post types (including custom post types)
* Improved dropdown accessibility and keyboard behavior
* Replaced browser alerts with WordPress admin notices for better UX
* Hardened permissions and output escaping
* Improved query performance and fixed postdata reset behavior
* Updated branding text from "QuikPick" to "QuickPick"

= 1.0.4 =
* Minor fix
* Compatibility WordPress Version 6.9

= 1.0.3 =
* Added action link in pages list to set page as homepage with one click
* Enhanced page management capabilities

= 1.0.2 =
* Minor fixes
* Compatibility WordPress Version 6.8

= 1.0.1 =
* Compatibility WordPress Version 6.1

= 1.0 =
* Initial release