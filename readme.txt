=== Hangar Connect ===
Contributors: gsouza
Donate link: https://www.barbas.digital
Tags: hangar, connect, wordPress, remote, api
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.2.13
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

Recommended: install or update via Plugins -> Add New -> Upload Plugin (zip). Manual file copy also works, but WordPress update hooks may not run.

== Frequently Asked Questions ==

= Do I need a Barbas Update license? =

No. Hangar Connect is free. Updates use the public GitHub repository.

= Where is the health endpoint? =

GET /wp-json/hangar-connect/v1/health -- public discovery (version, site URL, connected flag, capabilities). No secrets. Legacy path /wp-json/barbas-connect/v1/health also works.

= Does it dump WP Activity Log data? =

HMAC endpoints expose summarized productivity data from WP Activity Log (users + Logbook) for Hangar. No raw WSAL admin dump.

== Changelog ==

= 0.2.13 =
* Brand kit: Hangar Connect lockup + Runway Blue admin UI, plugin icons, Hangar footer (discreet Barbas Digital credit).

= 0.2.12 =
* Barbas Update hub 2.2.25: plugin details modal title shows the plugin name (not "View details" / "Ver detalhes").
* Installation note: zip upload recommended; manual file copy still OK.

= 0.2.11 =
* Plugins list description: remove outdated Activity Reports bridge stubs copy; reflect native WP Activity Log reports.
* Plugin details (pt_BR) and FAQ aligned with current Hangar Connect capabilities.

= 0.2.10 =
* HMAC headers: prefer X-Hangar-Connect-* (legacy X-Barbas-Connect-* still accepted).
* New pairing keys use hc_... prefix (legacy bc_... still accepted).

= 0.2.9 =
* Site status: WP Activity Log badge Active/Inactive (requires plugin active, not only tables).
* Fix ready=false when tables exist but plugin is inactive.

= 0.2.8 =
* Activity users: mark deleted/missing WP accounts as active=false (WSAL-only history).

= 0.2.7 =
* Add HMAC `/wp/snapshot` for Hangar fleet sync (plugin/theme/core update counts).

= 0.2.6 =
* Include compact WSAL status styles in Site status card.

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
