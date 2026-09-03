<?php
/**
 * Requirements and add-ons: one schema, one store, one form.
 *
 * Four requirement shapes shared `_wpss_requirements`, the REST validator
 * keyed by an `id` nothing wrote, wpss_service_requirements and
 * wpss_service_addons held rows nothing read, and two buyer form templates
 * rendered different fields (Basecamp 10264288662, 10264294443).
 *
 * Run: wp eval-file tests/test-requirements-one-store.php
 *
 * No strict_types: wp eval-file eval()s the file, and a declare must be the
 * first statement in a script.
 *
 * @package WPSellServices
 */

global $wpdb;

$failures = array();
$posts    = array();
$users    = array();
$orders   = array();

$orders_table = $wpdb->prefix . 'wpss_orders';
$answers      = $wpdb->prefix . 'wpss_order_requirements';

add_filter( 'pre_wp_mail', '__return_false' );
wp_set_current_user( 1 );

if ( ! function_exists( 'wpss_normalize_service_requirements' ) ) {
	echo "FAIL: wpss_normalize_service_requirements() does not exist - there is no canonical requirement schema.\n";
	exit( 1 );
}

$make_service = static function ( string $title ) use ( &$posts ): int {
	$id      = (int) wp_insert_post(
		array(
			'post_type'   => 'wpss_service',
			'post_title'  => $title,
			'post_status' => 'publish',
			'post_author' => 1,
		)
	);
	$posts[] = $id;
	return $id;
};

// 1. Every legacy shape reads back as the one schema, with stable ids.
$shapes = array(
	'wizard'  => array(
		array( 'question' => 'Brand name', 'type' => 'text', 'required' => true, 'options' => '' ),
		array( 'question' => 'Colour', 'type' => 'select', 'required' => true, 'options' => 'Red, Blue' ),
	),
	'metabox' => array(
		array( 'question' => 'Brand name', 'type' => 'text', 'required' => '1' ),
		array( 'question' => 'Colour', 'type' => 'select', 'required' => '1', 'choices' => 'Red, Blue' ),
	),
	'rest'    => array(
		array( 'field_type' => 'text', 'label' => 'Brand name', 'description' => 'As it should appear.', 'options' => array(), 'is_required' => true ),
		array( 'field_type' => 'select', 'label' => 'Colour', 'description' => '', 'options' => array( 'Red', 'Blue' ), 'is_required' => true ),
	),
	'table'   => array(
		array( 'service_id' => 1, 'field_type' => 'select', 'label' => 'Brand name', 'description' => '', 'options' => '', 'is_required' => 1 ),
		array( 'service_id' => 1, 'field_type' => 'select', 'label' => 'Colour', 'description' => '', 'options' => '["Red","Blue"]', 'is_required' => 1 ),
	),
);

foreach ( $shapes as $shape => $rows ) {
	$sid = $make_service( "F15 {$shape} shape" );
	// Written raw, as an upgraded site holds them; the reader must normalise.
	update_post_meta( $sid, '_wpss_requirements', $rows );
	$read = wpss_get_service_requirements( $sid );

	if ( 2 !== count( $read ) ) {
		$failures[] = "{$shape} shape: read back " . count( $read ) . ' requirements, expected 2.';
		continue;
	}
	foreach ( $read as $i => $req ) {
		if ( array_keys( $req ) !== array( 'id', 'label', 'type', 'required', 'options', 'description' ) ) {
			$failures[] = "{$shape} shape: row {$i} keys are " . implode( ',', array_keys( $req ) ) . ' - not the canonical schema.';
		}
	}
	if ( 'brand-name-0' !== $read[0]['id'] || 'colour-1' !== $read[1]['id'] ) {
		$failures[] = "{$shape} shape: ids are {$read[0]['id']} / {$read[1]['id']}, expected brand-name-0 / colour-1.";
	}
	if ( 'Colour' !== $read[1]['label'] || 'select' !== $read[1]['type'] || array( 'Red', 'Blue' ) !== $read[1]['options'] || true !== $read[1]['required'] ) {
		$failures[] = "{$shape} shape: Colour did not normalise to a required select with options Red/Blue.";
	}
	if ( 'table' !== $shape && ( 'text' !== $read[0]['type'] ) ) {
		$failures[] = "{$shape} shape: Brand name type is {$read[0]['type']}, expected text.";
	}
}

// A saved id survives a label edit; the writer keeps what the reader assigned.
$sid = $make_service( 'F15 stable ids' );
wpss_save_service_requirements( $sid, $shapes['wizard'] );
$stored = get_post_meta( $sid, '_wpss_requirements', true );
if ( 'brand-name-0' !== ( $stored[0]['id'] ?? '' ) ) {
	$failures[] = 'The writer did not persist the requirement id.';
}
$stored[0]['label'] = 'Company name';
wpss_save_service_requirements( $sid, $stored );
if ( 'brand-name-0' !== ( wpss_get_service_requirements( $sid )[0]['id'] ?? '' ) ) {
	$failures[] = 'Editing a label changed the requirement id.';
}

// 2. REST submission keyed by id succeeds and stores typed values; wrong types are refused.
$buyer = wp_insert_user(
	array(
		'user_login' => 'wpss_f15_buyer_' . wp_rand( 1000, 9999 ),
		'user_pass'  => wp_generate_password(),
		'role'       => 'subscriber',
	)
);
if ( is_wp_error( $buyer ) ) {
	$failures[] = 'Could not create a buyer.';
	$buyer      = 0;
}
$users[] = $buyer;

$service_id = $make_service( 'F15 REST service' );
wpss_save_service_requirements(
	$service_id,
	array(
		array( 'label' => 'Brand name', 'type' => 'text', 'required' => true ),
		array( 'label' => 'Colour', 'type' => 'select', 'required' => true, 'options' => array( 'Red', 'Blue' ) ),
		array( 'label' => 'Budget', 'type' => 'number', 'required' => false ),
	)
);

$new_order = static function () use ( $wpdb, $orders_table, $buyer, $service_id, &$orders ): int {
	$wpdb->insert(
		$orders_table,
		array(
			'order_number'   => 'WPSS-F15-' . wp_rand( 1000, 9999 ),
			'customer_id'    => $buyer,
			'vendor_id'      => 1,
			'service_id'     => $service_id,
			'platform'       => 'standalone',
			'total'          => 50.000,
			'currency'       => 'USD',
			'status'         => 'pending_requirements',
			'payment_status' => 'paid',
			'created_at'     => current_time( 'mysql' ),
		)
	);
	$orders[] = (int) $wpdb->insert_id;
	return (int) $wpdb->insert_id;
};

$submit = static function ( int $order_id, array $requirements ) use ( $buyer ) {
	wp_set_current_user( $buyer );
	$request = new WP_REST_Request( 'POST', "/wpss/v1/orders/{$order_id}/requirements" );
	$request->set_body_params( array( 'requirements' => $requirements ) );
	$response = rest_do_request( $request );
	wp_set_current_user( 1 );
	return $response;
};

if ( $buyer ) {
	$order_id = $new_order();
	$response = $submit(
		$order_id,
		array(
			'brand-name-0' => 'Acme <b>Ltd</b>',
			'colour-1'     => 'Red',
			'budget-2'     => '250',
		)
	);
	$data     = $response->get_data();
	if ( 200 !== $response->get_status() ) {
		$failures[] = 'REST submit keyed by id answered ' . $response->get_status() . ': ' . wp_json_encode( $data );
	}
	$stored = wpss_get_order_requirements( $order_id );
	if ( 'Acme Ltd' !== ( $stored['brand-name-0'] ?? null ) || 'Red' !== ( $stored['colour-1'] ?? null ) ) {
		$failures[] = 'REST submit did not store answers keyed by id: ' . wp_json_encode( $stored );
	}
	if ( 250 !== ( $stored['budget-2'] ?? null ) ) {
		$failures[] = 'REST submit stored the number field as ' . wp_json_encode( $stored['budget-2'] ?? null ) . ', expected the number 250.';
	}
	$status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$orders_table} WHERE id = %d", $order_id ) );
	if ( 'in_progress' !== $status ) {
		$failures[] = "REST submit left the order {$status}, expected in_progress.";
	}

	$order_id = $new_order();
	$response = $submit( $order_id, array( 'brand-name-0' => array( 'a', 'b' ), 'colour-1' => 'Red' ) );
	if ( 400 !== $response->get_status() ) {
		$failures[] = 'An array for a text field answered ' . $response->get_status() . ', expected 400.';
	}
	if ( null !== $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$answers} WHERE order_id = %d", $order_id ) ) ) {
		$failures[] = 'A rejected submission was still stored.';
	}

	$response = $submit( $order_id, array( 'brand-name-0' => 'Acme', 'colour-1' => 'Green' ) );
	if ( 400 !== $response->get_status() ) {
		$failures[] = 'A value outside the select options answered ' . $response->get_status() . ', expected 400.';
	}

	$response = $submit( $order_id, array( 'colour-1' => 'Blue' ) );
	$data     = $response->get_data();
	if ( 400 !== $response->get_status() || ! isset( $data['data']['errors']['brand-name-0'] ) ) {
		$failures[] = 'A missing required field did not answer 400 naming brand-name-0: ' . wp_json_encode( $data );
	}

	// 3. The buyer form renders the vendor's questions from the same schema.
	$order_id = $new_order();
	$order    = (object) array( 'service_id' => $service_id );
	ob_start();
	include WPSS_PLUGIN_DIR . 'templates/order/requirements-form.php';
	$html = (string) ob_get_clean();
	foreach ( array( 'Brand name', 'Colour', '<option value="Red"', 'name="requirements[0]"', 'type="number"' ) as $needle ) {
		if ( false === strpos( $html, $needle ) ) {
			$failures[] = "The buyer form does not render {$needle}.";
		}
	}
	if ( false !== strpos( $html, 'Project Description' ) ) {
		$failures[] = 'The buyer form fell back to the generic description form although the service has questions.';
	}
}

$tpl = (string) file_get_contents( WPSS_PLUGIN_DIR . 'templates/order/order-requirements.php' );
$view = (string) file_get_contents( WPSS_PLUGIN_DIR . 'templates/order/order-view.php' );
foreach ( array( 'order-requirements.php' => $tpl, 'order-view.php' => $view ) as $name => $src ) {
	if ( false === strpos( $src, "templates/order/requirements-form.php" ) || preg_match( '/<form[^>]*wpss-requirements-form/', $src ) ) {
		$failures[] = "{$name} renders its own requirements form instead of including requirements-form.php.";
	}
}
if ( is_dir( WPSS_PLUGIN_DIR . 'src/CustomFields' ) ) {
	$failures[] = 'src/CustomFields still exists - a second validator.';
}

// 4. Add-ons: post meta drives the modal helper and REST, whatever shape wrote them.
$sid = $make_service( 'F25 add-ons' );
wpss_save_service_addons(
	$sid,
	array(
		array( 'title' => 'Rush', 'price' => '10', 'extra_days' => 2 ),
		array( 'title' => 'Source files', 'description' => 'AI/PSD', 'price' => 15.5, 'delivery_days_extra' => 0 ),
	)
);
$addons = wpss_get_service_extras( $sid );
if ( 2 !== count( $addons ) || 2 !== ( $addons[0]['delivery_days_extra'] ?? null ) || 15.5 !== ( $addons[1]['price'] ?? null ) ) {
	$failures[] = 'The add-on helper did not normalise meta rows: ' . wp_json_encode( $addons );
}
$request  = new WP_REST_Request( 'GET', "/wpss/v1/services/{$sid}/addons" );
$response = rest_do_request( $request );
$data     = $response->get_data();
if ( 200 !== $response->get_status() || 2 !== count( (array) $data ) || 'Rush' !== ( $data[0]['title'] ?? '' ) || 0 !== ( $data[0]['id'] ?? null ) ) {
	$failures[] = 'GET /services/{id}/addons does not read the meta store: ' . wp_json_encode( $data );
}
if ( get_post_meta( $sid, '_wpss_extras', true ) ) {
	$failures[] = 'Add-ons were written to the retired _wpss_extras key.';
}

// 5. Migration: rows in the retired tables land in meta, then the tables are gone.
$req_table   = $wpdb->prefix . 'wpss_service_requirements';
$addon_table = $wpdb->prefix . 'wpss_service_addons';
$charset     = $wpdb->get_charset_collate();
$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$req_table}` ( id bigint(20) unsigned NOT NULL AUTO_INCREMENT, service_id bigint(20) unsigned NOT NULL, field_type varchar(50) DEFAULT 'text', label varchar(255) NOT NULL, description text, options longtext, is_required tinyint(1) DEFAULT 0, sort_order int(11) DEFAULT 0, created_at datetime DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id) ) {$charset}" );
$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$addon_table}` ( id bigint(20) unsigned NOT NULL AUTO_INCREMENT, service_id bigint(20) unsigned NOT NULL, title varchar(255) NOT NULL, description text, price decimal(10,2) NOT NULL DEFAULT 0, delivery_days_extra int(11) DEFAULT 0, is_active tinyint(1) DEFAULT 1, sort_order int(11) DEFAULT 0, PRIMARY KEY (id) ) {$charset}" );

$migrated = $make_service( 'F25 migrated from tables' );
$kept     = $make_service( 'F25 keeps its meta' );
wpss_save_service_requirements( $kept, array( array( 'label' => 'Kept question', 'type' => 'text' ) ) );
$wpdb->insert( $req_table, array( 'service_id' => $migrated, 'field_type' => 'textarea', 'label' => 'From the table', 'description' => 'Copied on upgrade.', 'is_required' => 1, 'sort_order' => 1 ) );
$wpdb->insert( $req_table, array( 'service_id' => $kept, 'field_type' => 'text', 'label' => 'Stale table row', 'is_required' => 0, 'sort_order' => 1 ) );
$wpdb->insert( $addon_table, array( 'service_id' => $migrated, 'title' => 'Table add-on', 'price' => 12.00, 'delivery_days_extra' => 3 ) );

update_option( 'wpss_db_version', '1.7.1' );
( new \WPSellServices\Database\SchemaManager() )->install();

$req = wpss_get_service_requirements( $migrated );
if ( 'From the table' !== ( $req[0]['label'] ?? '' ) || 'textarea' !== ( $req[0]['type'] ?? '' ) || true !== ( $req[0]['required'] ?? null ) ) {
	$failures[] = 'Requirement rows were not copied into meta on upgrade: ' . wp_json_encode( $req );
}
$req = wpss_get_service_requirements( $kept );
if ( 1 !== count( $req ) || 'Kept question' !== $req[0]['label'] ) {
	$failures[] = 'A service with its own requirements was overwritten by stale table rows.';
}
$ext = wpss_get_service_extras( $migrated );
if ( 'Table add-on' !== ( $ext[0]['title'] ?? '' ) || 3 !== ( $ext[0]['delivery_days_extra'] ?? null ) ) {
	$failures[] = 'Add-on rows were not copied into meta on upgrade: ' . wp_json_encode( $ext );
}
foreach ( array( $req_table, $addon_table ) as $table ) {
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
		$failures[] = "{$table} still exists after the upgrade.";
	}
}
$schema_src = (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Database/SchemaManager.php' );
if ( ! preg_match( "/'service_addons'\s*=>\s*'[^']*_wpss_addons/", $schema_src ) ) {
	$failures[] = 'wpss_service_addons is not in RETIRED_TABLES pointing at _wpss_addons.';
}

// Cleanup.
foreach ( $orders as $oid ) {
	$wpdb->delete( $answers, array( 'order_id' => $oid ) );
	$wpdb->delete( $orders_table, array( 'id' => $oid ) );
}
foreach ( $posts as $pid ) {
	wp_delete_post( $pid, true );
}
foreach ( array_filter( $users ) as $uid ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $uid );
}

if ( $failures ) {
	echo "FAIL\n - " . implode( "\n - ", $failures ) . "\n";
	exit( 1 );
}

echo "PASS: one requirement schema, one add-on store, one form, tables retired.\n";
