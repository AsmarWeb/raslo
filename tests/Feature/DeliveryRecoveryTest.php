<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Events\DeliveryStatusChanged;
use App\Listeners\SendDeliveryStatusNotifications;
use App\Models\CatalogConnection;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\IntegrationDeliveryOrder;
use App\Models\IntegrationDeliveryWebhook;
use App\Models\Role;
use App\Models\User;
use App\Services\Deliveries\DeliveryRecoveryService;
use App\Services\Deliveries\DeliveryTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DeliveryRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        Role::firstOrCreate(['name' => $role], ['display_name' => $role]);
        $user = User::factory()->create();
        $user->addRole($role);

        return $user;
    }

    private function job(string $status = 'in_transit'): array
    {
        $user = $this->user('driver');
        $driver = Driver::factory()->create(['user_id' => $user->id, 'max_active_deliveries' => 1, 'status' => 'active', 'is_available' => true]);
        $delivery = Delivery::factory()->create(['status' => $status, 'picked_up_at' => $status === 'in_transit' ? now() : null]);
        $assignment = DeliveryAssignment::create(['delivery_id' => $delivery->id, 'driver_id' => $driver->id, 'status' => 'accepted', 'assigned_at' => now(), 'accepted_at' => now()]);

        return [$user, $driver, $delivery, $assignment];
    }

    public function test_delivery_failure_reschedule_and_resume_preserve_custody_fee_and_assignment(): void
    {
        [$user, $driver, $delivery, $assignment] = $this->job();
        $fee = $delivery->delivery_fee;
        $service = app(DeliveryRecoveryService::class);
        $failed = $service->fail($delivery, $user, 'customer_unreachable', 'Customer did not answer');
        $this->assertSame(DeliveryStatus::DeliveryFailed, $failed->status);
        $this->assertFalse($driver->fresh()->hasDeliveryCapacity());
        $this->assertSame('customer_unreachable', $failed->statusHistories()->latest('id')->first()->metadata['failure_code']);
        $date = now()->addHours(2);
        $booked = $service->reschedule($failed, $this->user('admin'), $date->toIso8601String(), 'Customer confirmed new time');
        $this->assertSame(DeliveryStatus::Rescheduled, $booked->status);
        $this->travelTo($date->addMinute());
        $resumed = $service->resume($booked, $user);
        $this->assertSame(DeliveryStatus::InTransit, $resumed->status);
        $this->assertSame($fee, $resumed->delivery_fee);
        $this->assertSame('accepted', $assignment->fresh()->status);
        $this->assertSame(1, $resumed->assignments()->count());
    }

    public function test_pickup_failure_resumes_before_pickup_and_cannot_be_reported_twice(): void
    {
        [$user, , $delivery] = $this->job('accepted');
        $service = app(DeliveryRecoveryService::class);
        $failed = $service->fail($delivery, $user, 'store_not_ready', 'Parcel not ready');
        $this->assertSame(DeliveryStatus::PickupFailed, $failed->status);
        try {
            $service->fail($failed, $user, 'other', 'Repeated click');
            $this->fail('Duplicate should fail');
        } catch (ValidationException) {
        }
        $this->assertSame(1, $failed->statusHistories()->count());
        $booked = $service->reschedule($failed, $this->user('admin'), now()->addHour()->toIso8601String(), 'Store confirmed');
        $this->travel(2)->hours();
        $this->assertSame(DeliveryStatus::Accepted, $service->resume($booked, $user)->status);
    }

    public function test_resume_is_blocked_before_due_time_and_rematching_is_blocked(): void
    {
        [$user, , $delivery] = $this->job();
        $admin = $this->user('admin');
        $service = app(DeliveryRecoveryService::class);
        $failed = $service->fail($delivery, $user, 'incorrect_address', 'Customer gave incorrect address');
        $booked = $service->reschedule($failed, $admin, now()->addDay()->toIso8601String(), 'New appointment agreed');
        foreach ([fn () => $service->resume($booked, $user), fn () => app(DeliveryTransitionService::class)->transition($booked, DeliveryStatus::Matching, $admin)] as $action) {
            try {
                $action();
                $this->fail('Unsafe transition must fail');
            } catch (ValidationException) {
            }
        }
        $this->assertSame(DeliveryStatus::Rescheduled, $booked->fresh()->status);
    }

    public function test_api_enforces_ownership_reason_and_future_offset_appointment(): void
    {
        [$user, , $delivery] = $this->job();
        $base = '/api/v1/deliveries/'.$delivery->id.'/recovery';
        $other = $this->user('driver');
        $this->actingAs($other)->postJson($base.'/fail', ['failure_code' => 'other', 'reason' => 'Access blocked'])->assertNotFound();
        $this->actingAs($user)->postJson($base.'/fail', ['failure_code' => 'other', 'reason' => ' '])->assertUnprocessable();
        $this->postJson($base.'/fail', ['failure_code' => 'customer_refused', 'reason' => 'Customer refused parcel'])->assertOk();
        $this->postJson($base.'/reschedule', ['scheduled_at' => now()->addDay()->toIso8601String(), 'reason' => 'New time agreed'])->assertForbidden();
        $admin = $this->user('admin');
        $this->actingAs($admin)->postJson($base.'/reschedule', ['scheduled_at' => now()->subDay()->toIso8601String(), 'reason' => 'Past time'])->assertUnprocessable();
        $this->postJson($base.'/reschedule', ['scheduled_at' => now()->addDay()->timezone('Asia/Dubai')->format('Y-m-d\TH:i:sP'), 'reason' => 'Agreed Dubai appointment'])->assertOk();
        $this->getJson($base)->assertOk()->assertJsonPath('data.reason', 'Agreed Dubai appointment');
        $this->actingAs($other)->getJson($base)->assertNotFound();
    }

    public function test_merchant_can_only_reschedule_own_delivery_and_web_dubai_time_is_utc(): void
    {
        [$driver, , $delivery] = $this->job('accepted');
        app(DeliveryRecoveryService::class)->fail($delivery, $driver, 'store_not_ready', 'Please return later');
        $merchant = $delivery->merchant->user;
        Role::firstOrCreate(['name' => 'merchant'], ['display_name' => 'Merchant']);
        $merchant->addRole('merchant');
        $at = now()->addDay()->timezone('Asia/Dubai')->startOfMinute();
        $this->actingAs($merchant)->post(route('delivery.recovery.reschedule', $delivery), ['appointment' => $at->format('Y-m-d\TH:i'), 'reason' => 'Store will be ready'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($at->utc()->format('Y-m-d H:i'), $delivery->fresh()->scheduled_at->format('Y-m-d H:i'));
        $this->actingAs($this->user('merchant'))->getJson('/api/v1/deliveries/'.$delivery->id.'/recovery')->assertNotFound();
    }

    public function test_failed_and_rescheduled_events_reach_all_owners_once_without_private_details(): void
    {
        [$user, , $delivery] = $this->job();
        $admin = $this->user('admin');
        $connection = CatalogConnection::create(['merchant_id' => $delivery->merchant_id, 'name' => 'Shop', 'mode' => 'feed', 'status' => 'active',
            'key_hash' => hash('sha256', 'test'), 'key_prefix' => 'raslo_', 'scopes' => [], 'delivery_webhook_url' => 'https://shop.example/events', 'delivery_webhook_secret' => 'test-secret']);
        IntegrationDeliveryOrder::create(['id' => (string) Str::uuid(), 'catalog_connection_id' => $connection->id, 'delivery_id' => $delivery->id,
            'external_order_id' => 'wc:42', 'external_order_hash' => hash('sha256', 'wc:42'), 'idempotency_hash' => hash('sha256', 'key'),
            'payload_hash' => hash('sha256', 'payload'), 'payload' => [], 'tracking_token' => 'token', 'tracking_hash' => hash('sha256', 'token')]);
        $service = app(DeliveryRecoveryService::class);
        $service->fail($delivery, $user, 'customer_unreachable', 'Private recipient issue');
        $history = $delivery->statusHistories()->latest('id')->first();
        $event = new DeliveryStatusChanged($delivery->fresh(), $history);
        $listener = app(SendDeliveryStatusNotifications::class);
        $listener->handle($event);
        $listener->handle($event);
        foreach ([$user, $admin, $delivery->merchant->user] as $recipient) {
            $this->assertSame(1, $recipient->notifications()->count());
            $this->assertStringNotContainsString('Private recipient issue', json_encode($recipient->notifications()->first()->data));
        }
        $notification = $user->notifications()->first();
        $this->assertNotNull($notification->data['assignment_id']);
        $this->assertSame(1, IntegrationDeliveryWebhook::count());
        $service->reschedule($delivery->fresh(), $admin, now()->addHour()->toIso8601String(), 'New private instruction');
        $this->assertSame(2, IntegrationDeliveryWebhook::count());
        $this->assertSame('rescheduled', IntegrationDeliveryWebhook::orderByDesc('delivery_status_history_id')->first()->payload['data']['status']);
    }
    public function test_web_panels_render_for_driver_merchant_and_admin_with_escaped_reason(): void
    {
        [$driver, , $delivery, $assignment] = $this->job();
        app(DeliveryRecoveryService::class)->fail($delivery, $driver, 'other', '<script>alert(1)</script>');
        $this->actingAs($driver)->get(route('driver.assignments.show', $assignment))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $merchant = $delivery->merchant->user;
        Role::firstOrCreate(['name' => 'merchant'], ['display_name' => 'Merchant']);
        $merchant->addRole('merchant');
        $this->actingAs($merchant)->get(route('merchant.deliveries.show', $delivery))->assertOk()
            ->assertSee('name="appointment"', false);
        $this->actingAs($this->user('admin'))->get(route('admin.deliveries.show', $delivery))->assertOk()
            ->assertSee('name="appointment"', false);
    }

}
