# -*- coding: utf-8 -*-
"""Write barbas-connect README.md as UTF-8 (no BOM), ASCII-safe trees."""
from pathlib import Path

readme = r"""# Barbas Connect

![Version](https://img.shields.io/badge/Version-0.1.6-blue.svg)
![WordPress](https://img.shields.io/badge/Tested%20up%20to-7.0-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-green.svg)
![License](https://img.shields.io/badge/License-GPLv2%20or%20Later-orange.svg)

WordPress site agent for **Barbas Central**: pairing keys, own REST API, and bridge stubs for Activity Reports.

Admin UI source strings are English (i18n); languages/barbas-connect-pt_BR.mo provides Portuguese (Brazil).

## Features

- **Settings -> Barbas Connect** -- connections list, generate pairing key (copy once), rotate, disconnect / disconnect all.
- REST namespace /wp-json/barbas-connect/v1/... (never /wp/v2).
- Pairing secret encrypted at rest (OpenSSL AES via Barbas Update crypto); distinct from hub license token.
- GET /health public discovery; POST /pair for Central handshake; HMAC-protected /capabilities and activity stubs.
- **Settings -> Barbas Update** -- license tab **Connect** (BARBAS_LICENSE_TOKEN_CONNECT).

## Installation

1. WordPress -> **Plugins -> Add New -> Upload Plugin**
2. File: barbas-connect.zip
3. **Activate**
4. **Settings -> Barbas Connect** -> generate a pairing key

## Update license (private repository)

1. **Settings -> Barbas Update -> Connect**
2. Paste the license -> **Validate license** -> **Save**

**Safe option (wp-config.php):**

```php
define('BARBAS_LICENSE_TOKEN_CONNECT', 'github_pat_...');
```

## REST routes (v0.1.6)

| Method | Route | Auth |
|--------|-------|------|
| GET | /barbas-connect/v1/health | Public |
| POST | /barbas-connect/v1/pair | Public (one-time pairing key) |
| GET | /barbas-connect/v1/capabilities | HMAC |
| GET | /barbas-connect/v1/activity/users | HMAC (stub) |
| GET | /barbas-connect/v1/activity/report | HMAC (stub) |

HMAC headers: X-Barbas-Connect-Id, X-Barbas-Connect-Timestamp, X-Barbas-Connect-Nonce, X-Barbas-Connect-Signature.

POST /pair body: { "pairing_key": "bc_..." } -> { ok, connection_id, site_url, site_name }.

## Folder structure

```
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
```

## Requirements

WordPress 5.8+, PHP 7.4+, OpenSSL for secure pairing key storage.

## Changelog

### 0.1.6
- Clarify connections-screen description (no third-party worker branding).
- Fix Installation zip filename encoding (barbas-connect.zip).
- Align Site URL and Health endpoint value boxes in Site status.

### 0.1.5
- Rename user-facing Barbas Console references to Barbas Central.
- Site status card: responsive stacked grid (URL full width; health + Activity Reports; Connected to Central).
- Tighten New connection form spacing.

### 0.1.4
- New connection card: drop the Barbas Update license disclaimer; keep a short pairing-key explanation.

### 0.1.3
- Plugin list name without hyphen (Barbas Connect).
- Admin footer matches Barbas Update hub branding.
- Empty pairing label falls back to the WordPress site title.
- pt_BR translations for admin UI.

### 0.1.2
- Fix README encoding (corrupted Installation line / replacement characters).

### 0.1.1
- POST /pair handshake for Barbas Central (match pending bc_... key -> connection_id).

### 0.1.0
- Initial MVP scaffold: admin connections UI, REST health + HMAC helpers, Activity Reports bridge stubs, Barbas Update hub tab connect.

## Next

Use with Barbas Central SaaS for multi-site jobs and Activity Reports bridges.
"""

path = Path(__file__).resolve().parents[1] / "README.md"
path.write_bytes(readme.encode("utf-8"))
print("wrote", path, "bytes", path.stat().st_size)
raw = path.read_bytes()
assert not raw.startswith(b"\xef\xbb\xbf"), "BOM present"
text = raw.decode("utf-8")
assert "\ufffd" not in text, "U+FFFD found"
assert "\x08" not in text, "BS control char found"
assert "File: barbas-connect.zip" in text
assert "ManageWP" not in text
assert "arbas-connect" not in text.replace("barbas-connect", "")
assert "Barbas - Connect" not in text
assert "Barbas Console" not in text
print("README encoding OK")
