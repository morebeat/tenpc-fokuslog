# FokusLog

**Beobachten statt bewerten.**

FokusLog ist eine datenschutzfreundliche Progressive Web App (PWA) zur Dokumentation von ADHS-Symptomen, Medikation und Nebenwirkungen. Sie hilft Familien, Lehrkräften und therapeutischem Fachpersonal, Verläufe objektiv zu betrachten, ohne vorschnell zu urteilen.

## 🚀 Features

- **Datenschutz-First**: Keine Tracker, keine Cloud-Pflicht, volle Datenhoheit.
- **PWA**: Installierbar auf Smartphones, offline-fähig (Kernfunktionen).
- **Rollenbasiert**: Ansichten für Eltern, Kinder, Lehrkräfte und Erwachsene.
- **Gamification**: Motivierende Elemente für Kinder (Streaks, Badges).
- **Berichte & Analysen**: 
  - PDF-Export für Arztgespräche
  - Excel/CSV-Export mit Arzt-Format
  - Automatische Trend-Erkennung (Appetit, Stimmung, Gewicht)
  - Woche-über-Woche-Vergleiche
- **Benachrichtigungen** (NEU):
  - Push-Erinnerungen (morgens, mittags, abends)
  - Wöchentlicher E-Mail-Digest für Eltern
  - Automatische Alerts bei fehlenden Einträgen
- **Wissen**: Integriertes Lexikon und Hilfebereich.

## 🛠 Tech Stack

- **Frontend**: Vanilla JavaScript (ES6+), CSS3, HTML5. Keine Build-Tools notwendig.
- **Backend**: PHP 8.0+ (REST API mit Controller-Architektur).
- **Datenbank**: MySQL / MariaDB.
- **Libraries**: Chart.js (Visualisierung), jsPDF (Berichte), canvas-confetti (Gamification).

## 📁 API-Architektur

Die API nutzt eine modulare **Controller-basierte Architektur**:

```
api/
├── index.php              # Einstiegspunkt & Router-Konfiguration
├── RateLimiter.php        # Rate-Limiting
└── lib/
    ├── Router.php         # URL-Routing mit Parameter-Extraktion
    ├── EntryPayload.php   # DTO für Einträge
    └── Controller/
        ├── BaseController.php      # Gemeinsame Funktionen (Auth, Response)
        ├── AuthController.php      # Login, Register, Logout
        ├── UsersController.php     # Benutzer-CRUD
        ├── MedicationsController.php
        ├── EntriesController.php   # Tagebuch-Einträge + Gamification
        ├── TagsController.php
        ├── BadgesController.php
        ├── WeightController.php
        ├── GlossaryController.php
        ├── ReportController.php    # Analysen & Exporte
        ├── NotificationsController.php  # Push & E-Mail (NEU)
        └── AdminController.php     # Migration, Backup
```

## 🔗 API-Endpunkte

### Authentifizierung
| Methode | Endpunkt | Beschreibung |
|---------|----------|--------------|
| POST | `/register` | Familie/Benutzer registrieren |
| POST | `/login` | Anmelden |
| POST | `/logout` | Abmelden |
| GET | `/me` | Aktuelle Benutzerinfo |
| POST | `/users/me/password` | Passwort ändern |

### Benutzerverwaltung (Parent/Adult)
| Methode | Endpunkt | Beschreibung |
|---------|----------|--------------|
| GET | `/users` | Familienmitglieder auflisten |
| POST | `/users` | Benutzer anlegen |
| PUT | `/users/{id}` | Benutzer aktualisieren |
| DELETE | `/users/{id}` | Benutzer löschen |

### Einträge (Tagebuch)
| Methode | Endpunkt | Beschreibung |
|---------|----------|--------------|
| GET | `/entries` | Einträge abrufen |
| POST | `/entries` | Eintrag erstellen/aktualisieren |
| DELETE | `/entries/{id}` | Eintrag löschen |

### Medikamente
| Methode | Endpunkt | Beschreibung |
|---------|----------|--------------|
| GET | `/medications` | Medikamente auflisten |
| POST | `/medications` | Medikament anlegen |
| PUT | `/medications/{id}` | Medikament aktualisieren |
| DELETE | `/medications/{id}` | Medikament löschen |

### Reports & Analysen (NEU)
| Methode | Endpunkt | Beschreibung |
|---------|----------|--------------|
| GET | `/report/trends` | Trend-Analyse mit Muster-Erkennung |
| GET | `/report/compare` | Wochenvergleich oder Medikamenten-Vergleich |
| GET | `/report/summary` | Zusammenfassung für PDF-Reports |
| GET | `/report/export/excel` | Excel/CSV-Export (detailed, summary, doctor) |

#### Trend-Analyse (`/report/trends`)
Erkennt automatisch auffällige Muster:
- **Appetit-Warnung**: 3+ Tage mit niedrigem Appetit
- **Stimmungs-Trend**: Auf- oder Abwärtstrends
- **Schlaf-Qualität**: Niedriger Durchschnitt
- **Reizbarkeit**: Erhöhte Werte über mehrere Tage
- **Gewichtsverlust**: >3% Verlust im Zeitraum
- **Nebenwirkungen-Häufung**: Viele dokumentierte Nebenwirkungen

#### Vergleichs-Ansichten (`/report/compare`)
Query-Parameter:
- `type=week` – Woche-über-Woche-Vergleich (Standard)
- `type=medication&med1=X&med2=Y` – Medikamenten-Vergleich
- `type=custom&period1_from=...&period2_to=...` – Benutzerdefinierte Perioden

#### Export-Formate (`/report/export/excel`)
Query-Parameter `format`:
- `detailed` – Alle Felder als CSV
- `summary` – Zusammenfassung mit Durchschnittswerten
- `doctor` – Formatierter Arzt-Report mit allen relevanten Informationen

### Benachrichtigungen (NEU)
| Methode | Endpunkt | Beschreibung |
|---------|----------|--------------|
| GET | `/notifications/settings` | Benachrichtigungs-Einstellungen abrufen |
| PUT | `/notifications/settings` | Einstellungen aktualisieren |
| POST | `/notifications/push/subscribe` | Push-Benachrichtigungen aktivieren |
| POST | `/notifications/push/unsubscribe` | Push-Benachrichtigungen deaktivieren |
| POST | `/notifications/email/verify` | E-Mail-Adresse verifizieren |
| POST | `/notifications/email/resend-verification` | Verifizierungs-E-Mail erneut senden |
| GET | `/notifications/status` | Benachrichtigungs-Status abrufen |

#### Push-Benachrichtigungen
- **Morgens, Mittags, Abends**: Konfigurierbare Erinnerungszeiten
- **Web Push API**: Funktioniert auch bei geschlossener App
- **VAPID-Authentifizierung**: Sichere Server-zu-Browser-Kommunikation
- Benötigt VAPID-Keys in `.env`:
  ```
  VAPID_PUBLIC_KEY=your_public_key
  VAPID_PRIVATE_KEY=your_private_key
  ```

#### E-Mail-Benachrichtigungen
- **Wöchentlicher Digest**: Zusammenfassung der Woche für Eltern
- **Fehlende Einträge Alert**: Erinnerung nach X Tagen ohne Eintrag
- E-Mail-Adresse muss verifiziert werden

#### Notification Worker (Cron-Job)
Der Worker (`scripts/notification-worker.php`) verarbeitet geplante Benachrichtigungen:
```bash
# Alle 5 Minuten ausführen
*/5 * * * * php /path/to/scripts/notification-worker.php
```

### Glossar/Hilfe-Inhalte (Erweitert)
Die Glossar-API ermöglicht die Wiederverwendung von Hilfe-Inhalten in anderen Anwendungen und Layouts.

| Methode | Endpunkt | Beschreibung |
|---------|----------|--------------|
| GET | `/glossary` | Lexikon-Einträge mit Filtern |
| GET | `/glossary/categories` | Alle verfügbaren Kategorien |
| GET | `/glossary/export` | Export als JSON oder CSV |
| POST | `/glossary/import` | Import aus HTML-Dateien (Admin) |
| GET | `/glossary/{slug}` | Einzelner Eintrag mit vollem Inhalt |

#### Query-Parameter für `/glossary`
| Parameter | Beschreibung | Beispiel |
|-----------|--------------|----------|
| `category` | Filter nach Kategorie | `?category=Wissen` |
| `audience` | Filter nach Zielgruppe | `?audience=eltern` |
| `search` | Volltextsuche | `?search=medikament` |
| `format` | Ausgabeformat (list, full, plain) | `?format=plain` |
| `limit` | Max. Anzahl | `?limit=10` |
| `offset` | Pagination-Offset | `?offset=20` |

#### Ausgabeformate
- **list** (Standard): Kompakte Liste mit Titel, Kurztext, Link
- **full**: Alle Felder inkl. HTML und strukturierte Abschnitte
- **plain**: Nur Plaintext-Version ohne HTML

#### Zielgruppen (`audience`)
`eltern`, `kinder`, `erwachsene`, `lehrer`, `aerzte`, `alle`

#### Export (`/glossary/export`)
- `?format=json` – JSON-Export (Standard)
- `?format=csv` – CSV für Excel/Tabellenkalkulationen
- `?include=slug,title,content` – Nur bestimmte Felder

### Tags & Badges
| Methode | Endpunkt | Beschreibung |
|---------|----------|--------------|
| GET | `/tags` | Tags auflisten |
| POST | `/tags` | Tag anlegen |
| DELETE | `/tags/{id}` | Tag löschen |
| GET | `/badges` | Badges & Fortschritt |
| GET | `/badges/rank` | Aktueller Rang und Punkte |
| GET | `/weight` | Gewichtsverlauf |

##  Installation & Setup (Lokal)

### Voraussetzungen
- PHP 7.4 oder höher
- MySQL oder MariaDB
- Webserver (Apache, Nginx oder PHP Built-in Server)

### Schritte

1. **Repository klonen**
   ```bash
   git clone https://github.com/DEIN_USER/fokuslog-app.git
   cd fokuslog-app
   ```

2. **Datenbank einrichten**
   - Erstelle eine leere Datenbank (z. B. `fokuslog_dev`).
   - Importiere das Schema:
     ```bash
     mysql -u root -p fokuslog_dev < db/schema_v4.sql
     ```
   - (Optional) Importiere Testdaten:
     ```bash
     mysql -u root -p fokuslog_dev < db/seed.sql
     ```

3. **Konfiguration**
   - Kopiere die Beispiel-Konfiguration:
     ```bash
     cp .env.example .env
     ```
   - Bearbeite `.env` und trage deine Datenbank-Zugangsdaten ein.

4. **Hilfe-Inhalte importieren**
   - Damit das Lexikon funktioniert, müssen die HTML-Inhalte in die Datenbank importiert werden:
     ```bash
     # Normaler Import (nur geänderte Dateien)
     php app/help/import_help.php
     
     # Alle Dateien neu importieren
     php app/help/import_help.php --force
     
     # Nur simulieren (keine DB-Änderungen)
     php app/help/import_help.php --dry-run
     ```
   - Das Skript extrahiert automatisch:
     - Vollständigen HTML-Inhalt
     - Reinen Text (ohne HTML) für Previews
     - Strukturierte Abschnitte (alltag/wissen)
     - Keywords aus Überschriften
     - Zielgruppen-Erkennung
     - Geschätzte Lesezeit

5. **Starten**
   - Nutze den PHP Built-in Server für die Entwicklung:
     ```bash
     php -S localhost:8000
     ```
   - Öffne `http://localhost:8000` im Browser.

## 🚢 Deployment

Das Projekt enthält einen einfachen Webhook für Deployment via Git (`api/deploy.php`).
Dafür muss ein `DEPLOY_TOKEN` in der `.env` gesetzt sein.

Bei jedem Deployment werden automatisch:
1. `git fetch` und `git reset --hard origin/HEAD` ausgeführt
2. Die `.env` Datei gesichert und wiederhergestellt
3. Hilfe-Inhalte in die `glossary`-Tabelle importiert (Change-Detection via File-Hash)

### Deployment-Response
```json
{
  "success": true,
  "message": "Deployment erfolgreich",
  "migrations": ["..."],
  "help_import": ["Help Import: 5 importiert, 2 aktualisiert, 40 übersprungen, 0 gelöscht"],
  "commit": "abc1234",
  "timestamp": "2026-02-08 12:00:00"
}
```

### Bootstrap-Skript
Für lokale Setups oder CI/CD kann auch `scripts/bootstrap.php` verwendet werden:
```bash
php scripts/bootstrap.php --with-seed --with-tests
```
Optionen: `--skip-help`, `--skip-migrations`, `--dry-run`, `--help`

## 🤝 Mitmachen

Beiträge sind willkommen! Bitte beachte unsere CONTRIBUTING.md und das Leitbild.

## 📄 Lizenz

Dieses Projekt ist lizenziert unter der **CC BY-NC-SA 4.0** (Namensnennung - Nicht-kommerziell - Weitergabe unter gleichen Bedingungen).