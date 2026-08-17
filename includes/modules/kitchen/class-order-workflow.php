<?php
/**
 * Kitchen Order Workflow
 *
 * Item-level kitchen order lifecycle: creates kitchen orders from
 * WooCommerce orders (copying line items), tracks each item through
 * pending -> preparing -> ready, and auto-completes the order once every
 * item is ready. Prep tracking remains in the Kitchen Display module.
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Order_Workflow
{
    private $table_orders;
    private $table_items;

    public function __construct()
    {
        global $wpdb;

        $this->table_orders = $wpdb->prefix . 'erp_kitchen_orders';
        $this->table_items  = $wpdb->prefix . 'erp_kitchen_order_items';

        add_action('wp_ajax_orerp_create_workflow_order', [$this, 'ajax_create_from_order']);
        add_action('wp_ajax_orerp_get_workflow_order', [$this, 'ajax_get_order']);
        add_action('wp_ajax_orerp_update_workflow_item', [$this, 'ajax_update_item']);
    }

    /**
     * Create a kitchen order from a WooCommerce order, copying its line items.
     *
     * @param int    $wc_order_id WC order ID.
     * @param int    $branch_id   Branch to assign.
     * @param string $station     Kitchen station.
     * @param int    $priority    Priority level.
     * @return int|WP_Error Kitchen order ID.
     */
    public function create_from_order($wc_order_id, $branch_id, $station = '', $priority = 0)
    {
        global $wpdb;

        $wc_order_id = intval($wc_order_id);
        $branch_id   = intval($branch_id);
        $station     = sanitize_text_field($station);
        $priority    = intval($priority);

        if ($wc_order_id <= 0 || $branch_id <= 0) {
            return new WP_Error('missing_fields', __('Order ID and Branch are required.', 'obydullah-restaurant-erp'));
        }

        // Prevent duplicate kitchen orders for the same WC order + branch.
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_orders} WHERE order_id = %d AND branch_id = %d AND status != 'cancelled' LIMIT 1",
            $wc_order_id,
            $branch_id
        ));

        if ($existing) {
            return new WP_Error('duplicate', __('A kitchen order already exists for this order.', 'obydullah-restaurant-erp'));
        }

        $items = [];
        $notes = sprintf('Imported from order #%d', $wc_order_id);

        if (class_exists('WooCommerce')) {
            $order = wc_get_order($wc_order_id);
            if ($order) {
                $notes = $order->get_customer_note() ?: $notes;
                foreach ($order->get_items() as $item) {
                    $items[] = [
                        'product_id' => intval($item->get_product_id()),
                        'name'       => $item->get_name(),
                        'quantity'   => max(1, intval($item->get_quantity())),
                    ];
                }
            }
        }

        $insert = [
            'order_id'       => $wc_order_id,
            'branch_id'      => $branch_id,
            'station'        => $station,
            'priority'       => $priority,
            'status'         => 'pending',
            'estimated_time' => 0,
            'notes'          => $notes,
        ];

        $result = $wpdb->insert($this->table_orders, $insert);
        if ($result === false) {
            return new WP_Error('create_failed', __('Failed to create kitchen order.', 'obydullah-restaurant-erp'));
        }

        $kitchen_order_id = $wpdb->insert_id;

        foreach ($items as $item) {
            $wpdb->insert($this->table_items, [
                'kitchen_order_id' => $kitchen_order_id,
                'product_id'       => $item['product_id'],
                'name'             => sanitize_text_field($item['name']),
                'quantity'         => intval($item['quantity']),
                'status'           => 'pending',
            ]);
        }

        return $kitchen_order_id;
    }

    /**
     * Fetch a kitchen order with its line items.
     *
     * @param int $kitchen_order_id Kitchen order ID.
     * @return object|null
     */
    public function get_order($kitchen_order_id)
    {
        global $wpdb;

        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_orders} WHERE id = %d",
            intval($kitchen_order_id)
        ));

        if (!$order) {
            return null;
        }

        $order->items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_items} WHERE kitchen_order_id = %d ORDER BY id ASC",
            intval($kitchen_order_id)
        )) ?: [];

        foreach ($order->items as &$item) {
            $item->started_at = $item->started_at ?: '';
        }

        return $order;
    }

    /**
     * Update the status of a single kitchen order item.
     *
     * @param int    $item_id Item ID.
     * @param string $status  pending|preparing|ready.
     * @return bool|WP_Error
     */
    public function update_item_status($item_id, $status)
    {
        global $wpdb;

        $valid = ['pending', 'preparing', 'ready'];
        if (!in_array($status, $valid, true)) {
            return new WP_Error('invalid_status', __('Invalid status.', 'obydullah-restaurant-erp'));
        }

        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_items} WHERE id = %d",
            intval($item_id)
        ));

        if (!$item) {
            return new WP_Error('not_found', __('Item not found.', 'obydullah-restaurant-erp'));
        }

        $update = ['status' => $status];
        if ($status === 'preparing' && !$item->started_at) {
            $update['started_at'] = current_time('mysql');
        }

        $wpdb->update($this->table_items, $update, ['id' => intval($item_id)]);

        $this->maybe_complete_order($item->kitchen_order_id);

        return true;
    }

    /**
     * If all items of an order are ready, mark the order ready; if the order
     * has no items, leave its status untouched.
     *
     * @param int $kitchen_order_id Kitchen order ID.
     * @return void
     */
    private function maybe_complete_order($kitchen_order_id)
    {
        global $wpdb;

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT status FROM {$this->table_items} WHERE kitchen_order_id = %d",
            intval($kitchen_order_id)
        )) ?: [];

        if (empty($items)) {
            return;
        }

        $all_ready = true;
        foreach ($items as $item) {
            if ($item->status !== 'ready') {
                $all_ready = false;
                break;
            }
        }

        if ($all_ready) {
            $wpdb->update($this->table_orders, [
                'status'       => 'ready',
                'completed_at' => current_time('mysql'),
            ], ['id' => intval($kitchen_order_id)]);
        }
    }

    // --- AJAX ---

    public function ajax_create_from_order()
    {
        check_ajax_referer('orerp_kitchen', 'nonce');

        if (!Obydullah_ERP_Helpers::can('orerp_kitchen')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $result = $this->create_from_order(
            intval($_POST['order_id'] ?? 0),
            intval($_POST['branch_id'] ?? 0),
            sanitize_text_field(wp_unslash($_POST['station'] ?? '')),
            intval($_POST['priority'] ?? 0)
        );

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(['id' => $result, 'message' => __('Kitchen order created.', 'obydullah-restaurant-erp')]);
    }

    public function ajax_get_order()
    {
        check_ajax_referer('orerp_kitchen', 'nonce');

        if (!Obydullah_ERP_Helpers::can('orerp_kitchen')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $order = $this->get_order(intval($_GET['id'] ?? 0));

        if (!$order) {
            wp_send_json_error(__('Kitchen order not found.', 'obydullah-restaurant-erp'));
        }

        wp_send_json_success($order);
    }

    public function ajax_update_item()
    {
        check_ajax_referer('orerp_kitchen', 'nonce');

        if (!Obydullah_ERP_Helpers::can('orerp_kitchen')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $item_id = intval($_POST['item_id'] ?? 0);
        $status  = sanitize_text_field(wp_unslash($_POST['status'] ?? ''));

        $result = $this->update_item_status($item_id, $status);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(['message' => __('Item updated.', 'obydullah-restaurant-erp')]);
    }
}
