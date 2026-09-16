<?php
/** List/lightbox parity and dependency failure regression tests. */
if (PHP_SAPI !== 'cli') { exit; }
if (in_array('--without-woocommerce', $argv, true)) {
    require __DIR__ . '/run.php';
    function absint($value) { return abs((int) $value); }
    function is_user_logged_in() { return true; }
    define('LATEPOINT_STATUS_ERROR', 'error');
    $controller = new OsIshiPressOnsController();
    $controller->params = array('order_id' => 1);
    $controller->view_order_in_lightbox();
    check($controller->response['status'] === 'error' && $controller->rendered === null, 'Missing WooCommerce returns an error without rendering');
    check(!LatePoint_Dashboard_Extender::is_press_ons_order_for_customer(null, 7), 'Unavailable order class fails closed');
    echo "PASS: missing WooCommerce endpoint checks\n";
    exit;
}
require __DIR__ . '/pagination.php';
define('LATEPOINT_STATUS_ERROR', 'error');
if (!function_exists('wc_get_order')) {
    function wc_get_order($id) {
        $GLOBALS['lookup_count']++;
        return $GLOBALS['endpoint_order'];
    }
}
class HiddenTypeOrder extends WC_Order {
    public function get_type() { return 'shop_order_refund'; }
}
class HiddenStatusOrder extends WC_Order {
    public function get_status() { return 'checkout-draft'; }
}
$start_checks = $checks;
$GLOBALS['paging_logged_in'] = true;
$cases = array(
    array(new WC_Order(1), true),
    array(new WC_Order(1, array(99)), false),
    array(new WC_Order(1, array(99, 1)), true),
    array(new WC_Order(1, array(null)), true),
    array(new WC_Order(1, array()), true),
    array(new WC_Order(1, array(new PagingProduct(100, 99))), false),
    array(new WC_Order(1, array(1), 8), false),
    array(new HiddenTypeOrder(1), false),
    array(new HiddenStatusOrder(1), false),
);
foreach ($cases as $case) {
    list($order, $allowed) = $case;
    check(LatePoint_Dashboard_Extender::is_press_ons_order_for_customer($order, 7) === $allowed, 'Shared eligibility matches expected policy');
    $GLOBALS['paging_orders'] = array($order);
    check((count(page_result()['orders']) === 1) === $allowed, 'List applies the shared policy');
    $GLOBALS['endpoint_order'] = $order;
    $GLOBALS['lookup_count'] = 0;
    $controller = new OsIshiPressOnsController();
    $controller->params = array('order_id' => 1);
    $controller->view_order_in_lightbox();
    check(($controller->rendered === 'view_order_in_lightbox') === $allowed, 'Lightbox matches list eligibility');
    check($allowed ? $controller->vars['order'] === $order : $controller->response['status'] === 'error', 'Rejected requests never render order details');
}
$GLOBALS['endpoint_order'] = false;
$controller = new OsIshiPressOnsController();
$controller->params = array('order_id' => 1);
$controller->view_order_in_lightbox();
check($controller->response['message'] === 'Order Not Found' && $controller->rendered === null, 'Missing order fails closed');
foreach (array(array(true, 0), array(false, 1)) as $request) {
    $GLOBALS['paging_logged_in'] = $request[0];
    $GLOBALS['lookup_count'] = 0;
    $controller = new OsIshiPressOnsController();
    $controller->params = array('order_id' => $request[1]);
    $controller->view_order_in_lightbox();
    check($GLOBALS['lookup_count'] === 0 && $controller->rendered === null, 'Invalid or logged-out requests stop before order lookup');
}
check(!LatePoint_Dashboard_Extender::is_press_ons_order_for_customer(new WC_Order(1, array(1), 0), 0), 'Guest customer ID cannot authorize an order');
echo 'PASS: ' . ($checks - $start_checks) . " shared-policy/lightbox checks\n";
