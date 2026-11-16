# CLAUDE.md

The WordPress plugin "Ariadne AI Assistant for WordPress" (slug and text domain `ariadne-ai-assistant`). It adds the Ariadne chat to a WordPress or WooCommerce site and links the site to an Ariadne account with one click (Connect). Public repository, GPLv2 or later. Plain PHP, no Composer, no build step except the `.mo` files and the zip. WordPress 6.0+, PHP 7.4+.

Ariadne itself (API, dashboard, widget, site) lives in a separate private repository. This repository holds only the plugin, its checks and its release. Nothing private ever comes here: no `.env` values, no internal docs, no addresses other than the public ones below.

## Map

| Path | What it is |
|---|---|
| `ariadne-ai-assistant/` | The plugin, exactly the folder that goes into the zip |
| `ariadne-ai-assistant/ariadne-ai-assistant.php` | Header (version 1.0.0), the address constants and their filters, `require`s, activation and deactivation |
| `ariadne-ai-assistant/includes/class-ariadne-options.php` | Option names, the site key and URL checks, the one-time notices |
| `ariadne-ai-assistant/includes/class-ariadne-connect.php` | Connect: start (form POST), the return, the server-to-server exchange, Cancel |
| `ariadne-ai-assistant/includes/class-ariadne-settings.php` | Settings › Ariadne: status, Connect, the on/off switch, Disconnect, a site key field |
| `ariadne-ai-assistant/includes/class-ariadne-widget.php` | The loader tag on the front end (`script_loader_tag`) |
| `ariadne-ai-assistant/uninstall.php` | Deletes every `ariadne_*` option and transient |
| `ariadne-ai-assistant/readme.txt` | The wordpress.org readme, with the required "External services" section |
| `ariadne-ai-assistant/languages/` | `.pot`, Greek `-el.po`; the `.mo` is built, never committed |
| `docs/connect.md` | The parts of the Connect contract the plugin needs |
| `.wordpress-org/` | wordpress.org icon and banner PNGs (not in the zip); sources in `assets-src/`, rendered by `bin/render-assets.mjs` |
| `bin/build-mo.sh`, `bin/build-zip.sh` | The `.mo` files (`msgfmt`) and `dist/ariadne-ai-assistant.zip` |
| `docker-compose.yml`, `dev/mu-plugins/` | Local WordPress on `http://localhost:8080`, pointed at a local Ariadne |
| `.github/workflows/ci.yml` | `php -l` on PHP 7.4 and 8.3, Plugin Check, the zip build |
| `.github/workflows/release.yml` | On a `v*` tag: the zip on the GitHub Release, the site rebuild, wordpress.org once its secrets exist |

## Addresses

Production: dashboard `https://ariadnebot.com/admin` (`ARIADNE_APP_URL`, filter `ariadne_app_url`), API `https://api.ariadnebot.com` (`ARIADNE_API_URL`, `ariadne_api_url`), widget `https://ariadnebot.com/widget` (`ARIADNE_WIDGET_URL`, `ariadne_widget_url`, only for a site key pasted by hand), allowed hosts `ariadnebot.com`, `api.ariadnebot.com` (`ARIADNE_ALLOWED_HOSTS`, `ariadne_allowed_hosts`). The filters exist for local testing; `dev/mu-plugins/ariadne-local.php` sets them.

Owners download the plugin only from `https://ariadnebot.com/downloads/ariadne-ai-assistant.zip`. Nothing the plugin shows, and nothing in its readme, links to GitHub.

## WordPress security rules

- A nonce and a capability check (`manage_options`) on every form and admin-post action. The Connect return is the one exception: it arrives from our site with no nonce, and `state` does that job (single use, `hash_equals`, tied to the user).
- `esc_html`, `esc_attr` and `esc_url` on every output.
- `sanitize_text_field` and `esc_url_raw` on every input, `wp_unslash` first.
- No `eval`, no remote code, no tracking, no admin notices on other screens (notices print inside our own page only).
- The external service is described in `readme.txt` ("External services"); update it when what the plugin sends changes.
- `ABSPATH` guard at the top of every PHP file.
- Prefixed function, class, option, transient and hook names (`ariadne_`, `Ariadne_`).
- The Connect return is hooked on `admin_post_ariadne_connect_return` only, never `admin_post_nopriv_`.
- Nothing from the exchange is saved before it passes its check: the site key `^pk_[A-Za-z0-9]{24}$`, every URL https with its host in the allowed hosts (http only for `localhost`, which only a local filter adds).

## Rules

- **The contract.** `docs/connect.md` copies what the plugin needs from the contract in the private repository (`docs/plan/connect-wordpress.md`). A change to Connect changes both, in the same round.
- **Greek.** Every string has a Greek translation in `languages/ariadne-ai-assistant-el.po`, written natively for a Greek shop owner: εσύ, no «—», no «·», and Ariadne is feminine («με την Ariadne», «τον πίνακα της Ariadne»). After changing strings: regenerate the `.pot` (`wp i18n make-pot`, see the README), add the Greek, check with `msgcmp languages/ariadne-ai-assistant-el.po languages/ariadne-ai-assistant.pot`.
- **Free tools only.** No paid libraries, fonts, icons or services. Check the licence of every GitHub Action and pin third-party ones to a commit.
- **No secrets in logs.** `PAGES_DEPLOY_HOOK` is an address that starts a site build: never print it, never `curl -v`. The wordpress.org SVN secrets are the owner's; Claude never handles them.
- **A release** bumps the version in three places (header `Version`, `ARIADNE_VERSION`, readme `Stable tag`; `release.yml` refuses a tag that does not match all three), adds a changelog entry, then pushes the tag `vX.Y.Z`.

## Checks

- `php -l`: `docker run --rm -v "$PWD:/app" -w /app php:7.4-cli sh -c 'find . -path ./.git -prune -o -name "*.php" -print0 | xargs -0 -n1 php -l'` (and `php:8.3-cli`).
- Plugin Check on the local WordPress: `docker compose run --rm cli wp plugin install plugin-check --activate`, then `docker compose run --rm cli wp plugin check ariadne-ai-assistant`. One known warning: "WordPress" in the plugin name (see the README).
- The zip: `bin/build-zip.sh`.
