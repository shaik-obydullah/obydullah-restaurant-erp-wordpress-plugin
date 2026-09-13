<?php
/**
 * Kitchen Display System (KDS) - Standalone Board
 *
 * Rendered via the [orerp_kds] shortcode. Polls the kitchen order endpoints
 * and displays a color-coded board for kitchen staff. Assets are enqueued by
 * Obydullah_ERP_Kitchen_Display::orerp_render_kds_shortcode().
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="orerp-kds" class="orerp-kds">

    <div class="orerp-kds__top">
        <div class="orerp-kds__clock" id="orerp-kds-clock"></div>
        <div>
            <label for="orerp-kds-branch"><?php esc_html_e('Branch', 'obydullah-restaurant-erp'); ?></label>
            <select id="orerp-kds-branch" class="orerp-kds__filter">
                <option value=""><?php esc_html_e('All', 'obydullah-restaurant-erp'); ?></option>
            </select>
        </div>
    </div>

    <div class="orerp-kds__err" id="orerp-kds-err"></div>

    <div class="orerp-kds__stats">
        <div class="orerp-kds__stat"><div class="n" id="orerp-kds-n-pending">0</div><div class="l"><?php esc_html_e('Pending', 'obydullah-restaurant-erp'); ?></div></div>
        <div class="orerp-kds__stat orerp-kds__stat--preparing"><div class="n" id="orerp-kds-n-preparing">0</div><div class="l"><?php esc_html_e('Preparing', 'obydullah-restaurant-erp'); ?></div></div>
        <div class="orerp-kds__stat orerp-kds__stat--ready"><div class="n" id="orerp-kds-n-ready">0</div><div class="l"><?php esc_html_e('Ready', 'obydullah-restaurant-erp'); ?></div></div>
    </div>

    <div class="orerp-kds__grid" id="orerp-kds-grid">
        <div class="orerp-kds__empty"><?php esc_html_e('Loading orders...', 'obydullah-restaurant-erp'); ?></div>
    </div>
</div>