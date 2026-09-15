# 🍽️ Obydullah Restaurant ERP

**A complete restaurant management system for WordPress + WooCommerce — branches, employees, suppliers, purchases, accounting, kitchen operations, and reports.**

Everything you need to run a restaurant — multi-branch inventory, employee clock-in/out, purchase workflows, double-entry bookkeeping, a live Kitchen Display System, and full analytics — all inside your existing WordPress admin dashboard. No extra SaaS, no separate login, no per-user fees — just one plugin, one database, one dashboard.

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759B?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-96588A?logo=woocommerce&logoColor=white)](https://woocommerce.com/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![jQuery](https://img.shields.io/badge/jQuery-3.x-0769AD?logo=jquery&logoColor=white)](https://jquery.com/)

[![License](https://img.shields.io/badge/License-GPL--2.0-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.0.0-brightgreen)](https://wordpress.org/plugins/obydullah-restaurant-erp/)
[![WordPress Plugin](https://img.shields.io/badge/WordPress-plugin-blue?logo=wordpress&logoColor=white)](https://wordpress.org/plugins/obydullah-restaurant-erp/)
[![Tables](https://img.shields.io/badge/Tables-21-orange)]()
[![Admin Pages](https://img.shields.io/badge/Admin%20Pages-15-2D3436)]()

---

## ✨ Features

- 🏪 **Multi-Branch Management** — unlimited branches with per-branch inventory, reorder levels, and inter-branch stock transfers
- 👥 **Employee Management** — auto-generated codes, WP user linking, clock in/out, shift scheduling, and custom roles
- 🚚 **Supplier & Purchasing** — supplier directory, product mapping, and a full purchase-order workflow (Draft → Pending → Partial → Received → Cancelled)
- 🧾 **Double-Entry Accounting** — 29 pre-seeded accounts, balanced journal entries, general ledger, trial balance, P&L, balance sheet, and VAT/GST reports
- 🖥️ **Kitchen Display System** — real-time order grid, station filtering, priority levels, and the `[orerp_kds]` shortcode
- 🧑‍🍳 **Recipe Management** — recipes linked to WooCommerce products with ingredient and prep/cook time tracking
- 📊 **Reports & Analytics** — sales, inventory, financial, branch, and employee performance reports with print/PDF export
- 🔌 **WooCommerce Integration** — automatic journal entries on order completion, plus Obydullah Restaurant POS support
- 🔐 **Secure by Default** — nonces, sanitization, prepared statements, capability checks, and a full audit trail

## 🚀 Quick Start

```bash
# 1. Install and activate WooCommerce
# 2. Upload the `obydullah-restaurant-erp` folder to /wp-content/plugins/
# 3. Activate the plugin through the Plugins menu

# Optional: render a standalone Kitchen Display System board
shortcode: [orerp_kds]
```

The plugin creates its own `orerp_*` tables automatically on activation and seeds the default chart of accounts — no manual database setup required.

## 📦 Modules

| Module | Features |
|---|---|
| **Branches** | Unlimited branches, per-branch inventory, reorder levels, inter-branch transfers |
| **Employees** | Employee records, WP user linking, clock in/out, shifts, custom roles |
| **Suppliers** | Supplier directory, supplier-product mapping, payment terms |
| **Purchasing** | Purchase orders, goods receiving with stock updates, payment recording |
| **Accounting** | Chart of accounts, double-entry journal, general ledger, trial balance, P&L, balance sheet, VAT/GST |
| **Kitchen (KDS)** | Live order grid, station filtering, priority levels, prep-time tracking |
| **Recipes** | Product-linked recipes, ingredients, prep/cook times, servings |
| **Reports** | Sales, inventory, financial, branch, employee performance, print/PDF |
| **Settings** | Currency, currency position, date format, default tax rate |

## 🔌 Installation

1. Upload the `obydullah-restaurant-erp` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu
3. Ensure WooCommerce is installed and active
4. Navigate to **Restaurant ERP** in your admin menu

Requires **WooCommerce 8.0+** (tested up to 11.0) and works alongside **Obydullah Restaurant POS**.

## 🛠 Tech Stack

| Technology | Usage |
|---|---|
| PHP 8.0+ | Backend logic, form handling, database queries |
| WordPress 6.0+ | Platform, admin UI, security APIs |
| WooCommerce 8.0+ | Order sync, required dependency (tested up to 11.0) |
| MySQL 8.0 | 21 custom tables via `$wpdb` with prepared statements |
| jQuery | Client-side dynamic forms (journal balancing, line-item calculators, KDS grid) |
| CSS3 | Custom admin framework (cards, badges, tables, KDS, responsive) |

## 🗄 Database Schema

21 tables with the `orerp_` prefix, created via `dbDelta()` on activation:

| Module | Tables |
|---|---|
| Branches | `orerp_branches`, `orerp_branch_stock`, `orerp_transfers`, `orerp_transfer_items` |
| Employees | `orerp_employees`, `orerp_attendance`, `orerp_shifts` |
| Suppliers | `orerp_suppliers`, `orerp_supplier_products` |
| Purchases | `orerp_purchase_orders`, `orerp_purchase_items`, `orerp_purchase_payments` |
| Accounting | `orerp_accounts`, `orerp_journal_entries`, `orerp_journal_lines`, `orerp_fiscal_periods` |
| Kitchen | `orerp_recipes`, `orerp_recipe_ingredients`, `orerp_kitchen_orders`, `orerp_kitchen_order_items`, `orerp_prep_tracking` |

## 🧠 Accounting Rules

- **Double-entry enforcement** — every journal entry must balance (debit = credit); validated on the server
- **Normal balances** — debit-normal for assets/expenses, credit-normal for liabilities/equity/revenue
- **Automatic journal entries** — sale → *Dr Cash / Accounts Receivable, Cr Sales Revenue & VAT Payable*; purchase received → *Dr Inventory / Cr Accounts Payable*; purchase payment → *Dr Accounts Payable / Cr Cash*

## 🎭 Custom Roles

| Role | Capabilities |
|---|---|
| `restaurant_manager` | Full ERP admin, kitchen, reports |
| `restaurant_kitchen_staff` | Kitchen access only |
| `restaurant_cashier` | Reports access only |

## 🔒 Security

- `ABSPATH` guards on every PHP file
- Nonce verification on all AJAX requests and form submissions
- Input sanitization (`sanitize_text_field`, `sanitize_email`, `intval`, `floatval`)
- Prepared SQL statements (`$wpdb->prepare`)
- Output escaping (`esc_html`, `esc_attr`, `esc_url`)
- Capability checks (`manage_options`) and custom roles on all pages

## 📄 License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html) — free to use, modify, and redistribute.

---

_Built with WordPress, WooCommerce, PHP, MySQL, jQuery, and CSS3 — by [Obydullah](https://obydullah.com)_