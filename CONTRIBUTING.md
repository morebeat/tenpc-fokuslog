# Mitwirken an FokusLog

Vielen Dank für dein Interesse, an FokusLog mitzuarbeiten! Wir freuen uns über jede Hilfe, die das Projekt besser macht.

## Leitprinzipien

*   **Respekt:** Wir achten auf unsere Zielgruppen (Kinder, Familien, Lehrkräfte).
*   **Kleine Schritte:** Änderungen sollten klein, verständlich und fokussiert sein.
*   **Klarheit vor Cleverness:** Lesbarer Code ist wichtiger als "smarte" Einzeiler.

## Wie kann ich beitragen?

Wir freuen uns besonders über:
*   🐛 **Bugfixes** (Fehlerbehebungen)
*   ♿ **Barrierefreiheit** (Accessibility Improvements)
*   📝 **Dokumentation** (Verbesserungen an Texten und Anleitungen)
*   🎨 **UX-Optimierungen** (Benutzerfreundlichkeit)

Bitte vermeide:
*   Riesige, unkoordinierte Feature-Updates (bitte vorher ein Issue öffnen, um die Idee zu besprechen).
*   Komplette Rewrites in anderen Frameworks.
*   Änderungen, die den Datenschutz oder die Barrierefreiheit verschlechtern.

## Der Pull Request Prozess (Schritt für Schritt)

Wir nutzen den Standard-GitHub-Workflow. So reichst du deine Änderungen ein:

1.  **Forken**:
    Klicke oben rechts auf "Fork", um eine Kopie des Repositories in deinem GitHub-Account zu erstellen.

2.  **Klonen**:
    Lade deinen Fork auf deinen lokalen Rechner herunter.
    ```bash
    git clone https://github.com/DEIN_USER/fokuslog-app.git
    cd fokuslog-app
    ```

3.  **Branch erstellen**:
    Erstelle einen neuen Branch für deine Änderung. Wähle einen sprechenden Namen (z. B. `fix/login-error` oder `feat/dark-mode`).
    ```bash
    git checkout -b feat/mein-neues-feature
    ```

4.  **Änderungen implementieren**:
    Nimm deine Änderungen vor. Achte darauf, dass der Code sauber und verständlich bleibt.

5.  **Testen**:
    *   Führe, wenn möglich, die API-Tests aus (`php api/run_tests.php`).
    *   Prüfe deine Änderungen manuell im Browser.

6.  **Committen & Pushen**:
    ```bash
    git add .
    git commit -m "feat: Beschreibe kurz, was du getan hast"
    git push origin feat/mein-neues-feature
    ```

7.  **Pull Request (PR) öffnen**:
    *   Gehe auf GitHub zu deinem Fork oder zum Original-Repository.
    *   Du solltest einen Hinweis sehen: "Compare & pull request".
    *   Fülle das PR-Formular aus. Beschreibe **was** du geändert hast und **warum**.
    *   Füge Screenshots hinzu, falls du die Benutzeroberfläche geändert hast.

## Code-Richtlinien

*   **Sprache**: Wir nutzen Deutsch für Dokumentation/Issues und Englisch für Code/Kommentare.
*   **Keine "Magie"**: Code sollte explizit und nachvollziehbar sein.

### PHP

*   **Standard**: PSR-12 (Einrückung: 4 Spaces, nicht Tabs)
*   `declare(strict_types=1)` in jeder PHP-Datei am Anfang
*   Namespace: `FokusLog\Controller\` für Controller, `FokusLog\` für Services/Utilities
*   Alle Controller erben von `BaseController`
*   Input-Validierung via `FokusLog\Validator` statt Ad-hoc-Checks
*   DB-Queries: **immer** Prepared Statements mit PDO; kein direktes String-Bauen mit User-Input
*   Fehlerbehandlung: `try-catch (Throwable $e)` + `app_log()` + `$this->respond(5xx)`
*   Statische Analyse: `phpstan analyse api/ --level 5` muss ohne Fehler durchlaufen

### JavaScript

*   ES6+ (keine `var`, keine IE11-Kompatibilität nötig)
*   Semikolons verwenden
*   Async/Await statt Promise-Chains
*   HTTP-Requests über `FokusLog.utils.apiCall()` — kein direktes `fetch()`
*   Fehlermeldungen über `FokusLog.utils.toast()` — kein `alert()`
*   Debug-Logging über `FokusLog.utils.log()` / `FokusLog.utils.error()` — kein `console.log/error` direkt
*   Neue Strings → in `app/js/i18n/de.js` eintragen, dann via `FokusLog.utils.t('key')` verwenden
*   Page-Module: Namespace `FokusLog.pages.<name>` + `init(context)` Methode exportieren

### Naming Conventions

| Was | Konvention | Beispiel |
|-----|-----------|---------|
| PHP-Klassen | PascalCase | `AuthController`, `EnvLoader` |
| PHP-Methoden | camelCase | `requireAuth()`, `getJsonBody()` |
| PHP-Konstanten | UPPER\_SNAKE | `MIN_PASSWORD_LENGTH` |
| JS-Funktionen | camelCase | `loadPageModule()`, `apiCall()` |
| JS-Klassen | PascalCase | `ApiError` |
| CSS-Klassen | BEM / kebab-case | `.fl-toast`, `.fl-toast--error` |
| Routen | kebab-case | `/notifications/vapid-key` |
| DB-Spalten | snake\_case | `user_id`, `family_id` |

## PR-Checkliste

Bevor du einen Pull Request einreichst, prüfe folgendes:

*   [ ] Code ist lesbar und folgt den Richtlinien oben
*   [ ] Neue PHP-Inputs werden via `Validator` geprüft
*   [ ] Keine `console.log` / `alert()` direkt — stattdessen `utils.log()` / `utils.toast()`
*   [ ] PHP-Tests laufen: `php api/run_tests.php` ohne Fehler
*   [ ] PHPStan: `phpstan analyse api/ --level 5` ohne neue Fehler
*   [ ] Manuelle Browser-Prüfung durchgeführt (insbesondere Login, Eintrag anlegen)
*   [ ] Neue UI-Strings in `app/js/i18n/de.js` eingetragen
*   [ ] Zugehörige Dokumentation aktualisiert (CHANGELOG.md wenn nötig)
*   [ ] Keine sensiblen Daten committed (Passwörter, Tokens, `.env`)

## Werkzeuge

*   **PHP-Tests**: `php api/run_tests.php` (Custom SimpleTestRunner in `api/SimpleTestRunner.php`)
*   **Statische Analyse**: `phpstan analyse api/ --level 5`
*   **Lokale Entwicklung**: Docker Compose (`docker-compose up`) oder PHP Built-in Server

## Lizenz

Mit dem Einreichen eines Pull Requests stimmst du zu, dass deine Beiträge unter der **CC BY-NC-SA 4.0** Lizenz des Projekts veröffentlicht werden.