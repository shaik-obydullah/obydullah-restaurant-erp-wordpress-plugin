<?php
/**
 * Financial report (P&L + Balance Sheet) print template.
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$report_title = __('Financial Report', 'obydullah-restaurant-erp');
include ORERP_PATH . 'templates/reports/header.php';
?>
<div class="summary-grid">
    <div class="summary-card"><div class="label"><?php esc_html_e('Total Revenue', 'obydullah-restaurant-erp'); ?></div><div class="value"><?php echo esc_html(Obydullah_ERP_Helpers::format_currency($data['total_revenue'])); ?></div></div>
    <div class="summary-card"><div class="label"><?php esc_html_e('Total Expenses', 'obydullah-restaurant-erp'); ?></div><div class="value"><?php echo esc_html(Obydullah_ERP_Helpers::format_currency($data['total_expenses'])); ?></div></div>
    <div class="summary-card"><div class="label"><?php esc_html_e('Net Income', 'obydullah-restaurant-erp'); ?></div><div class="value"><?php echo esc_html(Obydullah_ERP_Helpers::format_currency($data['net_income'])); ?></div></div>
</div>

<?php foreach ([['title' => __('Revenue', 'obydullah-restaurant-erp'), 'items' => $data['revenue']], ['title' => __('Expenses', 'obydullah-restaurant-erp'), 'items' => $data['expenses']]] as $section): ?>
<h3><?php echo esc_html($section['title']); ?></h3>
<table>
    <thead><tr><th><?php esc_html_e('Code', 'obydullah-restaurant-erp'); ?></th><th><?php esc_html_e('Account', 'obydullah-restaurant-erp'); ?></th><th class="text-right"><?php esc_html_e('Debit', 'obydullah-restaurant-erp'); ?></th><th class="text-right"><?php esc_html_e('Credit', 'obydullah-restaurant-erp'); ?></th><th class="text-right"><?php esc_html_e('Net', 'obydullah-restaurant-erp'); ?></th></tr></thead>
    <tbody>
    <?php $net_total = 0; foreach ($section['items'] as $a): $net = floatval($a['total_credit']) - floatval($a['total_debit']); $net_total += $net; ?>
        <tr>
            <td><?php echo esc_html($a['code']); ?></td>
            <td><?php echo esc_html($a['name']); ?></td>
            <td class="text-right"><?php echo esc_html(Obydullah_ERP_Helpers::format_currency($a['total_debit'])); ?></td>
            <td class="text-right"><?php echo esc_html(Obydullah_ERP_Helpers::format_currency($a['total_credit'])); ?></td>
            <td class="text-right"><?php echo esc_html(Obydullah_ERP_Helpers::format_currency($net)); ?></td>
        </tr>
    <?php endforeach; ?>
    <tr class="totals-row"><td colspan="4"><?php esc_html_e('Total', 'obydullah-restaurant-erp'); ?></td><td class="text-right"><?php echo esc_html(Obydullah_ERP_Helpers::format_currency($net_total)); ?></td></tr>
    </tbody>
</table>
<?php endforeach; ?>

<h3><?php esc_html_e('Balance Sheet Summary', 'obydullah-restaurant-erp'); ?></h3>
<table>
    <thead><tr><th><?php esc_html_e('Category', 'obydullah-restaurant-erp'); ?></th><th class="text-right"><?php esc_html_e('Debit', 'obydullah-restaurant-erp'); ?></th><th class="text-right"><?php esc_html_e('Credit', 'obydullah-restaurant-erp'); ?></th></tr></thead>
    <tbody>
    <?php foreach ([['label' => __('Assets', 'obydullah-restaurant-erp'), 'items' => $data['assets']], ['label' => __('Liabilities', 'obydullah-restaurant-erp'), 'items' => $data['liabilities']], ['label' => __('Equity', 'obydullah-restaurant-erp'), 'items' => $data['equity']]] as $section): ?>
        <?php $td = 0; $tc = 0; foreach ($section['items'] as $a) { $td += floatval($a['total_debit']); $tc += floatval($a['total_credit']); } ?>
        <tr class="totals-row">
            <td><?php echo esc_html($section['label']); ?></td>
            <td class="text-right"><?php echo esc_html(Obydullah_ERP_Helpers::format_currency($td)); ?></td>
            <td class="text-right"><?php echo esc_html(Obydullah_ERP_Helpers::format_currency($tc)); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php include ORERP_PATH . 'templates/reports/footer.php'; ?>
