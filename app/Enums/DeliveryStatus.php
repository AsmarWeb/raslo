<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Draft = 'draft';
    case PendingPricing = 'pending_pricing';
    case Priced = 'priced';
    case Matching = 'matching';
    case Assigned = 'assigned';
    case Accepted = 'accepted';
    case Pickup = 'pickup';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Completed = 'completed';

    case Cancelled = 'cancelled';
    case PickupFailed = 'pickup_failed';
    case DeliveryFailed = 'delivery_failed';
    case Rescheduled = 'rescheduled';
    case Expired = 'expired';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [
                self::PendingPricing,
                self::Cancelled,
            ],

            self::PendingPricing => [
                self::Priced,
                self::Cancelled,
            ],

            self::Priced => [
                self::Matching,
                self::Cancelled,
            ],

            self::Matching => [
                self::Assigned,
                self::Expired,
                self::Cancelled,
            ],

            self::Assigned => [
                self::Accepted,
                self::Matching,
                self::Cancelled,
            ],

            self::Accepted => [
                self::PickupFailed,
                self::Pickup,
                self::Cancelled,
            ],

            self::Pickup => [
                self::InTransit,
                self::PickupFailed,
            ],

            self::InTransit => [
                self::Delivered,
                self::DeliveryFailed,
            ],

            self::Delivered => [
                self::Completed,
            ],

            self::PickupFailed,
            self::DeliveryFailed => [
                self::Rescheduled,
                self::Cancelled,
            ],

            self::Rescheduled => [self::Accepted, self::InTransit, self::Matching, self::Cancelled],
            self::Expired => [
                self::Matching,
                self::Cancelled,
            ],

            self::Completed,
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array(
            $status,
            $this->allowedTransitions(),
            true
        );
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Cancelled,
        ], true);
    }
}
