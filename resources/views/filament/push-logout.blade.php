{{--
    Stop the push notifications of the device when the user logs out, so that a shared device does not keep receiving
    the notifications of the previous user. The server deletes the subscription on the next push (the push service
    answers that it expired), and the user can enable them again from the profile.
--}}
<script>
    document.addEventListener('submit', async (event) => {
        const form = event.target;

        if (! (form instanceof HTMLFormElement) || form.action !== @js(filament()->getLogoutUrl()) || form.dataset.pushUnsubscribed) {
            return;
        }

        event.preventDefault();

        try {
            const registration = await navigator.serviceWorker?.getRegistration();
            const subscription = await registration?.pushManager?.getSubscription();

            await subscription?.unsubscribe();
        } catch {}

        form.dataset.pushUnsubscribed = 'true';
        form.requestSubmit();
    }, true);
</script>
