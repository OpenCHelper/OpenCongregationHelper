{{--
    Offer to install the Progressive Web App (see `<x-pwa-head />`) in a full-screen dialog over the pages of the app
    panel: it is the recommended way to use the app (and, on iOS, the only one with notifications).

    - Chromium browsers (Chrome, Edge, Android) fire `beforeinstallprompt`: it is captured as soon as possible, before
      Alpine starts, and the "Install" button opens the native prompt of the browser.
    - iOS has no install prompt: the steps to add the app to the home screen are shown instead.

    It opens by itself unless the app already runs installed or the user dismissed the offer in the last days, and on
    request from the user menu and the profile (`open-pwa-install` event), whose entries show only while the
    `pwaInstall` store says that the app can be installed.

    The `fullScreenPrompt` store holds the full-screen dialog that is open (this one or the offer to enable the push
    notifications, `livewire.push-notifications-prompt`), so that they take turns instead of opening over each other.
--}}
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('fullScreenPrompt', { current: null });
        Alpine.store('pwaInstall', { available: false, installed: false });
    });

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        window.pwaInstallPrompt = event;
        window.dispatchEvent(new CustomEvent('pwa-installable'));
    });
</script>

<div
    x-data="{
        name: 'pwa-install',
        dismissedKey: 'pwa-install-dismissed-at',
        dismissDays: @js(\App\Providers\Filament\AppPanelProvider::PWA_INSTALL_DISMISS_DAYS),
        canPrompt: false,
        isIos: false,
        requested: false,
        init() {
            this.$watch('wantsOpen', () => this.claim());
            this.$watch('$store.fullScreenPrompt.current', () => this.claim());
            this.$watch('isAvailable', (isAvailable) => this.$store.pwaInstall.available = isAvailable);

            if (this.isInstalled()) {
                this.$store.pwaInstall.installed = true;

                return;
            }

            const isAppleMobile = /iphone|ipad|ipod/i.test(navigator.userAgent)
                || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

            this.isIos = isAppleMobile && ! ('BeforeInstallPromptEvent' in window);
            this.canPrompt = !! window.pwaInstallPrompt;
            this.requested = ! this.wasDismissedRecently();
            this.$store.pwaInstall.available = this.isAvailable;

            window.addEventListener('pwa-installable', () => this.canPrompt = true);
            window.addEventListener('open-pwa-install', () => this.requested = true);
            window.addEventListener('appinstalled', () => {
                this.$store.pwaInstall.installed = true;
                this.canPrompt = false;
                this.isIos = false;
                this.close();
            });
            this.claim();
        },
        get isAvailable() {
            return this.canPrompt || this.isIos;
        },
        get wantsOpen() {
            return this.requested && this.isAvailable;
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
        isInstalled() {
            return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        },
        wasDismissedRecently() {
            try {
                const dismissedAt = Number(localStorage.getItem(this.dismissedKey));

                return dismissedAt > Date.now() - this.dismissDays * 24 * 60 * 60 * 1000;
            } catch {
                return false;
            }
        },
        async install() {
            const prompt = window.pwaInstallPrompt;

            if (! prompt) {
                return;
            }

            prompt.prompt();
            const { outcome } = await prompt.userChoice;

            // The browser lets each captured prompt be shown once: it fires a new one on a next visit.
            window.pwaInstallPrompt = null;
            this.canPrompt = false;
            outcome === 'accepted' ? this.close() : this.dismiss();
        },
        dismiss() {
            try {
                localStorage.setItem(this.dismissedKey, String(Date.now()));
            } catch {}

            this.close();
        },
        close() {
            this.requested = false;
        },
    }"
    x-show="isOpen"
    x-cloak
    x-transition.opacity
    x-trap.inert.noscroll="isOpen"
    x-on:keydown.escape.window="isOpen && dismiss()"
    role="dialog"
    aria-modal="true"
    aria-labelledby="pwa-install-title"
    aria-describedby="pwa-install-description"
    class="app-pwa-install fixed inset-0 z-50 overflow-y-auto bg-white dark:bg-gray-950"
>
    <div class="mx-auto flex min-h-full max-w-md flex-col px-6 pt-[max(3rem,env(safe-area-inset-top))] pb-[max(1.5rem,env(safe-area-inset-bottom))]">
        <div class="flex flex-1 flex-col items-center justify-center text-center">
            <img src="{{ asset('icons/icon.svg') }}" alt="" class="size-24 rounded-3xl shadow-lg ring-1 ring-gray-950/5 dark:ring-white/10">

            <span class="mt-6 inline-flex items-center gap-1 rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-700 ring-1 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30">
                <x-filament::icon icon="heroicon-m-star" class="size-3.5" />
                {{ __('pwa.install.recommended') }}
            </span>

            <h2 id="pwa-install-title" class="mt-4 text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                {{ __('pwa.install.title', ['app' => config('app.name')]) }}
            </h2>

            <p id="pwa-install-description" class="mt-2 text-base text-gray-600 dark:text-gray-400">
                {{ __('pwa.install.description', ['app' => config('app.name')]) }}
            </p>

            <ul class="mt-8 w-full space-y-5 text-start">
                {{-- Elsewhere the notifications do not need the app to be installed: they are offered after logging in --}}
                @foreach ([
                    'home_screen' => 'heroicon-o-device-phone-mobile',
                    'own_window' => 'heroicon-o-arrows-pointing-out',
                    'ios_notifications' => 'heroicon-o-bell-alert',
                ] as $benefit => $icon)
                    <li @if ($benefit === 'ios_notifications') x-show="isIos" @endif class="flex gap-4">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-400/10 dark:text-primary-400">
                            <x-filament::icon :icon="$icon" class="size-5" />
                        </span>

                        <div>
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">
                                {{ __("pwa.install.benefits.{$benefit}.title") }}
                            </p>

                            <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">
                                {{ __("pwa.install.benefits.{$benefit}.description") }}
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="mt-10 flex flex-col gap-3">
            <p x-show="isIos" class="rounded-xl bg-gray-50 p-4 text-center text-sm text-gray-700 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                {{ __('pwa.install.ios_steps_before') }}
                <x-filament::icon icon="heroicon-o-arrow-up-on-square" class="inline size-5 align-text-bottom" :label="__('pwa.install.ios_share')" />
                {{ __('pwa.install.ios_steps_after') }}
            </p>

            <x-filament::button x-show="canPrompt" x-on:click="install()" size="xl" icon="heroicon-o-arrow-down-tray" class="w-full">
                {{ __('pwa.install.action') }}
            </x-filament::button>

            <x-filament::button x-on:click="dismiss()" size="lg" color="gray" class="w-full">
                {{ __('pwa.install.dismiss') }}
            </x-filament::button>

            <p class="text-center text-xs text-gray-500 dark:text-gray-400">
                {{ __('pwa.install.later') }}
            </p>
        </div>
    </div>
</div>
