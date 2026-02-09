/*
 * Einfacher Service Worker für FokusLog
 *
 * Cacht statische Assets (App Shell) während der Installation und versucht bei
 * Anfragen zuerst aus dem Netz zu laden. Bei Offline‑Status wird der Cache
 * zurückgegeben. API‑Aufrufe werden nicht gecacht, damit immer aktuelle
 * Daten verwendet werden.
 */

const CACHE_NAME = 'fokuslog-cache-v8';
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
  '/app/help/help.html',
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

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      // Versuche URLs einzeln zu cachen, damit ein fehlendes File nicht die gesamte Installation blockiert
      return Promise.all(
        OFFLINE_URLS.map(url => {
          return cache.add(url).catch(err => console.warn('⚠️ Cache-Fehler (übersprungen):', url, err));
        })
      );
    })
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
      );
    })
  );
});

self.addEventListener('fetch', event => {
  const { request } = event;
  // API nicht cachen
  if (request.url.includes('/api/')) {
    return;
  }
  event.respondWith(
    fetch(request).catch(() => caches.match(request))
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
    self.registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: self.vapidPublicKey
    }).then(subscription => {
      // Sende neue Subscription an Server
      return fetch('/api/notifications/push/subscribe', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(subscription),
        credentials: 'include'
      });
    })
  );
});