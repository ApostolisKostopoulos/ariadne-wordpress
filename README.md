# Ariadne AI Assistant

The WordPress plugin of [Ariadne](https://ariadnebot.com), an AI assistant for websites and online shops. It adds the Ariadne chat to a WordPress or WooCommerce site and connects the site to an Ariadne account with one click, with no code to copy.

## Getting the plugin

Shop owners download the plugin from **https://ariadnebot.com/downloads/ariadne-ai-assistant.zip**, and find the steps at **https://ariadnebot.com/help/wordpress** (in Greek: https://ariadnebot.com/el/help/wordpress). Please do not send shop owners to this repository: it holds the source code, and its release files are what our own site serves.

## What is in this repository

- `ariadne-ai-assistant/`: the plugin itself, the folder that goes into the zip.
- `docs/connect.md`: how Connect works, from the plugin's side.
- The plugin's checks and its release workflow (GitHub Actions), a local WordPress in Docker, and the wordpress.org icon and banner.

The connection between the plugin and Ariadne (the Connect flow) follows a written contract kept with the Ariadne service; the parts the plugin needs are copied into [docs/connect.md](docs/connect.md).

## Try Connect on your computer

You need Docker Desktop and a local copy of the Ariadne service (its API on port 3000, its dashboard on 5173 and its widget on 5174).

1. **Start Ariadne locally**, with the dashboard talking to the real local API: in the Ariadne repository, `docker compose up -d`, then `pnpm dev` with `VITE_API_MODE=real` set for the dashboard.
2. **Start WordPress**: in this repository, `docker compose up -d`. WordPress answers on http://localhost:8080.
3. **Install WordPress and turn the plugin on.** Either open http://localhost:8080 and follow the installer, then Plugins › Activate "Ariadne AI Assistant"; or copy `.env.example` to `.env`, pick your own local values, and run:

   ```bash
   set -a; . ./.env; set +a
   docker compose run --rm cli wp core install --url=http://localhost:8080 --title="Test shop" --admin_user="$WP_ADMIN_USER" --admin_password="$WP_ADMIN_PASSWORD" --admin_email="$WP_ADMIN_EMAIL" --skip-email
   docker compose run --rm cli wp plugin activate ariadne-ai-assistant
   ```

4. **Nothing to configure**: `dev/mu-plugins/ariadne-local.php` (mounted by `docker-compose.yml`, never in the zip) sets the three filters: `ariadne_app_url` to `http://localhost:5173/admin`, `ariadne_api_url` to the API on port 3000, and `ariadne_allowed_hosts` to `localhost`. The API address there is `http://host.docker.internal:3000` rather than `http://localhost:3000`, because the exchange is a call from PHP inside the container, where `localhost` is the container itself.
5. **Connect**: in WordPress go to Settings › Ariadne and press "Connect to Ariadne". Log in or sign up on the local dashboard, press "Connect", and you are back in WordPress. The front end now carries the loader (`http://localhost:5174/loader.js`), and the dashboard's Install page finds it. The API accepts `http://localhost:8080/wp-admin/admin-post.php` as a return address only outside production.

`docker compose down` stops it; `docker compose down -v` also deletes the local site.

## Building

- `bin/build-mo.sh` builds the `.mo` files from the `.po` files with `msgfmt` (GNU gettext; Git Bash on Windows has it).
- `bin/build-zip.sh` builds `dist/ariadne-ai-assistant.zip` with one top folder, `ariadne-ai-assistant/`.
- After changing translatable strings, regenerate the template with WP-CLI and add the Greek to `languages/ariadne-ai-assistant-el.po`:

  ```bash
  docker run --rm -v "$PWD/ariadne-ai-assistant:/app" wordpress:cli-php8.3 wp i18n make-pot /app /app/languages/ariadne-ai-assistant.pot --slug=ariadne-ai-assistant --allow-root
  ```

## Releasing

1. Bump the version in the plugin header, `ARIADNE_VERSION` and the readme's `Stable tag`, and add a changelog entry.
2. Push the tag, for example `git tag v1.0.1 && git push origin v1.0.1`.
3. The release workflow builds the zip, attaches it to the GitHub Release, and calls the Cloudflare Pages deploy hook (repository secret `PAGES_DEPLOY_HOOK`): the site build downloads the latest release's zip and serves it at the address above.
4. Once the plugin is approved on wordpress.org and the secrets `SVN_USERNAME` and `SVN_PASSWORD` are added, the same workflow also publishes the release there, with the icon and banner from `.wordpress-org/`.

### Note for the wordpress.org submission

The readme has no `Contributors` line yet: add the wordpress.org username that will own the listing.

## Licence

GPLv2 or later, see [LICENSE](LICENSE).
