@extends('layouts.dashboard')

@section('content')
    @php
        $ar = app()->getLocale() === 'ar';
        $delivery = $assignment->delivery;
    @endphp


    @if (session('success'))
        <div class="mb-5 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200"
            role="status">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200"
            role="alert">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($assignment->status === 'pending')
        <div class="mt-6 grid gap-5 lg:grid-cols-2">

            {{-- Accept assignment --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-2 text-lg font-semibold">
                    {{ $ar ? 'قبول المهمة' : 'Accept Assignment' }}
                </h3>

                <p class="mb-4 text-sm text-gray-500">
                    {{ $ar ? 'عند القبول ستصبح مسؤولاً عن تنفيذ هذه الشحنة.' : 'Accepting makes you responsible for this delivery.' }}
                </p>

                <form method="POST" action="{{ route('driver.assignments.accept', $assignment) }}">
                    @csrf

                    <button type="submit"
                        class="w-full rounded-xl bg-green-600 px-5 py-3 font-semibold text-white hover:bg-green-700">
                        {{ $ar ? 'قبول المهمة' : 'Accept Assignment' }}
                    </button>
                </form>
            </div>

            {{-- Reject assignment --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-2 text-lg font-semibold">
                    {{ $ar ? 'رفض المهمة' : 'Reject Assignment' }}
                </h3>

                <p class="mb-4 text-sm text-gray-500">
                    {{ $ar ? 'يرجى توضيح سبب عدم إمكانية تنفيذ المهمة.' : 'Please explain why you cannot complete this assignment.' }}
                </p>

                <form method="POST" action="{{ route('driver.assignments.reject', $assignment) }}">
                    @csrf

                    <label for="rejection-reason" class="mb-2 block text-sm font-medium">
                        {{ $ar ? 'سبب الرفض' : 'Rejection Reason' }}
                    </label>

                    <textarea id="rejection-reason" name="reason" rows="3" maxlength="1000" required
                        class="mb-4 w-full rounded-xl border border-gray-300 bg-white p-3 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        placeholder="{{ $ar ? 'اكتب سبب الرفض...' : 'Enter rejection reason...' }}">{{ old('reason') }}</textarea>

                    <button type="submit"
                        class="w-full rounded-xl bg-red-600 px-5 py-3 font-semibold text-white hover:bg-red-700">
                        {{ $ar ? 'رفض المهمة' : 'Reject Assignment' }}
                    </button>
                </form>
            </div>
        </div>
    @endif


    <div class="mx-auto max-w-5xl space-y-6">

        <a href="{{ route('driver.assignments.index') }}" class="text-sm text-blue-400">
            {{ $ar ? '← العودة إلى المهام' : '← Back to Assignments' }}
        </a>

        <div class="rounded-xl border border-slate-700 p-6">
            <h1 class="text-2xl font-bold">
                {{ $ar ? 'تفاصيل المهمة' : 'Assignment Details' }}
            </h1>

            <p class="mt-2 opacity-70">
                #{{ $delivery?->tracking_number ?? $assignment->id }}
            </p>

            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <div>
                    <div class="text-sm opacity-60">
                        {{ $ar ? 'حالة المهمة' : 'Assignment Status' }}
                    </div>
                    <div class="mt-1 font-semibold">
                        {{ ucfirst($assignment->status) }}
                    </div>
                </div>

                <div>
                    <div class="text-sm opacity-60">
                        {{ $ar ? 'حالة الشحنة' : 'Delivery Status' }}
                    </div>

                    <div class="mt-1 font-semibold">
                        {{ $delivery?->status?->value ?? '—' }}
                    </div>
                </div>

                <div>
                    <div class="text-sm opacity-60">
                        {{ $ar ? 'تاريخ الإسناد' : 'Assigned At' }}
                    </div>
                    <div class="mt-1">
                        {{ $assignment->assigned_at?->format('Y-m-d H:i') ?? '—' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            @foreach ([['title' => $ar ? 'عنوان الاستلام' : 'Pickup Address', 'address' => $delivery?->pickupAddress], ['title' => $ar ? 'عنوان التسليم' : 'Drop-off Address', 'address' => $delivery?->dropoffAddress]] as $location)
                <div class="rounded-xl border border-slate-700 p-6">
                    <h2 class="mb-4 text-lg font-semibold">
                        {{ $location['title'] }}
                    </h2>

                    <p>{{ $location['address']?->address_line ?? '—' }}</p>

                    <p class="mt-2 text-sm opacity-70">
                        {{ $location['address']?->city ?? '' }}
                    </p>

                    @if ($location['address']?->contact_name)
                        <p class="mt-3">
                            {{ $location['address']->contact_name }}
                        </p>
                    @endif

                    @if ($location['address']?->contact_phone)
                        <p class="mt-1">
                            <a href="tel:{{ $location['address']->contact_phone }}" class="text-blue-400">
                                {{ $location['address']->contact_phone }}
                            </a>
                        </p>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($delivery)
            <div class="rounded-xl border border-slate-700 p-6">
                <h2 class="mb-4 text-lg font-semibold">
                    {{ $ar ? 'معلومات الشحنة' : 'Delivery Information' }}
                </h2>

                <div class="grid gap-4 text-sm md:grid-cols-2">
                    <p>
                        {{ $ar ? 'المستلم' : 'Recipient' }}:
                        {{ $delivery->recipient_name ?? '—' }}
                    </p>

                    <p>
                        {{ $ar ? 'الهاتف' : 'Phone' }}:
                        {{ $delivery->recipient_phone ?? '—' }}
                    </p>

                    <p>
                        {{ $ar ? 'نوع الطرد' : 'Package Type' }}:
                        {{ $delivery->package_type ?? '—' }}
                    </p>

                    <p>
                        {{ $ar ? 'الوزن' : 'Weight' }}:
                        {{ $delivery->weight ?? '—' }}
                    </p>
                </div>
            </div>
        @endif


        @if ($assignment->status === 'accepted' && $delivery)
            @if ($delivery->status === \App\Enums\DeliveryStatus::Accepted)
                <form method="POST" action="{{ route('driver.assignments.pickup', $assignment) }}">
                    @csrf

                    <button type="submit"
                        class="w-full rounded-xl bg-blue-600 px-5 py-3 font-semibold text-white hover:bg-blue-700">
                        {{ $ar ? 'تأكيد استلام الشحنة' : 'Confirm Pickup' }}
                    </button>
                </form>
            @elseif ($delivery->status === \App\Enums\DeliveryStatus::Pickup)
                <form method="POST" action="{{ route('driver.assignments.start-transit', $assignment) }}">
                    @csrf

                    <button type="submit"
                        class="w-full rounded-xl bg-indigo-600 px-5 py-3 font-semibold text-white hover:bg-indigo-700">
                        {{ $ar ? 'بدء التوصيل' : 'Start Delivery' }}
                    </button>
                </form>
            @elseif ($delivery->status === \App\Enums\DeliveryStatus::InTransit)
                <div class="rounded-xl border border-blue-500/30 p-5">
                    <p class="font-semibold">
                        {{ $ar
                            ? 'الشحنة قيد التوصيل. الخطوة التالية هي إثبات التسليم.'
                            : 'Delivery is in transit. The next step is proof of delivery.' }}
                    </p>
                    <a href="{{ route('driver.assignments.proof.create', $assignment) }}"
                        class="mt-4 inline-flex rounded-xl bg-green-600 px-5 py-3 font-semibold text-white hover:bg-green-700">
                        {{ $ar ? 'تسجيل إثبات التسليم' : 'Record Proof of Delivery' }}
                    </a>
                </div>
            @endif
        @endif


    </div>
    @include('deliveries.recovery', ['delivery' => $delivery])
@endsection
