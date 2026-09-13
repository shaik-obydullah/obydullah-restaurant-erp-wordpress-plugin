<?php
/**
 * Demo data seeder for Obydullah Restaurant ERP.
 *
 * Creates an internally-consistent, realistic dataset from an empty
 * database so every module has data to render:
 *
 *   - WooCommerce products (menu dishes + inventory ingredients)
 *   - WooCommerce orders (with the ERP integration recording sales)
 *   - Branches, employees (linked to WP users), shifts, suppliers
 *   - Recipes + ingredients, supplier-product pricelists
 *   - Purchase orders (+ receive + payments -> stock & journal entries)
 *   - Inter-branch transfers, branch stock
 *   - Kitchen orders derived from real WC orders
 *   - Fiscal periods, prep tracking, attendance
 *   - Accounting journal entries
 *
 * Run from within the WordPress environment:
 *   wp eval-file wp-content/plugins/obydullah-restaurant-erp/scripts/seed-demo-data.php --allow-root
 *
 * Optional: pass --reset to wipe the seeded data (ERP tables + marked
 * products/orders/users) before re-seeding:
 *   wp eval-file wp-content/plugins/obydullah-restaurant-erp/scripts/seed-demo-data.php --allow-root --reset
 *
 * Idempotent by design: existing entities are never duplicated.
 *
 * @package Obydullah_ERP
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	exit;
}

global $wpdb;

$prefix   = $wpdb->prefix;
$reset    = in_array( 'reset', (array) $args, true ) || ! empty( $assoc_args['reset'] );
$seed_tag = '_orerp_seed_test';

if ( ! class_exists( 'WooCommerce' ) ) {
	WP_CLI::error( 'WooCommerce is not active.' );
}

// ------------------------------------------------------------------
// Reset (optional): wipe marked seeded data + ERP rows.
// ------------------------------------------------------------------
if ( $reset ) {
	$seeded_orders = wc_get_orders(
		array(
			'meta_key'   => $seed_tag,
			'meta_value' => '1',
			'limit'      => -1,
		)
	);
	foreach ( $seeded_orders as $o ) {
		$o->delete( true );
	}
	WP_CLI::log( 'Removed ' . count( $seeded_orders ) . ' seeded orders.' );

	$seeded_products = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID WHERE p.post_type = 'product' AND m.meta_key = %s AND m.meta_value = '1'",
			$seed_tag
		)
	);
	foreach ( $seeded_products as $pid ) {
		wp_delete_post( $pid, true );
	}
	WP_CLI::log( 'Removed ' . count( $seeded_products ) . ' seeded products.' );

	$seeded_user_ids = get_users( array( 'meta_key' => $seed_tag, 'meta_value' => '1', 'fields' => 'ID' ) );
	foreach ( $seeded_user_ids as $uid ) {
		wp_delete_user( $uid );
	}
	WP_CLI::log( 'Removed ' . count( $seeded_user_ids ) . ' seeded users.' );

	$orerp_tables = array(
		'orerp_branches',
		'orerp_employees',
		'orerp_shifts',
		'orerp_attendance',
		'orerp_suppliers',
		'orerp_supplier_products',
		'orerp_recipes',
		'orerp_recipe_ingredients',
		'orerp_purchase_orders',
		'orerp_purchase_items',
		'orerp_purchase_payments',
		'orerp_kitchen_orders',
		'orerp_kitchen_order_items',
		'orerp_transfers',
		'orerp_transfer_items',
		'orerp_branch_stock',
		'orerp_fiscal_periods',
		'orerp_prep_tracking',
		'orerp_journal_entries',
		'orerp_journal_lines',
	);
	foreach ( $orerp_tables as $table ) {
		$wpdb->query( "TRUNCATE TABLE {$prefix}{$table}" );
	}
	wp_cache_flush();
	WP_CLI::log( 'ERP tables truncated.' );
}

$branch_seed_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}orerp_branches" );
if ( 0 < $branch_seed_total ) {
	WP_CLI::log( 'ERP data already present (' . $branch_seed_total . ' branches). Use --reset to reseed.' );
	return;
}

WP_CLI::log( '== Seeding demo data for Obydullah Restaurant ERP ==' );

// ------------------------------------------------------------------
// Helper: create a WooCommerce product if it doesn't already exist.
// ------------------------------------------------------------------
function ds_create_product( $name, $price, $ingredient = false ) {
	global $wpdb;

	$existing = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' AND post_title = %s LIMIT 1",
			$name
		)
	);
	if ( $existing ) {
		update_post_meta( $existing, '_orerp_seed_test', '1' );
		return $existing;
	}

	$pid = wp_insert_post(
		array(
			'post_type'    => 'product',
			'post_status'  => 'publish',
			'post_title'   => $name,
			'post_name'    => sanitize_title( $name ),
			'post_content' => '',
		)
	);
	if ( ! $pid || is_wp_error( $pid ) ) {
		WP_CLI::warning( "Could not create product '{$name}'." );
		return 0;
	}

	wp_set_object_terms( $pid, 'simple', 'product_type' );
	update_post_meta( $pid, '_regular_price', wc_format_decimal( $price ) );
	update_post_meta( $pid, '_price', wc_format_decimal( $price ) );
	update_post_meta( $pid, '_visibility', 'visible' );
	update_post_meta( $pid, '_stock_status', 'instock' );
	update_post_meta( $pid, '_manage_stock', $ingredient ? 'yes' : 'no' );
	if ( $ingredient ) {
		update_post_meta( $pid, '_stock', 1000 );
	}
	update_post_meta( $pid, '_orerp_seed_test', '1' );

	$product = wc_get_product( $pid );
	if ( $product ) {
		$product->set_category_ids( array() );
		$product->save();
	}
	wc_delete_product_transients( $pid );

	return (int) $pid;
}

// ------------------------------------------------------------------
// 1. Menu dishes + inventory ingredients.
// ------------------------------------------------------------------
$dishes = array(
	'Chicken Biryani'      => 8.99,
	'BBQ Bacon Burger'     => 9.49,
	'Mushroom Swiss Burger'=> 9.99,
	'Veggie Burger'        => 7.99,
	'Margherita Pizza'     => 10.99,
	'Pepperoni Pizza'      => 11.99,
	'Hawaiian Pizza'       => 11.49,
	'Quattro Formaggi'     => 12.49,
	'Crispy Fries'         => 3.49,
	'Onion Rings'          => 3.99,
	'Coleslaw'             => 2.99,
	'Fresh Lemonade'       => 3.49,
	'Iced Tea'             => 2.99,
	'Craft Cola'           => 2.49,
	'Chocolate Brownie'    => 4.99,
	'Cheesecake Slice'     => 5.49,
);
$ingredients = array(
	'Basmati Rice'     => 1.50,
	'Chicken Breast'   => 3.20,
	'Beef Patties'     => 2.10,
	'Mozzarella Cheese'=> 4.80,
	'All-Purpose Flour'=> 1.20,
	'Cooking Oil'      => 2.60,
	'Onions'           => 0.70,
	'Tomatoes'         => 0.80,
	'Butter'           => 3.40,
	'Sugar'            => 0.90,
	'Lemons'           => 0.50,
);

$dish_ids    = array();
$dish_by_name = array();
foreach ( $dishes as $name => $price ) {
	$id                        = ds_create_product( $name, $price );
	$dish_ids[]                = $id;
	$dish_by_name[ $name ]     = $id;
}
WP_CLI::log( 'Products: ' . count( $dish_ids ) . ' dishes created/linked.' );

$ing_ids          = array();
$ing_by_name      = array();
foreach ( $ingredients as $name => $price ) {
	$id                     = ds_create_product( $name, $price, true );
	$ing_ids[]              = $id;
	$ing_by_name[ $name ]   = $id;
}
WP_CLI::log( 'Products: ' . count( $ing_ids ) . ' ingredients created/linked.' );

$RICE = $ing_by_name['Basmati Rice'];
$CHK  = $ing_by_name['Chicken Breast'];
$BEEF = $ing_by_name['Beef Patties'];
$CHZ  = $ing_by_name['Mozzarella Cheese'];
$FLR  = $ing_by_name['All-Purpose Flour'];
$OIL  = $ing_by_name['Cooking Oil'];
$ONN  = $ing_by_name['Onions'];
$TOM  = $ing_by_name['Tomatoes'];
$BTR  = $ing_by_name['Butter'];
$SUG  = $ing_by_name['Sugar'];
$LEM  = $ing_by_name['Lemons'];

// ------------------------------------------------------------------
// 2. WP users for staff.
// ------------------------------------------------------------------
$staff_users = array(
	array( 'kabir', 'Kabir Ahmed', 'Chef' ),
	array( 'sana', 'Sana Rahman', 'Cashier' ),
	array( 'emon', 'Emon Hossain', 'Kitchen Staff' ),
);
$staff_user_ids = array();
foreach ( $staff_users as $s ) {
	$uid = username_exists( $s[0] );
	if ( ! $uid ) {
		$uid = wp_insert_user(
			array(
				'user_login'   => $s[0],
				'user_pass'    => wp_generate_password( 16, true ),
				'display_name' => $s[1],
				'role'         => 'subscriber',
				'user_email'   => $s[0] . '@example.test',
			)
		);
		if ( ! is_wp_error( $uid ) ) {
			update_user_meta( $uid, '_orerp_seed_test', '1' );
		}
	}
	if ( $uid && ! is_wp_error( $uid ) ) {
		$staff_user_ids[] = (int) $uid;
	}
}
WP_CLI::log( 'Users: ' . count( $staff_user_ids ) . ' staff users ready.' );

// ------------------------------------------------------------------
// 3. Branches.
// ------------------------------------------------------------------
$branch_data = array(
	array( 'Dhanmondi Flagship', 'BR-001', 'House 12, Road 5, Dhanmondi, Dhaka' ),
	array( 'Gulshan Gourmet', 'BR-002', 'Plot 34, Gulshan Avenue, Dhaka' ),
	array( 'Banani Bistro', 'BR-003', 'Level 4, Banani Commercial Area, Dhaka' ),
);
$branch_ids = array();
$branches   = new Obydullah_ERP_Branches();
$i          = 0;
foreach ( $branch_data as $b ) {
	$id = $branches->orerp_save_branch(
		array(
			'name'       => $b[0],
			'code'       => $b[1],
			'address'    => $b[2],
			'phone'      => '+880 2-' . str_pad( (string) ( 1000 + $i ), 4, '0', STR_PAD_LEFT ),
			'email'      => strtolower( str_replace( ' ', '', $b[0] ) ) . '@example.test',
			'manager_id' => 1,
			'is_active'  => 1,
		)
	);
	if ( ! is_wp_error( $id ) ) {
		$branch_ids[] = (int) $id;
	}
	$i++;
}
WP_CLI::log( 'Branches: ' . count( $branch_ids ) . ' created.' );

$branch_1 = $branch_ids[0];
$branch_2 = $branch_ids[1] ?? $branch_1;
$branch_3 = $branch_ids[2] ?? $branch_1;

// ------------------------------------------------------------------
// 4. Shifts.
// ------------------------------------------------------------------
$init_attendance = new Obydullah_ERP_Attendance();
$shift_data      = array(
	array( $branch_1, 'Morning Shift', '08:00:00', '16:00:00' ),
	array( $branch_1, 'Evening Shift', '16:00:00', '00:00:00' ),
	array( $branch_2, 'Day Shift', '09:00:00', '17:00:00' ),
	array( $branch_2, 'Night Shift', '17:00:00', '01:00:00' ),
	array( $branch_3, 'Split Shift', '10:00:00', '22:00:00' ),
);
foreach ( $shift_data as $sh ) {
	$init_attendance->orerp_save_shift(
		array(
			'branch_id'  => $sh[0],
			'name'       => $sh[1],
			'start_time' => $sh[2],
			'end_time'   => $sh[3],
		)
	);
}
WP_CLI::log( 'Shifts: ' . count( $shift_data ) . ' created.' );

// ------------------------------------------------------------------
// 5. Employees.
// ------------------------------------------------------------------
$employee_data = array(
	array( 'KAB-001', $branch_1, 'Manager', 12.50, 'Kabir', $staff_user_ids[0] ?? 0 ),
	array( 'SAN-002', $branch_1, 'Cashier', 6.50, 'Sana', $staff_user_ids[1] ?? 0 ),
	array( 'EMO-003', $branch_1, 'Chef', 10.00, 'Emon', $staff_user_ids[2] ?? 0 ),
	array( 'RFA-004', $branch_2, 'Senior Chef', 11.50, 'Rafiq', 0 ),
	array( 'NIL-005', $branch_2, 'Server', 5.50, 'Nilufa', 0 ),
	array( 'ASR-006', $branch_3, 'Kitchen Staff', 7.00, 'Asraf', 0 ),
	array( 'TAL-007', $branch_3, 'Waiter', 5.00, 'Talha', 0 ),
);
$employees = new Obydullah_ERP_Employees();
$employee_ids = array();
foreach ( $employee_data as $e ) {
	$id = $employees->orerp_save_employee(
		array(
			'employee_code'     => $e[0],
			'display_name'      => $e[3], // display_name handled when user_id set; fallback stored via user meta only.
			'branch_id'         => $e[1],
			'position'          => $e[2],
			'hourly_rate'       => $e[4],
			'hire_date'         => gmdate( 'Y-m-d', strtotime( '-300 days' ) ),
			'user_id'           => $e[5],
			'is_active'         => 1,
			'emergency_contact' => 'Family - ' . $e[3],
			'emergency_phone'   => '+880 19-' . str_pad( (string) ( 100 + $i++ ), 8, '0', STR_PAD_LEFT ),
		)
	);
	// Employee display name lives on the user; set it here for linked users.
	if ( ! is_wp_error( $id ) && $e[5] ) {
		update_user_meta( $e[5], 'display_name', $e[3] );
	}
	if ( ! is_wp_error( $id ) ) {
		$employee_ids[] = (int) $id;
	}
}
WP_CLI::log( 'Employees: ' . count( $employee_ids ) . ' created.' );

// ------------------------------------------------------------------
// 6. Suppliers + supplier-product pricelists.
// ------------------------------------------------------------------
$supplier_data = array(
	array( 'Fresh Farms Produce', 'SUP-001', 'Rowshan Ara', 'Fresh vegetables, fruits and citrus', 0 ),
	array( 'Barnard Meat Co.', 'SUP-002', 'Imran Bhuiyan', 'Halal chicken and beef', 0 ),
	array( 'Golden Grain Mills', 'SUP-003', 'Muhammad Alam', 'Rice, flour, sugar and dry goods', 0 ),
	array( 'Daily Dairy Plus', 'SUP-004', 'Sadia Islam', 'Cheese, butter and dairy', 0 ),
);
$suppliers = new Obydullah_ERP_Suppliers();
$supplier_ids = array();
$i = 0;
foreach ( $supplier_data as $s ) {
	$id = $suppliers->orerp_save_supplier(
		array(
			'name'           => $s[0],
			'code'           => $s[1],
			'contact_person' => $s[2],
			'email'          => 'sales-' . strtolower( str_replace( array( ' ', '.', "'" ), '', $s[0] ) ) . '@example.test',
			'phone'          => '+880 17-' . str_pad( (string) ( 100 + $i ), 8, '0', STR_PAD_LEFT ),
			'address'        => $s[3],
			'payment_terms'  => 'Net 30',
			'is_active'      => 1,
		)
	);
	if ( ! is_wp_error( $id ) ) {
		$supplier_ids[] = (int) $id;
	}
	$i++;
}
WP_CLI::log( 'Suppliers: ' . count( $supplier_ids ) . ' created.' );

$pricelist = array(
	$supplier_ids[0] => array(
		$TOM  => array( 0.60, 3 ),
		$ONN  => array( 0.55, 4 ),
		$LEM  => array( 0.40, 2 ),
		$FLR  => array( 1.10, 5 ),
	),
	$supplier_ids[1] => array(
		$CHK  => array( 2.90, 2 ),
		$BEEF => array( 1.85, 3 ),
	),
	$supplier_ids[2] => array(
		$RICE => array( 1.35, 4 ),
		$FLR  => array( 1.05, 5 ),
		$SUG  => array( 0.75, 3 ),
		$OIL  => array( 2.30, 4 ),
	),
	$supplier_ids[3] => array(
		$CHZ  => array( 4.40, 2 ),
		$BTR  => array( 3.10, 3 ),
	),
);
foreach ( $pricelist as $supplier_id => $items ) {
	foreach ( $items as $product_id => $cfg ) {
		$wpdb->insert(
			"{$prefix}orerp_supplier_products",
			array(
				'supplier_id'    => $supplier_id,
				'product_id'     => $product_id,
				'supplier_sku'   => 'SKU-' . $product_id,
				'unit_cost'      => $cfg[0],
				'lead_time_days' => $cfg[1],
				'min_order_qty'  => 10,
			),
			array( '%d', '%d', '%s', '%f', '%d', '%d' )
		);
	}
}
WP_CLI::log( 'Supplier pricelists: populated.' );

// ------------------------------------------------------------------
// 7. Recipes (one per dish) + amounts per ingredient.
// ------------------------------------------------------------------
$recipe_map = array(
	'Chicken Biryani'        => array( 4, 20, 45, array( $RICE => array( 0.50, 'kg' ), $CHK => array( 0.40, 'kg' ), $OIL => array( 0.10, 'l' ), $ONN => array( 0.20, 'kg' ), $TOM => array( 0.20, 'kg' ), $SUG => array( 0.05, 'kg' ) ) ),
	'BBQ Bacon Burger'       => array( 1, 10, 12, array( $BEEF => array( 1, 'pc' ), $CHZ => array( 0.05, 'kg' ), $TOM => array( 0.10, 'kg' ), $BTR => array( 0.02, 'kg' ) ) ),
	'Mushroom Swiss Burger'  => array( 1, 10, 12, array( $BEEF => array( 1, 'pc' ), $CHZ => array( 0.06, 'kg' ), $BTR => array( 0.02, 'kg' ) ) ),
	'Veggie Burger'          => array( 1, 8, 10, array( $FLR => array( 0.15, 'kg' ), $BTR => array( 0.03, 'kg' ), $TOM => array( 0.10, 'kg' ), $ONN => array( 0.10, 'kg' ) ) ),
	'Margherita Pizza'       => array( 2, 15, 20, array( $FLR => array( 0.30, 'kg' ), $CHZ => array( 0.20, 'kg' ), $TOM => array( 0.25, 'kg' ), $OIL => array( 0.05, 'l' ) ) ),
	'Pepperoni Pizza'        => array( 2, 15, 22, array( $FLR => array( 0.30, 'kg' ), $CHZ => array( 0.20, 'kg' ), $TOM => array( 0.20, 'kg' ) ) ),
	'Hawaiian Pizza'         => array( 2, 15, 22, array( $FLR => array( 0.30, 'kg' ), $CHZ => array( 0.20, 'kg' ), $TOM => array( 0.15, 'kg' ) ) ),
	'Quattro Formaggi'       => array( 2, 15, 22, array( $FLR => array( 0.30, 'kg' ), $CHZ => array( 0.30, 'kg' ), $BTR => array( 0.05, 'kg' ) ) ),
	'Crispy Fries'           => array( 2, 5, 8, array( $OIL => array( 0.10, 'l' ) ) ),
	'Onion Rings'            => array( 2, 5, 6, array( $FLR => array( 0.10, 'kg' ), $ONN => array( 0.20, 'kg' ), $OIL => array( 0.10, 'l' ) ) ),
	'Coleslaw'               => array( 4, 8, 0, array( $BTR => array( 0.05, 'kg' ), $SUG => array( 0.03, 'kg' ) ) ),
	'Fresh Lemonade'         => array( 1, 3, 0, array( $LEM => array( 2, 'pc' ), $SUG => array( 0.05, 'kg' ) ) ),
	'Iced Tea'               => array( 1, 3, 0, array( $SUG => array( 0.04, 'kg' ), $LEM => array( 1, 'pc' ) ) ),
	'Craft Cola'             => array( 1, 1, 0, array( $SUG => array( 0.08, 'kg' ) ) ),
	'Chocolate Brownie'      => array( 6, 15, 25, array( $FLR => array( 0.20, 'kg' ), $BTR => array( 0.15, 'kg' ), $SUG => array( 0.20, 'kg' ) ) ),
	'Cheesecake Slice'       => array( 8, 20, 0, array( $CHZ => array( 0.15, 'kg' ), $BTR => array( 0.10, 'kg' ), $SUG => array( 0.08, 'kg' ) ) ),
);
$recipe_ids = array();
$recipes    = new Obydullah_ERP_Recipes();
$i          = 0;
foreach ( $recipe_map as $dish_name => $cfg ) {
	$product_id = $dish_by_name[ $dish_name ];
	$ingredients_list = array();
	foreach ( $cfg[3] as $ing_product_id => $qty_unit ) {
		$ingredients_list[] = array(
			'product_id' => $ing_product_id,
			'quantity'   => $qty_unit[0],
			'unit'       => $qty_unit[1],
			'notes'      => '',
		);
	}
	$rid = $recipes->orerp_save_recipe(
		array(
			'product_id'        => $product_id,
			'name'              => $dish_name,
			'servings'          => $cfg[0],
			'prep_time_minutes' => $cfg[1],
			'cook_time_minutes' => $cfg[2],
			'instructions'      => 'Standard preparation procedure for ' . $dish_name . '.',
			'is_active'         => 1,
			'ingredients'       => $ingredients_list,
		)
	);
	if ( ! is_wp_error( $rid ) ) {
		$recipe_ids[] = (int) $rid;
	}
	$i++;
}
WP_CLI::log( 'Recipes: ' . count( $recipe_ids ) . ' created with ingredients.' );

// ------------------------------------------------------------------
// 8. Purchase orders (+ receive + payments).
// ------------------------------------------------------------------
$po_data = array(
	array(
		'supplier_id' => $supplier_ids[1],
		'branch_id'   => $branch_1,
		'status'      => 'received',
		'items'       => array( array( $CHK, 50, 2.90 ), array( $BEEF, 100, 1.85 ), array( $BTR, 20, 3.10 ) ),
		'receive'     => true,
		'payment'     => 0.0,
	),
	array(
		'supplier_id' => $supplier_ids[2],
		'branch_id'   => $branch_1,
		'status'      => 'received',
		'items'       => array( array( $RICE, 200, 1.35 ), array( $FLR, 100, 1.05 ), array( $SUG, 40, 0.75 ), array( $OIL, 30, 2.30 ) ),
		'receive'     => true,
		'payment'     => 1000.0,
	),
	array(
		'supplier_id' => $supplier_ids[0],
		'branch_id'   => $branch_2,
		'status'      => 'received',
		'items'       => array( array( $TOM, 80, 0.60 ), array( $ONN, 100, 0.55 ), array( $LEM, 60, 0.40 ) ),
		'receive'     => true,
		'payment'     => 300.0,
	),
	array(
		'supplier_id' => $supplier_ids[3],
		'branch_id'   => $branch_2,
		'status'      => 'pending',
		'items'       => array( array( $CHZ, 40, 4.40 ), array( $BTR, 25, 3.10 ) ),
		'receive'     => false,
		'payment'     => 0.0,
	),
);
$purchases = new Obydullah_ERP_Purchase_Orders();
$po_ids    = array();
$i         = 0;
foreach ( $po_data as $po ) {
	$items = array();
	foreach ( $po['items'] as $it ) {
		$items[] = array(
			'product_id' => $it[0],
			'quantity'   => $it[1],
			'unit_cost'  => $it[2],
		);
	}
	$pid = $purchases->orerp_save_purchase(
		array(
			'supplier_id'   => $po['supplier_id'],
			'branch_id'     => $po['branch_id'],
			'tax_amount'    => 0,
			'notes'         => 'Weekly provisioning - round ' . ( $i + 1 ),
			'expected_date' => gmdate( 'Y-m-d', strtotime( '+3 days' ) ),
			'items'         => $items,
		)
	);
	if ( is_wp_error( $pid ) ) {
		WP_CLI::warning( 'PO #' . ( $i + 1 ) . ' failed: ' . $pid->get_error_message() );
		$i++;
		continue;
	}
	$po_ids[] = (int) $pid;

	if ( $po['receive'] ) {
		$purchases->orerp_receive_purchase( $pid );
	}
	if ( $po['payment'] > 0 ) {
		$purchases->orerp_add_payment(
			array(
				'purchase_id'    => $pid,
				'amount'         => $po['payment'],
				'payment_method' => 'bank_transfer',
				'reference'      => 'BNK-TRN-' . str_pad( (string) $pid, 4, '0', STR_PAD_LEFT ),
				'notes'          => 'Bank transfer payment',
				'payment_date'   => gmdate( 'Y-m-d', strtotime( '-2 days' ) ),
			)
		);
	}
	$i++;
}
WP_CLI::log( 'Purchase orders: ' . count( $po_ids ) . ' created (' . sizeof( array_filter( array_column( $po_data, 'receive' ) ) ) . ' received).' );

// ------------------------------------------------------------------
// 9. Extra starting stock so every branch shows inventory.
// ------------------------------------------------------------------
$branches = new Obydullah_ERP_Branches();
$base_stock = array(
	$RICE => 150, $CHK => 40, $BEEF => 60, $CHZ => 25, $FLR => 90,
	$OIL => 50, $ONN => 70, $TOM => 55, $BTR => 20, $SUG => 60, $LEM => 80,
);
foreach ( $base_stock as $product_id => $qty ) {
	foreach ( $branch_ids as $bidx => $branch_id ) {
		$branches->orerp_update_branch_stock( $branch_id, $product_id, max( 5, $qty - ( $bidx * 15 ) ) );
	}
}
WP_CLI::log( 'Branch stock: baseline rows populated for ' . count( $branch_ids ) . ' branches.' );

// ------------------------------------------------------------------
// 10. Inter-branch transfers.
// ------------------------------------------------------------------
$transfers = new Obydullah_ERP_Branch_Transfers();
$t1 = $transfers->orerp_create_transfer(
	array(
		'from_branch_id' => $branch_1,
		'to_branch_id'   => $branch_2,
		'notes'          => 'Replenish Gulshan kitchen from Dhanmondi',
		'items'          => wp_json_encode( array( array( 'product_id' => $RICE, 'quantity' => 20 ), array( 'product_id' => $OIL, 'quantity' => 10 ) ) ),
	)
);
if ( ! is_wp_error( $t1 ) ) {
	$transfer = $transfers->orerp_get_transfer( $t1 );
	$received = array();
	if ( $transfer ) {
		foreach ( $transfer->items as $it ) {
			$received[ $it->id ][ $it->product_id ] = $it->quantity;
		}
	}
	$transfers->orerp_receive_transfer( $t1, $received );
	$wpdb->update( "{$prefix}orerp_transfers", array( 'status' => 'received', 'received_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ) ), array( 'id' => $t1 ) );
	WP_CLI::log( 'Transfer #1: received.' );
}

$t2 = $transfers->orerp_create_transfer(
	array(
		'from_branch_id' => $branch_2,
		'to_branch_id'   => $branch_3,
		'notes'          => 'Nightly rebalancing of fryer inventory',
		'items'          => wp_json_encode( array( array( 'product_id' => $OIL, 'quantity' => 15 ), array( 'product_id' => $BEEF, 'quantity' => 12 ) ) ),
	)
);
if ( ! is_wp_error( $t2 ) ) {
	WP_CLI::log( 'Transfer #2: created (in transit).' );
}

// ------------------------------------------------------------------
// 11. WooCommerce orders + kitchen orders.
// ------------------------------------------------------------------
$order_specs = array(
	array( 'kabir', 'Ahmed Hossain', 'completed', array( $dish_by_name['Chicken Biryani'] => 2, $dish_by_name['Crispy Fries'] => 1, $dish_by_name['Craft Cola'] => 2 ), $branch_1 ),
	array( 'sana', 'Sana Rahman', 'completed', array( $dish_by_name['Margherita Pizza'] => 1, $dish_by_name['Onion Rings'] => 1, $dish_by_name['Iced Tea'] => 2 ), $branch_1 ),
	array( 'emon', 'Karim Uddin', 'completed', array( $dish_by_name['BBQ Bacon Burger'] => 3, $dish_by_name['Fresh Lemonade'] => 2 ), $branch_1 ),
	array( 'kabir', 'Nusrat Jahan', 'processing', array( $dish_by_name['Hawaiian Pizza'] => 1, $dish_by_name['Cheesecake Slice'] => 1 ), $branch_2 ),
	array( 'sana', 'Tariq Islam', 'on-hold', array( $dish_by_name['Veggie Burger'] => 2, $dish_by_name['Chocolate Brownie'] => 2 ), $branch_2 ),
	array( 'emon', 'Laila Begum', 'processing', array( $dish_by_name['Chicken Biryani'] => 1, $dish_by_name['Craft Cola'] => 1 ), $branch_3 ),
);

$seeded_order_ids = array();
$kitchen_orders   = new Obydullah_ERP_Order_Workflow();
$ki               = 0;
foreach ( $order_specs as $os ) {
	$order = wc_create_order();
	if ( is_wp_error( $order ) ) {
		WP_CLI::warning( 'Failed to create order for ' . $os[1] );
		continue;
	}

	foreach ( $os[3] as $product_id => $qty ) {
		$product = wc_get_product( $product_id );
		if ( $product ) {
			$order->add_product( $product, $qty );
		}
	}

	$order->set_customer_note( 'Demo order - ' . $os[1] );
	$order->set_address(
		array(
			'first_name' => $os[1],
			'last_name'  => '',
			'address_1'  => '12/A Dhanmondi',
			'city'       => 'Dhaka',
			'postcode'   => '1209',
			'country'    => 'BD',
			'phone'      => '+880 1700 00000' . $ki,
			'email'      => strtolower( $os[0] ) . '.customer@example.test',
		),
		'billing'
	);
	$order->set_payment_method( 'cod' );
	$order->set_payment_method_title( 'Cash on Delivery' );
	$order->update_meta_data( '_orerp_seed_test', '1' );
	$order->update_meta_data( '_orerp_branch_id', $os[4] );
	$order->calculate_totals();

	if ( 'completed' === $os[2] ) {
		$order->payment_complete();
	} else {
		$order->update_status( $os[2] );
	}
	$order->save();

	$order_id = $order->get_id();

	$ko = $kitchen_orders->orerp_create_from_order( $order_id, $os[4], 'Main Kitchen', ( $os[2] === 'completed' ? 0 : 1 ) );
	if ( is_wp_error( $ko ) ) {
		WP_CLI::log( '  Kitchen order for #' . $order_id . ': ' . $ko->get_error_message() );
	} else {
		$seeded_order_ids[] = array( $order_id, $ko, $os[2] );
	}
	$ki++;
}
WP_CLI::log( 'Orders: ' . count( $seeded_order_ids ) . ' created with kitchen tickets.' );

// Advance kitchen order item/statuses for realism.
foreach ( $seeded_order_ids as $ix => $row ) {
	list( $order_id, $ko_id, $status ) = $row;
	$kitchen_order = $kitchen_orders->orerp_get_order( $ko_id );
	if ( ! $kitchen_order ) {
		continue;
	}

	if ( 'completed' === $status ) {
		// Mark each item ready, then the whole order completed.
		foreach ( $kitchen_order->items as $item ) {
			$kitchen_orders->orerp_update_item_status( $item->id, 'ready' );
		}
		$wpdb->update(
			"{$prefix}orerp_kitchen_orders",
			array(
				'status'       => 'completed',
				'started_at'   => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $ix + 1 ) . ' day 09:' . $ix . ':00' ) ),
				'completed_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $ix + 1 ) . ' day 09:' . ( $ix + 12 ) . ':00' ) ),
			),
			array( 'id' => $ko_id )
		);
	} elseif ( 'processing' === $status ) {
		// Some items already in the pan.
		$item_ids = array_keys( (array) $kitchen_order->items );
		foreach ( $item_ids as $k => $item_id ) {
			if ( $k % 2 === 0 ) {
				$kitchen_orders->orerp_update_item_status( $item_id, 'preparing' );
			}
		}
		$wpdb->update(
			"{$prefix}orerp_kitchen_orders",
			array( 'status' => 'preparing', 'started_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-30 minutes' ) ) ),
			array( 'id' => $ko_id )
		);
	}
}

// ------------------------------------------------------------------
// 12. Fiscal periods.
// ------------------------------------------------------------------
$year = (int) gmdate( 'Y' );
$periods = array(
	array( 'FY ' . $year, "{$year}-01-01", "{$year}-12-31" ),
	array( 'January ' . $year, "{$year}-01-01", "{$year}-01-31" ),
	array( 'February ' . $year, "{$year}-02-01", "{$year}-02-28" ),
	array( 'August ' . $year, "{$year}-08-01", "{$year}-08-31" ),
	array( 'September ' . $year, "{$year}-09-01", "{$year}-09-30" ),
);
foreach ( $periods as $p ) {
	$wpdb->insert(
		"{$prefix}orerp_fiscal_periods",
		array( 'name' => $p[0], 'start_date' => $p[1], 'end_date' => $p[2], 'is_closed' => 0 ),
		array( '%s', '%s', '%s', '%d' )
	);
}
WP_CLI::log( 'Fiscal periods: ' . count( $periods ) . ' created.' );

// ------------------------------------------------------------------
// 13. Attendance records (last 5 days).
// ------------------------------------------------------------------
$attendance = new Obydullah_ERP_Attendance();
$att_count  = 0;
foreach ( $employee_ids as $emp_index => $employee_id ) {
	$branch_id = 0 < $emp_index && $emp_index < 3 ? $branch_1 : ( $emp_index < 5 ? $branch_2 : $branch_3 );
	for ( $d = 1; $d <= 5; $d++ ) {
		$clock_in  = gmdate( 'Y-m-d', strtotime( '-' . $d . ' days' ) ) . ' 08:' . ( 0 . ( $emp_index ) ) . ':00';
		$clock_out = gmdate( 'Y-m-d', strtotime( '-' . $d . ' days' ) ) . ' 16:' . ( 0 . ( $emp_index ) ) . ':00';
		$attendance->orerp_save_attendance(
			array(
				'employee_id' => $employee_id,
				'branch_id'   => $branch_id,
				'clock_in'    => $clock_in,
				'clock_out'   => $clock_out,
				'notes'       => 'Regular shift',
			)
		);
		$att_count++;
	}
}
WP_CLI::log( 'Attendance: ' . $att_count . ' records created.' );

// ------------------------------------------------------------------
// 14. Prep tracking for completed kitchen orders.
// ------------------------------------------------------------------
$completed_kos = $wpdb->get_results(
	"SELECT id, order_id FROM {$prefix}orerp_kitchen_orders WHERE status = 'completed' ORDER BY id"
);
$pt_count = 0;
foreach ( $completed_kos as $krow ) {
	$kitchen_order = $kitchen_orders->orerp_get_order( $krow->id );
	if ( ! $kitchen_order || empty( $kitchen_order->items ) ) {
		continue;
	}
	$recipe_row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id FROM {$prefix}orerp_recipes WHERE product_id = %d LIMIT 1",
			(int) $kitchen_order->items[0]->product_id
		)
	);
	$employee_id = $employee_ids[ $pt_count % count( $employee_ids ) ];
	$wpdb->insert(
		"{$prefix}orerp_prep_tracking",
		array(
			'kitchen_order_id'    => $krow->id,
			'recipe_id'           => $recipe_row ? (int) $recipe_row->id : null,
			'employee_id'         => $employee_id,
			'started_at'          => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $pt_count + 1 ) . ' day 09:15:00' ) ),
			'completed_at'        => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $pt_count + 1 ) . ' day 09:45:00' ) ),
			'actual_time_minutes' => 30,
			'notes'               => 'Prep completed for kitchen order #' . $krow->id,
		),
		array( '%d', '%d', '%d', '%s', '%s', '%d', '%s' )
	);
	$pt_count++;
}
WP_CLI::log( 'Prep tracking: ' . $pt_count . ' records created.' );

// ------------------------------------------------------------------
// 15. Manual journal entries (capital + overheads) for the ledger.
// ------------------------------------------------------------------
$journal = new Obydullah_ERP_Journal_Entries();

$e1 = $journal->orerp_create_entry(
	array(
		'date'          => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
		'description'   => 'Owner investment to open the flagship branch',
		'reference_type' => 'seed',
		'reference_id'  => 0,
		'branch_id'     => $branch_1,
		'lines'         => array(
			array( 'account_code' => '1000', 'debit' => 5000.00, 'credit' => 0, 'description' => 'Opening cash' ),
			array( 'account_code' => '3000', 'debit' => 0, 'credit' => 5000.00, 'description' => 'Owner equity' ),
		),
	)
);
WP_CLI::log( $e1 ? 'Journal: owner investment entry created.' : 'Journal: skipped.' );

$e2 = $journal->orerp_create_entry(
	array(
		'date'          => gmdate( 'Y-m-d', strtotime( '-5 days' ) ),
		'description'   => 'Monthly rent - Dhanmondi Flagship',
		'reference_type' => 'seed',
		'reference_id'  => 0,
		'branch_id'     => $branch_1,
		'lines'         => array(
			array( 'account_code' => '6100', 'debit' => 800.00, 'credit' => 0, 'description' => 'Rent expense' ),
			array( 'account_code' => '1000', 'debit' => 0, 'credit' => 800.00, 'description' => 'Cash payment' ),
		),
	)
);
WP_CLI::log( $e2 ? 'Journal: rent entry created.' : 'Journal: skipped.' );

// ------------------------------------------------------------------
// 16. Set branch managers + current branch for clean dashboards.
// ------------------------------------------------------------------
$manager_emp = ! empty( $employee_ids ) ? $employee_ids[0] : 0;
foreach ( $branch_ids as $bidx => $branch_id ) {
	$wpdb->update(
		"{$prefix}orerp_branches",
		array( 'manager_id' => $manager_emp ? (int) $manager_emp : 1 ),
		array( 'id' => $branch_id )
	);
}
update_option( 'orerp_current_branch', $branch_1 );
update_option( 'orerp_default_branch', $branch_1 );

wp_cache_flush();

WP_CLI::log( '== Seeding complete ==' );