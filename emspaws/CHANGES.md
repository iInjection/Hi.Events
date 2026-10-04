# Emspaws changes to hi.events

Running list of everything this fork changes compared to upstream
[HiEventsDev/Hi.Events](https://github.com/HiEventsDev/Hi.Events). Branch: `emspaws`.
Newest entries first. After every upstream merge, re-check each entry under "Changes".

## Base

- Upstream release: **v2.0.0-rc.2** (2026-10-01)
- Remotes: `origin` = github.com/iInjection/Hi.Events (public fork, required by AGPL),
  `upstream` = HiEventsDev/Hi.Events (push disabled)
- Keep: the "Powered by Hi.Events" link must stay visible (licence). Do not modify `backend/ee/`
  or `frontend/src/ee/` (separate enterprise licence).
- Upstream's `CLAUDE.md` applies: no explanatory code comments, translations for all languages,
  unit tests for backend features, no AI attribution in commit messages.

## Changes

### Profile language is applied (2026-10-03, `8aba7436`)
- Problem (upstream bug): the language chosen under "Manage Profile" was never used. Startup
  skipped it because `getClientLocale()` always returns a value (browser fallback), and saving
  the profile never reloaded because the form was reset before checking for a change.
- Now: for logged-in users the profile language sets the `locale` cookie; the page reloads once
  if the language changed. Saving the profile switches immediately.
- Files: `frontend/src/StartupChecks.tsx`, `frontend/src/locales.ts` (`setLocaleCookie`),
  `ManageProfile`, `LanguageSwitcher`
- Test: English browser + profile German → German after login, no repeated reloads.
- Not sent upstream (decision: no PR for now).

### Add-on quantity limit per ticket (2026-10-03, `82e6e52e`)
- Add-on-only products get "Limit to the number of selected tickets" + "Maximum per ticket"
  (Add-ons section of the product form).
- Limit = selected tickets that offer the add-on × maximum per ticket (all ticket types count;
  per date for recurring events).
- Widget: stepper stops at the limit, hint "Up to N per ticket", message "Maximum N for the
  selected tickets"; lowering tickets lowers the add-on quantity automatically.
- Server: order validation rejects too many add-ons (German message in `backend/lang/de.json`).
- Only for add-on-only products, so standalone sales are never limited.
- Data: new column `products.addon_max_per_parent` (nullable = no limit), migration
  `2026_10_03_000001_add_addon_max_per_parent_to_products_table.php`.
- Files: `OrderCreateRequestValidationService` (+ 3 unit tests), product request/DTO/handlers/
  resources, `ProductForm`, `SelectProducts`, `Prices/Tiered`, `types.ts`, all `.po` locales.

### Promo code field above "Continue" (2026-10-03, `20f901d1`)
- The promo code input is always visible, labelled "Have a promo code?", directly above the
  Continue button (upstream: muted link below the button).
- If every ticket is hidden behind a code, the field is still shown on its own.
- Files: `SelectProducts/index.tsx`, `styles/widget/default.scss`, `e2e/pages/checkout.page.ts`
- No new translations needed (existing strings reused).

### Dev stack runs on Linux/WSL (2026-10-03, `a66cecbf`)
- Backend dev image maps `www-data` to the host UID/GID (`HOST_UID`/`HOST_GID`, default 1000),
  otherwise Composer cannot write to the mounted source.
- MinIO moved behind the optional `s3` compose profile (image `minio/minio` no longer published);
  local dev stores uploads on the local disk.
- Files: `backend/Dockerfile.dev`, `docker/development/docker-compose.dev.yml`

## Local development

- Code: Debian (WSL) `~/emspaws-registration-app`, open in VS Code via "WSL: Connect to WSL".
- Start: `cd docker/development && ./start-dev.sh`, answers n, n, y, y.
- App https://localhost:8443, mails (Mailpit) http://localhost:8025.
- `backend/.env` (not in git): local disk storage, Redis queue, sender `hello@emspaws.de`.
- Test data: `php artisan dev:bootstrap` creates a login + events. The bootstrap event has an
  add-on "Dog bowl" on the free ticket.

## Open topics

- **Multilingual organizer content** (plan proposed 2026-10-03, waiting for decisions):
  maintained translations with fallback + DeepL pre-fill; event language and fallback language;
  public language switcher; central "Translations" page per event; separate translations table.
  Open: approach, languages (de / en fallback / nl?), scope of the first phase.
- **Production (VPS, next season):** separate VPS (2 GB+), image built by GitHub Actions → GHCR,
  Caddy for `tickets.emspaws.de`, PostgreSQL + Redis, restic backups to Nextcloud, SMTP via
  Infomaniak (`hello@emspaws.de`), Stripe (own keys, no Connect) + offline payments.
  Before go-live: merge the latest stable upstream release.
- **attendee-widget:** switch the hi.events webhook to the self-hosted instance at go-live.
