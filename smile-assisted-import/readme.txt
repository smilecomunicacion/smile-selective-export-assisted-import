=== SMiLE Assisted Import ===
Contributors: smilecomunicacion
Tags: import, migration, patterns, media, wordpress
Requires at least: 6.3
Tested up to: 6.8
Stable tag: 1.0.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==
SMiLE Assisted Import is the companion importer for the SMiLE Selective Export tool. It restores pages, synced block patterns, and referenced media from a SMiLE JSON package so that the migrated content works out of the box on the destination site. The plugin downloads remote assets, remaps pattern references, rewrites links to match the current domain, and produces a detailed report for site administrators.

== Installation ==
1. Upload the plugin folder to the `/wp-content/plugins/` directory or install it through the WordPress admin screen.
2. Activate **SMiLE Assisted Import** through the **Plugins** menu in WordPress.
3. Navigate to **Tools → SMiLE Assisted Import**, upload a SMiLE JSON package exported from the source site, and run the importer.

== Frequently Asked Questions ==
= What are the system requirements? =
The importer requires WordPress 6.3 or higher and PHP 7.4 or higher. The server must allow outbound HTTP requests so media assets can be downloaded during the import process.

== Changelog ==
= 1.0.2 =
* Initial release included in this repository: imports pages, synced block patterns, and media from SMiLE JSON packages, rewrites URLs, and generates an import report.

== Upgrade Notice ==
= 1.0.2 =
Initial release packaged with the SMiLE Selective Export tooling.
