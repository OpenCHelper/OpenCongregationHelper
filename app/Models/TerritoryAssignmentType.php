<?php

namespace App\Models;

use App\Observers\TerritoryAssignmentTypeObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

#[ObservedBy(TerritoryAssignmentTypeObserver::class)]
class TerritoryAssignmentType extends Model
{
    /** @use HasFactory<\Database\Factories\TerritoryAssignmentTypeFactory> */
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

    public function creator(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'created_by');
    }
}
