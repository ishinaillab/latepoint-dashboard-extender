<?php
/**
 * Pagination regression tests. The existing runner establishes the WordPress
 * doubles, loads the plugin, and tests its behavior without the order API.
 */
if (PHP_SAPI !== 'cli') {
    exit;
}
require __DIR__ . '/run.php';

// Conditional declarations make the order API available after the base tests.
if (!function_exists('wc_get_orders')) {
    function wp_unslash($value) { return $value; }
    function is_user_logged_in() { return $GLOBALS['paging_logged_in']; }
    function get_current_user_id() { return 7; }
    function get_option($name, $default = false) { return $name === 'posts_per_page' ? $GLOBALS['paging_default'] : $default; }
    function apply_filters($tag, $value, ...$args) { return test_apply($tag, $value, ...$args); }
    function absint($value) { return abs((int) $value); }
    function sanitize_html_class($value) { return $value; }
    function esc_attr($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
    function esc_url_raw($url) { return $url; }
    function add_query_arg($name, $value) {
        $parts = parse_url($GLOBALS['paging_url']);
        parse_str($parts['query'] ?? '', $query);
        $query[$name] = $value;
        return $parts['path'] . '?' . http_build_query($query);
    }
    function remove_query_arg($name, $url) {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);
        unset($query[$name]);
        return $parts['path'] . '?' . http_build_query($query);
    }
    function wc_get_order_types($context) { return array('shop_order'); }
    function wc_get_order_statuses() { return array('wc-completed' => 'Completed'); }
    function wc_get_order_status_name($status) { return 'Completed'; }
    function wc_price($amount, $args = array()) { return '<span class="amount">$' . $amount . '</span>'; }
    function has_term($term, $taxonomy, $id) { return $term === 'latepoint' ? $id === 99 : ($term === 'custom-press-ons' && $id === 77); }
    function wc_get_orders($args) {
        $GLOBALS['paging_queries'][] = $args;
        $orders = array_filter($GLOBALS['paging_orders'], function ($order) use ($args) {
            return $order->get_customer_id() === $args['customer_id'];
        });
        usort($orders, function ($a, $b) { return $b->get_id() <=> $a->get_id(); });
        return array_slice(array_values($orders), $args['offset'], $args['limit']);
    }
    class PagingProduct {
        private $id;
        private $parent;
        public function __construct($id, $parent = 0) { $this->id = $id; $this->parent = $parent; }
        public function get_id() { return $this->id; }
        public function is_type($type) { return $type === 'variation' && $this->parent > 0; }
        public function get_parent_id() { return $this->parent; }
        public function get_image_id() { return 0; }
    }
    class PagingItem {
        private $product;
        public function __construct($product) { $this->product = $product; }
        public function get_product() { return $this->product; }
        public function get_name() { return 'Product'; }
        public function get_quantity() { return 1; }
    }
    class WC_Order {
        private $id;
        private $items;
        private $customer;
        public function __construct($id, $products = array(1), $customer = 7) {
            $this->id = $id;
            $this->customer = $customer;
            $this->items = array_map(function ($product) {
                return new PagingItem($product === null ? null : ($product instanceof PagingProduct ? $product : new PagingProduct($product)));
            }, $products);
        }
        public function get_id() { return $this->id; }
        public function get_customer_id() { return $this->customer; }
        public function get_items($type = 'line_item') { return $type === 'line_item' ? $this->items : array(); }
        public function get_type() { return 'shop_order'; }
        public function get_status() { return 'completed'; }
        public function get_order_number() { return (string) $this->id; }
        public function get_date_created() { return false; }
        public function get_subtotal() { return 100; }
        public function get_discount_total() { return 0; }
        public function get_shipping_total() { return 0; }
        public function get_fees() { return array(); }
        public function get_total_tax() { return 0; }
        public function get_total() { return 100; }
        public function get_currency() { return 'USD'; }
    }
    class OsUtilHelper {
        public static function build_os_params($params) { return http_build_query($params); }
    }
    class OsRouterHelper {
        public static function build_route_name($controller, $action) { return $controller . '__' . $action; }
    }
}
$GLOBALS['paging_logged_in'] = true;
$GLOBALS['paging_default'] = 10;
$GLOBALS['paging_url'] = '/?page_id=12&lang=en&ishi_press_ons_page=2';
$GLOBALS['paging_orders'] = array();
$GLOBALS['paging_queries'] = array();
$start_checks = $checks;
function page_result($page = null) {
    $_GET = $page === null ? array() : array('ishi_press_ons_page' => $page);
    $GLOBALS['paging_queries'] = array();
    $method = new ReflectionMethod(LatePoint_Dashboard_Extender::class, 'get_press_ons_orders');
    return $method->invoke(null);
}
function ids($result) { return array_keys($result['orders']); }
for ($id = 1; $id <= 23; $id++) {
    $GLOBALS['paging_orders'][] = new WC_Order($id);
}
$first = page_result();
check(ids($first) === range(23, 14), 'WooCommerce default displays ten orders');
check($first['page'] === 1 && $first['has_next'], 'First page detects next eligible order');
$second = page_result('2');
check(ids($second) === range(13, 4) && $second['has_next'], 'Second page has ten different orders');
$last = page_result('3');
check(ids($last) === array(3, 2, 1) && !$last['has_next'], 'Final page contains remainder');
check(ids(page_result('999999999')) === array(3, 2, 1), 'Out-of-range page resolves to last nonempty page');
check(page_result('999999999')['page'] === 3, 'Out-of-range page label is corrected');
foreach (array('0', '-1', 'no', '1.5', array('2'), str_repeat('9', 100)) as $invalid) {
    check(ids(page_result($invalid)) === ids($first), 'Invalid page falls back to page one');
}
$GLOBALS['paging_default'] = 7;
check(count(page_result()['orders']) === 7, 'Uses configured posts_per_page rather than hardcoded ten');
$filter = function ($args) { $args['limit'] = 4; $args['customer'] = 999; return $args; };
add_filter('woocommerce_my_account_my_orders_query', $filter);
check(count(page_result()['orders']) === 4, 'Honors positive My Account limit override');
check($GLOBALS['paging_queries'][0]['customer_id'] === 7, 'My Account filter cannot replace customer scope');
$GLOBALS['test_hooks']['woocommerce_my_account_my_orders_query'] = array();
add_filter('woocommerce_my_account_my_orders_query', function ($args) { $args['limit'] = -1; return $args; });
check(count(page_result()['orders']) === 7, 'Unlimited override falls back to finite WooCommerce default');
$GLOBALS['test_hooks']['woocommerce_my_account_my_orders_query'] = array();
$GLOBALS['paging_default'] = 10;
$GLOBALS['paging_orders'] = array();
$eligible = array();
for ($id = 1; $id <= 140; $id++) {
    $products = $id % 4 === 0 ? array(99, 1) : array(99);
    $GLOBALS['paging_orders'][] = new WC_Order($id, $products);
    if ($id % 4 === 0) { $eligible[] = $id; }
}
rsort($eligible);
$seen = array();
for ($page = 1; $page <= 4; $page++) {
    $result = page_result((string) $page);
    check(ids($result) === array_slice($eligible, ($page - 1) * 10, 10), 'Exclusion happens before page slicing');
    check($result['has_next'] === ($page < 4), 'Next link reflects eligible orders');
    $seen = array_merge($seen, ids($result));
    foreach ($GLOBALS['paging_queries'] as $query) {
        check($query['limit'] === 50 && $query['paginate'] === false, 'Order queries use bounded batches without total counts');
        check($query['orderby'] === array('date' => 'DESC', 'ID' => 'DESC'), 'Dates use a stable ID tie-breaker');
        check($query['type'] === array('shop_order') && $query['status'] === array('wc-completed'), 'Customer-viewable order scope preserved');
    }
}
check($seen === $eligible, 'All eligible orders appear exactly once across pages');
$GLOBALS['paging_orders'] = array(
    new WC_Order(10, array(99)),
    new WC_Order(9, array(new PagingProduct(500, 99))),
    new WC_Order(8, array(new PagingProduct(501, 1))),
    new WC_Order(7, array(null)),
    new WC_Order(6, array()),
    new WC_Order(5, array(99, 1)),
    new WC_Order(4, array(1), 888),
);
check(ids(page_result()) === array(8, 7, 6, 5), 'Variation parents, missing products, empty and mixed orders keep existing rules');
$GLOBALS['paging_orders'] = array();
for ($id = 1; $id <= 120; $id++) {
    $GLOBALS['paging_orders'][] = new WC_Order($id, $id > 11 ? array(99) : array(1));
}
$after_excluded = page_result();
check(ids($after_excluded) === range(11, 2) && $after_excluded['has_next'], 'Entire excluded batches do not create blank pages');
check(count($GLOBALS['paging_queries']) === 3, 'Scanning crosses excluded batches only as needed');
$GLOBALS['paging_orders'] = array(new WC_Order(1, array(99)));
check(page_result()['orders'] === array() && !page_result()['has_next'], 'Only LatePoint orders produce empty Press-Ons page');
$GLOBALS['paging_orders'] = array();
check(page_result('5')['page'] === 1, 'No orders resolve to empty first page');
$GLOBALS['paging_logged_in'] = false;
check(page_result()['orders'] === array() && $GLOBALS['paging_queries'] === array(), 'Logged-out requests never query orders');
$GLOBALS['paging_logged_in'] = true;
for ($id = 1; $id <= 500; $id++) { $GLOBALS['paging_orders'][] = new WC_Order($id); }
page_result();
check(count($GLOBALS['paging_queries']) === 1, 'First page stops without scanning entire history');
$GLOBALS['paging_orders'] = array();
for ($id = 1; $id <= 20; $id++) { $GLOBALS['paging_orders'][] = new WC_Order($id); }
check(!page_result('2')['has_next'], 'Exact final page does not create an empty next page');

$_GET = array('ishi_press_ons_page' => '2');
$output = render_dashboard(dashboard());
$x = xpath_for($output);
check($x->query('//article[@data-order-id]')->length === 10, 'Only current-page cards are rendered');
check($x->query('//button[contains(concat(" ",normalize-space(@class)," ")," active ")]')->item(0)->getAttribute('data-tab-target') === '.tab-content-ishi-customer-press-ons', 'Press-Ons remains selected on pagination');
check($x->query('//div[contains(concat(" ",normalize-space(@class)," ")," latepoint-tab-content ") and contains(concat(" ",normalize-space(@class)," ")," active ")]')->length === 1, 'Exactly one active panel');
$previous = $x->query('//nav[@class="ishi-press-ons-pagination"]/a[@rel="prev"]')->item(0);
check($previous !== null, 'Second page has Previous link');
parse_str(parse_url($previous->getAttribute('href'), PHP_URL_QUERY), $query);
check($query === array('page_id' => '12', 'lang' => 'en', 'ishi_press_ons_page' => '1'), 'Previous preserves permalink and unrelated parameters');
check($x->query('//nav/a[@rel="next"]')->length === 0, 'Last page has no Next link');
$_GET = array('ishi_press_ons_page' => '1');
$x = xpath_for(render_dashboard(dashboard()));
check($x->query('//nav/a[@rel="prev"]')->length === 0 && $x->query('//nav/a[@rel="next"]')->length === 1, 'First page has only Next');
$_GET = array();
$x = xpath_for(render_dashboard(dashboard()));
check($x->query('//button[contains(concat(" ",normalize-space(@class)," ")," active ")]')->item(0)->getAttribute('data-tab-target') === '.tab-content-customer-bookings', 'Normal visit retains default Appointments selection');
$GLOBALS['paging_orders'] = array(new WC_Order(1));
$x = xpath_for(render_dashboard(dashboard()));
check($x->query('//nav[@class="ishi-press-ons-pagination"]')->length === 0, 'Single page has no pagination controls');
$GLOBALS['paging_queries'] = array();
$native_html = dashboard();
check(count($GLOBALS['paging_queries']) === 2, 'Each order view queries once per native render');
$once_filtered = render_dashboard($native_html);
render_dashboard($once_filtered);
check(count($GLOBALS['paging_queries']) === 2, 'Shortcode post-processing never repeats either order query');
check(xpath_for($once_filtered)->query('//article[@data-order-id]')->length === 1, 'Prepared order cards are emitted once by native content hook');
echo 'PASS: ' . ($checks - $start_checks) . " pagination checks\n";
