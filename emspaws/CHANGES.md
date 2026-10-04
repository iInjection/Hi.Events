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

### Multilingual event content, phases 1 + 2 (2026-10-04, `592950f9`)
- New event menu entry **Translations** (Setup & Design): per event choose the language of the
  texts, the languages to translate into and a fallback language; then translate per language.
  Shows the original above each field, progress per language and "Original changed" when the
  original text was edited after translating.
- Translatable: event title/description; checkout texts (pre/post checkout message, continue and
  get-tickets button texts, offline payment instructions, online connection details, product
  page message); categories; tickets/products (title, description, highlight message); price tier
  labels; registration questions (title, description, answer options).
- Visitor sees: own language → fallback language → original. The source language always shows
  the original. Organizer list pages and order pages are translated too; order item names are
  rebuilt from translated product titles.
- Question answers are stored with the original option values (translated labels are only
  displayed), so reports and exports stay in the event language.
- Event URLs keep the slug of the original title (otherwise every language had its own URL).
- Language switcher on the public event page (footer) and below the checkout.
- Server-side rendering forwards the visitor language to the API (`setRequestLocale`).
- New orders store the language the visitor actually used (cookie/switcher), not the browser
  default, so emails can follow it (phase 3).
- Data: tables `event_translation_settings` and `content_translations` (migration
  `2026_10_04_000001_create_content_translations_tables.php`); upstream tables unchanged.
- Main files: `backend/app/Services/Domain/ContentTranslation/`, `.../Handlers/ContentTranslation/`,
  `Http/Actions/ContentTranslations/`, hooks in `GetEventPublicAction`, `GetQuestionsPublicAction`,
  `GetOrganizerEventsPublicAction`, `GetOrderPublicHandler`; frontend
  `components/routes/event/Translations/`, `CheckoutQuestion`, `EventHomepage`/`Checkout` layouts.
- Tests: `tests/Unit/Services/Domain/ContentTranslation/ContentTranslationServiceTest.php`.
- Limits: duplicating an event does not copy its translations; emails, PDF tickets, SEO texts and
  email templates are not translated yet (phase 3).

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
  add-on "Dog bowl" on the free ticket, a radio question "Dog size", and translations (original
  English, German and Dutch translations, fallback German).

## Open topics

- **Multilingual content, phase 3:** translate confirmation emails, PDF tickets, SEO title/
  description per language and email templates (orders already store the visitor language).
- **Multilingual content, phase 4:** "Translate with DeepL" button that pre-fills empty fields
  (needs a DeepL API key), plus copying translations when an event is duplicated.
- **Upstream SSR quirks seen while testing (not caused by us):** event dates are rendered in
  English on the server and re-rendered in the visitor language in the browser; the "Powered by"
  link URL differs between server and browser. Both cause harmless hydration warnings.
- **Production (VPS, next season):** separate VPS (2 GB+), image built by GitHub Actions → GHCR,
  Caddy for `tickets.emspaws.de`, PostgreSQL + Redis, restic backups to Nextcloud, SMTP via
  Infomaniak (`hello@emspaws.de`), Stripe (own keys, no Connect) + offline payments.
  Before go-live: merge the latest stable upstream release.
- **attendee-widget:** switch the hi.events webhook to the self-hosted instance at go-live.
