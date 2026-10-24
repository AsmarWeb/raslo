<?php

namespace App\Listeners;

use App\Enums\DeliveryStatus;
use App\Events\DeliveryStatusChanged;
use App\Models\DeliveryAssignment;
use App\Models\User;
use App\Notifications\DeliveryStatusNotification;
use Illuminate\Support\Facades\DB;

class SendDeliveryStatusNotifications
{
    /**
     * This listener writes database notifications after the transaction commits.
     * No queue worker is required for this first, database-only release.
     */
    public function handle(DeliveryStatusChanged $event): void
    {
        $status = $event->history->to_status;

        if ($status instanceof DeliveryStatus) {
            $status = $status->value;
        }
        if (! in_array($status, [
            DeliveryStatus::Priced->value,
            DeliveryStatus::Assigned->value,
            DeliveryStatus::Matching->value,
            DeliveryStatus::Accepted->value,
            DeliveryStatus::Pickup->value,
            DeliveryStatus::InTransit->value,
            DeliveryStatus::Delivered->value,
            DeliveryStatus::Completed->value,
            DeliveryStatus::Cancelled->value,
            DeliveryStatus::PickupFailed->value, DeliveryStatus::DeliveryFailed->value, DeliveryStatus::Rescheduled->value,
        ], true)) {
            return;
        }

        $delivery = $event->delivery;
        $assignment = $status === DeliveryStatus::Assigned->value
            ? DeliveryAssignment::query()->where('delivery_id', $delivery->getKey())
                ->when($event->history->metadata['assignment_id'] ?? null,
                    fn ($query, $id) => $query->whereKey($id),
                    fn ($query) => $query->where('status', 'pending'))
                ->latest('id')->first()
            : null;
        $recovery = in_array($status, ['pickup_failed', 'delivery_failed', 'rescheduled'], true);
        if ($recovery) {
            $assignment = $delivery->assignments()->where('status', 'accepted')->latest('id')->first();
        }
        $recipientIds = [];
        $merchantUserId = $delivery->merchant()->value('user_id');
        if ($merchantUserId !== null) {
            $recipientIds[] = (int) $merchantUserId;
        }

        if ($status === DeliveryStatus::Assigned->value || $recovery) {
            $driverUserId = $assignment?->driver?->user_id;
            if ($driverUserId !== null) {
                $recipientIds[] = (int) $driverUserId;
            }
        }

        if ($recovery) {
            $recipientIds = array_merge($recipientIds, User::whereHas('roles', fn ($query) => $query->where('name', 'admin'))->pluck('id')->all());
        }
        foreach (array_unique($recipientIds) as $userId) {
            $user = User::query()->find($userId);
            if ($user === null) {
                continue;
            }

            // Inserting a stable ID makes repeated event handling idempotent.
            // The notifications.id column is unique in Laravel's default migration.
            $id = \Ramsey\Uuid\Uuid::uuid5(
                \Ramsey\Uuid\Uuid::NAMESPACE_URL,
                'delivery-status:' . $event->history->getKey() . ':user:' . $userId
            )->toString();
            DB::transaction(function () use ($id, $user, $delivery, $status, $event, $assignment, $userId) {
                DB::table('notifications')->insertOrIgnore([
                    'id' => $id,
                    'type' => DeliveryStatusNotification::class,
                    'notifiable_type' => $user->getMorphClass(),
                    'notifiable_id' => $user->getKey(),
                    'data' => json_encode([
                        'delivery_id' => $delivery->getKey(),
                        'tracking_number' => $delivery->tracking_number,
                        'status' => $status,
                        'history_id' => $event->history->getKey(),
                        // A driver opens the offer, never another driver's later assignment.
                        'assignment_id' => $assignment?->driver?->user_id === $userId ? $assignment->id : null,
                    ], JSON_THROW_ON_ERROR),
                    'read_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                app(\App\Services\Push\PushOutbox::class)->enqueue($id, $userId);
            });
        }
    }
}
