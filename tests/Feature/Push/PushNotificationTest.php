<?php
namespace Tests\Feature\Push;
use App\Models\Merchant;
use App\Models\Role;
use App\Models\User;
use App\Notifications\DeliveryStatusNotification;
use App\Services\Push\PushOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;
    private User $user;
    private string $bearer;
    private int $session;
    private string $project = '3b3cb10a-c7c1-4ec1-922b-70c5795dc322';
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['push.enabled' => true, 'push.project_id' => $this->project, 'push.access_token' => null]);
        Role::firstOrCreate(['name' => 'merchant'], ['display_name' => 'Merchant']);
        $this->user = Merchant::factory()->create(['status' => 'active'])->user;
        $this->user->addRole('merchant');
        $token = $this->user->createToken('phone');
        $this->bearer = $token->plainTextToken; $this->session = $token->accessToken->id;
    }
    private function register(): void
    {
        $this->withToken($this->bearer)->postJson('/api/v1/push/device', [
            'token' => 'ExpoPushToken[test_phone]', 'project_id' => $this->project,
            'session_id' => $this->session, 'platform' => 'android', 'language' => 'ar',
        ])->assertOk();
    }
    private function notification(): string
    {
        $id = (string) \Illuminate\Support\Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id, 'type' => DeliveryStatusNotification::class,
            'notifiable_type' => $this->user->getMorphClass(), 'notifiable_id' => $this->user->id,
            'data' => json_encode(['delivery_id' => 123, 'tracking_number' => 'PRIVATE_TRACKING', 'status' => 'accepted', 'history_id' => 8]),
            'read_at' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $id;
    }
    public function test_registration_is_idempotent_encrypted_and_scoped_to_current_session(): void
    {
        $this->register(); $this->register();
        $this->assertDatabaseCount('push_devices', 1);
        $row = DB::table('push_devices')->first();
        $this->assertNotSame('ExpoPushToken[test_phone]', $row->token);
        $this->assertSame('ExpoPushToken[test_phone]', decrypt($row->token));
        $this->assertSame($this->session, (int) $row->access_token_id);
        Http::assertNothingSent();
    }
    public function test_notification_lookup_never_returns_another_accounts_record(): void
    {
        $other = User::factory()->create();
        $other->notify(new DeliveryStatusNotification(999, 'SECRET', 'assigned', 8));
        $id = $other->notifications()->first()->id;
        $this->withToken($this->bearer)->getJson('/api/v1/notifications/'.$id)->assertNotFound();
        $own = $this->notification();
        $this->getJson('/api/v1/notifications/'.$own)->assertOk()->assertJsonPath('data.data.tracking_number', 'PRIVATE_TRACKING');
    }
    public function test_invalid_tokens_projects_and_session_changes_cannot_register(): void
    {
        $payload = ['token' => 'https://evil.test', 'project_id' => $this->project, 'session_id' => $this->session, 'platform' => 'android', 'language' => 'en'];
        $this->withToken($this->bearer)->postJson('/api/v1/push/device', $payload)->assertUnprocessable();
        $payload['token'] = 'ExpoPushToken[test_phone]'; $payload['session_id']++;
        $this->postJson('/api/v1/push/device', $payload)->assertStatus(409);
        $payload['session_id'] = $this->session; $payload['project_id'] = 'b8ce78be-254e-4717-a93c-5e720a3267ab';
        $this->postJson('/api/v1/push/device', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('push_devices', 0);
    }
    public function test_outbox_is_idempotent_and_ticket_is_not_claimed_as_delivery(): void
    {
        $this->register(); $id = $this->notification(); $outbox = app(PushOutbox::class);
        $outbox->enqueue($id, $this->user->id); $outbox->enqueue($id, $this->user->id);
        $this->assertDatabaseCount('push_outbox', 1);
        Http::fake([
            'exp.host/--/api/v2/push/send' => Http::response(['data' => ['status' => 'ok', 'id' => 'ticket-one']]),
            'exp.host/--/api/v2/push/getReceipts' => Http::response(['data' => ['ticket-one' => ['status' => 'ok']]]),
        ]);
        $this->assertSame(1, $outbox->run()['tickets']);
        $this->assertDatabaseHas('push_outbox', ['status' => 'ticket']);
        Http::assertSent(fn ($request) => $request['data'] === ['notification_id' => $id]
            && ! str_contains($request->body(), 'PRIVATE_TRACKING') && ! str_contains($request->body(), 'delivery_id'));
        $outbox->run(); Http::assertSentCount(1);
        $this->travel(16)->minutes();
        $this->assertSame(1, $outbox->run()['handed_off']);
        $outbox->run(); Http::assertSentCount(2);
    }
    public function test_invalid_device_ticket_expires_the_registration(): void
    {
        $this->register(); $id = $this->notification(); $outbox = app(PushOutbox::class); $outbox->enqueue($id, $this->user->id);
        Http::fake(['*' => Http::response(['data' => ['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']]])]);
        $this->assertSame(1, $outbox->run()['failed']);
        $this->assertTrue(now()->gte(DB::table('push_devices')->first()->expires_at));
    }
    public function test_transient_failure_retries_without_faking_success(): void
    {
        $this->register(); $outbox = app(PushOutbox::class); $outbox->enqueue($this->notification(), $this->user->id);
        Http::fake(['*' => Http::response([], 503)]);
        $this->assertSame(1, $outbox->run()['retry']);
        $this->assertDatabaseHas('push_outbox', ['status' => 'pending', 'attempts' => 1, 'ticket_id' => null]);
        $outbox->run(); Http::assertSentCount(1);
        $this->travel(3)->minutes(); $outbox->run(); Http::assertSentCount(2);
    }
    public function test_read_notifications_disabled_feature_and_expired_sessions_do_not_send(): void
    {
        $this->register(); $id = $this->notification(); $outbox = app(PushOutbox::class); $outbox->enqueue($id, $this->user->id);
        config(['push.enabled' => false]); $outbox->run(); Http::assertNothingSent();
        config(['push.enabled' => true]); $this->user->notifications()->find($id)->markAsRead();
        $this->assertSame(1, $outbox->run()['revoked']); Http::assertNothingSent();
        $id = $this->notification(); $outbox->enqueue($id, $this->user->id);
        $this->user->tokens()->whereKey($this->session)->update(['expires_at' => now()->subMinute()]);
        $this->assertSame(1, $outbox->run()['revoked']); Http::assertNothingSent();
    }
    public function test_disable_and_successful_logout_remove_session_devices_and_pending_messages(): void
    {
        $this->register(); app(PushOutbox::class)->enqueue($this->notification(), $this->user->id);
        $this->withToken($this->bearer)->deleteJson('/api/v1/push/device', ['session_id' => $this->session])->assertOk();
        $this->assertDatabaseCount('push_devices', 0); $this->assertDatabaseCount('push_outbox', 0);
        $this->register();
        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('push_devices', 0);
        Http::assertNothingSent();
    }

    public function test_status_listener_enqueues_once_without_network_or_impact_on_in_app_notification(): void
    {
        $this->register();
        $merchant = Merchant::where('user_id', $this->user->id)->firstOrFail();
        $delivery = \App\Models\Delivery::factory()->create(['merchant_id' => $merchant->id]);
        $history = $delivery->statusHistories()->create(['user_id' => $this->user->id, 'from_status' => 'assigned', 'to_status' => 'accepted']);
        $event = new \App\Events\DeliveryStatusChanged($delivery, $history);
        $listener = app(\App\Listeners\SendDeliveryStatusNotifications::class);
        $listener->handle($event); $listener->handle($event);
        $this->assertSame(1, $this->user->notifications()->count());
        $this->assertDatabaseCount('push_outbox', 1);
        Http::assertNothingSent();
    }

    public function test_reassigned_device_never_receives_the_previous_accounts_pending_push(): void
    {
        $this->register(); app(PushOutbox::class)->enqueue($this->notification(), $this->user->id);
        $other = Merchant::factory()->create(['status' => 'active'])->user;
        $other->addRole('merchant');
        $session = $other->createToken('other-phone-session')->accessToken;
        DB::table('push_devices')->update(['user_id' => $other->id, 'access_token_id' => $session->id]);
        $this->assertSame(1, app(PushOutbox::class)->run()['revoked']);
        Http::assertNothingSent();
    }

    public function test_receipt_device_not_registered_expires_token_without_resending_ticket(): void
    {
        $this->register(); $outbox = app(PushOutbox::class); $outbox->enqueue($this->notification(), $this->user->id);
        Http::fake([
            'exp.host/--/api/v2/push/send' => Http::response(['data' => ['status' => 'ok', 'id' => 'receipt-test']]),
            'exp.host/--/api/v2/push/getReceipts' => Http::response(['data' => ['receipt-test' => ['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']]]]),
        ]);
        $outbox->run(); $this->travel(16)->minutes();
        $this->assertSame(1, $outbox->run()['failed']);
        $outbox->run(); Http::assertSentCount(2);
        $this->assertTrue(now()->gte(DB::table('push_devices')->first()->expires_at));
    }
    public function test_recovery_notification_for_accepted_assignment_is_not_treated_as_expired_offer(): void
    {
        $this->register();
        $id = $this->notification();
        DB::table('notifications')->where('id', $id)->update(['data' => json_encode(['delivery_id' => 123, 'status' => 'rescheduled', 'assignment_id' => 999])]);
        Http::fake(['exp.host/--/api/v2/push/send' => Http::response(['data' => ['status' => 'ok', 'id' => 'recovery-ticket']])]);
        app(PushOutbox::class)->enqueue($id, $this->user->id);
        $this->assertSame(1, app(PushOutbox::class)->run()['tickets']);
        Http::assertSentCount(1);
    }

}
