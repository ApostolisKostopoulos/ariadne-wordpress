# Connect: what the plugin relies on

Copied from the Connect contract in the private Ariadne repository, `docs/plan/connect-wordpress.md` (2026-11-16). That file is the source; **a change to Connect changes both files**.

## Addresses

| What | Production | Local (`dev/mu-plugins/ariadne-local.php`) |
|---|---|---|
| Dashboard (`APP_URL`, already ends in `/admin`) | `https://ariadnebot.com/admin` | `http://localhost:5173/admin` |
| Connect page | `{APP_URL}/connect/wordpress` | `http://localhost:5173/admin/connect/wordpress` |
| API | `https://api.ariadnebot.com` | `http://localhost:3000` (from inside Docker: `http://host.docker.internal:3000`) |
| Widget (the loader is `{widget}/loader.js`) | `https://ariadnebot.com/widget` | `http://localhost:5174` |

## The flow

```
WordPress admin                      Dashboard (browser)                         API
1 Connect button
  makes state + verifier,
  keeps them 10 min
2 redirect  ───────────────────────► /admin/connect/wordpress?site_url&return&state&challenge&woo&name
                                     3 signed out → log in or sign up, come back
                                     4 lookup: which of my sites has this host? ─► POST /v1/connect/wordpress/lookup
                                     5 "Connect" pressed
                                       (new site?)  ──────────────────────────► POST /v1/connect/wordpress/site
                                       approve      ──────────────────────────► POST /v1/sites/:id/connect/wordpress/approve
                                                                                 stores sha256(code) + challenge, 5 min
6 ◄─── browser goes to redirectTo: {return}?action=ariadne_connect_return&code&state
7 checks state, then server to server ────────────────────────────────────────► POST /v1/connect/wordpress/exchange {code, verifier}
8 saves site key and addresses, prints the widget                               one use; sha256(verifier) must equal challenge
```

What each check stops:

- **`state`** (made and checked by the plugin, tied to the WordPress user): nobody can make an admin's browser finish a Connect the admin did not start.
- **`challenge` / `verifier`** (PKCE style): the code travels in a browser address and can end in logs and history. Alone it is worthless; only the plugin that started the flow holds the verifier.
- **The return address must be on the same site as the WordPress address**, and the code is only ever sent there.
- **Approve never adds a domain to an Ariadne site**: the WordPress address must already be one of the site's allowed domains; otherwise the Connect page makes a new site for it.
- **A site made by Connect starts in preview mode**: only the owner's team sees the chat until the owner opens it to everyone.
- The exchange returns only values that are public anyway (the site key is in every page's source). Plugin v2 (product sync) will add a secret and must revisit every point here.

## Patterns

| Name | Pattern | Used for |
|---|---|---|
| `b64url43` | `^[A-Za-z0-9_-]{43}$` (32 bytes in base64url, no padding) | `code`, `challenge` |
| `state` | `^[A-Za-z0-9_-]{16,128}$` | the plugin's `state` (`wp_generate_password(32, false)`) |
| `verifier` | `^[A-Za-z0-9_-]{43,128}$` | the plugin's verifier (`wp_generate_password(64, false)`) |
| site key | `^pk_[A-Za-z0-9]{24}$` | `siteKey` in the exchange answer |

`challenge = base64url(sha256(verifier))` without `=` padding. In PHP: `rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')`.

## What the plugin sends to the Connect page

A redirect to `{APP_URL}/connect/wordpress`, each value through `rawurlencode`:

| Parameter | Value |
|---|---|
| `site_url` | `home_url()` |
| `return` | `admin_url('admin-post.php')` (no query: the API refuses one) |
| `state`, `challenge` | as above |
| `woo` | `1` when WooCommerce is active, else `0` |
| `name` | the site title, decoded, at most 80 UTF-16 units; left out when empty |

The API's approve route then checks the return address: same registrable domain as `site_url`; path ends in `/wp-admin/admin-post.php`; no user name, password, query or fragment; `https` unless `site_url` is `http`; no port, except `localhost` outside production (the local WordPress on `http://localhost:8080`).

## The return

- `{return}?action=ariadne_connect_return&code=…&state=…`, hooked on `admin_post_ariadne_connect_return` only. The plugin checks `manage_options`, deletes the user's pending state first (single use), checks the code's pattern and the state with `hash_equals`.
- **Cancel** on the Connect page goes back to `return` with no code (bare `admin-post.php`, or the return action without `code`). The plugin shows "Connecting was cancelled" and drops the pending state.

## The exchange

`POST {API}/v1/connect/wordpress/exchange`, server to server (`wp_remote_post`, 15-second timeout, no redirects), JSON body `{ "code": b64url43, "verifier": verifier }`. Strict: an unknown field is refused.

Answer 200, with `Cache-Control: no-store`:

```json
{
  "siteKey": "pk_…",
  "apiUrl": "https://api.ariadnebot.com",
  "widgetUrl": "https://ariadnebot.com/widget",
  "siteName": "My shop",
  "dashboardUrl": "https://ariadnebot.com/admin/s/<siteId>",
  "previewMode": true
}
```

- `previewMode` is the site's setting now; the plugin shows it in its notice and does not save it.
- Errors: every failure of the code (unknown, used, expired, wrong verifier, site deleted) is the same `400 invalid_code`; a body that fails the patterns is `400 validation_error`; `429 rate_limited` past 20 a minute per address. The plugin treats **every** non-200 the same: «Η σύνδεση δεν ολοκληρώθηκε. Δοκίμασε ξανά.» / "Connecting didn't finish. Please try again."
- One try per code: a wrong verifier still uses the code up, so the owner starts again.
- Codes live 5 minutes.

## What the plugin saves

`ariadne_site_key`, `ariadne_api_url`, `ariadne_widget_url`, `ariadne_site_name`, `ariadne_dashboard_url` (plus `ariadne_source` and the switch `ariadne_enabled`), only after: the site key matches its pattern, and each URL is `https` with its host in the allowed hosts (`http` only for `localhost`, which only the local filter adds).

## The widget tag

It must carry the same attributes as the dashboard's snippet, so the install check finds it:

```html
<script src="https://ariadnebot.com/widget/loader.js" data-site-key="pk_…" data-api="https://api.ariadnebot.com" async></script>
```

The install check looks, inside one `<script …>` opening tag, for `loader.js` and `data-site-key` (any order, any case), plus the site key anywhere in the page. WordPress prints, through `script_loader_tag`:

```html
<script id="ariadne-loader-js" async data-site-key="pk_…" data-api="https://api.ariadnebot.com" data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-pagespeed-no-defer nowprocket src="https://ariadnebot.com/widget/loader.js"></script>
```

The extra attributes keep speed plugins from inlining, combining, deferring or delaying the tag: the loader finds its own tag through `document.currentScript`. Even WP Rocket's delayed form (`type="rocketlazyloadscript" data-rocket-src="…/loader.js"`) is still found by the install check.
