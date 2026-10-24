<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Services\Deliveries\DeliveryRecoveryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeliveryRecoveryController extends Controller
{
    public function show(Request $request, Delivery $delivery, DeliveryRecoveryService $service)
    {
        $service->authorize($delivery, $request->user(), ! $request->user()->hasRole('admin') && ! $request->user()->hasRole('merchant'));
        $last = $delivery->statusHistories()->whereIn('to_status', ['pickup_failed', 'delivery_failed', 'rescheduled'])->latest('id')->first();

        return response()->json(['data' => ['status' => $delivery->status->value, 'reason' => $last?->reason,
            'scheduled_at' => $delivery->scheduled_at?->toIso8601String(), 'can_resume' => $delivery->status->value === 'rescheduled' && $delivery->scheduled_at?->isPast()]]);
    }

    public function fail(Request $request, Delivery $delivery, DeliveryRecoveryService $service)
    {
        $data = $request->validate(['failure_code' => ['required', Rule::in(DeliveryRecoveryService::REASONS)], 'reason' => ['required', 'string', 'min:3', 'max:500']]);

        return $this->respond($request, $service->fail($delivery, $request->user(), $data['failure_code'], $data['reason']));
    }

    public function reschedule(Request $request, Delivery $delivery, DeliveryRecoveryService $service)
    {
        if (! $request->is('api/*')) {
            $request->validate(['appointment' => ['required', 'date_format:Y-m-d\\TH:i']]);
            $request->merge(['scheduled_at' => CarbonImmutable::createFromFormat('!Y-m-d\\TH:i', $request->input('appointment'), 'Asia/Dubai')->toIso8601String()]);
        }
        $data = $request->validate(['scheduled_at' => ['required', 'date_format:Y-m-d\TH:i:sP'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);

        return $this->respond($request, $service->reschedule($delivery, $request->user(), $data['scheduled_at'], $data['reason']));
    }

    public function resume(Request $request, Delivery $delivery, DeliveryRecoveryService $service)
    {
        return $this->respond($request, $service->resume($delivery, $request->user()));
    }

    private function respond(Request $request, Delivery $delivery)
    {
        if ($request->is('api/*')) {
            return response()->json(['message' => 'Delivery updated.', 'data' => $delivery]);
        }

        return back()->with('success', app()->getLocale() === 'ar' ? 'تم تحديث التوصيل.' : 'Delivery updated.');
    }
}
