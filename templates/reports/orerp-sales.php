<?php
/**
 * Sales report print template.
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$orerp_report_title = __('Sales Report', 'obydullah-restaurant-erp');
include ORERP_PATH . 'templates/reports/orerp-header.php';
?>
<div class="summary-grid">
    <div class="summary-card"><div class="label"><?php esc_html_e('Revenue', 'obydullah-restaurant-erp'); ?></div><div class="value"><?php echo esc_html(Obydullah_ERP_Helpers::orerp_format_currency($data['revenue'])); ?></div></div>
    <div class="summary-card"><div class="label"><?php esc_html_e('COGS', 'obydullah-restaurant-erp'); ?></div><div class="value"><?php echo esc_html(Obydullah_ERP_Helpers::orerp_format_currency($data['cogs'])); ?></div></div>
    <div class="summary-card"><div class="label"><?php esc_html_e('Gross Profit', 'obydullah-restaurant-erp'); ?></div><div class="value"><?php echo esc_html(Obydullah_ERP_Helpers::orerp_format_currency($data['gross_profit'])); ?></div></div>
    <div class="summary-card"><div class="label"><?php esc_html_e('Margin', 'obydullah-restaurant-erp'); ?></div><div class="value"><?php echo esc_html($data['margin'] . '%'); ?></div></div>
</div>

<?php if (!empty($data['monthly'])): ?>
<h3><?php esc_html_e('Monthly Trend', 'obydullah-restaurant-erp'); ?></h3>
<table>
    <thead><tr><th><?php esc_html_e('Month', 'obydullah-restaurant-erp'); ?></th><th class="text-right"><?php esc_html_e('Revenue', 'obydullah-restaurant-erp'); ?></th><th class="text-right"><?php esc_html_e('COGS', 'obydullah-restaurant-erp'); ?></th><th class="text-right"><?php esc_html_e('Gross Profit', 'obydullah-restaurant-erp'); ?></th></tr></thead>
    <tbody>
    <?php foreach ($data['monthly'] as $m): ?>
        <tr>
            <td><?php echo esc_html($m->month); ?></td>
            <td class="text-right"><?php echo esc_html(Obydullah_ERP_Helpers::orerp_format_currency($m->revenue)); ?></td>
            <td class="text-right"><?php echo esc_html(Obydullah_ERP_Helpers::orerp_format_currency($m->cogs)); ?></td>
            <td class="text-right"><?php echo esc_html(Obydullah_ERP_Helpers::orerp_format_currency($m->revenue - $m->cogs)); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if (!empty($data['purchases'])): ?>
<h3><?php esc_html_e('Purchase Summary', 'obydullah-restaurant-erp'); ?></h3>
<table>
    <thead><tr><th><?php esc_html_e('Status', 'obydullah-restaurant-erp'); ?></th><th class="text-right"><?php esc_html_e('Count', 'obydullah-restaurant-erp'); ?></th><th class="text-right"><?php esc_html_e('Total', 'obydullah-restaurant-erp'); ?></th></tr></thead>
    <tbody>
    <?php foreach ($data['purchases'] as $orerp_p): ?>
        <tr><td><?php echo esc_html($orerp_p->status); ?></td><td class="text-right"><?php echo esc_html($orerp_p->count); ?></td><td class="text-right"><?php echo esc_html(Obydullah_ERP_Helpers::orerp_format_currency($orerp_p->total)); ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php include ORERP_PATH . 'templates/reports/orerp-footer.php'; ?>
