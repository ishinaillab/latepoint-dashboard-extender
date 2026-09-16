<?php
/** Server composition contract; domain logic remains covered by the existing suites. */
if (PHP_SAPI !== 'cli') { exit; }
require __DIR__ . '/run.php';
$start = $checks;
$GLOBALS['profile_enabled'] = true;
$GLOBALS['profile_calls'] = 0;
$GLOBALS['profile_output'] = '<div data-ishi-ui="profile"><form data-ishi-ui-form action="/wp-json/ishi-profile/v1/profile"><input name="account_first_name" value="A &amp; B"><input type="hidden" name="nonce" value="test-nonce"><button>Save profile</button></form></div>';
$GLOBALS['address_enabled'] = true;
$GLOBALS['address_output'] = '<div data-ishi-ui="addresses"><form data-ishi-ui-form action="/wp-json/ishi-profile/v1/addresses"><input name="billing_city" value="Manila"><button>Save address</button></form></div>';
$GLOBALS['messages_enabled'] = true;
$raw = dashboard();
$before = $GLOBALS['shortcode_calls'];
$output = render_dashboard($raw);
$x = xpath_for($output);
check(sequence($output) === $expected, 'Four named primary sections');
check($GLOBALS['profile_calls'] === 1, 'Ishi Profile rendered once');
check($GLOBALS['shortcode_calls'] === $before, 'Addresses not rerendered during composition');
check($x->query('//div[@data-ishi-ui="profile"]')->length === 1, 'Existing Ishi Profile component composed');
check($x->query('//input[@name="customer[first_name]"]')->length === 0, 'Native and Ishi profile forms are not duplicated');
check($x->query('//form//form')->length === 0, 'No nested forms');
check($x->query('//input[@name="nonce"]')->item(0)->getAttribute('value') === 'test-nonce', 'Component nonce retained');
check($x->query('//section[@data-ishi-section="appointments"]//*[@data-ishi-secondary]')->length === 1, 'Appointments has secondary navigation');
check($x->query('//section[@data-ishi-section="account"]//*[@data-ishi-secondary]')->length === 1, 'Account has secondary navigation');
check($x->query('//section[@data-ishi-section="press-ons"]//*[@data-ishi-secondary]')->length === 1, 'Press-Ons has category submenu navigation');
check($x->query('//section[@data-ishi-section="messages"]//*[@data-ishi-secondary]')->length === 0, 'Messages has no artificial secondary navigation');
check($x->query('//section[@data-ishi-section="appointments"]//button[@data-ishi-view="book"]')->length === 1, 'New Appointment remains in the Appointments section');
check($x->query('//section[@data-ishi-section="appointments"]//div[@data-ishi-view="book"]')->item(0)->textContent === 'Book', 'Booking panel retained');
check($x->query('//button[@data-ishi-primary="messages" and contains(@class,"latepoint-trigger-messages-tab")]')->length === 1, 'Primary Messages retains Pro click hook');
check($x->query('//button[@data-ishi-primary="messages"]//span[@class="lp-new-messages-count"]')->item(0)->textContent === '3', 'Unread badge retained');
check($x->query('//*[local-name()="svg" and @class="ishi-dashboard-icon" and @aria-hidden="true" and @focusable="false"]')->length === 4, 'Four decorative SVG icons');
check($x->query('//*[local-name()="path" and @class="ishi-dashboard-icon-outline"]')->length === 4, 'Each icon has an outline variant');
check($x->query('//*[local-name()="path" and @class="ishi-dashboard-icon-solid"]')->length === 4, 'Each icon has a filled variant');
check(strpos($output, 'nails_skin_elementor_icons') === false, 'Dashboard SVGs do not rely on the theme icon font');
check($x->query('//*[contains(concat(" ",normalize-space(@class)," ")," latepoint-tab-triggers ")]')->length === 0, 'Redesigned navigation does not bind competing flat LatePoint handler');
check(render_dashboard($output) === $output && $GLOBALS['profile_calls'] === 1, 'Idempotent output and Profile rendering');
check(LatePoint_Dashboard_Extender::transform_customer_dashboard_html($output) === $output, 'Explicit adapter is idempotent');
$together = render_dashboard(dashboard() . dashboard());
$ids = array();
foreach (xpath_for($together)->query('//*[@id]') as $node) { $ids[] = $node->getAttribute('id'); }
check(count($ids) === count(array_unique($ids)), 'Repeated dashboards have unique generated IDs');
$no_book = str_replace('<a href="#" data-tab-target=".tab-content-customer-new-appointment-form" class="latepoint-tab-trigger">New Appointment</a>', '', $raw);
check(xpath_for(render_dashboard($no_book))->query('//button[@data-ishi-view="book"]')->length === 0, 'Native hide booking setting respected');
$GLOBALS['profile_output'] = null;
check(xpath_for(render_dashboard(dashboard()))->query('//input[@name="customer[first_name]"]')->length === 1, 'Invalid optional Profile provider retains native form');
$GLOBALS['profile_output'] = new RuntimeException('Profile test failure');
try { render_dashboard(dashboard()); check(false, 'Expected Profile exception'); }
catch (RuntimeException $error) { check($error->getMessage() === 'Profile test failure', 'Component exception propagated'); }
$GLOBALS['profile_output'] = '<p>Recovered profile</p>';
check(strpos(render_dashboard(dashboard()), 'Recovered profile') !== false, 'Layout guard resets after exception');
$calls = $GLOBALS['profile_calls'];
$unknown = str_replace('>Profile</a>', '>Profile</a><a class="latepoint-tab-trigger" data-tab-target=".unknown">Other</a>', dashboard());
check(render_dashboard($unknown) === $unknown && $GLOBALS['profile_calls'] === $calls, 'Unknown add-on preserves native layout before component rendering');
$translated = str_replace('>Orders</a>', '>Pedidos</a>', dashboard());
check(strpos(render_dashboard($translated), '>History</button>') !== false, 'History label is independent of original language');
$booking_tabs = array();
foreach ($x->query('//div[@data-ishi-secondary="appointments"]/button') as $tab) { $booking_tabs[] = $tab->getAttribute('data-ishi-view'); }
check($booking_tabs === array('appointments', 'history', 'book'), 'New Appointment follows History in the same submenu');
check($x->query('//button[@data-ishi-view and @href]')->length === 0, 'Tab controls have no hash destinations');
check($x->query('//button[@data-ishi-view and @type="button"]')->length === 8, 'Every view trigger is non-submitting');
$native_header = '<div class="latepoint-w"><a href="/wp-admin/admin-post.php?action=latepoint_route_call&amp;route_name=customer_cabinet__logout">Sign out</a><h4>Bienvenue Customer</h4>';
$header_output = render_dashboard($native_header . dashboard() . '</div>');
check(strpos($header_output, 'customer_cabinet__logout') === false && strpos($header_output, 'Bienvenue') === false, 'Verified native header removed independent of translation');
$other_header = '<div class="latepoint-w"><a href="/help">Help</a><h4>Other heading</h4>';
check(strpos(render_dashboard($other_header . dashboard() . '</div>'), 'Other heading') !== false, 'Unrelated nearby header preserved');
check(render_dashboard($native_header . $unknown . '</div>') === $native_header . $unknown . '</div>', 'Native fallback preserves its header');
echo 'PASS: ' . ($checks - $start) . " layout checks\n";
