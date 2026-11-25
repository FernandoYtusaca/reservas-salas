<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'capacity',
        'opens_at',
        'closes_at',
        'max_booking_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'max_booking_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected function opensAt(): Attribute
    {
        return Attribute::get(fn (string $value) => substr($value, 0, 5));
    }

    protected function closesAt(): Attribute
    {
        return Attribute::get(fn (string $value) => substr($value, 0, 5));
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
