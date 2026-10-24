<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDeliveryController extends Controller
{

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => [
                'nullable',
                \Illuminate\Validation\Rule::in(
                    array_column(
                        \App\Enums\DeliveryStatus::cases(),
                        'value'
                    )
                ),
            ],
        ]);

        $deliveries = Delivery::query()
            ->with(['merchant', 'pickupAddress', 'dropoffAddress'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->query('search');

                $query->where(function ($q) use ($search) {
                    $q->where('tracking_number', 'like', "%{$search}%")
                        ->orWhere('recipient_name', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->query('status'));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.deliveries.index', compact('deliveries'));
    }


    public function show(Request $request, Delivery $delivery): View
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        $delivery->load([
            'merchant',
            'pickupAddress',
            'dropoffAddress',
            'assignments.driver.user',
            'statusHistories',
            'proofOfDelivery',
        ]);


        $availableDrivers = \App\Models\Driver::query()
            ->with('user')
            ->where('status', 'active')
            ->where('is_available', true)
            ->withCount([
                'assignments as active_deliveries_count' =>
                fn($query) => $query
                    ->whereIn('status', ['pending', 'accepted'])
                    ->whereHas(
                        'delivery',
                        fn($delivery) =>
                        $delivery->whereIn('status', [
                            'assigned',
                            'accepted',
                            'pickup',
                            'in_transit', 'pickup_failed', 'delivery_failed', 'rescheduled',
                        ])
                    ),
            ])
            ->orderBy('id')
            ->get()
            ->filter(fn ($driver) =>
                (int) $driver->active_deliveries_count
                    < (int) $driver->max_active_deliveries
            )
            ->values();


        return view('admin.deliveries.show', compact('delivery', 'availableDrivers'));
    }
}
