<?php
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
        add_action('wp_ajax_orerp_get_sales_report', [$this, 'ajax_get_sales_report']);
        add_action('wp_ajax_orerp_get_inventory_report', [$this, 'ajax_get_inventory_report']);
        add_action('wp_ajax_orerp_get_financial_report', [$this, 'ajax_get_financial_report']);
        add_action('wp_ajax_orerp_get_branch_comparison', [$this, 'ajax_get_branch_comparison']);
        add_action('wp_ajax_orerp_get_employee_performance', [$this, 'ajax_get_employee_performance']);
    }

    public function render_page()
    {
        $tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'sales';

        if (isset($_GET['print']) && $_GET['print'] === '1') {
            $this->render_print_view($tab);
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
                <a class="tab <?php echo $tab === 'sales' ? 'active' : ''; ?>"
                   href="<?php echo esc_url(admin_url('admin.php?page=orerp-reports&tab=sales')); ?>">
                    <?php esc_html_e('Sales', 'obydullah-restaurant-erp'); ?>
                </a>
                <a class="tab <?php echo $tab === 'inventory' ? 'active' : ''; ?>"
                   href="<?php echo esc_url(admin_url('admin.php?page=orerp-reports&tab=inventory')); ?>">
                    <?php esc_html_e('Inventory', 'obydullah-restaurant-erp'); ?>
                </a>
                <a class="tab <?php echo $tab === 'financial' ? 'active' : ''; ?>"
                   href="<?php echo esc_url(admin_url('admin.php?page=orerp-reports&tab=financial')); ?>">
                    <?php esc_html_e('Financial', 'obydullah-restaurant-erp'); ?>
                </a>
                <a class="tab <?php echo $tab === 'branches' ? 'active' : ''; ?>"
                   href="<?php echo esc_url(admin_url('admin.php?page=orerp-reports&tab=branches')); ?>">
                    <?php esc_html_e('Branch Comparison', 'obydullah-restaurant-erp'); ?>
                </a>
                <a class="tab <?php echo $tab === 'employees' ? 'active' : ''; ?>"
                   href="<?php echo esc_url(admin_url('admin.php?page=orerp-reports&tab=employees')); ?>">
                    <?php esc_html_e('Employee Performance', 'obydullah-restaurant-erp'); ?>
                </a>
            </div>

            <div class="orerp-card">
                <div class="orerp-filters" style="margin-bottom:15px;">
                    <div class="filter-group">
                        <label><?php esc_html_e('From', 'obydullah-restaurant-erp'); ?></label>
                        <input type="date" id="report-date-from" class="regular-text"
                            value="<?php echo esc_attr(date('Y-m-01')); ?>">
                    </div>
                    <div class="filter-group">
                        <label><?php esc_html_e('To', 'obydullah-restaurant-erp'); ?></label>
                        <input type="date" id="report-date-to" class="regular-text"
                            value="<?php echo esc_attr(date('Y-m-d')); ?>">
                    </div>
                    <div class="filter-group">
                        <label><?php esc_html_e('Branch', 'obydullah-restaurant-erp'); ?></label>
                        <select id="report-branch-filter">
                            <option value=""><?php esc_html_e('All Branches', 'obydullah-restaurant-erp'); ?></option>
                            <?php $this->render_branch_options(0); ?>
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

    private function render_branch_options($selected = 0)
    {
        global $wpdb;
        $branches = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}erp_branches WHERE is_active = 1 ORDER BY name");

        foreach ($branches as $branch) {
            printf(
                '<option value="%d" %s>%s</option>',
                $branch->id,
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
    private function render_print_view($tab)
    {
        $template_map = [
            'sales'     => 'sales.php',
            'inventory' => 'inventory.php',
            'financial' => 'financial.php',
            'branches'  => 'branches.php',
            'employees' => 'employees.php',
        ];

        $template = $template_map[$tab] ?? 'sales.php';
        $path = ORERP_PATH . 'templates/reports/' . $template;

        if (!file_exists($path)) {
            return;
        }

        switch ($tab) {
            case 'inventory':
                $data = $this->get_inventory_report();
                break;
            case 'financial':
                $data = $this->get_financial_report();
                break;
            case 'branches':
                $data = $this->get_branch_comparison();
                break;
            case 'employees':
                $data = $this->get_employee_performance();
                break;
            case 'sales':
            default:
                $data = $this->get_sales_report();
                break;
        }

        include $path;
        exit;
    }

    private function get_date_range()
    {
        $from = sanitize_text_field(wp_unslash($_GET['date_from'] ?? ''));
        $to   = sanitize_text_field(wp_unslash($_GET['date_to'] ?? ''));
        return [$from, $to];
    }

    private function get_branch_filter()
    {
        return intval($_GET['branch_id'] ?? 0);
    }

    public function get_sales_report()
    {
        list($from, $to) = $this->get_date_range();
        $sales = new Obydullah_ERP_Sales_Reports();
        return $sales->get_sales_report($from, $to, $this->get_branch_filter());
    }

    public function get_inventory_report()
    {
        $inventory = new Obydullah_ERP_Inventory_Reports();
        return $inventory->get_inventory_report($this->get_branch_filter());
    }

    public function get_financial_report()
    {
        list($from, $to) = $this->get_date_range();

        $financial = new Obydullah_ERP_Financial_Reports();
        $accounts = $financial->get_account_breakdown($from, $to);

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

        $period_from = Obydullah_ERP_Helpers::is_valid_date($from) ? $from : date('Y-m-01');
        $period_to   = Obydullah_ERP_Helpers::is_valid_date($to) ? $to : date('Y-m-d');

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

    public function get_branch_comparison()
    {
        list($from, $to) = $this->get_date_range();
        $branches = new Obydullah_ERP_Branch_Reports();
        return $branches->get_branch_comparison($from, $to);
    }

    public function get_employee_performance()
    {
        global $wpdb;

        list($from, $to) = $this->get_date_range();
        $branch_id = $this->get_branch_filter();

        $emp_table    = $wpdb->prefix . 'erp_employees';
        $attend_table = $wpdb->prefix . 'erp_attendance';
        $prep_table   = $wpdb->prefix . 'erp_prep_tracking';

        $where_branch = $branch_id > 0 ? $wpdb->prepare('AND e.branch_id = %d', $branch_id) : '';

        $query = "SELECT e.id, e.employee_code, e.branch_id, u.display_name AS display_name, b.name AS branch_name
            FROM {$emp_table} e
            LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
            LEFT JOIN {$wpdb->prefix}erp_branches b ON e.branch_id = b.id
            WHERE e.is_active = 1 {$where_branch}
            ORDER BY display_name ASC";

        $query = $where_branch ? $wpdb->prepare($query, $branch_id) : $query;
        $employees = $wpdb->get_results($query) ?: [];

        $results = [];

        foreach ($employees as $emp) {
            $attend = $wpdb->get_row($wpdb->prepare(
                "SELECT COUNT(*) as days_worked,
                        COALESCE(SUM(TIMESTAMPDIFF(MINUTE, clock_in, COALESCE(clock_out, NOW()))), 0) as total_minutes
                FROM {$attend_table}
                WHERE employee_id = %d AND DATE(clock_in) BETWEEN %s AND %s",
                $emp->id, $from, $to
            ));

            $prep = $wpdb->get_row($wpdb->prepare(
                "SELECT COUNT(*) as tasks_completed,
                        COALESCE(AVG(actual_time_minutes), 0) as avg_time
                FROM {$prep_table}
                WHERE employee_id = %d AND completed_at IS NOT NULL
                AND DATE(started_at) BETWEEN %s AND %s",
                $emp->id, $from, $to
            ));

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

    private function guard_report_access()
    {
        check_ajax_referer('orerp_reports', 'nonce');
        if (!Obydullah_ERP_Helpers::can('orerp_reports')) {
            wp_send_json_error(__('Insufficient permissions', 'obydullah-restaurant-erp'));
        }
    }

    public function ajax_get_sales_report()
    {
        $this->guard_report_access();
        wp_send_json_success($this->get_sales_report());
    }

    public function ajax_get_inventory_report()
    {
        $this->guard_report_access();
        wp_send_json_success($this->get_inventory_report());
    }

    public function ajax_get_financial_report()
    {
        $this->guard_report_access();
        wp_send_json_success($this->get_financial_report());
    }

    public function ajax_get_branch_comparison()
    {
        $this->guard_report_access();
        wp_send_json_success($this->get_branch_comparison());
    }

    public function ajax_get_employee_performance()
    {
        $this->guard_report_access();
        wp_send_json_success($this->get_employee_performance());
    }
}
