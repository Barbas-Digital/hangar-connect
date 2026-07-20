# Barbas - Connect

![Version](https://img.shields.io/badge/Version-0.1.0-blue.svg)
![WordPress](https://img.shields.io/badge/Tested%20up%20to-7.0-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-green.svg)
![License](https://img.shields.io/badge/License-GPLv2%20or%20Later-orange.svg)

WordPress site agent for **Barbas Console**: pairing keys, own REST API, and bridge stubs for Activity Reports.

WordPress admin UI strings are English in source (i18n-ready; pt_BR later).

## Features

- **Settings → Barbas Connect** — connections list, generate pairing key (copy once), rotate, disconnect / disconnect all.
- REST namespace /wp-json/barbas-connect/v1/... (never /wp/v2).
- Pairing secret encrypted at rest (OpenSSL AES via Barbas Update crypto); distinct from hub license token.
- GET /health public discovery; HMAC-protected /capabilities and activity stubs.
- **Settings → Barbas Update** — license tab **Connect** (BARBAS_UPDATE_TOKEN_CONNECT).

## Installation

1. WordPress → **Plugins → Add New → Upload Plugin**
2. File: arbas-connect.zip
3. **Activate**
4. **Settings → Barbas Connect** → generate a pairing key

## Update license (private repository)

1. **Settings → Barbas Update → Connect**
2. Paste the license → **Validate license** → **Save**

**Safe option (wp-config.php):**

`php
define('BARBAS_UPDATE_TOKEN_CONNECT', 'github_pat_...');
`

## REST routes (v0.1.0)

| Method | Route | Auth |
|--------|-------|------|
| GET | /barbas-connect/v1/health | Public |
| GET | /barbas-connect/v1/capabilities | HMAC |
| GET | /barbas-connect/v1/activity/users | HMAC (stub) |
| GET | /barbas-connect/v1/activity/report | HMAC (stub) |

HMAC headers: X-Barbas-Connect-Id, X-Barbas-Connect-Timestamp, X-Barbas-Connect-Nonce, X-Barbas-Connect-Signature.

## Folder structure

`
barbas-connect/
|-- barbas-connect.php
|-- readme.txt
|-- uninstall.php
|-- assets/
|   |-- css/
|   |-- js/
|   \-- img/
|-- includes/
|   |-- barbas-connect-admin.php
|   |-- barbas-connect-connections.php
|   |-- barbas-connect-hmac.php
|   |-- barbas-connect-rest.php
|   |-- barbas-update-*.php
|   \-- ...
|-- languages/
|-- lib/
|   \-- plugin-update-checker/
\-- scripts/
`

## Requirements

WordPress 5.8+, PHP 7.4+, OpenSSL for secure pairing key storage.

## Changelog

### 0.1.0
- Initial MVP scaffold: admin connections UI, REST health + HMAC helpers, Activity Reports bridge stubs, Barbas Update hub tab connect.

## Next

Barbas Console (SaaS) will consume health + HMAC APIs and complete pairing. Not included in this plugin repo yet.
