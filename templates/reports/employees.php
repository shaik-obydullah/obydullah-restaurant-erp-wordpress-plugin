<?php
/**
 * Employee performance report print template.
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$orerp_report_title = __('Employee Performance Report', 'obydullah-restaurant-erp');
include ORERP_PATH . 'templates/reports/header.php';
?>

<?php if (!empty($data['employees'])): ?>
<table>
    <thead><tr>
        <th><?php esc_html_e('Employee', 'obydullah-restaurant-erp'); ?></th>
        <th><?php esc_html_e('Branch', 'obydullah-restaurant-erp'); ?></th>
        <th class="text-right"><?php esc_html_e('Days Worked', 'obydullah-restaurant-erp'); ?></th>
        <th class="text-right"><?php esc_html_e('Total Hours', 'obydullah-restaurant-erp'); ?></th>
        <th class="text-right"><?php esc_html_e('Tasks Completed', 'obydullah-restaurant-erp'); ?></th>
        <th class="text-right"><?php esc_html_e('Avg Time (min)', 'obydullah-restaurant-erp'); ?></th>
    </tr></thead>
    <tbody>
    <?php foreach ($data['employees'] as $orerp_e): ?>
        <tr>
            <td><strong><?php echo esc_html($orerp_e['name']); ?></strong></td>
            <td><?php echo esc_html($orerp_e['branch'] ?: '-'); ?></td>
            <td class="text-right"><?php echo esc_html($orerp_e['days_worked']); ?></td>
            <td class="text-right"><?php echo esc_html($orerp_e['total_hours']); ?></td>
            <td class="text-right"><?php echo esc_html($orerp_e['tasks_completed']); ?></td>
            <td class="text-right"><?php echo esc_html($orerp_e['avg_time_min'] > 0 ? $orerp_e['avg_time_min'] : '-'); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
    <p><?php esc_html_e('No employee data available.', 'obydullah-restaurant-erp'); ?></p>
<?php endif; ?>

<?php include ORERP_PATH . 'templates/reports/footer.php'; ?>
