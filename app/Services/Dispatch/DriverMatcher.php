<?php
namespace App\Services\Dispatch;

use App\Models\Delivery;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DriverMatcher
{
    public function available(): Collection
    {
        return Driver::query()->where('status', 'active')->where('is_available', true)->where('dispatch_enabled', true)
            ->where('dispatch_seen_at', '>=', now()->subSeconds(config('dispatch.presence_seconds')))
            ->where('dispatch_location_at', '>=', now()->subSeconds(config('dispatch.location_seconds')))
            ->where('dispatch_location_at', '<=', now()->addSeconds(30))
            ->with('user.roles')->where('dispatch_seen_at', '<=', now()->addSeconds(30))
            ->whereNotNull('dispatch_latitude')->whereNotNull('dispatch_longitude')
            ->whereHas('user.roles', fn ($query) => $query->where('name', 'driver'))
            ->withCount(['assignments as dispatch_load' => fn (Builder $query) => $query
                ->whereIn('status', ['pending', 'accepted'])->whereHas('delivery', fn ($q) => $q
                    ->whereIn('status', ['assigned', 'accepted', 'pickup', 'in_transit', 'pickup_failed', 'delivery_failed', 'rescheduled']))])
            ->orderByDesc('dispatch_seen_at')->limit(500)->get()
            ->filter(fn ($driver) => $driver->dispatch_load < $driver->max_active_deliveries
                && abs((float) $driver->dispatch_latitude) <= 90 && abs((float) $driver->dispatch_longitude) <= 180)
            ->values();
    }

    public function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;
        return 6371 * 2 * asin(sqrt(min(1, max(0, $a))));
    }

    public function eligible(Driver $driver, Delivery $delivery): bool
    {
        $pickup = $delivery->pickupAddress;
        if ($delivery->scheduled_at?->isFuture() || $delivery->delivery_fee === null || $delivery->merchant?->status !== 'active' || ! $delivery->merchant?->user?->hasRole('merchant')) return false;
        if (! $pickup || $pickup->latitude === null || $pickup->longitude === null
            || abs((float) $pickup->latitude) > 90 || abs((float) $pickup->longitude) > 180) return false;
        if (! $driver->dispatch_enabled || ! $driver->hasDeliveryCapacity() || ! $driver->user?->hasRole('driver')
            || ! $driver->dispatch_seen_at || $driver->dispatch_seen_at->lt(now()->subSeconds(config('dispatch.presence_seconds')))
            || $driver->dispatch_seen_at->gt(now()->addSeconds(30))
            || ! $driver->dispatch_location_at || $driver->dispatch_location_at->lt(now()->subSeconds(config('dispatch.location_seconds')))
            || $driver->dispatch_location_at->gt(now()->addSeconds(30))
            || $driver->dispatch_latitude === null || $driver->dispatch_longitude === null
            || abs((float) $driver->dispatch_latitude) > 90 || abs((float) $driver->dispatch_longitude) > 180) return false;
        if ($delivery->assignments()->where('driver_id', $driver->id)->whereIn('status', ['rejected', 'expired'])->exists()) return false;
        return $this->distance((float) $pickup->latitude, (float) $pickup->longitude,
            (float) $driver->dispatch_latitude, (float) $driver->dispatch_longitude) <= config('dispatch.radius_km');
    }

    public function candidates(Delivery $delivery): Collection
    {
        $delivery->loadMissing(['pickupAddress', 'merchant.user.roles']);
        $pickup = $delivery->pickupAddress;
        if ($delivery->scheduled_at?->isFuture() || $delivery->delivery_fee === null || $delivery->merchant?->status !== 'active' || ! $delivery->merchant?->user?->hasRole('merchant') || ! $pickup
            || $pickup->latitude === null || $pickup->longitude === null
            || abs((float) $pickup->latitude) > 90 || abs((float) $pickup->longitude) > 180) return collect();
        $excluded = $delivery->assignments()->whereIn('status', ['rejected', 'expired'])->pluck('driver_id')->all();
        return $this->available()->reject(fn ($driver) => in_array($driver->id, $excluded, true))
            ->map(function ($driver) use ($pickup) {
                $distance = $this->distance((float) $pickup->latitude, (float) $pickup->longitude,
                    (float) $driver->dispatch_latitude, (float) $driver->dispatch_longitude);
                return ['driver' => $driver, 'distance_km' => round($distance, 2),
                    'score' => $distance + (int) $driver->dispatch_load * 5, 'distance' => $distance];
            })->filter(fn ($row) => $row['distance'] <= config('dispatch.radius_km'))
            ->sort(fn ($a, $b) => ($a['score'] <=> $b['score']) ?: ($a['driver']->id <=> $b['driver']->id))->take(5)->values();
    }
}
