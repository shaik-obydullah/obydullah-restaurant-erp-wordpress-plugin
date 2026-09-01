<?php
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL table names come from $wpdb->prefix and every value is bound via $wpdb->prepare() placeholders; direct queries are used for the ERP-specific tables that have no core caching API.
/**
 * Tax Reports - VAT / GST
 *
 * Output VAT (collected on sales, VAT Payable account) vs input VAT (paid on
 * purchases) and the resulting net payable per period.
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Tax_Reports
{
    private $table_orders;
    private $table_entries;
    private $table_lines;
    private $table_accounts;

    public function __construct()
    {
        global $wpdb;

        $this->table_orders   = $wpdb->prefix . 'erp_purchase_orders';
        $this->table_entries  = $wpdb->prefix . 'erp_journal_entries';
        $this->table_lines    = $wpdb->prefix . 'erp_journal_lines';
        $this->table_accounts = $wpdb->prefix . 'erp_accounts';

        add_action('wp_ajax_orerp_get_tax_summary', [$this, 'orerp_ajax_get_tax_summary']);
    }

    public function orerp_render_page()
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Tax Reports', 'obydullah-restaurant-erp'); ?></h1>
            <div class="orerp-print-actions" style="margin:8px 0 12px;">
                <button type="button" id="orerp-print-report" class="button"><?php esc_html_e('Print / PDF', 'obydullah-restaurant-erp'); ?></button>
            </div>
            <hr class="wp-header-end">

            <div class="orerp-card">
                <div class="orerp-filters" style="margin-bottom:15px;">
                    <div class="filter-group">
                        <label><?php esc_html_e('From', 'obydullah-restaurant-erp'); ?></label>
                        <input type="date" id="tax-date-from" class="regular-text"
                            value="<?php echo esc_attr(gmdate('Y-m-01')); ?>">
                    </div>
                    <div class="filter-group">
                        <label><?php esc_html_e('To', 'obydullah-restaurant-erp'); ?></label>
                        <input type="date" id="tax-date-to" class="regular-text"
                            value="<?php echo esc_attr(gmdate('Y-m-d')); ?>">
                    </div>
                    <div class="filter-actions">
                        <button type="button" id="run-tax-report" class="button button-primary">
                            <?php esc_html_e('Generate Tax Summary', 'obydullah-restaurant-erp'); ?>
                        </button>
                    </div>
                </div>

                <div id="tax-report-content">
                    <p class="description"><?php esc_html_e('Select a period and click "Generate Tax Summary".', 'obydullah-restaurant-erp'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * VAT summary for a date range.
     *
     * @param string $from Start date.
     * @param string $to   End date.
     * @return array
     */
    public function orerp_get_vat_summary($from = 'orerp_', $to = 'orerp_')
    {
        global $wpdb;

        $from = Obydullah_ERP_Helpers::orerp_is_valid_date($from) ? $from : gmdate('Y-m-01');
        $to   = Obydullah_ERP_Helpers::orerp_is_valid_date($to) ? $to : gmdate('Y-m-d');

        $output_vat = $this->orerp_get_output_vat($from, $to);
        $input_vat  = $this->orerp_get_input_vat($from, $to);

        return [
            'output_vat' => $output_vat,
            'input_vat'  => $input_vat,
            'net_payable' => $output_vat - $input_vat,
            'from'       => $from,
            'to'         => $to,
        ];
    }

    /**
     * Output VAT collected: posted credits to the VAT payable account (2300).
     *
     * @param string $from Start date.
     * @param string $to   End date.
     * @return float
     */
    public function orerp_get_output_vat($from, $to)
    {
        global $wpdb;

        $cache_key_vat_account = 'vat_account_2300';
        $vat_account = Obydullah_ERP_Cache::get($cache_key_vat_account, $this->table_accounts);
        if (false === $vat_account) {
            $vat_account = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table_accounts} WHERE code = %s LIMIT 1",
                '2300'
            ));
            Obydullah_ERP_Cache::set($cache_key_vat_account, $this->table_accounts, $vat_account);
        }

        if (!$vat_account) {
            return 0;
        }

        $cache_key = 'output_vat_' . sanitize_key($from . '_' . $to);
        $cached = Obydullah_ERP_Cache::get($cache_key, $this->table_lines);
        if (false !== $cached) {
            return $cached;
        }

        $total = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(jl.credit), 0)
            FROM {$this->table_lines} jl
            INNER JOIN {$this->table_entries} je ON jl.entry_id = je.id
            WHERE jl.account_id = %d AND je.is_posted = 1
                AND je.date BETWEEN %s AND %s",
            intval($vat_account), $from, $to
        )));

        Obydullah_ERP_Cache::set($cache_key, $this->table_lines, $total);
        return $total;
    }

    /**
     * Input VAT paid: tax amounts on non-cancelled purchase orders in period.
     *
     * @param string $from Start date.
     * @param string $to   End date.
     * @return float
     */
    public function orerp_get_input_vat($from, $to)
    {
        global $wpdb;

        $cache_key = 'input_vat_' . sanitize_key($from . '_' . $to);
        $cached = Obydullah_ERP_Cache::get($cache_key, $this->table_orders);
        if (false !== $cached) {
            return $cached;
        }

        $total = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(tax_amount), 0)
            FROM {$this->table_orders}
            WHERE status != 'cancelled'
                AND DATE(created_at) BETWEEN %s AND %s",
            $from, $to
        )));

        Obydullah_ERP_Cache::set($cache_key, $this->table_orders, $total);
        return $total;
    }

    /**
     * Monthly breakdown of output vs input VAT.
     *
     * @param string $from Start date.
     * @param string $to   End date.
     * @return array
     */
    public function orerp_get_vat_by_month($from = 'orerp_', $to = 'orerp_')
    {
        global $wpdb;

        $from = Obydullah_ERP_Helpers::orerp_is_valid_date($from) ? $from : gmdate('Y-01-01');
        $to   = Obydullah_ERP_Helpers::orerp_is_valid_date($to) ? $to : gmdate('Y-m-d');

        $output_by_month = [];
        $cache_key_vat_account = 'vat_account_2300';
        $vat_account = Obydullah_ERP_Cache::get($cache_key_vat_account, $this->table_accounts);
        if (false === $vat_account) {
            $vat_account = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table_accounts} WHERE code = %s LIMIT 1",
                '2300'
            ));
            Obydullah_ERP_Cache::set($cache_key_vat_account, $this->table_accounts, $vat_account);
        }

        if ($vat_account) {
            $cache_key_output = 'vat_output_by_month_' . sanitize_key($from . '_' . $to);
            $cached_output = Obydullah_ERP_Cache::get($cache_key_output, $this->table_lines);
            if (false !== $cached_output) {
                $rows = $cached_output;
            } else {
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT DATE_FORMAT(je.date, '%%Y-%%m') AS month, COALESCE(SUM(jl.credit), 0) AS total
                    FROM {$this->table_lines} jl
                    INNER JOIN {$this->table_entries} je ON jl.entry_id = je.id
                    WHERE jl.account_id = %d AND je.is_posted = 1
                        AND je.date BETWEEN %s AND %s
                    GROUP BY month",
                    intval($vat_account),
                    $from,
                    $to
                ));
                Obydullah_ERP_Cache::set($cache_key_output, $this->table_lines, $rows);
            }
            $rows = $rows ?: [];

            foreach ($rows as $row) {
                $output_by_month[$row->month] = floatval($row->total);
            }
        }

        $input_by_month = [];
        $cache_key_input = 'vat_input_by_month_' . sanitize_key($from . '_' . $to);
        $cached_input = Obydullah_ERP_Cache::get($cache_key_input, $this->table_orders);
        if (false !== $cached_input) {
            $rows = $cached_input;
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT DATE_FORMAT(created_at, '%%Y-%%m') AS month, COALESCE(SUM(tax_amount), 0) AS total
                FROM {$this->table_orders}
                WHERE status != 'cancelled' AND DATE(created_at) BETWEEN %s AND %s
                GROUP BY month",
                $from,
                $to
            ));
            Obydullah_ERP_Cache::set($cache_key_input, $this->table_orders, $rows);
        }
        $rows = $rows ?: [];

        foreach ($rows as $row) {
            $input_by_month[$row->month] = floatval($row->total);
        }

        // Merge all months present in either set.
        $months = array_unique(array_merge(array_keys($output_by_month), array_keys($input_by_month)));
        sort($months);

        $result = [];
        foreach ($months as $month) {
            $output = $output_by_month[$month] ?? 0;
            $input  = $input_by_month[$month] ?? 0;
            $result[] = [
                'month'       => $month,
                'output_vat'  => $output,
                'input_vat'   => $input,
                'net_payable' => $output - $input,
            ];
        }

        return $result;
    }

    public function orerp_ajax_get_tax_summary()
    {
        check_ajax_referer('orerp_tax_reports', 'nonce');

        if (!Obydullah_ERP_Helpers::can('orerp_reports')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $from = sanitize_text_field(wp_unslash($_GET['from'] ?? 'orerp_'));
        $to   = sanitize_text_field(wp_unslash($_GET['to'] ?? 'orerp_'));

        $summary = $this->orerp_get_vat_summary($from, $to);
        $summary['monthly'] = $this->orerp_get_vat_by_month($summary['from'], $summary['to']);

        wp_send_json_success($summary);
    }
}
