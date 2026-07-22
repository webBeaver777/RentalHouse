<?php

namespace App\Modules\Property\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Property\Domain\Enums\DeclarationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'street',
        'building_number',
        'apartment_number',
        'city',
        'postal_code',
        'country',
        'declaration_type',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'declaration_type' => DeclarationType::class,
        ];
    }

    /**
     * Get the user that owns the property.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get full address as string.
     */
    public function getFullAddressAttribute(): string
    {
        $address = $this->street . ' ' . $this->building_number;

        if ($this->apartment_number) {
            $address .= '/' . $this->apartment_number;
        }

        $address .= ', ' . $this->postal_code . ' ' . $this->city;

        return $address;
    }
}
