<?php
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL table names come from $wpdb->prefix and every value is bound via $wpdb->prepare() placeholders; direct queries are used for the ERP-specific tables that have no core caching API.
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
    public function orerp_get_sales_report($from = 'orerp_', $to = 'orerp_', $branch_id = 0)
    {
        global $wpdb;

        $from      = Obydullah_ERP_Helpers::orerp_is_valid_date($from) ? $from : gmdate('Y-m-01');
        $to        = Obydullah_ERP_Helpers::orerp_is_valid_date($to) ? $to : gmdate('Y-m-d');
        $branch_id = intval($branch_id);

        $table = $wpdb->prefix . 'erp_journal_lines';

        $cache_key = 'sales_revenue_' . $from . '_' . $to;
        $cached = Obydullah_ERP_Cache::get($cache_key, $table);
        if (false !== $cached) {
            $revenue = $cached;
        } else {
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
            Obydullah_ERP_Cache::set($cache_key, $table, $revenue);
        }

        $cache_key = 'sales_cogs_' . $from . '_' . $to;
        $cached = Obydullah_ERP_Cache::get($cache_key, $table);
        if (false !== $cached) {
            $cogs = $cached;
        } else {
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
            Obydullah_ERP_Cache::set($cache_key, $table, $cogs);
        }

        $po_table = $wpdb->prefix . 'erp_purchase_orders';

        if ($branch_id > 0) {
            $cache_key = 'sales_purchase_data_branch_' . $branch_id . '_' . $from . '_' . $to;
            $cached = Obydullah_ERP_Cache::get($cache_key, $po_table);
            if (false !== $cached) {
                $purchase_data = $cached;
            } else {
                $purchase_data = $wpdb->get_results($wpdb->prepare(
                    "SELECT po.status, COUNT(*) as count, COALESCE(SUM(po.total), 0) as total
                    FROM {$wpdb->prefix}erp_purchase_orders po
                    WHERE po.created_at BETWEEN %s AND %s AND po.branch_id = %d
                    GROUP BY po.status",
                    $from . ' 00:00:00', $to . ' 23:59:59', $branch_id
                ));
                Obydullah_ERP_Cache::set($cache_key, $po_table, $purchase_data);
            }
        } else {
            $cache_key = 'sales_purchase_data_' . $from . '_' . $to;
            $cached = Obydullah_ERP_Cache::get($cache_key, $po_table);
            if (false !== $cached) {
                $purchase_data = $cached;
            } else {
                $purchase_data = $wpdb->get_results($wpdb->prepare(
                    "SELECT po.status, COUNT(*) as count, COALESCE(SUM(po.total), 0) as total
                    FROM {$wpdb->prefix}erp_purchase_orders po
                    WHERE po.created_at BETWEEN %s AND %s
                    GROUP BY po.status",
                    $from . ' 00:00:00', $to . ' 23:59:59'
                ));
                Obydullah_ERP_Cache::set($cache_key, $po_table, $purchase_data);
            }
        }

        $je_table = $wpdb->prefix . 'erp_journal_entries';
        $cache_key = 'sales_monthly_trend_' . $from . '_' . $to;
        $cached = Obydullah_ERP_Cache::get($cache_key, $je_table);
        if (false !== $cached) {
            $monthly = $cached;
        } else {
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
            Obydullah_ERP_Cache::set($cache_key, $je_table, $monthly);
        }

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
