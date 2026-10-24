@extends('layouts.dashboard')

@section('title', app()->getLocale() === 'ar' ? 'تفاصيل الشحنة' : 'Delivery Details')
@section('page-title', app()->getLocale() === 'ar' ? 'تفاصيل الشحنة' : 'Delivery Details')

@section('content')
    @php
        $ar = app()->getLocale() === 'ar';
        $status = $delivery->status->value;
        $statusKey = 'dashboard.statuses.' . $status;
        $statusLabel = __($statusKey);

        if ($statusLabel === $statusKey) {
            $statusLabel = ucwords(str_replace('_', ' ', $status));
        }

        $date = fn($value) => $value ? $value->copy()->timezone('Asia/Dubai')->format('Y-m-d H:i') : '—';

        $proof = $delivery->proofOfDelivery;
        $acceptedAssignment = $delivery->assignments->firstWhere('status', 'accepted');

        $canComplete = $status === 'delivered' && $acceptedAssignment !== null;
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="{{ route('admin.deliveries.index') }}" class="text-sm text-blue-400 hover:text-blue-300">
                    ← {{ $ar ? 'العودة إلى الشحنات' : 'Back to deliveries' }}
                </a>

                <h1 class="mt-3 text-2xl font-bold text-white">
                    {{ $ar ? 'تفاصيل الشحنة' : 'Delivery Details' }}
                </h1>

                <p class="mt-2 font-mono text-sm text-slate-400" dir="ltr">
                    {{ $delivery->tracking_number }}
                </p>
            </div>

            <span class="rounded-full bg-blue-500/10 px-4 py-2 text-sm font-semibold text-blue-300">
                {{ $statusLabel }}
            </span>
        </div>

        @if (session('success'))
            <div role="status" class="rounded-xl border border-emerald-700 bg-emerald-950 p-4 text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-700 bg-red-950 p-4 text-red-200">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([[$ar ? 'الحالة' : 'Status', $statusLabel], [$ar ? 'رسوم التوصيل' : 'Delivery Fee', number_format((float) $delivery->delivery_fee, 2) . ' ' . $delivery->currency], [$ar ? 'تاريخ الإنشاء' : 'Created', $date($delivery->created_at)], [$ar ? 'تاريخ التسليم' : 'Delivered', $date($delivery->delivered_at)]] as [$label, $value])
                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                    <p class="text-xs text-slate-400">{{ $label }}</p>
                    <p class="mt-3 font-semibold text-white">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 lg:grid-cols-2">

            <section class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
                <h2 class="mb-5 text-lg font-bold text-white">
                    {{ $ar ? 'التاجر' : 'Merchant' }}
                </h2>

                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-slate-400">{{ $ar ? 'اسم الشركة' : 'Business Name' }}</dt>
                        <dd class="mt-1 text-white">
                            {{ $delivery->merchant?->business_name ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">{{ $ar ? 'البريد الإلكتروني' : 'Email' }}</dt>
                        <dd class="mt-1 break-all text-white">
                            {{ $delivery->merchant?->business_email ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
                <h2 class="mb-5 text-lg font-bold text-white">
                    {{ $ar ? 'المستلم والطرد' : 'Recipient & Package' }}
                </h2>

                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-slate-400">{{ $ar ? 'المستلم' : 'Recipient' }}</dt>
                        <dd class="mt-1 text-white">{{ $delivery->recipient_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">{{ $ar ? 'الهاتف' : 'Phone' }}</dt>
                        <dd class="mt-1 text-white" dir="ltr">{{ $delivery->recipient_phone ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">{{ $ar ? 'نوع الطرد' : 'Package Type' }}</dt>
                        <dd class="mt-1 text-white">{{ $delivery->package_type ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">{{ $ar ? 'الوزن' : 'Weight' }}</dt>
                        <dd class="mt-1 text-white">
                            {{ $delivery->weight !== null ? $delivery->weight . ' kg' : '—' }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
                <h2 class="mb-5 text-lg font-bold text-white">
                    {{ $ar ? 'عناوين التوصيل' : 'Delivery Addresses' }}
                </h2>

                @foreach ([[$ar ? 'الاستلام' : 'Pickup', $delivery->pickupAddress], [$ar ? 'التسليم' : 'Drop-off', $delivery->dropoffAddress]] as [$label, $address])
                    <div class="mb-5 border-s-2 border-blue-500 ps-4">
                        <h3 class="text-sm font-semibold text-blue-300">{{ $label }}</h3>
                        <p class="mt-2 text-sm text-white">
                            {{ $address?->address_line ?? '—' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ collect([$address?->area, $address?->city, $address?->country])->filter()->implode(', ') }}
                        </p>
                    </div>
                @endforeach
            </section>

            <section class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
                <h2 class="mb-5 text-lg font-bold text-white">
                    {{ $ar ? 'السائق والمهمة' : 'Driver & Assignment' }}
                </h2>

                @forelse($delivery->assignments as $assignment)
                    <div class="mb-3 rounded-xl bg-slate-800 p-4 text-sm">
                        <p class="font-semibold text-white">
                            {{ $assignment->driver?->user?->name ?? ($ar ? 'السائق' : 'Driver') }}
                        </p>
                        <p class="mt-2 text-slate-300">
                            {{ $ar ? 'حالة المهمة' : 'Assignment Status' }}:
                            {{ $assignment->status }}
                        </p>
                        <p class="mt-1 text-slate-400">
                            {{ $date($assignment->assigned_at) }}
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">
                        {{ $ar ? 'لم يتم تعيين سائق' : 'No driver assigned' }}
                    </p>
                @endforelse
            </section>
        </div>


        {{-- Admin Dispatch Panel --}}

        @if ($status === 'pending_pricing')
            <section class="rounded-2xl border border-blue-800 bg-slate-900 p-6">
                <h2 class="mb-4 text-lg font-bold text-white">
                    {{ $ar ? 'تسعير طلب التوصيل' : 'Delivery Pricing' }}
                </h2>

                <form method="POST" action="{{ route('admin.deliveries.price', $delivery) }}" class="space-y-4">
                    @csrf

                    <label class="block text-sm text-slate-300">
                        {{ $ar ? 'رسوم التوصيل' : 'Delivery Fee' }}
                    </label>

                    <input type="number" name="delivery_fee" min="0" step="0.01" required
                        value="{{ old('delivery_fee') }}"
                        class="w-full rounded-xl border border-slate-700 bg-slate-800 p-3 text-white">

                    <button type="submit" class="rounded-xl bg-blue-600 px-6 py-3 font-bold text-white hover:bg-blue-500">
                        {{ $ar ? 'اعتماد التسعير' : 'Approve Pricing' }}
                    </button>
                </form>
            </section>
        @endif

        @if (in_array($status, ['priced', 'matching'], true))
            <section class="rounded-2xl border border-emerald-800 bg-slate-900 p-6">
                <h2 class="mb-4 text-lg font-bold text-white">
                    {{ $ar ? 'إسناد الطلب إلى سائق' : 'Assign Driver' }}
                </h2>

                @if ($availableDrivers->isEmpty())
                    <p class="text-sm text-amber-300">
                        {{ $ar ? 'لا يوجد سائق متاح حالياً.' : 'No available drivers.' }}
                    </p>
                @else
                    <form method="POST" action="{{ route('admin.deliveries.assign', $delivery) }}" class="space-y-4">
                        @csrf

                        <label class="block text-sm text-slate-300">
                            {{ $ar ? 'اختر السائق' : 'Select Driver' }}
                        </label>

                        <select name="driver_id" required
                            class="w-full rounded-xl border border-slate-700 bg-slate-800 p-3 text-white">

                            <option value="">
                                {{ $ar ? 'اختر سائقاً متاحاً' : 'Choose an available driver' }}
                            </option>

                            @foreach ($availableDrivers as $driver)
                                <option value="{{ $driver->id }}" @selected(old('driver_id') == $driver->id)>
                                    {{ $driver->user?->name ?? 'Driver #' . $driver->id }}
                                    — {{ $driver->vehicle_type ?? 'Vehicle N/A' }}
                                </option>
                            @endforeach
                        </select>

                        <button type="submit" onclick="return confirm(@js($ar ? 'هل تريد إسناد الطلب لهذا السائق؟' : 'Assign this delivery to the selected driver?'))"
                            class="rounded-xl bg-emerald-600 px-6 py-3 font-bold text-white hover:bg-emerald-500">
                            {{ $ar ? 'تأكيد الإسناد' : 'Confirm Assignment' }}
                        </button>
                    </form>
                @endif
            </section>
        @endif


        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
            <h2 class="mb-5 text-lg font-bold text-white">
                {{ $ar ? 'إثبات التسليم (POD)' : 'Proof of Delivery (POD)' }}
            </h2>

            @if ($proof)
                <div class="grid gap-5 md:grid-cols-2">
                    <div class="space-y-3 text-sm">
                        <p class="text-slate-300">
                            {{ $ar ? 'اسم المستلم' : 'Recipient' }}:
                            <strong class="text-white">{{ $proof->recipient_name }}</strong>
                        </p>

                        <p class="text-slate-300">
                            {{ $ar ? 'وقت التأكيد' : 'Confirmed At' }}:
                            {{ $date($proof->confirmed_at) }}
                        </p>

                        @if ($proof->notes)
                            <p class="whitespace-pre-line break-words text-slate-300">
                                {{ $proof->notes }}
                            </p>
                        @endif
                    </div>


                    <div class="mt-5 grid gap-4 sm:grid-cols-2">

                        {{-- Delivery photo --}}
                        <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
                            <h3 class="mb-3 text-sm font-semibold text-white">
                                {{ $ar ? 'صورة إثبات التسليم' : 'Delivery Photo' }}
                            </h3>

                            @if ($proof->photo_path)
                                <a href="{{ route('admin.deliveries.proof.file', [
                                    'delivery' => $delivery,
                                    'type' => 'photo',
                                ]) }}"
                                    target="_blank" rel="noopener noreferrer"
                                    class="inline-flex rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                                    {{ $ar ? 'عرض الصورة' : 'View Photo' }}
                                </a>
                            @else
                                <p class="text-sm text-slate-400">
                                    {{ $ar ? 'لا توجد صورة' : 'No photo available' }}
                                </p>
                            @endif
                        </div>

                        {{-- Recipient signature --}}
                        <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
                            <h3 class="mb-3 text-sm font-semibold text-white">
                                {{ $ar ? 'توقيع المستلم' : 'Recipient Signature' }}
                            </h3>

                            @if ($proof->signature_path)
                                <a href="{{ route('admin.deliveries.proof.file', [
                                    'delivery' => $delivery,
                                    'type' => 'signature',
                                ]) }}"
                                    target="_blank" rel="noopener noreferrer"
                                    class="inline-flex rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                                    {{ $ar ? 'عرض التوقيع' : 'View Signature' }}
                                </a>
                            @else
                                <p class="text-sm text-slate-400">
                                    {{ $ar ? 'لا يوجد توقيع' : 'No signature available' }}
                                </p>
                            @endif
                        </div>

                    </div>

                </div>
            @else
                <p class="text-sm text-slate-400">
                    {{ $ar ? 'لا يوجد إثبات تسليم حتى الآن' : 'No proof of delivery available' }}
                </p>
            @endif
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
            <h2 class="mb-5 text-lg font-bold text-white">
                {{ $ar ? 'سجل حالات الشحنة' : 'Delivery Status History' }}
            </h2>

            <div class="space-y-4">
                @forelse($delivery->statusHistories->sortBy('created_at') as $history)
                    <div class="border-s-2 border-slate-700 ps-4">
                        <p class="font-semibold text-white">
                            {{ $history->from_status }} → {{ $history->to_status }}
                        </p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ $date($history->created_at) }}
                        </p>
                        @if ($history->reason)
                            <p class="mt-2 text-sm text-slate-300">{{ $history->reason }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-400">
                        {{ $ar ? 'لا يوجد سجل حالات' : 'No status history' }}
                    </p>
                @endforelse
            </div>
        </section>

        @if ($canComplete)
            <section class="rounded-2xl border border-emerald-800 bg-slate-900 p-6">
                <h2 class="text-lg font-bold text-white">
                    {{ $ar ? 'إكمال الشحنة' : 'Complete Delivery' }}
                </h2>

                <p class="mt-2 text-sm text-slate-400">
                    {{ $ar
                        ? 'سيتم تغيير الحالة إلى مكتملة وإعادة إتاحة السائق.'
                        : 'The delivery will be completed and the driver made available.' }}
                </p>

                <form method="POST" action="{{ route('admin.deliveries.complete', $delivery) }}" class="mt-5">
                    @csrf

                    <button type="submit" onclick="return confirm(@js($ar ? 'هل تريد إكمال هذه الشحنة؟' : 'Complete this delivery?'))"
                        class="rounded-xl bg-emerald-600 px-6 py-3 font-semibold text-white hover:bg-emerald-500">
                        {{ $ar ? 'تأكيد إكمال الشحنة' : 'Confirm Completion' }}
                    </button>
                </form>
            </section>
        @endif

    </div>
    @include('deliveries.recovery', ['delivery' => $delivery])
@endsection
