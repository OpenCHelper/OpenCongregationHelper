{{--
    Offer to enable the push notifications on the current device (see `resources/js/push-subscription.js`) in a
    full-screen dialog, on the first page after logging in (`App\Livewire\PushNotificationsPrompt`), like the offer
    to install the app (`filament.pwa-install-prompt`): the two take turns through the `fullScreenPrompt` store, so
    that only one is open at a time, and this one waits for the browser to tell whether the app can be installed, so
    that the offer to install opens first.

    Nothing is shown when the device cannot receive them (e.g. on iOS before installing the app), is already subscribed,
    blocks them, or for some days after the user dismissed the offer.
--}}
<div
    x-data="{
        ...pushSubscription(@js($publicKey)),
        name: 'push-notifications',
        dismissedKey: 'push-notifications-prompt-dismissed-at',
        dismissDays: @js(\App\Livewire\PushNotificationsPrompt::DISMISS_DAYS),
        wantsOpen: false,
        installOfferWaitMs: 4000,
        async init() {
            this.$watch('wantsOpen', () => this.claim());
            this.$watch('$store.fullScreenPrompt.current', () => this.claim());

            if (this.wasDismissedRecently()) {
                return;
            }

            await Promise.all([this.refreshStatus(), this.waitForInstallOffer()]);
            this.wantsOpen = this.status === 'disabled';
        },
        // Chromium announces that the app can be installed (`beforeinstallprompt`) only a moment after the page loads:
        // wait for it (or for a few seconds, when the app cannot be installed), so that the offer to install opens first
        waitForInstallOffer() {
            if (! ('BeforeInstallPromptEvent' in window) || window.pwaInstallPrompt || this.isInstalled()) {
                return Promise.resolve();
            }

            return new Promise((resolve) => {
                const timeout = setTimeout(done, this.installOfferWaitMs);

                function done() {
                    clearTimeout(timeout);
                    window.removeEventListener('pwa-installable', done);
                    resolve();
                }

                window.addEventListener('pwa-installable', done);
            });
        },
        get isOpen() {
            return this.$store.fullScreenPrompt.current === this.name;
        },
        claim() {
            const store = this.$store.fullScreenPrompt;

            if (this.wantsOpen && store.current === null) {
                store.current = this.name;
            } else if (! this.wantsOpen && store.current === this.name) {
                store.current = null;
            }
        },
        wasDismissedRecently() {
            try {
                const dismissedAt = Number(localStorage.getItem(this.dismissedKey));

                return dismissedAt > Date.now() - this.dismissDays * 24 * 60 * 60 * 1000;
            } catch {
                return false;
            }
        },
        async turnOn() {
            await this.enable();

            if (this.status === 'enabled') {
                new FilamentNotification().title(@js(__('profile.push_notifications.enabled'))).success().send();
                this.wantsOpen = false;
            } else if (this.status === 'denied') {
                this.dismiss();
            }
        },
        dismiss() {
            try {
                localStorage.setItem(this.dismissedKey, String(Date.now()));
            } catch {}

            this.wantsOpen = false;
        },
    }"
    x-show="isOpen"
    x-cloak
    x-transition.opacity
    x-trap.inert.noscroll="isOpen"
    x-on:keydown.escape.window="isOpen && ! busy && dismiss()"
    role="dialog"
    aria-modal="true"
    aria-labelledby="push-notifications-prompt-title"
    aria-describedby="push-notifications-prompt-description"
    class="app-push-notifications-prompt fixed inset-0 z-50 overflow-y-auto bg-white dark:bg-gray-950"
>
    <div class="mx-auto flex min-h-full max-w-md flex-col px-6 pt-[max(3rem,env(safe-area-inset-top))] pb-[max(1.5rem,env(safe-area-inset-bottom))]">
        <div class="flex flex-1 flex-col items-center justify-center text-center">
            <span class="flex size-24 items-center justify-center rounded-full bg-primary-50 text-primary-600 ring-8 ring-primary-50/50 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/5">
                <x-filament::icon icon="heroicon-o-bell-alert" class="size-12" />
            </span>

            <h2 id="push-notifications-prompt-title" class="mt-8 text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                {{ __('pwa.notifications.title') }}
            </h2>

            <p id="push-notifications-prompt-description" class="mt-2 text-base text-gray-600 dark:text-gray-400">
                {{ __('pwa.notifications.description', ['app' => config('app.name')]) }}
            </p>

            <ul class="mt-8 w-full space-y-5 text-start">
                @foreach ([
                    'heroicon-o-map' => 'assignments',
                    'heroicon-o-clock' => 'reminders',
                    'heroicon-o-megaphone' => 'campaigns',
                ] as $icon => $benefit)
                    <li class="flex gap-4">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-400/10 dark:text-primary-400">
                            <x-filament::icon :icon="$icon" class="size-5" />
                        </span>

                        <div>
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">
                                {{ __("pwa.notifications.benefits.{$benefit}.title") }}
                            </p>

                            <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">
                                {{ __("pwa.notifications.benefits.{$benefit}.description") }}
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="mt-10 flex flex-col gap-3">
            <p x-show="status === 'error'" class="text-center text-sm text-danger-600 dark:text-danger-400">
                {{ __('profile.push_notifications.error') }}
            </p>

            <x-filament::button x-on:click="turnOn()" x-bind:disabled="busy" size="xl" icon="heroicon-o-bell" class="w-full">
                {{ __('pwa.notifications.action') }}
            </x-filament::button>

            <x-filament::button x-on:click="dismiss()" x-bind:disabled="busy" size="lg" color="gray" class="w-full">
                {{ __('pwa.notifications.dismiss') }}
            </x-filament::button>

            <p class="text-center text-xs text-gray-500 dark:text-gray-400">
                {{ __('pwa.notifications.later') }}
            </p>
        </div>
    </div>
</div>
