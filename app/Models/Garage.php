<?php

namespace App\Models;

use App\Traits\HasAutoNumber;
use App\Traits\HasCoreFeature;
use App\Utilities\Constants\Const_Type;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Garage extends Model
{
    use HasAutoNumber, HasCoreFeature;

    /**
     * Length of the running number in the code (S001, C001, E001).
     */
    protected int $auto_number_length = 3;

    /**
     * The route name prefix used by HasRoute
     */
    protected $route_prefix = 'garage';

    /**
     * The permission name prefix used by HasCanAction.
     */
    protected $permission_prefix = 'garage';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'type',
        'daily_price',
        'price_per_kwh',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'daily_price' => 'decimal:2',
            'price_per_kwh' => 'decimal:2',
        ];
    }

    /**
     * The code prefix depends on the garage type: S, C or E.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public function autoNumberPrefix(array $data = []): string
    {
        return match ($this->type) {
            Const_Type::GARAGE_BICYCLE => 'S',
            Const_Type::GARAGE_CAR => 'C',
            Const_Type::GARAGE_ELECTRIC_CAR => 'E',
            default => throw new InvalidArgumentException('Unknown garage type: '.(string) $this->type),
        };
    }

    /**
     * Rentals (new and extension rows) booked on this garage.
     *
     * @return HasMany<Rental, $this>
     */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    /**
     * Whether the garage is an electric car garage.
     */
    public function isElectric(): bool
    {
        return $this->type === Const_Type::GARAGE_ELECTRIC_CAR;
    }
}
