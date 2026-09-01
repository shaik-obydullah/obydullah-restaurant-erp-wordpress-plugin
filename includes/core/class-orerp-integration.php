<?php
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL table names come from $wpdb->prefix and every value is bound via $wpdb->prepare() placeholders; direct queries are used for the ERP-specific tables that have no core caching API.
/**
 * POS / WooCommerce Integration
 *
 * Hooks into WooCommerce order lifecycle and the Obydullah Restaurant POS
 * plugin (orpl_process_sale) to automatically create double-entry journal
 * entries and sync kitchen orders.
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Integration
{
    public function __construct()
    {
        add_action('woocommerce_order_status_completed', [$this, 'orerp_handle_order_completed'], 10, 1);
        add_action('woocommerce_payment_complete', [$this, 'orerp_handle_payment_complete'], 10, 1);

        // Obydullah Restaurant POS Lite integration point.
        add_action('orpl_process_sale', [$this, 'orerp_handle_pos_sale'], 10, 2);
    }

    /**
     * A sale was completed. Auto-creates the revenue journal entry.
     *
     * DR: Cash / Accounts Receivable  (order total)
     * CR: Sales Revenue               (total - tax)
     * CR: VAT Payable                 (tax)
     *
     * @param int $order_id WooCommerce order ID.
     * @return void
     */
    public function orerp_handle_order_completed($order_id)
    {
        $this->orerp_record_sale($order_id, 'wc_order');
    }

    /**
     * WooCommerce payment completed (covers more gateways/states).
     *
     * @param int $order_id WooCommerce order ID.
     * @return void
     */
    public function orerp_handle_payment_complete($order_id)
    {
        $this->orerp_record_sale($order_id, 'wc_order');
    }

    /**
     * POS plugin sale hook (obydullah-restaurant-pos-lite).
     *
     * @param mixed $order_id WC order ID or order data array.
     * @param mixed $data     Optional payload from the POS plugin.
     * @return void
     */
    public function orerp_handle_pos_sale($order_id, $data = [])
    {
        if (is_array($order_id) && isset($order_id['order_id'])) {
            $order_id = $order_id['order_id'];
        }

        $this->orerp_record_sale(intval($order_id), 'pos_sale');
    }

    /**
     * Create the revenue journal entry for a completed sale if one does not
     * already exist for the given reference.
     *
     * @param int    $order_id    WooCommerce order ID.
     * @param string $ref_type    Reference type ('wc_order' or 'pos_sale').
     * @return bool|WP_Error
     */
    public function orerp_record_sale($order_id, $ref_type = 'wc_order')
    {
        $order_id = intval($order_id);

        if ($order_id <= 0 || !class_exists('WooCommerce') || !class_exists('Obydullah_ERP_Journal_Entries')) {
            return false;
        }

        if ($this->orerp_journal_entry_exists($ref_type, $order_id)) {
            return false;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        $total = floatval($order->get_total());
        $tax   = floatval($order->get_total_tax());

        if ($total <= 0) {
            return false;
        }

        // Cash vs Accounts Receivable depending on the payment method.
        $credit_methods = apply_filters('orerp_credit_sale_methods', ['invoice', 'credit_card_manual', 'pre_order', 'po']);
        $payment_method = $order->get_payment_method();
        $asset_code = in_array($payment_method, $credit_methods, true) ? '1100' : '1000';

        $lines = [
            ['account_code' => $asset_code, 'debit' => $total, 'credit' => 0, 'description' => sprintf('Sale %s', $order->get_order_number())],
            ['account_code' => '4000', 'debit' => 0, 'credit' => ($total - $tax), 'description' => sprintf('Sale revenue %s', $order->get_order_number())],
        ];

        if ($tax > 0) {
            $lines[] = ['account_code' => '2300', 'debit' => 0, 'credit' => $tax, 'description' => 'Sales VAT'];
        }

        $journal = new Obydullah_ERP_Journal_Entries();

        $result = $journal->orerp_create_entry([
            'date'           => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d') : current_time('Y-m-d'),
            'description'    => sprintf('Sale completed - Order #%s', $order->get_order_number()),
            'reference_type' => $ref_type,
            'reference_id'   => $order_id,
            'lines'          => $lines,
        ]);

        return $result;
    }

    /**
     * Check whether a journal entry already exists for the reference.
     *
     * @param string $ref_type Reference type.
     * @param int    $ref_id   Reference ID.
     * @return bool
     */
    private function orerp_journal_entry_exists($ref_type, $ref_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'erp_journal_entries';
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE reference_type = %s AND reference_id = %d",
            $ref_type,
            intval($ref_id)
        ));

        return intval($count) > 0;
    }
}
