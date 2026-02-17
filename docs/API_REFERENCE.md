# FokusLog — API Reference

Alle Requests an `/api` (rewritten zu `api/index.php`).
**Content-Type**: `application/json` für alle POST/PUT-Requests.
**Authentifizierung**: PHP Session Cookie (`session_id`).

---

## Authentifizierung

### `POST /register`
Registriert eine neue Familie (Parent) oder einen Einzelnutzer (Adult).

**Request:**
```json
{
  "account_type": "family",
  "username": "max_mustermann",
  "password": "sicheresPasswort123",
  "family_name": "Familie Mustermann"
}
```
`account_type`: `"family"` | `"individual"`

**Response 201:**
```json
{ "message": "Registrierung erfolgreich" }
```

**Fehler:** `400` (Validierung), `409` (Username belegt), `429` (Rate Limit)

---

### `POST /login`
Anmelden. Startet eine Session.

**Request:**
```json
{ "username": "max_mustermann", "password": "sicheresPasswort123" }
```

**Response 200:**
```json
{ "message": "Anmeldung erfolgreich", "user": { "id": 1, "username": "max_mustermann", "role": "parent" } }
```

**Fehler:** `401` (Ungültige Anmeldedaten), `429` (Rate Limit)

---

### `POST /logout`
Beendet die Session.

**Response 204** (kein Body)

---

### `GET /me`
Gibt aktuellen Nutzer mit Gamification-Stats zurück.

**Response 200:**
```json
{
  "user": {
    "id": 1, "username": "max_mustermann", "role": "parent",
    "points": 150, "streak_current": 7, "streak_longest": 14,
    "last_entry_date": "2026-02-10"
  },
  "badges": [ { "id": 1, "name": "Erste Woche", "icon_class": "badge-week" } ],
  "family": { "member_count": 3, "has_medications": true, "has_entries": true }
}
```

---

### `POST /users/me/password`
Ändert das eigene Passwort.

**Request:**
```json
{ "current_password": "altes123", "new_password": "neuesPasswort456" }
```

**Response 200:** `{ "message": "Passwort geändert" }`

**Fehler:** `400` (zu kurz, min. 8 Zeichen), `403` (falsches aktuelles Passwort), `429` (Rate Limit)

---

## Benutzer

### `GET /users`
Listet alle Familienmitglieder auf. Nur für Parent/Adult.

**Response 200:**
```json
{ "users": [ { "id": 2, "username": "kind1", "role": "child", "is_active": true } ] }
```

---

### `POST /users`
Erstellt ein Kind- oder Lehrer-Konto. Nur für Parent/Adult.

**Request:**
```json
{ "username": "kind1", "password": "passwort123", "role": "child" }
```
`role`: `"child"` | `"teacher"`

**Response 201:** `{ "message": "Benutzer angelegt", "user_id": 5 }`

---

### `PUT /users/{id}`
Aktualisiert Benutzerdaten. Nur für Parent/Adult.

**Request:** Felder die geändert werden sollen (alle optional):
```json
{ "username": "neuer_name", "is_active": false }
```

**Response 200:** `{ "message": "Benutzer aktualisiert" }`

---

### `DELETE /users/{id}`
Löscht Nutzer. Nur wenn keine Einträge vorhanden.

**Response 204** oder `409` (Einträge vorhanden)

---

## Einträge (Tagebuch)

### `GET /entries`
Gibt Einträge zurück. Unterstützt Pagination (neu) und Legacy-Limit.

**Query-Parameter:**

| Parameter | Typ | Default | Beschreibung |
|-----------|-----|---------|-------------|
| `date_from` | `YYYY-MM-DD` | — | Von-Datum (inkl.) |
| `date_to` | `YYYY-MM-DD` | — | Bis-Datum (inkl.) |
| `time` | `morning\|noon\|evening` | — | Nur dieser Zeitslot |
| `user_id` | int | eigene ID | Nur für Parent/Adult: anderer Nutzer in Familie |
| `page` | int | `1` | Seite (Pagination) |
| `per_page` | int | `50` | Einträge pro Seite (max. 200) |
| `limit` | int | — | Legacy: feste Anzahl (deaktiviert Pagination) |

**Response 200 (mit Pagination):**
```json
{
  "entries": [
    {
      "id": 42, "date": "2026-02-10", "time": "morning",
      "medication_name": "Ritalin", "dose": "10mg",
      "mood": 4, "focus": 3, "sleep": 5, "appetite": 2,
      "hyperactivity": 3, "irritability": 2,
      "tags": "Sport, Schule", "username": "kind1"
    }
  ],
  "pagination": { "total": 123, "page": 1, "per_page": 50, "pages": 3 }
}
```

**Cache:** ETag + `Cache-Control: private, max-age=60` — bei unverändertem ETag kommt HTTP 304.

---

### `POST /entries`
Erstellt oder aktualisiert einen Eintrag (Upsert per `(user_id, date, time)`).

**Request:**
```json
{
  "date": "2026-02-10",
  "time": "morning",
  "medication_id": 1,
  "dose": "10mg",
  "mood": 4, "focus": 3, "sleep": 5,
  "hyperactivity": 2, "irritability": 2, "appetite": 3,
  "weight": "35.5",
  "side_effects": "Kopfschmerzen",
  "tags": [1, 3]
}
```

**Response 201:**
```json
{
  "message": "Eintrag gespeichert",
  "gamification": {
    "points_earned": 10, "total_points": 160,
    "streak": 8,
    "new_badges": [],
    "next_badge": { "name": "Monatsheld", "required_streak": 30, "days_left": 22 }
  }
}
```

---

### `DELETE /entries/{id}`
Löscht einen Eintrag. Nur eigene Einträge oder als Parent.

**Response 204**

---

## Medikamente

### `GET /medications`
Listet Familien-Medikamente.

**Response 200:** `{ "medications": [ { "id": 1, "name": "Ritalin", "is_active": true } ] }`

---

### `POST /medications`
Fügt Medikament hinzu. Parent/Adult only.

**Request:** `{ "name": "Ritalin", "default_dose": "10mg" }`

**Response 201:** `{ "message": "Medikament angelegt", "id": 3 }`

---

### `PUT /medications/{id}`
Aktualisiert Medikament.

### `DELETE /medications/{id}`
Löscht Medikament (nur wenn keine Einträge).

---

## Tags

| Methode | Endpoint | Beschreibung |
|---------|---------|-------------|
| `GET` | `/tags` | Tags der Familie |
| `POST` | `/tags` | Neuen Tag anlegen: `{ "name": "Schule" }` |
| `DELETE` | `/tags/{id}` | Tag löschen |

---

## Badges & Gewicht

| Methode | Endpoint | Beschreibung |
|---------|---------|-------------|
| `GET` | `/badges` | Badges + Fortschritt des aktuellen Nutzers |
| `GET` | `/weight` | Gewichtsverlauf |
| `GET` | `/me/latest-weight` | Letzter Gewichtseintrag |

---

## Benachrichtigungen

### `GET /notifications/settings`
Benachrichtigungseinstellungen.

**Response 200:**
```json
{
  "settings": {
    "push_enabled": true,
    "push_morning": true, "push_morning_time": "08:00",
    "push_noon": false, "push_noon_time": "12:00",
    "push_evening": true, "push_evening_time": "18:00",
    "email": "user@example.com", "email_verified": true,
    "email_weekly_digest": true, "email_digest_day": 1,
    "email_missing_alert": false, "email_missing_days": 3
  }
}
```

---

### `PUT /notifications/settings`
Aktualisiert Einstellungen (nur gesendete Felder werden geändert).

---

### `POST /notifications/push/subscribe`
Speichert VAPID-Push-Subscription.

**Request:** `{ "subscription": { /* Web Push Subscription Objekt */ } }`

---

### `POST /notifications/push/unsubscribe`
Deaktiviert Push. Löscht Subscription aus DB.

---

### `POST /notifications/email/verify`
Verifiziert E-Mail per Token.

**Request:** `{ "token": "abc123..." }`

---

### `POST /notifications/email/resend-verification`
Sendet Verifikations-E-Mail erneut.

---

### `GET /notifications/status`
Dashboard-Status (fehlende Slots, Push/Email aktiv).

**Response 200:**
```json
{
  "last_entry_date": "2026-02-09",
  "days_since_entry": 1,
  "today_missing_slots": ["noon", "evening"],
  "notifications": { "push_enabled": true, "email_enabled": false }
}
```

---

### `GET /notifications/vapid-key`
VAPID Public Key für Push-Subscription-Setup.

**Response 200:** `{ "vapid_public_key": "BFgt..." }`

---

## Reports & Analytics

### `GET /report/trends`
Trendanalyse mit Pattern-Erkennung. Gibt Warnungen, Insights und Statistiken zurück.

**Query:** `date_from`, `date_to`, `user_id` (Parent)

**Response 200:** Warnungen (`appetite`, `mood`, `sleep`, `irritability`, `weight`, `side_effects`) + Statistiken (Mittelwerte, Streaks).

---

### `GET /report/compare`
Perioden- oder Medikamentenvergleich.

**Query-Parameter:**

| Parameter | Wert |
|-----------|------|
| `type` | `week` \| `medication` \| `custom` |
| `med1`, `med2` | Medikament-IDs (bei `type=medication`) |
| `period1_from` … `period2_to` | Datumsangaben (bei `type=custom`) |

---

### `GET /report/summary`
Zusammenfassungs-Daten für PDF-Export.

---

### `GET /report/export/excel`
CSV/Excel-Export.

**Query:** `format` = `detailed` \| `summary` \| `doctor`

---

## System

### `GET /health`
Health-Check für CI/CD und Load Balancer.

**Response 200 (ok):**
```json
{ "status": "ok", "timestamp": 1707566400, "php_version": "8.2.0", "database": "connected" }
```

**Response 503 (degraded):**
```json
{ "status": "degraded", "timestamp": 1707566400, "php_version": "8.2.0", "database": "error: ..." }
```

---

## Fehler-Codes

| Code | Bedeutung |
|------|----------|
| 200 | OK |
| 201 | Created |
| 204 | No Content (z.B. Logout, Delete) |
| 304 | Not Modified (ETag-Match) |
| 400 | Bad Request (Validierungsfehler) |
| 401 | Unauthorized (nicht angemeldet) |
| 403 | Forbidden (keine Berechtigung) |
| 404 | Not Found |
| 409 | Conflict (Duplikat, Lösch-Einschränkung) |
| 429 | Too Many Requests (Rate Limit) |
| 500 | Internal Server Error |
| 503 | Service Unavailable (DB down) |

Alle Fehler-Responses haben das Format:
```json
{ "error": "Beschreibung des Fehlers" }
```
