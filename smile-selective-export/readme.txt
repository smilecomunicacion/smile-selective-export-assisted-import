=== SMiLE Selective Export ===
Contributors: smile
Tags: export, migration, blocks, patterns, media
Requires at least: 6.3
Tested up to: 6.8
Stable tag: 1.0.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Export selected pages along with their synced patterns and media references into a JSON package that can be safely imported elsewhere.

== Description ==
SMiLE Selective Export streamlines migrations for block-based WordPress sites by building a complete JSON package that contains:

* The pages you choose to export, including their metadata.
* Any synced patterns (`wp_block` posts) referenced in the page content.
* A list of media URLs gathered from the selected pages and patterns, including attachment URLs where available.

This prevents missing blocks or media when the package is imported on another site, making migrations consistent and predictable.

= Key Features =
* Select specific pages to export from the WordPress admin.
* Automatically detect and include synced patterns referenced within the selected pages.
* Collect attachment IDs and absolute media URLs referenced in blocks to aid asset transfers.
* Generate a standards-compliant JSON file ready for downstream import tooling.

== Installation ==
1. Upload the `smile-selective-export` folder to the `/wp-content/plugins/` directory or install the plugin through the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **Tools → SMiLE Selective Export** to begin exporting your content.

== Usage ==
Follow these steps to generate an export package:

1. Go to **Tools → SMiLE Selective Export** while logged in as an administrator.
2. Select the pages you want to include in the export. The plugin supports published, draft, pending, and private pages.
3. Click **Export JSON package**.
4. The plugin scans each selected page for referenced synced patterns and media. Any detected `wp_block` posts are added automatically, and media IDs/URLs are collected for reference.
5. A JSON file named `smile-export-YYYYMMDD-HHMMSS.json` is streamed to your browser for download. Save the file locally for use with your import workflow.

== Frequently Asked Questions ==
= Does the export include media files themselves? =
No. The export includes a list of media URLs and attachment IDs so you can download or sync the files separately. This keeps the package lightweight and focused on structured content.

= Can I export other post types? =
Version 1.0.2 focuses on pages and synced patterns. Future updates may expand support to additional post types.

== Screenshots ==
1. Page selection screen for SMiLE Selective Export within the WordPress admin tools menu.

== Changelog ==
= 1.0.2 =
* Documented the JSON export workflow and ensured synced patterns/media references are gathered during export.

== Upgrade Notice ==
= 1.0.2 =
This update documents the export workflow and resolves the missing readme validation error for the plugin directory submission process.
