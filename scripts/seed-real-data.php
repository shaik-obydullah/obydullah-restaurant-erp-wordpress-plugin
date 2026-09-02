<?php
/**
 * Real-test-data seeder for Obydullah Restaurant ERP.
 *
 * Fills empty tables (branch_stock, supplier_products, transfers,
 * fiscal_periods, prep_tracking) and repairs broken references so that
 * every module returns realistic, internally-consistent data:
 *
 *   - recipes            -> real WC products (product_id)
 *   - purchase_items     -> real WC products (product_id)
 *   - kitchen orders     -> real WC orders (order_id) + real products
 *   - employees          -> linked to WP users (user_id)
 *
 * Run from within the WordPress environment:
 *   wp eval-file wp-content/plugins/obydullah-restaurant-erp/scripts/seed-real-data.php --allow-root
 *
 * Idempotent by design: it only inserts rows that are missing.
 *
 * @package Obydullah_ERP
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	exit;
}

global $wpdb;

$prefix = $wpdb->prefix;

echo "== Seeding real test data for Obydullah Restaurant ERP ==\n";

$branch_ids = array_column( $wpdb->get_results( "SELECT id FROM {$prefix}erp_branches WHERE is_active = 1" ), 'id' );
if ( empty( $branch_ids ) ) {
	echo "No active branches found. Aborting.\n";
	return;
}

// Map real dish products to recipe names so kitchen/recipes are coherent.
// product_id => recipe meta
$recipe_map = array(
	10 => array( 'Chicken Biryani', 4, 20, 45 ),
	11 => array( 'BBQ Bacon Burger', 1, 10, 12 ),
	12 => array( 'Mushroom Swiss Burger', 1, 10, 12 ),
	13 => array( 'Veggie Burger', 1, 8, 10 ),
	14 => array( 'Margherita Pizza', 2, 15, 20 ),
	15 => array( 'Pepperoni Pizza', 2, 15, 22 ),
	16 => array( 'Hawaiian Pizza', 2, 15, 22 ),
	17 => array( 'Quattro Formaggi', 2, 15, 22 ),
	18 => array( 'Crispy Fries', 2, 5, 8 ),
	19 => array( 'Onion Rings', 2, 5, 6 ),
	20 => array( 'Coleslaw', 4, 8, 0 ),
	21 => array( 'Fresh Lemonade', 1, 3, 0 ),
	22 => array( 'Iced Tea', 1, 3, 0 ),
	23 => array( 'Craft Cola', 1, 1, 0 ),
	24 => array( 'Chocolate Brownie', 6, 15, 25 ),
	25 => array( 'Cheesecake Slice', 8, 20, 0 ),
);

// Ensure recipes reference real WC products (one recipe per dish product).
// Existing recipes were seeded with product_id = 0, so we bind them in place.
$recipes = $wpdb->get_results( "SELECT id, product_id, name FROM {$prefix}erp_recipes ORDER BY id" );
$recipe_dish_ids = array_keys( $recipe_map ); // real dish product IDs.
foreach ( $recipes as $index => $r ) {
	$product_id = $recipe_dish_ids[ $index % count( $recipe_dish_ids ) ];
	list( $name, $servings, $prep, $cook ) = $recipe_map[ $product_id ];
	$wpdb->update(
		"{$prefix}erp_recipes",
		array(
			'product_id'        => $product_id,
			'name'              => $name,
			'servings'          => $servings,
			'prep_time_minutes' => $prep,
			'cook_time_minutes' => $cook,
			'instructions'      => 'Standard preparation procedure for ' . $name . '.',
			'is_active'         => 1,
		),
		array( 'id' => (int) $r->id )
	);
	echo "   recipe #{$r->id}: bound to product #{$product_id} ({$name})\n";
}

// ---- Bind recipe ingredients to real ingredient products ----
// Raw / ingredient products to assign as recipe inputs.
$ingredient_assign = array( 18, 19, 20, 21, 22, 23, 65, 66, 67, 68 );
$reci_ings = $wpdb->get_results( "SELECT id, recipe_id, product_id FROM {$prefix}erp_recipe_ingredients ORDER BY id" );
foreach ( $reci_ings as $ing ) {
	if ( (int) $ing->product_id === 0 ) {
		$product_id = $ingredient_assign[ ( (int) $ing->id - 1 ) % count( $ingredient_assign ) ];
		$wpdb->update( "{$prefix}erp_recipe_ingredients", array( 'product_id' => $product_id ), array( 'id' => (int) $ing->id ) );
		echo "   recipe_ingredient #{$ing->id}: bound to product #{$product_id}\n";
	}
}

// ---- Stock ingredient products used by recipes / purchases ----
// These are the raw products we treat as "ingredients/inventory".
$ingredient_product_ids_opt = array(
	10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25,
	61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71,
);

// ---- Purchase items: bind to real products ----
$purchase_items = $wpdb->get_results( "SELECT id, purchase_id, product_id FROM {$prefix}erp_purchase_items" );
$pi_products = array( 10, 12, 14, 18, 20, 61, 64, 21, 23, 67 );
foreach ( $purchase_items as $pi ) {
	if ( (int) $pi->product_id === 0 ) {
		$next = $pi_products[ ( (int) $pi->id - 1 ) % count( $pi_products ) ];
		$wpdb->update( "{$prefix}erp_purchase_items", array( 'product_id' => $next ), array( 'id' => (int) $pi->id ) );
		echo "   purchase_item #{$pi->id}: bound to product #{$next}\n";
	}
}

// ---- Branch stock: one row per active branch per ingredient product ----
$stock_map = array(
	10 => array( 40, 25, 15, 10 ),
	11 => array( 30, 20, 12, 8 ),
	12 => array( 25, 18, 10, 6 ),
	13 => array( 35, 22, 14, 9 ),
	14 => array( 28, 20, 12, 7 ),
	15 => array( 22, 16, 9, 5 ),
	16 => array( 24, 17, 10, 6 ),
	17 => array( 20, 15, 8, 4 ),
	18 => array( 50, 35, 25, 15 ),
	19 => array( 45, 30, 20, 12 ),
	20 => array( 60, 40, 28, 18 ),
	21 => array( 55, 38, 25, 16 ),
	22 => array( 55, 38, 25, 16 ),
	23 => array( 70, 50, 35, 20 ),
	24 => array( 18, 12, 8, 5 ),
	25 => array( 15, 10, 6, 3 ),
	61 => array( 32, 22, 14, 9 ),
	62 => array( 30, 20, 12, 8 ),
	63 => array( 28, 19, 11, 7 ),
	64 => array( 26, 18, 10, 6 ),
	65 => array( 80, 60, 40, 25 ),
	66 => array( 50, 35, 22, 14 ),
	67 => array( 60, 42, 28, 18 ),
	68 => array( 34, 24, 15, 10 ),
	69 => array( 20, 14, 9, 5 ),
	70 => array( 25, 18, 11, 7 ),
	71 => array( 22, 16, 10, 6 ),
);

$stock_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}erp_branch_stock" );
if ( $stock_count === 0 ) {
	foreach ( $stock_map as $product_id => $qtys ) {
		foreach ( $branch_ids as $i => $branch_id ) {
			$qty = isset( $qtys[ $i ] ) ? $qtys[ $i ] : max( 2, $qty - 5 );
			$wpdb->insert(
				"{$prefix}erp_branch_stock",
				array(
					'branch_id'      => $branch_id,
					'product_id'     => $product_id,
					'quantity'       => $qty,
					'reorder_level'  => 10,
					'last_restocked' => gmdate( 'Y-m-d H:i:s' ),
				)
			);
		}
	}
	echo "   branch_stock: created " . ( count( $stock_map ) * count( $branch_ids ) ) . " rows\n";
} else {
	echo "   branch_stock: already has {$stock_count} rows, skipping\n";
}

// ---- Supplier products ----
$sp_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}erp_supplier_products" );
if ( $sp_count === 0 ) {
	$suppliers = $wpdb->get_results( "SELECT id FROM {$prefix}erp_suppliers ORDER BY id" );
	$sp_assign = array(
		1 => array( 10, 11, 12, 13, 61, 62, 63 ),
		2 => array( 14, 15, 16, 17, 64 ),
		3 => array( 21, 22, 23, 65, 66, 24, 25, 69 ),
		4 => array( 18, 19, 20, 67, 68, 70, 71 ),
	);
	foreach ( $suppliers as $sup ) {
		foreach ( $sp_assign[ (int) $sup->id ] ?? array() as $product_id ) {
			$wpdb->insert(
				"{$prefix}erp_supplier_products",
				array(
					'supplier_id'    => (int) $sup->id,
					'product_id'     => $product_id,
					'supplier_sku'   => 'SKU-' . $product_id,
					'unit_cost'      => round( 2 + ( $product_id % 40 ), 2 ),
					'lead_time_days' => 3 + ( $product_id % 7 ),
					'min_order_qty'  => 10,
				)
			);
		}
	}
	echo "   supplier_products: created rows\n";
} else {
	echo "   supplier_products: already has {$sp_count} rows, skipping\n";
}

// ---- Transfers (inter-branch) ----
$transfer_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}erp_transfers" );
if ( $transfer_count === 0 && count( $branch_ids ) >= 2 ) {
	$from = $branch_ids[0];
	$to   = $branch_ids[1];
	$wpdb->insert(
		"{$prefix}erp_transfers",
		array(
			'from_branch_id' => $from,
			'to_branch_id'   => $to,
			'status'         => 'received',
			'notes'          => 'Replenish Gulshan stock from Dhanmondi warehouse',
			'created_by'     => 1,
			'created_at'     => gmdate( 'Y-m-d H:i:s', strtotime( '-6 days' ) ),
			'received_at'    => gmdate( 'Y-m-d H:i:s', strtotime( '-5 days' ) ),
		)
	);
	$t1 = $wpdb->insert_id;
	foreach ( array( 10, 18, 65 ) as $product_id ) {
		$wpdb->insert( "{$prefix}erp_transfer_items", array(
			'transfer_id'       => $t1,
			'product_id'        => $product_id,
			'quantity'          => 20,
			'received_quantity' => 20,
		) );
	}

	$wpdb->insert(
		"{$prefix}erp_transfers",
		array(
			'from_branch_id' => $branch_ids[1],
			'to_branch_id'   => $branch_ids[2],
			'status'         => 'in_transit',
			'notes'          => 'Nightly rebalancing of frier inventory',
			'created_by'     => 1,
			'created_at'     => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
		)
	);
	$t2 = $wpdb->insert_id;
	foreach ( array( 19, 67 ) as $product_id ) {
		$wpdb->insert( "{$prefix}erp_transfer_items", array(
			'transfer_id'       => $t2,
			'product_id'        => $product_id,
			'quantity'          => 15,
			'received_quantity' => 0,
		) );
	}
	echo "   transfers: created 2 transfers\n";
} else {
	echo "   transfers: already has {$transfer_count} items, skipping\n";
}

// ---- Fiscal periods ----
$fp_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}erp_fiscal_periods" );
if ( $fp_count === 0 ) {
	$year = (int) gmdate( 'Y' );
	$periods = array(
		array( 'FY ' . $year, "{$year}-01-01", "{$year}-12-31", 0 ),
		array( 'January ' . $year, "{$year}-01-01", "{$year}-01-31", 0 ),
		array( 'February ' . $year, "{$year}-02-01", "{$year}-02-28", 0 ),
		array( 'March ' . $year, "{$year}-03-01", "{$year}-03-31", 0 ),
		array( 'August ' . $year, "{$year}-08-01", "{$year}-08-31", 0 ),
		array( 'September ' . $year, "{$year}-09-01", "{$year}-09-30", 0 ),
	);
	foreach ( $periods as $p ) {
		$wpdb->insert( "{$prefix}erp_fiscal_periods", array(
			'name'       => $p[0],
			'start_date' => $p[1],
			'end_date'   => $p[2],
			'is_closed'  => $p[3],
		) );
	}
	echo "   fiscal_periods: created " . count( $periods ) . " periods\n";
} else {
	echo "   fiscal_periods: already has {$fp_count} periods\n";
}

// ---- Kitchen orders: link to real WC orders + real products ----
$real_wc_orders = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type='shop_order' AND post_status IN ('wc-completed','wc-processing') ORDER BY ID" );
if ( empty( $real_wc_orders ) ) {
	echo "   No real WC orders found for kitchen linking; using existing mapping.\n";
} else {
	$kitchen_orders = $wpdb->get_results( "SELECT id, order_id, station FROM {$prefix}erp_kitchen_orders ORDER BY id" );
	$kitchen_items  = $wpdb->get_results( "SELECT id, kitchen_order_id, product_id FROM {$prefix}erp_kitchen_order_items ORDER BY id" );
	$item_by_order  = array();
	foreach ( $kitchen_items as $ki ) {
		$item_by_order[ (int) $ki->kitchen_order_id ][] = $ki;
	}

	$dish_ids = array_slice( $ingredient_product_ids_opt, 0, 16 );
	foreach ( $kitchen_orders as $ko ) {
		$real_order_id = $real_wc_orders[ ( (int) $ko->id - 1 ) % count( $real_wc_orders ) ];
		$wpdb->update( "{$prefix}erp_kitchen_orders", array( 'order_id' => (int) $real_order_id ), array( 'id' => (int) $ko->id ) );

		// Rebuild items with real product names.
		foreach ( ( $item_by_order[ (int) $ko->id ] ?? array() ) as $ki ) {
			$product_id = $dish_ids[ ( (int) $ki->id - 1 ) % count( $dish_ids ) ];
			$product    = $wpdb->get_row( $wpdb->prepare( "SELECT post_title FROM {$wpdb->posts} WHERE ID = %d", $product_id ) );
			$name       = $product ? $product->post_title : 'Item ' . $ki->id;
			$wpdb->update( "{$prefix}erp_kitchen_order_items", array(
				'product_id' => $product_id,
				'name'       => $name,
			), array( 'id' => (int) $ki->id ) );
		}
	}
	echo "   kitchen_orders: linked " . count( $kitchen_orders ) . " orders to real WC orders/products\n";
}

// ---- Prep tracking ----
$pt_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}erp_prep_tracking" );
if ( $pt_count === 0 ) {
	$employees   = $wpdb->get_col( "SELECT id FROM {$prefix}erp_employees ORDER BY id" );
	$recipes     = $wpdb->get_col( "SELECT id FROM {$prefix}erp_recipes ORDER BY id" );
	$kitchen_ids = $wpdb->get_col( "SELECT id FROM {$prefix}erp_kitchen_orders WHERE status IN ('completed','ready') ORDER BY id" );
	$n = 0;
	foreach ( $kitchen_ids as $ko_id ) {
		if ( $n >= 12 ) {
			break;
		}
		$emp   = $employees[ $n % count( $employees ) ];
		$re    = $recipes[ $n % count( $recipes ) ];
		$start = gmdate( 'Y-m-d H:i:s', strtotime( "-{$n} day 10:00:00" ) );
		$mins  = 15 + ( $n % 40 );
		$end   = gmdate( 'Y-m-d H:i:s', strtotime( "-{$n} day 10:{$mins}:00" ) );
		$wpdb->insert( "{$prefix}erp_prep_tracking", array(
			'kitchen_order_id'   => $ko_id,
			'recipe_id'          => $re,
			'employee_id'        => $emp,
			'started_at'         => $start,
			'completed_at'       => $end,
			'actual_time_minutes' => 15 + ( $n % 40 ),
			'notes'              => 'Prep completed for kitchen order #' . $ko_id,
		) );
		$n++;
	}
	echo "   prep_tracking: created {$n} rows\n";
} else {
	echo "   prep_tracking: already has {$pt_count} rows\n";
}

// ---- Link employees to WP users ----
$user_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->users} ORDER BY ID" );
$use_users = array();
foreach ( $user_ids as $uid ) {
	if ( $uid != 1 ) { // skip admin
		$use_users[] = (int) $uid;
	}
}
$employees = $wpdb->get_results( "SELECT id, user_id FROM {$prefix}erp_employees ORDER BY id" );
foreach ( $employees as $e ) {
	if ( empty( $e->user_id ) && ! empty( $use_users ) ) {
		$target = $use_users[ ( (int) $e->id - 1 ) % count( $use_users ) ];
		$wpdb->update( "{$prefix}erp_employees", array( 'user_id' => $target ), array( 'id' => (int) $e->id ) );
		echo "   employee #{$e->id}: linked to WP user #{$target}\n";
	}
}

// Post any unposted but balanced journal entries so reports are complete.
$unposted = $wpdb->get_results( "SELECT id FROM {$prefix}erp_journal_entries WHERE is_posted = 0" );
foreach ( $unposted as $entry ) {
	$dr = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(debit),0) FROM {$prefix}erp_journal_lines WHERE entry_id = %d", (int) $entry->id ) );
	$cr = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(credit),0) FROM {$prefix}erp_journal_lines WHERE entry_id = %d", (int) $entry->id ) );
	if ( abs( $dr - $cr ) < 0.01 ) {
		$wpdb->update( "{$prefix}erp_journal_entries", array( 'is_posted' => 1 ), array( 'id' => (int) $entry->id ) );
		echo "   journal entry #{$entry->id}: posted (balanced)\n";
	}
}

echo "== Seeding complete ==";