# Changelog

## 0.11.5 - 2026-09-17

- Replace the authored SVGs with the supplied Ishi custom calendar, nail, chat and avatar outline/filled font icons.
- Reuse the registered icon stylesheet or enqueue the verified existing uploaded stylesheet through WordPress; do not copy font assets.
- Preserve filled-only primary selection, keyboard focus, labels, unread badge and all submenu/layout behavior.
- Show text labels if the uploaded stylesheet is unavailable.

## 0.11.4 - 2026-09-17

- Use paired original SVG icons: outline when inactive and filled when selected, driven by the existing ARIA selection state.
- Remove selected primary-tab background/text recoloring; preserve secondary-tab styling and keyboard focus indicators.
- Remove the dashboard icon-font enqueue dependency; inline SVGs remain available without Elementor or external font requests.
- Preserve navigation order, one-row booking submenu, spacing, unread badge and existing feature handlers.

## 0.11.3 - 2026-09-17

- Keep Appointments, History and New Appointment on one submenu row; allow local horizontal scrolling on narrow screens instead of wrapping New Appointment below.
- Preserve existing typography, colors, borders, section padding and content spacing.

## 0.11.2 - 2026-09-17

- Remove selected-menu borders/underlines and pointer-focus outlines while retaining keyboard focus indicators.
- Remove the verified native Welcome heading and logout link from adapted dashboard output.
- Apply 20px left/right section padding at every viewport without changing the 40px content gap.

## 0.11.1 - 2026-09-17

- Use non-submitting tab buttons without hash targets or navigation focus scrolling.
- Place New Appointment after History and keep its submenu available during booking.
- Apply compact submenu typography and one 40px gap to content.
- Add Custom Press-Ons using the existing order cards, totals, modal and paginated query, restricted to orders with a custom-press-ons category item; mixed orders remain in both lists.
- Keep category pagination independent and preserve customer ownership and existing eligibility checks.

## 0.11.0 - 2026-09-16

- Group the server-rendered dashboard into Appointments, Press-Ons, Messages and Account.
- Compose Ishi Profile through its existing shortcode and preserve native panels and feature handlers.
- Add keyboard-operable hierarchical navigation, responsive scoped styles and the existing Nails icon asset.
- Keep New Appointment as an Appointments action; preserve Press-Ons pagination selection.
- Expose an explicit HTML adapter for custom renderers; keep unknown or ambiguous markup in its original layout.


## 0.10.32 - 2026-09-16

- Share ownership, type/status visibility, and category eligibility between Press-Ons lists and lightboxes.
- Return a controlled lightbox error when WooCommerce is unavailable; stop immediately after rejected requests.
- Combine History naming, tab ordering, and pagination selection into one DOM pass, including History labels on repeated dashboards.
- Add list/lightbox policy parity and missing-WooCommerce endpoint regression tests.

## 0.10.31 - 2026-09-16

- Add Press-Ons and Addresses through LatePoint's native dashboard trigger/content hooks.
- Prepare each custom tab once and reuse its panel HTML; remove legacy HTML insertion methods.
- Preserve tab order, Messages badges, single Addresses rendering, and Press-Ons pagination selection on the shortcode path.
- Isolate repeated/nested renders and release pending state after renderer failures.
- Add native-hook lifecycle regression tests and update the base fixtures to execute action hooks.

## 0.10.30 - 2026-09-16

- Paginate Press-Ons using WooCommerce My Account Orders' default page size and positive limit overrides.
- Filter eligible orders before filling pages, using bounded WooCommerce API queries and one-order lookahead.
- Add Previous/Next links that preserve the dashboard URL and keep Press-Ons selected.
- Handle invalid and out-of-range pages without displaying an empty trailing page.
- Add pagination regression checks to GitHub Actions.

## 0.10.29 - 2026-09-16

- Render the Addresses shortcode once, directly inside its panel.
- Build the existing WooCommerce fallback only when the Addresses shortcode is unavailable.
- Remove the second Addresses rendering filter from the Press-Ons controller.
- Preserve intentionally empty shortcode output; restore parser state on rendering exceptions.
- Retain explicit tab order: Appointments, History, Press-Ons, Profile, Addresses, New Appointment, Messages.
- Add standalone regression tests, automated PHP checks, and release verification instructions.

## 0.10.28

- Added the Addresses dashboard tab. Subsequent branch changes embedded the customer-addresses shortcode and introduced explicit tab ordering.
