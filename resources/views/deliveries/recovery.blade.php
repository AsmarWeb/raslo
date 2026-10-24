@php
    $recoveryAr = app()->getLocale() === 'ar';
    $recoveryStatus = $delivery->status->value;
    $recoveryDriver = auth()->user()->hasRole('driver') && !auth()->user()->hasRole('admin') && !auth()->user()->hasRole('merchant');
    $recoveryHistory = $delivery->statusHistories()->whereIn('to_status', ['pickup_failed', 'delivery_failed', 'rescheduled'])->latest('id')->first();
@endphp
@if(in_array($recoveryStatus, ['accepted', 'in_transit', 'pickup_failed', 'delivery_failed', 'rescheduled']))
<section class="my-6 space-y-4 rounded-2xl border border-amber-300 p-5">
    <h2 class="text-lg font-bold">{{ $recoveryAr ? 'التعذر وإعادة الجدولة' : 'Delivery recovery' }}</h2>
    @if($recoveryHistory)
        <p class="whitespace-pre-wrap break-words text-sm">{{ $recoveryHistory->reason }}</p>
    @endif
    @if($recoveryStatus === 'rescheduled')
        <p>{{ $recoveryAr ? 'الموعد بتوقيت الإمارات' : 'Appointment in UAE time' }}: <span dir="ltr">{{ $delivery->scheduled_at?->timezone('Asia/Dubai')->format('Y-m-d H:i') }}</span></p>
        <p class="text-sm">{{ $recoveryAr ? 'يبقى الإسناد للسائق الحالي. استئناف المهمة متاح عند حلول الموعد.' : 'The current driver keeps the assignment. Resume is available when the appointment is due.' }}</p>
    @endif
    @if($errors->any())<div role="alert">@foreach($errors->all() as $recoveryError)<p>{{ $recoveryError }}</p>@endforeach</div>@endif
    @if($recoveryDriver && in_array($recoveryStatus, ['accepted', 'in_transit']))
        <form method="post" action="{{ route('delivery.recovery.fail', $delivery) }}" class="space-y-3">
            @csrf
            <label class="block">{{ $recoveryAr ? 'سبب التعذر' : 'Failure category' }}
                <select name="failure_code" required class="mt-2 block w-full rounded-lg border bg-white p-3 text-gray-900">
                    @foreach(['customer_unreachable' => ['العميل لا يرد', 'Customer unreachable'], 'incorrect_address' => ['العنوان غير صحيح', 'Incorrect address'], 'customer_refused' => ['رفض الاستلام', 'Customer refused'], 'store_not_ready' => ['المتجر غير جاهز', 'Store not ready'], 'access_problem' => ['تعذر الوصول', 'Access problem'], 'other' => ['سبب آخر', 'Other']] as $code => $label)
                    <option value="{{ $code }}">{{ $label[$recoveryAr ? 0 : 1] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">{{ $recoveryAr ? 'تفاصيل التعذر' : 'Failure details' }}<textarea name="reason" required minlength="3" maxlength="500" rows="3" class="mt-2 block w-full rounded-lg border bg-white p-3 text-gray-900">{{ old('reason') }}</textarea></label>
            <button class="rounded-lg bg-amber-800 px-5 py-3 text-white">{{ $recoveryAr ? 'تسجيل التعذر' : 'Report failure' }}</button>
        </form>
    @elseif(!$recoveryDriver && in_array($recoveryStatus, ['pickup_failed', 'delivery_failed']))
        <form method="post" action="{{ route('delivery.recovery.reschedule', $delivery) }}" class="space-y-3">
            @csrf
            <label class="block">{{ $recoveryAr ? 'موعد المحاولة الجديدة بتوقيت الإمارات' : 'New appointment in UAE time' }}<input name="appointment" type="datetime-local" required class="mt-2 block w-full rounded-lg border bg-white p-3 text-gray-900" value="{{ old('appointment') }}"></label>
            <label class="block">{{ $recoveryAr ? 'سبب إعادة الجدولة والتعليمات' : 'Reschedule reason and instructions' }}<textarea name="reason" required minlength="3" maxlength="500" rows="3" class="mt-2 block w-full rounded-lg border bg-white p-3 text-gray-900">{{ old('reason') }}</textarea></label>
            <button class="rounded-lg bg-emerald-800 px-5 py-3 text-white">{{ $recoveryAr ? 'اعتماد الموعد' : 'Confirm appointment' }}</button>
        </form>
    @elseif($recoveryDriver && $recoveryStatus === 'rescheduled' && $delivery->scheduled_at?->isPast())
        <form method="post" action="{{ route('delivery.recovery.resume', $delivery) }}">@csrf<button class="rounded-lg bg-emerald-800 px-5 py-3 text-white">{{ $recoveryAr ? 'استئناف المهمة' : 'Resume delivery' }}</button></form>
    @elseif($recoveryDriver && in_array($recoveryStatus, ['pickup_failed', 'delivery_failed']))
        <p>{{ $recoveryAr ? 'بانتظار قرار التاجر أو الإدارة. احتفظ بالشحنة إن كانت بحوزتك وتواصل مع مسؤول التشغيل.' : 'Await merchant or administrator instructions. Retain the parcel if in your custody and contact operations.' }}</p>
    @endif
</section>
@endif
