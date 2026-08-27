=== Obydullah Restaurant ERP ===
Contributors: obydullah, shaikobydullah
Tags: restaurant, erp, accounting, inventory, management
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 8.0
WC requires at least: 6.0
WC tested up to: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A complete restaurant management system with branches, employees, suppliers, purchases, accounting, kitchen operations, and reports.

== Description ==

A complete restaurant management system with branches, employees, suppliers, purchases, accounting, kitchen operations, and reports. Integrates with WooCommerce and Obydullah Restaurant POS.

**Key Features:**

* **Multi-Branch Management** — Unlimited branches with per-branch inventory and inter-branch transfers
* **Employee Management** — Auto-generated codes, WP user linking, clock in/out, shifts, custom roles
* **Supplier & Purchasing** — Purchase order workflow: Draft → Pending → Partial → Received → Cancelled
* **Double-Entry Accounting** — 29 pre-seeded accounts, journal entries, general ledger, trial balance, P&L, balance sheet, VAT/GST
* **Kitchen Display System** — Real-time order grid, station filtering, priority levels, `[orerp_kds]` shortcode
* **Recipe Management** — Linked to WooCommerce products, ingredient tracking
* **Reports & Analytics** — Sales, inventory, financial, branch, employee performance, print/PDF export
* **WooCommerce Integration** — Auto journal entries on order completion, POS integration

**Custom Roles:**

* `restaurant_manager`
* `restaurant_kitchen_staff`
* `restaurant_cashier`

== Installation ==

1. Upload the `obydullah-restaurant-erp` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Ensure WooCommerce is installed and active.
4. Go to Restaurant ERP → Settings to configure currency, date format, and tax rate.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes. WooCommerce must be installed and active for the plugin to function.

= What are the database requirements? =

The plugin creates 18 custom tables on activation for branches, employees, suppliers, purchases, accounting, kitchen operations, and reports.

= Does it support multiple branches? =

Yes. You can create unlimited branches with per-branch inventory, employee assignments, and inter-branch transfers.

== Screenshots ==

1. Dashboard overview
2. Branch management
3. Employee management
4. Purchase orders
5. Journal entries
6. Kitchen display system

== Changelog ==

= 1.0.0 =
* Initial release
