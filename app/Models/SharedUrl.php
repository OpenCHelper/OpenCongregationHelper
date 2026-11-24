<?php

namespace App\Models;

use App\Settings\TerritorySettings;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SharedUrl extends Model
{
    use HasUuids;

    /**
     * Whether sharing is enabled: while it is not, no link can be created and the existing ones do not open.
     */
    public static function isSharingEnabled(): bool
    {
        return (bool) app(TerritorySettings::class)->sharing_enabled;
    }

    /**
     * Find the link that can be opened with the given identifier, or abort with a 404: while sharing is disabled, and
     * when the shared model is missing or can no longer be shared (e.g. a returned assignment), whose link is deleted.
     */
    public static function findOpenableOrFail(string $uid): self
    {
        // The links are deleted when sharing is disabled: this also covers the pages opened meanwhile
        abort_unless(static::isSharingEnabled(), 404);

        $sharedUrl = static::where('uid', $uid)->firstOrFail();
        $shareable = $sharedUrl->shareable;

        if ($shareable === null || (method_exists($shareable, 'isShareable') && ! $shareable->isShareable())) {
            $sharedUrl->delete();

            abort(404, 'Shared resource not found.');
        }

        return $sharedUrl;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uid',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'uid' => 'string',
    ];

    /**
     * Get the parent shareable model.
     */
    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uid';
    }

    public function getShareableUrl(): string
    {
        return route('share.redirect', ['uuid' => $this->uid]);
    }

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return [
            'uid',
        ];
    }
}
