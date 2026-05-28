=== Ballkorg Cookie Consent ===
Contributors: ballkorg
Tags: cookie, gdpr, consent, privacy, 152-fz
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 5.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

GDPR and 152-FZ compliant cookie consent banner with consent logging, lazy analytics, and a settings tab for banner visibility and language.

== Description ==

Cookie consent banner for WordPress. Logs every decision to a custom database table,
lazy-loads Yandex Metrika and supports Google Consent Mode v2.
Raw IP is never stored — only a SHA-256 hash and an anonymized prefix.

The plugin includes a Tools → Cookie Logs screen where you can:
* review consent records
* export all logs to CSV
* enable or disable the frontend banner
* choose the plugin/banner language
* decide whether consent data is deleted on uninstall

== Installation ==

1. Upload the `ballkorg-cookie-consent` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Open **Tools → Cookie Logs** to review logs and change settings

== Changelog ==

= 5.1.0 =
* Added a Tools → Cookie Logs settings tab
* Added a frontend banner on/off switch
* Added a plugin/banner language selector
* Added CSV export of consent logs
* Added uninstall data retention control

= 5.0.0 =
* Complete architectural rewrite: monolithic to OOP namespaced classes
* PHP sessions removed; stateless architecture
* Raw IP storage removed for privacy compliance
* Google Consent Mode v2 support added
* Lazy Yandex Metrika loading

= 4.5.1 =
* Banner display regression fixed
* Cron tasks scheduling fixed