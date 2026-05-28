# Ballkorg Cookie Consent

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue?logo=wordpress)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4?logo=php)](https://php.net)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![Version](https://img.shields.io/badge/version-5.1.0-green)](CHANGELOG.md)

GDPR and 152-FZ compliant cookie consent banner for WordPress.

It logs consent events to a custom database table, lazy-loads Yandex Metrika and Google Consent Mode v2, and keeps raw IP addresses out of storage.

## Features

- **Frontend cookie banner** — responsive slide-up banner with accept/reject actions
- **Consent logging** — every decision is saved to a custom database table
- **Privacy-first storage** — raw IP is never stored; only a SHA-256 hash and anonymized prefix
- **Lazy analytics** — Yandex Metrika and Google Consent Mode v2 load only after consent
- **CSV export** — export the consent log from the admin screen
- **Language selector** — switch the plugin/banner language in the admin settings
- **Banner toggle** — enable or disable the frontend banner from the admin settings
- **Uninstall control** — choose whether consent data is deleted when the plugin is removed
- **WordPress standards** — namespaced OOP code and prepared database queries

## Requirements

| Dependency | Minimum |
|---|---|
| WordPress | 6.0 |
| PHP | 8.0 |
| MySQL | 5.7 / MariaDB 10.3 |

## Installation

### Manual install

1. Download the latest release from [GitHub Releases](https://github.com/ballkorg/ballkorg-cookie-consent/releases)
2. Upload the `ballkorg-cookie-consent` folder to `/wp-content/plugins/`
3. Activate the plugin in WordPress

### Via WP-CLI

```bash
wp plugin install https://github.com/ballkorg/ballkorg-cookie-consent/archive/refs/heads/main.zip --activate