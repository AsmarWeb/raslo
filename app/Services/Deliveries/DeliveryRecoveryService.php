<?php

namespace App\Services\Deliveries;

use App\Enums\DeliveryStatus as Status;
use App\Models\Delivery;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryRecoveryService
{
    public const REASONS = ['customer_unreachable', 'incorrect_address', 'customer_refused', 'store_not_ready', 'access_problem', 'other'];

    public function authorize(Delivery $delivery, User $actor, bool $driver = false): void
    {
        if ($driver) {
            abort_unless($actor->hasRole('driver'), 403);
            $assignment = $delivery->assignments()->where('status', 'accepted')->latest('id')->first();
            abort_unless($assignment && (int) $assignment->driver?->user_id === (int) $actor->id, 404);
        } else {
            abort_unless($actor->hasRole('admin') || $actor->hasRole('merchant'), 403);
            abort_unless($actor->hasRole('admin') || (int) $delivery->merchant?->user_id === (int) $actor->id, 404);
        }
    }

    public function fail(Delivery $delivery, User $actor, string $code, string $reason): Delivery
    {
        return DB::transaction(function () use ($delivery, $actor, $code, $reason) {
            $delivery = Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            $this->authorize($delivery, $actor, true);
            if (! in_array($code, self::REASONS, true) || mb_strlen(trim($reason)) < 3 || mb_strlen($reason) > 500) {
                throw ValidationException::withMessages(['reason' => 'Provide a valid failure category and details.']);
            }
            $next = match ($delivery->status) {
                Status::Accepted => Status::PickupFailed,
                Status::InTransit => Status::DeliveryFailed,
                default => null,
            };
            if ($next === Status::DeliveryFailed && ! $delivery->picked_up_at) {
                throw ValidationException::withMessages(['status' => 'Pickup record is missing; contact operations.']);
            }
            if (! $next) {
                throw ValidationException::withMessages(['status' => 'Failure can only be reported before pickup or during transit.']);
            }

            return app(DeliveryTransitionService::class)->transition($delivery, $next, $actor, trim($reason), ['failure_code' => $code]);
        });
    }

    public function reschedule(Delivery $delivery, User $actor, string $at, string $reason): Delivery
    {
        return DB::transaction(function () use ($delivery, $actor, $at, $reason) {
            $delivery = Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            $this->authorize($delivery, $actor);
            if (! in_array($delivery->status, [Status::PickupFailed, Status::DeliveryFailed], true)) {
                throw ValidationException::withMessages(['status' => 'Only failed deliveries can be rescheduled.']);
            }
            try {
                $date = CarbonImmutable::parse($at);
            } catch (\Throwable) {
                throw ValidationException::withMessages(['scheduled_at' => 'Invalid appointment.']);
            }
            if ($date->lessThanOrEqualTo(now()) || mb_strlen(trim($reason)) < 3 || mb_strlen($reason) > 500) {
                throw ValidationException::withMessages(['scheduled_at' => 'Choose a future appointment and provide a reason.']);
            }
            $assignment = $delivery->assignments()->where('status', 'accepted')->latest('id')->first();
            if (! $assignment) {
                throw ValidationException::withMessages(['assignment' => 'An accepted assignment is required; contact operations.']);
            }
            $delivery->scheduled_at = $date->utc();
            $delivery->save();

            return app(DeliveryTransitionService::class)->transition($delivery, Status::Rescheduled, $actor, trim($reason), [
                'scheduled_at' => $date->utc()->toIso8601String(), 'assignment_id' => $assignment->id,
                'recovery_from' => $delivery->status->value,
            ]);
        });
    }

    public function resume(Delivery $delivery, User $actor): Delivery
    {
        return DB::transaction(function () use ($delivery, $actor) {
            $delivery = Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            $this->authorize($delivery, $actor, true);
            if ($delivery->status !== Status::Rescheduled || ! $delivery->scheduled_at || $delivery->scheduled_at->isFuture()) {
                throw ValidationException::withMessages(['scheduled_at' => 'The rescheduled appointment is not due.']);
            }
            $history = $delivery->statusHistories()->where('to_status', 'rescheduled')->latest('id')->first();
            $assignment = $delivery->assignments()->where('status', 'accepted')->latest('id')->first();
            if ((int) ($history?->metadata['assignment_id'] ?? 0) !== $assignment->id) {
                throw ValidationException::withMessages(['assignment' => 'The recovery assignment changed; contact operations.']);
            }

            return app(DeliveryTransitionService::class)->transition($delivery,
                $delivery->picked_up_at ? Status::InTransit : Status::Accepted, $actor, 'Driver resumed the scheduled delivery', ['recovery_resume' => true]);
        });
    }
}
