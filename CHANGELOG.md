# Changelog

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
