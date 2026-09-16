<?php
/**
 * Exercise the native trigger/content lifecycle, not HTML-insertion fallbacks.
 */
if (PHP_SAPI !== 'cli') {
    exit;
}
require __DIR__ . '/run.php';
$start_checks = $checks;
$GLOBALS['address_enabled'] = true;
$GLOBALS['messages_enabled'] = true;
$GLOBALS['address_output'] = '<form id="native-addresses"><input name="city" value="Manila"></form>';
$before = $GLOBALS['shortcode_calls'];
$raw = dashboard();
$x = xpath_for($raw);
check($GLOBALS['shortcode_calls'] === $before + 1, 'Native hooks render shortcode once before output filters');
check($x->query('//a[@data-ishi-dashboard-tab="press-ons"]')->length === 1, 'Native trigger hook emits Press-Ons link');
check($x->query('//a[@data-ishi-dashboard-tab="addresses"]')->length === 1, 'Native trigger hook emits Addresses link');
check($x->query('//div[@data-ishi-dashboard-tab="press-ons"]')->length === 1, 'Native content hook emits Press-Ons panel');
check($x->query('//div[@data-ishi-dashboard-tab="addresses"]')->length === 1, 'Native content hook emits Addresses panel');
check($x->query('//form[@id="native-addresses"]')->length === 1, 'Prepared Addresses HTML emitted once');
check($x->query('//a[contains(@class,"latepoint-trigger-messages-tab")]')->length === 1, 'Pro Messages hook coexists at priority ten');
check(strpos($raw, '>Orders</a>') !== false, 'Native render precedes Orders label filter');
$before = $GLOBALS['shortcode_calls'];
$filtered = render_dashboard($raw);
check($GLOBALS['shortcode_calls'] === $before, 'Shortcode filters do not render Addresses again');
check(sequence($filtered) === $expected, 'Requested tab order retained after native rendering');
check(strpos($filtered, '>History</a>') !== false, 'Orders is still renamed History');
check(render_dashboard($filtered) === $filtered, 'Re-filtering does not duplicate custom tabs');

$without_hooks = render_dashboard(dashboard(false));
check(strpos($without_hooks, 'data-ishi-dashboard-tab') === false, 'Output filter never inserts custom tabs as a fallback');
check($GLOBALS['shortcode_calls'] === $before, 'No hook means no Addresses evaluation');
$without_hooks_calls = $GLOBALS['shortcode_calls'];
check(render_dashboard('<p>Login required</p>') === '<p>Login required</p>', 'Logged-out output remains untouched');
check($GLOBALS['shortcode_calls'] === $without_hooks_calls, 'Logged-out output does not call Addresses');

$customer = (object) array('id' => 42);
$first = render_dashboard(dashboard(true, $customer));
$second = render_dashboard(dashboard(true, $customer));
check($GLOBALS['shortcode_calls'] === $before + 2, 'Same customer can render two independent dashboards');
check(xpath_for($first . $second)->query('//form[@id="native-addresses"]')->length === 2, 'Each dashboard has its own Addresses form');

$GLOBALS['messages_enabled'] = false;
check(test_action_output('latepoint_customer_dashboard_after_tab_contents', $customer) === '', 'No orphan content emitted after frames are consumed');
$without_messages = render_dashboard(dashboard());
check(sequence($without_messages) === array_slice($expected, 0, 6), 'Messages can be disabled without disturbing custom tabs');

// Content must use the fragment prepared at the trigger hook even if providers change.
$before = $GLOBALS['shortcode_calls'];
$trigger_html = test_action_output('latepoint_customer_dashboard_after_tabs', $customer);
$GLOBALS['address_output'] = '<p>Replacement must not be evaluated</p>';
$content_html = test_action_output('latepoint_customer_dashboard_after_tab_contents', $customer);
check($GLOBALS['shortcode_calls'] === $before + 1, 'Content hook reuses prepared Addresses fragment');
check(strpos($content_html, 'native-addresses') !== false && strpos($content_html, 'Replacement') === false, 'Trigger/content pair uses the same render');
check(strpos($trigger_html, 'data-ishi-dashboard-tab="addresses"') !== false, 'Prepared address panel has a matching trigger');
check(test_action_output('latepoint_customer_dashboard_after_tab_contents', $customer) === '', 'Repeated content callback does not duplicate panels');

// Nested rendering from a different native callback must not consume the parent's frame.
$GLOBALS['address_output'] = '<p>Outer</p>';
$outer_links = test_action_output('latepoint_customer_dashboard_after_tabs', $customer);
$GLOBALS['address_output'] = '<p>Inner</p>';
$inner_dashboard = dashboard(true, (object) array('id' => 43));
$outer_content = test_action_output('latepoint_customer_dashboard_after_tab_contents', $customer);
check(strpos($inner_dashboard, '<p>Inner</p>') !== false && strpos($inner_dashboard, '<p>Outer</p>') === false, 'Nested render has independent prepared content');
check(strpos($outer_content, '<p>Outer</p>') !== false && strpos($outer_content, '<p>Inner</p>') === false, 'Parent frame survives nested hooks');
check(test_action_output('latepoint_customer_dashboard_after_tab_contents', $customer) === '', 'Nested frames fully consumed');

// A shortcode that itself renders a dashboard must not recurse through our tabs.
$GLOBALS['address_output'] = function () { return dashboard(); };
$before = $GLOBALS['shortcode_calls'];
$recursive = dashboard();
check($GLOBALS['shortcode_calls'] === $before + 1, 'Recursive shortcode guarded against repeated Addresses rendering');
check(xpath_for($recursive)->query('//a[@data-ishi-dashboard-tab="addresses"]')->length === 1, 'Recursive render creates only the outer custom link');
check(xpath_for($recursive)->query('//div[@data-ishi-dashboard-tab="addresses"]')->length === 1, 'Recursive render creates only the outer custom panel');
check(test_action_output('latepoint_customer_dashboard_after_tab_contents', $customer) === '', 'Recursion guard leaves no pending content');

// A failed render must release the frame and guard for the next dashboard.
$GLOBALS['address_output'] = new RuntimeException('Native renderer failure');
try {
    dashboard();
    throw new LogicException('Expected rendering exception');
} catch (RuntimeException $error) {
    check($error->getMessage() === 'Native renderer failure', 'Native renderer exception propagates');
}
check(test_action_output('latepoint_customer_dashboard_after_tab_contents', $customer) === '', 'Failed render leaves no stale frame');
$GLOBALS['address_output'] = '<p>After failure</p>';
check(strpos(dashboard(), '<p>After failure</p>') !== false, 'Next render recovers after failure');
$GLOBALS['address_output'] = null;
$invalid = dashboard();
check(strpos($invalid, 'data-ishi-dashboard-tab="addresses"') === false, 'Invalid Addresses result emits neither link nor panel');
$GLOBALS['address_output'] = '';
$empty = dashboard();
check(xpath_for($empty)->query('//a[@data-ishi-dashboard-tab="addresses"]')->length === 1, 'Empty shortcode retains its link');
check(xpath_for($empty)->query('//div[@data-ishi-dashboard-tab="addresses"]')->item(0)->childNodes->length === 0, 'Empty shortcode retains an empty matching panel');
echo 'PASS: ' . ($checks - $start_checks) . " native-hook checks\n";
