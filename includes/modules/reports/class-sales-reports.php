<?php
/**
 * Sales Report
 *
 * Revenue, COGS, gross profit, purchase summary and monthly trend within a
 * period. Data comes from posted journal entries.
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Sales_Reports
{
    public function get_sales_report($from = '', $to = '', $branch_id = 0)
    {
        global $wpdb;

        $from      = Obydullah_ERP_Helpers::is_valid_date($from) ? $from : gmdate('Y-m-01');
        $to        = Obydullah_ERP_Helpers::is_valid_date($to) ? $to : gmdate('Y-m-d');
        $branch_id = intval($branch_id);

        $revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(jl.credit), 0)
            FROM {$wpdb->prefix}erp_journal_lines jl
            JOIN {$wpdb->prefix}erp_journal_entries je ON jl.entry_id = je.id
            JOIN {$wpdb->prefix}erp_accounts ja ON jl.account_id = ja.id
            WHERE ja.type = 'revenue'
            AND je.date BETWEEN %s AND %s
            AND je.is_posted = 1",
            $from, $to
        ));

        $cogs = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(jl.debit), 0)
            FROM {$wpdb->prefix}erp_journal_lines jl
            JOIN {$wpdb->prefix}erp_journal_entries je ON jl.entry_id = je.id
            JOIN {$wpdb->prefix}erp_accounts ja ON jl.account_id = ja.id
            WHERE ja.code = '5000'
            AND je.date BETWEEN %s AND %s
            AND je.is_posted = 1",
            $from, $to
        ));

        if ($branch_id > 0) {
            $purchase_data = $wpdb->get_results($wpdb->prepare(
                "SELECT po.status, COUNT(*) as count, COALESCE(SUM(po.total), 0) as total
                FROM {$wpdb->prefix}erp_purchase_orders po
                WHERE po.created_at BETWEEN %s AND %s AND po.branch_id = %d
                GROUP BY po.status",
                $from . ' 00:00:00', $to . ' 23:59:59', $branch_id
            ));
        } else {
            $purchase_data = $wpdb->get_results($wpdb->prepare(
                "SELECT po.status, COUNT(*) as count, COALESCE(SUM(po.total), 0) as total
                FROM {$wpdb->prefix}erp_purchase_orders po
                WHERE po.created_at BETWEEN %s AND %s
                GROUP BY po.status",
                $from . ' 00:00:00', $to . ' 23:59:59'
            ));
        }

        $monthly = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(je.date, '%%Y-%%m') as month,
                    COALESCE(SUM(CASE WHEN ja.type = 'revenue' THEN jl.credit ELSE 0 END), 0) as revenue,
                    COALESCE(SUM(CASE WHEN ja.code = '5000' THEN jl.debit ELSE 0 END), 0) as cogs
            FROM {$wpdb->prefix}erp_journal_entries je
            JOIN {$wpdb->prefix}erp_journal_lines jl ON jl.entry_id = je.id
            JOIN {$wpdb->prefix}erp_accounts ja ON jl.account_id = ja.id
            WHERE je.date BETWEEN %s AND %s AND je.is_posted = 1
            GROUP BY month ORDER BY month",
            $from, $to
        ));

        $revenue = floatval($revenue);
        $cogs    = floatval($cogs);

        return [
            'period'       => ['from' => $from, 'to' => $to],
            'revenue'      => $revenue,
            'cogs'         => $cogs,
            'gross_profit' => $revenue - $cogs,
            'margin'       => $revenue > 0 ? round(($revenue - $cogs) / $revenue * 100, 1) : 0,
            'purchases'    => $purchase_data,
            'monthly'      => $monthly,
        ];
    }
}
