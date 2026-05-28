\---



\## 6. `CHANGELOG.md`



```markdown

\# Changelog



All notable changes to Ballkorg Cookie Consent are documented here.  

Format: \[Keep a Changelog](https://keepachangelog.com/en/1.0.0/) — versioning: \[SemVer](https://semver.org/).



\---

## [5.1.0] — 2026-05-28

### Added

- Language switcher in admin panel (ru / en) — affects banner text and admin UI labels

- Russian translation (`ru_RU`) — `languages/ballkorg-cookie-consent-ru_RU.po` / `.mo`

- CSV export of consent logs (streamed, all rows, UTF-8 BOM for Excel)

- "Delete consent data on plugin uninstall" setting

- "Reset cookie preferences" button for user

### Improved

- Pagination redesigned — window navigation with ellipsis

- Admin notice for missing DB table with dismiss button

### Fixed

- `class-admin-page.php` export button wired to AJAX nonce

- `admin.php?page=` URL corrected for pagination links

\## \[5.0.0] — 2026-05-27



\### Changed — Breaking

\- Complete architectural rewrite: monolithic `functions.php` → OOP namespaced classes

\- PHP sessions removed entirely; stateless architecture (cookie + localStorage only)

\- Raw IP storage removed (GDPR fix); replaced with SHA-256 hash + anonymized prefix

\- Log file moved from `wp-content/` root to `uploads/bcc-logs/` with `.htaccess` deny

\- All inline CSS/JS extracted to `assets/` and loaded via `wp\_enqueue\_scripts`

\- Plugin prefix changed from `ballkorg\_` → `bcc\_` (constants, hooks, options, DB table)

\- DB table renamed from `wp\_cookie\_consents` → `wp\_bcc\_cookie\_consents`



\### Added

\- `class-analytics-loader.php` — Google Consent Mode v2 defaults + lazy Yandex Metrika

\- `class-plugin.php` — manual DI container, single boot point

\- `uninstall.php` — clean DB table + options on plugin delete

\- `README.md`, `CHANGELOG.md`, `LICENSE` — GitHub/WP Directory readiness

\- PSR-4 autoloader (no Composer dependency)

\- PHP 8.0 minimum requirement guard

\- `bcc\_cookie\_policy\_url` filter for custom policy URL

\- `bcc\_yandex\_metrika\_id` filter for Metrika ID configuration

\- Pagination on admin consent log table



\### Fixed

\- Banner shown on cached pages (JS-side consent check, not PHP-side)

\- Debug HTML comment leaking to all visitors via `wp\_head`

\- `CREATE TABLE` running on every page load

\- Log file publicly accessible via URL



\### Removed

\- PHP `session\_start()` — breaks all page caching layers

\- `original\_ip` field from consent JSON — was storing PII in plain text

\- Inline `<style>` / `<script>` blocks from PHP output



\---



\## \[4.5.1] — 2026-02-04



\### Fixed

\- Banner display regression on front page

\- Cron tasks not scheduled after fresh install



\### Added

\- IP anonymization for IPv6 addresses

\- `do\_not\_track` flag stored in consent record



\---



\## \[4.0.0] — 2025-12-01



\### Added

\- Initial release as custom plugin for ballkorg.ru

\- Cookie consent banner with accept/reject buttons

\- Consent logging to custom DB table

\- Admin page under Tools → Cookie Logs

\- Yandex Metrika lazy-loading after consent

\- GDPR / 152-ФЗ compliance architecture

