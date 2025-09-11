<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Observers\CampaignObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

#[ObservedBy(CampaignObserver::class)]
class Campaign extends Model
{
    /** @use HasFactory<\Database\Factories\CampaignFactory> */
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;
    
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'short_name',
        'description',
        'color',
        'created_by',
        'valid_from',
        'valid_until',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }

    public function creator(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'created_by');
    }

    /**
     * Check if the campaign is terminated.
     */
    public function isTerminated(): bool
    {
        return CampaignStatus::fromCampaign($this) === CampaignStatus::TERMINATED;
    }

    /**
     * Get the campaign status.
     */
    public function getStatus(): CampaignStatus
    {
        return CampaignStatus::fromCampaign($this);
    }

    /**
     * Get all the assignments for the campaign.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TerritoryAssignment::class);
    }

    /**
     * Get all the territories that have been assigned to the campaign.
     */
    public function territories(): HasManyThrough
    {
        return $this->hasManyThrough(
            Territory::class,
            TerritoryAssignment::class,
            'campaign_id', // Foreign key on TerritoryAssignment table...
            'id', // Foreign key on Territory table...
            'id', // Local key on Campaign table...
            'territory_id' // Local key on TerritoryAssignment table...
        );
    }

    public function assignees(): HasManyThrough
    {
        return $this->hasManyThrough(
            User::class,
            TerritoryAssignment::class,
            'campaign_id', // Foreign key on TerritoryAssignment table...
            'id', // Foreign key on User table...
            'id', // Local key on Campaign table...
            'assignee_id' // Local key on TerritoryAssignment table...
        );
    }

    /**
     * Scope a query to include campaigns that are active.
     */
    public function scopeWithActive($query)
    {
        return $query->orWhere(function ($q) {
            $q->whereNull('valid_until')
                ->orWhere('valid_until', '>', now());
        })->where(function ($q) {
            $q->whereNull('valid_from')
                ->orWhere('valid_from', '<=', now());
        });
    }

    /**
     * Scope a query to include campaigns that are expired.
     */
    public function scopeWithExpired($query)
    {
        return $query->orWhere(function ($q) {
            $q->whereNotNull('valid_until')
                ->where('valid_until', '<', now());
        });
    }
}
