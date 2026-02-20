# ADR-003: PHP Sessions statt JWT für Authentifizierung

**Status:** Akzeptiert
**Datum:** 2025-01 (ursprüngliche Entscheidung)
**Zuletzt geprüft:** 2026-02

---

## Kontext

FokusLog benötigt Authentifizierung für ein mehrschichtiges Benutzermodell
(Parent, Child, Teacher, Adult). Optionen: PHP Native Sessions, JWT (JSON Web Tokens)
oder Session-basierte Tokens in DB.

---

## Entscheidung

**PHP Native Sessions** mit sicheren Cookie-Parametern:
```php
session_set_cookie_params([
    'lifetime' => 0,          // Session-Cookie (Browser-Schließen = Logout)
    'path'     => '/',
    'secure'   => $isSecure,  // HTTPS-only in Produktion
    'httponly' => true,        // Kein JavaScript-Zugriff
    'samesite' => 'Strict',   // CSRF-Schutz
]);
```

---

## Begründung

### Warum kein JWT?

**Probleme mit JWT für diesen Anwendungsfall:**
1. **Kein echtes Logout** — JWTs können nicht revoked werden ohne Token-Blacklist (= Zustand auf Server)
2. **Passwortänderung** — Nach Passwortänderung sind alte JWTs noch gültig bis Ablauf
3. **Rollen-Wechsel** — Wenn Elternteil Rolle ändert, gilt alter JWT noch
4. **Komplexität** — Refresh-Token-Handling, Token-Rotation, Blacklist-Verwaltung
5. **Shared Hosting** — Kein Redis/Memcached für Blacklist verfügbar

### Warum PHP Sessions?

- **Sofortiger Logout:** `session_destroy()` löscht Session serverseitig
- **Einfachheit:** PHP hat eingebauten Session-Support, kein Setup nötig
- **CSRF-Schutz:** `SameSite=Strict` verhindert Cross-Site-Angriffe
- **Shared Hosting:** Funktioniert ohne externe Dienste

### Sicherheitsmaßnahmen

- `HttpOnly`: Session-Cookie nicht per JS lesbar (XSS-Schutz)
- `Secure`: Nur über HTTPS (in Produktion)
- `SameSite=Strict`: Verhindert CSRF
- Session-ID-Regeneration nach Login (implizit durch PHP-Session-System)
- Rate Limiting auf Login/Register/ChangePassword (5–10/min per IP)

---

## Konsequenzen

**Positiv:**
- Sofortiger Logout möglich
- Einfache Implementierung
- Passwortänderung invalidiert automatisch andere Sessions
- Kein Token-Handling im Frontend

**Negativ:**
- Nicht stateless — Server muss Session-Dateien speichern
- Skalierung auf mehrere Server erfordert Shared Session Storage
- Mobile-Apps oder externe API-Clients können keine Sessions nutzen

---

## Revisionshinweis

Falls FokusLog eine öffentliche REST-API für Mobile-Apps oder Drittanbieter anbieten
soll, wird JWT oder OAuth 2.0 nötig. Für die aktuelle Web-Only-Anwendung ist
PHP-Sessions die einfachere und sicherere Wahl.
