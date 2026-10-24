@extends('layouts.dashboard')

@section('title', __('deliveries.details'))
@section('page-title', __('deliveries.details'))

@section('content')

    @php
        $locale = app()->getLocale();

        $status = $delivery->status->value;

        $statusKey = 'dashboard.statuses.' . $status;

        $statusLabel = __($statusKey);

        if ($statusLabel === $statusKey) {
            $statusLabel = ucwords(str_replace('_', ' ', $status));
        }

        $statusColors = [
            'draft' => 'bg-slate-500/10 text-slate-300',
            'pending_pricing' => 'bg-amber-500/10 text-amber-300',
            'priced' => 'bg-cyan-500/10 text-cyan-300',
            'matching' => 'bg-violet-500/10 text-violet-300',
            'assigned' => 'bg-blue-500/10 text-blue-300',
            'accepted' => 'bg-blue-500/10 text-blue-300',
            'pickup' => 'bg-indigo-500/10 text-indigo-300',
            'in_transit' => 'bg-indigo-500/10 text-indigo-300',
            'delivered' => 'bg-emerald-500/10 text-emerald-300',
            'completed' => 'bg-emerald-500/10 text-emerald-300',
            'cancelled' => 'bg-red-500/10 text-red-300',
        ];

        $badgeClass = $statusColors[$status] ?? 'bg-slate-500/10 text-slate-300';

        $formatDate = fn($date) => $date
            ? $date->copy()->timezone('Asia/Dubai')->locale($locale)->translatedFormat('d M Y, H:i')
            : '—';
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">

        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-4">

            <div>
                <a href="{{ route('merchant.deliveries.index') }}"
                    class="mb-3 inline-flex items-center gap-2 text-sm text-blue-400 hover:text-blue-300">
                    <span aria-hidden="true">←</span>
                    {{ __('deliveries.back_to_deliveries') }}
                </a>

                <h2 class="text-2xl font-bold text-white">
                    {{ __('deliveries.details') }}
                </h2>

                <p dir="ltr" class="mt-2 text-start font-mono text-sm text-slate-400">
                    {{ $delivery->tracking_number }}
                </p>
            </div>

            <span class="inline-flex rounded-full px-4 py-2 text-sm font-semibold {{ $badgeClass }}">
                {{ $statusLabel }}
            </span>

        </div>

        {{-- Summary --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <p class="text-xs text-slate-400">
                    {{ __('deliveries.status') }}
                </p>
                <p class="mt-3 font-semibold text-white">
                    {{ $statusLabel }}
                </p>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <p class="text-xs text-slate-400">
                    {{ __('deliveries.created') }}
                </p>
                <p class="mt-3 text-sm font-semibold text-white">
                    {{ $formatDate($delivery->created_at) }}
                </p>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <p class="text-xs text-slate-400">
                    {{ __('deliveries.scheduled_at') }}
                </p>
                <p class="mt-3 text-sm font-semibold text-white">
                    {{ $formatDate($delivery->scheduled_at) }}
                </p>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <p class="text-xs text-slate-400">
                    {{ __('deliveries.delivery_fee') }}
                </p>
                <p class="mt-3 font-semibold text-white">
                    @if (!is_null($delivery->delivery_fee))
                        {{ number_format((float) $delivery->delivery_fee, 2) }}
                        {{ $delivery->currency }}
                    @else
                        —
                    @endif
                </p>
            </div>

        </div>

        {{-- Details --}}
        <div class="grid gap-6 lg:grid-cols-2">

            {{-- Recipient --}}
            <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-6">

                <h3 class="mb-5 text-lg font-semibold text-white">
                    {{ __('deliveries.recipient_information') }}
                </h3>

                <dl class="space-y-4">

                    <div>
                        <dt class="text-xs text-slate-400">
                            {{ __('deliveries.recipient') }}
                        </dt>
                        <dd class="mt-1 text-sm font-medium text-white">
                            {{ $delivery->recipient_name ?: '—' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-slate-400">
                            {{ __('deliveries.recipient_phone') }}
                        </dt>
                        <dd class="mt-1 text-sm text-white" dir="ltr">
                            {{ $delivery->recipient_phone ?: '—' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-slate-400">
                            {{ __('deliveries.special_instructions') }}
                        </dt>
                        <dd class="mt-1 whitespace-pre-line break-words text-sm text-white">
                            {{ $delivery->special_instructions ?: '—' }}
                        </dd>
                    </div>

                </dl>
            </section>

            {{-- Package --}}
            <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-6">

                <h3 class="mb-5 text-lg font-semibold text-white">
                    {{ __('deliveries.package_information') }}
                </h3>

                <dl class="space-y-4">

                    <div>
                        <dt class="text-xs text-slate-400">
                            {{ __('deliveries.package_type') }}
                        </dt>
                        <dd class="mt-1 text-sm font-medium text-white">
                            {{ $delivery->package_type ?: '—' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-slate-400">
                            {{ __('deliveries.weight') }}
                        </dt>
                        <dd class="mt-1 text-sm text-white">
                            {{ $delivery->weight !== null ? $delivery->weight . ' kg' : '—' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-slate-400">
                            {{ __('deliveries.picked_up_at') }}
                        </dt>
                        <dd class="mt-1 text-sm text-white">
                            {{ $formatDate($delivery->picked_up_at) }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-slate-400">
                            {{ __('deliveries.delivered_at') }}
                        </dt>
                        <dd class="mt-1 text-sm text-white">
                            {{ $formatDate($delivery->delivered_at) }}
                        </dd>
                    </div>

                </dl>
            </section>


            {{-- Pickup & Drop-off Addresses --}}
            <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-6">

                <h3 class="mb-6 text-lg font-semibold text-white">
                    {{ __('deliveries.addresses') }}
                </h3>

                <div class="space-y-6">

                    @foreach ([
            [
                'title' => __('deliveries.pickup_address'),
                'address' => $delivery->pickupAddress,
                'color' => 'blue',
            ],
            [
                'title' => __('deliveries.dropoff_address'),
                'address' => $delivery->dropoffAddress,
                'color' => 'emerald',
            ],
        ] as $item)

                        @php
                            $address = $item['address'];

                            $borderColor = $item['color'] === 'blue' ? 'border-blue-500' : 'border-emerald-500';

                            $textColor = $item['color'] === 'blue' ? 'text-blue-400' : 'text-emerald-400';
                        @endphp

                        <div class="border-s-2 {{ $borderColor }} ps-4">

                            <h4 class="text-sm font-semibold {{ $textColor }}">
                                {{ $item['title'] }}
                            </h4>

                            @if ($address)
                                @if ($address->label)
                                    <p class="mt-2 font-semibold text-white">
                                        {{ $address->label }}
                                    </p>
                                @endif

                                <p class="mt-2 whitespace-pre-line break-words text-sm text-slate-200">
                                    {{ $address->address_line }}
                                </p>

                                <p class="mt-2 text-sm text-slate-400">
                                    {{ collect([
                                        $address->unit,
                                        $address->building,
                                        $address->street,
                                        $address->area,
                                        $address->city,
                                        $address->country,
                                    ])->filter()->implode('، ') }}
                                </p>

                                @if ($address->contact_name || $address->contact_phone)
                                    <div class="mt-4 space-y-1 text-sm">

                                        @if ($address->contact_name)
                                            <p class="text-slate-300">
                                                <span class="text-slate-500">
                                                    {{ __('deliveries.contact_name') }}:
                                                </span>
                                                {{ $address->contact_name }}
                                            </p>
                                        @endif

                                        @if ($address->contact_phone)
                                            <p class="text-slate-300">
                                                <span class="text-slate-500">
                                                    {{ __('deliveries.contact_phone') }}:
                                                </span>
                                                <span dir="ltr" class="inline-block">
                                                    {{ $address->contact_phone }}
                                                </span>
                                            </p>
                                        @endif

                                    </div>
                                @endif

                                @if ($address->delivery_instructions)
                                    <div class="mt-4 rounded-xl bg-slate-800/60 p-3">
                                        <p class="text-xs font-semibold text-slate-400">
                                            {{ __('deliveries.address_instructions') }}
                                        </p>
                                        <p class="mt-2 whitespace-pre-line break-words text-sm text-slate-200">
                                            {{ $address->delivery_instructions }}
                                        </p>
                                    </div>
                                @endif

                                @if (
                                    $address->latitude !== null &&
                                        $address->longitude !== null &&
                                        (float) $address->latitude >= -90 &&
                                        (float) $address->latitude <= 90 &&
                                        (float) $address->longitude >= -180 &&
                                        (float) $address->longitude <= 180)
                                    <a href="https://www.google.com/maps/search/?api=1&query={{ rawurlencode($address->latitude . ',' . $address->longitude) }}"
                                        target="_blank" rel="noopener noreferrer"
                                        class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-blue-400 hover:text-blue-300">

                                        <span aria-hidden="true">↗</span>
                                        {{ __('deliveries.view_on_map') }}

                                    </a>
                                @endif
                            @else
                                <p class="mt-3 text-sm text-slate-500">
                                    {{ __('deliveries.address_not_available') }}
                                </p>
                            @endif

                        </div>

                    @endforeach

                </div>

            </section>


            {{-- Delivery Status Timeline --}}
            <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-6">

                <div class="mb-6 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-white">
                            {{ __('deliveries.status_history') }}
                        </h3>

                        <p class="mt-1 text-xs text-slate-400">
                            {{ __('deliveries.timeline_description') }}
                        </p>
                    </div>

                    <span class="rounded-full bg-blue-500/10 px-3 py-1 text-xs font-semibold text-blue-300">
                        {{ $delivery->statusHistories->count() }}
                    </span>
                </div>

                <ol class="space-y-0">

                    @forelse ($delivery->statusHistories as $history)
                        @php
                            $fromStatus = $history->from_status;
                            $toStatus = $history->to_status;

                            $fromValue = $fromStatus instanceof \BackedEnum ? $fromStatus->value : $fromStatus;

                            $toValue = $toStatus instanceof \BackedEnum ? $toStatus->value : $toStatus;

                            $toKey = $toValue ? 'dashboard.statuses.' . $toValue : null;

                            $toLabel = $toKey ? __($toKey) : __('deliveries.status_updated');

                            if ($toKey && $toLabel === $toKey) {
                                $toLabel = ucwords(str_replace('_', ' ', $toValue));
                            }
                        @endphp

                        <li class="relative border-s-2 border-slate-700 pb-7 ps-6 last:border-transparent last:pb-0">

                            <span
                                class="absolute -start-[7px] top-1 flex h-3 w-3 rounded-full border-2 border-slate-900 bg-blue-500"></span>

                            <div class="flex flex-wrap items-start justify-between gap-3">

                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-white">
                                        {{ $toLabel }}
                                    </p>

                                    @if ($history->reason)
                                        <p class="mt-2 whitespace-pre-line break-words text-sm text-slate-400">
                                            {{ $history->reason }}
                                        </p>
                                    @endif
                                </div>

                                <time datetime="{{ $history->created_at->toIso8601String() }}"
                                    class="text-xs text-slate-500">
                                    {{ $formatDate($history->created_at) }}
                                </time>

                            </div>

                        </li>

                    @empty

                        <li class="rounded-xl border border-dashed border-slate-700 p-6 text-center text-sm text-slate-400">
                            {{ __('deliveries.no_history') }}
                        </li>
                    @endforelse

                </ol>

            </section>


            @if ($delivery->proofOfDelivery)
                @php
                    $proof = $delivery->proofOfDelivery;
                    $ar = app()->getLocale() === 'ar';
                @endphp

                <section class="rounded-2xl border border-emerald-900/60 bg-slate-900 p-5 sm:p-6">
                    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold text-white">
                            {{ $ar ? 'إثبات التسليم' : 'Proof of Delivery' }}
                        </h3>

                        <span class="rounded-full bg-emerald-500/10 px-3 py-1 text-sm text-emerald-400">
                            {{ $ar ? 'تم تأكيد التسليم' : 'Delivery Confirmed' }}
                        </span>
                    </div>

                    <dl class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-slate-400">
                                {{ $ar ? 'اسم المستلم' : 'Recipient Name' }}
                            </dt>
                            <dd class="mt-2 font-medium text-white">
                                {{ $proof->recipient_name }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs text-slate-400">
                                {{ $ar ? 'تاريخ التأكيد' : 'Confirmed At' }}
                            </dt>
                            <dd class="mt-2 text-white">
                                {{ $formatDate($proof->confirmed_at) }}
                            </dd>
                        </div>
                    </dl>

                    @if ($proof->notes)
                        <div class="mt-5">
                            <p class="text-xs text-slate-400">
                                {{ $ar ? 'ملاحظات التسليم' : 'Delivery Notes' }}
                            </p>
                            <p class="mt-2 whitespace-pre-line break-words text-sm text-slate-200">
                                {{ $proof->notes }}
                            </p>
                        </div>
                    @endif

                    @if ($proof->photo_path || $proof->signature_path)
                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            @foreach (['photo', 'signature'] as $type)
                                @php
                                    $path = $type === 'photo' ? $proof->photo_path : $proof->signature_path;
                                @endphp

                                @if ($path)
                                    <a href="{{ route('merchant.deliveries.proof.file', [$delivery, $type]) }}"
                                        target="_blank" rel="noopener noreferrer"
                                        class="rounded-xl border border-slate-700 bg-slate-800 p-5 transition hover:border-blue-500">
                                        <span class="block font-semibold text-white">
                                            @if ($type === 'photo')
                                                {{ $ar ? 'عرض صورة التسليم' : 'View Delivery Photo' }}
                                            @else
                                                {{ $ar ? 'عرض توقيع المستلم' : 'View Recipient Signature' }}
                                            @endif
                                        </span>

                                        <span class="mt-2 block text-xs text-slate-400">
                                            {{ $ar ? 'فتح الملف المحمي' : 'Open protected file' }}
                                        </span>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif


        </div>

    </div>

    @include('deliveries.recovery', ['delivery' => $delivery])
@endsection
