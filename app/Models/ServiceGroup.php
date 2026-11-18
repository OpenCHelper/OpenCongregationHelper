<?php

namespace App\Models;

use Database\Factories\ServiceGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Field service group: the publishers of the congregation are gathered in groups, each
 * led by a coordinator with up to two assistants.
 */
class ServiceGroup extends Model
{
    /** @use HasFactory<ServiceGroupFactory> */
    use HasFactory;

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
        'coordinator_id',
        'assistant_coordinator1_id',
        'assistant_coordinator2_id',
    ];

    /**
     * Get the publishers belonging to this group.
     */
    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function assistantCoordinator1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assistant_coordinator1_id');
    }

    public function assistantCoordinator2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assistant_coordinator2_id');
    }
}
