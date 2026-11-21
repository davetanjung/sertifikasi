<?php

namespace App\Models;

use App\Traits\HasAutoNumber;
use App\Traits\HasCoreFeature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rental extends Model
{
    use HasAutoNumber, HasCoreFeature;

    /**
     * Length of the daily running number in the code (RNT-YYYYMMDD-NNNN).
     */
    protected int $auto_number_length = 4; // soalnya bakal jadi 4 digit diakhir

    /**
     * The route name prefix used by HasRoute (for example `rental.index`).
     */
    protected $route_prefix = 'rental';

    /**
     * The permission name prefix used by HasCanAction.
     */
    protected $permission_prefix = 'rental';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'garage_id',
        'vehicle_id',
        'code',
        'status',
        'rental_type',
        'start_date',
        'end_date',
        'days',
        'daily_price',
        'rent_amount',
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
            'start_date' => 'date',
            'end_date' => 'date',
            'daily_price' => 'decimal:2',
            'rent_amount' => 'decimal:2',
        ];
    }

    /**
     * The code prefix is `RNT-` plus today's date plus `-`.
     *
     * @param  array<string, mixed>  $data
     */
    public function autoNumberPrefix(array $data = []): string
    {
        return 'RNT-'.now()->format('Ymd').'-';
    }

    /**
     * The tenant who made this rental.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The rented garage.
     *
     * @return BelongsTo<Garage, $this>
     */
    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    /**
     * The vehicle used for this rental.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Charging transactions made during this rental.
     *
     * @return HasMany<ChargingTransaction, $this>
     */
    public function chargingTransactions(): HasMany
    {
        return $this->hasMany(ChargingTransaction::class);
    }
}
