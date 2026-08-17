# Obydullah Restaurant ERP

![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759B?style=for-the-badge&logo=wordpress&logoColor=white)
![WooCommerce](https://img.shields.io/badge/WooCommerce-6.0%2B-96588A?style=for-the-badge&logo=woocommerce&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/License-GPL%20v2-or%20later-333?style=for-the-badge)
![Version](https://img.shields.io/badge/Version-1.0.0-green?style=for-the-badge)

![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![jQuery](https://img.shields.io/badge/jQuery-0769AD?style=for-the-badge&logo=jquery&logoColor=white)

![Build](https://img.shields.io/badge/Build-Passing-brightgreen?style=for-the-badge)
![Security](https://img.shields.io/badge/Security-Nonces%20%26%20Sanitization-orange?style=for-the-badge)
![i18n](https://img.shields.io/badge/i18n-Ready-blueviolet?style=for-the-badge)

---

## Complete Restaurant Management System for WordPress

A full-featured ERP plugin built on WordPress + WooCommerce. Manages branches, employees, suppliers, purchases, double-entry accounting, kitchen operations, recipes, and reporting — all from the WordPress admin dashboard.

---

## Features

### Multi-Branch Management
- Create and manage unlimited restaurant branches
- Per-branch inventory tracking with reorder levels
- Inter-branch stock transfers with pending → received workflow
- Global branch switching from the dashboard

### Employee Management
- Employee records with auto-generated codes (`EMP-0001`)
- WordPress user linking for login access
- Clock in / Clock out with hour tracking
- Shift management per branch
- Custom WordPress roles (Restaurant Manager, Kitchen Staff, Cashier)

### Supplier & Purchasing
- Supplier management with contact details and payment terms
- Supplier-product mapping with SKU, unit cost, and lead time
- Purchase order workflow (Draft → Pending → Partial → Received → Cancelled)
- Receive goods with automatic stock updates
- Record payments with journal entry generation

### Double-Entry Accounting
- Chart of Accounts with 29 pre-seeded accounts
- Journal entries with draft/posted lifecycle
- Balance validation (debit must equal credit)
- General Ledger with opening balances
- Trial Balance
- Profit & Loss Statement
- Balance Sheet
- VAT/GST Tax Reports (output vs input)

### Kitchen Display System (KDS)
- Real-time order grid with status cards
- Station filtering (Grill, Fry, Salad, Dessert, Drinks)
- Priority levels (Normal, High, Urgent with blinking animation)
- Item-level tracking from WooCommerce orders
- Prep time tracking per employee
- Standalone KDS board via `[orerp_kds]` shortcode

### Recipe Management
- Recipes linked to WooCommerce products
- Ingredient tracking with quantities and units
- Prep time and cook time tracking
- Servings configuration

### Reports & Analytics
- Sales reports from WooCommerce orders
- Inventory reports across branches
- Financial reports from journal entries
- Branch comparison metrics
- Employee performance (hours worked, tasks completed, avg prep time)
- Print/PDF export with dedicated templates

### WooCommerce Integration
- Auto-creates journal entries on order completion
- Supports POS integration via `orpl_process_sale` hook
- Cash vs Accounts Receivable based on payment method
- VAT/tax auto-calculation

---

## Installation

1. Upload the `obydullah-restaurant-erp` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Ensure WooCommerce is installed and active
4. The plugin will create 18 database tables and seed default chart of accounts on activation

---

## Admin Menu

| Menu | Description |
|------|-------------|
| **Dashboard** | Summary cards, quick actions, recent activity |
| **Branches** | Manage branch locations and stock |
| **Employees** | Employee records and WP user linking |
| **Attendance & Shifts** | Clock in/out and shift management |
| **Roles & Permissions** | Assign restaurant-specific roles |
| **Suppliers** | Supplier management and product mapping |
| **Purchases** | Purchase orders, receiving, and payments |
| **Accounting** | Chart of accounts and trial balance |
| **Journal Entries** | Double-entry journal with posting workflow |
| **General Ledger** | Consolidated ledger view with opening balances |
| **Tax Reports** | VAT/GST output vs input summary |
| **Kitchen** | Kitchen Display System (KDS) |
| **Recipes** | Recipe and ingredient management |
| **Reports** | Sales, inventory, financial, branch, employee reports |
| **Settings** | Currency, date format, tax rate configuration |

---

## Database Schema

The plugin creates **18 custom tables** (prefixed with your WordPress table prefix):

| Group | Tables |
|-------|--------|
| Branches | `erp_branches`, `erp_branch_stock`, `erp_transfers`, `erp_transfer_items` |
| Employees | `erp_employees`, `erp_attendance`, `erp_shifts` |
| Suppliers | `erp_suppliers`, `erp_supplier_products` |
| Purchases | `erp_purchase_orders`, `erp_purchase_items`, `erp_purchase_payments` |
| Accounting | `erp_accounts`, `erp_journal_entries`, `erp_journal_lines`, `erp_fiscal_periods` |
| Kitchen | `erp_recipes`, `erp_recipe_ingredients`, `erp_kitchen_orders`, `erp_kitchen_order_items`, `erp_prep_tracking` |

---

## Auto-Generated Journal Entries

### Sale Completed
```
DR: Cash (1000) or Accounts Receivable (1100)    — order total
CR: Sales Revenue (4000)                          — total minus tax
CR: VAT Payable (2300)                            — tax amount
```

### Purchase Received
```
DR: Inventory (1200)                              — qty × unit cost
CR: Accounts Payable (2000)                       — qty × unit cost
```

### Purchase Payment
```
DR: Accounts Payable (2000)                       — payment amount
CR: Cash (1000)                                   — payment amount
```

---

## Requirements

- PHP 7.4 or higher
- WordPress 5.8 or higher
- WooCommerce 6.0 or higher
- MySQL 5.7 or higher

---

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[orerp_kds]` | Renders a standalone Kitchen Display System board (requires `orerp_kitchen` capability) |

---

## Custom Roles

| Role | Capabilities |
|------|-------------|
| `restaurant_manager` | Full ERP admin, kitchen, reports |
| `restaurant_kitchen_staff` | Kitchen access only |
| `restaurant_cashier` | Reports access only |

---

## Settings

Configure under **Restaurant ERP → Settings**:

- **Currency Symbol** — Synced from WooCommerce on activation
- **Currency Position** — Left, Right, Left + Space, Right + Space
- **Date Format** — PHP `date()` format used across reports
- **Tax Rate** — Default VAT/GST percentage for purchase orders

---

## Security

- Nonce verification on all AJAX requests
- Capability checks on every action
- Input sanitization with WordPress sanitization functions
- Prepared statements for all database queries
- Direct access prevention (`ABSPATH` checks)

---

## Author

**Shaik Obydullah** — [obydullah.com](https://obydullah.com)

---

## License

GPL v2 or later — [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
