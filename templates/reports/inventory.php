<?php
/**
 * Inventory report print template.
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$orerp_report_title = __('Inventory Report', 'obydullah-restaurant-erp');
include ORERP_PATH . 'templates/reports/header.php';
?>
<div class="summary-grid">
    <div class="summary-card"><div class="label"><?php esc_html_e('Total Items', 'obydullah-restaurant-erp'); ?></div><div class="value"><?php echo esc_html($data['total_items']); ?></div></div>
    <div class="summary-card"><div class="label"><?php esc_html_e('Total Value', 'obydullah-restaurant-erp'); ?></div><div class="value"><?php echo esc_html(Obydullah_ERP_Helpers::format_currency($data['total_value'])); ?></div></div>
    <div class="summary-card"><div class="label"><?php esc_html_e('Low Stock', 'obydullah-restaurant-erp'); ?></div><div class="value"><?php echo esc_html(count($data['low_stock'])); ?></div></div>
    <div class="summary-card"><div class="label"><?php esc_html_e('Out of Stock', 'obydullah-restaurant-erp'); ?></div><div class="value"><?php echo esc_html(count($data['out_of_stock'])); ?></div></div>
</div>

<?php if (!empty($data['items'])): ?>
<h3><?php esc_html_e('Stock List', 'obydullah-restaurant-erp'); ?></h3>
<table>
    <thead><tr><th><?php esc_html_e('Product', 'obydullah-restaurant-erp'); ?></th><th><?php esc_html_e('Branch', 'obydullah-restaurant-erp'); ?></th><th class="text-right"><?php esc_html_e('Qty', 'obydullah-restaurant-erp'); ?></th><th class="text-right"><?php esc_html_e('Min', 'obydullah-restaurant-erp'); ?></th><th class="text-right"><?php esc_html_e('Value', 'obydullah-restaurant-erp'); ?></th></tr></thead>
    <tbody>
    <?php foreach ($data['items'] as $s): ?>
        <tr>
            <td><?php echo esc_html($s->product_name ?: '#' . $s->product_id); ?></td>
            <td><?php echo esc_html($s->branch_name ?: '-'); ?></td>
            <td class="text-right"><?php echo esc_html($s->quantity); ?></td>
            <td class="text-right"><?php echo esc_html($s->min_stock ?? 0); ?></td>
            <td class="text-right"><?php echo esc_html(Obydullah_ERP_Helpers::format_currency(floatval($s->quantity) * floatval($s->cost_price ?? 0))); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php include ORERP_PATH . 'templates/reports/footer.php'; ?>
