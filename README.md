# LatePoint Dashboard Extender

Current version: **0.11.0**

Organizes LatePoint's Customer Dashboard with server-rendered primary and secondary navigation while retaining its existing feature components.

## Dashboard layout

| Primary section | Views |
| --- | --- |
| Appointments | Appointments, History; New Appointment action |
| Press-Ons | Existing WooCommerce order cards and lightbox |
| Messages | Native LatePoint Pro conversations |
| Account | Ishi Profile, Ishi Addresses |

Primary controls use the existing Nails icon font with accessible names. Appointments and Account have secondary navigation. Single-view sections do not. Missing optional providers are omitted; unknown add-on targets or ambiguous markup preserve the original dashboard rather than discard functionality.

Profile uses the registered `[ishi_latepoint_profile]` component once per dashboard, replacing the native profile panel's contents. If that provider is unavailable or returns an invalid non-string result, the native profile remains. An intentional empty string remains empty. Addresses still uses `[ishi_customer_addresses]` once, or the existing WooCommerce fallback when its endpoint API is available. Neither integration duplicates form/save logic.

## Integration and state

LatePoint's native dashboard hooks continue to create Press-Ons and Addresses. The existing WordPress `do_shortcode_tag` filter groups the finished dashboard HTML on the server for `latepoint_customer_dashboard` and the block that uses that shortcode. JavaScript only selects existing views, coordinates accessibility and manages keyboard focus.

The inspected LatePoint version has no shared final-render hook or supported controller/template substitution filter. Direct custom PHP renderers must pass their native HTML through the explicit adapter **before JSON encoding or sending the response**:

```php
$html = LatePoint_Dashboard_Extender::transform_customer_dashboard_html($html);
```

The caller must already have rendered authorized dashboard HTML and arranged the normal LatePoint/component assets and initialization. This adapter does not authenticate users or intercept arbitrary controller responses. Unmodified direct-controller output retains its native layout.

The retained active content panel is the selection authority. Primary selection, secondary selection, visibility and ARIA derive from it. The redesigned navigation omits LatePoint's flat `latepoint-tab-triggers` delegation class, whose descendant-wide clearing conflicts with nested navigation. Native feature trigger classes, data attributes and bubbling events remain available, including Pro Messages. There is no additional URL/hash router or per-section state store. Press-Ons pagination retains its existing query parameter behavior.

Arrow keys and Home/End move tab focus; Enter/Space activate. Booking navigation provides a return action and predictable focus. Before JavaScript enhancement, the owned views are server-rendered and reachable with anchor links. This does not make JavaScript-dependent native forms or messaging work without their required scripts.

Layout assets use WordPress enqueue dependencies on LatePoint's frontend handle. The existing `elementor-icons-nails_skin_elementor_icons` stylesheet is reused when registered, otherwise its verified path under the site's uploads directory is enqueued if readable. Font files are not copied. If unavailable, controls display text labels. No Elementor runtime or fixed dashboard page URL is required.

## Press-Ons pagination

Press-Ons uses the same default page size as WooCommerce My Account → Orders: WordPress's `posts_per_page` setting (normally 10). A positive `limit` supplied through `woocommerce_my_account_my_orders_query` is honored. Other query overrides are not copied; order ownership, types, and statuses remain restricted. Invalid or unlimited limits fall back to the finite site default.

Previous/Next links use `ishi_press_ons_page` on the existing dashboard URL, preserving other query parameters and selecting Press-Ons after navigation. The normal initial visit still selects Appointments. Invalid page values use page one; requests beyond the end display the last eligible page.

Eligibility is checked before pages are filled: pure LatePoint-category orders are excluded, while mixed orders, missing products, and empty orders retain their existing treatment. Queries use WooCommerce's order API in batches of 50, with date/ID sorting. Only the current page is retained, and scanning stops once another eligible order establishes that Next is available. There is no unfiltered total/page count.

This avoids loading every order at once, but deep pages or histories dominated by excluded orders can still require scanning many batches. No database-specific SQL, persistent classification metadata, or cache is introduced.

Both the dashboard list and lightbox use the same ownership, viewable order type/status, and category eligibility check. The lightbox re-checks these rules on every request and returns an error when WooCommerce's order API is unavailable.

## Dependencies and compatibility

- WordPress, LatePoint and PHP DOM/libxml.
- WooCommerce for Press-Ons and fallback Addresses.
- Ishi Profile/Addresses providers for the intended Account components.
- LatePoint Pro Features with Messages enabled for Messages.
- Existing Nails custom icon stylesheet/font for icon-only presentation.

The installed sources inspected for this layout were LatePoint 5.6.10 and Ishi Profile 1.4.0. The Pro archive reports 1.6.3 while the site's asset version reports 1.6.4; the actual downloaded message implementation was inspected. These observations do not establish compatibility with every upstream version. See [integration notes](docs/dashboard-layout.md) for extension points, limitations and staging checks.

## Verification

Run PHP CLI with DOM enabled:

```sh
php tests/run.php
php tests/run.php --without-woocommerce
php tests/pagination.php
php tests/native-hooks.php
php tests/order-policy.php
php tests/order-policy.php --without-woocommerce
php tests/layout.php
php tests/assets.php
```

GitHub Actions runs PHP lint, these isolated regression suites and Playwright browser checks. Browser tests use synthetic customer data and feature-event doubles: they verify navigation, ARIA references, keyboard/focus, responsive widths, pagination selection, independent layout instances, component replacement and initialization idempotence. They do not perform real WordPress saves, booking, payment or messaging requests.

To run the browser suite locally, install Playwright 1.62.1 in a separate test directory, install its Chromium browser, set `NODE_PATH` to that directory's `node_modules`, then run `node tests/browser.cjs`. Optional `PHP_BINARY` and `CHROME_BINARY` choose existing executables. No Node dependency is required by the deployed plugin.

## Release checklist

1. Keep plugin header, version constant, README and CHANGELOG in sync; require green checks for the exact release commit.
2. Complete the staging matrix in [integration notes](docs/dashboard-layout.md), including real Profile/password/Addresses saves, conversations, booking return, logged-out access and PHP/browser logs.
3. Test multi-page Press-Ons and lightboxes with mixed/excluded orders, configured page sizes and supported WooCommerce storage modes.
4. Verify optional-provider fallback, native hooks, multiple instances and any custom direct-render/AJAX integration.
5. Record the WordPress, PHP, LatePoint, Pro, Ishi and WooCommerce versions actually tested before tagging or deploying.

GitHub commits do not deploy this plugin to WordPress automatically. Version 0.11.0 requires staging integration validation; the previous stable release remains available.
