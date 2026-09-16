# LatePoint Dashboard Extender

Current version: **0.10.32**

Adds Press-Ons and Addresses through LatePoint's native dashboard hooks while retaining native tab switching and lightboxes.

## Dashboard behavior

Tab order: **Appointments → History → Press-Ons → Profile → Addresses → New Appointment → Messages**.

Missing optional tabs are skipped. Unknown add-on tabs retain their positions.

Addresses uses `[ishi_customer_addresses]` when registered. Otherwise, it renders the existing WooCommerce billing/shipping display if WooCommerce's account endpoint API is available. The shortcode and fallback are never both rendered. An empty shortcode response stays empty; an invalid response omits both the Addresses link and panel.

The Addresses shortcode belongs to a separate plugin. Its own validation, saving, and scripts remain that plugin's responsibility.

## Native tab integration

- `latepoint_customer_dashboard_after_tabs` prepares each custom tab once and emits its link (priority 20).
- `latepoint_customer_dashboard_after_tab_contents` emits the matching prepared panels (priority 20).
- Messages remains owned by Pro Features, whose callbacks run at priority 10.
- Per-render frames pair links and panels, including repeated dashboards for the same customer. Nested rendering is isolated; recursive custom-tab rendering is suppressed and failures release the pending frame.
- One shortcode-output filter uses a single DOM pass to rename Orders to History, reorder existing links, and select Press-Ons for pagination. They no longer create custom tabs.

Native hooks also add links/panels when the dashboard is rendered directly by its controller. The configured ordering, History label, and pagination selection still require the `latepoint_customer_dashboard` shortcode filter path (also used by the dashboard block). Direct controller/AJAX output has no new final-output hook in this release; test any custom direct-render integration separately.

A template that omits the native hooks will not receive these custom tabs. There is deliberately no second HTML-insertion path that could duplicate them. PHP DOM is still required to build the Press-Ons cards and apply navigation transformations.

## Press-Ons pagination

Press-Ons uses the same default page size as WooCommerce My Account → Orders: WordPress's `posts_per_page` setting (normally 10). A positive `limit` supplied through `woocommerce_my_account_my_orders_query` is honored. Other query overrides are not copied; order ownership, types, and statuses remain restricted. Invalid or unlimited limits fall back to the finite site default.

Previous/Next links use `ishi_press_ons_page` on the existing dashboard URL, preserving other query parameters and selecting Press-Ons after navigation. The normal initial visit still selects Appointments. Invalid page values use page one; requests beyond the end display the last eligible page.

Eligibility is checked before pages are filled: pure LatePoint-category orders are excluded, while mixed orders, missing products, and empty orders retain their existing treatment. Queries use WooCommerce's order API in batches of 50, with date/ID sorting. Only the current page is retained, and scanning stops once another eligible order establishes that Next is available. There is no unfiltered total/page count.

This avoids loading every order at once, but deep pages or histories dominated by excluded orders can still require scanning many batches. No database-specific SQL, persistent classification metadata, or cache is introduced.

Both the dashboard list and lightbox use the same ownership, viewable order type/status, and category eligibility check. The lightbox re-checks these rules on every request and returns an error when WooCommerce's order API is unavailable.

## Dependencies

- WordPress and LatePoint.
- PHP DOM/libxml for dashboard composition.
- WooCommerce for Press-Ons order data and the fallback Addresses UI.
- LatePoint Pro Features with Messages enabled to display Messages.

The dashboard structure was inspected against local copies of LatePoint 5.6.11 and Pro Features 1.6.4. This is not a claim that every version or live site combination has been tested.

## Verification

Run with PHP CLI and the DOM extension:

```sh
php tests/run.php
php tests/run.php --without-woocommerce
php tests/pagination.php
php tests/native-hooks.php
php tests/order-policy.php
php tests/order-policy.php --without-woocommerce
```

GitHub Actions runs PHP syntax checks and the Addresses/tab, pagination, and native-hook regression suites on pushes to `main-features`, pull requests, and manual dispatch. The workflow uses the PHP runtime supplied by `ubuntu-24.04` and prints its version.

The standalone tests use small WordPress/WooCommerce doubles. They verify single Addresses rendering, fallback behavior, empty/invalid responses, Unicode and form preservation, exception cleanup, tab ordering, Messages badges, optional/unknown tabs, and release-version consistency. Native-hook tests also cover matching trigger/panel output, Pro Messages coexistence, repeated and nested renders, recursion guards, and cleanup after failures. They do not replace a live WordPress integration test.

## Release checklist

1. Update the plugin header version, runtime version constant, this README, and CHANGELOG together.
2. Require passing syntax and regression checks for the exact release commit.
3. On staging, verify address editing/saving and notices, all seven tabs, Messages unread counts/conversations, Press-Ons lightboxes, and mobile layout.
4. Repeat the Addresses check with its shortcode plugin disabled to confirm the fallback.
5. Confirm behavior for logged-out visitors and customers without orders or appointments.
6. Test Press-Ons with more than one page, mixed/excluded orders, Previous/Next, and the site's configured page size. Verify both HPOS and legacy order storage when those modes are supported by the deployment.
7. Verify the installed dashboard template fires both native hooks. Check a page with two dashboard shortcodes and any custom direct-controller integrations.
8. Record the WordPress, PHP, LatePoint, Pro Features, and WooCommerce versions actually tested before tagging or deploying a release.

GitHub commits do not deploy this plugin to WordPress automatically.
