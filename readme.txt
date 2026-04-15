=== CSV Importer for Store Locator ===
Contributors: enzomazzariol
Tags: csv, import, stores, wp store locator, wpsl
Requires at least: 5.6
Tested up to: 6.9.4
Stable tag: 2.0.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import store locations from CSV files into WP Store Locator — with real-time progress, duplicate detection, and CSV export.

== Description ==

**CSV Importer for Store Locator** is the easiest way to bulk-import store locations into [WP Store Locator](https://wordpress.org/plugins/wp-store-locator/) without touching the file manager or writing code.

Upload any CSV file and the plugin handles the rest: it detects your delimiter automatically, maps your columns to WPSL fields, and imports thousands of stores in real time with a progress bar — without PHP timeouts.

= Key Features =

* **Real-time progress bar** — AJAX-powered chunked import handles files of any size without timeouts. Most paid competitors charge extra for this.
* **Flexible column mapping** — map any CSV column name to any WPSL field. No need to rename your CSV.
* **Smart duplicate detection** — compares by store name *and* address, so chains like "Price Chopper" with 20 locations are handled correctly.
* **Three duplicate modes** — Skip, Update, or Always Insert.
* **CSV export** — download all your WPSL stores as a CSV compatible with this importer. One click.
* **Category support** — assign stores to WPSL store categories. Categories are created automatically if they don't exist. Supports multiple categories per store (separate with `|`).
* **Remembers your mapping** — column settings are saved and pre-filled for your next import.
* **Auto-detects delimiter** — comma or semicolon, detected automatically.
* **UTF-8 BOM handling** — works with CSVs exported from Excel without any conversion.
* **Capitalization normalization** — convert ALL CAPS fields to Title Case automatically.
* **Geocoding** — WPSL geocodes addresses automatically. Provide Lat/Lng columns to skip geocoding. On update, if the address changed, geocoding is re-triggered automatically.
* **Corrupt data cleanup** — built-in tool to detect and remove stores with corrupt zip codes from failed imports.

= Who Is This For? =

Anyone who needs to import a list of stores, branches, or locations into WP Store Locator — from a handful to tens of thousands of records.

= CSV Format =

The first row must contain column headers. Example:

`Name,Address,City,State,ZipCode,Country,Phone,Email,Website,Category`

Supported delimiters: `,` (comma) and `;` (semicolon), auto-detected.

Optional columns: Address2, Phone, Fax, Email, Website, Lat, Lng, Category.

If your CSV has no Country column, type a fixed value (e.g. `United States`) in the Country field — it will be applied to all imported rows.

= Requirements =

* [WP Store Locator](https://wordpress.org/plugins/wp-store-locator/) must be installed and active.

== Installation ==

1. Upload the `wpsl-csv-importer` folder to the `/wp-content/plugins/` directory, or install directly through the WordPress plugin screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **WP Store Locator → CSV Importer** (or **CSV Importer for Store Locator** in the main menu if WPSL is not active).
4. Upload your CSV file, map the column names, and click **Import now**.

== Frequently Asked Questions ==

= Does this work without WP Store Locator? =

No. This plugin imports data into WP Store Locator's post type (`wpsl_stores`). WP Store Locator must be installed and active.

= My CSV has thousands of rows. Will it time out? =

No. The import runs in chunks via AJAX (50 rows per request by default), so it will never time out regardless of file size.

= How does duplicate detection work? =

The plugin compares the store **name and address** together. This means chains with multiple locations (e.g. "Starbucks" at different addresses) are correctly identified as separate stores.

= Can I update existing stores? =

Yes. Choose **Update** in the duplicate mode option. Existing stores will have their data overwritten. If the address changed, WPSL will re-geocode automatically.

= My CSV uses semicolons instead of commas — is that supported? =

Yes. The delimiter (comma or semicolon) is detected automatically.

= Can I export my existing stores? =

Yes. Use the **Export stores to CSV** button at the bottom of the import form. The exported file uses the same column format this importer expects, so you can use it as a template.

= How do I assign store categories? =

Add a `Category` column to your CSV. The category will be created automatically if it does not exist. To assign multiple categories to one store, separate them with a pipe: `Category A|Category B`.

= The import seems to freeze on very large files =

Make sure JavaScript is enabled in your browser and that no browser extension is blocking AJAX requests. Each chunk processes 50 rows; you should see the progress bar advancing regularly.

= Where can I find the column names my CSV uses? =

Use the **Preview CSV columns** tool in the sidebar — upload your file and the plugin will show you all detected column headers before you start the import.

== Screenshots ==

1. The import form with column mapping, progress bar, and options.
2. Real-time progress bar during a large CSV import.
3. Export and preview tools in the sidebar.

== Privacy ==

This plugin does not collect or transmit any personal data.

If you use the **Import from URL** option, the plugin makes a server-side HTTP request to the URL you provide in order to download the CSV file. No data is sent to third parties by the plugin itself; the request goes directly from your WordPress server to the URL you specify.

== Changelog ==

= 2.0.0 =
* New: Import from URL — paste a public CSV URL (e.g. Google Sheets) and the plugin downloads it server-side.
* New: Dry run / Import Preview — see which stores would be inserted, updated, or skipped before committing.
* New: Export filters — filter by category, state, or ungeocoded stores before downloading.
* New: Bulk delete by category.
* New: Re-geocode stores without coordinates — clears and re-publishes stores so WPSL geocodes them automatically.
* New: Store statistics dashboard (total stores, ungeocoded count, categories, corrupt records).
* Improvement: Chunked import now uses byte-offset seeking — eliminates O(N²) row scanning on large files.
* Improvement: All JS strings are now fully translatable via wp_localize_script.
* Fix: Chunk size cap raised to 500 to match the Settings UI.
* Fix: Replaced all direct `file_put_contents` calls with WP_Filesystem API.
* Fix: Replaced all `@unlink` and `@mkdir` calls with proper WP file helpers.

= 1.2.0 =
* New: AJAX-powered chunked import with real-time progress bar — no more timeouts on large files.
* New: CSV export — download all WPSL stores as a CSV file.
* New: Store category support — assign and auto-create `wpsl_store_category` terms from CSV.
* New: Column mapping is now saved and pre-filled for the next import session.
* New: Dependency notice shown if WP Store Locator is not active.
* New: Uninstall hook to clean up plugin options.
* Fix: Duplicate detection now compares name **and** address — correctly handles store chains with multiple locations.
* Fix: Update mode now forces WPSL re-geocoding when the address changes.
* Improvement: Preview CSV columns tool now runs via AJAX (no page reload required).
* Improvement: All UI strings are now internationalized (i18n ready).

= 1.1.0 =
* Added: Corrupt zip cleanup tool.
* Added: Widget to preview CSV headers before importing.
* Added: Three duplicate modes (skip / update / always insert).
* Added: Optional capitalization normalization.
* Added: Duplicate detection using WP_Query (replaces deprecated `get_page_by_title`).
* Added: Import row limit to prevent timeouts on large files.
* Fixed: UTF-8 BOM handling for Excel-generated CSVs.
* Fixed: Auto-detection of comma vs. semicolon delimiter.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.2.0 =
Major update: chunked AJAX import with progress bar, CSV export, category support, and important bug fixes for duplicate detection and geocoding. Upgrade recommended for all users.
