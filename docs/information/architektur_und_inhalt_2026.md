# FokusLog – Architektur-, Daten- und Nutzungssnapshot (Februar 2026)

## Überblick
FokusLog ist eine vollständig clientseitige Progressive Web App mit einem kompakten PHP-Backend. Das Ziel ist, Medikations- und Symptomverläufe DSGVO-konform zu dokumentieren und für Familien, Lehrkräfte und Ärzt:innen nutzbar zu machen. Dieses Dokument fasst den aktuellen Stand von Architektur, Datenhaltung, API, Inhaltslandschaft und Nutzungsszenarien zusammen und adressiert explizit die Zielgruppen Nutzer:innen, Lehrkräfte, Ärzt:innen und Entwickler:innen.

## Technische Architektur
### Frontend (PWA)
- **Struktur**: Statische HTML-Seiten unter [app/](app) mit Vanilla-JS-Modulen (u.a. [app/js/app.js](app/js/app.js)) und globalem Styling ([app/style.css](app/style.css)).
- **PWA-Fähigkeiten**: [app/service-worker.js](app/service-worker.js) cached Kernressourcen, [app/manifest.json](app/manifest.json) definiert Installations-Metadaten.
- **Visualisierungen & Exporte**: Chart.js und jsPDF liegen lokal unter [vendor/](vendor) und werden offline nutzbar eingebunden.
- **Hilfebereich**: [app/help/index.html](app/help/index.html) bietet Baum- und Grid-Navigation; [app/help/lexikon.html](app/help/lexikon.html) rendert Glossar-Einträge dynamisch.

### Backend (API)
- **Single Entry Point**: Das komplette REST-API lebt in [api/index.php](api/index.php). PDO wird mit vorbereiteten Statements verwendet; Sessions sichern Authentifizierung.
- **Helper**: Payload-Normalisierung über [api/lib/EntryPayload.php](api/lib/EntryPayload.php), Logging via [api/lib/logger.php](api/lib/logger.php).
- **Deployment**: PHP 7.4+/8.x ohne Framework, kompatibel mit Shared Hosting und Docker (siehe [Dockerfile](Dockerfile) und [docker-compose.yml](docker-compose.yml)).

### Infrastruktur & Sicherheit
- `.env` im Projektwurzelverzeichnis bestimmt DB-Zugangsdaten und Admin-Tokens.
- Cookies sind `HttpOnly`, `Secure`, `SameSite=Strict`; Sessions werden bei Login regeneriert.
- `logAction()` schreibt JSON-Details in das Audit-Log, Failures blockieren den Request nicht.
- Admin-Endpunkte `/admin/migrate` und `/admin/backup` sind token-geschützt.

## Datenbank & Persistenz
- **Referenzschema**: [db/schema_v4.sql](db/schema_v4.sql) ist die neue Kanon-Definition. Es erweitert Vorgängerversionen um JSON-Einstellungen auf Familienebene, optionale Benutzerprofile sowie die Glossar-Tabelle für Wissensinhalte.
- **Zentrale Tabellen**
  - `families`, `users`, `medications`, `entries` bilden das Tagebuchfundament.
  - `tags` & `entry_tags` bieten konfigurierbare Klassifizierungen.
  - `badges`, `user_badges` treiben Gamification inklusive streaklosen Spezial-Badges.
  - `consents` dokumentieren Datenschutz-Einwilligungen, `audit_log` hält JSON-basierte Trails.
  - `glossary` speichert Hilfetexte inkl. `full_content` für Detailseiten. Inhalte werden über [app/help/import_help.php](app/help/import_help.php) aus den HTML-Dateien synchronisiert.
- **Seed-Daten**: Standard-Badges (inkl. „Wochenend-Warrior“, „Früher Vogel“, „Nachteule“) werden im Schema verteilt.

## API-Oberfläche
Alle Endpunkte laufen über `/api` (siehe [api/index.php](api/index.php)). Responses sind immer JSON. Session-Cookies übernehmen Auth.

| Kategorie | Methode & Pfad | Beschreibung |
| --- | --- | --- |
| Auth | POST `/register`, `/login`, `/logout` | Familien anlegen, Sessions verwalten |
| Profil | GET `/me`, POST `/users/me/password`, GET `/me/latest-weight` | Benutzerinfos, Gamification, Passwortwechsel, Gewicht |
| Benutzer | GET/POST `/users`, GET/PUT/DELETE `/users/{id}` | Familiensicht, Rollenverwaltung (Parent/Adult) |
| Einträge | GET/POST `/entries`, DELETE `/entries/{id}` | Tagebuchabfragen (inkl. Tag-IDs), Upsert je Zeitslot |
| Medikation | CRUD auf `/medications` | Familienweite Medikationsliste |
| Tags | GET/POST `/tags`, DELETE `/tags/{id}` | Haushalts-Tags pflegen |
| Badges | GET `/badges` | Alle verfügbaren Badges mit Earned-Flag |
| Gewicht | GET `/weight` | Gewichtshistorie je Benutzer (Teacher ausgeschlossen) |
| Glossar | GET `/glossary` | Liefert strukturierte Hilfseinträge aus `glossary` |
| Admin | POST `/admin/migrate`, `/admin/backup` | Migrationen/Backups via Token |

Fehlerfälle liefern strukturierte JSON-Meldungen; alle sicherheitskritischen Aktionen landen im `audit_log`.

## Inhalte & Nutzung
- **Help Hub**: Die Startseite gruppiert Inhalte in „Über die App“, „Alltag“ und „Wissen“, ergänzt durch Zielgruppenpfade (Ärzt:innen, Lehrkräfte, Eltern, Kinder).
- **Lexikon/Glossar**: `/api/glossary` beliefert den Suchindex in [app/help/assets/help.js](app/help/assets/help.js) und [app/help/lexikon.html](app/help/lexikon.html); clientseitige Filter ermöglichen Zielgruppen-spezifische Recherchen.
- **Gamification**: Dashboard, Entry-Formulare und Badge-Widgets werden über [app/js/app.js](app/js/app.js) befüllt und motivieren zu täglichen Einträgen.
- **Berichte & Export**: Chart-Ansichten und PDF/CSV-Exports helfen Familien und Ärzten, Entscheidungen anhand realer Verlaufsdaten zu treffen.

## Zielgruppen-spezifische Leitplanken
### Nutzer:innen & Familien
1. Registrierung über `/register`, Familienrollen über `/users` pflegen.
2. Tägliche Einträge via `/entries` oder UI; Gamification (Punkte/Streaks) läuft automatisch.
3. PDF/CSV-Export sowie Badge-Feedback stärken Motivation und Transparenz.
4. Hilfebereich liefert praxisnahe Alltagstipps und medizinische Einordnung direkt aus der App.

### Lehrkräfte
- Erstellen Einträge für verknüpfte Kinder über das gleiche Formular; `child_id` macht Einreichungen eindeutig.
- Historische Einträge und Gewichtsdaten sind bewusst gesperrt – Fokus liegt auf aktueller Beobachtung am Schultag.
- „App-Einsatz in der Schule“ und „Kooperation Schule“ in [app/help/index.html](app/help/index.html) strukturieren pädagogische Hinweise.

### Ärzt:innen
- Zugriff erfolgt typischerweise indirekt über Exporte oder gemeinsame Sitzungen. Eltern können PDF-Berichte exportieren und die Kategorien „Arzttermine & Austausch“ nutzen.
- Audit-Log unterstützt revisionssichere Nachvollziehbarkeit sensibler Operationen.
- Glossar bietet valide Kommunikationsgrundlage (z.B. Nebenwirkungslexikon).

### Entwickler:innen
1. Setup laut [README.md](README.md): `php -S localhost:8000` als Dev-Server, API-Tests via [api/run_tests.php](api/run_tests.php).
2. Datenbank generieren mit [db/schema_v4.sql](db/schema_v4.sql) oder `scripts/rebuild-database.sh`.
3. Hilfsdaten aktualisieren über [app/help/import_help.php](app/help/import_help.php) (CLI-Skript für Glossar-Sync).
4. Änderungen stets in API-Tests und ggf. Frontend-Rauchtests absichern; Logging hilft beim Debugging.

## Betrieb & Weiterentwicklung
- **Migrationen**: Für neue Tabellen/Indizes liegen Skripte unter [db/migrations/](db/migrations). `/admin/migrate` führt sie kontrolliert aus.
- **Backups**: `/admin/backup` erzeugt SQL-Dumps unter `backups/` und rotiert ältere Artefakte.
- **Monitoring**: `app_log()` sammelt strukturierte Events; Audit-Log dient als Compliance-Anker.
- **Weiteres**: Neue Inhalte sollten in `app/help/` entstehen und anschließend via Importskript in `glossary` landen, damit PWA-Suche und API synchron bleiben.

Mit diesem Paket (Schema v4 + Zielgruppenleitfaden) liegen technische und inhaltliche Grundlagen konsistent vor und können als Referenz für Planung, Schulung oder externe Reviews dienen.
