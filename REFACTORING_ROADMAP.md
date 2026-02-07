# FokusLog — Refactoring & Optimization Roadmap

**Datum:** Februar 2026  
**Status:** Work in Progress  
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
- **Status**: Not started
- **Effort**: Medium (4–6h)
- **Impact**: High — easier testing, less giant file
- **Details**:
  - `api/index.php` ist ~1400 Zeilen (schwer zu lesen, zu debuggen)
  - Aufteilen in: `api/Router.php`, `api/Handlers/` (Endpoints), `api/Middleware/`
  - Bsp.: `handleRegister()` → `Handlers/Auth.php`, `handleEntriesGet()` → `Handlers/Entries.php`
  - Vorteil: Unit-Tests, bessere Error Handling, klare Struktur
- **Related Issues**: 
  - Große File ist schwer zu warten
  - Repetitives `requireAuth()` / `requireRole()` in jedem Handler

### 2. **Frontend: Remove/Suppress Console Logs in Production**
- **Status**: Not started
- **Effort**: Low (1–2h)
- **Impact**: Medium — Datenschutz, Performance (minor)
- **Details**:
  - ~30+ `console.log()` / `console.error()` Aufrufe in `app.js`
  - Bsp.: `console.log('Eintrag gefunden', entry)` kann sensible Daten zeigen
  - Lösung: Environment-gesteuerte Logging (Dev vs. Prod) oder einen Logger-Wrapper verwenden
  - Bsp.: `logger.debug('Eintrag gefunden', entry)` → nur in Dev aktiv
- **Related Issues**:
  - Sensible Daten könnten geloggt werden

### 3. **API: Centralized Input Validation & Sanitization**
- **Status**: Not started
- **Effort**: Medium–High (6–8h)
- **Impact**: High — Security, Consistency
- **Details**:
  - Derzeit: Ad-hoc Validierung in jedem Handler (`trim()`, `empty()` checks)
  - Besser: Validator-Klasse oder Middleware-Pipeline
  - Bsp.: `Validator::string('name', ['min' => 3, 'max' => 100])`
  - Vorteil: Wiederverwendbar, konsistent, leichter zu testen
- **Related Issues**:
  - SQL-Injection wird durch PDO::PREPARE mitigiert, aber noch Input-Validation fehlt
  - Fehlende Fehlerbehandlung bei ungültigen Eingaben

### 4. **Database: Add Indexes & Query Optimization**
- **Status**: Not started
- **Effort**: Low (2–3h)
- **Impact**: Medium–High — Performance bei wachsenden Datamengen
- **Details**:
  - Fehlende Indexes auf häufig abgefragten Spalten: `entries.user_id`, `entries.date`, `users.family_id`
  - Typ-Mismatch: `TINYINT` für Ratings (1–5) ist OK, aber `ENUM` für time-slot auch OK
  - Query: `SELECT * FROM entries WHERE user_id = ? AND date BETWEEN ? AND ?` sollte auf `(user_id, date)` oder `(user_id, date DESC)` Index haben
  - Bsp. Indexes zu ergänzen:
    ```sql
    ALTER TABLE entries ADD INDEX idx_user_date (user_id, date);
    ALTER TABLE users ADD INDEX idx_family (family_id);
    ALTER TABLE user_badges ADD INDEX idx_user (user_id);
    ```
- **Related Issues**:
  - Bei >10k entries können Reports langsam werden

### 5. **Sessions: Implement Secure Session Storage (Optional Upgrade)**
- **Status**: Not started
- **Effort**: Medium (4–5h)
- **Impact**: Low–Medium — Enterprise Security
- **Details**:
  - Derzeit: PHP native Sessions (stored on disk, unsicher bei shared hosting)
  - Option 1: Redis/Memcached für Session Storage (wenn verfügbar)
  - Option 2: JWT Tokens + Stateless Auth (weniger invasiv für bestehenden Code)
  - Achtung: Nur wenn ihr skaliert oder strikte Security-Anforderungen habt
- **Related Issues**:
  - Sessions können bei Deployment auf mehreren Servern inkonsistent sein

---

## 🎯 CODE QUALITY & MAINTAINABILITY (P1)

### 6. **API: Add Type Hints & Return Types**
- **Status**: Not started
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
- **Status**: Not started
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
- **Status**: Not started
- **Effort**: High (8–12h)
- **Impact**: High — Maintainability, Testing
- **Details**:
  - `app.js` ist ~2000 Zeilen (ein großer Block)
  - Aufteilen in Module:
    - `modules/auth.js` — Login, Logout, Auth-Check
    - `modules/entries.js` — Eintragsverwaltung
    - `modules/meds.js` — Medikamentenverwaltung
    - `modules/ui.js` — UI-Helfer (addFooterLinks, escapeHtml)
    - `modules/api.js` — Zentrale API-Calls (Fetch-Wrapper)
  - Nutzen: ES6 Modules (`.js` mit `import/export`)
  - Vorteil: Bessere Lesbarkeit, Wiederverwendbarkeit, Testing
- **Related Issues**:
  - Code ist schwer zu navigieren
  - Viele lokale Funktionen, die sich "verstecken"

### 9. **Frontend: Create API Client Wrapper Class**
- **Status**: Not started
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
- **Status**: Not started
- **Effort**: Low (2–3h)
- **Impact**: Medium — IDE Support, Code Documentation
- **Details**:
  - Bsp. PHP:
    ```php
    /**
     * Erstellt einen neuen Eintrag.
     * @param PDO $pdo Database connection.
     * @param array $data Entry data (date, time, etc.).
     * @return void
     * @throws PDOException If database operation fails.
     */
    function handleEntriesPost(PDO $pdo): void { ... }
    ```
  - Bsp. JS:
    ```js
    /**
     * Loads all entries for the current user.
     * @async
     * @param {object} options - Query options (dateFrom, dateTo, etc.)
     * @returns {Promise<Array>} List of entries.
     */
    async function loadEntries(options = {}) { ... }
    ```
  - Vorteil: IDE-Completion, schnellere Onboarding
- **Related Issues**:
  - Wenig dokumentiert; IDE kann nicht inferieren

---

## ⚡ PERFORMANCE OPTIMIZATION (P1–P2)

### 11. **Frontend: Implement Lazy Loading for Images & Charts**
- **Status**: Not started
- **Effort**: Low–Medium (2–3h)
- **Impact**: Low–Medium — Page Load Performance
- **Details**:
  - Derzeit: Charts mit Chart.js werden sofort geladen
  - Besser: Lazy-load auf Demand (wenn Benutzer zum Report scrollt)
  - Tools: Intersection Observer API, `loading="lazy"` (bilder)
  - Vorteil: Initial Page Load schneller, speziell auf Mobile
- **Related Issues**:
  - Reports können auf langsamen Verbindungen zögerlich sein

### 12. **Frontend: Service Worker Caching Strategy (Offline)**
- **Status**: Implemented (partially)
- **Effort**: Low (1–2h, für Verbesserung)
- **Impact**: Medium — Offline Experience
- **Details**:
  - SW vorhanden: `service-worker.js` mit grundlegender offline-Unterstützung
  - Aber: Keine Stale-while-revalidate, keine Background Sync
  - Verbesserung:
    - Implement `stale-while-revalidate` Strategy (serve cached, update in background)
    - Add Background Sync API für Offline-Einträge (speichern bei reconnect)
  - Vorteil: Schnellere Loads, bessere Offline-UX
- **Related Issues**:
  - Offline-Mode funktioniert, aber ist minimal

### 13. **API: Implement Caching Headers & ETags (for Reports)**
- **Status**: Not started
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
- **Status**: Partially implemented
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
- **Status**: Not started
- **Effort**: Medium (4–6h)
- **Impact**: Medium — Usability
- **Details**:
  - Benutzer wollen Einträge/Help schnell durchsuchen
  - Lösung: Lunr.js (client-side, German stemmer) oder Mini-Search
  - Bsp.:
    - Hilfe-Seiten durchsuchbar machen (siehe frühere Diskussion)
    - Einträge nach Notizen/Tags durchsuchen
  - Vorteil: Bessere Navigation, weniger frustriert
- **Related Issues**:
  - Benutzer können Einträge schwer finden (nur nach Datum möglich)

### 16. **Add Dark Mode Toggle**
- **Status**: Not started
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
- **Status**: Partially done
- **Effort**: Low–Medium (2–4h)
- **Impact**: Medium — Mobile UX
- **Details**:
  - Derzeit: CSS hat `@media` Queries, aber nicht alle Komponenten responsive
  - Problem: Tables zu groß auf Mobile (z.B. Entry Details)
  - Lösung:
    - Horizontales Scrolling für Tables
    - Oder: Stack Layouts auf Mobile (1 Spalte statt Grid)
    - Chart.js sollte responsive sein (check)
  - Tools: `@media (max-width: 768px)` Refinements
- **Related Issues**:
  - Mobile-Nutzer haben schlechtes Experience auf manchen Seiten

### 18. **Add Toast Notifications / Feedback UI**
- **Status**: Not started
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
- **Status**: Basic tests exist (ApiTest.php)
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
- **Status**: Not started
- **Effort**: High (10–15h)
- **Impact**: Medium — E2E Quality
- **Details**:
  - Automatisierte Tests für User Flows:
    - Register → Create Entry → View Report → Export PDF
    - Parent → Create Child → Manage Child Entries
    - Login/Logout Session Handling
  - Tool: Playwright oder Cypress (modern, easy)
  - Bsp.:
    ```bash
    npx playwright test
    ```
  - Vorteil: Catch UI/API Mismatches, Faster QA
- **Related Issues**:
  - UI-Änderungen können unerwartete Breaking Changes haben

### 21. **Add Static Analysis (PHPStan, ESLint)**
- **Status**: Partially done (ESLint konfiguriert in CI/CD?)
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
- **Status**: Implemented (scripts/deploy-*.sh)
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
- **Status**: Partially implemented
- **Effort**: Low (1–2h)
- **Impact**: Medium — Disaster Recovery
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
- **Status**: Not started
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
- **Status**: Not started
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
- **Status**: Exists (CONTRIBUTING.md)
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
- **Status**: Not started
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
- **Status**: Not started
- **Effort**: High (15–20h)
- **Impact**: Low–Medium (nice-to-have)
- **Details**:
  - Derzeit: Polling (Benutzer müssen manuell aktualisieren)
  - Besser: WebSockets oder Server-Sent Events (SSE)
  - Bsp.: Parent sieht sofort, wenn Child neue Einträge erstellt
  - Komplexität: Server-Side State Management, Reconnection Logic
  - Hinweis: Nur wenn Echtzeit-Daten wichtig sind
- **Related Issues**:
  - Parent muss Page manuell aktualisieren, um neue Einträge zu sehen

### 30. **Internationalization (i18n) Framework**
- **Status**: Not started
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
- **Status**: Not started
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

## 🗓️ Suggested Implementation Order

### Phase 1 (Immediate — Week 1–2)
1. **Security**: Add input validation (P0 #3)
2. **Code Quality**: Type hints & docblocks (P1 #6, #10)
3. **Testing**: Expand API tests (P1 #19)

### Phase 2 (Month 1–2)
4. **Refactoring**: Extract router (P0 #1)
5. **Refactoring**: Modularize `app.js` (P1 #8)
6. **Performance**: Add database indexes (P0 #4)
7. **Testing**: Add integration tests (P1 #20)

### Phase 3 (Month 2–3)
8. **Performance**: Lazy loading, caching (P1–P2 #11, #13)
9. **UX**: Search functionality (P2 #15)
10. **Deployment**: Improve scripts, health checks (P2 #22, #25)

### Phase 4+ (Long-term)
- Everything else: Dark Mode, i18n, Advanced Analytics, Real-time Sync

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

**Zuletzt aktualisiert:** 2026-02-03  
**Status:** Entwurf — Feedback willkommen!
