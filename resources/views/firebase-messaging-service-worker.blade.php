importScripts('https://www.gstatic.com/firebasejs/12.18.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/12.18.0/firebase-messaging-compat.js');

firebase.initializeApp(@json(json_decode($config, true)));
const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
    const data = payload.data || {};
    self.registration.showNotification(data.title || 'Nouvelle notification', {
        body: data.body || '',
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        tag: data.notification_id ? `school-${data.notification_id}` : undefined,
        data: { url: data.url || '/dashboard' },
    });
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const requested = new URL(event.notification.data?.url || '/dashboard', self.location.origin);
    const target = requested.origin === self.location.origin ? requested.href : new URL('/dashboard', self.location.origin).href;

    event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        for (const client of windows) {
            if (client.url === target && 'focus' in client) return client.focus();
        }
        return clients.openWindow ? clients.openWindow(target) : undefined;
    }));
});
