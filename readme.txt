=== Barbas Connect ===
Contributors: gsouza
Donate link: https://www.barbas.digital
Tags: barbas, connect, central, remote, api
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.10
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Site agent for Barbas Central: pairing keys, secure REST API, and Activity Reports bridge.

== Description ==

Barbas Connect turns each WordPress site into a secure agent for Barbas Central.

* Connections screen: generate pairing key, copy once, rotate, disconnect / disconnect all.
* One Central pairing at a time (New connection hidden while a connection exists).
* Own REST namespace (/wp-json/barbas-connect/v1/...) -- never exposes generic /wp/v2.
* Pairing key is separate from the Barbas Update license token.
* Health discovery endpoint; HMAC-protected capabilities and Activity Reports bridge.
* Updates via Settings -> Barbas Update (Connect tab).

== Installation ==

1. Plugins -> Add New -> Upload Plugin -> barbas-connect.zip
2. Activate
3. Settings -> Barbas Connect -> Generate pairing key
4. Settings -> Barbas Update -> Connect -> save and validate license (for updates)

Always install via WordPress (not hosting file manager only).

== Frequently Asked Questions ==

= Is the pairing key the same as the update license? =

No. The pairing key authenticates Central API calls. The license under Barbas Update is only for GitHub plugin updates (BARBAS_LICENSE_TOKEN_CONNECT).

= Where is the health endpoint? =

GET /wp-json/barbas-connect/v1/health -- public discovery (version, site URL, connected flag, capabilities). No secrets.

= Does it dump WP Activity Log data? =

HMAC endpoints expose summarized Activity Reports data (users + productivity report) for Barbas Central. No raw WSAL admin dump.

== Changelog ==

= 0.1.10 =
* Hide New connection card when a connection already exists (one Central at a time).
* Reject create / POST /pair while already paired (HTTP 409).
* Format Connections table dates as DD/MM/YYYY HH:MM for pt_BR.

= 0.1.9 =
* Harden Activity Reports bridge loader (full AR include order, WSAL table checks, bridge_version in responses).
* Advertise activity_bridge_ready in /health and /capabilities for Barbas Central.
* Sites still on Connect < 0.1.7 return the old 501 stub ("bridge is not implemented yet") — update required.

= 0.1.8 =
* Vertically center Connections table cells (status, dates, actions vs two-line label).
* Align version pill with the page header title block.
* Suppress third-party admin notices on the Barbas Connect settings screen.

= 0.1.7 =
* Implement Activity Reports bridge: GET /activity/users and GET /activity/report (json/html/csv).
* Resolve users by email or username for multi-site Central reports.

= 0.1.6 =
* Clarify connections-screen description (no third-party worker branding).
* Fix Installation zip filename encoding (barbas-connect.zip).
* Align Site URL and Health endpoint value boxes in Site status.

= 0.1.5 =
* Rename user-facing Barbas Console references to Barbas Central.
* Site status card layout: stacked responsive grid; tighter New connection form.

= 0.1.4 =
* New connection card: remove Barbas Update license disclaimer; keep short pairing-key copy.

= 0.1.3 =
* Plugin list name without hyphen (Barbas Connect).
* Admin footer matches Barbas Update hub (logo + Barbas Digital + tagline).
* Empty connection label falls back to the WordPress site title.
* pt_BR translations for admin UI.

= 0.1.2 =
* Fix README encoding (Installation file line / replacement characters).

= 0.1.1 =
* POST /pair for Central pairing handshake.

= 0.1.0 =
* Initial MVP: connections UI, REST health + HMAC scaffolding, Activity Reports bridge stubs, Barbas Update hub (tab connect).

== Upgrade Notice ==

= 0.1.10 =
One Central pairing at a time; pt_BR date format in Connections table.

= 0.1.9 =
Required for Barbas Central productivity reports. Replaces the old 501 activity bridge stub.

= 0.1.8 =
Connections table alignment polish and cleaner Connect admin screen (no foreign notices).

= 0.1.7 =
Activity Reports bridge for Barbas Central multi-site productivity reports.

= 0.1.6 =
Cleaner plugin details (fixed zip name) and aligned Site status URL fields.

= 0.1.5 =
Barbas Central naming in UI/docs and improved Site status card layout.

= 0.1.4 =
Simpler new-connection copy (no license disclaimer on the pairing card).

= 0.1.3 =
UI polish: plugin name, hub footer branding, site-title label fallback, and Portuguese (Brazil) admin translations.

= 0.1.2 =
README encoding fix (clean install docs in the release zip).

= 0.1.1 =
Adds POST /pair for Barbas Central handshake.

= 0.1.0 =
First public MVP scaffold for Barbas Connect.
