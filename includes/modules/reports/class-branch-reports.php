<?php
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
    public function get_branch_comparison($from = '', $to = '')
    {
        global $wpdb;

        $from = Obydullah_ERP_Helpers::is_valid_date($from) ? $from : gmdate('Y-m-01');
        $to   = Obydullah_ERP_Helpers::is_valid_date($to) ? $to : gmdate('Y-m-d');

        $branches = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}erp_branches WHERE is_active = 1 ORDER BY name");
        $comparison = [];

        foreach ($branches as $branch) {
            $emp_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}erp_employees WHERE branch_id = %d AND is_active = 1",
                $branch->id
            ));

            $po_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}erp_purchase_orders WHERE branch_id = %d AND created_at BETWEEN %s AND %s",
                $branch->id, $from . ' 00:00:00', $to . ' 23:59:59'
            ));

            $po_total = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(total), 0) FROM {$wpdb->prefix}erp_purchase_orders WHERE branch_id = %d AND status = 'received' AND created_at BETWEEN %s AND %s",
                $branch->id, $from . ' 00:00:00', $to . ' 23:59:59'
            ));

            $kitchen_orders = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}erp_kitchen_orders WHERE branch_id = %d AND DATE(created_at) BETWEEN %s AND %s",
                $branch->id, $from, $to
            ));

            $stock_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(quantity), 0) FROM {$wpdb->prefix}erp_branch_stock WHERE branch_id = %d",
                $branch->id
            ));

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
