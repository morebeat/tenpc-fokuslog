(function (global) {
    const FokusLog = global.FokusLog || (global.FokusLog = {});
    const existingUtils = FokusLog.utils || {};

    const escapeHtml = (unsafe) => {
        if (typeof unsafe !== 'string' && typeof unsafe !== 'number') return unsafe;
        return String(unsafe)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    };

    const addFooterLinks = () => {
        if (document.getElementById('app-footer')) return;

        document.body.style.display = 'flex';
        document.body.style.flexDirection = 'column';
        document.body.style.minHeight = '100vh';
        document.body.style.margin = '0';
        const main = document.querySelector('main');
        if (main) main.style.flex = '1';

        const footer = document.createElement('footer');
        footer.id = 'app-footer';
        footer.style.marginTop = '40px';
        footer.style.padding = '30px 0';
        footer.style.backgroundColor = '#f8f9fa';
        footer.style.borderTop = '1px solid #e9ecef';
        footer.style.textAlign = 'center';
        footer.style.color = '#6c757d';
        footer.style.fontSize = '0.9rem';

        const linkStyle = 'color: #495057; text-decoration: none; margin: 0 10px; font-weight: 500;';
        const isHelpPage = window.location.pathname.includes('/help/');
        const basePath = isHelpPage ? '../' : '';

        footer.innerHTML = `
            <div style="max-width: 960px; margin: 0 auto; padding: 0 15px;">
                <p style="margin-bottom: 10px;">
                    <a href="${basePath}impressum.html" style="${linkStyle}">Impressum</a> &bull;
                    <a href="${basePath}privacy.html" style="${linkStyle}">Datenschutz</a> &bull;
                    <a href="${basePath}help/index.html" style="${linkStyle}">Hilfe</a>
                </p>
                <p style="margin: 0; font-size: 0.8rem; opacity: 0.8;">&copy; ${new Date().getFullYear()} FokusLog</p>
            </div>`;
        document.body.appendChild(footer);
    };

    function notifyUpdate(worker) {
        const notification = document.createElement('div');
        notification.style.cssText = 'position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #333; color: white; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 6px rgba(0,0,0,0.2); z-index: 10000; display: flex; align-items: center; gap: 15px; font-family: sans-serif; font-size: 14px;';
        notification.innerHTML = `
            <span>Neue Version verfügbar.</span>
            <button id="sw-update-btn" style="background: #4e8cff; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">Aktualisieren</button>
        `;

        document.body.appendChild(notification);

        document.getElementById('sw-update-btn').addEventListener('click', () => {
            worker.postMessage({ action: 'skipWaiting' });
        });
    }

    const initServiceWorker = () => {
        if (!('serviceWorker' in navigator)) return;

        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/app/service-worker.js', { scope: '/app/', updateViaCache: 'none' })
                .then(registration => {
                    if (registration.waiting) {
                        notifyUpdate(registration.waiting);
                    }

                    registration.addEventListener('updatefound', () => {
                        const newWorker = registration.installing;
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                notifyUpdate(newWorker);
                            }
                        });
                    });
                })
                .catch(err => (window.FokusLog?.utils?.error || (() => {}))('SW Registration failed:', err));
        });

        let refreshing;
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (refreshing) return;
            window.location.reload();
            refreshing = true;
        });
    };

    FokusLog.utils = {
        ...existingUtils,
        escapeHtml,
        addFooterLinks,
        initServiceWorker,
        notifyUpdate
    };
})(window);
