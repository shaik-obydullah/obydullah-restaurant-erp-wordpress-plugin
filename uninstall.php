<?php
/**
 * Plugin Uninstaller
 *
 * Removes all Obydullah Restaurant ERP data when the plugin is deleted
 * from the WordPress admin: custom DB tables, options, and roles/caps.
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

// Bail if WordPress uninstall hook was not called.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Safety: only run for this plugin (prevents accidental data loss).
if ('obydullah-restaurant-erp/obydullah-restaurant-erp.php' !== WP_UNINSTALL_PLUGIN) {
    exit;
}

global $wpdb;

$prefix = $wpdb->prefix;

/**
 * 1. Drop custom tables.
 *
 * Drop both the current (orerp_) and legacy (erp_) prefixes so that
 * installations predating the schema migration are fully cleaned up.
 */
$tables = [
    'accounts', 'attendance', 'branches', 'branch_stock', 'employees',
    'fiscal_periods', 'journal_entries', 'journal_lines',
    'kitchen_order_items', 'kitchen_orders', 'prep_tracking',
    'purchase_items', 'purchase_orders', 'purchase_payments',
    'recipe_ingredients', 'recipes', 'shifts', 'supplier_products',
    'suppliers', 'transfer_items', 'transfers',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$prefix}orerp_{$table}");
    $wpdb->query("DROP TABLE IF EXISTS {$prefix}erp_{$table}");
}

/**
 * 2. Delete plugin options.
 */
$options = [
    'orerp_version',
    'orerp_currency',
    'orerp_currency_position',
    'orerp_date_format',
    'orerp_current_branch',
    'orerp_tax_rate',
    'orerp_db_version',
];

foreach ($options as $option) {
    delete_option($option);
}

// Dynamic per-table cache version options.
$wpdb->query(
    $wpdb->prepare("DELETE FROM {$prefix}options WHERE option_name LIKE %s", 'orerp_cache_%')
);

/**
 * 3. Remove custom roles and capabilities.
 */
remove_role('restaurant_manager');
remove_role('restaurant_kitchen_staff');
remove_role('restaurant_cashier');

foreach (wp_roles()->roles as $role_name => $role) {
    $role = get_role($role_name);
    if (!$role) {
        continue;
    }
    foreach (['orerp_admin', 'orerp_kitchen', 'orerp_reports'] as $cap) {
        $role->remove_cap($cap);
    }
}