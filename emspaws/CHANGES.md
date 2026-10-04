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

### Buyers can edit selected answers on their order page (2026-10-04, `b24cb22e`)
- New question switch "Buyers can edit this answer on their order page" (column
  `questions.is_buyer_editable`, default off; disabled while "Hide this question" is on, hidden
  questions are never shown to buyers). Copied when an event is duplicated.
- The buyer's order page (link "manage your order" in the confirmation email) shows a "Questions"
  section with only these questions: order questions once, ticket questions per attendee,
  product questions per product. Current answer (or "—"), edit icon, same inputs as checkout, in
  the buyer's language (translated titles, option labels and ticket names).
- Rules: only completed orders and orders awaiting offline payment; required questions cannot be
  cleared; answers are validated like at checkout; a buyer can only answer slots of their own
  order; rate limit `self-service-edit` (as upstream's self-service edits). An answer row is
  created on first save, so this also works for optional questions skipped at checkout.
- API: `GET`/`PUT /public/events/{event_id}/order/{order_short_id}/question-answers`.
- Files: `Services/Domain/Question/BuyerEditableQuestionAnswerService.php`,
  `Handlers/Question/{Get,Save}BuyerEditableAnswer*`, `Http/Actions/Questions/*BuyerEditable*`,
  migration `2026_10_04_000002_add_is_buyer_editable_to_questions_table.php`; frontend
  `QuestionForm`, `OrderSummaryAndProducts/BuyerQuestionAnswers.tsx`, `self-service.client.ts`.
- Tests: `tests/Unit/Services/Domain/Question/BuyerEditableQuestionAnswerServiceTest.php`.

### Questions added later appear on existing registrations (2026-10-04, `61679c36`)
- Saving a registration question (create or edit) syncs it to existing registrations: every
  completed order or order awaiting offline payment gets the question without an answer. Order
  questions are added per order; ticket questions per attendee (cancelled attendees skipped);
  questions on non-ticket products once per order that contains the product.
- The organizer fills the answers in the order or attendee overview (existing edit icon). Buyers
  do not see or edit these questions; webhook payloads contain them with an empty answer.
- Changing the products of a question or deleting it removes only the empty rows that no longer
  apply; given answers are never touched. A question can be deleted as long as nobody has
  answered it (upstream blocked deletion as soon as any answer row existed).
- Questions created before this change are synced the next time they are saved. Orders placed
  later that skip an optional question also get it when the question is saved again.
- Data: empty answers are rows in `question_answers` with `answer = NULL` (hard-deleted when
  obsolete, because the `question_and_answer_views` view does not filter soft-deleted rows).
- Files: `Services/Domain/Question/QuestionAnswerSyncService.php`, `Create/Edit/DeleteQuestionHandler`,
  `QuestionAnswerRepository::forceDeleteUnansweredForQuestion`, nullable answers in
  `QuestionAndAnswerViewDomainObject` and `QuestionAnswerFormatter`, empty-answer defaults in
  `frontend/src/components/common/QuestionAndAnswerList`.
- Tests: `tests/Unit/Services/Domain/Question/QuestionAnswerSyncServiceTest.php`.

### Language switcher works for logged-in users (2026-10-04, `24e07471`)
- Bug: logged-in organizers who used the public language switcher were switched back to their
  profile language after the reload, because the profile language was enforced on every page load;
  server-side rendering also let the profile language win over the chosen language.
- Now: the profile language is applied once per browser (at login or when it changes in the
  profile; remembered in localStorage `applied_profile_locale` as `userId:locale`). Afterwards the
  switcher choice is kept. Server-side rendering forwards the choice as the `locale` cookie, so the
  backend uses the same order as in the browser: chosen language → profile → browser language.
- Files: `frontend/src/StartupChecks.tsx`, `frontend/src/utilites/apiClient.ts`

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
- Empty event/checkout fields shown on 2026-10-04: the "Event Details" section always lists event
  title and description and the checkout texts (pre/post checkout message, button texts, offline
  payment instructions, connection details). Fields without an original show a note with a link
  to Event Settings; unset button texts explain that visitors see the default text in their own
  language. Before, empty fields were hidden, which looked like they were not translatable.
- Layout tightened on 2026-10-04: the Translations page removes upstream's global input and card
  bottom margins (`.compactInputs`, `.page` in `Translations.module.scss`) and uses smaller gaps.
- Fallback choice fixed on 2026-10-04 (`9a746869`, `fefdff4a`): the fallback list offers
  every language (before: only languages already under "Translate into", so English was missing).
  The language of the texts is listed as "<language> (original texts)" and is the default; it is
  stored as "no fallback". Choosing another language adds it to "Translate into"; removing it
  there resets the fallback to the original texts. New events default to "Translate into:
  English, fallback: English" unless the texts are English.

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
