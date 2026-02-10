# Copilot / AI Agent Instructions — FokusLog

Short, actionable guidance to make an AI coding agent immediately productive in this repo.

1) Big picture
- Frontend: a static Progressive Web App under [app](app) (HTML/CSS/vanilla JS). See [app/manifest.json](app/manifest.json#L1) and [app/service-worker.js](app/service-worker.js#L1) for PWA/offline behavior.
- Backend: PHP REST API with **Controller-based architecture**:
  - Entry point: [api/index.php](api/index.php#L1) - Router configuration only
  - Router: [api/lib/Router.php](api/lib/Router.php#L1) - URL routing with `{param}` extraction
  - Controllers: [api/lib/Controller/](api/lib/Controller/) - One controller per domain
  - Base: [api/lib/Controller/BaseController.php](api/lib/Controller/BaseController.php#L1) - Shared methods (requireAuth, respond, logAction)
- Data model: families -> users (roles like `parent`), medications, entries and audit_log.
- Tests: lightweight HTTP API tests in [api/ApiTest.php](api/ApiTest.php#L1) driven by [api/run_tests.php](api/run_tests.php#L1) and `SimpleTestRunner.php`.

2) How to run and test locally (exact, reproducible steps)
- Start the PHP built-in server from the project root (where `app` and `api` live):
```bash
php -S localhost:8000
```
- In another shell run the API test runner (it uses `API_URL` env var or defaults):
```bash
# POSIX shells
API_URL=http://localhost:8000/api php api/run_tests.php

# Windows cmd
set API_URL=http://localhost:8000/api && php api/run_tests.php

# PowerShell
$env:API_URL='http://localhost:8000/api'; php api/run_tests.php
```
- Note: `api/index.php` will also read database credentials from `../.env` if present. Use that for safe local DB config. See [api/index.php](api/index.php#L1).

3) Project-specific conventions and patterns
- **Controller Architecture**: Each domain has its own controller extending `BaseController`. Routes are defined declaratively in `index.php` using the Router class.
- **Adding a new endpoint**: 
  1. Create or update a Controller in `api/lib/Controller/`
  2. Add route in `api/index.php` using `$router->get()` or `$router->post()`
  3. Use `$this->requireAuth()`, `$this->respond()`, `$this->logAction()` from BaseController
- Authentication: session-based (PHP sessions). Tests rely on cookie/session behavior in `ApiTest.php`.
- Error handling: API always returns JSON. Use `$this->respond(code, data)` in controllers.
- DB access: use prepared statements via `$this->pdo`. Controllers inherit PDO from BaseController.

4) Controller Reference
| Controller | Routes | Purpose |
|------------|--------|---------|
| HealthController | /health | CI/CD health check |
| AuthController | /register, /login, /logout, /me | Authentication |
| UsersController | /users, /users/{id} | User CRUD |
| MedicationsController | /medications, /medications/{id} | Medications |
| EntriesController | /entries, /entries/{id} | Diary entries + gamification |
| TagsController | /tags, /tags/{id} | Custom tags |
| BadgesController | /badges | Gamification badges |
| WeightController | /weight, /me/latest-weight | Weight tracking |
| GlossaryController | /glossary, /glossary/categories, /glossary/export, /glossary/import, /glossary/{slug} | Help lexicon with filters, export & import |
| **ReportController** | /report/trends, /report/compare, /report/summary, /report/export/excel | **Analytics & Exports** |
| **NotificationsController** | /notifications/settings, /notifications/push/*, /notifications/email/*, /notifications/status | **Push & Email Notifications** |
| **EventsController** | /events, /events/cleanup | **Server-Sent Events (SSE) for real-time updates** |
| AdminController | /admin/migrate, /admin/backup | Admin operations |

4a) Frontend Utilities (`FokusLog.utils`)
| Utility | Purpose |
|---------|---------|
| `utils.apiCall(endpoint, options)` | Fetch wrapper with error handling |
| `utils.toast(message, type, duration)` | Non-blocking notifications |
| `utils.log(...args)` / `utils.error(...)` | Debug-only logging |
| `utils.t(key, params)` | i18n translation lookup |
| `utils.poll(endpoint, interval, callback)` | Periodic API polling |
| `utils.lazyLoad(element, callback, options)` | Intersection Observer for lazy loading |
| `utils.subscribe(endpoint, handlers, options)` | SSE/EventSource wrapper with auto-reconnect |

5) Integration points and external deps
- Vendor JS libs are under `vendor/` (Chart.js, jsPDF). Frontend expects these files to be present; do not replace them with CDN calls without reviewing privacy implications.

6) Example PR tasks an agent can perform (with entry points)
- Add a new API endpoint: Create/update controller in [api/lib/Controller/](api/lib/Controller/), add route in [api/index.php](api/index.php#L1).
- Add analytics feature: Update [ReportController.php](api/lib/Controller/ReportController.php#L1) and [app/js/pages/report.js](app/js/pages/report.js#L1).
- Add offline assets or change caching: edit [app/service-worker.js](app/service-worker.js#L1) and `manifest.json`.

7) Safety & policy notes for contributors
- This project prioritizes privacy and non-commercial licensing (see [README.md](README.md#L1) and `LICENSE.md`). Avoid introducing third-party analytics or hosted services.
- Sensitive domain: UX and copy affect children and families. Keep wording and flows conservative and reviewed by humans.

8) Helpful files to inspect when working on features or bugs
- App entry and UI: [app/index.html](app/index.html#L1)
- PWA hooks: [app/service-worker.js](app/service-worker.js#L1), [app/manifest.json](app/manifest.json#L1)
- API entry: [api/index.php](api/index.php#L1)
- Router: [api/lib/Router.php](api/lib/Router.php#L1)
- Base Controller: [api/lib/Controller/BaseController.php](api/lib/Controller/BaseController.php#L1)
- Report Controller: [api/lib/Controller/ReportController.php](api/lib/Controller/ReportController.php#L1)
- Report Frontend: [app/js/pages/report.js](app/js/pages/report.js#L1)
- Notifications Controller: [api/lib/Controller/NotificationsController.php](api/lib/Controller/NotificationsController.php#L1)
- Notifications Frontend: [app/notifications.html](app/notifications.html#L1), [app/js/pages/notifications.js](app/js/pages/notifications.js#L1)
- Notification Worker (Cron): [scripts/notification-worker.php](scripts/notification-worker.php#L1)
- API tests: [api/ApiTest.php](api/ApiTest.php#L1), [api/run_tests.php](api/run_tests.php#L1)
- DB schema and migrations: [db/schema.sql](db/schema.sql#L1), [db/migrations/](db/migrations/)
- Help import script: [app/help/import_help.php](app/help/import_help.php#L1) - Scans HTML files, extracts structured content

If any area is unclear or you want the file to include more details (e.g., exact test output interpretation or CI/deploy steps), tell me which section to expand.
