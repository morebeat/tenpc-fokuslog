/*
 * Service Worker für FokusLog — Stale-While-Revalidate
 *
 * Strategie:
 * - Statische Assets: Stale-while-revalidate (sofort aus Cache, Update im Hintergrund)
 * - API-Calls: Network-only (immer aktuelle Daten)
 * - Offline: Fallback auf Cache
 *
 * Background Sync wird vorbereitet für spätere Offline-Entry-Erstellung.
 */

const CACHE_NAME = 'fokuslog-cache-v11';
const OFFLINE_URLS = [
  '/app/index.html',
  '/app/style.css',
  '/app/js/app.js',
  '/app/login.html',
  '/app/register.html',
  '/app/dashboard.html',
  '/app/entry.html',
  '/app/report.html',
  '/app/notifications.html',
  '/app/manage_users.html',
  '/app/manage_meds.html',
  '/app/privacy.html',
  '/app/manifest.json',
  '/app/icons/icon-192.png',
  '/app/icons/icon-512.png',
  '/app/help/index.html',
  '/app/help/assets/help.css',
  '/app/help/assets/help.js',
  '/app/help/css/guide.css',
  '/app/help/js/guide.js',
  '/app/help/guide/README.md',
  '/app/help/guide/00-erste-schritte.md',
  '/app/help/guide/01-grundlagen-adhs.md',
  '/app/help/guide/02-warum-medikation.md',
  '/app/help/guide/03-medikamente-ueberblick.md',
  '/app/help/guide/04-eindosierung-und-verlauf.md',
  '/app/help/guide/05-mythen-und-vorurteile.md',
  '/app/help/guide/06-alltag-ernaehrung-sport.md',
  '/app/help/guide/07-reisen-verkehr-recht.md',
  '/app/help/guide/08-arztgespraech.md',
  '/app/help/guide/zielgruppen/eltern.md',
  '/app/help/guide/zielgruppen/kinder-und-jugendliche.md',
  '/app/help/guide/zielgruppen/lehrkraefte.md',
  '/app/help/guide/zielgruppen/erwachsene.md',
  '/app/help/guide/zielgruppen/frauen-und-adhs.md',
  '/app/help/guide/zielgruppen/anhang/faq.md',
  '/app/help/guide/zielgruppen/anhang/glossar.md'
];

// ─── Installation: Pre-Cache App Shell ────────────────────────────────────────
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      // Versuche URLs einzeln zu cachen, damit ein fehlendes File nicht blockiert
      return Promise.all(
        OFFLINE_URLS.map(url =>
          cache.add(url).catch(err => console.warn('⚠️ Cache-Fehler (übersprungen):', url, err))
        )
      );
    })
  );
  // Sofort aktivieren ohne auf andere Tabs zu warten
  self.skipWaiting();
});

// ─── Aktivierung: Alte Caches löschen ─────────────────────────────────────────
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
      )
    ).then(() => self.clients.claim())
  );
});

// ─── Fetch: Stale-While-Revalidate für statische Assets ───────────────────────
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  // API-Calls: Network-only (keine Cache-Interferenz)
  if (url.pathname.startsWith('/api/')) {
    return;
  }

  // Nur GET-Requests cachen
  if (request.method !== 'GET') {
    return;
  }

  // Stale-While-Revalidate Strategie
  event.respondWith(
    caches.open(CACHE_NAME).then(cache =>
      cache.match(request).then(cachedResponse => {
        // Immer im Hintergrund aktualisieren
        const fetchPromise = fetch(request)
          .then(networkResponse => {
            // Nur gültige Responses cachen (status 200, same-origin)
            if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
              cache.put(request, networkResponse.clone());
            }
            return networkResponse;
          })
          .catch(() => {
            // Network-Fehler: nichts tun, cached Response wird verwendet
            return null;
          });

        // Sofort aus Cache antworten (falls vorhanden), sonst auf Network warten
        return cachedResponse || fetchPromise;
      })
    )
  );
});

self.addEventListener('message', event => {
  if (event.data && event.data.action === 'skipWaiting') {
    self.skipWaiting();
  }
});

/*
 * Push Notification Handler
 * Wird aufgerufen wenn eine Push-Nachricht vom Server empfangen wird
 */
self.addEventListener('push', event => {
  let data = {
    title: 'FokusLog',
    body: 'Zeit für deinen Eintrag!',
    icon: '/app/icons/icon-192.png',
    badge: '/app/icons/icon-192.png',
    tag: 'fokuslog-reminder',
    data: { url: '/app/entry.html' }
  };

  // Versuche Push-Daten zu parsen
  if (event.data) {
    try {
      const payload = event.data.json();
      data = { ...data, ...payload };
    } catch (e) {
      // Falls kein JSON, nutze Text als Body
      data.body = event.data.text() || data.body;
    }
  }

  const options = {
    body: data.body,
    icon: data.icon || '/app/icons/icon-192.png',
    badge: data.badge || '/app/icons/icon-192.png',
    tag: data.tag || 'fokuslog-notification',
    vibrate: [200, 100, 200],
    requireInteraction: false,
    data: data.data || { url: '/app/entry.html' },
    actions: [
      { action: 'open', title: 'Öffnen' },
      { action: 'dismiss', title: 'Später' }
    ]
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

/*
 * Notification Click Handler
 * Wird aufgerufen wenn der Benutzer auf eine Notification klickt
 */
self.addEventListener('notificationclick', event => {
  event.notification.close();

  // Bei "dismiss" nichts tun
  if (event.action === 'dismiss') {
    return;
  }

  // URL aus Notification-Daten holen
  const urlToOpen = event.notification.data?.url || '/app/entry.html';
  const fullUrl = new URL(urlToOpen, self.location.origin).href;

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then(windowClients => {
        // Versuche ein existierendes Fenster zu finden und zu fokussieren
        for (const client of windowClients) {
          if (client.url === fullUrl && 'focus' in client) {
            return client.focus();
          }
        }
        // Sonst neues Fenster öffnen
        if (clients.openWindow) {
          return clients.openWindow(fullUrl);
        }
      })
  );
});

/*
 * Push Subscription Change Handler
 * Wird aufgerufen wenn sich das Push-Abonnement ändert
 */
self.addEventListener('pushsubscriptionchange', event => {
  event.waitUntil(
    // Fetch VAPID public key from API
    fetch('/api/notifications/vapid-key')
      .then(response => {
        if (!response.ok) {
          throw new Error('VAPID key not available');
        }
        return response.json();
      })
      .then(data => {
        if (!data.vapid_public_key) {
          throw new Error('VAPID key missing in response');
        }
        // Convert base64 to Uint8Array
        const padding = '='.repeat((4 - data.vapid_public_key.length % 4) % 4);
        const base64 = (data.vapid_public_key + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = atob(base64);
        const applicationServerKey = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
          applicationServerKey[i] = rawData.charCodeAt(i);
        }
        return self.registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: applicationServerKey
        });
      })
      .then(subscription => {
        // Sende neue Subscription an Server
        return fetch('/api/notifications/push/subscribe', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ subscription: subscription.toJSON() }),
          credentials: 'include'
        });
      })
      .catch(error => {
        console.error('pushsubscriptionchange failed:', error);
      })
  );
});