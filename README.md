# WPSL CSV Importer

Import store locations from CSV files into [WP Store Locator](https://wordpress.org/plugins/wp-store-locator/) — with a real-time progress bar, smart duplicate detection, CSV export, and category support.

**Free. No upsells. No limits.**

---

## Features

- **Real-time progress bar** — AJAX-powered chunked processing handles files of any size without PHP timeouts
- **Flexible column mapping** — map any CSV column name to any WPSL field; no need to rename your file
- **Smart duplicate detection** — compares by store name *and* address, so chains like "Starbucks" with multiple locations are handled correctly
- **Three duplicate modes** — Skip, Update, or Always Insert
- **CSV export** — download all your WPSL stores as a CSV file compatible with this importer (one click)
- **Category support** — assign `wpsl_store_category` terms from a CSV column; categories are created automatically if they don't exist; multiple categories supported (separate with `|`)
- **Remembers your mapping** — column settings are saved and pre-filled for your next import
- **Auto-detects delimiter** — comma or semicolon, detected from the file automatically
- **UTF-8 BOM handling** — works with CSVs exported from Excel without any manual conversion
- **Capitalization normalization** — convert ALL CAPS fields to Title Case automatically
- **Smart geocoding** — provide Lat/Lng columns to skip geocoding, or let WPSL handle it; if an address changes on update, re-geocoding is triggered automatically
- **Corrupt data cleanup** — detects and removes stores with corrupt zip codes from failed imports
- **i18n ready** — all UI strings wrapped in `__()` for full translation support

---

## Requirements

- WordPress 5.6+
- PHP 7.4+
- [WP Store Locator](https://wordpress.org/plugins/wp-store-locator/) installed and active

---

## Installation

1. Download the latest release zip or clone this repository into `wp-content/plugins/wpsl-csv-importer/`
2. Activate the plugin in **Plugins → Installed Plugins**
3. Go to **WP Store Locator → CSV Importer**

---

## CSV Format

The first row must contain column headers. Everything else is flexible — you map your column names to WPSL fields inside the plugin.

**Example:**
```
Name,Address,City,State,ZipCode,Country,Phone,Email,Website,Category
Acme Store,123 Main St,Springfield,IL,62701,United States,(217) 555-0100,info@acme.com,https://acme.com,Hardware
```

| Column | Required | Notes |
|---|---|---|
| Name | Yes | Store name |
| Address | Yes | Street address |
| City | Yes | |
| State | Yes | State or province |
| ZipCode | Yes | |
| Country | No | Can also be set as a fixed value for all rows (e.g. `United States`) |
| Address2 | No | Suite, floor, etc. |
| Phone | No | |
| Fax | No | |
| Email | No | |
| Website | No | |
| Lat | No | If provided, WPSL skips geocoding |
| Lng | No | If provided, WPSL skips geocoding |
| Category | No | Creates the category if it doesn't exist. Multiple: `Cat A\|Cat B` |

**Delimiter:** Comma (`,`) or semicolon (`;`), auto-detected.

**Encoding:** UTF-8 recommended. UTF-8 BOM (Excel default) is handled automatically.

---

## How the import works

1. You upload the CSV and submit the form
2. The plugin validates your column mapping against the actual headers
3. Your column mapping is saved for next time
4. Processing runs in chunks of 50 rows via AJAX — the progress bar updates in real time
5. Each chunk inserts/updates/skips stores and returns a partial result
6. When all chunks are done, a summary is shown (inserted / updated / skipped / errors)

This means a 10,000-row CSV processes without any risk of timeout, even on shared hosting.

---

## Duplicate detection

The plugin compares the incoming store's **name and address** against existing `wpsl_stores` posts. This correctly handles store chains:

- "Price Chopper" at "100 Main St" → matched to the existing record, skipped or updated
- "Price Chopper" at "200 Elm St" → treated as a different location, inserted

Comparison is case-insensitive on the address field.

---

## Screenshots

> Screenshots will be added after the wordpress.org submission.

---

## Changelog

### 1.2.0
- AJAX import with real-time progress bar
- CSV export (UTF-8 BOM, includes categories)
- Store category support (auto-create, pipe-separated multiple)
- Column mapping saved between sessions
- Duplicate detection fixed: name + address (not name-only)
- Geocoding fix: re-triggers when address changes on update
- Full i18n support
- wordpress.org-ready `readme.txt`
- Uninstall hook + dependency notice

### 1.1.0
- Corrupt zip cleanup tool
- CSV header preview widget
- Three duplicate modes (skip / update / always insert)
- Capitalization normalization
- Auto-detect comma vs. semicolon delimiter
- UTF-8 BOM handling

### 1.0.0
- Initial release

---

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html)
