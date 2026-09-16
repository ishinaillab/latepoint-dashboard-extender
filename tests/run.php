<?php
/**
 * Standalone regression tests with small WordPress/WooCommerce doubles.
 * Run each mode in a fresh PHP process; no WordPress database is required.
 */
if (PHP_SAPI !== 'cli') {
    exit;
}
if (!class_exists('DOMDocument')) {
    fwrite(STDERR, "The PHP DOM extension is required.\n");
    exit(1);
}
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return false;
});
define('ABSPATH', __DIR__);
$without_wc = in_array('--without-woocommerce', $argv, true);
$GLOBALS['test_hooks'] = array();
$GLOBALS['address_enabled'] = true;
$GLOBALS['shortcode_calls'] = 0;
$GLOBALS['fallback_calls'] = 0;
$GLOBALS['address_output'] = '<form class="address-form"><input name="address" value="A &amp; B"><br><span>住所</span></form>';
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_dir_url($file) { return 'https://example.test/plugin/'; }
function add_filter($tag, $callback, $priority = 10, $accepted = 1) {
    $GLOBALS['test_hooks'][$tag][$priority][] = array($callback, $accepted);
}
function add_action($tag, $callback, $priority = 10, $accepted = 1) {
    add_filter($tag, $callback, $priority, $accepted);
}
function test_apply($tag, $value, ...$args) {
    $hooks = $GLOBALS['test_hooks'][$tag] ?? array();
    ksort($hooks);
    foreach ($hooks as $callbacks) {
        foreach ($callbacks as $entry) {
            $value = call_user_func_array($entry[0], array_slice(array_merge(array($value), $args), 0, $entry[1]));
        }
    }
    return $value;
}
function test_action_output($tag, ...$args) {
    $hooks = $GLOBALS['test_hooks'][$tag] ?? array();
    ksort($hooks);
    ob_start();
    try {
        foreach ($hooks as $callbacks) {
            foreach ($callbacks as $entry) {
                call_user_func_array($entry[0], array_slice($args, 0, $entry[1]));
            }
        }
        return ob_get_contents();
    } finally {
        ob_end_clean();
    }
}
function __($text, $domain = '') { return $text; }
function wp_unique_id($prefix = '') { static $id = 0; return $prefix . ++$id; }
function shortcode_exists($tag) {
    if ($tag === 'ishi_latepoint_profile') { return !empty($GLOBALS['profile_enabled']); }
    return $tag === 'ishi_customer_addresses' && $GLOBALS['address_enabled'];
}
function do_shortcode($input) {
    if ($input === '[ishi_latepoint_profile]') {
        $GLOBALS['profile_calls']++;
        $result = $GLOBALS['profile_output'];
        if ($result instanceof Throwable) { throw $result; }
        return is_callable($result) ? $result() : $result;
    }
    if ($input !== '[ishi_customer_addresses]') {
        throw new RuntimeException('Unexpected shortcode');
    }
    $GLOBALS['shortcode_calls']++;
    if ($GLOBALS['address_output'] instanceof Throwable) {
        throw $GLOBALS['address_output'];
    }
    if (is_callable($GLOBALS['address_output'])) {
        return call_user_func($GLOBALS['address_output']);
    }
    return test_apply('do_shortcode_tag', $GLOBALS['address_output'], 'ishi_customer_addresses', array(), array());
}
function esc_url($url) { return $url; }
function wp_strip_all_tags($html) { return strip_tags($html); }
if (!$without_wc) {
    function wc_get_account_endpoint_url($endpoint) {
        $GLOBALS['fallback_calls']++;
        return 'https://example.test/my-account/' . $endpoint . '/';
    }
    function WC() { return (object) array('customer' => (object) array('id' => 1)); }
    function wc_get_account_formatted_address($type) {
        return $type === 'billing' ? 'Billing Street<br/>Manila' : 'Shipping Street<br/>Manila';
    }
}
// Loading the controller catches accidental reintroduction of its old filter.
class OsController {
    public $params = array();
    public $vars = array();
    public $action_access = array('customer' => array());
    public $views_folder;
    public $response;
    public $rendered;
    public function __construct() {}
    public function send_json($response) { $this->response = $response; }
    public function format_render($view) { $this->rendered = $view; }
}
require dirname(__DIR__) . '/latepoint-dashboard-extender.php';
LatePoint_Dashboard_Extender::load_latepoint_extension();
$checks = 0;
function check($condition, $message) {
    global $checks;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    $checks++;
}
// Pro Features adds Messages through these same hooks at priority 10.
$GLOBALS['messages_enabled'] = true;
add_action('latepoint_customer_dashboard_after_tabs', function ($customer) {
    if ($GLOBALS['messages_enabled']) {
        echo '<a href="#" data-tab-target=".tab-content-customer-booking-messages" class="latepoint-tab-trigger latepoint-trigger-messages-tab">Messages<span class="lp-new-messages-count">3</span></a>';
    }
}, 10, 1);
add_action('latepoint_customer_dashboard_after_tab_contents', function ($customer) {
    if ($GLOBALS['messages_enabled']) {
        echo '<div class="latepoint-tab-content tab-content-customer-booking-messages">Chat</div>';
    }
}, 10, 1);
function dashboard($with_hooks = true, $customer = null) {
    $customer = $customer ?? (object) array('id' => 1);
    return '<div class="latepoint-tabs-w"><div class="latepoint-tab-triggers customer-dashboard-tabs">'
        . '<a href="#" data-tab-target=".tab-content-customer-bookings" class="latepoint-tab-trigger active">Appointments</a>'
        . '<a href="#" data-tab-target=".tab-content-customer-orders" class="latepoint-tab-trigger">Orders</a>'
        . '<a href="#" data-tab-target=".tab-content-customer-info-form" class="latepoint-tab-trigger">Profile</a>'
        . '<a href="#" data-tab-target=".tab-content-customer-new-appointment-form" class="latepoint-tab-trigger">New Appointment</a>'
        . ($with_hooks ? test_action_output('latepoint_customer_dashboard_after_tabs', $customer) : '')
        . '</div><div class="latepoint-tab-content tab-content-customer-bookings active">Bookings</div>'
        . '<div class="latepoint-tab-content tab-content-customer-orders">Native orders</div>'
        . '<div class="latepoint-tab-content tab-content-customer-info-form"><form><input name="customer[first_name]" value="Unchanged"></form></div>'
        . '<div class="latepoint-tab-content tab-content-customer-new-appointment-form">Book</div>'
        . ($with_hooks ? test_action_output('latepoint_customer_dashboard_after_tab_contents', $customer) : '')
        . '</div>';
}
function render_dashboard($html) {
    return test_apply('do_shortcode_tag', $html, 'latepoint_customer_dashboard', array(), array());
}
function xpath_for($html) {
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try {
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    return new DOMXPath($dom);
}
function sequence($html) {
    $result = array();
    foreach (xpath_for($html)->query('//*[contains(concat(" ", normalize-space(@class), " "), " customer-dashboard-tabs ")]/a') as $tab) {
        $result[] = $tab->hasAttribute('data-ishi-primary') ? $tab->getAttribute('data-ishi-primary') : $tab->getAttribute('data-tab-target');
    }
    return $result;
}
$expected = array('appointments', 'press-ons', 'messages', 'account');
$output = render_dashboard(dashboard());
check($GLOBALS['shortcode_calls'] === 1, 'Shortcode runs exactly once, with the controller loaded');
check($GLOBALS['fallback_calls'] === 0, 'No fallback address UI is built when shortcode exists');
check(sequence($output) === $expected, 'Four primary sections use the requested order');
$x = xpath_for($output);
check($x->query('//form[@class="address-form"]')->length === 1, 'Only one address form');
check($x->query('//input[@name="address"]')->item(0)->getAttribute('value') === 'A & B', 'HTML form attributes preserved');
check(strpos($output, '住所') !== false, 'Unicode shortcode content preserved');
check($x->query('//span[@class="lp-new-messages-count"]')->item(0)->textContent === '3', 'Unread badge preserved');
check($x->query('//a[contains(@class,"latepoint-trigger-messages-tab")]')->length === 1, 'Messages click-hook class preserved');
check($x->query('//a[contains(@class,"active")]')->item(0)->getAttribute('data-tab-target') === '.tab-content-customer-bookings', 'Active tab preserved');
check($x->query('//input[@name="customer[first_name]"]')->item(0)->getAttribute('value') === 'Unchanged', 'Profile input preserved');
render_dashboard($output);
check($GLOBALS['shortcode_calls'] === 1, 'Existing Addresses panel is not rendered again');
$count = $GLOBALS['shortcode_calls'];
check(render_dashboard('<p>Login required</p>') === '<p>Login required</p>', 'Login output preserved');
check($GLOBALS['shortcode_calls'] === $count, 'Shortcode not evaluated without dashboard tabs');
check(test_apply('do_shortcode_tag', '<p>Other</p>', 'other', array(), array()) === '<p>Other</p>', 'Unrelated shortcode unchanged');

$GLOBALS['address_output'] = '';
$empty = render_dashboard(dashboard());
$x = xpath_for($empty);
$panel = $x->query('//div[contains(@class,"tab-content-ishi-customer-addresses")]')->item(0);
check($panel && !$panel->hasChildNodes(), 'Empty shortcode output intentionally stays empty');
check($GLOBALS['fallback_calls'] === 0, 'Empty shortcode does not invoke fallback');

$GLOBALS['address_output'] = null;
$invalid = render_dashboard(dashboard());
check(strpos($invalid, 'tab-content-ishi-customer-addresses') === false, 'Invalid shortcode response does not create a broken tab');
check($GLOBALS['fallback_calls'] === 0, 'Invalid response does not render a second address UI');

$GLOBALS['address_output'] = new RuntimeException('Test renderer failure');
$previous = libxml_use_internal_errors(false);
try {
    render_dashboard(dashboard());
    throw new LogicException('Renderer exception was not propagated');
} catch (RuntimeException $exception) {
    check($exception->getMessage() === 'Test renderer failure', 'Renderer exception propagated');
    check(libxml_use_internal_errors() === false, 'libxml error mode restored after exception');
} finally {
    libxml_use_internal_errors($previous);
}
$GLOBALS['address_output'] = '<p>Recovered</p>';
check(strpos(render_dashboard(dashboard()), 'Recovered') !== false, 'Processing guard reset after exception');

$GLOBALS['address_enabled'] = false;
$count = $GLOBALS['shortcode_calls'];
$fallback = render_dashboard(dashboard());
check($GLOBALS['shortcode_calls'] === $count, 'Unavailable shortcode not evaluated');
if ($without_wc) {
    check(strpos($fallback, 'tab-content-ishi-customer-addresses') === false, 'No Addresses tab without either provider');
} else {
    check($GLOBALS['fallback_calls'] === 2, 'Fallback builds billing and shipping links once each');
    check(strpos($fallback, 'Billing Street') !== false && strpos($fallback, 'Shipping Street') !== false, 'Fallback addresses preserved');
    check(sequence($fallback) === $expected, 'Fallback tab has the same configured position');
}
$order = function ($html) {
    return LatePoint_Dashboard_Extender::order_customer_dashboard_tabs($html, 'latepoint_customer_dashboard', array(), array());
};
check($order($output) === $output, 'Ordering is idempotent');
$shuffled = str_replace('</div><div class="latepoint-tab-content tab-content-customer-bookings', '<a class="latepoint-tab-trigger" data-tab-target=".unknown">Extra</a></div><div class="latepoint-tab-content tab-content-customer-bookings', dashboard());
$ordered = sequence($order($shuffled));
check(end($ordered) === '.unknown', 'Unknown add-on tab retained in its slot');
$single_sequence = sequence($order(dashboard()));
check(sequence($order(dashboard() . dashboard())) === array_merge($single_sequence, $single_sequence), 'Independent dashboard navigation containers ordered');
$duplicate = str_replace('>Profile</a>', '>Profile</a><a class="latepoint-tab-trigger" data-tab-target=".tab-content-customer-info-form">Duplicate</a>', dashboard());
check($order($duplicate) === $duplicate, 'Ambiguous duplicate targets return original HTML');
$double = render_dashboard(dashboard() . dashboard());
check(substr_count($double, '>History</a>') === 2, 'History label applied independently to both dashboards');
$badged = str_replace('>Orders</a>', '><span>Orders</span><span class="count">2</span></a>', dashboard());
check(strpos(render_dashboard($badged), '<span>History</span><span class="count">2</span>') !== false, 'History rename preserves nested badge markup');
$source = file_get_contents(dirname(__DIR__) . '/latepoint-dashboard-extender.php');
preg_match('/^\s*\*\s*Version:\s*(\S+)/m', $source, $version);
check(($version[1] ?? '') === LATEPOINT_DASHBOARD_EXTENDER_VERSION, 'Plugin header and runtime version agree');
$changelog = file_get_contents(dirname(__DIR__) . '/CHANGELOG.md');
check(strpos($changelog, '## ' . LATEPOINT_DASHBOARD_EXTENDER_VERSION . ' - ') !== false, 'Current release has a changelog entry');
echo 'PASS: ' . $checks . ' checks (' . ($without_wc ? 'without WooCommerce' : 'with WooCommerce') . ")\n";
