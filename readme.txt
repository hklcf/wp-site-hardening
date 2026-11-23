=== WP Site Hardening ===
Contributors: HKLCF
Tags: security, xml-rpc, rest-api, feeds, login
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Disable XML-RPC, disable feeds, fully disable the REST API, and hide wp-login.php behind a randomly generated login slug. Nothing is disabled by default.

== Description ==

Hardening for any WordPress site. One plugin, four jobs:

* Disable XML-RPC — `/xmlrpc.php` returns 403.
* Disable feeds — `/feed/` and `/comments/feed/` redirect to the home page (or 404).
* Fully disable the REST API — every `/wp-json/` request gets 403 until you switch it back on.
* Custom admin login URL — the login page moves from `/wp-login.php` to a randomly generated slug, e.g. `/login-a1b2c3d4e5/`.

To use the API again, switch the REST API mode to "Restrict to authenticated requests" in Settings > WP Site Hardening. That mode works with WP API Key Authentication: a valid API key counts as authenticated and passes, while anonymous requests get 401. Switch back to "Fully disable" when you no longer need the API.

== Installation ==

1. Copy the `wp-site-hardening` folder into `wp-content/plugins/`.
2. Activate WP Site Hardening in WordPress admin.
3. Open Settings > WP Site Hardening and switch on the restrictions you want. If you switch on the custom login URL, bookmark it.

== Frequently Asked Questions ==

= I forgot the custom login slug. Am I locked out? =

No. Add `define( 'WP_SITE_HARDENING_DISABLE_HIDE_LOGIN', true );` to `wp-config.php` and `/wp-login.php` works normally again. Fix the slug, then remove the line.

= Will this break API publishing? =

Yes, in fully disabled mode — that is the point. Fully disabled means every REST request gets 403, including API-key requests. Switch to "Restrict to authenticated requests" when you need to publish via the API, then switch back to "Fully disable" afterwards. Nothing is disabled by default, so this only applies once you switch a restriction on.

= Does disabling feeds affect SEO? =

Feed URLs redirect to the home page (301) by default. Search engines follow the redirect; there is no feed content left to index.

== Changelog ==

See CHANGELOG.md for the full version history.
