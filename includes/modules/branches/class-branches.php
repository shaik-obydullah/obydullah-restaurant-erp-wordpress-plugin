<?php
/**
 * Branches Management
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Branches
{
    private $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'erp_branches';

        add_action('wp_ajax_orerp_get_branches', [$this, 'ajax_get_branches']);
        add_action('wp_ajax_orerp_save_branch', [$this, 'ajax_save_branch']);
        add_action('wp_ajax_orerp_delete_branch', [$this, 'ajax_delete_branch']);
        add_action('wp_ajax_orerp_set_current_branch', [$this, 'ajax_set_current_branch']);
        add_action('wp_ajax_orerp_get_branch_for_edit', [$this, 'ajax_get_branch_for_edit']);
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
            <h1 class="wp-heading-inline"><?php esc_html_e('Branches', 'obydullah-restaurant-erp'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-branches&action=add')); ?>" class="page-title-action">
                <?php esc_html_e('Add New', 'obydullah-restaurant-erp'); ?>
            </a>
            <hr class="wp-header-end">

            <div class="orerp-card">
                <div id="branches-list">
                    <div class="orerp-loading">
                        <span class="spinner is-active"></span>
                        <p><?php esc_html_e('Loading branches...', 'obydullah-restaurant-erp'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_form($mode)
    {
        $branch = null;

        if ($mode === 'edit') {
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            if ($id) {
                $branch = $this->get_branch($id);
            }
        }

        $title = $mode === 'edit' ? __('Edit Branch', 'obydullah-restaurant-erp') : __('Add New Branch', 'obydullah-restaurant-erp');
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html($title); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-branches')); ?>" class="page-title-action">
                <?php esc_html_e('Back to List', 'obydullah-restaurant-erp'); ?>
            </a>
            <hr class="wp-header-end">

            <div class="orerp-card orerp-form">
                <form id="branch-form" method="post">
                    <input type="hidden" name="action" value="orerp_save_branch">
                    <?php wp_nonce_field('orerp_save_branch', 'branch_nonce'); ?>
                    <input type="hidden" name="branch_id" id="branch-id" value="<?php echo esc_attr($branch->id ?? ''); ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="branch-name"><?php esc_html_e('Branch Name', 'obydullah-restaurant-erp'); ?> <span class="required">*</span></label>
                            <input type="text" id="branch-name" name="name" class="regular-text" required
                                value="<?php echo esc_attr($branch->name ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="branch-code"><?php esc_html_e('Branch Code', 'obydullah-restaurant-erp'); ?> <span class="required">*</span></label>
                            <input type="text" id="branch-code" name="code" class="regular-text" required
                                value="<?php echo esc_attr($branch->code ?? ''); ?>"
                                placeholder="<?php esc_attr_e('e.g. BR-001', 'obydullah-restaurant-erp'); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="branch-address"><?php esc_html_e('Address', 'obydullah-restaurant-erp'); ?></label>
                        <textarea id="branch-address" name="address" rows="3" class="large-text"><?php echo esc_textarea($branch->address ?? ''); ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="branch-phone"><?php esc_html_e('Phone', 'obydullah-restaurant-erp'); ?></label>
                            <input type="tel" id="branch-phone" name="phone" class="regular-text"
                                value="<?php echo esc_attr($branch->phone ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="branch-email"><?php esc_html_e('Email', 'obydullah-restaurant-erp'); ?></label>
                            <input type="email" id="branch-email" name="email" class="regular-text"
                                value="<?php echo esc_attr($branch->email ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="branch-manager"><?php esc_html_e('Manager', 'obydullah-restaurant-erp'); ?></label>
                        <select id="branch-manager" name="manager_id" class="regular-text">
                            <option value="0"><?php esc_html_e('Select Manager', 'obydullah-restaurant-erp'); ?></option>
                            <?php $this->render_manager_options($branch->manager_id ?? 0); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" value="1" <?php checked($branch->is_active ?? 1, 1); ?>>
                            <?php esc_html_e('Active', 'obydullah-restaurant-erp'); ?>
                        </label>
                    </div>

                    <p class="submit">
                        <button type="submit" id="submit-branch" class="button button-primary">
                            <span class="btn-text"><?php esc_html_e('Save Branch', 'obydullah-restaurant-erp'); ?></span>
                            <span class="spinner" style="display:none;"></span>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    private function render_manager_options($selected = 0)
    {
        $users = get_users(['fields' => ['ID', 'display_name'], 'orderby' => 'display_name']);

        foreach ($users as $user) {
            printf(
                '<option value="%d" %s>%s</option>',
                intval($user->ID),
                selected($selected, $user->ID, false),
                esc_html($user->display_name)
            );
        }
    }

    public function get_branches($args = [])
    {
        global $wpdb;

        $defaults = [
            'per_page' => 20,
            'page'     => 1,
            'search'   => '',
            'active'   => '',
            'orderby'  => 'name',
            'order'    => 'ASC',
        ];

        $args = wp_parse_args($args, $defaults);

        $where = '1=1';
        $prepare_args = [];

        if (!empty($args['search'])) {
            $where .= ' AND (name LIKE %s OR code LIKE %s)';
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

        $orderby_map = [
            'name' => 'name',
            'code' => 'code',
            'created_at' => 'created_at',
        ];
        $orderby = $orderby_map[$args['orderby']] ?? 'name';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
            array_merge($prepare_args, [$args['per_page'], $offset])
        ));

        return [
            'branches'     => $results ?: [],
            'total'        => $total,
            'total_pages'  => ceil($total / $args['per_page']),
            'current_page' => $args['page'],
            'per_page'     => $args['per_page'],
        ];
    }

    public function get_branch($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", intval($id)));
    }

    public function save_branch($data)
    {
        global $wpdb;

        $id = intval($data['branch_id'] ?? 0);
        $name = sanitize_text_field($data['name'] ?? '');
        $code = sanitize_text_field($data['code'] ?? '');
        $address = sanitize_textarea_field($data['address'] ?? '');
        $phone = sanitize_text_field($data['phone'] ?? '');
        $email = sanitize_email($data['email'] ?? '');
        $manager_id = intval($data['manager_id'] ?? 0);
        $is_active = isset($data['is_active']) ? 1 : 0;

        if (empty($name) || empty($code)) {
            return new WP_Error('missing_fields', __('Branch name and code are required.', 'obydullah-restaurant-erp'));
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE code = %s AND id != %d",
            $code,
            $id
        ));

        if ($existing) {
            return new WP_Error('duplicate_code', __('Branch code already exists.', 'obydullah-restaurant-erp'));
        }

        $save_data = [
            'name'       => $name,
            'code'       => $code,
            'address'    => $address,
            'phone'      => $phone,
            'email'      => $email,
            'manager_id' => $manager_id,
            'is_active'  => $is_active,
        ];

        if ($id > 0) {
            $result = $wpdb->update($this->table, $save_data, ['id' => $id]);
        } else {
            $result = $wpdb->insert($this->table, $save_data);
            $id = $wpdb->insert_id;
        }

        if ($result === false) {
            return new WP_Error('save_failed', __('Failed to save branch.', 'obydullah-restaurant-erp'));
        }

        return $id;
    }

    public function delete_branch($id)
    {
        global $wpdb;
        $id = intval($id);

        $stock_table = $wpdb->prefix . 'erp_branch_stock';
        $stock_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$stock_table} WHERE branch_id = %d AND quantity > 0",
            $id
        ));

        if ($stock_count > 0) {
            return new WP_Error('has_stock', __('Cannot delete branch with stock items.', 'obydullah-restaurant-erp'));
        }

        $result = $wpdb->delete($this->table, ['id' => $id]);
        $wpdb->delete($stock_table, ['branch_id' => $id]);

        return $result !== false;
    }

    public function ajax_get_branches()
    {
        check_ajax_referer('orerp_branches', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $page = intval($_GET['page'] ?? 1);
        $per_page = intval($_GET['per_page'] ?? 20);
        $search = sanitize_text_field(wp_unslash($_GET['search'] ?? ''));

        $result = $this->get_branches([
            'page'     => $page,
            'per_page' => $per_page,
            'search'   => $search,
        ]);

        $helpers = new Obydullah_ERP_Helpers();

        foreach ($result['branches'] as &$branch) {
            $branch->formatted_date = $helpers->format_date($branch->created_at);
            $manager = get_user_by('ID', $branch->manager_id);
            $branch->manager_name = $manager ? $manager->display_name : '-';
        }

        wp_send_json_success($result);
    }

    public function ajax_save_branch()
    {
        check_ajax_referer('orerp_save_branch', 'branch_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $result = $this->save_branch($_POST);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(['id' => $result, 'message' => __('Branch saved successfully.', 'obydullah-restaurant-erp')]);
    }

    public function ajax_delete_branch()
    {
        check_ajax_referer('orerp_branches', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            wp_send_json_error(__('Invalid branch ID.', 'obydullah-restaurant-erp'));
        }

        $result = $this->delete_branch($id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Branch deleted successfully.', 'obydullah-restaurant-erp'));
    }

    public function ajax_set_current_branch()
    {
        check_ajax_referer('orerp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $branch_id = intval($_POST['branch_id'] ?? 0);
        Obydullah_ERP_Helpers::set_current_branch_id($branch_id);

        wp_send_json_success(__('Branch switched.', 'obydullah-restaurant-erp'));
    }

    public function ajax_get_branch_for_edit()
    {
        check_ajax_referer('orerp_branches', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $id = intval($_GET['id'] ?? 0);
        $branch = $this->get_branch($id);

        if (!$branch) {
            wp_send_json_error(__('Branch not found.', 'obydullah-restaurant-erp'));
        }

        wp_send_json_success($branch);
    }

    public function get_all_active()
    {
        global $wpdb;
        return $wpdb->get_results("SELECT id, name, code FROM {$this->table} WHERE is_active = 1 ORDER BY name");
    }

    public function get_branch_stock($branch_id, $args = [])
    {
        global $wpdb;

        $stock_table = $wpdb->prefix . 'erp_branch_stock';
        $defaults = ['per_page' => 20, 'page' => 1, 'search' => ''];
        $args = wp_parse_args($args, $defaults);

        $where = 'bs.branch_id = %d';
        $prepare_args = [$branch_id];

        if (!empty($args['search'])) {
            $where .= ' AND p.post_title LIKE %s';
            $prepare_args[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }

        $offset = ($args['page'] - 1) * $args['per_page'];

        $total = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$stock_table} bs LEFT JOIN {$wpdb->posts} p ON bs.product_id = p.ID WHERE {$where}",
            $prepare_args
        )));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT bs.*, p.post_title as product_name
            FROM {$stock_table} bs
            LEFT JOIN {$wpdb->posts} p ON bs.product_id = p.ID
            WHERE {$where}
            ORDER BY p.post_title ASC
            LIMIT %d OFFSET %d",
            array_merge($prepare_args, [$args['per_page'], $offset])
        ));

        return [
            'stock'        => $results ?: [],
            'total'        => $total,
            'total_pages'  => ceil($total / $args['per_page']),
            'current_page' => $args['page'],
        ];
    }

    public function update_branch_stock($branch_id, $product_id, $quantity, $note = '')
    {
        global $wpdb;

        $stock_table = $wpdb->prefix . 'erp_branch_stock';

        $current = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$stock_table} WHERE branch_id = %d AND product_id = %d",
            $branch_id,
            $product_id
        ));

        $old_qty = $current ? intval($current->quantity) : 0;
        $new_qty = max(0, $old_qty + intval($quantity));

        if ($current) {
            $wpdb->update($stock_table, [
                'quantity'       => $new_qty,
                'last_restocked' => current_time('mysql'),
            ], [
                'branch_id'  => $branch_id,
                'product_id' => $product_id,
            ]);
        } else {
            $wpdb->insert($stock_table, [
                'branch_id'       => $branch_id,
                'product_id'      => $product_id,
                'quantity'        => $new_qty,
                'last_restocked'  => current_time('mysql'),
            ]);
        }

        return ['old_qty' => $old_qty, 'new_qty' => $new_qty];
    }
}
