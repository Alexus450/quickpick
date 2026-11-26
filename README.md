# QuickPick WordPress Plugin

[![WordPress](https://img.shields.io/wordpress/v/quickpick.svg)](https://wordpress.org/plugins/quickpick/)
[![WordPress](https://img.shields.io/wordpress/plugin/v/quickpick.svg)](https://wordpress.org/plugins/quickpick/)
[![WordPress](https://img.shields.io/wordpress/plugin/installs/quickpick.svg)](https://wordpress.org/plugins/quickpick/)
[![WordPress](https://img.shields.io/wordpress/plugin/rating/quickpick.svg)](https://wordpress.org/plugins/quickpick/)

Jump back to recently edited posts and pages from the admin list screens or the toolbar.

## Features

- **Quick Access**: Display recently edited posts and pages in a convenient dropdown
- **Admin Toolbar**: Jump to recent items from any admin or front-end screen
- **Clean Interface**: Intuitive dropdown menu that matches WordPress admin design
- **Homepage Integration**: Special homepage link for pages
- **Set as Homepage**: Set any page as homepage with one click (AJAX-powered)
- **Flexible Settings**: Configure item limit, visibility scope, and enabled post types
- **Custom Post Types**: Enable QuickPick for supported post types from plugin settings
- **Lightweight**: Minimal impact on site performance
- **Sensible Defaults**: Works immediately with optional tuning
- **Multisite Compatible**: Works with both single-site and multisite installations

### Perfect For:
- Content creators who work on multiple posts
- Editors managing various pages
- Developers who frequently edit content
- Anyone who wants to save time navigating WordPress admin

## Installation

1. Upload the plugin files to `/wp-content/plugins/quickpick/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. The "QuickPick" button will appear in your Posts and Pages admin list views.

## Usage

After activation, a "QuickPick" button will appear in your Posts and Pages admin list views. Click it to jump to recently edited content. You can turn on a toolbar menu in Settings > QuickPick.

### For Posts
- Shows the last 5 edited posts
- Displays post title and last modified date/time
- Direct links to edit or view each post

### For Pages
- Shows the last 5 edited pages
- Includes special homepage link if a static page is set as homepage
- Quick "Set as Homepage" action for any published page (one-click with AJAX)
- Displays page title and last modified date/time
- Direct links to edit or view each page

## Requirements

- WordPress 5.0 or higher
- PHP 5.6 or higher

## Frequently Asked Questions

**Does this plugin work with custom post types?**
Yes. From version 1.0.5 you can enable QuickPick for supported post types in Settings > QuickPick.

**How many items does QuickPick show?**
QuickPick displays the last 5 edited posts or pages by default.

**Does QuickPick affect site performance?**
No. QuickPick is lightweight. It loads in the admin area, and on the front end only when the admin toolbar is showing.

**Is QuickPick compatible with WordPress multisite?**
Yes, QuickPick works with both single-site and multisite WordPress installations.

## Screenshots

1. Posts list with the QuickPick button
2. Pages list with the QuickPick button
3. Page lists showing homepage link
4. QuickPick settings page
5. Admin toolbar menu with recently edited items

## Changelog

### 1.0.8
- Tested with WordPress 7.2
- Arrow keys, Home, and End move through the list dropdown; Escape returns focus to the button
- Set as Homepage only accepts a published page
- View and Preview links on recent items in the list dropdown
- List-screen script no longer depends on jQuery
- "Only my content" matches pages you just saved even when WordPress does not store `_edit_last`
- "Only my content" and the admin toolbar menu are off by default

### 1.0.7
- Added a QuickPick menu to the WordPress admin toolbar
- Added a setting to show or hide the admin toolbar menu
- "Only my content" now filters by the last user who saved the item, not the original author
- Recent items show untitled fallbacks, post status, and the site date/time format
- Added a Settings link on the Plugins screen
- Plugin options are removed on uninstall
- Redesigned the settings page
- Compatibility with WordPress version 7.1

### 1.0.6
- Compatibility with WordPress version 7.0

### 1.0.5
- Added a new settings page under `Settings > QuickPick`
- Added options for item count, "only my content", and enabled post types (including custom post types)
- Improved dropdown accessibility with proper button semantics and Escape/outside-click closing
- Replaced browser alerts with WordPress admin notices for AJAX feedback
- Hardened security checks and output escaping
- Improved query performance and fixed post data reset flow
- Updated UI label from `QuikPick` to `QuickPick`

### 1.0.4
- Minor fix
- Compatibility WordPress Version 6.9

### 1.0.3
- Added "Set as Homepage" feature for pages
- New AJAX functionality to quickly set any published page as the homepage
- Added action link in pages list to set page as homepage with one click
- Enhanced page management capabilities

### 1.0.2
- Minor fixes
- Compatibility WordPress Version 6.8

### 1.0.1
- Compatibility WordPress Version 6.1

### 1.0
- Initial release

## Support

For support, feature requests, or bug reports, please visit the [WordPress.org plugin page](https://wordpress.org/plugins/quickpick/).

## License

This plugin is licensed under the GPL v2 or later.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Author

**Alexei Samarschi**
- WordPress Profile: [alexus450](https://profiles.wordpress.org/alexus450/)
- Donate: [PayPal](http://paypal.me/gt330/5usd)