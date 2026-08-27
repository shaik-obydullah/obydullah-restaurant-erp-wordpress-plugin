<?php
/**
 * Suppliers Management
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Suppliers
{
    private $table;
    private $products_table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'erp_suppliers';
        $this->products_table = $wpdb->prefix . 'erp_supplier_products';

        add_action('wp_ajax_orerp_get_suppliers', [$this, 'ajax_get_suppliers']);
        add_action('wp_ajax_orerp_save_supplier', [$this, 'ajax_save_supplier']);
        add_action('wp_ajax_orerp_delete_supplier', [$this, 'ajax_delete_supplier']);
        add_action('wp_ajax_orerp_get_suppliers_list', [$this, 'ajax_get_suppliers_list']);
        add_action('wp_ajax_orerp_get_supplier_products', [$this, 'ajax_get_supplier_products']);
        add_action('wp_ajax_orerp_save_supplier_product', [$this, 'ajax_save_supplier_product']);
        add_action('wp_ajax_orerp_delete_supplier_product', [$this, 'ajax_delete_supplier_product']);
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
            <h1 class="wp-heading-inline"><?php esc_html_e('Suppliers', 'obydullah-restaurant-erp'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-suppliers&action=add')); ?>" class="page-title-action">
                <?php esc_html_e('Add New', 'obydullah-restaurant-erp'); ?>
            </a>
            <hr class="wp-header-end">

            <div class="orerp-card">
                <div id="suppliers-list">
                    <div class="orerp-loading">
                        <span class="spinner is-active"></span>
                        <p><?php esc_html_e('Loading suppliers...', 'obydullah-restaurant-erp'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_form($mode)
    {
        $supplier = null;
        if ($mode === 'edit') {
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            if ($id) {
                $supplier = $this->get_supplier($id);
            }
        }

        $title = $mode === 'edit' ? __('Edit Supplier', 'obydullah-restaurant-erp') : __('Add New Supplier', 'obydullah-restaurant-erp');
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html($title); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-suppliers')); ?>" class="page-title-action">
                <?php esc_html_e('Back to List', 'obydullah-restaurant-erp'); ?>
            </a>
            <hr class="wp-header-end">

            <div class="orerp-card orerp-form">
                <form id="supplier-form" method="post">
                    <input type="hidden" name="action" value="orerp_save_supplier">
                    <?php wp_nonce_field('orerp_save_supplier', 'supplier_nonce'); ?>
                    <input type="hidden" name="supplier_id" value="<?php echo esc_attr($supplier->id ?? ''); ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label><?php esc_html_e('Supplier Name', 'obydullah-restaurant-erp'); ?> <span class="required">*</span></label>
                            <input type="text" name="name" class="regular-text" required
                                value="<?php echo esc_attr($supplier->name ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label><?php esc_html_e('Supplier Code', 'obydullah-restaurant-erp'); ?> <span class="required">*</span></label>
                            <input type="text" name="code" class="regular-text" required
                                value="<?php echo esc_attr($supplier->code ?? Obydullah_ERP_Helpers::generate_supplier_code()); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><?php esc_html_e('Contact Person', 'obydullah-restaurant-erp'); ?></label>
                            <input type="text" name="contact_person" class="regular-text"
                                value="<?php echo esc_attr($supplier->contact_person ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label><?php esc_html_e('Phone', 'obydullah-restaurant-erp'); ?></label>
                            <input type="tel" name="phone" class="regular-text"
                                value="<?php echo esc_attr($supplier->phone ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><?php esc_html_e('Email', 'obydullah-restaurant-erp'); ?></label>
                            <input type="email" name="email" class="regular-text"
                                value="<?php echo esc_attr($supplier->email ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label><?php esc_html_e('Payment Terms', 'obydullah-restaurant-erp'); ?></label>
                            <select name="payment_terms" class="regular-text">
                                <option value=""><?php esc_html_e('Select', 'obydullah-restaurant-erp'); ?></option>
                                <option value="COD" <?php selected($supplier->payment_terms ?? '', 'COD'); ?>><?php esc_html_e('Cash on Delivery', 'obydullah-restaurant-erp'); ?></option>
                                <option value="Net15" <?php selected($supplier->payment_terms ?? '', 'Net15'); ?>><?php esc_html_e('Net 15 Days', 'obydullah-restaurant-erp'); ?></option>
                                <option value="Net30" <?php selected($supplier->payment_terms ?? '', 'Net30'); ?>><?php esc_html_e('Net 30 Days', 'obydullah-restaurant-erp'); ?></option>
                                <option value="Net60" <?php selected($supplier->payment_terms ?? '', 'Net60'); ?>><?php esc_html_e('Net 60 Days', 'obydullah-restaurant-erp'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><?php esc_html_e('Address', 'obydullah-restaurant-erp'); ?></label>
                        <textarea name="address" rows="3" class="large-text"><?php echo esc_textarea($supplier->address ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" value="1" <?php checked($supplier->is_active ?? 1, 1); ?>>
                            <?php esc_html_e('Active', 'obydullah-restaurant-erp'); ?>
                        </label>
                    </div>

                    <p class="submit">
                        <button type="submit" id="submit-supplier" class="button button-primary">
                            <span class="btn-text"><?php esc_html_e('Save Supplier', 'obydullah-restaurant-erp'); ?></span>
                            <span class="spinner" style="display:none;"></span>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    public function get_suppliers($args = [])
    {
        global $wpdb;

        $defaults = ['per_page' => 20, 'page' => 1, 'search' => '', 'active' => ''];
        $args = wp_parse_args($args, $defaults);

        $where = '1=1';
        $prepare_args = [];

        if (!empty($args['search'])) {
            $where .= ' AND (name LIKE %s OR code LIKE %s OR contact_person LIKE %s)';
            $prepare_args[] = '%' . $wpdb->esc_like($args['search']) . '%';
            $prepare_args[] = '%' . $wpdb->esc_like($args['search']) . '%';
            $prepare_args[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }

        if ($args['active'] !== '') {
            $where .= ' AND is_active = %d';
            $prepare_args[] = intval($args['active']);
        }

        $offset = ($args['page'] - 1) * $args['per_page'];

        if (!empty($prepare_args)) {
            $total = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table} WHERE {$where}", $prepare_args)));
        } else {
            $total = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table} WHERE 1 = %d", 1)));
        }

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE {$where} ORDER BY name ASC LIMIT %d OFFSET %d",
            array_merge($prepare_args, [$args['per_page'], $offset])
        ));

        return [
            'suppliers'    => $results ?: [],
            'total'        => $total,
            'total_pages'  => ceil($total / $args['per_page']),
            'current_page' => $args['page'],
        ];
    }

    public function get_supplier($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", intval($id)));
    }

    public function save_supplier($data)
    {
        global $wpdb;

        $id = intval($data['supplier_id'] ?? 0);
        $name = sanitize_text_field($data['name'] ?? '');
        $code = sanitize_text_field($data['code'] ?? '');
        $contact_person = sanitize_text_field($data['contact_person'] ?? '');
        $email = sanitize_email($data['email'] ?? '');
        $phone = sanitize_text_field($data['phone'] ?? '');
        $address = sanitize_textarea_field($data['address'] ?? '');
        $payment_terms = sanitize_text_field($data['payment_terms'] ?? '');
        $is_active = isset($data['is_active']) ? 1 : 0;

        if (empty($name) || empty($code)) {
            return new WP_Error('missing_fields', __('Supplier name and code are required.', 'obydullah-restaurant-erp'));
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE code = %s AND id != %d",
            $code, $id
        ));
        if ($existing) {
            return new WP_Error('duplicate_code', __('Supplier code already exists.', 'obydullah-restaurant-erp'));
        }

        $save_data = compact('name', 'code', 'contact_person', 'email', 'phone', 'address', 'payment_terms', 'is_active');

        if ($id > 0) {
            $result = $wpdb->update($this->table, $save_data, ['id' => $id]);
        } else {
            $result = $wpdb->insert($this->table, $save_data);
            $id = $wpdb->insert_id;
        }

        return $result !== false ? $id : new WP_Error('save_failed', __('Failed to save supplier.', 'obydullah-restaurant-erp'));
    }

    public function delete_supplier($id)
    {
        global $wpdb;
        $wpdb->delete($this->table, ['id' => intval($id)]);
        $wpdb->delete($this->products_table, ['supplier_id' => intval($id)]);
        return true;
    }

    public function get_supplier_products($supplier_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT sp.*, p.post_title as product_name
            FROM {$this->products_table} sp
            LEFT JOIN {$wpdb->posts} p ON sp.product_id = p.ID
            WHERE sp.supplier_id = %d
            ORDER BY p.post_title",
            intval($supplier_id)
        )) ?: [];
    }

    public function save_supplier_product($data)
    {
        global $wpdb;

        $id = intval($data['id'] ?? 0);
        $supplier_id = intval($data['supplier_id'] ?? 0);
        $product_id = intval($data['product_id'] ?? 0);
        $supplier_sku = sanitize_text_field($data['supplier_sku'] ?? '');
        $unit_cost = floatval($data['unit_cost'] ?? 0);
        $lead_time_days = intval($data['lead_time_days'] ?? 0);
        $min_order_qty = intval($data['min_order_qty'] ?? 1);

        if (!$supplier_id || !$product_id) {
            return new WP_Error('missing_fields', __('Supplier and product are required.', 'obydullah-restaurant-erp'));
        }

        $save_data = compact('supplier_id', 'product_id', 'supplier_sku', 'unit_cost', 'lead_time_days', 'min_order_qty');

        if ($id > 0) {
            $result = $wpdb->update($this->products_table, $save_data, ['id' => $id]);
        } else {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->products_table} WHERE supplier_id = %d AND product_id = %d",
                $supplier_id, $product_id
            ));
            if ($existing) {
                $result = $wpdb->update($this->products_table, $save_data, ['id' => $existing]);
                $id = $existing;
            } else {
                $result = $wpdb->insert($this->products_table, $save_data);
                $id = $wpdb->insert_id;
            }
        }

        return $result !== false ? $id : new WP_Error('save_failed', __('Failed to save product.', 'obydullah-restaurant-erp'));
    }

    public function delete_supplier_product($id)
    {
        global $wpdb;
        return $wpdb->delete($this->products_table, ['id' => intval($id)]) !== false;
    }

    public function ajax_get_suppliers()
    {
        check_ajax_referer('orerp_suppliers', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $result = $this->get_suppliers([
            'per_page' => intval($_GET['per_page'] ?? 20),
            'page'     => intval($_GET['page'] ?? 1),
            'search'   => sanitize_text_field(wp_unslash($_GET['search'] ?? '')),
        ]);

        wp_send_json_success($result);
    }

    public function ajax_save_supplier()
    {
        check_ajax_referer('orerp_save_supplier', 'supplier_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $result = $this->save_supplier($_POST);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(['id' => $result, 'message' => __('Supplier saved.', 'obydullah-restaurant-erp')]);
    }

    public function ajax_delete_supplier()
    {
        check_ajax_referer('orerp_suppliers', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $id = intval($_POST['id'] ?? 0);
        $this->delete_supplier($id);
        wp_send_json_success(__('Supplier deleted.', 'obydullah-restaurant-erp'));
    }

    public function ajax_get_suppliers_list()
    {
        check_ajax_referer('orerp_suppliers', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        global $wpdb;
        $suppliers = $wpdb->get_results(
            "SELECT id, name, code FROM {$this->table} WHERE is_active = 1 ORDER BY name"
        );
        wp_send_json_success($suppliers);
    }

    public function ajax_get_supplier_products()
    {
        check_ajax_referer('orerp_suppliers', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $supplier_id = intval($_GET['supplier_id'] ?? 0);
        wp_send_json_success($this->get_supplier_products($supplier_id));
    }

    public function ajax_save_supplier_product()
    {
        check_ajax_referer('orerp_suppliers', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $result = $this->save_supplier_product($_POST);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(['id' => $result, 'message' => __('Product saved.', 'obydullah-restaurant-erp')]);
    }

    public function ajax_delete_supplier_product()
    {
        check_ajax_referer('orerp_suppliers', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $id = intval($_POST['id'] ?? 0);
        $this->delete_supplier_product($id);
        wp_send_json_success(__('Product deleted.', 'obydullah-restaurant-erp'));
    }
}
