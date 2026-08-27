<?php
/**
 * General Ledger
 *
 * Consolidated view of all posted journal entries: every account with its
 * opening balance, total debits/credits and running balance per entry.
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Ledger
{
    private $table_entries;
    private $table_lines;
    private $table_accounts;

    public function __construct()
    {
        global $wpdb;

        $this->table_entries  = $wpdb->prefix . 'erp_journal_entries';
        $this->table_lines    = $wpdb->prefix . 'erp_journal_lines';
        $this->table_accounts = $wpdb->prefix . 'erp_accounts';

        add_action('wp_ajax_orerp_get_ledger', [$this, 'orerp_ajax_get_ledger']);
    }

    /**
     * Build the general ledger.
     *
     * @param string $from       Start date (Y-m-d).
     * @param string $to         End date (Y-m-d).
     * @param string $account_id Optional account filter.
     * @return array
     */
    public function orerp_render_page()
    {
        global $wpdb;
        $accounts = $wpdb->get_results("SELECT id, code, name FROM {$this->table_accounts} WHERE is_active = 1 ORDER BY code") ?: [];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('General Ledger', 'obydullah-restaurant-erp'); ?></h1>
            <hr class="wp-header-end">

            <div class="orerp-card">
                <div class="orerp-filters" style="margin-bottom:15px;">
                    <div class="filter-group">
                        <label><?php esc_html_e('From', 'obydullah-restaurant-erp'); ?></label>
                        <input type="date" id="ledger-date-from" class="regular-text"
                            value="<?php echo esc_attr(gmdate('Y-m-01')); ?>">
                    </div>
                    <div class="filter-group">
                        <label><?php esc_html_e('To', 'obydullah-restaurant-erp'); ?></label>
                        <input type="date" id="ledger-date-to" class="regular-text"
                            value="<?php echo esc_attr(gmdate('Y-m-d')); ?>">
                    </div>
                    <div class="filter-group">
                        <label><?php esc_html_e('Account', 'obydullah-restaurant-erp'); ?></label>
                        <select id="ledger-account-filter">
                            <option value=""><?php esc_html_e('All Accounts', 'obydullah-restaurant-erp'); ?></option>
                            <?php foreach ($accounts as $account): ?>
                            <option value="<?php echo esc_attr($account->id); ?>"><?php echo esc_html($account->code . ' - ' . $account->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="button" id="run-ledger" class="button button-primary">
                            <?php esc_html_e('Load Ledger', 'obydullah-restaurant-erp'); ?>
                        </button>
                    </div>
                </div>

                <div id="ledger-content">
                    <p class="description"><?php esc_html_e('Select a date range and click "Load Ledger".', 'obydullah-restaurant-erp'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    public function orerp_get_ledger($from = 'orerp_', $to = 'orerp_', $account_id = 0)
    {
        global $wpdb;

        $from = Obydullah_ERP_Helpers::orerp_is_valid_date($from) ? $from : gmdate('Y-m-01');
        $to   = Obydullah_ERP_Helpers::orerp_is_valid_date($to) ? $to : gmdate('Y-m-d');

        $where   = " AND je.is_posted = 1 AND je.date BETWEEN %s AND %s";
        $prepare = [$from, $to];

        if ($account_id > 0) {
            $where .= " AND jl.account_id = %d";
            $prepare[] = $account_id;
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT jl.*, je.date AS entry_date, je.description AS entry_description,
                je.reference_type, je.reference_id, a.code AS account_code, a.name AS account_name, a.type AS account_type
            FROM {$this->table_lines} jl
            INNER JOIN {$this->table_entries} je ON jl.entry_id = je.id
            INNER JOIN {$this->table_accounts} a ON jl.account_id = a.id
            WHERE 1=1{$where}
            ORDER BY je.date ASC, je.id ASC, jl.id ASC",
            $prepare
        )) ?: [];

        // Opening balances grouped by account (everything posted before $from).
        $opening = $this->orerp_get_opening_balances($from, $account_id);

        $ledger = [];
        $totals = ['debit' => 0, 'credit' => 0];

        foreach ($rows as $row) {
            $key = (int) $row->account_id;

            if (!isset($ledger[$key])) {
                $ledger[$key] = [
                    'account_id'   => $key,
                    'account_code' => $row->account_code,
                    'account_name' => $row->account_name,
                    'account_type' => $row->account_type,
                    'opening'      => $opening[$key] ?? 0,
                    'debit'        => 0,
                    'credit'       => 0,
                    'entries'      => [],
                ];
            }

            $debit  = floatval($row->debit);
            $credit = floatval($row->credit);

            $ledger[$key]['debit']  += $debit;
            $ledger[$key]['credit'] += $credit;

            $ledger[$key]['entries'][] = [
                'id'              => (int) $row->id,
                'entry_id'        => (int) $row->entry_id,
                'entry_date'      => $row->entry_date,
                'entry_description' => $row->entry_description,
                'reference_type'  => $row->reference_type,
                'reference_id'    => (int) $row->reference_id,
                'debit'           => $debit,
                'credit'          => $credit,
                'description'     => $row->description,
            ];

            $totals['debit']  += $debit;
            $totals['credit'] += $credit;
        }

        $accounts = array_values($ledger);

        return [
            'accounts' => $accounts,
            'totals'   => $totals,
            'from'     => $from,
            'to'       => $to,
        ];
    }

    /**
     * Opening balance per account = sum(debit - credit) posted before $from.
     *
     * @param string $from       Start date.
     * @param int    $account_id Account filter.
     * @return array
     */
    private function orerp_get_opening_balances($from, $account_id = 0)
    {
        global $wpdb;

        $where   = " AND je.is_posted = 1 AND je.date < %s";
        $prepare = [$from];

        if ($account_id > 0) {
            $where .= " AND jl.account_id = %d";
            $prepare[] = $account_id;
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT jl.account_id, SUM(jl.debit) AS debit, SUM(jl.credit) AS credit
            FROM {$this->table_lines} jl
            INNER JOIN {$this->table_entries} je ON jl.entry_id = je.id
            WHERE 1=1{$where}
            GROUP BY jl.account_id",
            $prepare
        )) ?: [];

        $balances = [];
        foreach ($rows as $row) {
            $balances[(int) $row->account_id] = floatval($row->debit) - floatval($row->credit);
        }

        return $balances;
    }

    // --- AJAX ---

    public function orerp_ajax_get_ledger()
    {
        check_ajax_referer('orerp_ledger', 'nonce');

        if (!Obydullah_ERP_Helpers::can('orerp_reports')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $from       = sanitize_text_field(wp_unslash($_POST['from'] ?? 'orerp_'));
        $to         = sanitize_text_field(wp_unslash($_POST['to'] ?? 'orerp_'));
        $account_id = intval($_POST['account_id'] ?? 0);

        $result = $this->orerp_get_ledger($from, $to, $account_id);

        // Apply account type ordering for a stable display.
        $order = ['asset' => 0, 'liability' => 1, 'equity' => 2, 'revenue' => 3, 'expense' => 4];
        usort($result['accounts'], function ($a, $b) use ($order) {
            $ta = $order[$a['account_type']] ?? 5;
            $tb = $order[$b['account_type']] ?? 5;
            return $ta <=> $tb;
        });

        wp_send_json_success($result);
    }
}
