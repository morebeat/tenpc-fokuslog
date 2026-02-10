# ADR-002: Modulares Vanilla-JS-Frontend (kein React/Vue)

**Status:** Akzeptiert
**Datum:** 2025-01 (ursprüngliche Entscheidung)
**Zuletzt geprüft:** 2026-02

---

## Kontext

Das Frontend ist eine PWA (Progressive Web App) mit mehreren HTML-Seiten.
Optionen für das Frontend-Framework waren: React, Vue.js, Svelte oder Vanilla JS.

---

## Entscheidung

**Vanilla JavaScript (ES6+)** mit einem zentralen Bootstrapper (`app.js`) und
seitenspezifischen Modulen in `app/js/pages/*.js`. Kein Build-System.

---

## Architektur

```
app/js/
├── app.js              # Bootstrapper: Routing, Auth-Check, Modul-Loading
├── i18n/
│   └── de.js           # Übersetzungs-Dictionary
└── pages/
    ├── auth.js         # Login & Register
    ├── dashboard.js    # Hauptseite
    ├── entry.js        # Eintrag erstellen/bearbeiten
    ├── report.js       # Auswertungen & Charts
    ├── notifications.js
    └── …               # weitere Module
```

Jedes Page-Modul exportiert in den `FokusLog.pages`-Namespace:
```js
(function(global) {
    const FokusLog = global.FokusLog;
    FokusLog.pages.dashboard = {
        async init(context) { /* Seiten-Logik */ }
    };
})(window);
```

Shared Utilities in `FokusLog.utils`:
- `apiCall()` — Fetch-Wrapper mit ApiError
- `toast()` — Non-blocking Benachrichtigungen
- `log()` / `error()` — Debug-Logger (nur wenn `FOKUSLOG_DEBUG`)
- `t()` — i18n-Lookup
- `poll()` — Polling-Utility

---

## Begründung

| Kriterium | Vanilla JS | React/Vue |
|-----------|-----------|-----------|
| **Build-System** | Keins nötig | Node.js, Webpack/Vite, npm |
| **Deployment** | Dateien direkt hochladen | Build-Step nötig |
| **Shared Hosting** | Überall lauffähig | Node.js auf Server nötig |
| **Bundle Size** | 0 KB Framework-Overhead | React min: ~44 KB, Vue min: ~35 KB |
| **Performance** | Nativ | Virtual DOM-Overhead |
| **Wartbarkeit** | Code ist was man sieht | JSX/Template-Syntax-Lernkurve |

Das Ziel ist eine PWA, die ohne Build-Pipeline auf einfachem Shared Hosting läuft.
Kein npm, kein node_modules, keine CI/CD-Abhängigkeit vom Build-System.

---

## Konsequenzen

**Positiv:**
- Kein Build-Step, direktes Deployment
- Funktioniert auf beliebigem PHP-Hoster
- Volle Kontrolle über Ladeverhalten (async Script-Injection, 10 s Timeout)

**Negativ:**
- Kein Reactive State Management (kein `v-model`, `useState`)
- DOM-Manipulation manuell
- Keine Type Safety für JS (kein TypeScript)

**Mitigationen:**
- `FokusLog.utils`-Namespace für geteilte Utilities
- `FokusLog.utils.apiCall()` als einheitlicher Fetch-Wrapper
- Strikte Modul-Trennung: 1 Datei = 1 Seite
- i18n-Foundation für spätere Mehrsprachigkeit

---

## Revisionshinweis

Wenn das Team wächst oder komplexe Client-State-Verwaltung nötig wird (z.B.
Offline-First mit komplizierter Sync-Logik), sollte Vue.js (No-Build mit CDN)
oder Petite-Vue evaluiert werden.
