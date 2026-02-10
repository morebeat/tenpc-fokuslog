# Changelog

All notable changes to FokusLog are documented here.
Format loosely based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [Unreleased] — 2026-02-10

### Architektur-Review & P0/P1-Refactoring

Vollständige Überprüfung der Codebasis, gefolgt von der Umsetzung aller
kritischen (P0) und hochprioren (P1) Maßnahmen.

---

### Neu hinzugefügt

#### `api/lib/EnvLoader.php` (neu)
- Eigener `.env`-Parser ersetzt `parse_ini_file()`.
- Unterstützt Sonderzeichen (`!`, `@`, `#`, …) in unquotierten Werten,
  Single- und Double-Quotes, `export`-Präfix, Inline-`#`-Kommentare.
- **Hintergrund**: `parse_ini_file()` warf PHP-Warnings für
  `DEPLOY_TOKEN=diesisteindepl0ymentToken!` und triggerte eine
  Endlosschleife im Error-Log (`php_error.log`), weil der Fallback
  auf `.env-dev` eine fehlende `DB_HOST`-Variable produzierte.

#### `api/lib/Validator.php` (neu)
- Zentrale Input-Validierungsklasse mit `ValidationException`.
- Methoden: `string`, `stringOptional`, `int`, `intOptional`,
  `enum`, `enumOptional`, `date`, `dateOptional`,
  `emailOptional`, `ratingOptional`.
- Eingesetzt in `AuthController` für Register- und Passwort-Endpunkte.

#### `db/schema_v3.sql` — Tabelle `notification_settings`
- Vollständige Tabellendefinition für Push- und E-Mail-Benachrichtigungen
  (war im Schema noch nicht vorhanden, aber im Controller bereits genutzt).
- Felder: `push_enabled`, `push_subscription` (VAPID-JSON, TEXT),
  Zeitslot-Konfiguration, `email`, `email_verified`,
  `email_verification_token`, `email_weekly_digest`, `email_missing_alert`.
- Abschnittsnummern 4–8 angepasst (neuer Abschnitt als §4 eingefügt).

---

### Geändert

#### `api/index.php`
- `.env`-Laden auf `EnvLoader::load()` umgestellt (war `parse_ini_file()`).
- Fehlerfall wirft jetzt `RuntimeException` und antwortet mit HTTP 500
  inkl. erwartetem Pfad — statt stiller Degradation auf `.env-dev`.

#### `api/RateLimiter.php`
- **Race Condition behoben**: `file_put_contents()` ohne Lock durch
  `fopen('c+') + flock(LOCK_EX) + rewind + ftruncate + fwrite`
  ersetzt — atomar, konkurrenzsicher.
- **Lesepfad gesichert**: Neues `readLocked()` mit `flock(LOCK_SH)`.
- **Neue Methode `reset(string $ip)`**: Zähler nach erfolgreichem Login
  zurücksetzen — verhindert falsch-positives Sperren.
- Rate Limiting jetzt auch auf `/register` (10/min) und
  `/changePassword` (5/min) aktiv.

#### `api/lib/Controller/BaseController.php`
- **`MIN_PASSWORD_LENGTH = 8`** (öffentliche Konstante): Passwortlänge
  einheitlich in Register und Passwortänderung; war zuvor inkonsistent
  (Register: 8, changePassword: 6).
- **Request-Scoped User-Cache**: `$cachedUser`-Property +
  überarbeitetes `currentUser()` speichert DB-Ergebnis für die
  Laufzeit des Requests — kein doppelter DB-Query mehr pro Request.
- **`clearUserCache()`**: Invalidiert den Cache nach eigenem
  Profilupdate.
- `currentUser()` selektiert jetzt explizite Spalten statt `SELECT *`
  und filtert zusätzlich auf `is_active = 1`.

#### `api/lib/Controller/AuthController.php`
- **Register**: Validator-Integration für `account_type`, `username`,
  `password`, `family_name`; Rate Limiting (10 Versuche/60 s).
- **Login**: `$limiter->reset($ip)` nach erfolgreichem Login.
- **`changePassword`**: `MIN_PASSWORD_LENGTH`-Konstante genutzt (8,
  war 6); Rate Limiting (5 Versuche/60 s).
- **`me()`**: Von 5 auf 2 DB-Queries reduziert — `has_entries` und
  `has_medications` werden als korrelierte Subqueries in einem
  einzigen `SELECT` abgefragt.

#### `api/lib/Controller/NotificationsController.php`
- **Explizite SQL-Queries** ersetzen dynamische Feldnamen-Interpolation.
- UPDATE nutzt `COALESCE(:param, spaltenname)`-Pattern: `NULL`-Parameter
  überschreiben keine bestehenden Werte.
- INSERT mit vollständiger, fixer Spaltenliste.

#### `app/js/app.js`
- **Modul-Timeout**: `injectScript()` bricht nach 10 s mit Fehler ab
  (war: kein Timeout, hängender Load möglich).
- **`showPageError(message)`**: Zeigt einen sichtbaren DOM-Banner mit
  `role="alert"` bei Seiten-Ladefehlern statt stiller `console.error`.
- **Logout-Fehler**: `catch (error)` → `catch {}` — Redirect passiert
  immer, Fehler werden nicht mehr unbeabsichtigt aufgefangen.

---

### Entfernt

#### `api/lib/Controller/badges.js`
- Leere (1-zeilige) JavaScript-Datei im PHP-Controller-Verzeichnis
  ohne Funktion entfernt.

---

### Technische Schulden / Bekannte Einschränkungen

- `mail()` in `NotificationsController::sendVerificationEmail()` ist
  ein Platzhalter — für Produktion sollte ein SMTP-Service
  (PHPMailer, Symfony Mailer) eingesetzt werden.
- `app.js` bleibt eine einzelne ~2000-Zeilen-Datei (P1-Aufgabe #8
  „Frontend modularisieren" ist noch offen).
- Keine Unit-Tests für Validator.php und EnvLoader.php (P1 #19).

---

## Ältere Versionen

Änderungen vor Februar 2026 sind in [P0_REFACTORING_COMPLETE.md](P0_REFACTORING_COMPLETE.md)
und [REFACTORING_ROADMAP.md](REFACTORING_ROADMAP.md) dokumentiert.
