<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'dispatch_enabled', 'dispatch_seen_at', 'dispatch_latitude', 'dispatch_longitude', 'dispatch_location_at',
        'phone',
        'vehicle_type',
        'vehicle_plate',
        'license_number',
        'status',
        'is_available',
        'current_latitude',
        'current_longitude',
        'last_location_at',
        'max_active_deliveries',
        'work_status',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'dispatch_enabled' => 'boolean',
            'dispatch_seen_at' => 'datetime', 'dispatch_location_at' => 'datetime',
            'dispatch_latitude' => 'decimal:7', 'dispatch_longitude' => 'decimal:7',
            'current_latitude' => 'decimal:7',
            'current_longitude' => 'decimal:7',
            'last_location_at' => 'datetime',
            'max_active_deliveries' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DeliveryAssignment::class);
    }

    public function activeDeliveriesCount(): int
    {
        return $this->assignments()
            ->whereIn('status', ['pending', 'accepted'])
            ->whereHas('delivery', function ($query) {
                $query->whereIn('status', [
                    'assigned',
                    'accepted',
                    'pickup',
                    'in_transit', 'pickup_failed', 'delivery_failed', 'rescheduled',
                ]);
            })
            ->count();
    }

    public function hasDeliveryCapacity(): bool
    {
        return $this->status === 'active'
            && $this->is_available
            && $this->activeDeliveriesCount()
            < $this->max_active_deliveries;
    }
}
