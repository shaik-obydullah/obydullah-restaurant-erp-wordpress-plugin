<?php
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL table names come from $wpdb->prefix and every value is bound via $wpdb->prepare() placeholders; direct queries are used for the ERP-specific tables that have no core caching API.
/**
 * Dashboard Reports
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Dashboard_Reports
{
    public function __construct()
    {
        add_action('wp_ajax_orerp_get_dashboard_stats', [$this, 'orerp_ajax_get_dashboard_stats']);
    }

    public function orerp_render_dashboard()
    {
        $branch_id = Obydullah_ERP_Helpers::orerp_get_current_branch_id();
        $stats = $this->orerp_get_dashboard_stats();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Restaurant ERP Dashboard', 'obydullah-restaurant-erp'); ?></h1>
            <hr class="wp-header-end">

            <!-- Branch Selector -->
            <div style="margin-bottom: 20px;">
                <div class="orerp-branch-selector">
                    <label for="orerp-branch-select"><?php esc_html_e('Current Branch:', 'obydullah-restaurant-erp'); ?></label>
                    <?php $this->orerp_render_branch_selector(); ?>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="orerp-summary-grid">
                <div class="orerp-summary-card blue">
                    <div class="label"><?php esc_html_e('Total Branches', 'obydullah-restaurant-erp'); ?></div>
                    <div class="value"><?php echo esc_html($stats['total_branches']); ?></div>
                </div>

                <div class="orerp-summary-card green">
                    <div class="label"><?php esc_html_e('Active Employees', 'obydullah-restaurant-erp'); ?></div>
                    <div class="value"><?php echo esc_html($stats['active_employees']); ?></div>
                </div>

                <div class="orerp-summary-card purple">
                    <div class="label"><?php esc_html_e('Active Suppliers', 'obydullah-restaurant-erp'); ?></div>
                    <div class="value"><?php echo esc_html($stats['active_suppliers']); ?></div>
                </div>

                <div class="orerp-summary-card orange">
                    <div class="label"><?php esc_html_e('Pending Purchases', 'obydullah-restaurant-erp'); ?></div>
                    <div class="value"><?php echo esc_html($stats['pending_purchases']); ?></div>
                </div>

                <div class="orerp-summary-card red">
                    <div class="label"><?php esc_html_e('Kitchen Orders', 'obydullah-restaurant-erp'); ?></div>
                    <div class="value"><?php echo esc_html($stats['active_kitchen_orders']); ?></div>
                </div>

                <div class="orerp-summary-card blue">
                    <div class="label"><?php esc_html_e('This Month Revenue', 'obydullah-restaurant-erp'); ?></div>
                    <div class="value"><?php echo esc_html(Obydullah_ERP_Helpers::orerp_format_currency($stats['month_revenue'])); ?></div>
                </div>

                <div class="orerp-summary-card red">
                    <div class="label"><?php esc_html_e('This Month Expenses', 'obydullah-restaurant-erp'); ?></div>
                    <div class="value"><?php echo esc_html(Obydullah_ERP_Helpers::orerp_format_currency($stats['month_expenses'])); ?></div>
                </div>

                <div class="orerp-summary-card green">
                    <div class="label"><?php esc_html_e('Net Profit', 'obydullah-restaurant-erp'); ?></div>
                    <div class="value"><?php echo esc_html(Obydullah_ERP_Helpers::orerp_format_currency($stats['net_profit'])); ?></div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="orerp-card">
                <div class="orerp-card-header">
                    <h2><?php esc_html_e('Quick Actions', 'obydullah-restaurant-erp'); ?></h2>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-branches&action=add')); ?>" class="orerp-btn orerp-btn-primary">
                        <span class="dashicons dashicons-building"></span>
                        <?php esc_html_e('Add Branch', 'obydullah-restaurant-erp'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-employees&action=add')); ?>" class="orerp-btn orerp-btn-primary">
                        <span class="dashicons dashicons-groups"></span>
                        <?php esc_html_e('Add Employee', 'obydullah-restaurant-erp'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-suppliers&action=add')); ?>" class="orerp-btn orerp-btn-primary">
                        <span class="dashicons dashicons-businesswoman"></span>
                        <?php esc_html_e('Add Supplier', 'obydullah-restaurant-erp'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-purchases&action=add')); ?>" class="orerp-btn orerp-btn-primary">
                        <span class="dashicons dashicons-cart"></span>
                        <?php esc_html_e('Create Purchase Order', 'obydullah-restaurant-erp'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-kitchen')); ?>" class="orerp-btn orerp-btn-success">
                        <span class="dashicons dashicons-food"></span>
                        <?php esc_html_e('Kitchen Display', 'obydullah-restaurant-erp'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-journal&action=add')); ?>" class="orerp-btn orerp-btn-outline">
                        <span class="dashicons dashicons-media-default"></span>
                        <?php esc_html_e('New Journal Entry', 'obydullah-restaurant-erp'); ?>
                    </a>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="orerp-card">
                <div class="orerp-card-header">
                    <h2><?php esc_html_e('Recent Purchase Orders', 'obydullah-restaurant-erp'); ?></h2>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-purchases')); ?>" class="orerp-btn orerp-btn-sm orerp-btn-outline">
                        <?php esc_html_e('View All', 'obydullah-restaurant-erp'); ?>
                    </a>
                </div>
                <div id="recent-purchases-list">
                    <?php $this->orerp_render_recent_purchases($branch_id); ?>
                </div>
            </div>

            <div class="orerp-card">
                <div class="orerp-card-header">
                    <h2><?php esc_html_e('Recent Journal Entries', 'obydullah-restaurant-erp'); ?></h2>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=orerp-journal')); ?>" class="orerp-btn orerp-btn-sm orerp-btn-outline">
                        <?php esc_html_e('View All', 'obydullah-restaurant-erp'); ?>
                    </a>
                </div>
                <div id="recent-journal-list">
                    <?php $this->orerp_render_recent_journal_entries($branch_id); ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function orerp_render_branch_selector()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'erp_branches';
        $current = Obydullah_ERP_Helpers::orerp_get_current_branch_id();

        $cache_key = 'branch_selector_active';
        $cached = Obydullah_ERP_Cache::get($cache_key, $table);
        if (false !== $cached) {
            $branches = $cached;
        } else {
            $branches = $wpdb->get_results($wpdb->prepare("SELECT id, name FROM {$table} WHERE is_active = 1 AND 1 = %d ORDER BY name", 1));
            Obydullah_ERP_Cache::set($cache_key, $table, $branches);
        }

        echo '<select id="orerp-branch-select">';
        echo '<option value="0">' . esc_html__('All Branches', 'obydullah-restaurant-erp') . '</option>';

        foreach ($branches as $branch) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($branch->id),
                selected($current, $branch->id, false),
                esc_html($branch->name)
            );
        }

        echo '</select>';
    }

    private function orerp_get_dashboard_stats()
    {
        global $wpdb;

        $branch_id = Obydullah_ERP_Helpers::orerp_get_current_branch_id();

        $stats = [
            'total_branches'        => $this->orerp_get_count($wpdb->prefix . 'erp_branches', 'is_active = 1'),
            'active_employees'      => $this->orerp_get_count($wpdb->prefix . 'erp_employees', 'is_active = 1', $branch_id, 'branch_id'),
            'active_suppliers'      => $this->orerp_get_count($wpdb->prefix . 'erp_suppliers', 'is_active = 1'),
            'pending_purchases'     => $this->orerp_get_count($wpdb->prefix . 'erp_purchase_orders', "status IN ('draft','pending','partial')", $branch_id, 'branch_id'),
            'active_kitchen_orders' => $this->orerp_get_count($wpdb->prefix . 'erp_kitchen_orders', "status IN ('pending','preparing')", $branch_id, 'branch_id'),
            'month_revenue'         => $this->orerp_get_month_revenue($branch_id),
            'month_expenses'        => $this->orerp_get_month_expenses($branch_id),
            'net_profit'            => 0,
        ];

        $stats['net_profit'] = $stats['month_revenue'] - $stats['month_expenses'];

        return $stats;
    }

    private function orerp_get_count($table, $where = '1=1', $branch_id = 0, $branch_column = '')
    {
        global $wpdb;

        $extra_where = '';
        $extra_args = [];

        if ($branch_id > 0 && !empty($branch_column)) {
            $extra_where = " AND {$branch_column} = %d";
            $extra_args[] = $branch_id;
        }

        $cache_key = 'count_' . md5($table . '_' . $where . $extra_where . $branch_id);
        $cached = Obydullah_ERP_Cache::get($cache_key, $table);
        if (false !== $cached) {
            return intval($cached);
        }

        $prepare_args = array_merge([1], $extra_args);
        $count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where} AND 1 = %d{$extra_where}", $prepare_args)));
        Obydullah_ERP_Cache::set($cache_key, $table, $count);
        return $count;
    }

    private function orerp_get_month_revenue($branch_id = 0)
    {
        global $wpdb;

        $month = intval(current_time('n'));
        $year  = intval(current_time('Y'));

        $branch_filter = '';
        $branch_args = [];
        if ($branch_id > 0) {
            $branch_filter = 'AND je.branch_id = %d';
            $branch_args[] = $branch_id;
        }

        $table = $wpdb->prefix . 'erp_journal_lines';
        $cache_key = 'month_revenue_' . $year . '_' . $month . '_' . $branch_id;
        $cached = Obydullah_ERP_Cache::get($cache_key, $table);
        if (false !== $cached) {
            return floatval($cached);
        }

        $prepare_args = array_merge([$month, $year], $branch_args);
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(jl.credit), 0)
                FROM {$wpdb->prefix}erp_journal_lines jl
                JOIN {$wpdb->prefix}erp_journal_entries je ON jl.entry_id = je.id
                JOIN {$wpdb->prefix}erp_accounts ja ON jl.account_id = ja.id
                WHERE ja.type = 'revenue'
                AND je.is_posted = 1
                AND MONTH(je.date) = %d
                AND YEAR(je.date) = %d
                {$branch_filter}",
                $prepare_args
            )
        );

        Obydullah_ERP_Cache::set($cache_key, $table, $result);
        return floatval($result);
    }

    private function orerp_get_month_expenses($branch_id = 0)
    {
        global $wpdb;

        $month = intval(current_time('n'));
        $year  = intval(current_time('Y'));

        $branch_filter = '';
        $branch_args = [];
        if ($branch_id > 0) {
            $branch_filter = 'AND je.branch_id = %d';
            $branch_args[] = $branch_id;
        }

        $table = $wpdb->prefix . 'erp_journal_lines';
        $cache_key = 'month_expenses_' . $year . '_' . $month . '_' . $branch_id;
        $cached = Obydullah_ERP_Cache::get($cache_key, $table);
        if (false !== $cached) {
            return floatval($cached);
        }

        $prepare_args = array_merge([$month, $year], $branch_args);
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(jl.debit), 0)
                FROM {$wpdb->prefix}erp_journal_lines jl
                JOIN {$wpdb->prefix}erp_journal_entries je ON jl.entry_id = je.id
                JOIN {$wpdb->prefix}erp_accounts ja ON jl.account_id = ja.id
                WHERE ja.type = 'expense'
                AND je.is_posted = 1
                AND MONTH(je.date) = %d
                AND YEAR(je.date) = %d
                {$branch_filter}",
                $prepare_args
            )
        );

        Obydullah_ERP_Cache::set($cache_key, $table, $result);
        return floatval($result);
    }

    private function orerp_render_recent_purchases($branch_id = 0)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'erp_purchase_orders';

        $branch_filter = '';
        $branch_args = [];
        if ($branch_id > 0) {
            $branch_filter = 'AND po.branch_id = %d';
            $branch_args[] = $branch_id;
        }

        $cache_key = 'recent_purchases_5_' . $branch_id;
        $cached = Obydullah_ERP_Cache::get($cache_key, $table);
        if (false !== $cached) {
            $orders = $cached;
        } else {
            if (!empty($branch_filter)) {
                $orders = $wpdb->get_results($wpdb->prepare(
                    "SELECT po.*, s.name as supplier_name
                    FROM {$wpdb->prefix}erp_purchase_orders po
                    LEFT JOIN {$wpdb->prefix}erp_suppliers s ON po.supplier_id = s.id
                    WHERE 1 = 1 {$branch_filter}
                    ORDER BY po.created_at DESC LIMIT 5",
                    $branch_args
                ));
            } else {
                $orders = $wpdb->get_results(
                    "SELECT po.*, s.name as supplier_name
                    FROM {$wpdb->prefix}erp_purchase_orders po
                    LEFT JOIN {$wpdb->prefix}erp_suppliers s ON po.supplier_id = s.id
                    ORDER BY po.created_at DESC LIMIT 5"
                );
            }
            Obydullah_ERP_Cache::set($cache_key, $table, $orders);
        }

        if (empty($orders)) {
            echo '<p class="orerp-empty">' . esc_html__('No purchase orders yet.', 'obydullah-restaurant-erp') . '</p>';
            return;
        }

        echo '<table class="orerp-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('PO Number', 'obydullah-restaurant-erp') . '</th>';
        echo '<th>' . esc_html__('Supplier', 'obydullah-restaurant-erp') . '</th>';
        echo '<th>' . esc_html__('Total', 'obydullah-restaurant-erp') . '</th>';
        echo '<th>' . esc_html__('Status', 'obydullah-restaurant-erp') . '</th>';
        echo '<th>' . esc_html__('Date', 'obydullah-restaurant-erp') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($orders as $order) {
            echo '<tr>';
            echo '<td>' . esc_html($order->po_number) . '</td>';
            echo '<td>' . esc_html($order->supplier_name ?? '-') . '</td>';
            echo '<td>' . esc_html(Obydullah_ERP_Helpers::orerp_format_currency($order->total)) . '</td>';
            echo '<td><span class="status-badge ' . esc_attr($order->status) . '">' . esc_html(ucfirst($order->status)) . '</span></td>';
            echo '<td>' . esc_html(Obydullah_ERP_Helpers::orerp_format_date($order->created_at)) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function orerp_render_recent_journal_entries($branch_id = 0)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'erp_journal_entries';

        $branch_filter = '';
        $branch_args = [];
        if ($branch_id > 0) {
            $branch_filter = 'AND branch_id = %d';
            $branch_args[] = $branch_id;
        }

        $cache_key = 'recent_journal_entries_5_' . $branch_id;
        $cached = Obydullah_ERP_Cache::get($cache_key, $table);
        if (false !== $cached) {
            $entries = $cached;
        } elseif (!empty($branch_args)) {
            $entries = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE 1 = 1 {$branch_filter} ORDER BY date DESC, id DESC LIMIT 5",
                $branch_args
            ));
        } else {
            $entries = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$table} WHERE 1 = %d ORDER BY date DESC, id DESC LIMIT 5", 1)
            );
        }
        if (false === $cached) {
            Obydullah_ERP_Cache::set($cache_key, $table, $entries);
        }

        if (empty($entries)) {
            echo '<p class="orerp-empty">' . esc_html__('No journal entries yet.', 'obydullah-restaurant-erp') . '</p>';
            return;
        }

        echo '<table class="orerp-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Entry #', 'obydullah-restaurant-erp') . '</th>';
        echo '<th>' . esc_html__('Date', 'obydullah-restaurant-erp') . '</th>';
        echo '<th>' . esc_html__('Description', 'obydullah-restaurant-erp') . '</th>';
        echo '<th>' . esc_html__('Status', 'obydullah-restaurant-erp') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($entries as $entry) {
            echo '<tr>';
            echo '<td>' . esc_html($entry->entry_number) . '</td>';
            echo '<td>' . esc_html(Obydullah_ERP_Helpers::orerp_format_date($entry->date)) . '</td>';
            echo '<td>' . esc_html($entry->description) . '</td>';
            echo '<td><span class="status-badge ' . ($entry->is_posted ? 'completed' : 'draft') . '">';
            echo esc_html($entry->is_posted ? __('Posted', 'obydullah-restaurant-erp') : __('Draft', 'obydullah-restaurant-erp'));
            echo '</span></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    public function orerp_ajax_get_dashboard_stats()
    {
        check_ajax_referer('orerp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }

        wp_send_json_success($this->orerp_get_dashboard_stats());
    }
}
