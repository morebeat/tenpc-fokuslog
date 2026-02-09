# FokusLog - Project Documentation

## 1. Project Overview
**FokusLog** is a privacy-focused, minimalist web application designed as a medication and symptom diary for ADHD (ADHS). It enables families, individuals, and teachers to track medication intake, dosage, and various metrics (mood, focus, sleep, etc.) to optimize treatment in consultation with medical professionals.

### Key Features
- **Multi-Tenancy**: Supports "Families" (Parents/Children) and "Individual" (Adult) accounts.
- **Role-Based Access**: Distinct roles for Parents, Children, Teachers, and Adults.
- **Gamification**: Points, streaks, and badges system to motivate children.
- **Reporting**: Visual charts (Mood/Focus/Sleep/Weight), CSV export, and PDF generation.
- **Privacy**: GDPR (DSGVO) compliant design, local logging, and strict data separation.

---

## 2. Technical Architecture

### Backend
- **Language**: PHP 8.0+ (Vanilla, no framework).
- **Architecture**: MVC-style Controller pattern with Router.
- **Entry Point**: Single entry point via `api/index.php`, routing handled by `lib/Router.php`.
- **Controllers**: Modular controllers in `api/lib/Controller/` for each domain.
- **Database Access**: PDO (PHP Data Objects) with prepared statements.
- **Authentication**: PHP Native Sessions (`HttpOnly`, `Secure`, `SameSite=Strict`).
- **Logging**: Custom file-based logger (`api/lib/logger.php`) with sensitive data redaction.

### Frontend
- **Language**: Vanilla JavaScript (ES6+).
- **Logic**: `app/js/app.js` handles routing, API calls, and UI interactivity.
- **Libraries**:
  - `Chart.js`: For visualizing progress reports.
  - `jsPDF`: For generating PDF reports client-side.

### Database
- **System**: MySQL / MariaDB.
- **Schema**: Relational model defined in `db/schema.sql`.

---

## 3. Directory Structure

```text
fokuslog-app/
├── api/
│   ├── index.php           # API entry point & router config
│   ├── RateLimiter.php     # Rate limiting
│   └── lib/
│       ├── Router.php      # URL routing with parameter extraction
│       ├── EntryPayload.php
│       └── Controller/     # MVC Controllers
│           ├── BaseController.php
│           ├── AuthController.php
│           ├── UsersController.php
│           ├── MedicationsController.php
│           ├── EntriesController.php
│           ├── TagsController.php
│           ├── BadgesController.php
│           ├── WeightController.php
│           ├── GlossaryController.php
│           ├── ReportController.php   # Analytics & Exports
│           └── AdminController.php
├── app/
│   └── js/
│       ├── app.js          # Main frontend logic
│       └── pages/
│           └── report.js   # Report page with trends & comparisons
├── db/
│   └── schema.sql          # Database definition
├── docs/
│   └── dsgvo.md            # Privacy documentation
└── scripts/
    └── update_schema.sql   # Database migration scripts
```

---

## 4. Database Schema

### Core Tables
- **`families`**: Tenant root.
- **`users`**: Accounts belonging to a family.
  - Roles: `parent` (Admin), `child` (User), `teacher` (Write-only), `adult` (Self-managed).
- **`medications`**: List of available medications per family.
- **`entries`**: Daily logs.
  - Unique Constraint: `(user_id, date, time)` ensures max one entry per time slot (morning/noon/evening).
  - Metrics: 1-5 scales for sleep, hyperactivity, mood, irritability, appetite, focus.

### Gamification & Metadata
- **`badges`**: Definitions of earnable achievements (based on streaks).
- **`user_badges`**: Many-to-Many link between users and earned badges.
- **`tags`**: Custom tracking tags per family.
- **`entry_tags`**: Tags attached to specific entries.
- **`audit_log`**: Security log for critical actions (login, delete, etc.).

---

## 5. API Reference

All requests should be sent to `/api` (rewritten to `api/index.php`).
**Content-Type**: `application/json` (except GET).

### Authentication
| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `POST` | `/register` | Register a new family/parent or individual. |
| `POST` | `/login` | Authenticate and start session. |
| `POST` | `/logout` | Destroy session. |
| `GET` | `/me` | Get current user info & gamification stats. |
| `POST` | `/users/me/password` | Change own password. |

### User Management (Parent/Adult only)
| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/users` | List family members. |
| `POST` | `/users` | Create a new user (Child/Teacher). |
| `PUT` | `/users/{id}` | Update user details. |
| `DELETE` | `/users/{id}` | Delete user (if no entries exist). |

### Entries (Diary)
| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/entries` | Fetch entries. Params: `date_from`, `date_to`, `user_id` (Parent only). |
| `POST` | `/entries` | Create or Update (Upsert) an entry. |

### Medications
| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/medications` | List family medications. |
| `POST` | `/medications` | Add medication. |
| `PUT` | `/medications/{id}` | Update medication. |
| `DELETE` | `/medications/{id}` | Delete medication. |

### Metadata & Stats
| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/tags` | List tags. |
| `POST` | `/tags` | Create tag. |
| `DELETE` | `/tags/{id}` | Delete tag. |
| `GET` | `/badges` | List badges and progress. |
| `GET` | `/weight` | Get weight history. |
| `GET` | `/me/latest-weight` | Get most recent weight entry. |

### Reports & Analytics (NEW)
| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/report/trends` | Trend analysis with pattern detection. Returns warnings, insights, and statistics. |
| `GET` | `/report/compare` | Period or medication comparison. Params: `type` (week/medication/custom). |
| `GET` | `/report/summary` | Summary data for PDF reports. |
| `GET` | `/report/export/excel` | Excel/CSV export. Params: `format` (detailed/summary/doctor). |

#### Trend Analysis Response
The `/report/trends` endpoint automatically detects:
- **Appetite warnings**: 3+ consecutive days with low appetite (1-2)
- **Mood trends**: Declining or improving mood patterns
- **Sleep quality**: Below-average sleep scores
- **Irritability spikes**: Extended periods of high irritability
- **Weight loss**: >3% loss over the analysis period
- **Side effects**: Frequent documentation of side effects

#### Comparison Types
- `type=week`: Week-over-week comparison (default)
- `type=medication&med1=X&med2=Y`: Compare two medications
- `type=custom&period1_from=...&period2_to=...`: Custom period comparison

---

## 6. Frontend Logic (`app.js`)

The frontend is a Single Page Application (SPA)-like structure served via separate HTML files, but controlled by a unified `app.js`.

### Page Detection
The script detects the current page via `document.body.dataset.page`.

### Key Modules
1.  **Smiley UI**: Handles the 1-5 rating scales using radio buttons and CSS classes (`highlight`, `active`).
2.  **Gamification**:
    -   Checks `user.role === 'child'`.
    -   Displays Points, Streaks, and Badges in the dashboard.
    -   Alerts user upon earning points/badges after entry submission.
3.  **Reporting**:
    -   Fetches data via `/api/entries`.
    -   Renders charts using `Chart.js` (Mood/Focus/Sleep lines).
    -   Exports: CSV (Blob download) and PDF (`jsPDF` with autoTable).
4.  **Entry Form**:
    -   Loads medications and tags dynamically.
    -   Pre-fills data if an entry exists for the selected Date/Time (Edit mode).
    -   Handles "Upsert" logic transparently to the user.

---

## 7. Setup & Configuration

### Environment Variables
The application looks for a `.env` file in the parent directory of `api/`.

```ini
DB_HOST=localhost
DB_NAME=fokuslog
DB_USER=root
DB_PASS=secret
DEBUG_LOG=1
LOG_FILE=/path/to/app.log
```

### Installation
1.  Configure Web Server (Apache/Nginx) to serve the `app/` directory as public and route `/api` requests to `api/index.php`.
2.  Import `db/schema.sql` into your MySQL database.
3.  Ensure the `logs/` directory is writable by the web server user. Log files:
    - `logs/error.log` - PHP errors
    - `logs/app.log` - Application logs
    - `logs/deploy.log` - Deployment logs

---

## 8. Security & Business Rules

1.  **Data Isolation**: Every database query is scoped by `family_id` (extracted from the session) to prevent data leaks between tenants.
2.  **Teacher Restrictions**:
    -   Teachers can *create* entries for assigned children.
    -   Teachers *cannot* view historical entries (privacy).
    -   Teachers *cannot* see weight data.
3.  **Deletion Rules**:
    -   Users/Medications cannot be deleted if they have associated entries (Referential Integrity & Business Logic).
4.  **Future Entries**: Entries cannot be created for future dates.
5.  **Self-Modification**: Parents cannot change their own role or delete themselves via the User Management API (must use Account settings).

---

## 9. Code Snippets for AI Context

### Logging (PHP)
```php
app_log('INFO', 'action_name', ['user_id' => 1, 'details' => '...']);
// Automatically redacts sensitive keys like 'password', 'teacher_feedback'.
```

### Database Connection (PHP)
```php
$dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
$pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);
```

### Fetch Wrapper (JS)
Standard pattern used in `app.js`:
```javascript
const response = await fetch('/api/endpoint', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
});
```