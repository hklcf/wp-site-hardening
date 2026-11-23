# Changelog

All notable changes to WP Site Hardening are recorded here. This is the
only place the full version history is kept; `README.md` and `readme.txt`
only point here.

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
