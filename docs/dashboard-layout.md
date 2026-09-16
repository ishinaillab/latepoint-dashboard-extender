# Customer dashboard integration notes

## Verified rendering path

The inspected LatePoint 5.6.10 source follows `OsShortcodesHelper::shortcode_latepoint_customer_dashboard()` → `OsCustomerCabinetController::dashboard()` → the customer cabinet dashboard view. The controller performs native authentication and prepares bookings, orders and bundles. The dashboard block calls the shortcode. Its direct controller rendering path has no shared final HTML filter; JSON responses may be sent and terminated by the controller.

The dashboard view emits native triggers and panels plus `latepoint_customer_dashboard_after_tabs` and `latepoint_customer_dashboard_after_tab_contents`. Ishi custom tabs remain on these hooks at priority 20; Pro Messages uses priority 10. Matched per-render frames avoid duplicate Addresses/Press-Ons rendering.

The adapter parses only completed dashboard HTML, preflights known direct trigger/panel pairs, then groups the retained nodes on the server. It never captures whole-page output, modifies core templates or reconstructs panels in the browser. An unknown target, duplicate target or missing matched panel returns the original HTML. Already adapted output is unchanged.

## Upstream contracts

| View | Retained panel selector | Functional owner |
| --- | --- | --- |
| Appointments | .tab-content-customer-bookings | LatePoint |
| History | .tab-content-customer-orders | LatePoint |
| Press-Ons | .tab-content-ishi-customer-press-ons | This extension / WooCommerce APIs |
| Profile | .tab-content-customer-info-form | Ishi shortcode; native fallback |
| Addresses | .tab-content-ishi-customer-addresses | Ishi shortcode; existing WooCommerce fallback |
| Messages | .tab-content-customer-booking-messages | LatePoint Pro |
| New Appointment | .tab-content-customer-new-appointment-form | LatePoint booking shortcode/handlers |

All seven panels are server-rendered in the inspected installation. Their subsequent actions may use native AJAX/REST: appointments and order lightboxes, message loading/conversations, booking steps, and Ishi form saves. No replacement endpoints are introduced.

LatePoint's flat tab handler is delegated on `.latepoint-tab-triggers` and clears all descendant trigger/content active classes in its nearest `.latepoint-tabs-w`. It cannot safely own nested tab lists. The new navigation deliberately excludes that delegation class while retaining the wrapper, panel classes and feature selectors. One small layout handler owns selection for adapted dashboards only; native dashboards keep native switching.

Pro binds `.latepoint-trigger-messages-tab` directly during initial page setup. The adapter preserves that exact anchor and its unread badge. Layout activation runs in capture phase, then the original event reaches Pro normally. No message reload/send handler is copied. Pro's conversation selection and polling contain global selectors: the layout can isolate its own state across multiple dashboards, but it cannot promise multiple independent native chat widgets when Pro itself does not support that arrangement.

Booking remains the original panel with its native booking button/shortcode configuration. The New Appointment action selects it, and Back to appointments returns within the same section. Any native booking lightbox/step behavior remains owned by LatePoint.

Ishi Profile 1.4.0 supplies `[ishi_latepoint_profile]`; Ishi Addresses supplies `[ishi_customer_addresses]`. The inspected component scripts use delegated events and replace their own component contents after REST responses. The navigation wraps the existing components without entering those replaceable roots. Profile rendering is raw-shortcode substitution after navigation serialization, so no form fields/nonces are rebuilt.

Required assets remain LatePoint frontend CSS/JS, Pro Messages assets, Ishi UI/profile/addresses assets, and existing Press-Ons styling/lightbox support. The two layout assets depend on `latepoint-main-front`; the icon CSS is reused via WordPress enqueue. The verified icon path is relative to `wp_upload_dir()`, not a page URL. Elementor is not a runtime dependency. Site migrations that relocate/remove the custom font should update its normal registration; missing assets fall back to text controls.

## State and initialization

The active retained leaf panel is authoritative. The layout derives section visibility, selected controls and ARIA from it. There is no hash/history state, so Back/Forward retains normal page navigation. Press-Ons pagination still reloads the current dashboard URL with its existing parameter and selects Press-Ons server-side. Clicking another primary section selects its default leaf; clicking an already selected primary section keeps its current leaf.

Handlers are delegated once and initialization is idempotent. Added adapted markup is detected without moving its content. Component-only AJAX replacements do not rebuild navigation. The observer can synchronize native changes to an owned active panel, but does not invent state when external code removes every active panel or replaces the dashboard with unrelated markup.

For custom direct PHP rendering, pass authorized native HTML to `LatePoint_Dashboard_Extender::transform_customer_dashboard_html($html)` before encoding/sending it. Arbitrary complete-dashboard AJAX insertion still requires the host's supported native initialization: Pro and some LatePoint handlers bind directly on initial page setup. This extension's observer cannot initialize those private business handlers. Unmodified direct calls continue to use the native layout.

## Security and fallback

Rendering and hiding panels are presentation only. Existing authentication, customer ownership, order eligibility, nonce validation, sanitization and permission callbacks remain the functional owners' responsibility and are unchanged. The Press-Ons controller, modal template, query policy and card implementation are unchanged by this layout release.

Logged-out/login HTML without the verified dashboard structure is returned unchanged. Missing DOM support or incompatible markup preserves native HTML. Unknown future add-on tabs trigger a full native-layout fallback, not silent omission. No-JS fallback exposes only owned server-rendered views and preserves anchor navigation; native interactive features still need their own scripts.

## Verification and staging matrix

Automated PHP tests cover render-once components, Unicode/nonces/forms, optional/unknown targets, recursion/exception cleanup, native hooks, pagination/order policy, asset registration, unique identifiers, and release metadata. Automated Chromium checks use synthetic components and listeners. They verify the layout's event delivery, not backend operations.

| Scenario | Automated evidence | Required staging check |
| --- | --- | --- |
| Initial load and all primary/secondary transitions | Active leaf/section and ARIA assertions | Real dashboard content and active styling |
| Press-Ons cards/pagination | Existing PHP policy tests; ten cards and page-two selection | Real mixed/excluded orders, prices, modal contents |
| Messages | Trigger, badge, conversation markup and click delivery retained | Loading, conversation selection, sending, polling/unread updates |
| Profile/password | Single shortcode; nonce preservation; form-event delivery | Actual save, password change, validation and notices |
| Billing/shipping | Single component; component replacement and save-event simulation | Actual edit/save, validation and notices |
| New Appointment/return | Panel activation and focus return | Native booking flow, modal, completion and cancellation |
| Logged-out | Non-dashboard HTML unchanged | Login, logout, session expiry and access boundaries |
| Responsive layout | 320, 390, 768, 1280px; overflow and touch targets | Real component content, installed theme and icon font |
| Keyboard | Arrow/Home/End/Enter/Space, focus and unique ARIA targets | Screen-reader announcements and native dialogs/forms |
| Reload/direct render | Page-two selection; repeated adapter is idempotent | Custom render adapter, native initialization and pagination back/forward |
| AJAX | Component replacement and repeat initialization | Real REST/AJAX errors and complete-fragment host integration |
| Errors/logs | PHP CLI suites and browser console | WordPress debug/PHP logs and browser console after deployment |

The checked-in implementation is not evidence that these live staging operations have run. Keep the previous stable release available until staging passes and record exact installed versions. The inspected Pro source archive's header says 1.6.3 while the installed asset query version says 1.6.4; compatibility is based on the inspected implementation, not that ambiguous label alone.
