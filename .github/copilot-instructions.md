<!-- Short, focused Copilot / AI agent instructions for working in this repo -->
# docker4drupal — quick agent notes

This file contains short, actionable guidance for AI coding agents (Copilot-style) to be immediately productive in this repository. Keep output concise, reference files, and avoid making assumptions about running Docker on the user's machine.

1) Big picture
- This repository provides a Docker-based local Drupal stack (wodby/docker4drupal). The main orchestration is Docker Compose (`compose.yml`) with helper Makefiles (`docker.mk`, `Makefile`). The webroot is `web/` (Drupal core), PHP entrypoint is `web/index.php`.
- Key components: `php`, `nginx`, `mariadb` (default), `traefik` entry in `compose.yml` + `traefik.yml`. Tests live under `tests/` (per-drupal-version subfolders and `tests/cms`).

2) Where to read code and configs
- Local orchestration and service definitions: `compose.yml` and `traefik.yml`.
- Developer commands and helpers: `docker.mk`, `Makefile` (targets: `up`, `down`, `shell`, `composer`, `drush`, `logs`, `prune`, `test`, `test-cms`). Use these targets instead of inventing custom docker-compose invocations.
- Drupal app entry: `web/index.php`, Drupal root: `web/`.
- Tests and CI: `tests/` and GitHub Actions workflow at `.github/workflows/workflow.yml` (runs `make test` / `make test-cms`).

3) Developer workflows (exact commands)
- Start the local stack: make up (this runs `docker compose pull` then `docker compose up -d`). See `docker.mk::up`.
- Stop: make down (aliases to stop). Pause/start: make stop / make start.
- Open a shell into the PHP container: make shell
- Run drush inside container: make drush "<drush command>" (default drupal root `/var/www/html/web`). Example: make drush "status"
- Run composer: make composer "install" (executes composer in container using COMPOSER_ROOT `/var/www/html`).
- Run tests (local): make test (for `DRUPAL_VER`, `PHP_VER` overrides) and make test-cms for CMS tests.

4) Project conventions & patterns
- Bind-mount code: `compose.yml` mounts the repo root into `/var/www/html`, so prefer editing files locally and using container commands (drush/composer/tests) rather than rebuilding images.
- Xdebug/xhprof: PHP image has toggles controlled by envvars in `compose.yml`. Look for `PHP_XDEBUG_*` and `PHP_EXTENSIONS_DISABLE` in `compose.yml` when adding profiling/debugging.
- Database: `mariadb` is bound to `mariadb/` for persistent data. Do not assume an ephemeral DB when writing destructive migrations or tests.
- Traefik labels: several services include Traefik labels. When adding services that expose ports, mirror the label pattern used in `compose.yml`.

5) Integration points & external deps
- The stack relies on external wodby images (see `README.md` for image mapping). Changes that depend on different images must update tags via `.env` (loaded in `docker.mk`) or the compose file.
- CI uses `make test` / `make test-cms` in `.github/workflows/workflow.yml`. Keep tests fast and avoid relying on host-local tooling (CI runs inside GitHub Runners).

6) When editing code, prefer these files for common tasks
- To change container helpers: edit `docker.mk` (Make targets) and/or `compose.yml` (service definitions).
- To change local dev scripts: `Makefile` (shortcuts for tests per Drupal version) and `tests/*/run.sh` (test runners).
- To add Drupal hooks or module changes: modify code in `web/modules/` or `web/themes/` and use `make drush` to clear caches (`drush cr`) and run updates.

7) Examples you can use in PR descriptions
- "Start stack locally: `make up`; run database migrations: `make drush \"updatedb -y\"`; clear caches: `make drush \"cr\"`." 
- "Run tests for Drupal 11 and PHP 8.4 locally: `DRUPAL_VER=11 PHP_VER=8.4 make test`."

8) Constraints for AI-generated changes
- Do not modify `vendor/` files. Prefer changes in `web/`, `compose.yml`, `docker.mk`, `tests/`, or `.github/` only.
- Avoid changing `.env` or secrets; surface recommended ENV changes in PR text instead of embedding them.
- When suggesting new container images or altering service ports, mention impact on Traefik labels and `.github/workflows/workflow.yml` CI matrix.

9) Quick pointers for common tasks
- Rebuild or update images: Edit tags in `.env` and run `make up`.
- Inspect running services: `make ps` and `make logs php nginx`.
- Run one-off commands inside PHP container: `make drush "sql:query 'SELECT 1'"` or `make composer "require vendor/package"`.

10) If unclear, ask the maintainer for:
- Expected `.env` values used locally (PROJECT_NAME, PROJECT_BASE_URL, PROJECT_PORT, DB_*) if not present.
- Whether changes should support older Drupal versions — CI tests Drupal 10 and 11 per matrix.

----
If you'd like, I can iterate on tone, add more examples (e.g., exact `tests/*/run.sh` behavior), or incorporate any team-specific instructions you want preserved.
