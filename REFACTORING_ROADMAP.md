# FokusLog — Refactoring & Optimization Roadmap

**Datum:** Februar 2026
**Status:** P0/P1 abgeschlossen — Work in Progress (P2/P3 offen)
Dokumentation von Optimierungsmöglichkeiten, gruppiert nach Kategorien und Priorität.

---

## 📋 Kategorien & Prioritäten

- **P0 (Critical)**: Security, Stability, Major Performance issues
- **P1 (High)**: Code Quality, Maintainability, Common Pain Points
- **P2 (Medium)**: Nice-to-have optimizations, Developer Experience
- **P3 (Low)**: Refactoring für zukünftige Erweiterbarkeit, Tech Debt

---

## 🔒 SECURITY & STABILITY (P0)

### 1. **Backend: Extract Router to Separate File/Class**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: Medium (4–6h)
- **Impact**: High — easier testing, less giant file
- **Details**:
  - `api/index.php` aufgeteilt: `api/lib/Router.php` + `api/lib/Controller/` (Domain-Controller)
  - `BaseController` mit `requireAuth()`, `requireRole()`, `respond()`, `logAction()`, `getJsonBody()`
  - Separate Controller: `AuthController`, `EntriesController`, `MedicationsController`, `NotificationsController`, …
- **Related Issues**:
  - Große File ist schwer zu warten
  - Repetitives `requireAuth()` / `requireRole()` in jedem Handler

### 2. **Frontend: Remove/Suppress Console Logs in Production**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: Low (1–2h)
- **Impact**: Medium — Datenschutz, Performance (minor)
- **Details**:
  - ~30 `console.log()` / `console.error()` Aufrufe ersetzt durch `FokusLog.utils.log()` / `utils.error()`
  - Logging nur aktiv wenn `window.FOKUSLOG_DEBUG = true` gesetzt ist
  - Betroffene Dateien: `app.js`, `gamification.js`, alle Page-Module in `app/js/pages/`
  - Produktionssicher: Keine sensiblen Daten mehr in Browser-Console sichtbar
- **Related Issues**:
  - ~~Sensible Daten könnten geloggt werden~~ → Gelöst

### 3. **API: Centralized Input Validation & Sanitization**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: Medium–High (6–8h)
- **Impact**: High — Security, Consistency
- **Details**:
  - `api/lib/Validator.php` neu erstellt mit `ValidationException`
  - Methoden: `string`, `stringOptional`, `int`, `intOptional`, `enum`, `enumOptional`, `date`, `dateOptional`, `emailOptional`, `ratingOptional`
  - Eingesetzt in `AuthController` (Register, changePassword)
  - Weitere Controller (EntriesController, etc.) können sukzessive umgestellt werden
- **Related Issues**:
  - SQL-Injection wird durch PDO::PREPARE mitigiert, aber noch Input-Validation fehlt
  - Fehlende Fehlerbehandlung bei ungültigen Eingaben

### 4. **Database: Add Indexes & Query Optimization**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: Low (2–3h)
- **Impact**: Medium–High — Performance bei wachsenden Datamengen
- **Details**:
  - Composite Indexes in `db/schema_v3.sql` ergänzt:
    - `idx_entries_user_date (user_id, date DESC)` — Report-Queries
    - `idx_users_family (family_id)` — Family-scoped Queries
    - `idx_user_badges_user (user_id)` — Badge-Lookup
  - `me()` in AuthController von 5 auf 2 DB-Queries reduziert (korrelierte Subqueries)
- **Related Issues**:
  - Bei >10k entries können Reports langsam werden

### 4a. **Backend: Fix .env Parsing (EnvLoader)**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: Low (1–2h)
- **Impact**: Critical — verhinderte Log-Flooding & fehlende DB-Verbindung
- **Details**:
  - `parse_ini_file()` warf PHP-Warning für `!` in unquotierten Werten
  - Endlosschleife in `php_error.log` durch Fallback auf `.env-dev` ohne `DB_HOST`
  - Ersetzt durch `api/lib/EnvLoader.php` — unterstützt Sonderzeichen, Quotes, `export`-Syntax
- **Related Issues**:
  - Massiver `php_error.log`-Flood (tausende Zeilen per Request)

### 4b. **Backend: RateLimiter Race Condition Fix**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: Low (1h)
- **Impact**: High — verhindert inkorrekte Zähler unter Last
- **Details**:
  - `file_put_contents()` ohne Lock ersetzt durch atomares `fopen/flock(LOCK_EX)/rewind/ftruncate/fwrite`
  - Neues `reset(string $ip)` — Zähler nach erfolgreichem Login löschen
  - Rate Limiting auf `/register` (10/min) und `/changePassword` (5/min) ausgeweitet
- **Related Issues**:
  - Race Conditions bei parallelen Login-Requests möglich

### 4c. **Backend: NotificationsController — Explizite SQL-Queries**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: Low (1h)
- **Impact**: Medium — Architektur-Sicherheit, Wartbarkeit
- **Details**:
  - Dynamische Feldnamen-Interpolation durch explizite COALESCE-basierte UPDATE-Query ersetzt
  - INSERT mit vollständiger, fixer Spaltenliste
  - `NULL`-Parameter überschreiben keine bestehenden DB-Werte mehr
- **Related Issues**:
  - Dynamische Felder waren durch Whitelist abgesichert, aber fragil

### 4d. **Backend: User-Cache in BaseController**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: Low (0.5h)
- **Impact**: Medium — reduziert redundante DB-Queries pro Request
- **Details**:
  - `$cachedUser`-Property + überarbeitetes `currentUser()` mit Request-Scope-Cache
  - `clearUserCache()` für Invalidierung nach Profilupdate
  - Explizite Spaltenliste statt `SELECT *`; `is_active = 1`-Filter

### 4e. **Frontend: Error Boundaries & Modul-Timeout (app.js)**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: Low (1h)
- **Impact**: Medium — sichtbare Fehlermeldung statt stille Fehler
- **Details**:
  - `injectScript()` bricht nach 10 s ab (war: kein Timeout)
  - `showPageError()` zeigt DOM-Banner mit `role="alert"` bei Ladefehlern
  - Logout-Redirect passiert jetzt immer (Fehler nicht mehr unbeabsichtigt aufgefangen)

### 5. **Sessions: Implement Secure Session Storage (Optional Upgrade)**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: Medium (4–5h)
- **Impact**: Low–Medium — Enterprise Security
- **Details**:
  - **`api/lib/SessionHandler.php`** erstellt mit drei Backends:
    - `files`: PHP-Standard (default)
    - `redis`: Redis-Server (erfordert phpredis Extension)
    - `database`: MySQL-basierte Sessions für Horizontal Scaling
  - Konfiguration via `.env`:
    ```
    SESSION_HANDLER=redis
    SESSION_REDIS_HOST=127.0.0.1
    SESSION_REDIS_PORT=6379
    ```
  - Database-Migration in `db/migrations/008_realtime_events.sql` (sessions Tabelle)
  - Alle Session-Cookie-Parameter bleiben sicher (httponly, samesite=Strict)
- **Related Issues**:
  - ~~Sessions können bei Deployment auf mehreren Servern inkonsistent sein~~ → Gelöst

---

## 🎯 CODE QUALITY & MAINTAINABILITY (P1)

### 6. **API: Add Type Hints & Return Types**
- **Status**: ✅ Akzeptabel as-is (2026-02-10)
- **Effort**: Medium (4–6h)
- **Impact**: High — IDE Support, fewer bugs
- **Details**:
  - Derzeit: Keine Type Hints in PHP (außer `declare(strict_types=1)`)
  - Bsp. Vorher:
    ```php
    function handleEntriesPost(PDO $pdo): void { ... }
    ```
  - Bsp. Nachher:
    ```php
    function handleEntriesPost(PDO $pdo, array $requestData): Response { ... }
    ```
  - Vorteil: Static Analysis (PHPStan), bessere IDE-Completion
  - Tools: PHPStan Level 5+, Psalm, Visual Studio Code Extensions
- **Related Issues**:
  - Zu viele `$var['key']` ohne Type-Info; könnte zu Bugs führen

### 7. **API: Extract Error Handling to Central Middleware**
- **Status**: ✅ Akzeptabel as-is (2026-02-10) — try-catch per Methode ist konsistent
- **Effort**: Medium (4–5h)
- **Impact**: High — DRY, Consistency
- **Details**:
  - Derzeit: Jeder Handler hat eigenes `try-catch` + `app_log()` + `respond()`
  - Besser: Error-Handler Middleware
  - Bsp.:
    ```php
    try {
      handleEntriesPost($pdo);
    } catch (ValidationException $e) {
      respond(400, ['error' => $e->getMessage()]);
    } catch (Throwable $e) {
      app_log('ERROR', 'unhandled_exception', ...);
      respond(500, ['error' => 'Internal Server Error']);
    }
    ```
  - Vorteil: Konsistente Error-Antworten, leichter zu erweitern
- **Related Issues**:
  - Viel wiederholter Code in `try-catch` Blöcken

### 8. **Frontend: Modularize app.js into Separate Modules**
- **Status**: ✅ Done (bereits erledigt — 2026-02-10 verifiziert)
- **Effort**: High (8–12h)
- **Impact**: High — Maintainability, Testing
- **Details**:
  - `app.js` ist 186 Zeilen (Bootstrapper) + 12 Module in `app/js/pages/`
  - `FokusLog.utils` Namespace mit `apiCall()`, `toast()`, `log()`, `t()`, `poll()`
  - Ziel dieser Aufgabe wurde anders als erwartet bereits erreicht

### 9. **Frontend: Create API Client Wrapper Class**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: Low–Medium (3–4h)
- **Impact**: Medium — DRY, Error Handling
- **Details**:
  - Derzeit: Jeder Aufruf wiederholt `fetch()` + Error-Handling
  - Bsp.:
    ```js
    const response = await fetch('/api/entries', { method: 'GET' });
    if (!response.ok) {
      console.error('Fehler beim Laden');
      return;
    }
    const data = await response.json();
    ```
  - Besser: API-Klasse
    ```js
    const entries = await api.get('/entries');
    // Oder mit Error-Handling eingebaut
    try {
      const entries = await api.get('/entries');
    } catch (error) {
      ui.showError('Fehler beim Laden der Einträge');
    }
    ```
  - Vorteil: DRY, konsistentes Error-Handling, Timeout-Management
- **Related Issues**:
  - Viel Boilerplate-Code für HTTP-Requests

### 10. **Add JSDoc & PHP DocBlocks**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: Low (2–3h)
- **Impact**: Medium — IDE Support, Code Documentation
- **Details**:
  - **PHP-Controller**: Bereits vollständig dokumentiert mit DocBlocks (`BaseController`, `AuthController`, `EntriesController`, etc.)
  - **JS Utils** (`app.js`): JSDoc für `FokusLog.utils` Namespace:
    - `apiCall()`, `toast()`, `log()`, `error()`, `t()`, `poll()` mit @param, @returns, @example
  - **JS Page-Module**: JSDoc-Header in `entry.js`, `dashboard.js` mit @module, @description
  - IDE-Completion funktioniert jetzt konsistent
- **Related Issues**:
  - ~~Wenig dokumentiert~~ → Kernmodule dokumentiert

---

## ⚡ PERFORMANCE OPTIMIZATION (P1–P2)

### 11. **Frontend: Implement Lazy Loading for Images & Charts**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: Low–Medium (2–3h)
- **Impact**: Low–Medium — Page Load Performance
- **Details**:
  - **`utils.lazyLoad()`** Utility in `app.js` implementiert:
    - Intersection Observer API mit konfigurierbarem `rootMargin` und `threshold`
    - Fallback für ältere Browser (sofortige Ausführung)
    - Automatische Observer-Disconnection nach Callback
  - Verwendung:
    ```js
    utils.lazyLoad('#reportChart', (el) => initializeChart(el));
    ```
  - Bilder: Keine relevanten Bilder im App-Bereich vorhanden
  - Charts: Report-Charts werden sofort geladen (Hauptinhalt der Seite)
- **Related Issues**:
  - ~~Reports können auf langsamen Verbindungen zögerlich sein~~ → Utility verfügbar

### 12. **Frontend: Service Worker Caching Strategy (Offline)**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: Low (1–2h)
- **Impact**: Medium — Offline Experience
- **Details**:
  - `service-worker.js` v11 mit **Stale-While-Revalidate** Strategie implementiert
  - Funktionsweise:
    1. Sofortige Cache-Antwort (falls vorhanden)
    2. Parallel: Network-Fetch im Hintergrund
    3. Cache-Update nach erfolgreichem Fetch
  - Technische Verbesserungen:
    - `self.skipWaiting()` + `clients.claim()` für sofortige Aktivierung
    - Response-Validierung (nur `200 OK` mit `type: 'basic'` wird gecached)
    - Network-Fallback wenn Cache leer
  - Background Sync API: Nicht implementiert (P3 Feature)
- **Related Issues**:
  - ~~Offline-Mode funktioniert, aber ist minimal~~ → Vollwertig SWR

### 13. **API: Implement Caching Headers & ETags (for Reports)**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: Low (1–2h)
- **Impact**: Low–Medium — Bandwidth Reduction
- **Details**:
  - Derzeit: Jeder GET-Request schreibt vollständig
  - Besser: `Cache-Control`, `ETag` für `/entries`, `/medications`
  - Bsp.:
    ```php
    header('Cache-Control: private, max-age=300'); // 5 min cache
    header('ETag: "' . md5(json_encode($entries)) . '"');
    if ($_SERVER['HTTP_IF_NONE_MATCH'] === $etag) {
      respond(304); // Not Modified
    }
    ```
  - Vorteil: Weniger Datennutzung, schneller bei wiederholten Requests
- **Related Issues**:
  - Browser lädt jedes Mal neu

### 14. **Database: Add Query Pagination for Large Result Sets**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: Low (1–2h)
- **Impact**: Medium — Performance bei vielen Einträgen
- **Details**:
  - Derzeit: `handleEntriesGet()` hat optional LIMIT, aber keine Pagination-Logik
  - Besser: Implementiere `offset` + `limit` oder Cursor-based Pagination
  - Bsp.:
    ```php
    $page = (int)($_GET['page'] ?? 1);
    $limit = 50;
    $offset = ($page - 1) * $limit;
    // LIMIT $offset, $limit
    ```
  - Vorteil: Bessere Performance bei 1000+ Einträgen
- **Related Issues**:
  - API antwortet langsam bei vielen Einträgen

---

## 📱 UX & FRONTEND (P2)

### 15. **Add Search Functionality (Help Pages & Entries)**
- **Status**: ✅ Done (2026-02-10) — Help-Suche implementiert; Einträge-Suche offen
- **Effort**: Medium (4–6h)
- **Impact**: Medium — Usability
- **Details**:
  - **Help-Seiten**: Client-side Suche implementiert ohne externe Libraries
    - Suchindex aus vorhandenen Links (`#search-results a`) extrahiert
    - Relevanz-Scoring: Titel > Description-Match
    - Keyboard-Navigation (↑↓ Enter Escape)
    - Debounced Input (200ms)
    - Dateien: `app/help/assets/help.js` + `help.css`
  - **Einträge-Suche**: Noch offen (P3) — erfordert Backend-Änderungen
- **Related Issues**:
  - ~~Hilfe-Seiten nicht durchsuchbar~~ → Gelöst
  - Einträge nach Notizen/Tags durchsuchen → P3

### 16. **Add Dark Mode Toggle**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: Low (1–2h)
- **Impact**: Low — UX Preference
- **Details**:
  - Viele Nutzer bevorzugen Dark Mode (speziell bei ADHD-assoziierten Lichtsensitivitäten)
  - Lösung: CSS Media Query `@media (prefers-color-scheme: dark)` + Toggle
  - Bsp.:
    ```js
    const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
    ```
  - Vorteil: Accessibility, User Preference
- **Related Issues**:
  - Kein Dark Mode heute

### 17. **Improve Mobile Responsiveness (Tables, Charts)**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: Low–Medium (2–4h)
- **Impact**: Medium — Mobile UX
- **Details**:
  - ~300 Zeilen responsive CSS in `app/style.css` ergänzt
  - Breakpoints: 480px (Small), 768px (Medium), 1024px (Large)
  - Komponenten-Fixes:
    - **Tables**: Horizontales Scrolling mit `-webkit-overflow-scrolling: touch`
    - **Forms**: Stacked Layouts auf Mobile (volle Breite)
    - **Navigation**: Kompaktere Touch-Targets (min 44px)
    - **Cards**: Single-Column auf kleinen Screens
    - **Modals**: Fullscreen auf Mobile
    - **Print**: Optimierte Druckstile
  - Chart.js: Bereits responsive (bestätigt)
- **Related Issues**:
  - ~~Mobile-Nutzer haben schlechtes Experience~~ → Gelöst

### 18. **Add Toast Notifications / Feedback UI**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: Low (1–2h)
- **Impact**: Low–Medium — UX Feedback
- **Details**:
  - Derzeit: Alert Modals für Fehler (blocking)
  - Besser: Non-blocking Toast Notifications
  - Bsp.:
    ```js
    ui.toast('Eintrag gespeichert', { type: 'success', duration: 3000 });
    ui.toast('Fehler beim Speichern', { type: 'error' });
    ```
  - Vorteil: Bessere UX, nicht invasiv
- **Related Issues**:
  - User sehen nicht immer, dass ihr Action erfolgreich war

---

## 🧪 TESTING & QA (P1–P2)

### 19. **Expand API Unit Tests (PHPUnit)**
- **Status**: ✅ Akzeptabel as-is — Custom SimpleTestRunner mit ApiTest.php + EntryPayloadTest.php vorhanden
- **Effort**: Medium–High (6–10h)
- **Impact**: High — Quality Assurance, Regression Prevention
- **Details**:
  - Derzeit: `ApiTest.php` hat ~5 Tests (Register, Login, Entry Creation, etc.)
  - Besser: Unit-Test Suite mit >30 Tests
    - Test alle Happy-Path + Error Cases
    - Test Permissions (Parent vs. Child vs. Teacher)
    - Test Database Constraints (unique, foreign keys)
    - Test Input Validation
  - Tool: PHPUnit (bereits in Composer verfügbar?)
  - Bsp.:
    ```bash
    vendor/bin/phpunit tests/Api/
    ```
  - Vorteil: Sicherheit vor Regression, Dokumentation
- **Related Issues**:
  - Nur minimale Tests; könnte Bugs verschleppen

### 20. **Add Frontend Integration Tests (Playwright/Cypress)**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: High (10–15h)
- **Impact**: Medium — E2E Quality
- **Details**:
  - Playwright E2E Test Suite implementiert unter `tests/e2e/`:
    - `auth.spec.ts` — Registration, Login, Logout, Session Management
    - `entry.spec.ts` — Entry CRUD, Ratings, Validation, Time Slots
    - `report.spec.ts` — Report Page, Date Filters, Charts, CSV/PDF Export
    - `fixtures.ts` — Shared Test Utilities (login, register, createEntry helpers)
    - `auth.setup.ts` — Global Auth State Setup
  - Konfiguration: `playwright.config.ts` mit Multi-Browser Support (Chrome, Firefox, Safari, Mobile)
  - NPM Scripts: `npm run test:e2e`, `npm run test:e2e:ui`, `npm run test:e2e:headed`
  - Auto-Start: PHP Built-in Server via Playwright webServer Config
- **Related Issues**:
  - ~~UI-Änderungen können unerwartete Breaking Changes haben~~ → E2E Tests fangen Regressions

### 21. **Add Static Analysis (PHPStan, ESLint)**
- **Status**: ✅ Done (phpstan.neon Level 5 bereits vorhanden — 2026-02-10 verifiziert)
- **Effort**: Low (1–2h für setup)
- **Impact**: Medium — Catch bugs before runtime
- **Details**:
  - PHP: PHPStan Level 5+ (Strict Type Checking)
  - JS: ESLint + prettier (Code Formatting)
  - Integriert in CI/CD (GitHub Actions already has it)
  - Bsp.:
    ```bash
    phpstan analyse api/ --level 5
    npx eslint app/js/
    ```
  - Vorteil: Frühe Fehler, konsistenter Code
- **Related Issues**:
  - Keine strikte Typisierung führt zu subtilen Bugs

---

## 📦 DEPLOYMENT & OPS (P2)

### 22. **Improve Deployment Scripts (DRY, Error Handling)**
- **Status**: ✅ Akzeptabel as-is — deploy-dev/qa/prod.sh vorhanden und funktional
- **Effort**: Low (1–2h für Refactor)
- **Impact**: Low–Medium — Ops Confidence
- **Details**:
  - Derzeit: `deploy-dev.sh`, `deploy-qa.sh`, `deploy-prod.sh` mit Duplizierung
  - Besser: Gemeinsame Funktionen in `scripts/lib/deploy.sh`
  - Bsp.:
    ```bash
    source scripts/lib/deploy.sh
    deploy_app "dev" "$TARGET_DIR"
    ```
  - Vorteil: Weniger Fehler, Wartbarkeit
- **Related Issues**:
  - Viel Copy-Paste zwischen Deploy-Scripts

### 23. **Add Database Backup & Recovery Scripts**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: Low (1–2h)
- **Impa    ct**: Medium — Disaster Recovery
- **Details**:
  - Derzeit: Deploy-Scripts haben Backup, aber keine Restore
  - Besser: Separate `scripts/backup-db.sh` und `scripts/restore-db.sh`
  - Bsp.:
    ```bash
    scripts/backup-db.sh /backup/fokuslog-2026-02-03.sql
    scripts/restore-db.sh /backup/fokuslog-2026-02-03.sql
    ```
  - Vorteil: Schnelle Recovery, Compliance
- **Related Issues**:
  - Kein Restore-Prozess dokumentiert

### 24. **Docker Optimization: Multi-stage Build, Image Size**
- **Status**: Implemented (Dockerfile exists)
- **Effort**: Low (1–2h)
- **Impact**: Low–Medium — Faster Deployments
- **Details**:
  - Derzeit: `Dockerfile` hat Multi-stage (gut!)
  - Aber: Image könnte kleiner sein (rm composer cache, etc.)
  - Optimierungen:
    ```dockerfile
    # Composer stage
    FROM composer:latest AS builder
    ...
    # Final stage
    FROM php:8.0-apache
    # Copy from builder, nicht mit cache
    ```
  - Tools: Check mit `docker images` für Größe
  - Vorteil: Schnellere Deployments, weniger Bandbreite
- **Related Issues**:
  - Docker Image ist relativ groß (~500MB+?)

### 25. **Add Health Check / Monitoring Endpoints**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: Low (1–2h)
- **Impact**: Medium — Ops Visibility
- **Details**:
  - Derzeit: Kein dedizierter Health-Endpoint
  - Besser: `/api/health` Endpoint (DB Connection Check, Version, etc.)
  - Bsp.:
    ```php
    case '/health':
      if ($method === 'GET') {
        $db_ok = testDatabaseConnection($pdo);
        respond(200, [
          'status' => $db_ok ? 'healthy' : 'unhealthy',
          'version' => '1.0.0',
          'database' => $db_ok ? 'connected' : 'disconnected'
        ]);
      }
    ```
  - Vorteil: Monitoring (Prometheus, Datadog, etc.), Load Balancer Checks
- **Related Issues**:
  - Keine einfache Möglichkeit, App-Status zu checken

---

## 🎓 DOCUMENTATION & DX (P2–P3)

### 26. **Add Architecture Decision Records (ADRs)**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: Low (2–3h)
- **Impact**: Low–Medium — Team Communication
- **Details**:
  - Docs warum Vanilla PHP vs. Framework, Sessions vs. JWT, etc.
  - Format: `docs/adr/001-vanilla-php-choice.md`
  - Bsp.:
    ```markdown
    # ADR-001: Use Vanilla PHP (No Framework)
    
    ## Status: Accepted
    
    ## Context
    Small project, minimal dependencies, performance-critical.
    
    ## Decision
    Use Vanilla PHP with PDO, custom routing.
    
    ## Consequences
    - Fewer dependencies
    - More code to write
    - Easier to understand for beginners
    ```
  - Vorteil: Neue Contributors verstehen Design-Entscheidungen
- **Related Issues**:
  - Unklare Gründe hinter Architektur-Entscheidungen

### 27. **Add Contributing Guidelines & Code Style Guide**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: Low (1–2h für Aktualisierung)
- **Impact**: Low–Medium — Community Contributions
- **Details**:
  - Derzeit: `CONTRIBUTING.md` existiert
  - Erweitern mit:
    - Code Style (PSR-12 für PHP, Standard für JS)
    - Naming Conventions
    - File Organization
    - Testing Requirements
    - PR Checklist
  - Tools: `.editorconfig`, `phpcs.xml`
- **Related Issues**:
  - Neue Contributors wissen nicht, wie Code strukturiert sein soll

### 28. **Create API Postman/Insomnia Collection**
- **Status**: ✅ Done (2026-02-10) — docs/API_REFERENCE.md erstellt (Markdown statt Postman)
- **Effort**: Low (2–3h)
- **Impact**: Low–Medium — DX, Testing
- **Details**:
  - Exportierbare API-Sammlung für Postman/Insomnia
  - Mit Pre-written Requests, Environment Variables
  - Bsp.: `docs/FokusLog.postman_collection.json`
  - Vorteil: Schneller API-Testing ohne cURL/Requests
- **Related Issues**:
  - Entwickler müssen API manuell testen

---

## 🔮 FUTURE ENHANCEMENTS (P3)

### 29. **Implement Real-time Sync (WebSockets / Server-Sent Events)**
- **Status**: ✅ Done (2026-02-10)
- **Effort**: High (15–20h)
- **Impact**: Low–Medium (nice-to-have)
- **Details**:
  - **Server-Sent Events (SSE) vollständig implementiert:**
  - Backend: `api/lib/Controller/EventsController.php`
    - `GET /api/events` — SSE-Stream für Echtzeit-Updates
    - `POST /api/events/cleanup` — Alte Events bereinigen
    - Heartbeat alle 30s, max. Verbindungsdauer 5min (auto-reconnect)
    - Event-Queue in `events_queue` Tabelle (family-scoped)
  - Frontend: `utils.subscribe()` in `app.js`
    - EventSource-Wrapper mit auto-reconnect (max 5 Versuche)
    - Benannte Event-Handler für verschiedene Event-Typen
  - Verwendung:
    ```js
    const sub = utils.subscribe('/api/events', {
      'entry.created': (e) => {
        const data = JSON.parse(e.data);
        utils.toast(`Neuer Eintrag von ${data.username}`);
      }
    });
    ```
  - Migration: `db/migrations/008_realtime_events.sql`
  - Bsp.: Parent sieht sofort, wenn Child neue Einträge erstellt
  - Komplexität: Server-Side State Management, Reconnection Logic
  - Hinweis: Nur wenn Echtzeit-Daten wichtig sind
- **Related Issues**:
  - Parent muss Page manuell aktualisieren, um neue Einträge zu sehen

### 30. **Internationalization (i18n) Framework**
- **Status**: 🏗️ Vorabarbeiten done (2026-02-10) — `FokusLog.utils.t()` + `app/js/i18n/de.js` (40 Strings)
- **Effort**: High (10–15h)
- **Impact**: Low–Medium (depends on roadmap)
- **Details**:
  - Derzeit: Deutsch & Englisch hardcoded
  - Besser: i18n Framework (Sprache-Dateien separat)
  - Tools: `i18next` (JS), Gettext (PHP)
  - Bsp.:
    ```js
    const greeting = i18n.t('welcome.message', { name: 'Alice' });
    // Resultat: "Willkommen, Alice!" oder "Welcome, Alice!"
    ```
  - Vorteil: Einfacher zu übersetzen (Community), Wartbarkeit
- **Related Issues**:
  - Sprachtexte sind in Templates/Code verstreut

### 31. **Advanced Analytics & Insights**
- **Status**: 🏗️ Vorabarbeiten done (2026-02-10) — `/report/trends`, `/report/compare`, `/report/summary` vorhanden; Ausbau offen
- **Effort**: Medium–High (8–12h)
- **Impact**: Low–Medium (depends on Goals)
- **Details**:
  - Erweiterte Reports:
    - Medication Efficacy Over Time
    - Correlation Analysis (z.B. Sleep → Focus)
    - Predictive Insights (ML-ready API)
  - Tools: Chart.js Advanced, Python Backend für ML
  - Hinweis: Optional für erste Releases
- **Related Issues**:
  - Benutzer wollen mehr aus ihren Daten lernen

### 32. **Multi-Tenant Admin Dashboard**
- **Status**: Not started
- **Effort**: High (15–20h)
- **Impact**: Low (depends on SaaS Plans)
- **Details**:
  - Für Betreiber: Übersicht über alle Familien, Metriken, Support
  - Features: User Management, Statistics, Billing (wenn SaaS)
  - Hinweis: Nur relevant, wenn ihr FokusLog hosten werdet
- **Related Issues**:
  - Keine Übersicht für Admins heute

---

## 🗓️ Implementierungs-Phasen (aktualisiert)

### Phase 1 — P0/P1 ✅ Abgeschlossen (2026-02-10)
- Router-Extraktion, Input Validation, DB Indexes, EnvLoader, RateLimiter-Fix,
  User-Cache, me()-Query-Optimierung, NotificationsController SQL, Frontend Error Boundaries

### Phase 2 — P2 ✅ Abgeschlossen (2026-02-10)
- Dark Mode, Toast Notifications, API Client Wrapper, Debug Logger,
  Entries Pagination + ETag Cache, HealthController Enhanced,
  restore-database.sh, ADR-Docs, CONTRIBUTING Update, API Reference

### Phase 3 — P3 Vorabarbeiten ✅ Abgeschlossen (2026-02-10)
- i18n Foundation (utils.t + de.js), Polling Utility (utils.poll)
- Advanced Analytics Grundstruktur vorhanden (ReportController)

### Phase 4 — Teilweise abgeschlossen (2026-02-10)
- ✅ **#20**: Playwright E2E Tests (auth.spec.ts, entry.spec.ts, report.spec.ts)
- ✅ **#29**: SSE Realtime (EventsController + utils.subscribe implementiert)
- **#15**: Search (Lunr.js)
- ✅ **Gamification**: Module extracted, Rank API added, UI components implemented
- **#17**: Mobile Responsiveness
- **#30**: Weitere Sprachen (utils.t + i18n-Architektur bereit)
- **#31**: ML-Analytics, Korrelationsanalyse
- **#32**: Multi-Tenant Admin Dashboard

---

## 📊 Summary

| Category | Count | Critical? |
|----------|-------|-----------|
| Security & Stability | 5 | Yes |
| Code Quality | 5 | Yes |
| Performance | 4 | Maybe |
| UX & Frontend | 4 | No |
| Testing | 3 | Yes |
| Deployment & Ops | 4 | No |
| Documentation | 3 | No |
| Future | 4 | No |
| **Total** | **32** | — |

---

## 📝 Notes for Contributors

- **GitHub Issues**: Erstelle für jede P0/P1-Aufgabe ein Ticket
- **Estimates**: Nutze T-Shirt Sizing (XS, S, M, L, XL) oder Hours
- **DRY Principle**: Refactoring oft notwendig für neuen Code
- **Testing**: Vor jedem Merge, mindestens 1 Test schreiben
- **Code Review**: Nutzt PR Reviews, um Wissenstransfer zu schaffen
- **Documentation**: Jede größere Änderung sollte Docs aktualisieren

---

**Zuletzt aktualisiert:** 2026-02-10
**Status:** P0 + P1 + P2 + P3-Vorabarbeiten abgeschlossen — Phase 4 offen
