# Repository-Neuaufsetzung & Hardening-Plan (Februar 2026)

## A. Zielsetzung
Dieses Dokument beschreibt, wie das bisherige FokusLog-Repository trotz enthaltener Produktiv-Credentials sicher neu aufgesetzt wird. Es kombiniert eine technische Schritt-für-Schritt-Anleitung, Automatisierungsskripte und organisatorische Best Practices, damit das Projekt langfristig als gepflegte Open-Source-Codebasis betrieben werden kann.

## B. Vorbereitende Maßnahmen (Pflicht)
1. **Credential-Inventur**
   - `.env`, Datenbank-Dumps, Backup-Archive, CI-Secrets, Third-Party-Keys identifizieren.
   - Liste führen, welche Secrets kompromittiert wurden.
2. **Rotation & Sperrung**
   - Datenbank-Passwörter, Tokens (z. B. Backups, Migration-API), OAuth-Keys deaktivieren und neu ausstellen.
   - Revoke-Logs aufbewahren.
3. **Forensik & Backups**
   - Letzten Stand in ein separates, nicht öffentliches Archiv sichern (`git clone --mirror` in isoliertes Repo).
   - Prüfen, ob produktive Daten (CSV/PDF-Ausgaben, Dump-Dateien) mit eingecheckt wurden und entfernen.
4. **Kommunikation**
   - Maintainer-Team informieren, ggf. Nutzer:innen über Credential-Rotation unterrichten.

## C. Automatisierte Neuaufsetzung
Nach der Bereinigung helfen folgende Schritte:

1. **Cleanup-Kopie erstellen**
   - Skript `scripts/repo-reinit.sh` (Linux/macOS) oder `scripts/repo-reinit.ps1` (Windows PowerShell) ausführen.
   - Parameter: Quellpfad, Zielordner, neuer Remote-URL, optionale Exclude-Liste.
   - Ergebnis: neue, saubere Arbeitskopie ohne `.git`, `.env`, `logs/`, `backups/`, `vendor-cache/` u. Ä.

2. **Neues Git-Repository initialisieren**
   - `cd <target>`
   - `git init --initial-branch=main`
   - `.env.example` gegenprüfen, dass keine Secrets mehr enthalten sind.
   - `git add . && git commit -m "Initial sanitize import"`.
   - `git remote add origin <neues GitHub Repo>`.

3. **Automatisierter Projekt-Setup**
   - In neuer Kopie `php scripts/bootstrap.php --create-db --with-seed --skip-help` aufrufen, um Schema v4 einzuspielen (CI & Docker berücksichtigen).
   - Help-/Glossar-Import erst wieder aktivieren, nachdem DOM-Extension bereitsteht (`--skip-help` entfernen).

4. **Push & Schutzmaßnahmen**
   - `git push -u origin main`.
   - GitHub-Branch-Protection (Review-Pflicht, Status Checks) aktivieren.
   - Repository-Secrets konfigurieren (z. B. `DB_HOST`, `MIGRATION_TOKEN`).

## D. Manuelle Nacharbeiten
1. `.env.example`/`README.md` final auf neue Setup-Schritte anpassen.
2. CI/CD (z. B. GitHub Actions) reinitialisieren, vorhandene Workflows auf neue Secrets referenzieren.
3. Optional: GitHub Security Scans aktivieren (Secret Scanning, Dependabot, CodeQL).
4. Archiv des alten Repos privat halten oder zerstören, sobald alle Credentials rotiert sind.

## E. Ordnerstruktur – Bewertung & Empfehlungen
| Ordner/Datei | Einschätzung | Maßnahme |
| --- | --- | --- |
| `api/` | zentraler PHP-Backend-Einstieg; notwendig | beibehalten, `api/index.php` ist "single entry point" |
| `app/` | statische PWA inkl. Hilfe-Bereich | beibehalten; Unterordner `help/` regelmäßig mit Import-Skript synchronisieren |
| `assets/` (duplizierte Hilfe-Dateien) | teilw. Legacy-Kopien | prüfen, ob Inhalte aus `assets/help*` noch referenziert werden; ansonsten archivieren |
| `vendor/` | lokale JS-Libraries (Chart.js, jsPDF) | notwendig für Offline/PWA; ggf. Versionshinweis dokumentieren |
| `db/` | Schemata, Seeds, Migrationen | `schema_v4.sql` künftig als Single-Source, ältere Schemas in `archive/` verschieben |
| `docs/` | dokumentarische Quelle | neue Guides (dieses Dokument, Architektur) dort bündeln |
| `scripts/` | Wartung & Deployment | `repo-reinit.*` und `bootstrap.php` hier; Redundanzen (z. B. alte Update-Skripte) prüfen |
| `logs/`, `backups/` | Laufzeitverzeichnisse | nicht einchecken; per `.gitignore` schützen |

Empfehlung: Eine `ARCHIVE.md` führen, sobald Ordner entfernt wurden, um Kontext zu behalten.

## F. Best Practices für Open-Source-Pflege
1. **Secret Management**: `.env` nie einchecken, stattdessen `.env.example` pflegen. GitHub Secret Scanning aktivieren.
2. **Branch-Strategie**: `main` als geschützten Release-Branch, Feature-Branches via Pull Requests + Reviews.
3. **Automatisierte Tests**: `scripts/bootstrap.php --with-tests` in CI laufen lassen (mindestens auf PR-Ebene).
4. **Issue & PR Templates**: in `.github/` strukturieren, Labeling vereinheitlichen.
5. **Security Policy**: `SECURITY.md` bereitstellen, Responsible Disclosure Regeln festhalten.
6. **Changelog & Versionierung**: SemVer + `CHANGELOG.md` oder GitHub Releases.
7. **Documentation-First**: Änderungen an API oder Datenmodell immer in `docs/` widerspiegeln; README kurz halten, tiefe Infos in separaten Guides.
8. **Regular Dependency Review**: JS-Libs & PHP-Version im Blick behalten, CVE-Monitoring.

## G. Umsetzung in drei Phasen
1. **Phase 1 – Sofort (Tag 0–1)**
   - Credential-Inventur, Rotation, alte Repo-Kopie sichern.
   - Skripte `repo-reinit.*` prüfen.
2. **Phase 2 – Neuaufsetzen (Tag 1–2)**
   - Skript ausführen, sauberes Repo erzeugen, `bootstrap.php` laufen lassen, Tests verifizieren.
   - README & `.env.example` finalisieren, neue Remote pushen.
3. **Phase 3 – Hardening & Community (Tag 2+)**
   - Branch Protection, Issue-Templates, Security-Policy, regelmäßige Releases.
   - Optionale CI-Workflows (Linting, Secret Scan, Deploy) ergänzen.

Mit diesem Plan entstehen klare Verantwortlichkeiten und reproduzierbare Schritte, um FokusLog ohne Legacy-Secrets weiterzuentwickeln.
