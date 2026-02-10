# ADR-001: Vanilla PHP ohne Framework

**Status:** Akzeptiert
**Datum:** 2025-01 (ursprüngliche Entscheidung)
**Zuletzt geprüft:** 2026-02

---

## Kontext

FokusLog ist eine datenschutzsensible Anwendung für Familien und Kinder, die auf
selbst-gehostetem LAMP/LEMP-Stack betrieben wird. Bei Projektstart stand die Wahl:
Vanilla PHP, Laravel, Symfony oder Slim.

---

## Entscheidung

**Vanilla PHP (kein Framework)** mit eigenem Router, PDO und MVC-ähnlicher
Controller-Struktur.

---

## Begründung

| Kriterium | Vanilla PHP | Laravel/Symfony |
|-----------|------------|-----------------|
| **Abhängigkeiten** | Keine Composer-Deps | Composer, 100+ Pakete |
| **Performance** | Minimaler Overhead | Framework-Bootstrap ~50–200 ms |
| **Verständlichkeit** | Jeder PHP-Kenner liest den Code | Framework-Spezifisches Wissen nötig |
| **Sicherheit (Supply Chain)** | Kein Risiko durch fremde Pakete | Composer-Pakete können Sicherheitslücken einbringen |
| **Deployment** | `rsync` auf beliebigem Hoster | PHP-FPM-Konfiguration, Composer-Install nötig |
| **Shared Hosting** | Funktioniert überall | Oft Probleme mit CLI-Zugriff |

Das Projekt ist bewusst klein und fokussiert. Ein Framework würde die
Einstiegshürde für Contributions erhöhen und das Deployment komplizieren, ohne
einen proportionalen Mehrwert zu liefern.

---

## Konsequenzen

**Positiv:**
- Keine externen Abhängigkeiten (kein `composer.json`)
- Deployment per `rsync` oder FTP auf Shared Hosting
- Vollständige Kontrolle über Request-Lifecycle
- Schnell: kein Framework-Overhead

**Negativ:**
- ORM, Dependency Injection, Request/Response-Abstraktion müssen selbst gelöst werden
- Keine automatischen Security-Updates durch Framework-Maintainer
- Mehr Boilerplate für Routing, Middleware etc.

**Mitigationen:**
- Router (`lib/Router.php`) extrahiert und testbar gemacht
- BaseController mit gemeinsamen Methoden (auth, respond, audit log)
- PHPStan Level 5 für statische Analyse
- `declare(strict_types=1)` überall

---

## Revisionshinweis

Falls das Projekt auf >5 Entwickler wächst oder komplexe Job-Queues, Event-Sourcing
oder ORM benötigt, sollte ein Framework-Wechsel erneut evaluiert werden.
