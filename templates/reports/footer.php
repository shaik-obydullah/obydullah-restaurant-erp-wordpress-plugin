<?php
/**
 * Report print footer template.
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
    <div class="footer">
        <?php
        echo esc_html__('Generated', 'obydullah-restaurant-erp') . ' ' .
            esc_html(current_time('Y-m-d H:i')) . ' - ' .
            esc_html(get_bloginfo('name'));
        ?>
    </div>
</body>
</html>
