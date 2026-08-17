<?php
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

        add_action('wp_ajax_orerp_get_tax_summary', [$this, 'ajax_get_tax_summary']);
    }

    public function render_page()
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
                            value="<?php echo esc_attr(date('Y-m-01')); ?>">
                    </div>
                    <div class="filter-group">
                        <label><?php esc_html_e('To', 'obydullah-restaurant-erp'); ?></label>
                        <input type="date" id="tax-date-to" class="regular-text"
                            value="<?php echo esc_attr(date('Y-m-d')); ?>">
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
    public function get_vat_summary($from = '', $to = '')
    {
        global $wpdb;

        $from = Obydullah_ERP_Helpers::is_valid_date($from) ? $from : date('Y-m-01');
        $to   = Obydullah_ERP_Helpers::is_valid_date($to) ? $to : date('Y-m-d');

        $output_vat = $this->get_output_vat($from, $to);
        $input_vat  = $this->get_input_vat($from, $to);

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
    public function get_output_vat($from, $to)
    {
        global $wpdb;

        $vat_account = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_accounts} WHERE code = %s LIMIT 1",
            '2300'
        ));

        if (!$vat_account) {
            return 0;
        }

        $query = "SELECT COALESCE(SUM(jl.credit), 0)
            FROM {$this->table_lines} jl
            INNER JOIN {$this->table_entries} je ON jl.entry_id = je.id
            WHERE jl.account_id = %d AND je.is_posted = 1
                AND je.date BETWEEN %s AND %s";

        return floatval($wpdb->get_var($wpdb->prepare($query, intval($vat_account), $from, $to)));
    }

    /**
     * Input VAT paid: tax amounts on non-cancelled purchase orders in period.
     *
     * @param string $from Start date.
     * @param string $to   End date.
     * @return float
     */
    public function get_input_vat($from, $to)
    {
        global $wpdb;

        $query = "SELECT COALESCE(SUM(tax_amount), 0)
            FROM {$this->table_orders}
            WHERE status != 'cancelled'
                AND DATE(created_at) BETWEEN %s AND %s";

        return floatval($wpdb->get_var($wpdb->prepare($query, $from, $to)));
    }

    /**
     * Monthly breakdown of output vs input VAT.
     *
     * @param string $from Start date.
     * @param string $to   End date.
     * @return array
     */
    public function get_vat_by_month($from = '', $to = '')
    {
        global $wpdb;

        $from = Obydullah_ERP_Helpers::is_valid_date($from) ? $from : date('Y-01-01');
        $to   = Obydullah_ERP_Helpers::is_valid_date($to) ? $to : date('Y-m-d');

        $output_by_month = [];
        $vat_account = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_accounts} WHERE code = %s LIMIT 1",
            '2300'
        ));

        if ($vat_account) {
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
            )) ?: [];

            foreach ($rows as $row) {
                $output_by_month[$row->month] = floatval($row->total);
            }
        }

        $input_by_month = [];
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(created_at, '%%Y-%%m') AS month, COALESCE(SUM(tax_amount), 0) AS total
            FROM {$this->table_orders}
            WHERE status != 'cancelled' AND DATE(created_at) BETWEEN %s AND %s
            GROUP BY month",
            $from,
            $to
        )) ?: [];

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

    public function ajax_get_tax_summary()
    {
        check_ajax_referer('orerp_tax_reports', 'nonce');

        if (!Obydullah_ERP_Helpers::can('orerp_reports')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $from = sanitize_text_field(wp_unslash($_GET['from'] ?? ''));
        $to   = sanitize_text_field(wp_unslash($_GET['to'] ?? ''));

        $summary = $this->get_vat_summary($from, $to);
        $summary['monthly'] = $this->get_vat_by_month($summary['from'], $summary['to']);

        wp_send_json_success($summary);
    }
}
