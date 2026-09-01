<?php
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL table names come from $wpdb->prefix and every value is bound via $wpdb->prepare() placeholders; direct queries are used for the ERP-specific tables that have no core caching API.
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
    public static function orerp_get_capabilities()
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
    public static function orerp_register_roles()
    {
        $full = array_keys(self::orerp_get_capabilities());

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
            foreach (array_keys(self::orerp_get_capabilities()) as $cap) {
                $admin->add_cap($cap);
            }
        }
    }

    public function __construct()
    {
        add_action('init', [__CLASS__, 'orerp_register_roles']);

        add_action('wp_ajax_orerp_get_employee_roles', [$this, 'orerp_ajax_get_employee_roles']);
        add_action('wp_ajax_orerp_assign_employee_role', [$this, 'orerp_ajax_assign_employee_role']);
    }

    public function orerp_render_page()
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
                        <?php foreach ($this->orerp_get_role_capabilities() as $role_key => $cap_list): ?>
                        <tr>
                            <td><strong><?php echo esc_html(wp_roles()->roles[$role_key]['name'] ?? $role_key); ?></strong></td>
                            <td>
                                <?php
                                $labels = self::orerp_get_capabilities();
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
    private function orerp_get_role_capabilities()
    {
        $full = array_keys(self::orerp_get_capabilities());

        return [
            'administrator'           => $full,
            'restaurant_manager'      => $full,
            'restaurant_kitchen_staff' => ['orerp_kitchen'],
            'restaurant_cashier'      => ['orerp_reports'],
        ];
    }

    public function orerp_get_employees_with_roles()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'erp_employees';
        $cache_key = 'employees_with_roles';
        $cached = Obydullah_ERP_Cache::get($cache_key, $table);
        if (false !== $cached) {
            return $cached;
        }

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
            $employee->wp_role = 'orerp_';
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

        Obydullah_ERP_Cache::set($cache_key, $table, $users);
        return $users;
    }

    public function orerp_assign_role($user_id, $role_key)
    {
        global $wpdb;
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
            Obydullah_ERP_Cache::invalidate($wpdb->prefix . 'erp_employees');
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

        Obydullah_ERP_Cache::invalidate($wpdb->prefix . 'erp_employees');

        return true;
    }

    // --- AJAX ---

    public function orerp_ajax_get_employee_roles()
    {
        check_ajax_referer('orerp_roles', 'nonce');

        if (!Obydullah_ERP_Helpers::can('orerp_admin')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        wp_send_json_success($this->orerp_get_employees_with_roles());
    }

    public function orerp_ajax_assign_employee_role()
    {
        check_ajax_referer('orerp_roles', 'nonce');

        if (!Obydullah_ERP_Helpers::can('orerp_admin')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $user_id = intval($_POST['user_id'] ?? 0);
        $role_key = sanitize_text_field(wp_unslash($_POST['role'] ?? 'orerp_'));

        $result = $this->orerp_assign_role($user_id, $role_key);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Role updated.', 'obydullah-restaurant-erp'));
    }
}
