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
    'class-orerp-helpers.php',
    'class-orerp-integration.php',
    'class-orerp-cache.php',
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

            $this->orerp_load_modules();

            add_action('admin_menu', [$this, 'orerp_register_admin_menu'], 99);
            add_action('admin_enqueue_scripts', [$this, 'orerp_enqueue_admin_scripts']);
        }

        private function orerp_load_modules()
        {
            $module_files = [
                'branches'           => 'modules/branches/class-orerp-branches.php',
                'branch_transfers'   => 'modules/branches/class-orerp-branch-transfers.php',
                'employees'          => 'modules/employees/class-orerp-employees.php',
                'attendance'         => 'modules/employees/class-orerp-attendance.php',
                'roles'              => 'modules/employees/class-orerp-roles.php',
                'suppliers'          => 'modules/suppliers/class-orerp-suppliers.php',
                'purchases'          => 'modules/purchases/class-orerp-purchase-orders.php',
                'chart_accounts'     => 'modules/accounting/class-orerp-chart-accounts.php',
                'financial_reports'  => 'modules/accounting/class-orerp-financial-reports.php',
                'journal'            => 'modules/accounting/class-orerp-journal-entries.php',
                'ledger'             => 'modules/accounting/class-orerp-ledger.php',
                'tax_reports'        => 'modules/accounting/class-orerp-tax-reports.php',
                'kitchen_display'    => 'modules/kitchen/class-orerp-kitchen-display.php',
                'order_workflow'     => 'modules/kitchen/class-orerp-order-workflow.php',
                'recipes'            => 'modules/kitchen/class-orerp-recipes.php',
                'dashboard_reports'  => 'modules/reports/class-orerp-dashboard-reports.php',
                'sales_reports'      => 'modules/reports/class-orerp-sales-reports.php',
                'inventory_reports'  => 'modules/reports/class-orerp-inventory-reports.php',
                'branch_reports'     => 'modules/reports/class-orerp-branch-reports.php',
                'reports'            => 'modules/reports/class-orerp-reports.php',
            ];

            foreach ($module_files as $prop => $file) {
                $path = ORERP_PATH . 'includes/' . $file;
                if (file_exists($path)) {
                    require_once $path;
                    $class_name = $this->orerp_get_class_name_from_file($path);
                    if ($class_name && class_exists($class_name)) {
                        $this->$prop = new $class_name();
                    }
                }
            }
        }

        private function orerp_get_class_name_from_file($path)
        {
            $content = file_get_contents($path);
            if (preg_match('/class\s+(\w+)/', $content, $matches)) {
                return $matches[1];
            }
            return null;
        }

        private function orerp_get_property_name($key)
        {
            $parts = explode('/', $key);
            $last = end($parts);
            $last = str_replace('class-', '', $last);
            $last = str_replace('-', '_', $last);
            return $last;
        }

        public function orerp_register_admin_menu()
        {
            add_menu_page(
                __('Restaurant ERP', 'obydullah-restaurant-erp'),
                __('Restaurant ERP', 'obydullah-restaurant-erp'),
                'manage_options',
                'orerp-dashboard',
                [$this, 'orerp_render_dashboard'],
                'dashicons-building',
                26
            );

            add_submenu_page(
                'orerp-dashboard',
                __('Dashboard', 'obydullah-restaurant-erp'),
                __('Dashboard', 'obydullah-restaurant-erp'),
                'manage_options',
                'orerp-dashboard',
                [$this, 'orerp_render_dashboard']
            );

            $submenus = [
                'orerp-branches' => [
                    'label'   => __('Branches', 'obydullah-restaurant-erp'),
                    'class'   => 'branches',
                    'method'  => 'orerp_render_page',
                ],
                'orerp-employees' => [
                    'label'   => __('Employees', 'obydullah-restaurant-erp'),
                    'class'   => 'employees',
                    'method'  => 'orerp_render_page',
                ],
                'orerp-attendance' => [
                    'label'   => __('Attendance & Shifts', 'obydullah-restaurant-erp'),
                    'class'   => 'attendance',
                    'method'  => 'orerp_render_page',
                ],
                'orerp-roles' => [
                    'label'   => __('Roles & Permissions', 'obydullah-restaurant-erp'),
                    'class'   => 'roles',
                    'method'  => 'orerp_render_page',
                ],
                'orerp-suppliers' => [
                    'label'   => __('Suppliers', 'obydullah-restaurant-erp'),
                    'class'   => 'suppliers',
                    'method'  => 'orerp_render_page',
                ],
                'orerp-purchases' => [
                    'label'   => __('Purchases', 'obydullah-restaurant-erp'),
                    'class'   => 'purchases',
                    'method'  => 'orerp_render_page',
                ],
                'orerp-accounting' => [
                    'label'   => __('Accounting', 'obydullah-restaurant-erp'),
                    'class'   => 'chart_accounts',
                    'method'  => 'orerp_render_page',
                ],
                'orerp-journal' => [
                    'label'   => __('Journal Entries', 'obydullah-restaurant-erp'),
                    'class'   => 'journal',
                    'method'  => 'orerp_render_page',
                ],
                'orerp-ledger' => [
                    'label'   => __('General Ledger', 'obydullah-restaurant-erp'),
                    'class'   => 'ledger',
                    'method'  => 'orerp_render_page',
                ],
                'orerp-tax-reports' => [
                    'label'   => __('Tax Reports', 'obydullah-restaurant-erp'),
                    'class'   => 'tax_reports',
                    'method'  => 'orerp_render_page',
                ],
                'orerp-kitchen' => [
                    'label'   => __('Kitchen', 'obydullah-restaurant-erp'),
                    'class'   => 'kitchen_display',
                    'method'  => 'orerp_render_page',
                ],
                'orerp-recipes' => [
                    'label'   => __('Recipes', 'obydullah-restaurant-erp'),
                    'class'   => 'recipes',
                    'method'  => 'orerp_render_page',
                ],
                'orerp-reports' => [
                    'label'   => __('Reports', 'obydullah-restaurant-erp'),
                    'class'   => 'reports',
                    'method'  => 'orerp_render_page',
                ],
                'orerp-settings' => [
                    'label'   => __('Settings', 'obydullah-restaurant-erp'),
                    'class'   => 'Obydullah_ERP_Helpers',
                    'method'  => 'orerp_render_settings_page',
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
                    $callback = $obj ? [$obj, $method] : [$this, 'orerp_render_empty'];
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

        public function orerp_render_dashboard()
        {
            if ($this->dashboard_reports) {
                $this->dashboard_reports->orerp_render_dashboard();
            } else {
                $this->orerp_render_empty();
            }
        }

        public function orerp_render_empty()
        {
            echo '<div class="wrap"><h1>' . esc_html__('Module not loaded', 'obydullah-restaurant-erp') . '</h1></div>';
        }

        public function orerp_enqueue_admin_scripts($hook)
        {
            $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : 'orerp_'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin GET parameter (navigation/filter), not a state-changing request.

            if (strpos($hook, 'orerp-') === false && strpos($current_page, 'orerp-') === false) {
                return;
            }

            wp_enqueue_style(
                'orerp-admin-css',
                ORERP_URL . 'assets/css/orerp-admin.css',
                [],
                ORERP_VERSION
            );

            wp_enqueue_script(
                'orerp-admin-js',
                ORERP_URL . 'assets/js/orerp-erp-admin.js',
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
                'orerp-branches'      => ['file' => 'orerp-branches.js', 'object' => 'orerpBranches', 'nonce_action' => 'orerp_branches'],
                'orerp-employees'     => ['file' => 'orerp-employees.js', 'object' => 'orerpEmployees', 'nonce_action' => 'orerp_employees'],
                'orerp-attendance'    => ['file' => 'orerp-attendance.js', 'object' => 'orerpEmployees', 'nonce_action' => 'orerp_employees'],
                'orerp-roles'         => ['file' => 'orerp-roles.js', 'object' => 'orerpRoles', 'nonce_action' => 'orerp_roles'],
                'orerp-suppliers'     => ['file' => 'orerp-suppliers.js', 'object' => 'orerpSuppliers', 'nonce_action' => 'orerp_suppliers'],
                'orerp-purchases'     => ['file' => 'orerp-purchases.js', 'object' => 'orerpPurchases', 'nonce_action' => 'orerp_purchases'],
                'orerp-accounting'    => ['file' => 'orerp-accounting.js', 'object' => 'orerpAccounting', 'nonce_action' => 'orerp_accounting'],
                'orerp-journal'       => ['file' => 'orerp-accounting.js', 'object' => 'orerpJournal', 'nonce_action' => 'orerp_journal'],
                'orerp-ledger'        => ['file' => 'orerp-ledger.js', 'object' => 'orerpLedger', 'nonce_action' => 'orerp_ledger'],
                'orerp-tax-reports'   => ['file' => 'orerp-tax-reports.js', 'object' => 'orerpTaxReports', 'nonce_action' => 'orerp_tax_reports'],
                'orerp-kitchen'       => ['file' => 'orerp-kitchen.js', 'object' => 'orerpKitchen', 'nonce_action' => 'orerp_kitchen'],
                'orerp-recipes'       => ['file' => 'orerp-kitchen.js', 'object' => 'orerpRecipes', 'nonce_action' => 'orerp_recipes'],
                'orerp-reports'       => ['file' => 'orerp-reports.js', 'object' => 'orerpReports', 'nonce_action' => 'orerp_reports'],
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
