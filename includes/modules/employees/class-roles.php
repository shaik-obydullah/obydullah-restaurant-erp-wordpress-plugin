<?php
/**
 * Custom Roles & Permissions
 *
 * Registers restaurant-specific WordPress roles with ERP capabilities and
 * provides an admin page to assign roles to employees.
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Roles
{
    /**
     * ERP capability definitions.
     *
     * @return array
     */
    public static function get_capabilities()
    {
        return [
            'orerp_admin'     => __('Full ERP administration', 'obydullah-restaurant-erp'),
            'orerp_kitchen'   => __('Kitchen & attendance access', 'obydullah-restaurant-erp'),
            'orerp_reports'   => __('View reports & dashboard', 'obydullah-restaurant-erp'),
        ];
    }

    /**
     * Register the restaurant roles.
     *
     * @return void
     */
    public static function register_roles()
    {
        $full = array_keys(self::get_capabilities());

        add_role(
            'restaurant_manager',
            __('Restaurant Manager', 'obydullah-restaurant-erp'),
            array_fill_keys($full, true)
        );

        add_role(
            'restaurant_kitchen_staff',
            __('Kitchen Staff', 'obydullah-restaurant-erp'),
            [
                'orerp_kitchen' => true,
                'read'          => true,
            ]
        );

        add_role(
            'restaurant_cashier',
            __('Cashier', 'obydullah-restaurant-erp'),
            [
                'orerp_reports' => true,
                'read'          => true,
            ]
        );

        // Give administrators all ERP capabilities as well.
        $admin = get_role('administrator');
        if ($admin) {
            foreach (array_keys(self::get_capabilities()) as $cap) {
                $admin->add_cap($cap);
            }
        }
    }

    public function __construct()
    {
        add_action('init', [__CLASS__, 'register_roles']);

        add_action('wp_ajax_orerp_get_employee_roles', [$this, 'ajax_get_employee_roles']);
        add_action('wp_ajax_orerp_assign_employee_role', [$this, 'ajax_assign_employee_role']);
    }

    public function render_page()
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Roles & Permissions', 'obydullah-restaurant-erp'); ?></h1>
            <hr class="wp-header-end">

            <div class="orerp-card">
                <div class="orerp-card-header">
                    <h2><?php esc_html_e('Assigned Roles', 'obydullah-restaurant-erp'); ?></h2>
                </div>
                <div id="roles-list">
                    <div class="orerp-loading">
                        <span class="spinner is-active"></span>
                        <p><?php esc_html_e('Loading employees...', 'obydullah-restaurant-erp'); ?></p>
                    </div>
                </div>
            </div>

            <div class="orerp-card">
                <div class="orerp-card-header">
                    <h2><?php esc_html_e('Available Roles & Capabilities', 'obydullah-restaurant-erp'); ?></h2>
                </div>
                <table class="orerp-table widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Role', 'obydullah-restaurant-erp'); ?></th>
                            <th><?php esc_html_e('Capabilities', 'obydullah-restaurant-erp'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->get_role_capabilities() as $role_key => $cap_list): ?>
                        <tr>
                            <td><strong><?php echo esc_html(wp_roles()->roles[$role_key]['name'] ?? $role_key); ?></strong></td>
                            <td>
                                <?php
                                $labels = self::get_capabilities();
                                foreach ($cap_list as $cap) {
                                    if (isset($labels[$cap])) {
                                        printf('<span class="status-badge active">%s</span> ', esc_html($labels[$cap]));
                                    }
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Map of role key => granted ERP capabilities.
     *
     * @return array
     */
    private function get_role_capabilities()
    {
        $full = array_keys(self::get_capabilities());

        return [
            'administrator'           => $full,
            'restaurant_manager'      => $full,
            'restaurant_kitchen_staff' => ['orerp_kitchen'],
            'restaurant_cashier'      => ['orerp_reports'],
        ];
    }

    public function get_employees_with_roles()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'erp_employees';
        $users = $wpdb->get_results(
            "SELECT e.id, e.employee_code, e.position, e.user_id,
                b.name AS branch_name, u.display_name AS display_name
            FROM {$table} e
            LEFT JOIN {$wpdb->prefix}erp_branches b ON e.branch_id = b.id
            LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
            WHERE e.is_active = 1
            ORDER BY u.display_name ASC"
        ) ?: [];

        foreach ($users as &$employee) {
            $employee->wp_role = '';
            if ($employee->user_id) {
                $user = get_userdata($employee->user_id);
                if ($user) {
                    $restaurant_roles = array_filter(
                        $user->roles,
                        function ($role) {
                            return in_array($role, ['restaurant_manager', 'restaurant_kitchen_staff', 'restaurant_cashier'], true);
                        }
                    );
                    $employee->wp_role = implode(',', $restaurant_roles);
                }
            }
        }

        return $users;
    }

    public function assign_role($user_id, $role_key)
    {
        $user_id = intval($user_id);

        if (!$user_id) {
            return new WP_Error('invalid_user', __('Invalid user.', 'obydullah-restaurant-erp'));
        }

        $allowed = ['restaurant_manager', 'restaurant_kitchen_staff', 'restaurant_cashier'];

        if (empty($role_key)) {
            // Remove restaurant roles from the user.
            $user = new WP_User($user_id);
            foreach ($allowed as $role) {
                $user->remove_role($role);
            }
            return true;
        }

        if (!in_array($role_key, $allowed, true)) {
            return new WP_Error('invalid_role', __('Invalid role.', 'obydullah-restaurant-erp'));
        }

        $user = new WP_User($user_id);
        foreach ($allowed as $role) {
            $user->remove_role($role);
        }
        $user->add_role($role_key);

        return true;
    }

    // --- AJAX ---

    public function ajax_get_employee_roles()
    {
        check_ajax_referer('orerp_roles', 'nonce');

        if (!Obydullah_ERP_Helpers::can('orerp_admin')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        wp_send_json_success($this->get_employees_with_roles());
    }

    public function ajax_assign_employee_role()
    {
        check_ajax_referer('orerp_roles', 'nonce');

        if (!Obydullah_ERP_Helpers::can('orerp_admin')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $user_id = intval($_POST['user_id'] ?? 0);
        $role_key = sanitize_text_field(wp_unslash($_POST['role'] ?? ''));

        $result = $this->assign_role($user_id, $role_key);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Role updated.', 'obydullah-restaurant-erp'));
    }
}
