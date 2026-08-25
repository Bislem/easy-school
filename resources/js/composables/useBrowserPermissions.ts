export type BrowserPermission =
    | 'camera'
    | 'microphone'
    | 'geolocation'
    | 'notifications';

export type BrowserPermissionState =
    | PermissionState
    | 'unsupported'
    | 'unknown';

export type PermissionRequestResult<T = undefined> = {
    outcome: 'granted' | 'denied' | 'blocked' | 'unsupported';
    state: BrowserPermissionState;
    value?: T;
    error?: unknown;
};

let notificationRequest: Promise<PermissionRequestResult> | null = null;

export async function checkBrowserPermission(
    permission: BrowserPermission,
): Promise<BrowserPermissionState> {
    if (permission === 'notifications') {
        if (!('Notification' in window)) return 'unsupported';
        return Notification.permission === 'default'
            ? 'prompt'
            : Notification.permission;
    }

    if (!('permissions' in navigator)) return 'unknown';

    try {
        return (
            await navigator.permissions.query({
                name: permission as PermissionName,
            })
        ).state;
    } catch {
        return 'unknown';
    }
}

export function requestNotificationPermission(): Promise<PermissionRequestResult> {
    if (!('Notification' in window)) {
        return Promise.resolve({
            outcome: 'unsupported',
            state: 'unsupported',
        });
    }
    if (Notification.permission === 'granted') {
        return Promise.resolve({ outcome: 'granted', state: 'granted' });
    }
    if (Notification.permission === 'denied') {
        return Promise.resolve({ outcome: 'denied', state: 'denied' });
    }
    if (notificationRequest) return notificationRequest;

    // Called directly from the click handler so Chrome retains user activation.
    notificationRequest = Notification.requestPermission()
        .then((permission): PermissionRequestResult => {
            if (permission === 'granted') {
                return { outcome: 'granted', state: 'granted' };
            }
            if (permission === 'denied') {
                return { outcome: 'denied', state: 'denied' };
            }

            return { outcome: 'blocked', state: 'prompt' };
        })
        .catch(async (error): Promise<PermissionRequestResult> => {
            const state = await checkBrowserPermission('notifications');
            return {
                outcome: state === 'denied' ? 'denied' : 'blocked',
                state,
                error,
            };
        })
        .finally(() => {
            notificationRequest = null;
        });

    return notificationRequest;
}

async function requestMediaPermission(
    permission: 'camera' | 'microphone',
): Promise<PermissionRequestResult<MediaStream>> {
    if (!navigator.mediaDevices?.getUserMedia) {
        return { outcome: 'unsupported', state: 'unsupported' };
    }
    const state = await checkBrowserPermission(permission);
    if (state === 'denied') return { outcome: 'denied', state };

    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: permission === 'camera',
            audio: permission === 'microphone',
        });
        return { outcome: 'granted', state: 'granted', value: stream };
    } catch (error) {
        const current = await checkBrowserPermission(permission);
        return {
            outcome: current === 'denied' ? 'denied' : 'blocked',
            state: current,
            error,
        };
    }
}

export function requestCameraPermission() {
    return requestMediaPermission('camera');
}

export function requestMicrophonePermission() {
    return requestMediaPermission('microphone');
}

export async function requestGeolocationPermission(): Promise<
    PermissionRequestResult<GeolocationPosition>
> {
    if (!('geolocation' in navigator)) {
        return { outcome: 'unsupported', state: 'unsupported' };
    }
    const state = await checkBrowserPermission('geolocation');
    if (state === 'denied') return { outcome: 'denied', state };

    try {
        const position = await new Promise<GeolocationPosition>(
            (resolve, reject) =>
                navigator.geolocation.getCurrentPosition(resolve, reject, {
                    enableHighAccuracy: false,
                    timeout: 15000,
                    maximumAge: 60000,
                }),
        );
        return { outcome: 'granted', state: 'granted', value: position };
    } catch (error) {
        const current = await checkBrowserPermission('geolocation');
        return {
            outcome: current === 'denied' ? 'denied' : 'blocked',
            state: current,
            error,
        };
    }
}

export function useBrowserPermissions() {
    return {
        check: checkBrowserPermission,
        requestCamera: requestCameraPermission,
        requestMicrophone: requestMicrophonePermission,
        requestGeolocation: requestGeolocationPermission,
        requestNotifications: requestNotificationPermission,
    };
}
