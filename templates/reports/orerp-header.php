<?php
/**
 * Report print header template.
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($orerp_report_title); ?></title>
    <?php wp_print_styles(array('orerp-report-print')); ?>
</head>
<body>
    <div class="report-header">
        <h1><?php echo esc_html($orerp_report_title); ?></h1>
        <div class="report-meta">
            <?php echo esc_html(get_bloginfo('name')); ?>
            <?php if (!empty($data['period'])): ?>
                &mdash; <?php echo esc_html($data['period']['from'] . ' to ' . $data['period']['to']); ?>
            <?php elseif (isset($data['as_of'])): ?>
                &mdash; <?php esc_html_e('As of', 'obydullah-restaurant-erp'); ?> <?php echo esc_html($data['as_of']); ?>
            <?php endif; ?>
            <span class="orerp-report-actions-float">
                <button class="orerp-print-button" type="button"><?php esc_html_e('Print / PDF', 'obydullah-restaurant-erp'); ?></button>
            </span>
        </div>
    </div>
