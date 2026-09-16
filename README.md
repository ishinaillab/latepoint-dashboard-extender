# LatePoint Dashboard Extender

Current version: **0.10.29**

Extends the output of `[latepoint_customer_dashboard]` while retaining LatePoint's native tab switching and lightboxes.

## Dashboard behavior

Tab order: **Appointments → History → Press-Ons → Profile → Addresses → New Appointment → Messages**.

Missing optional tabs are skipped. Unknown add-on tabs retain their positions.

Addresses uses `[ishi_customer_addresses]` when registered. Otherwise, it renders the existing WooCommerce billing/shipping display if WooCommerce's account endpoint API is available. The shortcode and fallback are never both rendered. An empty shortcode response stays empty; an invalid response leaves the incoming dashboard unchanged at the Addresses composition stage.

The Addresses shortcode belongs to a separate plugin. Its own validation, saving, and scripts remain that plugin's responsibility.

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
```

GitHub Actions runs PHP syntax checks and both regression modes on pushes to `main-features`, pull requests, and manual dispatch. The workflow uses the PHP runtime supplied by `ubuntu-24.04` and prints its version.

The standalone tests use small WordPress/WooCommerce doubles. They verify single Addresses rendering, fallback behavior, empty/invalid responses, Unicode and form preservation, exception cleanup, tab ordering, Messages badges, optional/unknown tabs, and release-version consistency. They do not replace a live WordPress integration test.

## Release checklist

1. Update the plugin header version, runtime version constant, this README, and CHANGELOG together.
2. Require passing syntax and regression checks for the exact release commit.
3. On staging, verify address editing/saving and notices, all seven tabs, Messages unread counts/conversations, Press-Ons lightboxes, and mobile layout.
4. Repeat the Addresses check with its shortcode plugin disabled to confirm the fallback.
5. Confirm behavior for logged-out visitors and customers without orders or appointments.
6. Record the WordPress, PHP, LatePoint, Pro Features, and WooCommerce versions actually tested before tagging or deploying a release.

GitHub commits do not deploy this plugin to WordPress automatically.
