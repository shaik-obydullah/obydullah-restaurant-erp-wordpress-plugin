<?php
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL table names come from $wpdb->prefix and every value is bound via $wpdb->prepare() placeholders; direct queries are used for the ERP-specific tables that have no core caching API.
/**
 * Helper Functions
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Helpers
{
    public static function orerp_get_settings()
    {
        return [
            'currency'          => get_option('orerp_currency', '$'),
            'currency_position' => get_option('orerp_currency_position', 'left'),
            'date_format'       => get_option('orerp_date_format', 'Y-m-d'),
            'current_branch'    => get_option('orerp_current_branch', 0),
        ];
    }

    public static function orerp_format_currency($amount)
    {
        $settings = self::orerp_get_settings();
        $currency = $settings['currency'];
        $position = $settings['currency_position'];
        $amount_formatted = number_format(floatval($amount), 2);

        switch ($position) {
            case 'right':
                return $amount_formatted . $currency;
            case 'left_space':
                return $currency . ' ' . $amount_formatted;
            case 'right_space':
                return $amount_formatted . ' ' . $currency;
            case 'left':
            default:
                return $currency . $amount_formatted;
        }
    }

    public static function orerp_format_date($date_string)
    {
        if (empty($date_string)) {
            return 'orerp_';
        }

        $settings = self::orerp_get_settings();
        $date_format = $settings['date_format'];
        $timestamp = strtotime($date_string);

        if (false === $timestamp) {
            return $date_string;
        }

        return gmdate($date_format, $timestamp);
    }

    public static function orerp_get_currency_symbol()
    {
        if (function_exists('get_woocommerce_currency_symbol') && function_exists('get_woocommerce_currency')) {
            return get_woocommerce_currency_symbol(get_woocommerce_currency());
        }
        return self::orerp_get_settings()['currency'];
    }

    public static function orerp_get_current_branch_id()
    {
        return intval(get_option('orerp_current_branch', 0));
    }

    public static function orerp_set_current_branch_id($branch_id)
    {
        update_option('orerp_current_branch', intval($branch_id));
    }

    public static function orerp_generate_po_number()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'erp_purchase_orders';
        $prefix = 'PO-' . gmdate('Ymd') . '-';

        $last = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT po_number FROM {$table} WHERE po_number LIKE %s ORDER BY id DESC LIMIT 1",
                $prefix . '%'
            )
        );

        if ($last) {
            $last_num = intval(substr($last, -4));
            $new_num = $last_num + 1;
        } else {
            $new_num = 1;
        }

        return $prefix . str_pad($new_num, 4, '0', STR_PAD_LEFT);
    }

    public static function orerp_generate_entry_number()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'erp_journal_entries';
        $prefix = 'JE-' . gmdate('Ymd') . '-';

        $last = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT entry_number FROM {$table} WHERE entry_number LIKE %s ORDER BY id DESC LIMIT 1",
                $prefix . '%'
            )
        );

        if ($last) {
            $last_num = intval(substr($last, -4));
            $new_num = $last_num + 1;
        } else {
            $new_num = 1;
        }

        return $prefix . str_pad($new_num, 4, '0', STR_PAD_LEFT);
    }

    public static function orerp_generate_employee_code()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'erp_employees';
        $prefix = 'EMP-';

        $last = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT employee_code FROM {$table} WHERE employee_code LIKE %s ORDER BY id DESC LIMIT 1",
                $prefix . '%'
            )
        );

        if ($last) {
            $last_num = intval(substr($last, -4));
            $new_num = $last_num + 1;
        } else {
            $new_num = 1;
        }

        return $prefix . str_pad($new_num, 4, '0', STR_PAD_LEFT);
    }

    public static function orerp_generate_supplier_code()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'erp_suppliers';
        $prefix = 'SUP-';

        $last = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT code FROM {$table} WHERE code LIKE %s ORDER BY id DESC LIMIT 1",
                $prefix . '%'
            )
        );

        if ($last) {
            $last_num = intval(substr($last, -4));
            $new_num = $last_num + 1;
        } else {
            $new_num = 1;
        }

        return $prefix . str_pad($new_num, 4, '0', STR_PAD_LEFT);
    }

    public static function orerp_get_account_id_by_code($code)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'erp_accounts';

        $cache_key = 'account_id_by_code_' . $code;
        $cached = Obydullah_ERP_Cache::get($cache_key, $table);
        if (false !== $cached) {
            return intval($cached);
        }

        $result = intval($wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE code = %s",
            $code
        )));

        Obydullah_ERP_Cache::set($cache_key, $table, $result);
        return $result;
    }

    public static function orerp_sanitize_price($price)
    {
        return floatval(preg_replace('/[^0-9.\-]/', 'orerp_', $price));
    }

    public static function orerp_is_valid_date($date, $format = 'Y-m-d')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * Check if the current user has the given ERP capability.
     * Administrators always pass; otherwise the specific role capability is used.
     *
     * @param string $cap Capability name (defaults to full admin).
     * @return bool
     */
    public static function orerp_can($cap = 'orerp_admin')
    {
        if (current_user_can('manage_options')) {
            return true;
        }

        return current_user_can($cap);
    }

    /**
     * Render the plugin Settings page.
     *
     * @return void
     */
    public static function orerp_render_settings_page()
    {
        if (isset($_POST['orerp_save_settings']) && check_admin_referer('orerp_save_settings', 'orerp_settings_nonce')) {
            $currency          = sanitize_text_field(wp_unslash($_POST['currency'] ?? '$'));
            $currency_position = sanitize_text_field(wp_unslash($_POST['currency_position'] ?? 'left'));
            $date_format       = sanitize_text_field(wp_unslash($_POST['date_format'] ?? 'Y-m-d'));
            $tax_rate          = floatval($_POST['tax_rate'] ?? 0);

            update_option('orerp_currency', $currency);
            update_option('orerp_currency_position', $currency_position);
            update_option('orerp_date_format', $date_format);
            update_option('orerp_tax_rate', max(0, min(100, $tax_rate)));

            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'obydullah-restaurant-erp') . '</p></div>';
        }

        $settings = self::orerp_get_settings();
        $tax_rate = floatval(get_option('orerp_tax_rate', 0));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Restaurant ERP Settings', 'obydullah-restaurant-erp'); ?></h1>
            <hr class="wp-header-end">

            <div class="orerp-card orerp-form">
                <form method="post" action="">
                    <?php wp_nonce_field('orerp_save_settings', 'orerp_settings_nonce'); ?>
                    <input type="hidden" name="orerp_save_settings" value="1">

                    <div class="form-row">
                        <div class="form-group">
                            <label><?php esc_html_e('Currency Symbol', 'obydullah-restaurant-erp'); ?></label>
                            <input type="text" name="currency" class="small-text"
                                value="<?php echo esc_attr($settings['currency']); ?>" maxlength="5">
                        </div>
                        <div class="form-group">
                            <label><?php esc_html_e('Currency Position', 'obydullah-restaurant-erp'); ?></label>
                            <select name="currency_position" class="regular-text">
                                <option value="left" <?php selected($settings['currency_position'], 'left'); ?>><?php esc_html_e('Left ($100)', 'obydullah-restaurant-erp'); ?></option>
                                <option value="right" <?php selected($settings['currency_position'], 'right'); ?>><?php esc_html_e('Right (100$)', 'obydullah-restaurant-erp'); ?></option>
                                <option value="left_space" <?php selected($settings['currency_position'], 'left_space'); ?>><?php esc_html_e('Left + Space ($ 100)', 'obydullah-restaurant-erp'); ?></option>
                                <option value="right_space" <?php selected($settings['currency_position'], 'right_space'); ?>><?php esc_html_e('Right + Space (100 $)', 'obydullah-restaurant-erp'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><?php esc_html_e('Date Format', 'obydullah-restaurant-erp'); ?></label>
                            <input type="text" name="date_format" class="regular-text"
                                value="<?php echo esc_attr($settings['date_format']); ?>"
                                placeholder="<?php esc_attr_e('e.g. Y-m-d', 'obydullah-restaurant-erp'); ?>">
                            <p class="description"><?php esc_html_e('PHP date() format used across reports.', 'obydullah-restaurant-erp'); ?></p>
                        </div>
                        <div class="form-group">
                            <label><?php esc_html_e('Default Tax / VAT Rate (%)', 'obydullah-restaurant-erp'); ?></label>
                            <input type="number" name="tax_rate" class="small-text" min="0" max="100" step="0.01"
                                value="<?php echo esc_attr($tax_rate); ?>">
                            <p class="description"><?php esc_html_e('Used as the default rate when creating purchase orders.', 'obydullah-restaurant-erp'); ?></p>
                        </div>
                    </div>

                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e('Save Settings', 'obydullah-restaurant-erp'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
}
