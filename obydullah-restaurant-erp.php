<?php
/**
 * Plugin Name: Obydullah Restaurant ERP
 * Plugin URI: https://obydullah.com/project/restaurant-micro-erp
 * Description: A complete restaurant management system with branches, employees, suppliers, purchases, accounting, kitchen operations, and reports.
 * Version: 1.0.0
 * Author: Shaik Obydullah
 * Author URI: https://obydullah.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: obydullah-restaurant-erp
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 * Domain Path: /languages
 * WooCommerce requires at least: 8.0
 * WooCommerce tested up to: 11.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ORERP_VERSION', '1.0.0');
define('ORERP_PATH', plugin_dir_path(__FILE__));
define('ORERP_URL', plugin_dir_url(__FILE__));
define('ORERP_BASENAME', plugin_basename(__FILE__));

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

require_once ORERP_PATH . 'includes/core/class-orerp-activator.php';
require_once ORERP_PATH . 'includes/core/class-orerp-deactivator.php';
require_once ORERP_PATH . 'includes/core/class-orerp-handler.php';

register_activation_hook(__FILE__, ['Obydullah_ERP_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['Obydullah_ERP_Deactivator', 'deactivate']);

add_action('plugins_loaded', ['Obydullah_ERP_Activator', 'orerp_maybe_upgrade']);

add_action('plugins_loaded', 'orerp_init');
function orerp_init()
{
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p><strong>Obydullah Restaurant ERP</strong> requires WooCommerce to be installed and active.</p></div>';
        });
        return;
    }

    static $plugin = null;
    if (null === $plugin) {
        $plugin = Obydullah_ERP_Handler::instance();
    }
    return $plugin;
}
