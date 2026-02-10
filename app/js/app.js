(function (global) {
    const FokusLog = global.FokusLog || (global.FokusLog = {});
    const utils = FokusLog.utils || (FokusLog.utils = {});
    const pages = FokusLog.pages || (FokusLog.pages = {});

    // ─── Debug Logger ─────────────────────────────────────────────────────────
    // Nur aktiv wenn window.FOKUSLOG_DEBUG === true (in .env: DEBUG_LOG=1 → HTML setzt flag)
    utils.log = function (...args) {
        if (global.FOKUSLOG_DEBUG) console.log('[FokusLog]', ...args);
    };
    utils.error = function (...args) {
        if (global.FOKUSLOG_DEBUG) console.error('[FokusLog]', ...args);
    };

    // ─── i18n Lookup ──────────────────────────────────────────────────────────
    // FokusLog.i18n wird von app/js/i18n/de.js befüllt.
    // Verwendung: utils.t('error.network') → "Netzwerkfehler"
    // Platzhalter: utils.t('greeting', { name: 'Alice' }) → "Hallo, Alice!"
    utils.t = function (key, params) {
        const dict = FokusLog.i18n || {};
        let text = dict[key] || key;
        if (params) {
            Object.keys(params).forEach(k => {
                text = text.replace(new RegExp(`\\{${k}\\}`, 'g'), params[k]);
            });
        }
        return text;
    };

    // ─── API Client ───────────────────────────────────────────────────────────
    // Zentraler Fetch-Wrapper mit einheitlicher Fehlerbehandlung.
    // Wirft ApiError bei HTTP-Fehlern (4xx, 5xx).
    class ApiError extends Error {
        constructor(status, message, body) {
            super(message);
            this.name = 'ApiError';
            this.status = status;
            this.body = body;
        }
    }
    utils.ApiError = ApiError;

    utils.apiCall = async function (endpoint, options = {}) {
        const defaults = {
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' }
        };
        if (options.headers) {
            defaults.headers = Object.assign({}, defaults.headers, options.headers);
        }
        const config = Object.assign({}, defaults, options, { headers: defaults.headers });

        const response = await fetch(endpoint, config);

        if (response.status === 204) return null;

        let body;
        const ct = response.headers.get('content-type') || '';
        if (ct.includes('application/json')) {
            body = await response.json();
        } else {
            body = await response.text();
        }

        if (!response.ok) {
            const message = (body && body.error) || `HTTP ${response.status}`;
            throw new ApiError(response.status, message, body);
        }
        return body;
    };

    // ─── Toast Notifications ──────────────────────────────────────────────────
    // utils.toast(message, type, duration)
    // type: 'success' | 'error' | 'info' | 'warning'
    utils.toast = function (message, type = 'info', duration = 3500) {
        let container = document.getElementById('fl-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'fl-toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `fl-toast fl-toast--${type}`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'polite');
        toast.textContent = message;

        const dismiss = () => {
            toast.classList.add('fl-toast--hide');
            toast.addEventListener('animationend', () => toast.remove(), { once: true });
        };
        toast.addEventListener('click', dismiss);

        container.appendChild(toast);
        setTimeout(dismiss, duration);
    };

    // ─── Polling Utility ──────────────────────────────────────────────────────
    // Pollt einen Endpoint in regelmäßigen Abständen.
    // Gibt ein Objekt mit stop()-Methode zurück.
    // Verwendung: const p = utils.poll('/api/me', 30000, data => ...);
    //             p.stop();
    utils.poll = function (endpoint, interval, callback, options = {}) {
        let timerId = null;
        let stopped = false;

        const run = async () => {
            if (stopped) return;
            try {
                const data = await utils.apiCall(endpoint, options);
                if (!stopped) callback(null, data);
            } catch (err) {
                if (!stopped) callback(err, null);
            }
            if (!stopped) timerId = setTimeout(run, interval);
        };

        timerId = setTimeout(run, interval);
        return {
            stop() {
                stopped = true;
                if (timerId !== null) clearTimeout(timerId);
            }
        };
    };
    // ─────────────────────────────────────────────────────────────────────────

    const PUBLIC_PAGES = new Set(['login', 'register', 'privacy', 'impressum', 'help']);
    const PAGE_SCRIPTS = {
        account: { module: 'account' },
        badges: { module: 'badges' },
        dashboard: { module: 'dashboard' },
        entry: { module: 'entry' },
        help: { module: 'help' },
        login: { module: 'auth' },
        register: { module: 'auth' },
        report: { module: 'report' },
        manage_tags: { module: 'manage-tags' },
        'manage-tags': { module: 'manage-tags', namespace: 'manage_tags' },
        manage_meds: { module: 'manage-meds' },
        'manage-meds': { module: 'manage-meds', namespace: 'manage_meds' },
        manage_users: { module: 'manage-users' },
        'manage-users': { module: 'manage-users', namespace: 'manage_users' },
        edit_user: { module: 'edit-user' },
        'edit-user': { module: 'edit-user', namespace: 'edit_user' }
    };

    const loadedModules = new Set();
    const appScript = document.currentScript;
    const appScriptUrl = appScript ? new URL(appScript.src, global.location.href) : new URL('js/app.js', global.location.href);
    const pagesBaseUrl = new URL('./pages/', appScriptUrl);

    document.addEventListener('DOMContentLoaded', () => initializeApp());

    async function initializeApp() {
        const pageAttr = document.body?.dataset?.page;
        const page = pageAttr || 'default';
        const context = {
            page,
            utils,
            elements: {
                logoutBtn: document.getElementById('logout-btn'),
                welcomeMsg: document.getElementById('welcome')
            }
        };

        setupLogout(context.elements.logoutBtn);

        const user = await resolveCurrentUser(pageAttr);
        if (!user && !isPublicPage(pageAttr)) {
            redirectToLogin();
            return;
        }

        context.user = user || null;
        updateWelcomeMessage(context);

        await bootstrapPage(page, context);

        utils.addFooterLinks?.();
        utils.initServiceWorker?.();
    }

    function isPublicPage(pageAttr) {
        if (!pageAttr) return true;
        return PUBLIC_PAGES.has(pageAttr);
    }

    async function resolveCurrentUser(pageAttr) {
        try {
            const response = await fetch('/api/me');
            if (response.status === 401) {
                return null;
            }
            if (!response.ok) {
                throw new Error(`Unerwarteter Status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            if (!isPublicPage(pageAttr)) {
                utils.error('Fehler beim Abrufen des aktuellen Benutzers:', error);
            }
            return null;
        }
    }

    function redirectToLogin() {
        global.location.href = 'login.html';
    }

    async function bootstrapPage(page, context) {
        const descriptor = PAGE_SCRIPTS[page];
        if (!descriptor) {
            return;
        }
        const moduleKey = typeof descriptor === 'string' ? descriptor : descriptor.module;
        const namespace = typeof descriptor === 'string' ? page : descriptor.namespace || page;
        try {
            const moduleRef = await loadPageModule(moduleKey, namespace);
            if (moduleRef?.init) {
                await moduleRef.init(context);
            }
        } catch (error) {
            utils.error(`Fehler beim Initialisieren der Seite "${page}":`, error);
            showPageError(`Die Seite konnte nicht geladen werden. Bitte die Seite neu laden.`);
        }
    }

    async function loadPageModule(moduleKey, namespace) {
        if (pages[namespace]) {
            return pages[namespace];
        }
        if (!loadedModules.has(moduleKey)) {
            await injectScript(moduleKey);
            loadedModules.add(moduleKey);
        }
        return pages[namespace] || null;
    }

    /**
     * Lädt ein Seiten-Skript mit Timeout.
     * Verhindert, dass ein hängendes Script die Seite für immer blockiert.
     */
    function injectScript(moduleKey, timeoutMs = 10000) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = new URL(`${moduleKey}.js`, pagesBaseUrl).toString();
            script.async = false;

            const timer = setTimeout(() => {
                script.onload = null;
                script.onerror = null;
                reject(new Error(`Timeout: Seitenmodul "${moduleKey}" konnte nicht rechtzeitig geladen werden.`));
            }, timeoutMs);

            script.onload = () => {
                clearTimeout(timer);
                resolve();
            };
            script.onerror = () => {
                clearTimeout(timer);
                reject(new Error(`Seitenmodul "${moduleKey}" konnte nicht geladen werden.`));
            };

            document.head.appendChild(script);
        });
    }

    /**
     * Zeigt eine nutzbare Fehlermeldung im DOM an, wenn ein Seitenmodul nicht lädt.
     * Sucht nach einem <main>-Element, dann nach einem .container, sonst body.
     */
    function showPageError(message) {
        const target = document.querySelector('main') ||
                       document.querySelector('.container') ||
                       document.body;
        if (!target) return;
        const banner = document.createElement('div');
        banner.setAttribute('role', 'alert');
        banner.style.cssText = [
            'background:#fff3cd', 'border:1px solid #ffc107',
            'border-radius:6px', 'padding:1rem 1.25rem',
            'margin:1rem 0', 'color:#664d03', 'font-size:1rem'
        ].join(';');
        banner.textContent = message;
        target.prepend(banner);
    }

    function setupLogout(button) {
        if (!button) return;
        button.addEventListener('click', async () => {
            try {
                await fetch('/api/logout', { method: 'POST' });
            } catch {
                // Logout-Fehler still ignorieren — Weiterleitung erfolgt sowieso
            } finally {
                global.location.href = 'login.html';
            }
        });
    }

    function updateWelcomeMessage({ user, elements }) {
        if (!user || !elements?.welcomeMsg) {
            return;
        }
        elements.welcomeMsg.textContent = `Hallo, ${user.username}!`;
    }
})(window);
