<?php
/** Synthetic data only. Never exposes customer data or connects to WordPress. */
if (PHP_SAPI !== 'cli') { exit; }
ob_start();
require __DIR__ . '/pagination.php';
ob_end_clean();
$_GET = in_array('--custom-page-two', $argv, true) ? array('ishi_custom_press_ons_page' => '2') : (in_array('--page-two', $argv, true) ? array('ishi_press_ons_page' => '2') : array());
$GLOBALS['paging_orders'] = array();
for ($id = 1; $id <= 23; $id++) { $GLOBALS['paging_orders'][] = new WC_Order($id, $id % 2 === 0 ? array(1, 77) : array(1)); }
$GLOBALS['address_enabled'] = true;
$GLOBALS['profile_enabled'] = true;
$GLOBALS['profile_calls'] = 0;
$GLOBALS['messages_enabled'] = true;
$GLOBALS['profile_output'] = '<div data-ishi-ui="profile"><form data-ishi-ui-form><label>First name <input name="account_first_name" value="Test customer"></label><label>New password <input type="password" name="password_1"></label><button type="submit">Save profile</button></form></div>';
$GLOBALS['address_output'] = '<div data-ishi-ui="addresses"><button type="button" data-address="billing">Edit billing</button><button type="button" data-address="shipping">Edit shipping</button></div>';
$raw = dashboard();
$raw = str_replace('>Bookings</div>', '><h2>Upcoming appointments</h2><button type="button" data-native-appointment>View appointment</button></div>', $raw);
$raw = str_replace('>Native orders</div>', '><h2>History</h2><p>Your previous appointments and orders.</p></div>', $raw);
$raw = str_replace('>Chat</div>', '><div class="latepoint-chat-box-w" data-route="messages__messages_for_booking" data-check-unread-route="messages__check_unread_messages"><div class="lc-contents"><div class="lc-conversations"><button class="lc-conversation lc-selected" data-booking-id="101">Conversation one</button><button class="lc-conversation" data-booking-id="102">Conversation two</button></div><div class="lcb-content"><div class="booking-messages-list">Initial conversation</div><div class="os-booking-messages-input-w" data-author-type="customer" data-booking-id="101"><input class="os-booking-messages-input" aria-label="Message"><button class="os-bm-send-btn">Send</button></div></div></div></div></div>', $raw);
$raw = str_replace('>Book</div>', '><button type="button" class="latepoint-book-button os_trigger_booking">Open booking</button></div>', $raw);
if (in_array('--double', $argv, true)) { $raw .= $raw; }
$html = render_dashboard($raw);
$css = file_get_contents(dirname(__DIR__) . '/public/stylesheets/customer-dashboard.css');
$cards_css = file_get_contents(dirname(__DIR__) . '/public/stylesheets/press-ons.css');
$js = file_get_contents(dirname(__DIR__) . '/public/javascripts/customer-dashboard.js');
echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Ishi dashboard layout test</title><style>body{margin:0;font-family:system-ui,sans-serif;color:#42494d}main{max-width:1100px;margin:auto;padding:16px}*{box-sizing:border-box}input{max-width:100%;padding:10px}label{display:block;margin-block:12px}button{padding:12px}.latepoint-tab-content{display:none}.latepoint-tab-content.active{display:block}.customer-orders-tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr));gap:16px}.customer-order{padding:16px;border:1px solid #ddd;border-radius:12px}.lc-contents{display:flex;gap:16px;flex-wrap:wrap}.lc-conversations{display:flex;gap:8px;flex-wrap:wrap}</style><style>' . $cards_css . $css . '</style><main class="latepoint latepoint-w">' . $html . '</main><script>' . $js . '</script></html>';
