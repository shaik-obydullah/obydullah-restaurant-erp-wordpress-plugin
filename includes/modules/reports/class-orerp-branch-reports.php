<?php
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL table names come from $wpdb->prefix and every value is bound via $wpdb->prepare() placeholders; direct queries are used for the ERP-specific tables that have no core caching API.
/**
 * Branch Comparison Report
 *
 * Side-by-side performance across branches: employees, purchase orders,
 * kitchen volume and stock levels within a period.
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Branch_Reports
{
    public function orerp_get_branch_comparison($from = 'orerp_', $to = 'orerp_')
    {
        global $wpdb;

        $from = Obydullah_ERP_Helpers::orerp_is_valid_date($from) ? $from : gmdate('Y-m-01');
        $to   = Obydullah_ERP_Helpers::orerp_is_valid_date($to) ? $to : gmdate('Y-m-d');

        $branches_table = $wpdb->prefix . 'erp_branches';
        $cache_key = 'branch_list_active';
        $cached = Obydullah_ERP_Cache::get($cache_key, $branches_table);
        if (false !== $cached) {
            $branches = $cached;
        } else {
            $branches = $wpdb->get_results("SELECT id, name FROM {$branches_table} WHERE is_active = 1 ORDER BY name");
            Obydullah_ERP_Cache::set($cache_key, $branches_table, $branches);
        }

        $comparison = [];

        foreach ($branches as $branch) {
            $emp_table = $wpdb->prefix . 'erp_employees';

            $cache_key = 'branch_emp_count_' . $branch->id;
            $cached = Obydullah_ERP_Cache::get($cache_key, $emp_table);
            if (false !== $cached) {
                $emp_count = $cached;
            } else {
                $emp_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}erp_employees WHERE branch_id = %d AND is_active = 1",
                    $branch->id
                ));
                Obydullah_ERP_Cache::set($cache_key, $emp_table, $emp_count);
            }

            $po_table = $wpdb->prefix . 'erp_purchase_orders';

            $cache_key = 'branch_po_count_' . $branch->id . '_' . $from . '_' . $to;
            $cached = Obydullah_ERP_Cache::get($cache_key, $po_table);
            if (false !== $cached) {
                $po_count = $cached;
            } else {
                $po_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}erp_purchase_orders WHERE branch_id = %d AND created_at BETWEEN %s AND %s",
                    $branch->id, $from . ' 00:00:00', $to . ' 23:59:59'
                ));
                Obydullah_ERP_Cache::set($cache_key, $po_table, $po_count);
            }

            $cache_key = 'branch_po_total_' . $branch->id . '_' . $from . '_' . $to;
            $cached = Obydullah_ERP_Cache::get($cache_key, $po_table);
            if (false !== $cached) {
                $po_total = $cached;
            } else {
                $po_total = $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(total), 0) FROM {$wpdb->prefix}erp_purchase_orders WHERE branch_id = %d AND status = 'received' AND created_at BETWEEN %s AND %s",
                    $branch->id, $from . ' 00:00:00', $to . ' 23:59:59'
                ));
                Obydullah_ERP_Cache::set($cache_key, $po_table, $po_total);
            }

            $ko_table = $wpdb->prefix . 'erp_kitchen_orders';

            $cache_key = 'branch_kitchen_orders_' . $branch->id . '_' . $from . '_' . $to;
            $cached = Obydullah_ERP_Cache::get($cache_key, $ko_table);
            if (false !== $cached) {
                $kitchen_orders = $cached;
            } else {
                $kitchen_orders = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}erp_kitchen_orders WHERE branch_id = %d AND DATE(created_at) BETWEEN %s AND %s",
                    $branch->id, $from, $to
                ));
                Obydullah_ERP_Cache::set($cache_key, $ko_table, $kitchen_orders);
            }

            $bs_table = $wpdb->prefix . 'erp_branch_stock';

            $cache_key = 'branch_stock_count_' . $branch->id;
            $cached = Obydullah_ERP_Cache::get($cache_key, $bs_table);
            if (false !== $cached) {
                $stock_count = $cached;
            } else {
                $stock_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(quantity), 0) FROM {$wpdb->prefix}erp_branch_stock WHERE branch_id = %d",
                    $branch->id
                ));
                Obydullah_ERP_Cache::set($cache_key, $bs_table, $stock_count);
            }

            $comparison[] = [
                'branch_id'      => $branch->id,
                'branch_name'    => $branch->name,
                'employees'      => intval($emp_count),
                'po_count'       => intval($po_count),
                'po_total'       => floatval($po_total),
                'kitchen_orders' => intval($kitchen_orders),
                'stock_items'    => floatval($stock_count),
            ];
        }

        return ['period' => ['from' => $from, 'to' => $to], 'branches' => $comparison];
    }
}
