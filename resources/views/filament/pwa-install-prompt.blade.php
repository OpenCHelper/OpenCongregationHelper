{{--
    Offer to install the Progressive Web App (see `<x-pwa-head />`), at the bottom of the pages of the app panel.

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
    role="dialog"
    aria-labelledby="pwa-install-title"
    class="app-pwa-install fixed inset-x-4 bottom-24 z-40 mx-auto max-w-md lg:bottom-4 rounded-xl bg-white p-4 shadow-lg ring-1 ring-gray-950/5 sm:inset-x-auto sm:right-4 sm:mx-0 sm:w-96 dark:bg-gray-900 dark:ring-white/10"
>
    <div class="flex items-start gap-3">
        <img src="{{ asset('icons/icon.svg') }}" alt="" class="size-10 shrink-0 rounded-lg">

        <div class="min-w-0 flex-1">
            <p id="pwa-install-title" class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('pwa.install.title', ['app' => config('app.name')]) }}
            </p>

            <p x-show="! isIos" class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('pwa.install.description') }}
            </p>

            <p x-show="isIos" class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('pwa.install.ios_steps_before') }}
                <x-filament::icon icon="heroicon-o-arrow-up-on-square" class="inline size-4 align-text-bottom" :label="__('pwa.install.ios_share')" />
                {{ __('pwa.install.ios_steps_after') }}
            </p>

            <div class="mt-3 flex flex-wrap gap-2">
                <x-filament::button x-show="canPrompt" x-on:click="install()" size="sm" icon="heroicon-o-arrow-down-tray">
                    {{ __('pwa.install.action') }}
                </x-filament::button>

                <x-filament::button x-on:click="dismiss()" size="sm" color="gray" outlined>
                    {{ __('pwa.install.dismiss') }}
                </x-filament::button>
            </div>
        </div>
    </div>
</div>
