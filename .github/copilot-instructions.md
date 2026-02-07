# Copilot / AI Agent Instructions — FokusLog

Short, actionable guidance to make an AI coding agent immediately productive in this repo.

1) Big picture
- Frontend: a static Progressive Web App under [app](app) (HTML/CSS/vanilla JS). See [app/manifest.json](app/manifest.json#L1) and [app/service-worker.js](app/service-worker.js#L1) for PWA/offline behavior.
- Backend: single-file PHP REST API at [api/index.php](api/index.php#L1). It uses PHP sessions for auth, PDO for MySQL, and returns JSON for all endpoints.
- Data model: families -> users (roles like `parent`), medications, entries and audit_log. Routes live in `api/index.php` (e.g. `/register`, `/login`, `/entries`, `/medications`).
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
- Minimalist, explicit code: prefer small, readable functions over complex frameworks. Follow existing style in `api/index.php` (explicit `respond()`, `getJsonBody()`, `requireAuth()` helpers).
- Authentication: session-based (PHP sessions). Tests rely on cookie/session behavior in `ApiTest.php` (the test client stores cookies). When changing auth, update tests accordingly.
- Error handling: API always returns JSON and installs a shutdown handler for fatal errors — keep responses JSON and use `respond(code, data)`.
- DB access: use prepared statements via PDO. Avoid ORM rewrites; maintain row-level SQL in `api/index.php`.

4) Integration points and external deps
- Vendor JS libs are under `vendor/` (Chart.js, jsPDF). Frontend expects these files to be present; do not replace them with CDN calls without reviewing privacy implications.
- OpenAPI spec: [docs/openapi.yaml](docs/openapi.yaml#L1) describes the API surface — keep it updated when adding/removing endpoints.

5) Example PR tasks an agent can perform (with entry points)
- Add a new API endpoint: update [api/index.php](api/index.php#L1), follow routing pattern and use `respond()` and `logAction()`.
- Add offline assets or change caching: edit [app/service-worker.js](app/service-worker.js#L1) and `manifest.json`.
- Update API contract: edit [docs/openapi.yaml](docs/openapi.yaml#L1) and run `api/run_tests.php` to validate behavior.

6) Safety & policy notes for contributors
- This project prioritizes privacy and non-commercial licensing (see [README.md](README.md#L1) and `LICENSE.md`). Avoid introducing third-party analytics or hosted services.
- Sensitive domain: UX and copy affect children and families. Keep wording and flows conservative and reviewed by humans.

7) Helpful files to inspect when working on features or bugs
- App entry and UI: [app/index.html](app/index.html#L1)
- PWA hooks: [app/service-worker.js](app/service-worker.js#L1), [app/manifest.json](app/manifest.json#L1)
- API server: [api/index.php](api/index.php#L1)
- API tests: [api/ApiTest.php](api/ApiTest.php#L1), [api/run_tests.php](api/run_tests.php#L1)
- DB schema and migrations: [db/schema.sql](db/schema.sql#L1), [scripts/update_schema.sql](scripts/update_schema.sql#L1)
- Documentation and legal: [docs](docs)

If any area is unclear or you want the file to include more details (e.g., exact test output interpretation or CI/deploy steps), tell me which section to expand.
