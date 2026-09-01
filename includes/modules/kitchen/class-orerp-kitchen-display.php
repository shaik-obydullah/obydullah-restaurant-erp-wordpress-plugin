<?php
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL table names come from $wpdb->prefix and every value is bound via $wpdb->prepare() placeholders; direct queries are used for the ERP-specific tables that have no core caching API.
/**
 * Kitchen Display System (KDS)
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Kitchen_Display
{
    private $table_orders;
    private $table_prep;

    public function __construct()
    {
        global $wpdb;
        $this->table_orders = $wpdb->prefix . 'erp_kitchen_orders';
        $this->table_prep   = $wpdb->prefix . 'erp_prep_tracking';

        add_action('wp_ajax_orerp_get_kitchen_orders', [$this, 'orerp_ajax_get_orders']);
        add_action('wp_ajax_orerp_update_order_status', [$this, 'orerp_ajax_update_status']);
        add_action('wp_ajax_orerp_create_kitchen_order', [$this, 'orerp_ajax_create_order']);
        add_action('wp_ajax_orerp_add_prep_tracking', [$this, 'orerp_ajax_add_prep']);
        add_action('wp_ajax_orerp_complete_prep', [$this, 'orerp_ajax_complete_prep']);
        add_action('wp_ajax_orerp_get_kitchen_stats', [$this, 'orerp_ajax_get_stats']);

        add_shortcode('orerp_kds', [$this, 'orerp_render_kds_shortcode']);
    }

    /**
     * Render the standalone KDS board via the [orerp_kds] shortcode.
     *
     * @return string
     */
    public function orerp_render_kds_shortcode()
    {
        if (!Obydullah_ERP_Helpers::can('orerp_kitchen')) {
            return '<p>' . esc_html__('You do not have permission to view the kitchen display.', 'obydullah-restaurant-erp') . '</p>';
        }

        $template = ORERP_PATH . 'templates/orerp-kds-display.php';
        if (!file_exists($template)) {
            return 'orerp_';
        }

        ob_start();
        include $template;
        return ob_get_clean();
    }

    public function orerp_render_page()
    {
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin GET parameter (navigation/filter), not a state-changing request.

        if ($action === 'add') {
            $this->orerp_render_form('add');
        } else {
            $this->orerp_render_kds();
        }
    }

    private function orerp_render_kds()
    {
        $branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin GET parameter (navigation/filter), not a state-changing request.
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Kitchen Display', 'obydullah-restaurant-erp'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-kitchen&action=add')); ?>" class="page-title-action">
                <?php esc_html_e('New Order', 'obydullah-restaurant-erp'); ?>
            </a>
            <hr class="wp-header-end">

            <div class="orerp-filters" style="margin-bottom:20px;">
                <div class="filter-group">
                    <label><?php esc_html_e('Branch', 'obydullah-restaurant-erp'); ?></label>
                    <select id="kitchen-branch-filter">
                        <option value=""><?php esc_html_e('All Branches', 'obydullah-restaurant-erp'); ?></option>
                        <?php $this->orerp_render_branch_options($branch_id); ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label><?php esc_html_e('Station', 'obydullah-restaurant-erp'); ?></label>
                    <select id="kitchen-station-filter">
                        <option value=""><?php esc_html_e('All Stations', 'obydullah-restaurant-erp'); ?></option>
                        <option value="grill"><?php esc_html_e('Grill', 'obydullah-restaurant-erp'); ?></option>
                        <option value="fry"><?php esc_html_e('Fry', 'obydullah-restaurant-erp'); ?></option>
                        <option value="salad"><?php esc_html_e('Salad/Prep', 'obydullah-restaurant-erp'); ?></option>
                        <option value="dessert"><?php esc_html_e('Dessert', 'obydullah-restaurant-erp'); ?></option>
                        <option value="drinks"><?php esc_html_e('Drinks', 'obydullah-restaurant-erp'); ?></option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><?php esc_html_e('Status', 'obydullah-restaurant-erp'); ?></label>
                    <select id="kitchen-status-filter">
                        <option value=""><?php esc_html_e('All', 'obydullah-restaurant-erp'); ?></option>
                        <option value="pending"><?php esc_html_e('Pending', 'obydullah-restaurant-erp'); ?></option>
                        <option value="preparing"><?php esc_html_e('Preparing', 'obydullah-restaurant-erp'); ?></option>
                        <option value="ready"><?php esc_html_e('Ready', 'obydullah-restaurant-erp'); ?></option>
                    </select>
                </div>
            </div>

            <div class="kitchen-stats-row">
                <div class="kitchen-stat-card" id="stat-pending">
                    <span class="stat-number">0</span>
                    <span class="stat-label"><?php esc_html_e('Pending', 'obydullah-restaurant-erp'); ?></span>
                </div>
                <div class="kitchen-stat-card preparing" id="stat-preparing">
                    <span class="stat-number">0</span>
                    <span class="stat-label"><?php esc_html_e('Preparing', 'obydullah-restaurant-erp'); ?></span>
                </div>
                <div class="kitchen-stat-card ready" id="stat-ready">
                    <span class="stat-number">0</span>
                    <span class="stat-label"><?php esc_html_e('Ready', 'obydullah-restaurant-erp'); ?></span>
                </div>
                <div class="kitchen-stat-card completed" id="stat-completed">
                    <span class="stat-number">0</span>
                    <span class="stat-label"><?php esc_html_e('Completed (Today)', 'obydullah-restaurant-erp'); ?></span>
                </div>
            </div>

            <div class="orerp-card">
                <div id="kitchen-orders-grid" class="kitchen-grid">
                    <div class="orerp-loading">
                        <span class="spinner is-active"></span>
                        <p><?php esc_html_e('Loading orders...', 'obydullah-restaurant-erp'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function orerp_render_form($mode)
    {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('New Kitchen Order', 'obydullah-restaurant-erp'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-kitchen')); ?>" class="page-title-action">
                <?php esc_html_e('Back to KDS', 'obydullah-restaurant-erp'); ?>
            </a>
            <hr class="wp-header-end">

            <div class="orerp-card orerp-form">
                <form id="kitchen-order-form" method="post">
                    <input type="hidden" name="action" value="orerp_create_kitchen_order">
                    <?php wp_nonce_field('orerp_kitchen', 'nonce'); ?>

                    <div class="form-row">
                        <div class="form-group">
                            <label><?php esc_html_e('WooCommerce Order ID', 'obydullah-restaurant-erp'); ?> <span class="required">*</span></label>
                            <input type="number" name="order_id" class="regular-text" required min="1">
                        </div>
                        <div class="form-group">
                            <label><?php esc_html_e('Branch', 'obydullah-restaurant-erp'); ?> <span class="required">*</span></label>
                            <select name="branch_id" class="regular-text" required>
                                <?php $this->orerp_render_branch_options(0); ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><?php esc_html_e('Station', 'obydullah-restaurant-erp'); ?></label>
                            <select name="station" class="regular-text">
                                <option value="grill"><?php esc_html_e('Grill', 'obydullah-restaurant-erp'); ?></option>
                                <option value="fry"><?php esc_html_e('Fry', 'obydullah-restaurant-erp'); ?></option>
                                <option value="salad"><?php esc_html_e('Salad/Prep', 'obydullah-restaurant-erp'); ?></option>
                                <option value="dessert"><?php esc_html_e('Dessert', 'obydullah-restaurant-erp'); ?></option>
                                <option value="drinks"><?php esc_html_e('Drinks', 'obydullah-restaurant-erp'); ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?php esc_html_e('Priority', 'obydullah-restaurant-erp'); ?></label>
                            <select name="priority" class="regular-text">
                                <option value="0"><?php esc_html_e('Normal', 'obydullah-restaurant-erp'); ?></option>
                                <option value="1"><?php esc_html_e('High', 'obydullah-restaurant-erp'); ?></option>
                                <option value="2"><?php esc_html_e('Urgent (VIP/Rush)', 'obydullah-restaurant-erp'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><?php esc_html_e('Estimated Time (minutes)', 'obydullah-restaurant-erp'); ?></label>
                            <input type="number" name="estimated_time" class="regular-text" min="0" value="15">
                        </div>
                    </div>

                    <div class="form-group">
                        <label><?php esc_html_e('Notes', 'obydullah-restaurant-erp'); ?></label>
                        <textarea name="notes" rows="3" class="large-text"></textarea>
                    </div>

                    <p class="submit">
                        <button type="submit" id="submit-kitchen-order" class="button button-primary">
                            <span class="btn-text"><?php esc_html_e('Create Kitchen Order', 'obydullah-restaurant-erp'); ?></span>
                            <span class="spinner" style="display:none;"></span>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    private function orerp_render_branch_options($selected = 0)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'erp_branches';
        $cache_key = 'active_branches';
        $cached = Obydullah_ERP_Cache::get($cache_key, $table);
        if (false !== $cached) {
            $branches = $cached;
        } else {
            $branches = $wpdb->get_results("SELECT id, name FROM {$table} WHERE is_active = 1 ORDER BY name");
            Obydullah_ERP_Cache::set($cache_key, $table, $branches);
        }

        foreach ($branches as $branch) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($branch->id),
                selected($selected, $branch->id, false),
                esc_html($branch->name)
            );
        }
    }

    public function orerp_get_orders($args = [])
    {
        global $wpdb;

        $defaults = [
            'per_page'  => 50,
            'page'      => 1,
            'status'    => 'orerp_',
            'branch_id' => 0,
            'station'   => 'orerp_',
            'date'      => 'orerp_',
        ];
        $args = wp_parse_args($args, $defaults);

        $where = '1=1';
        $prepare_args = [];

        if (!empty($args['status'])) {
            $where .= ' AND ko.status = %s';
            $prepare_args[] = $args['status'];
        }

        if ($args['branch_id'] > 0) {
            $where .= ' AND ko.branch_id = %d';
            $prepare_args[] = $args['branch_id'];
        }

        if (!empty($args['station'])) {
            $where .= ' AND ko.station = %s';
            $prepare_args[] = $args['station'];
        }

        $date_filter = $args['date'] ?: current_time('Y-m-d');
        $where .= ' AND DATE(ko.created_at) = %s';
        $prepare_args[] = $date_filter;

        $cache_key = 'orders_count_' . md5(serialize($prepare_args));
        $cached = Obydullah_ERP_Cache::get($cache_key, $this->table_orders);
        if (false !== $cached) {
            $total = (int) $cached;
        } else {
            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_orders} ko WHERE {$where}",
                $prepare_args
            ));
            Obydullah_ERP_Cache::set($cache_key, $this->table_orders, $total);
        }

        $offset = ($args['page'] - 1) * $args['per_page'];
        $list_key = 'orders_list_' . md5(serialize(array_merge($prepare_args, [$args['per_page'], $offset])));
        $cached = Obydullah_ERP_Cache::get($list_key, $this->table_orders);
        if (false !== $cached) {
            $orders = $cached;
        } else {
            $orders = $wpdb->get_results($wpdb->prepare(
                "SELECT ko.* FROM {$this->table_orders} ko
                WHERE {$where}
                ORDER BY ko.priority DESC, ko.created_at ASC
                LIMIT %d OFFSET %d",
                array_merge($prepare_args, [$args['per_page'], $offset])
            )) ?: [];
            Obydullah_ERP_Cache::set($list_key, $this->table_orders, $orders);
        }

        foreach ($orders as &$order) {
            $order->formatted_time = Obydullah_ERP_Helpers::orerp_format_date($order->created_at);
            $order->elapsed = $this->orerp_get_elapsed_time($order->started_at ?: $order->created_at);
        }

        return [
            'orders'       => $orders,
            'total'        => $total,
            'total_pages'  => ceil($total / $args['per_page']),
            'current_page' => $args['page'],
        ];
    }

    public function orerp_get_stats($branch_id = 0)
    {
        global $wpdb;

        $today = current_time('Y-m-d');

        $cache_keys = [
            'pending'         => 'stats_pending_' . $branch_id,
            'preparing'       => 'stats_preparing_' . $branch_id,
            'ready'           => 'stats_ready_' . $branch_id,
            'completed_today' => 'stats_completed_today_' . $branch_id . '_' . $today,
        ];

        $stats = [];
        foreach ($cache_keys as $stat => $key) {
            $cached = Obydullah_ERP_Cache::get($key, $this->table_orders);
            if (false !== $cached) {
                $stats[$stat] = (int) $cached;
            }
        }

        if (!isset($stats['pending'])) {
            if ($branch_id > 0) {
                $stats['pending'] = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_orders} WHERE status = 'pending' AND branch_id = %d",
                    $branch_id
                ));
            } else {
                $stats['pending'] = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$this->table_orders} WHERE status = 'pending'"
                );
            }
            Obydullah_ERP_Cache::set($cache_keys['pending'], $this->table_orders, $stats['pending']);
        }
        if (!isset($stats['preparing'])) {
            if ($branch_id > 0) {
                $stats['preparing'] = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_orders} WHERE status = 'preparing' AND branch_id = %d",
                    $branch_id
                ));
            } else {
                $stats['preparing'] = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$this->table_orders} WHERE status = 'preparing'"
                );
            }
            Obydullah_ERP_Cache::set($cache_keys['preparing'], $this->table_orders, $stats['preparing']);
        }
        if (!isset($stats['ready'])) {
            if ($branch_id > 0) {
                $stats['ready'] = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_orders} WHERE status = 'ready' AND branch_id = %d",
                    $branch_id
                ));
            } else {
                $stats['ready'] = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$this->table_orders} WHERE status = 'ready'"
                );
            }
            Obydullah_ERP_Cache::set($cache_keys['ready'], $this->table_orders, $stats['ready']);
        }
        if (!isset($stats['completed_today'])) {
            if ($branch_id > 0) {
                $stats['completed_today'] = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_orders} WHERE status = 'completed' AND DATE(completed_at) = %s AND branch_id = %d",
                    $today, $branch_id
                ));
            } else {
                $stats['completed_today'] = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_orders} WHERE status = 'completed' AND DATE(completed_at) = %s",
                    $today
                ));
            }
            Obydullah_ERP_Cache::set($cache_keys['completed_today'], $this->table_orders, $stats['completed_today']);
        }

        $pending = $stats['pending'];
        $preparing = $stats['preparing'];
        $ready = $stats['ready'];
        $completed_today = $stats['completed_today'];

        return compact('pending', 'preparing', 'ready', 'completed_today');
    }

    public function orerp_create_order($data)
    {
        global $wpdb;

        $order_id    = intval($data['order_id'] ?? 0);
        $branch_id   = intval($data['branch_id'] ?? 0);
        $station     = sanitize_text_field($data['station'] ?? 'orerp_');
        $priority    = intval($data['priority'] ?? 0);
        $est_time    = intval($data['estimated_time'] ?? 15);
        $notes       = sanitize_textarea_field($data['notes'] ?? 'orerp_');

        if ($order_id <= 0 || $branch_id <= 0) {
            return new WP_Error('missing_fields', __('Order ID and Branch are required.', 'obydullah-restaurant-erp'));
        }

        $insert = [
            'order_id'       => $order_id,
            'branch_id'      => $branch_id,
            'station'        => $station,
            'priority'       => $priority,
            'status'         => 'pending',
            'estimated_time' => $est_time,
            'notes'          => $notes,
        ];

        $result = $wpdb->insert($this->table_orders, $insert);
        Obydullah_ERP_Cache::invalidate($this->table_orders);
        return $result !== false ? $wpdb->insert_id : new WP_Error('create_failed', __('Failed to create kitchen order.', 'obydullah-restaurant-erp'));
    }

    public function orerp_update_status($order_id, $new_status)
    {
        global $wpdb;

        $valid = ['pending', 'preparing', 'ready', 'completed', 'cancelled'];
        if (!in_array($new_status, $valid)) {
            return new WP_Error('invalid_status', __('Invalid status.', 'obydullah-restaurant-erp'));
        }

        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_orders} WHERE id = %d", intval($order_id)));
        if (!$order) {
            return new WP_Error('not_found', __('Order not found.', 'obydullah-restaurant-erp'));
        }

        $update = ['status' => $new_status];
        $now = current_time('mysql');

        if ($new_status === 'preparing' && $order->status !== 'preparing') {
            $update['started_at'] = $now;
        } elseif ($new_status === 'completed' && $order->status !== 'completed') {
            $update['completed_at'] = $now;
        }

        $wpdb->update($this->table_orders, $update, ['id' => intval($order_id)]);
        Obydullah_ERP_Cache::invalidate($this->table_orders);

        return true;
    }

    public function orerp_add_prep_tracking($data)
    {
        global $wpdb;

        $kitchen_order_id = intval($data['kitchen_order_id'] ?? 0);
        $recipe_id        = intval($data['recipe_id'] ?? 0) ?: null;
        $employee_id      = intval($data['employee_id'] ?? 0) ?: null;
        $notes            = sanitize_textarea_field($data['notes'] ?? 'orerp_');

        if ($kitchen_order_id <= 0) {
            return new WP_Error('missing_fields', __('Kitchen order ID is required.', 'obydullah-restaurant-erp'));
        }

        $insert = [
            'kitchen_order_id' => $kitchen_order_id,
            'recipe_id'        => $recipe_id,
            'employee_id'      => $employee_id,
            'started_at'       => current_time('mysql'),
            'notes'            => $notes,
        ];

        $result = $wpdb->insert($this->table_prep, $insert);
        Obydullah_ERP_Cache::invalidate($this->table_prep);
        return $result !== false ? $wpdb->insert_id : new WP_Error('save_failed', __('Failed to add prep tracking.', 'obydullah-restaurant-erp'));
    }

    public function orerp_complete_prep($prep_id)
    {
        global $wpdb;

        $prep = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_prep} WHERE id = %d", intval($prep_id)));
        if (!$prep) {
            return new WP_Error('not_found', __('Prep record not found.', 'obydullah-restaurant-erp'));
        }

        $now = current_time('mysql');
        $started = strtotime($prep->started_at);
        $actual_minutes = max(1, round((time() - $started) / 60));

        $wpdb->update($this->table_prep, [
            'completed_at'        => $now,
            'actual_time_minutes' => $actual_minutes,
        ], ['id' => intval($prep_id)]);
        Obydullah_ERP_Cache::invalidate($this->table_prep);

        return ['actual_time_minutes' => $actual_minutes];
    }

    public function orerp_get_prep_by_order($kitchen_order_id)
    {
        global $wpdb;

        $cache_key = 'prep_by_order_' . intval($kitchen_order_id);
        $cached = Obydullah_ERP_Cache::get($cache_key, $this->table_prep);
        if (false !== $cached) {
            return $cached;
        }

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT pt.*, r.name AS recipe_name, COALESCE(NULLIF(e.employee_code, 'orerp_'), u.display_name) AS employee_name
            FROM {$this->table_prep} pt
            LEFT JOIN {$wpdb->prefix}erp_recipes r ON pt.recipe_id = r.id
            LEFT JOIN {$wpdb->prefix}erp_employees e ON pt.employee_id = e.id
            LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
            WHERE pt.kitchen_order_id = %d
            ORDER BY pt.started_at DESC",
            intval($kitchen_order_id)
        )) ?: [];
        Obydullah_ERP_Cache::set($cache_key, $this->table_prep, $results);
        return $results;
    }

    private function orerp_get_elapsed_time($from)
    {
        $diff = time() - strtotime($from);
        if ($diff < 60) return $diff . 's';
        if ($diff < 3600) return floor($diff / 60) . 'm';
        return floor($diff / 3600) . 'h ' . floor(($diff % 3600) / 60) . 'm';
    }

    // --- AJAX ---

    public function orerp_ajax_get_orders()
    {
        check_ajax_referer('orerp_kitchen', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $args = [
            'page'      => intval($_GET['page'] ?? 1),
            'per_page'  => 50,
            'status'    => sanitize_text_field(wp_unslash($_GET['status'] ?? 'orerp_')),
            'branch_id' => intval($_GET['branch_id'] ?? 0),
            'station'   => sanitize_text_field(wp_unslash($_GET['station'] ?? 'orerp_')),
            'date'      => sanitize_text_field(wp_unslash($_GET['date'] ?? 'orerp_')),
        ];

        wp_send_json_success($this->orerp_get_orders($args));
    }

    public function orerp_ajax_update_status()
    {
        check_ajax_referer('orerp_kitchen', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $order_id = intval($_POST['order_id'] ?? 0);
        $status   = sanitize_text_field(wp_unslash($_POST['status'] ?? 'orerp_'));

        $result = $this->orerp_update_status($order_id, $status);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(['message' => __('Order status updated.', 'obydullah-restaurant-erp')]);
    }

    public function orerp_ajax_create_order()
    {
        check_ajax_referer('orerp_kitchen', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $result = $this->orerp_create_order($_POST);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(['id' => $result, 'message' => __('Kitchen order created.', 'obydullah-restaurant-erp')]);
    }

    public function orerp_ajax_add_prep()
    {
        check_ajax_referer('orerp_kitchen', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $result = $this->orerp_add_prep_tracking($_POST);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(['id' => $result, 'message' => __('Prep tracking started.', 'obydullah-restaurant-erp')]);
    }

    public function orerp_ajax_complete_prep()
    {
        check_ajax_referer('orerp_kitchen', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $prep_id = intval($_POST['prep_id'] ?? 0);
        $result = $this->orerp_complete_prep($prep_id);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result + ['message' => __('Prep completed.', 'obydullah-restaurant-erp')]);
    }

    public function orerp_ajax_get_stats()
    {
        check_ajax_referer('orerp_kitchen', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        $branch_id = intval($_GET['branch_id'] ?? 0);
        wp_send_json_success($this->orerp_get_stats($branch_id));
    }
}
