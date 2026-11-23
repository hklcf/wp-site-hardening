# Changelog

All notable changes to WP Site Hardening are recorded here. This is the
only place the full version history is kept; `README.md` and `readme.txt`
only point here.

## 1.0.8 — 2026-11-24

- Security fix: the login page loader only checked the `wsh_login`
  query var, which is a registered query var — so anyone could
  reach the login page without knowing the slug just by opening
  `/?wsh_login=1` on any URL, which defeated the whole point of
  hiding the login page (the code is open source, so the var name
  is public). The loader now also requires the request path itself
  to be the slug. Login actions reached through the slug (logout,
  lost password, register, password reset) are unaffected: the
  query string is not part of the path check.

## 1.0.7 — 2026-11-24

- Change: WordPress no longer prints oEmbed discovery links — or
  the host script that makes embedded iframes responsive — into the
  page `<head>` when the oEmbed endpoint is closed to anonymous
  visitors, since the links would only advertise an endpoint that
  returns 401. The links are shown again as soon as oEmbed becomes
  publicly reachable: this plugin's REST mode `off`, and WP API Key
  Authentication (when installed) either open or in allowlist mode
  with a whitelist pattern matching `/oembed/1.0/embed` (its
  default whitelist has no oEmbed routes, and in `require_all` mode
  the whitelist is inactive, so oEmbed stays closed there).

## 1.0.6 — 2026-11-24

- Fix: logout was still broken in 1.0.5. The logout link had no nonce at
  all, so WordPress only showed the "Are you sure?" page and logout
  looped forever. Cause: the `logout_url` filter rebuilt the URL and
  copied `_wpnonce` back with `parse_str()`, but the URL arrives
  HTML-encoded (`&amp;`), and `parse_str()` reads that as a key
  literally named `amp;_wpnonce` — so the nonce was silently dropped.
  The filter now decodes `&amp;` first and keeps the whole query
  string (action, nonce, redirect) verbatim instead of copying
  individual parameters.
- Fix: a logged-out visitor opening `/login` was redirected straight
  to the custom login URL, giving the secret slug away. WordPress
  redirects `/login` (and `/login.php`) to `wp_login_url()`, and the
  old filter only sent `/wp-admin/` probes to the home page. The
  filter now sends every logged-out visitor to the home page —
  from `/wp-admin/`, `/login`, and any "Log in" link — so only
  someone who already knows the slug can reach the login page.
  Links generated while already on the slug login page (lost
  password, reset, post-logout) keep the slug, as do logged-in
  users (reauth flows).
- Fix: after logout, the login form with the "logged out" message is
  now shown via the `logout_redirect` filter. `wp_login_url()` alone
  cannot do this, because by the time it is called the user is
  already logged out and the filter above would send them home.
- Fix: saving settings with an empty slug field no longer silently
  generates a fresh random slug (which could change the login URL
  without anyone noticing). A blank field now keeps the stored slug;
  the only ways to get a new slug are the "Generate new random slug"
  button or typing a different slug. Activation still only generates
  a slug when none is stored, so updates never change it.

## 1.0.5 — 2026-11-24

- Fix: the custom login slug page forced `$action = 'login'` before
  loading wp-login.php, so every other login action reached through
  the slug rendered the plain login form instead of running: logout
  did nothing, and lost password, register, password reset
  (`action=rp`) and post password were all broken too. `$action` is
  now taken from the request, exactly as wp-login.php itself does.
- Fix: a direct `wp-login.php?action=logout` link now reaches
  wp-login.php instead of bouncing to the home page with the user
  still logged in. Core checks the nonce and then redirects to the
  slug login page with `?loggedout=true`.
- Fix: a logged-out visitor opening `/wp-admin/` was redirected to
  the custom login URL, which put the secret slug in the address
  bar. They are now sent to the home page instead, so only someone
  who already knows the slug can reach the login page.

## 1.0.4 — 2026-11-24

- Defaults changed: nothing is disabled by default. A fresh install changes
  nothing until the site owner switches a restriction on (XML-RPC off,
  feeds off, REST API `off`, custom login URL off). Existing saved settings
  are not changed by this update.

## 1.0.3 — 2026-11-24

- Settings page: "WP API Key Authentication" is now a link to
  https://github.com/hklcf/wp-api-key-authentication, in the intro
  paragraph and in the REST API description, so it can be downloaded
  from GitHub.

## 1.0.2 — 2026-11-24

- Fix: deferred rewrite flush to the next init, so turning hide-login off
  actually removes the old login slug instead of flushing it back into
  the database.

## 1.0.1 — 2026-11-24

- Fix: load `wp-login.php` with its variables in global scope, so the
  custom login page no longer shows `Undefined variable $user_login` /
  `$error` warnings or a wrong login-action body class.

## 1.0.0 — 2026-11-24

- First release. Disable XML-RPC (filter + hard 403 + header/link
  removal), disable feeds (`/feed/`, `/comments/feed/`, all feed types)
  with 301 or 404, fully disable the REST API by default with an
  authenticated-only mode for use with WP API Key Authentication, custom
  login URL with randomly generated slug on activation, rewrite rule,
  `login_url` / `logout_url` / `lostpassword_url` / `register_url` /
  `site_url` rewriting, and a `wp-config.php` escape hatch.
- Settings page under Settings → WP Site Hardening.
- `uninstall.php`: deleting the plugin removes its settings.
