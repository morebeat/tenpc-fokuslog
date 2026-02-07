# FokusLog

**Beobachten statt bewerten.**

FokusLog ist eine datenschutzfreundliche Progressive Web App (PWA) zur Dokumentation von ADHS-Symptomen, Medikation und Nebenwirkungen. Sie hilft Familien, Lehrkräften und therapeutischem Fachpersonal, Verläufe objektiv zu betrachten, ohne vorschnell zu urteilen.

## 🚀 Features

- **Datenschutz-First**: Keine Tracker, keine Cloud-Pflicht, volle Datenhoheit.
- **PWA**: Installierbar auf Smartphones, offline-fähig (Kernfunktionen).
- **Rollenbasiert**: Ansichten für Eltern, Kinder, Lehrkräfte und Erwachsene.
- **Gamification**: Motivierende Elemente für Kinder (Streaks, Badges).
- **Berichte**: PDF-Export für Arztgespräche.
- **Wissen**: Integriertes Lexikon und Hilfebereich.

## 🛠 Tech Stack

- **Frontend**: Vanilla JavaScript (ES6+), CSS3, HTML5. Keine Build-Tools notwendig.
- **Backend**: PHP 7.4+ (REST API).
- **Datenbank**: MySQL / MariaDB.
- **Libraries**: Chart.js (Visualisierung), jsPDF (Berichte), canvas-confetti (Gamification).

## 📦 Installation & Setup (Lokal)

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
     php app/help/import_help.php
     ```

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
1. `git pull` ausgeführt.
2. Die `.env` Datei geschützt.
3. Neue Hilfe-Inhalte in die Datenbank importiert.

## 🤝 Mitmachen

Beiträge sind willkommen! Bitte beachte unsere CONTRIBUTING.md und das Leitbild.

## 📄 Lizenz

Dieses Projekt ist lizenziert unter der **CC BY-NC-SA 4.0** (Namensnennung - Nicht-kommerziell - Weitergabe unter gleichen Bedingungen).