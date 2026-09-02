<?php
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL table names come from $wpdb->prefix and every value is bound via $wpdb->prepare() placeholders; direct queries are used for the ERP-specific tables that have no core caching API.
/**
 * Reports & Analytics
 *
 * Aggregator that delegates each report type to its dedicated class:
 * Sales, Inventory, Branch Comparison and Financial reports. Employee
 * performance is computed here.
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Reports
{
    public function __construct()
    {
        add_action('wp_ajax_orerp_get_sales_report', [$this, 'orerp_ajax_get_sales_report']);
        add_action('wp_ajax_orerp_get_inventory_report', [$this, 'orerp_ajax_get_inventory_report']);
        add_action('wp_ajax_orerp_get_financial_report', [$this, 'orerp_ajax_get_financial_report']);
        add_action('wp_ajax_orerp_get_branch_comparison', [$this, 'orerp_ajax_get_branch_comparison']);
        add_action('wp_ajax_orerp_get_employee_performance', [$this, 'orerp_ajax_get_employee_performance']);
    }

    public function orerp_render_page()
    {
        $tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'sales'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin GET parameter (navigation/filter), not a state-changing request.

        if (isset($_GET['print']) && $_GET['print'] === '1') { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin GET parameter (navigation/filter), not a state-changing request.
            $this->orerp_render_print_view($tab);
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Reports & Analytics', 'obydullah-restaurant-erp'); ?></h1>
            <div class="orerp-print-actions" style="margin:8px 0 12px;">
                <button type="button" id="orerp-print-report" class="button"><?php esc_html_e('Print / PDF', 'obydullah-restaurant-erp'); ?></button>
            </div>
            <hr class="wp-header-end">

            <div class="orerp-tabs">
                <a class="tab <?php echo $tab === 'sales' ? 'active' : 'orerp_'; ?>"
                   href="<?php echo esc_url(admin_url('admin.php?page=orerp-reports&tab=sales')); ?>">
                    <?php esc_html_e('Sales', 'obydullah-restaurant-erp'); ?>
                </a>
                <a class="tab <?php echo $tab === 'inventory' ? 'active' : 'orerp_'; ?>"
                   href="<?php echo esc_url(admin_url('admin.php?page=orerp-reports&tab=inventory')); ?>">
                    <?php esc_html_e('Inventory', 'obydullah-restaurant-erp'); ?>
                </a>
                <a class="tab <?php echo $tab === 'financial' ? 'active' : 'orerp_'; ?>"
                   href="<?php echo esc_url(admin_url('admin.php?page=orerp-reports&tab=financial')); ?>">
                    <?php esc_html_e('Financial', 'obydullah-restaurant-erp'); ?>
                </a>
                <a class="tab <?php echo $tab === 'branches' ? 'active' : 'orerp_'; ?>"
                   href="<?php echo esc_url(admin_url('admin.php?page=orerp-reports&tab=branches')); ?>">
                    <?php esc_html_e('Branch Comparison', 'obydullah-restaurant-erp'); ?>
                </a>
                <a class="tab <?php echo $tab === 'employees' ? 'active' : 'orerp_'; ?>"
                   href="<?php echo esc_url(admin_url('admin.php?page=orerp-reports&tab=employees')); ?>">
                    <?php esc_html_e('Employee Performance', 'obydullah-restaurant-erp'); ?>
                </a>
            </div>

            <div class="orerp-card">
                <div class="orerp-filters" style="margin-bottom:15px;">
                    <div class="filter-group">
                        <label><?php esc_html_e('From', 'obydullah-restaurant-erp'); ?></label>
                        <input type="date" id="report-date-from" class="regular-text"
                            value="<?php echo esc_attr(gmdate('Y-m-01')); ?>">
                    </div>
                    <div class="filter-group">
                        <label><?php esc_html_e('To', 'obydullah-restaurant-erp'); ?></label>
                        <input type="date" id="report-date-to" class="regular-text"
                            value="<?php echo esc_attr(gmdate('Y-m-d')); ?>">
                    </div>
                    <div class="filter-group">
                        <label><?php esc_html_e('Branch', 'obydullah-restaurant-erp'); ?></label>
                        <select id="report-branch-filter">
                            <option value=""><?php esc_html_e('All Branches', 'obydullah-restaurant-erp'); ?></option>
                            <?php $this->orerp_render_branch_options(0); ?>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="button" id="run-report" class="button button-primary"
                            data-report="<?php echo esc_attr($tab); ?>">
                            <?php esc_html_e('Generate Report', 'obydullah-restaurant-erp'); ?>
                        </button>
                    </div>
                </div>

                <div id="report-content">
                    <p class="description"><?php esc_html_e('Click "Generate Report" to load data.', 'obydullah-restaurant-erp'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    private function orerp_render_branch_options($selected = 0)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'erp_branches';

        $cache_key = 'branch_options_active';
        $cached = Obydullah_ERP_Cache::get($cache_key, $table);
        if (false !== $cached) {
            $branches = $cached;
        } else {
            $branches = $wpdb->get_results($wpdb->prepare("SELECT id, name FROM {$wpdb->prefix}erp_branches WHERE is_active = 1 AND 1 = %d ORDER BY name", 1));
            Obydullah_ERP_Cache::set($cache_key, $table, $branches);
        }

        foreach ($branches as $branch) {
            printf(
                '<option value="%d" %s>%s</option>',
                intval($branch->id),
                selected($selected, $branch->id, false),
                esc_html($branch->name)
            );
        }
    }

    /**
     * Print-friendly view rendered as a standalone HTML document.
     *
     * @param string $tab Report tab.
     * @return void
     */
    private function orerp_render_print_view($tab)
    {
        $template_map = [
            'sales'     => 'orerp-sales.php',
            'inventory' => 'orerp-inventory.php',
            'financial' => 'orerp-financial.php',
            'branches'  => 'orerp-branches.php',
            'employees' => 'orerp-employees.php',
        ];

        $template = $template_map[$tab] ?? 'orerp-sales.php';
        $path = ORERP_PATH . 'templates/reports/' . $template;

        if (!file_exists($path)) {
            return;
        }

        switch ($tab) {
            case 'inventory':
                $data = $this->orerp_get_inventory_report();
                break;
            case 'financial':
                $data = $this->orerp_get_financial_report();
                break;
            case 'branches':
                $data = $this->orerp_get_branch_comparison();
                break;
            case 'employees':
                $data = $this->orerp_get_employee_performance();
                break;
            case 'sales':
            default:
                $data = $this->orerp_get_sales_report();
                break;
        }

        include $path;
        exit;
    }

    private function orerp_get_date_range()
    {
        $from = sanitize_text_field(wp_unslash($_GET['date_from'] ?? 'orerp_')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin GET parameter (navigation/filter), not a state-changing request.
        $to   = sanitize_text_field(wp_unslash($_GET['date_to'] ?? 'orerp_')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin GET parameter (navigation/filter), not a state-changing request.
        return [$from, $to];
    }

    private function orerp_get_branch_filter()
    {
        return intval($_GET['branch_id'] ?? 0); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin GET parameter (navigation/filter), not a state-changing request.
    }

    public function orerp_get_sales_report()
    {
        list($from, $to) = $this->orerp_get_date_range();
        $sales = new Obydullah_ERP_Sales_Reports();
        return $sales->orerp_get_sales_report($from, $to, $this->orerp_get_branch_filter());
    }

    public function orerp_get_inventory_report()
    {
        $inventory = new Obydullah_ERP_Inventory_Reports();
        return $inventory->orerp_get_inventory_report($this->orerp_get_branch_filter());
    }

    public function orerp_get_financial_report()
    {
        list($from, $to) = $this->orerp_get_date_range();

        $financial = new Obydullah_ERP_Financial_Reports();
        $accounts = $financial->orerp_get_account_breakdown($from, $to);

        $revenue = [];
        $expenses = [];
        $assets = [];
        $liabilities = [];
        $equity = [];

        foreach ($accounts as $acc) {
            switch ($acc['type']) {
                case 'revenue':
                    $revenue[] = $acc;
                    break;
                case 'expense':
                    $expenses[] = $acc;
                    break;
                case 'asset':
                    $assets[] = $acc;
                    break;
                case 'liability':
                    $liabilities[] = $acc;
                    break;
                case 'equity':
                    $equity[] = $acc;
                    break;
            }
        }

        $total_revenue = 0;
        foreach ($revenue as $r) {
            $total_revenue += floatval($r['total_credit']) - floatval($r['total_debit']);
        }

        $total_expenses = 0;
        foreach ($expenses as $e) {
            $total_expenses += floatval($e['total_debit']) - floatval($e['total_credit']);
        }

        $period_from = Obydullah_ERP_Helpers::orerp_is_valid_date($from) ? $from : gmdate('Y-m-01');
        $period_to   = Obydullah_ERP_Helpers::orerp_is_valid_date($to) ? $to : gmdate('Y-m-d');

        return [
            'period'         => ['from' => $period_from, 'to' => $period_to],
            'revenue'        => $revenue,
            'total_revenue'  => $total_revenue,
            'expenses'       => $expenses,
            'total_expenses' => $total_expenses,
            'net_income'     => $total_revenue - $total_expenses,
            'assets'         => $assets,
            'liabilities'    => $liabilities,
            'equity'         => $equity,
        ];
    }

    public function orerp_get_branch_comparison()
    {
        list($from, $to) = $this->orerp_get_date_range();
        $branches = new Obydullah_ERP_Branch_Reports();
        return $branches->orerp_get_branch_comparison($from, $to);
    }

    public function orerp_get_employee_performance()
    {
        global $wpdb;

        list($from, $to) = $this->orerp_get_date_range();
        $branch_id = $this->orerp_get_branch_filter();

        if ($branch_id > 0) {
            $cache_key = 'employee_performance_list_branch_' . $branch_id;
            $emp_table = $wpdb->prefix . 'erp_employees';
            $cached = Obydullah_ERP_Cache::get($cache_key, $emp_table);
            if (false !== $cached) {
                $employees = $cached;
            } else {
                $employees = $wpdb->get_results($wpdb->prepare(
                    "SELECT e.id, e.employee_code, e.branch_id, u.display_name AS display_name, b.name AS branch_name
                    FROM {$wpdb->prefix}erp_employees e
                    LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
                    LEFT JOIN {$wpdb->prefix}erp_branches b ON e.branch_id = b.id
                    WHERE e.is_active = 1 AND e.branch_id = %d
                    ORDER BY display_name ASC",
                    $branch_id
                )) ?: [];
                Obydullah_ERP_Cache::set($cache_key, $emp_table, $employees);
            }
        } else {
            $cache_key = 'employee_performance_list_all';
            $emp_table = $wpdb->prefix . 'erp_employees';
            $cached = Obydullah_ERP_Cache::get($cache_key, $emp_table);
            if (false !== $cached) {
                $employees = $cached;
            } else {
                $employees = $wpdb->get_results($wpdb->prepare(
                    "SELECT e.id, e.employee_code, e.branch_id, u.display_name AS display_name, b.name AS branch_name
                    FROM {$wpdb->prefix}erp_employees e
                    LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
                    LEFT JOIN {$wpdb->prefix}erp_branches b ON e.branch_id = b.id
                    WHERE e.is_active = 1 AND 1 = %d
                    ORDER BY display_name ASC",
                    1
                )) ?: [];
                Obydullah_ERP_Cache::set($cache_key, $emp_table, $employees);
            }
        }

        $results = [];

        $attendance_table = $wpdb->prefix . 'erp_attendance';

        foreach ($employees as $emp) {
            $cache_key = 'attendance_stats_' . $emp->id . '_' . $from . '_' . $to;
            $cached = Obydullah_ERP_Cache::get($cache_key, $attendance_table);
            if (false !== $cached) {
                $attend = $cached;
            } else {
                $attend = $wpdb->get_row($wpdb->prepare(
                    "SELECT COUNT(*) as days_worked,
                            COALESCE(SUM(TIMESTAMPDIFF(MINUTE, clock_in, COALESCE(clock_out, NOW()))), 0) as total_minutes
                    FROM {$wpdb->prefix}erp_attendance
                    WHERE employee_id = %d AND DATE(clock_in) BETWEEN %s AND %s",
                    $emp->id, $from, $to
                ));
                Obydullah_ERP_Cache::set($cache_key, $attendance_table, $attend);
            }

            $prep_table = $wpdb->prefix . 'erp_prep_tracking';

            $cache_key = 'prep_tracking_stats_' . $emp->id . '_' . $from . '_' . $to;
            $cached = Obydullah_ERP_Cache::get($cache_key, $prep_table);
            if (false !== $cached) {
                $prep = $cached;
            } else {
                $prep = $wpdb->get_row($wpdb->prepare(
                    "SELECT COUNT(*) as tasks_completed,
                            COALESCE(AVG(actual_time_minutes), 0) as avg_time
                    FROM {$wpdb->prefix}erp_prep_tracking
                    WHERE employee_id = %d AND completed_at IS NOT NULL
                    AND DATE(started_at) BETWEEN %s AND %s",
                    $emp->id, $from, $to
                ));
                Obydullah_ERP_Cache::set($cache_key, $prep_table, $prep);
            }

            $name = trim($emp->display_name);
            if (empty($name)) {
                $name = $emp->employee_code;
            }

            $results[] = [
                'employee_id'     => $emp->id,
                'name'            => $name,
                'branch'          => $emp->branch_name,
                'days_worked'     => intval($attend->days_worked ?? 0),
                'total_hours'     => round(floatval($attend->total_minutes ?? 0) / 60, 1),
                'tasks_completed' => intval($prep->tasks_completed ?? 0),
                'avg_time_min'    => round(floatval($prep->avg_time ?? 0), 1),
            ];
        }

        return ['period' => ['from' => $from, 'to' => $to], 'employees' => $results];
    }

    // --- AJAX ---

    private function orerp_guard_report_access()
    {
        check_ajax_referer('orerp_reports', 'nonce');
        if (!Obydullah_ERP_Helpers::can('orerp_reports')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }
    }

    public function orerp_ajax_get_sales_report()
    {
        $this->orerp_guard_report_access();
        wp_send_json_success($this->orerp_get_sales_report());
    }

    public function orerp_ajax_get_inventory_report()
    {
        $this->orerp_guard_report_access();
        wp_send_json_success($this->orerp_get_inventory_report());
    }

    public function orerp_ajax_get_financial_report()
    {
        $this->orerp_guard_report_access();
        wp_send_json_success($this->orerp_get_financial_report());
    }

    public function orerp_ajax_get_branch_comparison()
    {
        $this->orerp_guard_report_access();
        wp_send_json_success($this->orerp_get_branch_comparison());
    }

    public function orerp_ajax_get_employee_performance()
    {
        $this->orerp_guard_report_access();
        wp_send_json_success($this->orerp_get_employee_performance());
    }
}
