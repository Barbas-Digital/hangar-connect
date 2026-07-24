=== Hangar Connect ===
Contributors: gsouza
Donate link: https://www.barbas.digital
Tags: hangar, connect, wordPress, remote, api
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.2.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Site agent for Hangar: pairing keys, secure REST API, and productivity reports via WP Activity Log.

== Description ==

Hangar Connect turns each WordPress site into a secure agent for Hangar.

Free plugin: public GitHub repository, no license token required.

* Connections screen: generate pairing key, copy once, rotate when pending, disconnect / disconnect all.
* One Hangar pairing at a time (New connection and Generate new key hidden while connected).
* Own REST namespace (/wp-json/hangar-connect/v1/...) -- never exposes generic /wp/v2.
* Legacy alias /wp-json/barbas-connect/v1 for older Hangar images.
* Health discovery endpoint; HMAC-protected capabilities and productivity reports via WP Activity Log.
* Updates from public GitHub Releases (hangar-connect.zip).

== Installation ==

1. Plugins -> Add New -> Upload Plugin -> hangar-connect.zip
2. Activate
3. Settings -> Hangar Connect -> Generate pairing key
4. Pair the site from Hangar using that key

Always install via WordPress (not hosting file manager only).

== Frequently Asked Questions ==

= Do I need a Barbas Update license? =

No. Hangar Connect is free. Updates use the public GitHub repository.

= Where is the health endpoint? =

GET /wp-json/hangar-connect/v1/health -- public discovery (version, site URL, connected flag, capabilities). No secrets. Legacy path /wp-json/barbas-connect/v1/health also works.

= Does it dump WP Activity Log data? =

HMAC endpoints expose summarized Activity Reports data (users + productivity report) for Hangar. No raw WSAL admin dump.

== Changelog ==

= 0.2.5 =
* Fix pt_BR mojibake (broken UTF-8 in .mo from unicode_escape compile).
* Compact WP Activity Log status inside Site status card.
* Translate new Activity Log readiness strings.

= 0.2.4 =
* Native productivity reports from WP Activity Log (no Activity Reports plugin).
* WSAL readiness status in admin and capabilities.
* GET /wp/users and /activity/users without AR.


= 0.2.2 =
* Legacy REST alias barbas-connect/v1 for older Hangar images.
* Migrate plugin folder barbas-connect to hangar-connect after updates.
* pt_BR .mo + Hangar product strings.
* Site status card shows only Site URL and Health endpoint.

= 0.2.1 =
* Public repository; remove Connect license / Barbas Update token requirement.
* GitHub updates work without BARBAS_LICENSE_TOKEN_CONNECT.

= 0.2.0 =
* Rebrand to Hangar Connect (slug hangar-connect, REST hangar-connect/v1).
* Display name filter hangar_connect_display_name for future white-label.
* Release zip hangar-connect.zip.

= 0.1.12 =
* After generate/rotate/disconnect, redirect to a clean admin URL (transient flash). JS also strips legacy bc_notice/bc_id/bc_msg so refresh never re-shows them.

= 0.1.11 =
* Remove the Barbas Digital eyebrow label above the Hangar Connect title.
* Hide Generate new key while a connection is Connected (keep Disconnect).

= 0.1.10 =
* Hide New connection card when a connection already exists (one Central at a time).
* Reject create / POST /pair while already paired (HTTP 409).
* Format Connections table dates as DD/MM/YYYY HH:MM for pt_BR.

== Upgrade Notice ==

= 0.2.2 =
Install hangar-connect.zip. Dual REST namespaces fix pairing with Hangar 0.9.x. Folder migrate from barbas-connect when possible.

= 0.2.1 =
Free public updates: license tab no longer required. Reinstall hangar-connect.zip if updating from a private-repo build.

= 0.2.0 =
Clean slug rename to hangar-connect. Remove any old barbas-connect folder before installing hangar-connect.zip. Re-pair sites with Hangar after install.
