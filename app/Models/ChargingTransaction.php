<?php

namespace App\Models;

use App\Traits\HasCoreFeature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargingTransaction extends Model
{
    use HasCoreFeature;

    /**
     * The route name prefix used by HasRoute (for example `charging-transaction.index`).
     */
    protected $route_prefix = 'charging-transaction';

    /**
     * The permission name prefix used by HasCanAction.
     */
    protected $permission_prefix = 'charging-transaction';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'rental_id',
        'current_capacity_kw',
        'charged_kwh',
        'price_per_kwh',
        'amount',
        'payment_proof_path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_capacity_kw' => 'decimal:2',
            'charged_kwh' => 'decimal:2',
            'price_per_kwh' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * The rental (new row) this charging belongs to.
     *
     * @return BelongsTo<Rental, $this>
     */
    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }
}
