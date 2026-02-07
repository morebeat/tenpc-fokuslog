# FokusLog - ADHD Medication Tracking PWA

[![License: CC BY-NC-SA 4.0](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-lightgrey.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Status: Actively Maintained](https://img.shields.io/badge/Status-Actively%20Maintained-brightgreen.svg)](https://github.com/)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)
![JavaScript ES6+](https://img.shields.io/badge/JavaScript-ES6%2B-yellow.svg)

**FokusLog** is a privacy-first, open-source Progressive Web App for documenting and optimizing ADHD medication adjustments. It's designed for children, parents, medical professionals, and teachers to collaborate safely without tracking, profiling, or external services.

## 🎯 What is FokusLog?

FokusLog is a **digital medication and observation diary** that helps:

- **Children & Teens**: Track how ADHD medication affects their day-to-day life
- **Parents**: Understand patterns and share data with doctors
- **Doctors**: Make informed medication adjustments based on real observations
- **Teachers**: Record classroom behavior and focus levels

### Key Features

- 📝 **Simple Daily Entries** - Rate mood, focus, sleep, and more on a 1-5 scale
- 📊 **Visual Reports** - Charts showing patterns over time
- � **Automatic Trend Analysis** - Detects patterns like appetite loss, mood changes, weight loss
- 📈 **Week-over-Week Comparisons** - See how metrics change over time
- 💊 **Medication Tracking** - Compare effectiveness across different medications
- 👨‍👩‍👧‍👦 **Family Management** - Parents manage multiple children securely
- 🎮 **Gamification** - Points, streaks, and badges motivate children to track consistently
- 🔒 **Privacy First** - GDPR compliant, no tracking, no external services
- 📱 **Progressive Web App** - Works on all devices, offline support
- 📄 **Multiple Export Formats** - PDF reports, Excel/CSV, Doctor-ready exports
- 🌍 **Multi-language** - German and English support

### What FokusLog is NOT

- ❌ **Not a diagnostic tool** - Cannot diagnose ADHD
- ❌ **Not a medical decision maker** - Doctors make final medication decisions
- ❌ **Not a surveillance system** - Designed to empower, not control
- ❌ **Not a therapy replacement** - Complements professional treatment

---

## 🚀 Quick Start

### For Users (60 seconds)

1. **Visit** the FokusLog website
2. **Register** a family or personal account (free)
3. **Create your first entry** - Rate your mood, focus, and sleep
4. **Watch your streak grow** - Earn badges for consistency
5. **Share with your doctor** - Export PDF reports anytime

📖 [Full User Guide](docs/USER_GUIDE.md)

### For Developers

```bash
# Clone repository
git clone https://github.com/[your-org]/fokuslog-app.git
cd fokuslog-app

# Create database
mysql -u root -p < db/schema.sql

# Configure environment
cp api/.env.example api/.env
nano api/.env  # Edit with your credentials

# Set permissions
chmod 755 api/ app/ db/

# Access in browser
# http://localhost/fokuslog-app/app/index.html
```

📖 [Full Installation Guide](docs/TECHNICAL_ARCHITECTURE.md#deployment-architecture)

### Automated Setup (Docker & CI)

Use [scripts/bootstrap.php](scripts/bootstrap.php) to provision the database, run migrations, sync the help glossary, and optionally execute API regression tests. The script is environment-agnostic, so the same command can run on bare metal, inside Docker containers, or inside CI jobs.

- **Local development**: `php scripts/bootstrap.php --create-db --with-seed` (applies [db/schema_v4.sql](db/schema_v4.sql) and loads [db/seed.sql](db/seed.sql)).
- **Docker**: `docker compose exec app php scripts/bootstrap.php --env .env.docker --with-seed` (runs inside the PHP container after `docker compose up`).
- **CI pipeline**: `php scripts/bootstrap.php --env .env.ci --create-db --with-tests --api-url=$CI_API_URL --skip-help` (runs schema + migrations, then calls [api/run_tests.php](api/run_tests.php) against the provided base URL).

Key flags:

- `--create-db` ensures the target schema exists (useful for ephemeral CI databases).
- `--with-seed` loads fixture data after the schema import.
- `--with-tests` triggers the PHP API test suite once the backend is up.
- `--skip-help` may be handy in minimal CI containers where DOM extensions are not available; otherwise the script invokes [app/help/import_help.php](app/help/import_help.php) so `/api/glossary` stays in sync.

---

## 📚 Complete Documentation

### For Users
- **[User Guide](docs/USER_GUIDE.md)** - Features, setup, FAQ, troubleshooting
- **[Privacy Policy](DATENSCHUTZERKLAERUNG.md)** - Your data rights (German)

### For Developers
- **[Technical Architecture](docs/TECHNICAL_ARCHITECTURE.md)** - System design, deployment, scaling
- **[API Documentation](docs/API_DOCUMENTATION.md)** - Complete API reference with examples
- **[Project Overview](PROJECT_DOCUMENTATION.md)** - System components and features

### For Contributors & Legal
- **[Contributing Guide](CONTRIBUTING.md)** - How to help
- **[License](LICENSE.md)** - CC BY-NC-SA 4.0
- **[Governance](GOVERNANCE.md)** - Decision process
- **[Impressum](IMPRESSUM.md)** - Legal info (German)

---

## 🏗️ Technology Stack

| Layer | Technology | Notes |
|-------|-----------|-------|
| **Frontend** | HTML5, CSS3, JavaScript ES6+ | No framework bloat |
| **Backend** | PHP 7.4+ / 8.0+ | Single-file REST API |
| **Database** | MySQL 5.7+ / MariaDB 10.3+ | Relational with prepared statements |
| **Charts** | Chart.js 3.x | Data visualization |
| **Export** | jsPDF 2.x | Client-side PDF generation |
| **PWA** | Service Worker API | Offline support, installable |

---

## 🔒 Security & Privacy

### Privacy First
✅ GDPR/DSGVO compliant  
✅ Zero tracking or analytics  
✅ Zero third-party access  
✅ Encrypted data transmission (HTTPS)  
✅ Secure session management  
✅ Audit logging for security  

### Security Features
- Prepared statements (SQL injection prevention)
- Bcrypt password hashing
- HttpOnly, Secure, SameSite cookies
- Server-side input validation
- Structured error handling

[📖 Full Security Details](docs/TECHNICAL_ARCHITECTURE.md#security-architecture)

---

## 🎮 Gamification System

Encourages children to track consistently:

| Achievement | Requirement | Reward |
|-------------|-------------|--------|
| 3-Tage-Serie | 3-day streak | 🥉 Bronze badge |
| Wochen-Held | 7-day streak | 🥈 Silver badge |
| Halbmond | 15-day streak | 🥇 Gold badge |
| Monats-Meister | 30-day streak | 👑 Platinum badge |

Plus **10 points** per entry for motivation!

---

## 📁 Project Structure

```
fokuslog-app/
├── api/                          # REST API backend
│   ├── index.php                 # Main router & handlers
│   ├── lib/logger.php            # Logging utility
│   └── .env                      # Configuration
├── app/                          # Web application frontend
│   ├── *.html                    # Page templates
│   ├── js/app.js                 # Application logic
│   ├── style.css                 # Global styles
│   ├── service-worker.js         # PWA offline
│   └── manifest.json             # PWA config
├── db/
│   └── schema.sql                # Database schema
├── docs/                         # Documentation
│   ├── USER_GUIDE.md             # User documentation
│   ├── TECHNICAL_ARCHITECTURE.md # Architecture guide
│   └── API_DOCUMENTATION.md      # API reference
└── [license, contributing, etc]
```

---

## 💡 Use Cases

### Family with Child on Medication
Parent registers → Adds child → Adds medications → Child creates daily entries → Parent reviews patterns → Export report for doctor → Doctor adjusts medication

### School Collaboration
Parent adds teacher → Teacher records classroom observations → Combined with home observations → Complete picture for doctor

### Adult Self-Management
Adult registers → Tracks personal medication response → Identifies patterns → Exports for own doctor

---

## 🤝 Contributing

We welcome contributions in:
- 🐛 Bug fixes
- 📖 Documentation improvements
- 🎨 UX/Accessibility enhancements
- 🌍 Translations

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

---

## 📄 License

**Creative Commons Attribution–NonCommercial–ShareAlike (CC BY-NC-SA 4.0)**

✅ Free for personal, educational, and medical use  
❌ Commercial use requires explicit permission

[See LICENSE.md](LICENSE.md) for details.

---

## 🙋 FAQ

| Question | Answer |
|----------|--------|
| **Is it free?** | Yes, completely free and open-source |
| **Is my data safe?** | Yes, GDPR compliant, encrypted, secure storage |
| **Offline support?** | Yes, install as PWA for offline mode |
| **Multi-family support?** | Yes, each family isolated and secure |
| **Data export?** | Yes, PDF or CSV anytime |
| **Mobile app?** | Works on mobile browsers, PWA installable |

[More FAQ](docs/USER_GUIDE.md#faq)

---

## 📞 Support

- **Users**: [User Guide](docs/USER_GUIDE.md)
- **Developers**: [Technical Docs](docs/TECHNICAL_ARCHITECTURE.md)
- **Contributing**: [Contributing Guide](CONTRIBUTING.md)
- **Legal/Privacy**: [IMPRESSUM.md](IMPRESSUM.md)

---

## 🎯 Core Principles

- 🔒 **Privacy First** - GDPR compliant, no tracking
- ♿ **Accessible** - Designed for children and professionals
- 🏗️ **Sustainable** - No heavy dependencies, runs on shared hosting
- 🤝 **Transparent** - Open-source, clear decision-making
- 📊 **Evidence-Based** - Real observations, real patterns

---

## 📈 Project Status

✅ **Actively Maintained** - Regular updates and bug fixes  
✅ **Production Ready** - Stable for everyday use  
✅ **Community Welcome** - Contributions encouraged  

---

## 🌟 Key Features at a Glance

| Feature | Benefit |
|---------|---------|
| Multi-person family accounts | Coordinate between home and school |
| 1-5 rating scales with emojis | Easy for children to use |
| Medication comparison | Find what works best |
| Visual charts & trends | Spot patterns quickly |
| Doctor-ready reports | Share with healthcare providers |
| Points & badges | Motivate children to track daily |
| Secure, private | Peace of mind for families |
| Works offline | Track anytime, anywhere |
| Export data | Your data, your control |
| GDPR compliant | Privacy by design |

---

## 📚 Additional Resources

- [Project Documentation](PROJECT_DOCUMENTATION.md) - Detailed system overview
- [API Documentation](docs/API_DOCUMENTATION.md) - Complete API reference
- [User Guide](docs/USER_GUIDE.md) - How to use FokusLog
- [Privacy Policy (German)](DATENSCHUTZERKLAERUNG.md) - Your rights
- [Governance](GOVERNANCE.md) - How decisions are made

---

## 🙏 Acknowledgments

FokusLog is built for families managing ADHD medication, inspired by the need for privacy-first healthcare technology and community-driven development.

---

**Ready to get started?** [Visit FokusLog](https://example.com/) | [User Guide](docs/USER_GUIDE.md) | [Developer Docs](docs/TECHNICAL_ARCHITECTURE.md)

---

**License:** CC BY-NC-SA 4.0 | **Version:** 1.0.0 | **Last Updated:** February 3, 2026

---

# LICENSE.md

## Creative Commons Attribution–NonCommercial–ShareAlike 4.0 International (CC BY-NC-SA 4.0)

You are free to:

* Share — copy and redistribute the material in any medium or format
* Adapt — remix, transform, and build upon the material

Under the following terms:

* **Attribution** — You must give appropriate credit
* **NonCommercial** — You may not use the material for commercial purposes
* **ShareAlike** — If you remix or build upon the material, you must distribute your contributions under the same license

### Commercial Use

Commercial use includes, but is not limited to:

* Offering FokusLog as a paid service
* Hosting FokusLog as part of a commercial SaaS
* Integrating FokusLog into proprietary products

Commercial use requires explicit written permission and may involve licensing fees.

---

# CONTRIBUTING.md

## Contributing to FokusLog

Thank you for your interest in contributing to FokusLog!

### Guiding Principles

* Respect the target groups (children, families, educators)
* Keep changes small and understandable
* Prefer clarity over cleverness

---

## How to Contribute

1. Fork the repository
2. Create a feature or fix branch
3. Make focused, well-documented changes
4. Open a Pull Request with a clear description

---

## What We Welcome

* Bug fixes
* Accessibility improvements
* UX copy improvements
* Documentation enhancements

## What We Avoid

* Large uncoordinated feature drops
* Framework rewrites
* Changes that reduce privacy or accessibility

---

## Code Style

* Readable, explicit code
* No hidden magic
* Consistent naming

---

# GOVERNANCE.md

## Governance Model

FokusLog follows a **Benevolent Maintainer Model**.

### Roles

**Maintainer**

* Defines vision and roadmap
* Makes final decisions
* Merges pull requests

**Contributors**

* Propose changes via issues or pull requests
* Participate in discussions respectfully

---

## Decision Making

* Consensus is preferred
* The Maintainer has final decision authority
* Decisions prioritize users over technology

---

## Conflict Resolution

If conflicts arise:

1. Discuss respectfully in the issue or PR
2. Maintainer moderates and decides

---

# CODE_OF_CONDUCT.md

## Code of Conduct

FokusLog is a project dealing with sensitive topics involving children, families, and mental health.

We are committed to providing a **safe, respectful, and inclusive environment**.

### Expected Behavior

* Be respectful and empathetic
* Assume good intent
* Use clear and inclusive language

### Unacceptable Behavior

* Harassment or discrimination
* Dismissive or mocking language
* Exploitation of sensitive topics

### Enforcement

The Maintainer reserves the right to moderate discussions, remove content, or restrict participation if necessary.

---

*End of public documentation*
