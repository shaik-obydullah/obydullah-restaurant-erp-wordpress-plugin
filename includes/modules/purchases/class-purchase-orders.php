<?php
/**
 * Purchase Orders Management
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Purchase_Orders
{
    private $table;
    private $items_table;
    private $payments_table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'erp_purchase_orders';
        $this->items_table = $wpdb->prefix . 'erp_purchase_items';
        $this->payments_table = $wpdb->prefix . 'erp_purchase_payments';

        add_action('wp_ajax_orerp_get_purchases', [$this, 'ajax_get_purchases']);
        add_action('wp_ajax_orerp_save_purchase', [$this, 'ajax_save_purchase']);
        add_action('wp_ajax_orerp_delete_purchase', [$this, 'ajax_delete_purchase']);
        add_action('wp_ajax_orerp_get_purchase_for_edit', [$this, 'ajax_get_purchase_for_edit']);
        add_action('wp_ajax_orerp_receive_purchase', [$this, 'ajax_receive_purchase']);
        add_action('wp_ajax_orerp_add_purchase_payment', [$this, 'ajax_add_payment']);
        add_action('wp_ajax_orerp_get_purchase_payments', [$this, 'ajax_get_payments']);
    }

    public function render_page()
    {
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list';

        if ($action === 'add' || $action === 'edit') {
            $this->render_form($action);
        } else {
            $this->render_list();
        }
    }

    private function render_list()
    {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Purchase Orders', 'obydullah-restaurant-erp'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-purchases&action=add')); ?>" class="page-title-action">
                <?php esc_html_e('Add New', 'obydullah-restaurant-erp'); ?>
            </a>
            <hr class="wp-header-end">

            <div class="orerp-card">
                <div id="purchases-list">
                    <div class="orerp-loading">
                        <span class="spinner is-active"></span>
                        <p><?php esc_html_e('Loading purchase orders...', 'obydullah-restaurant-erp'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_form($mode)
    {
        $po = null;
        $items = [];

        if ($mode === 'edit') {
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            if ($id) {
                $po = $this->get_purchase($id);
                $items = $this->get_purchase_items($id);
            }
        }

        $title = $mode === 'edit' ? __('Edit Purchase Order', 'obydullah-restaurant-erp') : __('New Purchase Order', 'obydullah-restaurant-erp');
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html($title); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-purchases')); ?>" class="page-title-action">
                <?php esc_html_e('Back to List', 'obydullah-restaurant-erp'); ?>
            </a>
            <hr class="wp-header-end">

            <div class="orerp-card orerp-form">
                <form id="purchase-form" method="post">
                    <input type="hidden" name="action" value="orerp_save_purchase">
                    <?php wp_nonce_field('orerp_save_purchase', 'purchase_nonce'); ?>
                    <input type="hidden" name="purchase_id" value="<?php echo esc_attr($po->id ?? ''); ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label><?php esc_html_e('PO Number', 'obydullah-restaurant-erp'); ?> <span class="required">*</span></label>
                            <input type="text" name="po_number" class="regular-text" required readonly
                                value="<?php echo esc_attr($po->po_number ?? Obydullah_ERP_Helpers::generate_po_number()); ?>">
                        </div>
                        <div class="form-group">
                            <label><?php esc_html_e('Branch', 'obydullah-restaurant-erp'); ?> <span class="required">*</span></label>
                            <select name="branch_id" class="regular-text" required>
                                <option value=""><?php esc_html_e('Select Branch', 'obydullah-restaurant-erp'); ?></option>
                                <?php
                                global $wpdb;
                                $branches = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}erp_branches WHERE is_active = 1 ORDER BY name");
                                foreach ($branches as $b) {
                                    printf('<option value="%s" %s>%s</option>', esc_attr($b->id), selected($po->branch_id ?? '', $b->id, false), esc_html($b->name));
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><?php esc_html_e('Supplier', 'obydullah-restaurant-erp'); ?> <span class="required">*</span></label>
                            <select name="supplier_id" class="regular-text" required id="po-supplier">
                                <option value=""><?php esc_html_e('Select Supplier', 'obydullah-restaurant-erp'); ?></option>
                                <?php
                                $suppliers = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}erp_suppliers WHERE is_active = 1 ORDER BY name");
                                foreach ($suppliers as $s) {
                                    printf('<option value="%s" %s>%s</option>', esc_attr($s->id), selected($po->supplier_id ?? '', $s->id, false), esc_html($s->name));
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?php esc_html_e('Expected Date', 'obydullah-restaurant-erp'); ?></label>
                            <input type="date" name="expected_date" class="regular-text"
                                value="<?php echo esc_attr($po->expected_date ?? ''); ?>">
                        </div>
                    </div>

                    <h3><?php esc_html_e('Items', 'obydullah-restaurant-erp'); ?></h3>
                    <table class="orerp-table widefat" id="po-items-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Product', 'obydullah-restaurant-erp'); ?></th>
                                <th width="100"><?php esc_html_e('Quantity', 'obydullah-restaurant-erp'); ?></th>
                                <th width="120"><?php esc_html_e('Unit Cost', 'obydullah-restaurant-erp'); ?></th>
                                <th width="120"><?php esc_html_e('Total', 'obydullah-restaurant-erp'); ?></th>
                                <th width="60"></th>
                            </tr>
                        </thead>
                        <tbody id="po-items-body">
                            <?php if (!empty($items)): ?>
                            <?php foreach ($items as $item): ?>
                            <tr class="po-item-row">
                                <td>
                                    <select name="items[<?php echo esc_attr($item->id); ?>][product_id]" class="po-product-select regular-text" required>
                                        <option value="<?php echo esc_attr($item->product_id); ?>"><?php echo esc_html($item->product_name); ?></option>
                                    </select>
                                    <input type="hidden" name="items[<?php echo esc_attr($item->id); ?>][id]" value="<?php echo esc_attr($item->id); ?>">
                                </td>
                                <td><input type="number" name="items[<?php echo esc_attr($item->id); ?>][quantity]" class="po-qty" min="1" value="<?php echo esc_attr($item->quantity); ?>" required></td>
                                <td><input type="number" name="items[<?php echo esc_attr($item->id); ?>][unit_cost]" class="po-cost" step="0.01" min="0" value="<?php echo esc_attr($item->unit_cost); ?>" required></td>
                                <td class="po-item-total"><?php echo esc_html(Obydullah_ERP_Helpers::format_currency($item->total)); ?></td>
                                <td><button type="button" class="button remove-item">X</button></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4">
                                    <button type="button" id="add-po-item" class="button"><?php esc_html_e('+ Add Item', 'obydullah-restaurant-erp'); ?></button>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-right"><strong><?php esc_html_e('Subtotal:', 'obydullah-restaurant-erp'); ?></strong></td>
                                <td><strong id="po-subtotal"><?php echo esc_html(Obydullah_ERP_Helpers::format_currency($po->subtotal ?? 0)); ?></strong></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-right"><strong><?php esc_html_e('Tax:', 'obydullah-restaurant-erp'); ?></strong></td>
                                <td><input type="number" name="tax_amount" id="po-tax" step="0.01" min="0" value="<?php echo esc_attr($po->tax_amount ?? 0); ?>"></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-right"><strong><?php esc_html_e('Total:', 'obydullah-restaurant-erp'); ?></strong></td>
                                <td><strong id="po-total"><?php echo esc_html(Obydullah_ERP_Helpers::format_currency($po->total ?? 0)); ?></strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="form-group" style="margin-top: 15px;">
                        <label><?php esc_html_e('Notes', 'obydullah-restaurant-erp'); ?></label>
                        <textarea name="notes" rows="3" class="large-text"><?php echo esc_textarea($po->notes ?? ''); ?></textarea>
                    </div>

                    <p class="submit">
                        <button type="submit" id="submit-purchase" class="button button-primary">
                            <span class="btn-text"><?php esc_html_e('Save Purchase Order', 'obydullah-restaurant-erp'); ?></span>
                            <span class="spinner" style="display:none;"></span>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    public function get_purchases($args = [])
    {
        global $wpdb;

        $defaults = ['per_page' => 20, 'page' => 1, 'search' => '', 'status' => ''];
        $args = wp_parse_args($args, $defaults);

        $where = '1=1';
        $prepare_args = [];

        if (!empty($args['search'])) {
            $where .= ' AND (po.po_number LIKE %s)';
            $prepare_args[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }

        if (!empty($args['status'])) {
            $where .= ' AND po.status = %s';
            $prepare_args[] = $args['status'];
        }

        $offset = ($args['page'] - 1) * $args['per_page'];

        if (!empty($prepare_args)) {
            $total = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table} po WHERE {$where}", $prepare_args)));
        } else {
            $total = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table} po WHERE 1 = %d", 1)));
        }

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT po.*, s.name as supplier_name, b.name as branch_name
            FROM {$this->table} po
            LEFT JOIN {$wpdb->prefix}erp_suppliers s ON po.supplier_id = s.id
            LEFT JOIN {$wpdb->prefix}erp_branches b ON po.branch_id = b.id
            WHERE {$where}
            ORDER BY po.created_at DESC
            LIMIT %d OFFSET %d",
            array_merge($prepare_args, [$args['per_page'], $offset])
        ));

        $helpers = new Obydullah_ERP_Helpers();
        foreach ($results as &$row) {
            $row->formatted_total = Obydullah_ERP_Helpers::format_currency($row->total);
            $row->formatted_date = $helpers->format_date($row->created_at);
        }

        return [
            'purchases'    => $results ?: [],
            'total'        => $total,
            'total_pages'  => ceil($total / $args['per_page']),
            'current_page' => $args['page'],
        ];
    }

    public function get_purchase($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", intval($id)));
    }

    public function get_purchase_items($purchase_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT pi.*, p.post_title as product_name
            FROM {$this->items_table} pi
            LEFT JOIN {$wpdb->posts} p ON pi.product_id = p.ID
            WHERE pi.purchase_id = %d
            ORDER BY pi.id",
            intval($purchase_id)
        )) ?: [];
    }

    public function save_purchase($data)
    {
        global $wpdb;

        $id = intval($data['purchase_id'] ?? 0);
        $po_number = sanitize_text_field($data['po_number'] ?? '');
        $supplier_id = intval($data['supplier_id'] ?? 0);
        $branch_id = intval($data['branch_id'] ?? 0);
        $expected_date = sanitize_text_field($data['expected_date'] ?? '');
        $tax_amount = floatval($data['tax_amount'] ?? 0);
        $notes = sanitize_textarea_field($data['notes'] ?? '');
        $items = $data['items'] ?? [];

        if (!$supplier_id || !$branch_id) {
            return new WP_Error('missing_fields', __('Supplier and branch are required.', 'obydullah-restaurant-erp'));
        }

        if (empty($po_number)) {
            $po_number = Obydullah_ERP_Helpers::generate_po_number();
        }

        $subtotal = 0;
        foreach ($items as $item) {
            $qty = intval($item['quantity'] ?? 0);
            $cost = floatval($item['unit_cost'] ?? 0);
            $subtotal += $qty * $cost;
        }

        $total = $subtotal + $tax_amount;

        $save_data = [
            'po_number'    => $po_number,
            'supplier_id'  => $supplier_id,
            'branch_id'    => $branch_id,
            'subtotal'     => $subtotal,
            'tax_amount'   => $tax_amount,
            'total'        => $total,
            'notes'        => $notes,
            'expected_date' => $expected_date ?: null,
            'created_by'   => get_current_user_id(),
        ];

        if ($id > 0) {
            $result = $wpdb->update($this->table, $save_data, ['id' => $id]);
            $wpdb->delete($this->items_table, ['purchase_id' => $id]);
        } else {
            $save_data['status'] = 'draft';
            $result = $wpdb->insert($this->table, $save_data);
            $id = $wpdb->insert_id;
        }

        if (!$id) {
            return new WP_Error('save_failed', __('Failed to save purchase order.', 'obydullah-restaurant-erp'));
        }

        foreach ($items as $item) {
            $product_id = intval($item['product_id'] ?? 0);
            $quantity = intval($item['quantity'] ?? 0);
            $unit_cost = floatval($item['unit_cost'] ?? 0);

            if ($product_id && $quantity > 0) {
                $wpdb->insert($this->items_table, [
                    'purchase_id' => $id,
                    'product_id'  => $product_id,
                    'quantity'    => $quantity,
                    'unit_cost'   => $unit_cost,
                    'total'       => $quantity * $unit_cost,
                ]);
            }
        }

        return $id;
    }

    public function receive_purchase($id)
    {
        global $wpdb;

        $id = intval($id);
        $po = $this->get_purchase($id);

        if (!$po) {
            return new WP_Error('not_found', __('Purchase order not found.', 'obydullah-restaurant-erp'));
        }

        if ($po->status === 'received' || $po->status === 'cancelled') {
            return new WP_Error('invalid_status', __('Cannot receive in current status.', 'obydullah-restaurant-erp'));
        }

        $items = $this->get_purchase_items($id);
        $branches = new Obydullah_ERP_Branches();

        foreach ($items as $item) {
            $remaining = $item->quantity - $item->received_qty;

            if ($remaining > 0) {
                $wpdb->update($this->items_table, [
                    'received_qty' => $item->quantity,
                ], ['id' => $item->id]);

                $branches->update_branch_stock($po->branch_id, $item->product_id, $remaining);

                $this->create_journal_entry_for_purchase($po, $item, $remaining);
            }
        }

        $wpdb->update($this->table, [
            'status'        => 'received',
            'received_date' => current_time('Y-m-d'),
        ], ['id' => $id]);

        return true;
    }

    private function create_journal_entry_for_purchase($po, $item, $qty)
    {
        if (class_exists('Obydullah_ERP_Journal_Entries')) {
            $journal = new Obydullah_ERP_Journal_Entries();
            $amount = $qty * $item->unit_cost;

            $journal->create_entry([
                'date'          => current_time('Y-m-d'),
                'description'   => sprintf('PO %s - %s', $po->po_number, $item->product_name),
                'reference_type' => 'purchase',
                'reference_id'  => $po->id,
                'lines'         => [
                    ['account_code' => '1200', 'debit' => $amount, 'credit' => 0],
                    ['account_code' => '2000', 'debit' => 0, 'credit' => $amount],
                ],
            ]);
        }
    }

    public function add_payment($data)
    {
        global $wpdb;

        $purchase_id = intval($data['purchase_id'] ?? 0);
        $amount = floatval($data['amount'] ?? 0);
        $payment_method = sanitize_text_field($data['payment_method'] ?? 'cash');
        $reference = sanitize_text_field($data['reference'] ?? '');
        $notes = sanitize_textarea_field($data['notes'] ?? '');
        $payment_date = sanitize_text_field($data['payment_date'] ?? current_time('Y-m-d'));

        if (!$purchase_id || $amount <= 0) {
            return new WP_Error('invalid_data', __('Purchase and amount are required.', 'obydullah-restaurant-erp'));
        }

        $wpdb->insert($this->payments_table, [
            'purchase_id'    => $purchase_id,
            'amount'         => $amount,
            'payment_method' => $payment_method,
            'reference'      => $reference,
            'notes'          => $notes,
            'payment_date'   => $payment_date,
        ]);

        $po = $this->get_purchase($purchase_id);
        if ($po) {
            $journal = new Obydullah_ERP_Journal_Entries();
            $journal->create_entry([
                'date'          => $payment_date,
                'description'   => sprintf('Payment for PO %s', $po->po_number),
                'reference_type' => 'purchase_payment',
                'reference_id'   => $wpdb->insert_id,
                'lines'          => [
                    ['account_code' => '2000', 'debit' => $amount, 'credit' => 0],
                    ['account_code' => '1000', 'debit' => 0, 'credit' => $amount],
                ],
            ]);
        }

        return $wpdb->insert_id;
    }

    public function get_payments($purchase_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->payments_table} WHERE purchase_id = %d ORDER BY payment_date DESC",
            intval($purchase_id)
        )) ?: [];
    }

    public function delete_purchase($id)
    {
        global $wpdb;
        $wpdb->delete($this->items_table, ['purchase_id' => intval($id)]);
        $wpdb->delete($this->payments_table, ['purchase_id' => intval($id)]);
        $wpdb->delete($this->table, ['id' => intval($id)]);
        return true;
    }

    // --- AJAX ---

    public function ajax_get_purchases()
    {
        check_ajax_referer('orerp_purchases', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $result = $this->get_purchases([
            'per_page' => intval($_GET['per_page'] ?? 20),
            'page'     => intval($_GET['page'] ?? 1),
            'search'   => sanitize_text_field(wp_unslash($_GET['search'] ?? '')),
            'status'   => sanitize_text_field(wp_unslash($_GET['status'] ?? '')),
        ]);

        wp_send_json_success($result);
    }

    public function ajax_save_purchase()
    {
        check_ajax_referer('orerp_save_purchase', 'purchase_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $result = $this->save_purchase($_POST);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(['id' => $result, 'message' => __('Purchase order saved.', 'obydullah-restaurant-erp')]);
    }

    public function ajax_delete_purchase()
    {
        check_ajax_referer('orerp_purchases', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $id = intval($_POST['id'] ?? 0);
        $this->delete_purchase($id);
        wp_send_json_success(__('Purchase order deleted.', 'obydullah-restaurant-erp'));
    }

    public function ajax_get_purchase_for_edit()
    {
        check_ajax_referer('orerp_purchases', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $id = intval($_GET['id'] ?? 0);
        $po = $this->get_purchase($id);
        if (!$po) {
            wp_send_json_error(__('Purchase order not found.', 'obydullah-restaurant-erp'));
        }

        $po->items = $this->get_purchase_items($id);
        wp_send_json_success($po);
    }

    public function ajax_receive_purchase()
    {
        check_ajax_referer('orerp_purchases', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $id = intval($_POST['purchase_id'] ?? 0);
        $result = $this->receive_purchase($id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Purchase order received successfully.', 'obydullah-restaurant-erp'));
    }

    public function ajax_add_payment()
    {
        check_ajax_referer('orerp_purchases', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $result = $this->add_payment($_POST);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(['id' => $result, 'message' => __('Payment recorded.', 'obydullah-restaurant-erp')]);
    }

    public function ajax_get_payments()
    {
        check_ajax_referer('orerp_purchases', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $purchase_id = intval($_GET['purchase_id'] ?? 0);
        wp_send_json_success($this->get_payments($purchase_id));
    }
}
