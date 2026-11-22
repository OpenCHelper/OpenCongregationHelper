<?php

namespace App\Notifications;

use App\Filament\Pages\Auth\ResetPassword;
use App\Models\User;
use Carbon\CarbonInterval;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Invites a user given access by an administrator to choose their own password.
 *
 * The link opens the password reset page of the app panel, but its token belongs to the `invitations` password broker,
 * so it lasts longer than the links sent from "Forgot password?" (see `ResetPassword`).
 */
class UserInvitationNotification extends Notification
{
    public function __construct(
        #[\SensitiveParameter] public string $token,
    ) {}

    /**
     * Send the invitation to a user, with a new invitation token, unless one was sent to them less than the throttle of
     * the `invitations` password broker ago (see `isInCooldown()`).
     *
     * @return bool Whether the invitation was sent.
     */
    public static function sendTo(User $user): bool
    {
        if (static::isInCooldown($user)) {
            return false;
        }

        RateLimiter::hit(static::getCooldownKey($user), static::getCooldownSeconds());

        $token = Password::broker(ResetPassword::INVITATION_PASSWORD_BROKER)->createToken($user);

        $user->notify(new self($token));

        return true;
    }

    /**
     * Whether an invitation was sent to the user too recently to send another one, whatever sent it (creating the
     * user, giving them access, correcting their email or "Resend invitation"). It is kept per user rather than per
     * token or email, so that switching the email back and forth does not send an email on every save.
     */
    public static function isInCooldown(User $user): bool
    {
        return RateLimiter::tooManyAttempts(static::getCooldownKey($user), 1);
    }

    public static function getCooldownSeconds(): int
    {
        return (int) config('auth.passwords.'.ResetPassword::INVITATION_PASSWORD_BROKER.'.throttle');
    }

    protected static function getCooldownKey(User $user): string
    {
        return 'user-invitation:'.$user->getKey();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(CanResetPassword $notifiable): MailMessage
    {
        $expiration = CarbonInterval::minutes(config('auth.passwords.'.ResetPassword::INVITATION_PASSWORD_BROKER.'.expire'))
            ->cascade()
            ->forHumans();

        return (new MailMessage)
            ->subject(__('filament-resources.mailables.invitation_subject', ['app' => config('app.name')]))
            ->greeting(__('filament-resources.mailables.greeting', ['name' => $notifiable->name]))
            ->line(__('filament-resources.mailables.invitation_body', ['app' => config('app.name')]))
            ->action(__('filament-resources.mailables.invitation_action'), $this->getUrl($notifiable))
            ->line(__('filament-resources.mailables.invitation_expiration', ['time' => $expiration]));
    }

    public function getUrl(CanResetPassword $notifiable): string
    {
        return Filament::getPanel('app')->getResetPasswordUrl($this->token, $notifiable, ['invitation' => 1]);
    }
}
