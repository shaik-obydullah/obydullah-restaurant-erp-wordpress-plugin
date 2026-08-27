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
    <style>
        body { font-family: Georgia, 'Times New Roman', serif; color: #222; margin: 0; padding: 30px; }
        .report-header { border-bottom: 3px double #333; padding-bottom: 10px; margin-bottom: 20px; }
        .report-header h1 { margin: 0 0 4px; font-size: 22px; color: #1d2327; }
        .report-meta { font-size: 13px; color: #555; }
        h2, h3 { color: #1d2327; }
        table { border-collapse: collapse; width: 100%; margin: 12px 0; }
        th, td { border: 1px solid #bbb; padding: 6px 10px; text-align: left; font-size: 12px; }
        th { background: #f0f0f1; }
        .text-right { text-align: right; }
        .totals-row { background: #f6f7f7; font-weight: bold; }
        .summary-grid { display: flex; gap: 12px; margin: 16px 0; flex-wrap: wrap; }
        .summary-card { border: 1px solid #bbb; padding: 12px 16px; flex: 1; min-width: 150px; }
        .summary-card .label { font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: .5px; }
        .summary-card .value { font-size: 20px; font-weight: bold; margin-top: 4px; }
        .footer { margin-top: 30px; font-size: 11px; color: #888; text-align: center; }
        @media print {
            .no-print { display: none; }
        }
    </style>
    <script>window.onload = function () { window.print(); };</script>
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
            <span class="no-print" style="float:right;">
                <button onclick="window.print();return false;"><?php esc_html_e('Print / PDF', 'obydullah-restaurant-erp'); ?></button>
            </span>
        </div>
    </div>
