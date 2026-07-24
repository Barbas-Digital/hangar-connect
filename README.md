# Hangar Connect

![Version](https://img.shields.io/badge/Version-0.2.4-blue.svg)
![WordPress](https://img.shields.io/badge/Tested%20up%20to-7.0-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-green.svg)
![License](https://img.shields.io/badge/License-GPLv2%20or%20Later-orange.svg)

WordPress site agent for **Hangar**: pairing keys, own REST API, and productivity reports via WP Activity Log (no Activity Reports dependency).

Free and open: public GitHub repo, no license token. Updates come from GitHub Releases (`hangar-connect.zip`).

Admin UI source strings are English (i18n); `languages/hangar-connect-pt_BR.po` + `.mo` provide Portuguese (Brazil).

**Slug:** `hangar-connect` (immutable). Display name defaults to Hangar Connect; filter `hangar_connect_display_name` for white-label (`{company} Connect`).

## Features

- **Settings -> Hangar Connect** -- connections list, generate pairing key (copy once), rotate when pending, disconnect / disconnect all.
- One active Hangar pairing at a time: New connection card and "Generate new key" are hidden while connected.
- REST namespace `/wp-json/hangar-connect/v1/...` (never `/wp/v2`).
- Legacy alias `/wp-json/barbas-connect/v1/...` for older Hangar images still calling the pre-rebrand path.
- Pairing secret encrypted at rest (OpenSSL AES via shared crypto helpers).
- GET `/health` public discovery; POST `/pair` for Hangar handshake; HMAC-protected `/capabilities` and productivity reports via WP Activity Log (no Activity Reports dependency).
- WordPress updates from public GitHub Releases (Plugin Update Checker) -- no Barbas Update license tab.
- Migrates leftover `barbas-connect/` plugin folder to `hangar-connect/` after updates.

## Installation

1. WordPress -> **Plugins -> Add New -> Upload Plugin**
2. File: `hangar-connect.zip`
3. **Activate**
4. **Settings -> Hangar Connect** -> generate a pairing key
5. Paste the key in Hangar to pair the site

If updating from `barbas-connect`, prefer upload of `hangar-connect.zip` (or wait for the in-plugin folder migration on 0.2.2+).

## REST routes (v0.2.2)

| Method | Route | Auth |
|--------|-------|------|
| GET | /hangar-connect/v1/health | Public |
| POST | /hangar-connect/v1/pair | Public (one-time pairing key) |
| GET | /hangar-connect/v1/capabilities | HMAC |
| GET | /hangar-connect/v1/activity/users | HMAC |
| GET | /hangar-connect/v1/activity/report | HMAC (query: user, from, to, format) |

Legacy: the same routes under `/barbas-connect/v1/...`.

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
|   |-- hangar-connect-migrate.php
|   |-- hangar-connect-rest.php
|   |-- barbas-update-*.php
|-- assets/
|-- languages/
\-- lib/plugin-update-checker/
```

## Changelog

### 0.2.2

- Legacy REST alias `barbas-connect/v1` (compatibility with Hangar images still on the old namespace).
- Migrate plugin directory `barbas-connect` -> `hangar-connect` after updates.
- pt_BR: compile `.mo`; Hangar product strings.
- Site status card: only Site URL + Health endpoint (pairing lives in Connections).

### 0.2.1

- Public repository; remove Connect license / Barbas Update token requirement.
- GitHub updates work without `BARBAS_LICENSE_TOKEN_CONNECT`.

### 0.2.0

- Rebrand to Hangar Connect (slug `hangar-connect`, REST `hangar-connect/v1`).
- Display name filter `hangar_connect_display_name` for future white-label.
- Release zip: `hangar-connect.zip`.

### 0.1.12

- After generate/rotate/disconnect, redirect to a clean admin URL (transient flash).
