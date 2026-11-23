/**
 * Push notifications of the current device, shared by the section of the profile and the dialog shown after logging in
 * (`resources/views/livewire/profile-push-notifications.blade.php` and `push-notifications-prompt.blade.php`).
 *
 * The browser subscribes with the public VAPID key and the subscription is sent to the Livewire component (`$wire`) to
 * be stored for the user, so that the service worker (`public/sw.js`) shows the notifications even with the app closed.
 *
 * On iOS and iPadOS the push notifications are available only once the app is added to the Home Screen.
 *
 * A subscription made with other keys (e.g. before a new deployment of a test environment, which generates new ones)
 * cannot be reached anymore, and the browser refuses new subscriptions until it is removed: it is removed first.
 *
 * Spread into the `x-data` of the component, whose `init()` calls `refreshStatus()`.
 *
 * @param {string} publicKey
 */
window.pushSubscription = (publicKey) => ({
    publicKey,
    status: 'loading',
    busy: false,
    async refreshStatus() {
        if (! ('serviceWorker' in navigator) || ! ('PushManager' in window) || ! ('Notification' in window)) {
            this.status = this.isIos() && ! this.isInstalled() ? 'install' : 'unsupported';

            return;
        }

        if (Notification.permission === 'denied') {
            this.status = 'denied';

            return;
        }

        try {
            const subscription = await this.currentSubscription();

            if (! subscription || ! await this.$wire.ownsSubscription(subscription.endpoint)) {
                this.status = 'disabled';

                return;
            }

            // Sent again on every visit, as the browser may have renewed its keys in the meantime.
            await this.$wire.subscribe(this.serialize(subscription));
            this.status = 'enabled';
        } catch (error) {
            console.error(error);
            this.status = 'error';
        }
    },
    isIos() {
        return /iphone|ipad|ipod/i.test(navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    },
    isInstalled() {
        return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    },
    async currentSubscription() {
        const registration = await this.serviceWorker();
        const subscription = await registration.pushManager.getSubscription();

        if (subscription && ! this.usesServerKey(subscription)) {
            await subscription.unsubscribe();

            return null;
        }

        return subscription;
    },
    usesServerKey(subscription) {
        const key = subscription.options?.applicationServerKey;

        // Browsers that do not expose the key: the subscription is assumed to be made with it.
        if (! key) {
            return true;
        }

        const subscriptionKey = new Uint8Array(key);
        const serverKey = this.applicationServerKey();

        return subscriptionKey.length === serverKey.length
            && subscriptionKey.every((byte, index) => byte === serverKey[index]);
    },
    serviceWorker() {
        // `ready` never settles when the service worker cannot be registered.
        return Promise.race([
            navigator.serviceWorker.ready,
            new Promise((resolve, reject) => setTimeout(() => reject(new Error('Service worker not ready')), 10000)),
        ]);
    },
    serialize(subscription) {
        return {
            ...subscription.toJSON(),
            // `aes128gcm` is the standard encoding, supported by every browser with the Push API.
            contentEncoding: PushManager.supportedContentEncodings?.includes('aes128gcm') === false
                ? PushManager.supportedContentEncodings[0]
                : 'aes128gcm',
        };
    },
    applicationServerKey() {
        const base64 = (this.publicKey + '='.repeat((4 - this.publicKey.length % 4) % 4))
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        return Uint8Array.from(atob(base64), (character) => character.charCodeAt(0));
    },
    async enable() {
        this.busy = true;

        try {
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                this.status = permission === 'denied' ? 'denied' : 'disabled';

                return;
            }

            // Removes a subscription made with other keys, which would make the new one fail.
            await this.currentSubscription();

            const registration = await this.serviceWorker();
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.applicationServerKey(),
            });

            await this.$wire.subscribe(this.serialize(subscription));
            this.status = 'enabled';
        } catch (error) {
            console.error(error);
            this.status = 'error';
        } finally {
            this.busy = false;
        }
    },
    async disable() {
        this.busy = true;

        try {
            const subscription = await this.currentSubscription();

            if (subscription) {
                await this.$wire.unsubscribe(subscription.endpoint);
                await subscription.unsubscribe();
            }

            this.status = 'disabled';
        } catch (error) {
            console.error(error);
            this.status = 'error';
        } finally {
            this.busy = false;
        }
    },
});
