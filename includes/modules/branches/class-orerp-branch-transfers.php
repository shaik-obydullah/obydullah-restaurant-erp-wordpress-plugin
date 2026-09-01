<?php
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL table names come from $wpdb->prefix and every value is bound via $wpdb->prepare() placeholders; direct queries are used for the ERP-specific tables that have no core caching API.
/**
 * Branch Transfers - Inter-branch stock transfers
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Branch_Transfers
{
    private $table;
    private $items_table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'erp_transfers';
        $this->items_table = $wpdb->prefix . 'erp_transfer_items';

        add_action('wp_ajax_orerp_get_transfers', [$this, 'orerp_ajax_get_transfers']);
        add_action('wp_ajax_orerp_save_transfer', [$this, 'orerp_ajax_save_transfer']);
        add_action('wp_ajax_orerp_receive_transfer', [$this, 'orerp_ajax_receive_transfer']);
        add_action('wp_ajax_orerp_cancel_transfer', [$this, 'orerp_ajax_cancel_transfer']);
    }

    public function orerp_get_transfers($args = [])
    {
        global $wpdb;

        $defaults = [
            'per_page' => 20,
            'page'     => 1,
            'status'   => 'orerp_',
            'branch_id' => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        $cache_key = 'transfers_list_' . $args['page'] . '_' . $args['per_page'] . '_' . $args['status'] . '_' . $args['branch_id'];
        $cached = Obydullah_ERP_Cache::get($cache_key, $this->table);
        if (false !== $cached) {
            return $cached;
        }

        $where = '1=1';
        $prepare_args = [];

        if (!empty($args['status'])) {
            $where .= ' AND t.status = %s';
            $prepare_args[] = $args['status'];
        }

        if ($args['branch_id'] > 0) {
            $where .= ' AND (t.from_branch_id = %d OR t.to_branch_id = %d)';
            $prepare_args[] = $args['branch_id'];
            $prepare_args[] = $args['branch_id'];
        }

        $offset = ($args['page'] - 1) * $args['per_page'];

        if (!empty($prepare_args)) {
            $total = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table} t WHERE {$where}", $prepare_args)));
        } else {
            $total = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table} t WHERE 1 = %d", 1)));
        }

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*,
            fb.name as from_branch_name,
            tb.name as to_branch_name
            FROM {$this->table} t
            LEFT JOIN {$wpdb->prefix}erp_branches fb ON t.from_branch_id = fb.id
            LEFT JOIN {$wpdb->prefix}erp_branches tb ON t.to_branch_id = tb.id
            WHERE {$where}
            ORDER BY t.created_at DESC
            LIMIT %d OFFSET %d",
            array_merge($prepare_args, [$args['per_page'], $offset])
        ));

        $return = [
            'transfers'    => $results ?: [],
            'total'        => $total,
            'total_pages'  => ceil($total / $args['per_page']),
            'current_page' => $args['page'],
        ];

        Obydullah_ERP_Cache::set($cache_key, $this->table, $return);
        return $return;
    }

    public function orerp_get_transfer($id)
    {
        global $wpdb;

        $id = intval($id);
        $cache_key = 'transfer_' . $id;
        $cached = Obydullah_ERP_Cache::get($cache_key, $this->table);
        if (false !== $cached) {
            return $cached;
        }

        $transfer = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*,
            fb.name as from_branch_name,
            tb.name as to_branch_name
            FROM {$this->table} t
            LEFT JOIN {$wpdb->prefix}erp_branches fb ON t.from_branch_id = fb.id
            LEFT JOIN {$wpdb->prefix}erp_branches tb ON t.to_branch_id = tb.id
            WHERE t.id = %d",
            $id
        ));

        if ($transfer) {
            $transfer->items = $wpdb->get_results($wpdb->prepare(
                "SELECT ti.*, p.post_title as product_name
                FROM {$this->items_table} ti
                LEFT JOIN {$wpdb->posts} p ON ti.product_id = p.ID
                WHERE ti.transfer_id = %d",
                $id
            ));
        }

        Obydullah_ERP_Cache::set($cache_key, $this->table, $transfer);
        return $transfer;
    }

    public function orerp_create_transfer($data)
    {
        global $wpdb;

        $from_branch = intval($data['from_branch_id'] ?? 0);
        $to_branch = intval($data['to_branch_id'] ?? 0);
        $notes = sanitize_textarea_field($data['notes'] ?? 'orerp_');
        $items = json_decode(stripslashes($data['items'] ?? '[]'), true);

        if (!$from_branch || !$to_branch) {
            return new WP_Error('missing_branches', __('Please select both branches.', 'obydullah-restaurant-erp'));
        }

        if ($from_branch === $to_branch) {
            return new WP_Error('same_branch', __('Source and destination branches cannot be the same.', 'obydullah-restaurant-erp'));
        }

        if (empty($items)) {
            return new WP_Error('no_items', __('Please add at least one item.', 'obydullah-restaurant-erp'));
        }

        $wpdb->insert($this->table, [
            'from_branch_id' => $from_branch,
            'to_branch_id'   => $to_branch,
            'status'         => 'pending',
            'notes'          => $notes,
            'created_by'     => get_current_user_id(),
        ]);

        $transfer_id = $wpdb->insert_id;

        if (!$transfer_id) {
            return new WP_Error('create_failed', __('Failed to create transfer.', 'obydullah-restaurant-erp'));
        }

        foreach ($items as $item) {
            $wpdb->insert($this->items_table, [
                'transfer_id' => $transfer_id,
                'product_id'  => intval($item['product_id']),
                'quantity'    => intval($item['quantity']),
            ]);
        }

        Obydullah_ERP_Cache::invalidate($this->table);
        Obydullah_ERP_Cache::invalidate($this->items_table);

        return $transfer_id;
    }

    public function orerp_receive_transfer($id, $received_items = [])
    {
        global $wpdb;

        $id = intval($id);
        $transfer = $this->orerp_get_transfer($id);

        if (!$transfer) {
            return new WP_Error('not_found', __('Transfer not found.', 'obydullah-restaurant-erp'));
        }

        if ($transfer->status !== 'pending' && $transfer->status !== 'in_transit') {
            return new WP_Error('invalid_status', __('Transfer cannot be received in current status.', 'obydullah-restaurant-erp'));
        }

        $branches = new Obydullah_ERP_Branches();

        foreach ($transfer->items as $item) {
            $received_qty = intval($received_items[$item->id][$item->product_id] ?? $item->quantity);

            $wpdb->update($this->items_table, [
                'received_quantity' => $received_qty,
            ], ['id' => $item->id]);

            if ($received_qty > 0) {
                $branches->update_branch_stock($transfer->from_branch_id, $item->product_id, -$received_qty);
                $branches->update_branch_stock($transfer->to_branch_id, $item->product_id, $received_qty);
            }
        }

        $wpdb->update($this->table, [
            'status'      => 'received',
            'received_at' => current_time('mysql'),
        ], ['id' => $id]);

        Obydullah_ERP_Cache::invalidate($this->table);
        Obydullah_ERP_Cache::invalidate($this->items_table);

        return true;
    }

    public function orerp_cancel_transfer($id)
    {
        global $wpdb;
        $id = intval($id);

        $transfer = $this->orerp_get_transfer($id);

        if (!$transfer) {
            return new WP_Error('not_found', __('Transfer not found.', 'obydullah-restaurant-erp'));
        }

        if ($transfer->status === 'received' || $transfer->status === 'cancelled') {
            return new WP_Error('invalid_status', __('Cannot cancel transfer in current status.', 'obydullah-restaurant-erp'));
        }

        $wpdb->update($this->table, [
            'status' => 'cancelled',
        ], ['id' => $id]);

        Obydullah_ERP_Cache::invalidate($this->table);

        return true;
    }

    public function orerp_ajax_get_transfers()
    {
        check_ajax_referer('orerp_branches', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $result = $this->orerp_get_transfers([
            'per_page'  => intval($_GET['per_page'] ?? 20),
            'page'      => intval($_GET['page'] ?? 1),
            'status'    => sanitize_text_field(wp_unslash($_GET['status'] ?? 'orerp_')),
            'branch_id' => intval($_GET['branch_id'] ?? 0),
        ]);

        wp_send_json_success($result);
    }

    public function orerp_ajax_save_transfer()
    {
        check_ajax_referer('orerp_branches', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $result = $this->orerp_create_transfer($_POST);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'id'      => $result,
            'message' => __('Transfer created successfully.', 'obydullah-restaurant-erp'),
        ]);
    }

    public function orerp_ajax_receive_transfer()
    {
        check_ajax_referer('orerp_branches', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $id = intval($_POST['transfer_id'] ?? 0);
        $received_items = array_map('intval', $_POST['received_items'] ?? []);

        $result = $this->orerp_receive_transfer($id, $received_items);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Transfer received successfully.', 'obydullah-restaurant-erp'));
    }

    public function orerp_ajax_cancel_transfer()
    {
        check_ajax_referer('orerp_branches', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $id = intval($_POST['transfer_id'] ?? 0);

        $result = $this->orerp_cancel_transfer($id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Transfer cancelled.', 'obydullah-restaurant-erp'));
    }
}
