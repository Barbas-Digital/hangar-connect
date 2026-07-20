=== Barbas - Connect ===
Contributors: gsouza
Donate link: https://www.barbas.digital
Tags: barbas, connect, console, remote, api
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Site agent for Barbas Console: pairing keys, secure REST API, and bridge stubs for Activity Reports.

== Description ==

Barbas Connect turns each WordPress site into a secure agent for Barbas Console.

* ManageWP Worker–like connections screen: generate pairing key, copy once, rotate, disconnect / disconnect all.
* Own REST namespace (`/wp-json/barbas-connect/v1/...`) — never exposes generic `/wp/v2`.
* Pairing key is separate from the Barbas Update license token.
* Health discovery endpoint; HMAC-protected capability and activity bridge stubs.
* Updates via **Settings -> Barbas Update** (Connect tab).

== Installation ==

1. Plugins -> Add New -> Upload Plugin -> `barbas-connect.zip`
2. Activate
3. Settings -> Barbas Connect -> Generate pairing key
4. Settings -> Barbas Update -> Connect -> save and validate license (for updates)

Always install via WordPress (not hosting file manager only).

== Frequently Asked Questions ==

= Is the pairing key the same as the update license? =

No. The pairing key authenticates Console API calls. The license under Barbas Update is only for GitHub plugin updates (`BARBAS_UPDATE_TOKEN_CONNECT`).

= Where is the health endpoint? =

`GET /wp-json/barbas-connect/v1/health` — public discovery (version, site URL, connected flag, capabilities). No secrets.

= Does it dump WP Activity Log data? =

No. Activity bridge routes are stubs until the Console + Activity Reports integration is ready. No raw WSAL dump.

== Changelog ==

= 0.1.1 =
* POST /pair for Console pairing handshake.

= 0.1.0 =
* Initial MVP: connections UI, REST health + HMAC scaffolding, Activity Reports bridge stubs, Barbas Update hub (tab `connect`).

== Upgrade Notice ==

= 0.1.1 =
Adds POST /pair for Barbas Console handshake.

= 0.1.0 =
First public MVP scaffold for Barbas Connect.
