<?php
/**
 * Smoke tests for product sales / buyers helpers (no WordPress bootstrap).
 * Run: php hesabdar/tests/smoke-product-reports.php
 */
error_reporting( E_ALL );

function absint( $v ) { return abs( (int) $v ); }
function sanitize_text_field( $v ) { return is_string( $v ) ? trim( strip_tags( $v ) ) : (string) $v; }
function apply_filters( $tag, $value ) { return $value; }
function taxonomy_exists( $t ) { return false; }
function get_terms( $a ) { return array(); }
function wc_get_product( $id ) { return null; }
function wc_get_product_term_ids( $id, $tax ) { return array(); }
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

require_once dirname( __DIR__ ) . '/includes/class-wap-data.php';

class FakeItem {
	private $pid;
	private $vid;
	private $qty;
	private $total;
	private $name;
	public function __construct( $pid, $qty, $total, $name = 'P', $vid = 0 ) {
		$this->pid   = $pid;
		$this->vid   = $vid;
		$this->qty   = $qty;
		$this->total = $total;
		$this->name  = $name;
	}
	public function get_product_id() { return $this->pid; }
	public function get_variation_id() { return $this->vid; }
	public function get_quantity() { return $this->qty; }
	public function get_total() { return $this->total; }
	public function get_name() { return $this->name; }
	public function get_product() { return null; }
}

class FakeDate {
	private $ts;
	public function __construct( $ts ) { $this->ts = $ts; }
	public function getTimestamp() { return $this->ts; }
	public function date( $fmt ) { return gmdate( $fmt, $this->ts ); }
}

class FakeOrder {
	private $id;
	private $status;
	private $items;
	private $customer_id;
	private $email;
	private $phone;
	private $name;
	private $city;
	private $ts;
	public function __construct( $args ) {
		$this->id          = $args['id'];
		$this->status      = $args['status'];
		$this->items       = $args['items'];
		$this->customer_id = $args['customer_id'] ?? 0;
		$this->email       = $args['email'] ?? '';
		$this->phone       = $args['phone'] ?? '';
		$this->name        = $args['name'] ?? '';
		$this->city        = $args['city'] ?? '';
		$this->ts          = $args['ts'] ?? time();
	}
	public function get_id() { return $this->id; }
	public function get_status() { return $this->status; }
	public function get_items() { return $this->items; }
	public function get_customer_id() { return $this->customer_id; }
	public function get_billing_email() { return $this->email; }
	public function get_billing_phone() { return $this->phone; }
	public function get_billing_city() { return $this->city; }
	public function get_formatted_billing_full_name() { return $this->name; }
	public function get_date_created() { return new FakeDate( $this->ts ); }
}

$orders = array(
	new FakeOrder( array(
		'id' => 1, 'status' => 'completed', 'customer_id' => 11, 'email' => 'a@x.com', 'phone' => '09120000001',
		'name' => 'علی تست', 'city' => 'تهران', 'ts' => 1700000000,
		'items' => array( new FakeItem( 100, 2, 200000, 'کفش' ) ),
	) ),
	new FakeOrder( array(
		'id' => 2, 'status' => 'processing', 'customer_id' => 11, 'email' => 'a@x.com', 'phone' => '09120000001',
		'name' => 'علی تست', 'city' => 'تهران', 'ts' => 1700001000,
		'items' => array( new FakeItem( 100, 1, 100000, 'کفش' ), new FakeItem( 200, 3, 90000, 'جوراب' ) ),
	) ),
	new FakeOrder( array(
		'id' => 3, 'status' => 'pending', 'customer_id' => 0, 'email' => 'b@x.com', 'phone' => '09120000002',
		'name' => 'مهمان', 'city' => 'شیراز', 'ts' => 1700002000,
		'items' => array( new FakeItem( 100, 5, 500000, 'کفش' ) ),
	) ),
	new FakeOrder( array(
		'id' => 4, 'status' => 'completed', 'customer_id' => 22, 'email' => 'c@x.com', 'phone' => '09120000003',
		'name' => 'سارا', 'city' => 'اصفهان', 'ts' => 1700003000,
		'items' => array( new FakeItem( 200, 1, 30000, 'جوراب' ) ),
	) ),
);

$sales_paid = WAP_Data::get_product_sales( $orders, true, 0 );
assert( count( $sales_paid ) === 2, 'two products in paid-only sales' );
$by_pid = array();
foreach ( $sales_paid as $p ) { $by_pid[ $p['pid'] ] = $p; }
assert( $by_pid[100]['qty'] === 3, 'product 100 qty paid-only = 3 (pending excluded)' );
assert( $by_pid[200]['qty'] === 4, 'product 200 qty = 4' );

$sales_all_status = WAP_Data::get_product_sales( $orders, false, 0 );
$by_pid2 = array();
foreach ( $sales_all_status as $p ) { $by_pid2[ $p['pid'] ] = $p; }
assert( $by_pid2[100]['qty'] === 8, 'product 100 qty with pending included = 8' );

$buyers = WAP_Data::get_buyers_by_products( $orders, array( 100 ), true );
assert( count( $buyers ) === 1, 'one unique buyer for product 100 (paid)' );
assert( $buyers[0]['customer_id'] === 11, 'buyer is customer 11' );
assert( $buyers[0]['qty'] === 3, 'buyer qty 3' );
assert( $buyers[0]['orders_count'] === 2, 'buyer has 2 orders' );

$buyers_multi = WAP_Data::get_buyers_by_products( $orders, array( 100, 200 ), true );
assert( count( $buyers_multi ) === 2, 'two buyers for products 100 or 200' );

$buyers_pending = WAP_Data::get_buyers_by_products( $orders, array( 100 ), false );
assert( count( $buyers_pending ) === 2, 'two buyers when unpaid included' );

assert( WAP_Data::parse_ids( '1,2,2' ) === array( 1, 2 ), 'parse_ids' );
assert( WAP_Data::should_require_paid( array( 'order_status' => '' ) ) === true, 'paid when no status' );
assert( WAP_Data::should_require_paid( array( 'order_status' => 'pending' ) ) === false, 'not paid-only when status set' );

echo "ALL SMOKE TESTS PASSED\n";
