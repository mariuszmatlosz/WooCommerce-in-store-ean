=== EAN to PoS ===
Contributors: virgin71
Tags: woocommerce, point of sale, barcode, ean, inventory
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.3.1
License: MIT
License URI: https://opensource.org/license/mit

Scan barcodes to record in-store WooCommerce sales and update stock from WordPress Admin.

== Description ==

Developed by Mariusz Matłosz (virgin71).

EAN to PoS lets store staff scan product barcodes or SKUs directly into a register in WordPress Admin. Repeated scans add units, and staff review products and quantities before saving.

* Looks up the native WooCommerce GTIN field when available, then the SKU. Supports simple products and variations.
* Records paid in-store sales as completed WooCommerce orders and lets WooCommerce handle stock reduction.
* Uses the WooCommerce store address for billing by default and supports a dedicated PoS billing address. The order uses the logged-in employee’s account. Names come from the employee’s WooCommerce billing or shipping profile, with the WordPress profile as a fallback.
* Records the payment received and optional references to existing receipts, invoices or shipments. Suggests editable document numbers with configurable formats and separate counters.
* Receives and issues tracked stock for staff with product editing permissions. The review shows unit prices and warns when the resulting stock is negative.
* Includes English, Polish, Czech and Spanish (Spain) admin interfaces.

The register records payments already received outside the plugin. It does not process payments or issue fiscal receipts or invoices.

== Installation ==

1. Install and activate WooCommerce.
2. Install and activate EAN to PoS.
3. Set the full store address under WooCommerce > Settings > General, or enter a separate address under EAN to PoS > Settings.
4. Open EAN to PoS in the WordPress sidebar, choose an operation and focus the scan field. Use a scanner configured as a keyboard with Enter after each code, or paste a list of codes.
5. Review the scans, then confirm the sale or stock movement.

== Frequently Asked Questions ==

= Who can use the register? =

Users who can edit WooCommerce orders can record sales. Stock movements also require permission to edit the affected products.

= What happens if the employee profile is incomplete? =

The register prompts the employee to complete their profile. Missing names remain blank. A complete billing address from WooCommerce or the PoS settings is required for sales.

= Who receives order emails? =

The order is linked to the employee's account. Any enabled WooCommerce customer notifications for that order use the employee's billing email.

= How do document numbers work? =

The register starts with Receipt selected. Receipts use a continuous sequence, and invoices default to S3/001/MM/YYYY, with a new sequence each month. Dates use the WordPress site timezone.

Under EAN to PoS > Settings, you can hide invoices, change the default document, or set each series' format, minimum number of digits, starting suggestion and reset cycle. Use {number} once, then set the minimum sequence width separately. Supported PHP-style date placeholders include {Y} and {y} (year), {m} and {n} (month), and {d} and {j} (day). The settings page includes examples and aliases. Monthly formats require month and year, and yearly formats require year.

The number is editable. Enter a sequence or the complete reference. Reset restores the configured starting number, while Use next number increments the number in the field. A number must be higher than the last one recorded in the same series and period. To continue an existing external series, set the first suggestion to the next number or enter that number when recording the first sale. Counters advance on confirmation. References remain on their WooCommerce orders if the settings change.

== Changelog ==

= 0.3.1 =
* Separated resetting the document number from incrementing it.

= 0.3.0 =
* Added a top-level menu with separate sale and stock workflows.
* Added WooCommerce profile fallbacks and live document number suggestions.
* Added unit prices and warnings for negative inventory on stock movements.
* Included Polish, Czech and Spanish translations.

= 0.2.0 =
* Added editable number suggestions and configurable receipt and invoice series.
* Added the default document and invoice visibility settings.
* Added an optional billing address for PoS orders.

= 0.1.0 =
* Initial release.
