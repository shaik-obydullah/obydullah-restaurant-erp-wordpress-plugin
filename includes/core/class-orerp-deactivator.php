<?php
/**
 * Plugin Deactivator
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Deactivator
{
    public static function deactivate()
    {
        delete_option('orerp_version');
        delete_option('orerp_currency');
        delete_option('orerp_current_branch');

        flush_rewrite_rules();
    }
}
