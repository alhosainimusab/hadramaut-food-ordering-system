# Changelog

All notable changes to this project. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [2.0.0] – 2026-10-06 — Portfolio release

Reworked after the course submission for publication on GitHub.

### Security
- Passwords are now hashed with `password_hash()` and checked with `password_verify()`. They were previously stored and compared in plain text.
- Removed the hard-coded admin-registration key, which let anyone register as admin. New accounts are always customers.
- Added CSRF tokens to every form. Deletes and logout changed from GET links to POST.
- Escaped all output. This fixes a stored XSS: customer names were printed unescaped on the admin dashboard.
- Session ID is regenerated on login, and the session cookie is `HttpOnly` with `SameSite=Lax`.
- Image uploads are checked for extension, size (2 MB) and real image content. Uploads get random file names.
- Database errors are logged instead of being shown to users.
- Admin pages check permissions before printing any output.
- Moved to a `public/` web root. Added `.htaccess` deny rules for `src/`, `database/`, `tests/` and `docs/`.

### Fixed
- New menu items saved their category as `0`. Cause: `bind_param("ssdds")` bound the category as a double. As a result, the menu category filter never worked.
- Changing a password always failed, because login compared plain text while the change form used `password_verify()`.
- Submitting feedback without a rating caused a fatal error. The rating is optional in the form but the column was `NOT NULL`.
- "Headers already sent" redirects: pages printed the header before calling `header('Location')`. It only worked on XAMPP because output buffering is on there.
- The "Forgot password?" link on the login page pointed to a page that requires login.
- Fixed a broken layout (`<main>` closed immediately).
- Fixed references to missing files (`assets/js/scripts.js`, `img/welcome.jpg`) and the undefined `custom-navbar` style.
- Admin dashboard status badges used non-existent classes (`bg-pending` etc.).
- Cart badge and "Total Items" counted distinct dishes instead of units.

### Added
- Delivery (flat RM 5.00 fee) or self-pickup at checkout. The database already had these columns, but the code never set them.
- Prices are re-read from the database at checkout, so a stale cart price is never charged.
- Customers can cancel an order while it is still *Pending*.
- Admin dashboard: best sellers, orders by status, and revenue that excludes cancelled orders.
- Admin orders: filter by status, and exact search by order number.
- Admin users: search by name or email, plus order count and total spend per customer.
- Admin dashboard: feedback count card.
- Cart: "Continue shopping" button.
- Admin feedback: average rating.
- Server-side validation for registration, profile, checkout, feedback, menu items and order status.
- Unit tests (`tests/run_tests.php`, 33 checks) and an end-to-end smoke test (`tests/smoke_test.sh`, 53 checks). Both were verified to fail on 10 deliberately injected bugs.
- GitHub Actions CI: PHP lint, unit tests, and the smoke test against MySQL 8.
- New responsive theme, screenshots, README and CHANGELOG.

### Changed
- Merged the separate customer and admin headers. Profile and password code (previously copy-pasted into 4 files) moved to `src/account.php`. Order display code moved to `src/order_view.php`.
- Database settings moved to `src/config.php`, with environment-variable overrides.
- Database schema:
  - Added foreign keys: `CASCADE` from users, `RESTRICT` from menu items.
  - Added CHECK constraints and indexes.
  - Made `feedback.rating` nullable.
- Image paths are stored relative to `public/assets/img/`.
- Removed 14 duplicate image copies, and compressed the remaining images.
- Line endings normalised to LF via `.gitattributes`.

### Removed
- Real personal data from the SQL dump (name, phone numbers, an ID-like number, address and email addresses). Replaced with clearly fictional demo data, in which every order has items and correct totals.
- Card number, expiry and CVV fields are no longer submitted to the server. Card payment is clearly marked as simulated.

## [1.0.0] – 2026-06 — Course submission

The original group submission: a PHP/MySQL food ordering system with a customer site and an admin panel.
