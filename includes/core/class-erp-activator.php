<?php
/**
 * Plugin Activator
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Activator
{
    public static function activate()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        self::create_branches_tables($charset_collate);
        self::create_employees_tables($charset_collate);
        self::create_suppliers_tables($charset_collate);
        self::create_purchases_tables($charset_collate);
        self::create_accounting_tables($charset_collate);
        self::create_kitchen_tables($charset_collate);

        self::seed_default_data();

        update_option('orerp_version', ORERP_VERSION);

        if (!get_option('orerp_currency') && function_exists('get_woocommerce_currency_symbol')) {
            update_option('orerp_currency', get_woocommerce_currency_symbol());
        }

        flush_rewrite_rules();
    }

    private static function create_branches_tables($charset_collate)
    {
        global $wpdb;

        $table_branches = $wpdb->prefix . 'erp_branches';
        $table_branch_stock = $wpdb->prefix . 'erp_branch_stock';
        $table_transfers = $wpdb->prefix . 'erp_transfers';
        $table_transfer_items = $wpdb->prefix . 'erp_transfer_items';

        $sql = "CREATE TABLE {$table_branches} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            code VARCHAR(50) NOT NULL,
            address TEXT DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            manager_id BIGINT(20) UNSIGNED DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code)
        ) {$charset_collate};";

        $sql .= "CREATE TABLE {$table_branch_stock} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            branch_id BIGINT(20) UNSIGNED NOT NULL,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            quantity INT(11) DEFAULT 0,
            reorder_level INT(11) DEFAULT 0,
            last_restocked DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY branch_product (branch_id, product_id),
            KEY branch_id (branch_id),
            KEY product_id (product_id)
        ) {$charset_collate};";

        $sql .= "CREATE TABLE {$table_transfers} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            from_branch_id BIGINT(20) UNSIGNED NOT NULL,
            to_branch_id BIGINT(20) UNSIGNED NOT NULL,
            status ENUM('pending','in_transit','received','cancelled') DEFAULT 'pending',
            notes TEXT DEFAULT NULL,
            created_by BIGINT(20) UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            received_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY from_branch_id (from_branch_id),
            KEY to_branch_id (to_branch_id)
        ) {$charset_collate};";

        $sql .= "CREATE TABLE {$table_transfer_items} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            transfer_id BIGINT(20) UNSIGNED NOT NULL,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            quantity INT(11) NOT NULL,
            received_quantity INT(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY transfer_id (transfer_id),
            KEY product_id (product_id)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    private static function create_employees_tables($charset_collate)
    {
        global $wpdb;

        $table_employees = $wpdb->prefix . 'erp_employees';
        $table_attendance = $wpdb->prefix . 'erp_attendance';
        $table_shifts = $wpdb->prefix . 'erp_shifts';

        $sql = "CREATE TABLE {$table_employees} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED DEFAULT NULL,
            employee_code VARCHAR(50) NOT NULL,
            branch_id BIGINT(20) UNSIGNED DEFAULT NULL,
            position VARCHAR(100) DEFAULT NULL,
            hourly_rate DECIMAL(10,2) DEFAULT 0.00,
            hire_date DATE DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            emergency_contact VARCHAR(255) DEFAULT NULL,
            emergency_phone VARCHAR(50) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY employee_code (employee_code),
            KEY user_id (user_id),
            KEY branch_id (branch_id)
        ) {$charset_collate};";

        $sql .= "CREATE TABLE {$table_attendance} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            employee_id BIGINT(20) UNSIGNED NOT NULL,
            branch_id BIGINT(20) UNSIGNED NOT NULL,
            clock_in DATETIME NOT NULL,
            clock_out DATETIME DEFAULT NULL,
            break_minutes INT(11) DEFAULT 0,
            notes TEXT DEFAULT NULL,
            PRIMARY KEY (id),
            KEY employee_id (employee_id),
            KEY branch_id (branch_id)
        ) {$charset_collate};";

        $sql .= "CREATE TABLE {$table_shifts} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            branch_id BIGINT(20) UNSIGNED NOT NULL,
            name VARCHAR(100) DEFAULT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY branch_id (branch_id)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    private static function create_suppliers_tables($charset_collate)
    {
        global $wpdb;

        $table_suppliers = $wpdb->prefix . 'erp_suppliers';
        $table_supplier_products = $wpdb->prefix . 'erp_supplier_products';

        $sql = "CREATE TABLE {$table_suppliers} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            code VARCHAR(50) NOT NULL,
            contact_person VARCHAR(255) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            payment_terms VARCHAR(100) DEFAULT NULL,
            balance DECIMAL(12,2) DEFAULT 0.00,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code)
        ) {$charset_collate};";

        $sql .= "CREATE TABLE {$table_supplier_products} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            supplier_id BIGINT(20) UNSIGNED NOT NULL,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            supplier_sku VARCHAR(100) DEFAULT NULL,
            unit_cost DECIMAL(10,2) DEFAULT 0.00,
            lead_time_days INT(11) DEFAULT 0,
            min_order_qty INT(11) DEFAULT 1,
            PRIMARY KEY (id),
            KEY supplier_id (supplier_id),
            KEY product_id (product_id)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    private static function create_purchases_tables($charset_collate)
    {
        global $wpdb;

        $table_purchase_orders = $wpdb->prefix . 'erp_purchase_orders';
        $table_purchase_items = $wpdb->prefix . 'erp_purchase_items';
        $table_purchase_payments = $wpdb->prefix . 'erp_purchase_payments';

        $sql = "CREATE TABLE {$table_purchase_orders} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            po_number VARCHAR(50) NOT NULL,
            supplier_id BIGINT(20) UNSIGNED NOT NULL,
            branch_id BIGINT(20) UNSIGNED NOT NULL,
            status ENUM('draft','pending','partial','received','cancelled') DEFAULT 'draft',
            subtotal DECIMAL(12,2) DEFAULT 0.00,
            tax_amount DECIMAL(10,2) DEFAULT 0.00,
            total DECIMAL(12,2) DEFAULT 0.00,
            notes TEXT DEFAULT NULL,
            expected_date DATE DEFAULT NULL,
            received_date DATE DEFAULT NULL,
            created_by BIGINT(20) UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY po_number (po_number),
            KEY supplier_id (supplier_id),
            KEY branch_id (branch_id)
        ) {$charset_collate};";

        $sql .= "CREATE TABLE {$table_purchase_items} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            purchase_id BIGINT(20) UNSIGNED NOT NULL,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            quantity INT(11) NOT NULL,
            received_qty INT(11) DEFAULT 0,
            unit_cost DECIMAL(10,2) NOT NULL,
            total DECIMAL(12,2) DEFAULT 0.00,
            PRIMARY KEY (id),
            KEY purchase_id (purchase_id),
            KEY product_id (product_id)
        ) {$charset_collate};";

        $sql .= "CREATE TABLE {$table_purchase_payments} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            purchase_id BIGINT(20) UNSIGNED NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            payment_method VARCHAR(50) DEFAULT NULL,
            reference VARCHAR(100) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            payment_date DATE DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY purchase_id (purchase_id)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    private static function create_accounting_tables($charset_collate)
    {
        global $wpdb;

        $table_accounts = $wpdb->prefix . 'erp_accounts';
        $table_journal_entries = $wpdb->prefix . 'erp_journal_entries';
        $table_journal_lines = $wpdb->prefix . 'erp_journal_lines';
        $table_fiscal_periods = $wpdb->prefix . 'erp_fiscal_periods';

        $sql = "CREATE TABLE {$table_accounts} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(50) NOT NULL,
            name VARCHAR(255) NOT NULL,
            type ENUM('asset','liability','equity','revenue','expense') NOT NULL,
            parent_id BIGINT(20) UNSIGNED DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            description TEXT DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY type (type),
            KEY parent_id (parent_id)
        ) {$charset_collate};";

        $sql .= "CREATE TABLE {$table_journal_entries} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_number VARCHAR(50) NOT NULL,
            date DATE NOT NULL,
            description TEXT NOT NULL,
            reference_type VARCHAR(50) DEFAULT NULL,
            reference_id BIGINT(20) UNSIGNED DEFAULT NULL,
            is_posted TINYINT(1) DEFAULT 0,
            created_by BIGINT(20) UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY entry_number (entry_number),
            KEY date (date),
            KEY reference (reference_type, reference_id)
        ) {$charset_collate};";

        $sql .= "CREATE TABLE {$table_journal_lines} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_id BIGINT(20) UNSIGNED NOT NULL,
            account_id BIGINT(20) UNSIGNED NOT NULL,
            debit DECIMAL(12,2) DEFAULT 0.00,
            credit DECIMAL(12,2) DEFAULT 0.00,
            description TEXT DEFAULT NULL,
            PRIMARY KEY (id),
            KEY entry_id (entry_id),
            KEY account_id (account_id)
        ) {$charset_collate};";

        $sql .= "CREATE TABLE {$table_fiscal_periods} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            is_closed TINYINT(1) DEFAULT 0,
            PRIMARY KEY (id)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    private static function create_kitchen_tables($charset_collate)
    {
        global $wpdb;

        $table_recipes = $wpdb->prefix . 'erp_recipes';
        $table_recipe_ingredients = $wpdb->prefix . 'erp_recipe_ingredients';
        $table_kitchen_orders = $wpdb->prefix . 'erp_kitchen_orders';
        $table_kitchen_order_items = $wpdb->prefix . 'erp_kitchen_order_items';
        $table_prep_tracking = $wpdb->prefix . 'erp_prep_tracking';

        $sql = "CREATE TABLE {$table_recipes} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            servings INT(11) DEFAULT 1,
            prep_time_minutes INT(11) DEFAULT NULL,
            cook_time_minutes INT(11) DEFAULT NULL,
            instructions TEXT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id)
        ) {$charset_collate};";

        $sql .= "CREATE TABLE {$table_recipe_ingredients} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            recipe_id BIGINT(20) UNSIGNED NOT NULL,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            quantity DECIMAL(10,3) NOT NULL,
            unit VARCHAR(50) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            PRIMARY KEY (id),
            KEY recipe_id (recipe_id),
            KEY product_id (product_id)
        ) {$charset_collate};";

        $sql .= "CREATE TABLE {$table_kitchen_orders} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT(20) UNSIGNED NOT NULL,
            branch_id BIGINT(20) UNSIGNED NOT NULL,
            station VARCHAR(100) DEFAULT NULL,
            priority INT(11) DEFAULT 0,
            status ENUM('pending','preparing','ready','completed','cancelled') DEFAULT 'pending',
            estimated_time INT(11) DEFAULT NULL,
            started_at DATETIME DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY branch_id (branch_id),
            KEY status (status)
        ) {$charset_collate};";

        $sql .= "CREATE TABLE {$table_kitchen_order_items} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            kitchen_order_id BIGINT(20) UNSIGNED NOT NULL,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            name VARCHAR(255) DEFAULT NULL,
            quantity INT(11) NOT NULL DEFAULT 1,
            status ENUM('pending','preparing','ready') DEFAULT 'pending',
            started_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY kitchen_order_id (kitchen_order_id),
            KEY product_id (product_id)
        ) {$charset_collate};";

        $sql .= "CREATE TABLE {$table_prep_tracking} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            kitchen_order_id BIGINT(20) UNSIGNED NOT NULL,
            recipe_id BIGINT(20) UNSIGNED DEFAULT NULL,
            employee_id BIGINT(20) UNSIGNED DEFAULT NULL,
            started_at DATETIME DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            actual_time_minutes INT(11) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            PRIMARY KEY (id),
            KEY kitchen_order_id (kitchen_order_id)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    private static function seed_default_data()
    {
        global $wpdb;

        $accounts_table = $wpdb->prefix . 'erp_accounts';
        $exists = $wpdb->get_var("SHOW TABLES LIKE '{$accounts_table}'");

        if ($exists) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$accounts_table}");
            if ($count > 0) {
                return;
            }
        }

        $default_accounts = [
            ['code' => '1000', 'name' => 'Cash', 'type' => 'asset'],
            ['code' => '1010', 'name' => 'Petty Cash', 'type' => 'asset'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset'],
            ['code' => '1200', 'name' => 'Inventory', 'type' => 'asset'],
            ['code' => '1300', 'name' => 'Prepaid Expenses', 'type' => 'asset'],
            ['code' => '1500', 'name' => 'Equipment', 'type' => 'asset'],
            ['code' => '1600', 'name' => 'Accumulated Depreciation', 'type' => 'asset'],
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability'],
            ['code' => '2100', 'name' => 'Accrued Expenses', 'type' => 'liability'],
            ['code' => '2200', 'name' => 'Sales Tax Payable', 'type' => 'liability'],
            ['code' => '2300', 'name' => 'VAT Payable', 'type' => 'liability'],
            ['code' => '2500', 'name' => 'Loans Payable', 'type' => 'liability'],
            ['code' => '3000', 'name' => "Owner's Equity", 'type' => 'equity'],
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity'],
            ['code' => '3200', 'name' => 'Current Year Earnings', 'type' => 'equity'],
            ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'revenue'],
            ['code' => '4100', 'name' => 'Service Revenue', 'type' => 'revenue'],
            ['code' => '4200', 'name' => 'Other Income', 'type' => 'revenue'],
            ['code' => '4300', 'name' => 'Discount Income', 'type' => 'revenue'],
            ['code' => '5000', 'name' => 'Cost of Goods Sold', 'type' => 'expense'],
            ['code' => '5100', 'name' => 'Purchase Discounts', 'type' => 'expense'],
            ['code' => '6000', 'name' => 'Salaries Expense', 'type' => 'expense'],
            ['code' => '6100', 'name' => 'Rent Expense', 'type' => 'expense'],
            ['code' => '6200', 'name' => 'Utilities Expense', 'type' => 'expense'],
            ['code' => '6300', 'name' => 'Supplies Expense', 'type' => 'expense'],
            ['code' => '6400', 'name' => 'Marketing Expense', 'type' => 'expense'],
            ['code' => '6500', 'name' => 'Depreciation Expense', 'type' => 'expense'],
            ['code' => '6600', 'name' => 'Insurance Expense', 'type' => 'expense'],
            ['code' => '6700', 'name' => 'Miscellaneous Expense', 'type' => 'expense'],
        ];

        foreach ($default_accounts as $account) {
            $wpdb->insert($accounts_table, $account, ['%s', '%s', '%s']);
        }
    }
}
