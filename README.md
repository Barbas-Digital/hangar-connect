# Hangar Connect

![Version](https://img.shields.io/badge/Version-0.2.0-blue.svg)
![WordPress](https://img.shields.io/badge/Tested%20up%20to-7.0-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-green.svg)
![License](https://img.shields.io/badge/License-GPLv2%20or%20Later-orange.svg)

WordPress site agent for **Hangar**: pairing keys, own REST API, and Activity Reports bridge.

Admin UI source strings are English (i18n); `languages/hangar-connect-pt_BR.po` provides Portuguese (Brazil).

**Slug:** `hangar-connect` (immutable). Display name defaults to Hangar Connect; filter `hangar_connect_display_name` for white-label (`{company} Connect`).

## Features

- **Settings -> Hangar Connect** -- connections list, generate pairing key (copy once), rotate when pending, disconnect / disconnect all.
- One active Hangar pairing at a time: New connection card and "Generate new key" are hidden while connected.
- REST namespace `/wp-json/hangar-connect/v1/...` (never `/wp/v2`).
- Pairing secret encrypted at rest (OpenSSL AES via Barbas Update crypto); distinct from hub license token.
- GET `/health` public discovery; POST `/pair` for Hangar handshake; HMAC-protected `/capabilities` and Activity Reports bridge.
- **Settings -> Barbas Update** -- license tab **Connect** (`BARBAS_LICENSE_TOKEN_CONNECT`).

## Installation

1. WordPress -> **Plugins -> Add New -> Upload Plugin**
2. File: `hangar-connect.zip`
3. **Activate**
4. **Settings -> Hangar Connect** -> generate a pairing key

## Update license (private repository)

1. **Settings -> Barbas Update -> Connect**
2. Paste the license -> **Validate license** -> **Save**

**Safe option (wp-config.php):**

```php
define('BARBAS_LICENSE_TOKEN_CONNECT', 'github_pat_...');
```

## REST routes (v0.2.0)

| Method | Route | Auth |
|--------|-------|------|
| GET | /hangar-connect/v1/health | Public |
| POST | /hangar-connect/v1/pair | Public (one-time pairing key) |
| GET | /hangar-connect/v1/capabilities | HMAC |
| GET | /hangar-connect/v1/activity/users | HMAC |
| GET | /hangar-connect/v1/activity/report | HMAC (query: user, from, to, format) |

POST /pair body: `{ "pairing_key": "bc_..." }` -> `{ ok, connection_id, site_url, site_name }`.

## Structure

```
hangar-connect/
|-- hangar-connect.php
|-- uninstall.php
|-- readme.txt
|-- includes/
|   |-- hangar-connect-admin.php
|   |-- hangar-connect-activity.php
|   |-- hangar-connect-connections.php
|   |-- hangar-connect-hmac.php
|   |-- hangar-connect-rest.php
|   |-- barbas-update-*.php
|-- assets/
|-- languages/
\-- lib/plugin-update-checker/
```

## Changelog

### 0.2.0

- Rebrand to Hangar Connect (slug `hangar-connect`, REST `hangar-connect/v1`).
- Display name filter `hangar_connect_display_name` for future white-label.
- Release zip: `hangar-connect.zip`.

### 0.1.12

- After generate/rotate/disconnect, redirect to a clean admin URL (transient flash).
