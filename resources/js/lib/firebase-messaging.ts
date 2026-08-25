import { getApp, getApps, initializeApp } from 'firebase/app';
import {
    getMessaging,
    getToken,
    isSupported,
    onMessage,
} from 'firebase/messaging';

type FirebasePublicConfig = {
    apiKey: string;
    authDomain: string;
    projectId: string;
    messagingSenderId: string;
    appId: string;
    vapidKey: string;
};

let configuration: Promise<FirebasePublicConfig> | null = null;

async function config(): Promise<FirebasePublicConfig> {
    configuration ??= fetch('/firebase/config', {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    }).then(async (response) => {
        if (!response.ok)
            throw new Error('Firebase configuration is unavailable.');
        return response.json();
    });

    return configuration;
}

async function registration(): Promise<ServiceWorkerRegistration> {
    return navigator.serviceWorker.register('/firebase-messaging-sw.js', {
        scope: '/',
    });
}

async function messagingContext() {
    if (!(await isSupported()))
        throw new Error('Firebase Messaging is not supported by this browser.');
    const firebaseConfig = await config();
    if (
        !firebaseConfig.apiKey ||
        !firebaseConfig.projectId ||
        !firebaseConfig.messagingSenderId ||
        !firebaseConfig.appId ||
        !firebaseConfig.vapidKey
    ) {
        throw new Error('Firebase is not fully configured.');
    }
    const app = getApps().length ? getApp() : initializeApp(firebaseConfig);

    return {
        messaging: getMessaging(app),
        firebaseConfig,
        serviceWorkerRegistration: await registration(),
    };
}

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content || ''
    );
}

export async function enableFirebaseNotifications(): Promise<void> {
    if (Notification.permission !== 'granted')
        throw new Error('Notification permission must be granted first.');
    const { messaging, firebaseConfig, serviceWorkerRegistration } =
        await messagingContext();
    const token = await getToken(messaging, {
        vapidKey: firebaseConfig.vapidKey,
        serviceWorkerRegistration,
    });
    if (!token) throw new Error('Firebase did not return a browser token.');

    const response = await fetch('/fcm/tokens', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ token }),
    });
    if (!response.ok) throw new Error('The browser token could not be saved.');
}

export async function firebaseNotificationState(): Promise<
    'unsupported' | 'default' | 'denied' | 'subscribed'
> {
    if (
        !('Notification' in window) ||
        !('serviceWorker' in navigator) ||
        !(await isSupported())
    )
        return 'unsupported';
    if (Notification.permission === 'denied') return 'denied';
    if (Notification.permission !== 'granted') return 'default';
    try {
        const { messaging, firebaseConfig, serviceWorkerRegistration } =
            await messagingContext();
        return (await getToken(messaging, {
            vapidKey: firebaseConfig.vapidKey,
            serviceWorkerRegistration,
        }))
            ? 'subscribed'
            : 'default';
    } catch {
        return 'default';
    }
}

export async function startFirebaseMessaging(): Promise<void> {
    if (!('Notification' in window) || Notification.permission !== 'granted')
        return;
    const { messaging, firebaseConfig, serviceWorkerRegistration } =
        await messagingContext();
    const token = await getToken(messaging, {
        vapidKey: firebaseConfig.vapidKey,
        serviceWorkerRegistration,
    });
    if (token) {
        fetch('/fcm/tokens', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ token }),
        }).catch(() => undefined);
    }
    onMessage(messaging, async (payload) => {
        window.dispatchEvent(new CustomEvent('portal-notification-created'));
        const data = payload.data || {};
        if (Notification.permission === 'granted') {
            try {
                await serviceWorkerRegistration.showNotification(
                    data.title || 'Nouvelle notification',
                    {
                        body: data.body || '',
                        icon: '/favicon.ico',
                        data: { url: data.url || '/dashboard' },
                    },
                );
            } catch {
                // A foreground display failure must not interrupt FCM updates.
            }
        }
    });
}
