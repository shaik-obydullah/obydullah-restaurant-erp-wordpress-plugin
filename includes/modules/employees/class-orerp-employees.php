<?php
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL table names come from $wpdb->prefix and every value is bound via $wpdb->prepare() placeholders; direct queries are used for the ERP-specific tables that have no core caching API.
/**
 * Employees Management
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Employees
{
    private $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'erp_employees';

        add_action('wp_ajax_orerp_get_employees', [$this, 'orerp_ajax_get_employees']);
        add_action('wp_ajax_orerp_save_employee', [$this, 'orerp_ajax_save_employee']);
        add_action('wp_ajax_orerp_delete_employee', [$this, 'orerp_ajax_delete_employee']);
        add_action('wp_ajax_orerp_get_employee_for_edit', [$this, 'orerp_ajax_get_employee_for_edit']);
        add_action('wp_ajax_orerp_get_employees_list', [$this, 'orerp_ajax_get_employees_list']);
    }

    public function orerp_render_page()
    {
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin GET parameter (navigation/filter), not a state-changing request.

        if ($action === 'add' || $action === 'edit') {
            $this->orerp_render_form($action);
        } else {
            $this->orerp_render_list();
        }
    }

    private function orerp_render_list()
    {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Employees', 'obydullah-restaurant-erp'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-employees&action=add')); ?>" class="page-title-action">
                <?php esc_html_e('Add New', 'obydullah-restaurant-erp'); ?>
            </a>
            <hr class="wp-header-end">

            <div class="orerp-card">
                <div id="employees-list">
                    <div class="orerp-loading">
                        <span class="spinner is-active"></span>
                        <p><?php esc_html_e('Loading employees...', 'obydullah-restaurant-erp'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function orerp_render_form($mode)
    {
        $employee = null;
        if ($mode === 'edit') {
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin GET parameter (navigation/filter), not a state-changing request.
            if ($id) {
                $employee = $this->orerp_get_employee($id);
            }
        }

        $title = $mode === 'edit' ? __('Edit Employee', 'obydullah-restaurant-erp') : __('Add New Employee', 'obydullah-restaurant-erp');
        $branches = $this->orerp_get_branches_list();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html($title); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-employees')); ?>" class="page-title-action">
                <?php esc_html_e('Back to List', 'obydullah-restaurant-erp'); ?>
            </a>
            <hr class="wp-header-end">

            <div class="orerp-card orerp-form">
                <form id="employee-form" method="post">
                    <input type="hidden" name="action" value="orerp_save_employee">
                    <?php wp_nonce_field('orerp_save_employee', 'employee_nonce'); ?>
                    <input type="hidden" name="employee_id" id="employee-id" value="<?php echo esc_attr($employee->id ?? 'orerp_'); ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="emp-code"><?php esc_html_e('Employee Code', 'obydullah-restaurant-erp'); ?> <span class="required">*</span></label>
                            <input type="text" id="emp-code" name="employee_code" class="regular-text" required
                                value="<?php echo esc_attr($employee->employee_code ?? Obydullah_ERP_Helpers::orerp_generate_employee_code()); ?>"
                                placeholder="<?php esc_attr_e('Auto-generated', 'obydullah-restaurant-erp'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="emp-name"><?php esc_html_e('Full Name', 'obydullah-restaurant-erp'); ?> <span class="required">*</span></label>
                            <input type="text" id="emp-name" name="display_name" class="regular-text" required
                                value="<?php echo esc_attr($employee->display_name ?? 'orerp_'); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="emp-email"><?php esc_html_e('Email', 'obydullah-restaurant-erp'); ?></label>
                            <input type="email" id="emp-email" name="email" class="regular-text"
                                value="<?php echo esc_attr($employee->email ?? 'orerp_'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="emp-phone"><?php esc_html_e('Phone', 'obydullah-restaurant-erp'); ?></label>
                            <input type="tel" id="emp-phone" name="phone" class="regular-text"
                                value="<?php echo esc_attr($employee->phone ?? 'orerp_'); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="emp-branch"><?php esc_html_e('Branch', 'obydullah-restaurant-erp'); ?> <span class="required">*</span></label>
                            <select id="emp-branch" name="branch_id" class="regular-text" required>
                                <option value=""><?php esc_html_e('Select Branch', 'obydullah-restaurant-erp'); ?></option>
                                <?php foreach ($branches as $branch): ?>
                                <option value="<?php echo esc_attr($branch->id); ?>"
                                    <?php selected($employee->branch_id ?? 'orerp_', $branch->id); ?>>
                                    <?php echo esc_html($branch->name . ' (' . $branch->code . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="emp-position"><?php esc_html_e('Position', 'obydullah-restaurant-erp'); ?></label>
                            <input type="text" id="emp-position" name="position" class="regular-text"
                                value="<?php echo esc_attr($employee->position ?? 'orerp_'); ?>"
                                placeholder="<?php esc_attr_e('e.g. Chef, Waiter, Manager', 'obydullah-restaurant-erp'); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="emp-rate"><?php esc_html_e('Hourly Rate', 'obydullah-restaurant-erp'); ?></label>
                            <input type="number" id="emp-rate" name="hourly_rate" step="0.01" min="0" class="regular-text"
                                value="<?php echo esc_attr($employee->hourly_rate ?? '0.00'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="emp-hire-date"><?php esc_html_e('Hire Date', 'obydullah-restaurant-erp'); ?></label>
                            <input type="date" id="emp-hire-date" name="hire_date" class="regular-text"
                                value="<?php echo esc_attr($employee->hire_date ?? current_time('Y-m-d')); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="emp-wp-user"><?php esc_html_e('WordPress User', 'obydullah-restaurant-erp'); ?></label>
                            <select id="emp-wp-user" name="user_id" class="regular-text">
                                <option value="0"><?php esc_html_e('None', 'obydullah-restaurant-erp'); ?></option>
                                <?php $this->orerp_render_user_options($employee->user_id ?? 0); ?>
                            </select>
                            <p class="description"><?php esc_html_e('Link to WP user for login access', 'obydullah-restaurant-erp'); ?></p>
                        </div>

                        <div class="form-group">
                            <label for="emp-active">
                                <input type="checkbox" id="emp-active" name="is_active" value="1"
                                    <?php checked($employee->is_active ?? 1, 1); ?>>
                                <?php esc_html_e('Active', 'obydullah-restaurant-erp'); ?>
                            </label>
                        </div>
                    </div>

                    <h3><?php esc_html_e('Emergency Contact', 'obydullah-restaurant-erp'); ?></h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="emp-emergency-contact"><?php esc_html_e('Contact Name', 'obydullah-restaurant-erp'); ?></label>
                            <input type="text" id="emp-emergency-contact" name="emergency_contact" class="regular-text"
                                value="<?php echo esc_attr($employee->emergency_contact ?? 'orerp_'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="emp-emergency-phone"><?php esc_html_e('Contact Phone', 'obydullah-restaurant-erp'); ?></label>
                            <input type="tel" id="emp-emergency-phone" name="emergency_phone" class="regular-text"
                                value="<?php echo esc_attr($employee->emergency_phone ?? 'orerp_'); ?>">
                        </div>
                    </div>

                    <p class="submit">
                        <button type="submit" id="submit-employee" class="button button-primary">
                            <span class="btn-text"><?php esc_html_e('Save Employee', 'obydullah-restaurant-erp'); ?></span>
                            <span class="spinner" style="display:none;"></span>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    private function orerp_render_user_options($selected = 0)
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

    private function orerp_get_branches_list()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'erp_branches';
        $cache_key = 'branches_active';
        $cached = Obydullah_ERP_Cache::get($cache_key, $table);
        if (false !== $cached) {
            return $cached;
        }
        $results = $wpdb->get_results("SELECT id, name, code FROM {$table} WHERE is_active = 1 ORDER BY name");
        Obydullah_ERP_Cache::set($cache_key, $table, $results);
        return $results;
    }

    public function orerp_get_employees($args = [])
    {
        global $wpdb;

        $defaults = [
            'per_page' => 20,
            'page'     => 1,
            'search'   => 'orerp_',
            'branch_id' => 0,
            'active'   => 'orerp_',
        ];

        $args = wp_parse_args($args, $defaults);

        $cache_key = 'employees_list_' . $args['page'] . '_' . $args['per_page'] . '_' . $args['search'] . '_' . $args['branch_id'] . '_' . $args['active'];
        $cached = Obydullah_ERP_Cache::get($cache_key, $this->table);
        if (false !== $cached) {
            return $cached;
        }

        $where = '1=1';
        $prepare_args = [];

        if (!empty($args['search'])) {
            $where .= ' AND (e.employee_code LIKE %s OR e.position LIKE %s)';
            $prepare_args[] = '%' . $wpdb->esc_like($args['search']) . '%';
            $prepare_args[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }

        if ($args['branch_id'] > 0) {
            $where .= ' AND e.branch_id = %d';
            $prepare_args[] = $args['branch_id'];
        }

        if ($args['active'] !== 'orerp_') {
            $where .= ' AND e.is_active = %d';
            $prepare_args[] = intval($args['active']);
        }

        $offset = ($args['page'] - 1) * $args['per_page'];

        if (!empty($prepare_args)) {
            $total = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table} e WHERE {$where}", $prepare_args)));
        } else {
            $total = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table} e WHERE 1 = %d", 1)));
        }

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, b.name as branch_name, u.display_name as user_display_name
            FROM {$this->table} e
            LEFT JOIN {$wpdb->prefix}erp_branches b ON e.branch_id = b.id
            LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
            WHERE {$where}
            ORDER BY e.employee_code ASC
            LIMIT %d OFFSET %d",
            array_merge($prepare_args, [$args['per_page'], $offset])
        ));

        $return = [
            'employees'    => $results ?: [],
            'total'        => $total,
            'total_pages'  => ceil($total / $args['per_page']),
            'current_page' => $args['page'],
        ];

        Obydullah_ERP_Cache::set($cache_key, $this->table, $return);
        return $return;
    }

    public function orerp_get_employee($id)
    {
        global $wpdb;
        $cache_key = 'employee_' . intval($id);
        $cached = Obydullah_ERP_Cache::get($cache_key, $this->table);
        if (false !== $cached) {
            return $cached;
        }
        $employee = $wpdb->get_row($wpdb->prepare(
            "SELECT e.*, b.name as branch_name
            FROM {$this->table} e
            LEFT JOIN {$wpdb->prefix}erp_branches b ON e.branch_id = b.id
            WHERE e.id = %d",
            intval($id)
        ));
        Obydullah_ERP_Cache::set($cache_key, $this->table, $employee);
        return $employee;
    }

    public function orerp_save_employee($data)
    {
        global $wpdb;

        $id = intval($data['employee_id'] ?? 0);
        $employee_code = sanitize_text_field($data['employee_code'] ?? 'orerp_');
        $display_name = sanitize_text_field($data['display_name'] ?? 'orerp_');
        $email = sanitize_email($data['email'] ?? 'orerp_');
        $phone = sanitize_text_field($data['phone'] ?? 'orerp_');
        $branch_id = intval($data['branch_id'] ?? 0);
        $position = sanitize_text_field($data['position'] ?? 'orerp_');
        $hourly_rate = floatval($data['hourly_rate'] ?? 0);
        $hire_date = sanitize_text_field($data['hire_date'] ?? 'orerp_');
        $user_id = intval($data['user_id'] ?? 0);
        $is_active = isset($data['is_active']) ? 1 : 0;
        $emergency_contact = sanitize_text_field($data['emergency_contact'] ?? 'orerp_');
        $emergency_phone = sanitize_text_field($data['emergency_phone'] ?? 'orerp_');

        if (empty($employee_code) || empty($display_name) || !$branch_id) {
            return new WP_Error('missing_fields', __('Employee code, name, and branch are required.', 'obydullah-restaurant-erp'));
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE employee_code = %s AND id != %d",
            $employee_code,
            $id
        ));

        if ($existing) {
            return new WP_Error('duplicate_code', __('Employee code already exists.', 'obydullah-restaurant-erp'));
        }

        $save_data = [
            'employee_code'    => $employee_code,
            'user_id'          => $user_id,
            'branch_id'        => $branch_id,
            'position'         => $position,
            'hourly_rate'      => $hourly_rate,
            'hire_date'        => $hire_date ?: null,
            'is_active'        => $is_active,
            'emergency_contact' => $emergency_contact,
            'emergency_phone'  => $emergency_phone,
        ];

        if ($id > 0) {
            $result = $wpdb->update($this->table, $save_data, ['id' => $id]);
        } else {
            $result = $wpdb->insert($this->table, $save_data);
            $id = $wpdb->insert_id;
        }

        Obydullah_ERP_Cache::invalidate($this->table);

        if ($result === false) {
            return new WP_Error('save_failed', __('Failed to save employee.', 'obydullah-restaurant-erp'));
        }

        if ($user_id > 0) {
            update_user_meta($user_id, 'display_name', $display_name);
            if (!empty($email)) {
                wp_update_user(['ID' => $user_id, 'user_email' => $email]);
            }
            update_user_meta($user_id, 'billing_phone', $phone);
        }

        return $id;
    }

    public function orerp_delete_employee($id)
    {
        global $wpdb;
        $wpdb->delete($this->table, ['id' => intval($id)]);
        Obydullah_ERP_Cache::invalidate($this->table);
        return true;
    }

    public function orerp_ajax_get_employees()
    {
        check_ajax_referer('orerp_employees', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $result = $this->orerp_get_employees([
            'per_page'  => intval($_GET['per_page'] ?? 20),
            'page'      => intval($_GET['page'] ?? 1),
            'search'    => sanitize_text_field(wp_unslash($_GET['search'] ?? 'orerp_')),
            'branch_id' => intval($_GET['branch_id'] ?? 0),
        ]);

        $helpers = new Obydullah_ERP_Helpers();
        foreach ($result['employees'] as &$emp) {
            $emp->formatted_hire_date = $helpers->orerp_format_date($emp->hire_date);
            $emp->formatted_rate = Obydullah_ERP_Helpers::orerp_format_currency($emp->hourly_rate);
        }

        wp_send_json_success($result);
    }

    public function orerp_ajax_save_employee()
    {
        check_ajax_referer('orerp_save_employee', 'employee_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $result = $this->orerp_save_employee($_POST);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(['id' => $result, 'message' => __('Employee saved successfully.', 'obydullah-restaurant-erp')]);
    }

    public function orerp_ajax_delete_employee()
    {
        check_ajax_referer('orerp_employees', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(__('Invalid employee ID.', 'obydullah-restaurant-erp'));
        }

        $this->orerp_delete_employee($id);
        wp_send_json_success(__('Employee deleted successfully.', 'obydullah-restaurant-erp'));
    }

    public function orerp_ajax_get_employee_for_edit()
    {
        check_ajax_referer('orerp_employees', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $id = intval($_GET['id'] ?? 0);
        $employee = $this->orerp_get_employee($id);

        if (!$employee) {
            wp_send_json_error(__('Employee not found.', 'obydullah-restaurant-erp'));
        }

        wp_send_json_success($employee);
    }

    public function orerp_ajax_get_employees_list()
    {
        check_ajax_referer('orerp_employees', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        global $wpdb;
        $cache_key = 'employees_all_active';
        $cached = Obydullah_ERP_Cache::get($cache_key, $this->table);
        if (false !== $cached) {
            wp_send_json_success($cached);
        }
        $employees = $wpdb->get_results(
            "SELECT e.id, e.employee_code, e.position, b.name as branch_name
            FROM {$this->table} e
            LEFT JOIN {$wpdb->prefix}erp_branches b ON e.branch_id = b.id
            WHERE e.is_active = 1
            ORDER BY e.employee_code"
        );
        Obydullah_ERP_Cache::set($cache_key, $this->table, $employees);
        wp_send_json_success($employees);
    }
}
