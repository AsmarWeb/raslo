<?php
namespace App\Services\Push;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\PersonalAccessToken;

class PushOutbox
{
    public function enqueue(string $notificationId, int $userId): void
    {
        if (! config('push.enabled')) return;
        foreach (DB::table('push_devices')->where('user_id', $userId)->where('expires_at', '>', now())->get() as $device) {
            DB::table('push_outbox')->insertOrIgnore([
                'device_id' => $device->id, 'user_id' => $userId, 'notification_id' => $notificationId,
                'status' => 'pending', 'attempts' => 0, 'next_attempt_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
    public function run(): array
    {
        $counts = ['tickets' => 0, 'handed_off' => 0, 'failed' => 0, 'revoked' => 0, 'retry' => 0];
        if (! config('push.enabled') || ! filled(config('push.project_id'))) return $counts;
        DB::table('push_outbox')->whereIn('status', ['handed_off', 'failed', 'revoked'])->where('updated_at', '<', now()->subDays(30))->delete();
        DB::table('push_devices')->where('expires_at', '<', now()->subDays(7))->delete();
        $ids = DB::table('push_outbox')->whereIn('status', ['pending', 'ticket'])->where('next_attempt_at', '<=', now())
            ->orderBy('next_attempt_at')->orderBy('id')->limit(20)->pluck('id');
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$counts) {
                $row = DB::table('push_outbox')->where('id', $id)->lockForUpdate()->first();
                if (! $row || ! in_array($row->status, ['pending', 'ticket']) || now()->lt($row->next_attempt_at)) return;
                $device = DB::table('push_devices')->where('id', $row->device_id)->lockForUpdate()->first();
                $user = User::find($row->user_id);
                $session = $device ? PersonalAccessToken::find($device->access_token_id) : null;
                $notification = $user?->notifications()->find($row->notification_id);
                $sessionExpired = $session && (($session->expires_at && $session->expires_at->isPast())
                    || (config('sanctum.expiration') && $session->created_at->lte(now()->subMinutes(config('sanctum.expiration')))));
                $merchant = \App\Models\Merchant::where('user_id', $row->user_id)->first();
                $driver = \App\Models\Driver::where('user_id', $row->user_id)->first();
                $approved = ($user?->hasRole('merchant') && $merchant?->status === 'active')
                    || ($user?->hasRole('driver') && $driver?->status === 'active');
                if (! $device || (int) $device->user_id !== (int) $row->user_id || ! $session || $sessionExpired
                    || (int) $session->tokenable_id !== (int) $row->user_id || $session->tokenable_type !== $user?->getMorphClass()
                    || now()->gte($device->expires_at) || ! $approved || ! $notification || $notification->read_at) {
                    $this->save($row->id, ['status' => 'revoked']); $counts['revoked']++; return;
                }
                $assignmentId = $notification->data['assignment_id'] ?? null;
                if ($row->status === 'pending' && ($notification->data['status'] ?? null) === 'assigned' && $assignmentId && ! \App\Models\DeliveryAssignment::whereKey($assignmentId)->where('status', 'pending')->exists()) {
                    $this->save($row->id, ['status' => 'revoked']); $counts['revoked']++; return;
                }
                // Never send an old offer late; other updates also have a short useful lifetime.
                if ($row->status === 'pending' && $notification->created_at->diffInMinutes(now()) > 5) {
                    $this->save($row->id, ['status' => 'failed', 'error_code' => 'Expired']); $counts['failed']++; return;
                }
                $attempt = $row->attempts + 1;
                try {
                    $http = Http::acceptJson()->connectTimeout(2)->timeout(5);
                    if (filled(config('push.access_token'))) $http = $http->withToken(config('push.access_token'));
                    if ($row->status === 'pending') {
                        $ar = $device->language === 'ar';
                        $response = $http->post('https://exp.host/--/api/v2/push/send', [
                            'to' => decrypt($device->token), 'title' => 'Raslo',
                            'body' => $ar ? 'لديك تحديث جديد. افتح التطبيق لعرض التفاصيل.' : 'You have a new update. Open the app for details.',
                            'sound' => 'default', 'channelId' => 'deliveries', 'ttl' => 120,
                            'data' => ['notification_id' => $row->notification_id],
                        ]);
                        $result = $response->json('data');
                        if (isset($result[0])) $result = $result[0];
                    } else {
                        $response = $http->post('https://exp.host/--/api/v2/push/getReceipts', ['ids' => [$row->ticket_id]]);
                        $result = $response->json('data.'.$row->ticket_id);
                    }
                    if ($response->status() === 429 || $response->serverError()) throw new \RuntimeException('Transient');
                    if (! $response->successful()) { $this->save($id, ['status' => 'failed', 'error_code' => 'ProviderHttp']); $counts['failed']++; return; }
                    if (is_array($result) && ($result['status'] ?? null) === 'ok') {
                        if ($row->status === 'pending' && is_string($result['id'] ?? null)) {
                            $this->save($id, ['status' => 'ticket', 'ticket_id' => $result['id'], 'ticket_at' => now(), 'attempts' => 0, 'next_attempt_at' => now()->addMinutes(15)]);
                            $counts['tickets']++; return;
                        }
                        if ($row->status === 'ticket') {
                            $this->save($id, ['status' => 'handed_off']); $counts['handed_off']++; return;
                        }
                    }
                    $code = is_array($result) ? ($result['details']['error'] ?? null) : null;
                    if ($code === 'DeviceNotRegistered') {
                        $this->save($id, ['status' => 'failed', 'error_code' => 'DeviceNotRegistered']);
                        DB::table('push_devices')->where('id', $device->id)->update(['expires_at' => now()]);
                        $counts['failed']++; return;
                    }
                    if ($code && $code !== 'MessageRateExceeded') {
                        $this->save($id, ['status' => 'failed', 'error_code' => 'ProviderRejected']); $counts['failed']++; return;
                    }
                    throw new \RuntimeException('MissingResult');
                } catch (\Throwable) {
                    $limit = $row->status === 'ticket' ? 8 : 4;
                    $this->save($row->id, ['attempts' => $attempt, 'status' => $attempt >= $limit ? 'failed' : $row->status,
                        'error_code' => 'RetryableOrUnknown', 'next_attempt_at' => now()->addMinutes(min(60, 2 ** $attempt))]);
                    $counts[$attempt >= $limit ? 'failed' : 'retry']++;
                }
            });
        }
        return $counts;
    }
    private function save(int $id, array $values): void
    {
        DB::table('push_outbox')->where('id', $id)->update($values + ['updated_at' => now()]);
    }
}
