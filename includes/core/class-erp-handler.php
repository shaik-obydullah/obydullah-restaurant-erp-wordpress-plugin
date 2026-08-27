<?php
/**
 * Main Plugin Handler
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$orerp_core_files = [
    'class-erp-helpers.php',
    'class-erp-integration.php',
];

foreach ($orerp_core_files as $orerp_file) {
    $path = ORERP_PATH . 'includes/core/' . $orerp_file;
    if (file_exists($path)) {
        require_once $path;
    }
}

if (!class_exists('Obydullah_ERP_Handler')) {
    class Obydullah_ERP_Handler
    {
        private static $instance = null;

        public $helpers;
        public $integration;

        public $branches;
        public $branch_transfers;
        public $employees;
        public $attendance;
        public $roles;
        public $suppliers;
        public $purchases;
        public $chart_accounts;
        public $journal;
        public $ledger;
        public $financial_reports;
        public $tax_reports;
        public $kitchen_display;
        public $order_workflow;
        public $recipes;
        public $reports;
        public $sales_reports;
        public $inventory_reports;
        public $branch_reports;
        public $dashboard_reports;

        public static function instance()
        {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct()
        {
            $this->helpers = new Obydullah_ERP_Helpers();
            $this->integration = new Obydullah_ERP_Integration();

            $this->load_modules();

            add_action('admin_menu', [$this, 'register_admin_menu'], 99);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        }

        private function load_modules()
        {
            $module_files = [
                'branches/class-branches'           => 'modules/branches/class-branches.php',
                'branches/class-branch-transfers'   => 'modules/branches/class-branch-transfers.php',
                'employees/class-employees'         => 'modules/employees/class-employees.php',
                'employees/class-attendance'        => 'modules/employees/class-attendance.php',
                'employees/class-roles'             => 'modules/employees/class-roles.php',
                'suppliers/class-suppliers'         => 'modules/suppliers/class-suppliers.php',
                'purchases/class-purchase-orders'   => 'modules/purchases/class-purchase-orders.php',
                'accounting/class-chart-accounts'   => 'modules/accounting/class-chart-accounts.php',
                'accounting/class-financial-reports' => 'modules/accounting/class-financial-reports.php',
                'accounting/class-journal-entries'  => 'modules/accounting/class-journal-entries.php',
                'accounting/class-ledger'           => 'modules/accounting/class-ledger.php',
                'accounting/class-tax-reports'      => 'modules/accounting/class-tax-reports.php',
                'kitchen/class-kitchen-display'     => 'modules/kitchen/class-kitchen-display.php',
                'kitchen/class-order-workflow'      => 'modules/kitchen/class-order-workflow.php',
                'kitchen/class-recipes'             => 'modules/kitchen/class-recipes.php',
                'reports/class-dashboard-reports'   => 'modules/reports/class-dashboard-reports.php',
                'reports/class-sales-reports'       => 'modules/reports/class-sales-reports.php',
                'reports/class-inventory-reports'   => 'modules/reports/class-inventory-reports.php',
                'reports/class-branch-reports'      => 'modules/reports/class-branch-reports.php',
                'reports/class-reports'             => 'modules/reports/class-reports.php',
            ];

            foreach ($module_files as $key => $file) {
                $path = ORERP_PATH . 'includes/' . $file;
                if (file_exists($path)) {
                    require_once $path;
                    $class_name = $this->get_class_name_from_file($path);
                    if ($class_name && class_exists($class_name)) {
                        $prop = $this->get_property_name($key);
                        $this->$prop = new $class_name();
                    }
                }
            }
        }

        private function get_class_name_from_file($path)
        {
            $content = file_get_contents($path);
            if (preg_match('/class\s+(\w+)/', $content, $matches)) {
                return $matches[1];
            }
            return null;
        }

        private function get_property_name($key)
        {
            $parts = explode('/', $key);
            $last = end($parts);
            $last = str_replace('class-', '', $last);
            $last = str_replace('-', '_', $last);
            return $last;
        }

        public function register_admin_menu()
        {
            add_menu_page(
                __('Restaurant ERP', 'obydullah-restaurant-erp'),
                __('Restaurant ERP', 'obydullah-restaurant-erp'),
                'manage_options',
                'orerp-dashboard',
                [$this, 'render_dashboard'],
                'dashicons-building',
                26
            );

            add_submenu_page(
                'orerp-dashboard',
                __('Dashboard', 'obydullah-restaurant-erp'),
                __('Dashboard', 'obydullah-restaurant-erp'),
                'manage_options',
                'orerp-dashboard',
                [$this, 'render_dashboard']
            );

            $submenus = [
                'orerp-branches' => [
                    'label'   => __('Branches', 'obydullah-restaurant-erp'),
                    'class'   => 'branches',
                    'method'  => 'render_page',
                ],
                'orerp-employees' => [
                    'label'   => __('Employees', 'obydullah-restaurant-erp'),
                    'class'   => 'employees',
                    'method'  => 'render_page',
                ],
                'orerp-attendance' => [
                    'label'   => __('Attendance & Shifts', 'obydullah-restaurant-erp'),
                    'class'   => 'attendance',
                    'method'  => 'render_page',
                ],
                'orerp-roles' => [
                    'label'   => __('Roles & Permissions', 'obydullah-restaurant-erp'),
                    'class'   => 'roles',
                    'method'  => 'render_page',
                ],
                'orerp-suppliers' => [
                    'label'   => __('Suppliers', 'obydullah-restaurant-erp'),
                    'class'   => 'suppliers',
                    'method'  => 'render_page',
                ],
                'orerp-purchases' => [
                    'label'   => __('Purchases', 'obydullah-restaurant-erp'),
                    'class'   => 'purchases',
                    'method'  => 'render_page',
                ],
                'orerp-accounting' => [
                    'label'   => __('Accounting', 'obydullah-restaurant-erp'),
                    'class'   => 'chart_accounts',
                    'method'  => 'render_page',
                ],
                'orerp-journal' => [
                    'label'   => __('Journal Entries', 'obydullah-restaurant-erp'),
                    'class'   => 'journal',
                    'method'  => 'render_page',
                ],
                'orerp-ledger' => [
                    'label'   => __('General Ledger', 'obydullah-restaurant-erp'),
                    'class'   => 'ledger',
                    'method'  => 'render_page',
                ],
                'orerp-tax-reports' => [
                    'label'   => __('Tax Reports', 'obydullah-restaurant-erp'),
                    'class'   => 'tax_reports',
                    'method'  => 'render_page',
                ],
                'orerp-kitchen' => [
                    'label'   => __('Kitchen', 'obydullah-restaurant-erp'),
                    'class'   => 'kitchen_display',
                    'method'  => 'render_page',
                ],
                'orerp-recipes' => [
                    'label'   => __('Recipes', 'obydullah-restaurant-erp'),
                    'class'   => 'recipes',
                    'method'  => 'render_page',
                ],
                'orerp-reports' => [
                    'label'   => __('Reports', 'obydullah-restaurant-erp'),
                    'class'   => 'reports',
                    'method'  => 'render_page',
                ],
                'orerp-settings' => [
                    'label'   => __('Settings', 'obydullah-restaurant-erp'),
                    'class'   => 'Obydullah_ERP_Helpers',
                    'method'  => 'render_settings_page',
                    'static'  => true,
                ],
            ];

            foreach ($submenus as $slug => $data) {
                $class_prop = $data['class'];
                $method = $data['method'];
                $is_static = isset($data['static']) && $data['static'];

                if ($is_static) {
                    $callback = [$data['class'], $method];
                } else {
                    $obj = $this->$class_prop ?? null;
                    $callback = $obj ? [$obj, $method] : [$this, 'render_empty'];
                }

                add_submenu_page(
                    'orerp-dashboard',
                    $data['label'],
                    $data['label'],
                    'manage_options',
                    $slug,
                    $callback
                );
            }
        }

        public function render_dashboard()
        {
            if ($this->dashboard_reports) {
                $this->dashboard_reports->render_dashboard();
            } else {
                $this->render_empty();
            }
        }

        public function render_empty()
        {
            echo '<div class="wrap"><h1>' . esc_html__('Module not loaded', 'obydullah-restaurant-erp') . '</h1></div>';
        }

        public function enqueue_admin_scripts($hook)
        {
            $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

            if (strpos($hook, 'orerp-') === false && strpos($current_page, 'orerp-') === false) {
                return;
            }

            wp_enqueue_style(
                'orerp-admin-css',
                ORERP_URL . 'assets/css/erp-admin.css',
                [],
                ORERP_VERSION
            );

            wp_enqueue_script(
                'orerp-admin-js',
                ORERP_URL . 'assets/js/erp-admin.js',
                ['jquery'],
                ORERP_VERSION,
                true
            );

            wp_localize_script('orerp-admin-js', 'orerpAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('orerp_admin_nonce'),
                'strings' => [
                    'confirm'   => __('Are you sure?', 'obydullah-restaurant-erp'),
                    'success'   => __('Operation completed successfully.', 'obydullah-restaurant-erp'),
                    'error'     => __('An error occurred.', 'obydullah-restaurant-erp'),
                    'loading'   => __('Loading...', 'obydullah-restaurant-erp'),
                    'noData'    => __('No data found.', 'obydullah-restaurant-erp'),
                ],
            ]);

            $module_js = [
                'orerp-branches'      => ['file' => 'branches.js', 'object' => 'orerpBranches', 'nonce_action' => 'orerp_branches'],
                'orerp-employees'     => ['file' => 'employees.js', 'object' => 'orerpEmployees', 'nonce_action' => 'orerp_employees'],
                'orerp-attendance'    => ['file' => 'attendance.js', 'object' => 'orerpEmployees', 'nonce_action' => 'orerp_employees'],
                'orerp-roles'         => ['file' => 'roles.js', 'object' => 'orerpRoles', 'nonce_action' => 'orerp_roles'],
                'orerp-suppliers'     => ['file' => 'suppliers.js', 'object' => 'orerpSuppliers', 'nonce_action' => 'orerp_suppliers'],
                'orerp-purchases'     => ['file' => 'purchases.js', 'object' => 'orerpPurchases', 'nonce_action' => 'orerp_purchases'],
                'orerp-accounting'    => ['file' => 'accounting.js', 'object' => 'orerpAccounting', 'nonce_action' => 'orerp_accounting'],
                'orerp-journal'       => ['file' => 'accounting.js', 'object' => 'orerpJournal', 'nonce_action' => 'orerp_journal'],
                'orerp-ledger'        => ['file' => 'ledger.js', 'object' => 'orerpLedger', 'nonce_action' => 'orerp_ledger'],
                'orerp-tax-reports'   => ['file' => 'tax-reports.js', 'object' => 'orerpTaxReports', 'nonce_action' => 'orerp_tax_reports'],
                'orerp-kitchen'       => ['file' => 'kitchen.js', 'object' => 'orerpKitchen', 'nonce_action' => 'orerp_kitchen'],
                'orerp-recipes'       => ['file' => 'kitchen.js', 'object' => 'orerpRecipes', 'nonce_action' => 'orerp_recipes'],
                'orerp-reports'       => ['file' => 'reports.js', 'object' => 'orerpReports', 'nonce_action' => 'orerp_reports'],
            ];

            if (isset($module_js[$current_page])) {
                $js = $module_js[$current_page];

                wp_enqueue_script(
                    'orerp-' . $current_page . '-js',
                    ORERP_URL . 'assets/js/' . $js['file'],
                    ['jquery', 'orerp-admin-js'],
                    ORERP_VERSION,
                    ['in_footer' => true, 'strategy' => 'defer']
                );

                wp_localize_script(
                    'orerp-' . $current_page . '-js',
                    $js['object'],
                    [
                        'ajaxUrl' => admin_url('admin-ajax.php'),
                        'nonce'   => wp_create_nonce($js['nonce_action']),
                    ]
                );
            }
        }
    }
}
