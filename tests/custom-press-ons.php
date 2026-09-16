<?php
if (PHP_SAPI !== 'cli') { exit; }
require __DIR__ . '/pagination.php';
$start = $checks;
$method = new ReflectionMethod(LatePoint_Dashboard_Extender::class, 'get_press_ons_orders');
$GLOBALS['paging_orders'] = array(
    new WC_Order(9, array(77)),
    new WC_Order(8, array(1, 77)),
    new WC_Order(7, array(new PagingProduct(200, 77))),
    new WC_Order(6, array(1)),
    new WC_Order(5, array(null)),
    new WC_Order(4, array()),
    new WC_Order(3, array(99)),
    new WC_Order(2, array(77), 900)
);
$_GET = array();
check(ids($method->invoke(null, true)) === array(9, 8, 7), 'Custom subset includes category, mixed orders and variation parents only');
check(ids($method->invoke(null, false)) === array(9, 8, 7, 6, 5, 4), 'Original list still includes custom and mixed orders');
$x = xpath_for(render_dashboard(dashboard()));
foreach (array('press-ons', 'custom-press-ons') as $view) {
    $mixed = $x->query('//div[@data-ishi-view="' . $view . '"]//article[@data-order-id="8"]')->item(0);
    check($mixed !== null, 'Mixed order appears in ' . $view);
    check($x->query('.//div[@class="ishi-press-ons-order-item"]', $mixed)->length === 2, 'Complete mixed order items retained in ' . $view);
    check($x->query('.//a[@data-os-action="ishi_press_ons__view_order_in_lightbox"]', $mixed)->length === 1, 'Shared secured lightbox retained in ' . $view);
}
$GLOBALS['paging_orders'] = array();
for ($id = 1; $id <= 23; $id++) { $GLOBALS['paging_orders'][] = new WC_Order($id, array(77)); }
for ($id = 24; $id <= 125; $id++) { $GLOBALS['paging_orders'][] = new WC_Order($id, array(1)); }
$_GET = array('ishi_custom_press_ons_page' => '2');
$result = $method->invoke(null, true);
check(ids($result) === range(13, 4) && $result['has_next'], 'Custom pagination counts matches before filling page');
$GLOBALS['paging_url'] = '/?page_id=12&lang=en&ishi_press_ons_page=7&ishi_custom_press_ons_page=2';
$x = xpath_for(render_dashboard(dashboard()));
check($x->query('//div[@data-ishi-view="custom-press-ons" and contains(@class,"active")]')->length === 1, 'Custom reload selects correct submenu');
$link = $x->query('//div[@data-ishi-view="custom-press-ons"]//a[@rel="next"]')->item(0);
parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
check($query === array('page_id' => '12', 'lang' => 'en', 'ishi_custom_press_ons_page' => '3'), 'Custom links preserve unrelated parameters and remove original page state');
$link = $x->query('//div[@data-ishi-view="press-ons"]//a[@rel="next"]')->item(0);
parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
check(!isset($query['ishi_custom_press_ons_page']) && isset($query['ishi_press_ons_page']), 'Original pagination removes custom page state');
$_GET = array('ishi_custom_press_ons_page' => '999');
check(ids($method->invoke(null, true)) === array(3, 2, 1), 'Custom out-of-range page clamps to last matching page');
$_GET = array('ishi_custom_press_ons_page' => array('2'));
check(ids($method->invoke(null, true)) === range(23, 14), 'Invalid custom page falls back safely');
$GLOBALS['paging_orders'] = array(new WC_Order(1, array(1)));
check($method->invoke(null, true)['orders'] === array(), 'No category match is empty, never an unfiltered fallback');
$GLOBALS['paging_logged_in'] = false;
$GLOBALS['paging_queries'] = array();
check($method->invoke(null, true)['orders'] === array() && !$GLOBALS['paging_queries'], 'Logged-out custom requests never query orders');
echo 'PASS: ' . ($checks - $start) . " custom category checks\n";
