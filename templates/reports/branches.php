<?php
/**
 * Branch comparison report print template.
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$report_title = __('Branch Comparison Report', 'obydullah-restaurant-erp');
include ORERP_PATH . 'templates/reports/header.php';
?>

<?php if (!empty($data['branches'])): ?>
<table>
    <thead><tr>
        <th><?php esc_html_e('Branch', 'obydullah-restaurant-erp'); ?></th>
        <th class="text-right"><?php esc_html_e('Employees', 'obydullah-restaurant-erp'); ?></th>
        <th class="text-right"><?php esc_html_e('PO Count', 'obydullah-restaurant-erp'); ?></th>
        <th class="text-right"><?php esc_html_e('PO Total', 'obydullah-restaurant-erp'); ?></th>
        <th class="text-right"><?php esc_html_e('Kitchen Orders', 'obydullah-restaurant-erp'); ?></th>
        <th class="text-right"><?php esc_html_e('Stock Items', 'obydullah-restaurant-erp'); ?></th>
    </tr></thead>
    <tbody>
    <?php
    $totals = ['employees' => 0, 'po_count' => 0, 'po_total' => 0, 'kitchen_orders' => 0, 'stock_items' => 0];
    foreach ($data['branches'] as $b):
        $totals['employees'] += $b['employees'];
        $totals['po_count'] += $b['po_count'];
        $totals['po_total'] += $b['po_total'];
        $totals['kitchen_orders'] += $b['kitchen_orders'];
        $totals['stock_items'] += $b['stock_items'];
    ?>
        <tr>
            <td><strong><?php echo esc_html($b['branch_name']); ?></strong></td>
            <td class="text-right"><?php echo esc_html($b['employees']); ?></td>
            <td class="text-right"><?php echo esc_html($b['po_count']); ?></td>
            <td class="text-right"><?php echo esc_html(Obydullah_ERP_Helpers::format_currency($b['po_total'])); ?></td>
            <td class="text-right"><?php echo esc_html($b['kitchen_orders']); ?></td>
            <td class="text-right"><?php echo esc_html($b['stock_items']); ?></td>
        </tr>
    <?php endforeach; ?>
    <tr class="totals-row">
        <td><?php esc_html_e('Total', 'obydullah-restaurant-erp'); ?></td>
        <td class="text-right"><?php echo esc_html($totals['employees']); ?></td>
        <td class="text-right"><?php echo esc_html($totals['po_count']); ?></td>
        <td class="text-right"><?php echo esc_html(Obydullah_ERP_Helpers::format_currency($totals['po_total'])); ?></td>
        <td class="text-right"><?php echo esc_html($totals['kitchen_orders']); ?></td>
        <td class="text-right"><?php echo esc_html($totals['stock_items']); ?></td>
    </tr>
    </tbody>
</table>
<?php else: ?>
    <p><?php esc_html_e('No branch data available.', 'obydullah-restaurant-erp'); ?></p>
<?php endif; ?>

<?php include ORERP_PATH . 'templates/reports/footer.php'; ?>
