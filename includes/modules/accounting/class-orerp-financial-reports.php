<?php
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL table names come from $wpdb->prefix and every value is bound via $wpdb->prepare() placeholders; direct queries are used for the ERP-specific tables that have no core caching API.
/**
 * Financial Reports - Profit & Loss and Balance Sheet
 *
 * Period-aware statements built from posted journal entries. P&L covers a
 * date range; the Balance Sheet shows balances as of a specific date.
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Financial_Reports
{
    private $table_accounts;
    private $table_lines;
    private $table_entries;

    public function __construct()
    {
        global $wpdb;

        $this->table_accounts = $wpdb->prefix . 'erp_accounts';
        $this->table_lines    = $wpdb->prefix . 'erp_journal_lines';
        $this->table_entries  = $wpdb->prefix . 'erp_journal_entries';
    }

    /**
     * Posted debit/credit totals per account, optionally within a date range.
     *
     * @param string $from Start date (inclusive).
     * @param string $to   End date (inclusive).
     * @return array account_id => ['debit'=>float,'credit'=>float]
     */
    private function orerp_get_posted_totals($from = 'orerp_', $to = 'orerp_')
    {
        global $wpdb;

        $where   = ' AND je.is_posted = 1';
        $prepare = [];

        if (Obydullah_ERP_Helpers::orerp_is_valid_date($from)) {
            $where   .= ' AND je.date >= %s';
            $prepare[] = $from;
        }

        if (Obydullah_ERP_Helpers::orerp_is_valid_date($to)) {
            $where   .= ' AND je.date <= %s';
            $prepare[] = $to;
        }

        $cache_key = 'posted_totals_' . sanitize_key($from . '_' . $to);
        $cached = Obydullah_ERP_Cache::get($cache_key, $this->table_lines);
        if (false !== $cached) {
            $rows = $cached;
        } elseif (!empty($prepare)) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT jl.account_id,
                    COALESCE(SUM(jl.debit), 0) AS debit,
                    COALESCE(SUM(jl.credit), 0) AS credit
                FROM {$this->table_lines} jl
                INNER JOIN {$this->table_entries} je ON jl.entry_id = je.id
                WHERE 1=1{$where}
                GROUP BY jl.account_id",
                $prepare
            ));
            Obydullah_ERP_Cache::set($cache_key, $this->table_lines, $rows);
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT jl.account_id,
                    COALESCE(SUM(jl.debit), 0) AS debit,
                    COALESCE(SUM(jl.credit), 0) AS credit
                FROM {$this->table_lines} jl
                INNER JOIN {$this->table_entries} je ON jl.entry_id = je.id
                WHERE 1=1 AND 1 = %d
                GROUP BY jl.account_id",
                1
            ));
            Obydullah_ERP_Cache::set($cache_key, $this->table_lines, $rows);
        }
        $rows = $rows ?: [];

        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row->account_id] = [
                'debit'  => floatval($row->debit),
                'credit' => floatval($row->credit),
            ];
        }

        return $totals;
    }

    /**
     * Profit & Loss for a date range.
     *
     * @param string $from Start date.
     * @param string $to   End date.
     * @return array
     */
    public function orerp_get_profit_loss($from = 'orerp_', $to = 'orerp_')
    {
        global $wpdb;

        $from = Obydullah_ERP_Helpers::orerp_is_valid_date($from) ? $from : '1970-01-01';
        $to   = Obydullah_ERP_Helpers::orerp_is_valid_date($to) ? $to : gmdate('Y-m-d');

        $totals = $this->orerp_get_posted_totals($from, $to);

        $revenue_items = [];
        $expense_items = [];
        $total_revenue = 0;
        $total_expense = 0;

        $types = ['revenue', 'expense'];
        $cache_key = 'pl_accounts';
        $cached = Obydullah_ERP_Cache::get($cache_key, $this->table_accounts);
        if (false !== $cached) {
            $accounts = $cached;
        } else {
            $accounts = $wpdb->get_results($wpdb->prepare(
                "SELECT id, code, name, type FROM {$this->table_accounts} WHERE type IN (%s, %s) AND is_active = 1 ORDER BY code",
                $types[0],
                $types[1]
            ));
            Obydullah_ERP_Cache::set($cache_key, $this->table_accounts, $accounts);
        }
        $accounts = $accounts ?: [];

        foreach ($accounts as $acc) {
            $t = $totals[(int) $acc->id] ?? ['debit' => 0, 'credit' => 0];

            if ($acc->type === 'revenue') {
                $amount = abs($t['credit'] - $t['debit']);
                if ($amount != 0) {
                    $revenue_items[] = ['code' => $acc->code, 'name' => $acc->name, 'amount' => $amount];
                    $total_revenue += $amount;
                }
            } else {
                $amount = abs($t['debit'] - $t['credit']);
                if ($amount != 0) {
                    $expense_items[] = ['code' => $acc->code, 'name' => $acc->name, 'amount' => $amount];
                    $total_expense += $amount;
                }
            }
        }

        return [
            'revenue_items' => $revenue_items,
            'expense_items' => $expense_items,
            'total_revenue' => $total_revenue,
            'total_expense' => $total_expense,
            'net_income'    => $total_revenue - $total_expense,
            'from'          => $from,
            'to'            => $to,
        ];
    }

    /**
     * Per-account debit/credit totals within a period (all active accounts).
     *
     * @param string $from Start date.
     * @param string $to   End date.
     * @return array
     */
    public function orerp_get_account_breakdown($from = 'orerp_', $to = 'orerp_')
    {
        global $wpdb;

        $from = Obydullah_ERP_Helpers::orerp_is_valid_date($from) ? $from : gmdate('Y-m-01');
        $to   = Obydullah_ERP_Helpers::orerp_is_valid_date($to) ? $to : gmdate('Y-m-d');

        $totals = $this->orerp_get_posted_totals($from, $to);

        $cache_key = 'breakdown_accounts';
        $cached = Obydullah_ERP_Cache::get($cache_key, $this->table_accounts);
        if (false !== $cached) {
            $accounts = $cached;
        } else {
            $accounts = $wpdb->get_results($wpdb->prepare("SELECT id, code, name, type FROM {$this->table_accounts} WHERE is_active = 1 AND 1 = %d ORDER BY code", 1));
            Obydullah_ERP_Cache::set($cache_key, $this->table_accounts, $accounts);
        }
        $accounts = $accounts ?: [];

        $result = [];
        foreach ($accounts as $acc) {
            $t = $totals[(int) $acc->id] ?? ['debit' => 0, 'credit' => 0];
            $result[] = [
                'id'           => (int) $acc->id,
                'code'         => $acc->code,
                'name'         => $acc->name,
                'type'         => $acc->type,
                'total_debit'  => $t['debit'],
                'total_credit' => $t['credit'],
            ];
        }

        return $result;
    }

    /**
     * Balance Sheet as of a given date.
     *
     * @param string $as_of Balances are accumulated up to and including this date.
     * @return array
     */
    public function orerp_get_balance_sheet($as_of = 'orerp_')
    {
        global $wpdb;

        $as_of = Obydullah_ERP_Helpers::orerp_is_valid_date($as_of) ? $as_of : gmdate('Y-m-d');

        $totals = $this->orerp_get_posted_totals('orerp_', $as_of);

        $result = [];

        foreach (['asset', 'liability', 'equity'] as $type) {
            $cache_key = 'balance_sheet_accounts_' . $type;
            $cached = Obydullah_ERP_Cache::get($cache_key, $this->table_accounts);
            if (false !== $cached) {
                $accounts = $cached;
            } else {
                $accounts = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, code, name FROM {$this->table_accounts} WHERE type = %s AND is_active = 1 ORDER BY code",
                    $type
                ));
                Obydullah_ERP_Cache::set($cache_key, $this->table_accounts, $accounts);
            }
            $accounts = $accounts ?: [];

            $items = [];
            $total = 0;

            foreach ($accounts as $acc) {
                $t = $totals[(int) $acc->id] ?? ['debit' => 0, 'credit' => 0];
                $amount = abs($t['debit'] - $t['credit']);

                if ($amount > 0) {
                    $items[] = ['code' => $acc->code, 'name' => $acc->name, 'amount' => $amount];
                    $total += $amount;
                }
            }

            $result[$type] = ['items' => $items, 'total' => $total];
        }

        $result['as_of'] = $as_of;

        return $result;
    }
}
