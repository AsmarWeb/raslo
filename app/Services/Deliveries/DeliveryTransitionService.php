<?php

namespace App\Services\Deliveries;

use App\Enums\DeliveryStatus;
use App\Events\DeliveryStatusChanged;
use App\Models\Delivery;
use App\Models\DeliveryCommission;
use App\Models\User;
use App\Services\Integrations\DeliveryWebhookOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryTransitionService
{
    public function transition(
        Delivery $delivery,
        DeliveryStatus $to,
        User $actor,
        ?string $reason = null,
        array $metadata = []
    ): Delivery {
        return DB::transaction(function () use (
            $delivery,
            $to,
            $actor,
            $reason,
            $metadata
        ) {
            $delivery = Delivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->getKey());

            $from = $delivery->status;

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => [
                        "Invalid transition: {$from->value} -> {$to->value}",
                    ],
                ]);
            }

            if ($from === DeliveryStatus::Rescheduled) {
                if ($to === DeliveryStatus::Matching && ($delivery->picked_up_at || $delivery->assignments()->where('status', 'accepted')->exists())) {
                    throw ValidationException::withMessages(['status' => 'Resolve the assigned shipment before rematching.']);
                }
                if (in_array($to, [DeliveryStatus::Accepted, DeliveryStatus::InTransit], true)
                    && (empty($metadata['recovery_resume']) || ! $delivery->scheduled_at || $delivery->scheduled_at->isFuture()
                        || ($to === DeliveryStatus::InTransit) !== ($delivery->picked_up_at !== null))) {
                    throw ValidationException::withMessages(['status' => 'Use the authorized recovery resume action when due.']);
                }
            }

            $delivery->status = $to;

            if ($to === DeliveryStatus::Pickup) {
                $delivery->picked_up_at = now();
            }

            if ($to === DeliveryStatus::Delivered) {
                $delivery->delivered_at = now();
            }

            $delivery->save();

            // Update only an existing approval snapshot; never backfill using a later plan rate.
            if (in_array($to, [DeliveryStatus::Completed, DeliveryStatus::Cancelled], true)) {
                DeliveryCommission::query()
                    ->where('delivery_id', $delivery->id)
                    ->where('merchant_id', $delivery->merchant_id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => $to === DeliveryStatus::Completed ? 'earned' : 'cancelled',
                        'earned_at' => $to === DeliveryStatus::Completed ? now() : null,
                    ]);
            }

            if ($to === DeliveryStatus::Cancelled) {
                $delivery->codRecord()->where('status', 'expected')->update(['status' => 'cancelled']);
            }

            $history = $delivery->statusHistories()->create([
                'user_id' => $actor->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'reason' => $reason,
                'metadata' => $metadata,
            ]);

            app(DeliveryWebhookOutbox::class)->record($delivery, $history);

            DB::afterCommit(function () use ($delivery, $history) {
                event(new DeliveryStatusChanged(
                    $delivery,
                    $history
                ));
            });

            return $delivery->refresh();
        });
    }
}
