<?php
/**
 * Inventory Report
 *
 * Per-branch stock levels from erp_branch_stock with product names, latest
 * purchase cost, low-stock alerts and stock-outs.
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Obydullah_ERP_Inventory_Reports
{
    public function get_inventory_report($branch_id = 0)
    {
        global $wpdb;

        $branch_id = intval($branch_id);

        $stock_table    = $wpdb->prefix . 'erp_branch_stock';
        $purchase_items = $wpdb->prefix . 'erp_purchase_items';
        $branches_table = $wpdb->prefix . 'erp_branches';

        $where   = '1=1';
        $prepare = [];

        if ($branch_id > 0) {
            $where   .= ' AND bs.branch_id = %d';
            $prepare[] = $branch_id;
        }

        $query = "SELECT bs.*, p.post_title AS product_name, b.name AS branch_name,
                COALESCE((
                    SELECT pi.unit_cost FROM {$purchase_items} pi
                    WHERE pi.product_id = bs.product_id
                    ORDER BY pi.id DESC LIMIT 1
                ), 0) AS cost_price
            FROM {$stock_table} bs
            LEFT JOIN {$wpdb->posts} p ON bs.product_id = p.ID
            LEFT JOIN {$branches_table} b ON bs.branch_id = b.id
            WHERE {$where}
            ORDER BY p.post_title ASC";

        $query = $prepare ? $wpdb->prepare($query, $prepare) : $query;
        $stock = $wpdb->get_results($query) ?: [];

        // Backward-compatible min_stock alias for the reports UI.
        foreach ($stock as &$item) {
            $item->min_stock = (int) ($item->reorder_level ?? 0);
        }
        unset($item);

        $total_items  = count($stock);
        $total_value  = 0;
        $low_stock    = [];
        $out_of_stock = [];

        foreach ($stock as $item) {
            $qty = floatval($item->quantity);
            $total_value += $qty * floatval($item->cost_price ?? 0);

            if ($qty <= 0) {
                $out_of_stock[] = $item;
            } elseif ($qty <= intval($item->min_stock)) {
                $low_stock[] = $item;
            }
        }

        return [
            'items'        => $stock,
            'total_items'  => $total_items,
            'total_value'  => $total_value,
            'low_stock'    => $low_stock,
            'out_of_stock' => $out_of_stock,
        ];
    }
}
