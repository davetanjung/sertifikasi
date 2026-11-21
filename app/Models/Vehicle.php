<?php

namespace App\Models;

use App\Traits\HasCoreFeature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasCoreFeature;

    /**
     * The route name prefix used by HasRoute (for example `vehicle.index`).
     */
    protected $route_prefix = 'vehicle';

    /**
     * The permission name prefix used by HasCanAction.
     */
    protected $permission_prefix = 'vehicle';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'plate_number',
        'brand',
        'model',
        'maximum_capacity_kw',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'maximum_capacity_kw' => 'decimal:2',
        ];
    }

    /**
     * The tenant who owns this vehicle.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Rentals that used this vehicle.
     *
     * @return HasMany<Rental, $this>
     */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }
}
