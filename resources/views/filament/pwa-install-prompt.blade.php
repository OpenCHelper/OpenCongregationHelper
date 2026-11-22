{{--
    Offer to install the Progressive Web App (see `<x-pwa-head />`) in a full-screen dialog over the pages of the app
    panel: it is the recommended way to use the app, as it brings the notifications of the app to the device.

    - Chromium browsers (Chrome, Edge, Android) fire `beforeinstallprompt`: it is captured as soon as possible, before
      Alpine starts, and the "Install" button opens the native prompt of the browser.
    - iOS has no install prompt: the steps to add the app to the home screen are shown instead.

    Nothing is shown when the app already runs installed, or for some days after the user dismissed the offer.
--}}
<script>
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        window.pwaInstallPrompt = event;
        window.dispatchEvent(new CustomEvent('pwa-installable'));
    });
</script>

<div
    x-data="{
        dismissedKey: 'pwa-install-dismissed-at',
        dismissDays: @js(\App\Providers\Filament\AppPanelProvider::PWA_INSTALL_DISMISS_DAYS),
        canPrompt: false,
        isIos: false,
        init() {
            if (this.isInstalled() || this.wasDismissedRecently()) {
                return;
            }

            const isAppleMobile = /iphone|ipad|ipod/i.test(navigator.userAgent)
                || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

            this.isIos = isAppleMobile && ! ('BeforeInstallPromptEvent' in window);
            this.canPrompt = !! window.pwaInstallPrompt;

            window.addEventListener('pwa-installable', () => this.canPrompt = true);
            window.addEventListener('appinstalled', () => this.close());
        },
        get isOpen() {
            return this.canPrompt || this.isIos;
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

            window.pwaInstallPrompt = null;
            outcome === 'accepted' ? this.close() : this.dismiss();
        },
        dismiss() {
            try {
                localStorage.setItem(this.dismissedKey, String(Date.now()));
            } catch {}

            this.close();
        },
        close() {
            this.canPrompt = false;
            this.isIos = false;
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
                @foreach ([
                    'heroicon-o-bell-alert' => 'notifications',
                    'heroicon-o-device-phone-mobile' => 'home_screen',
                ] as $icon => $benefit)
                    <li class="flex gap-4">
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
        </div>
    </div>
</div>
