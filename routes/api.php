<?php

use App\Http\Controllers\Api\V1\Auth\AccountProfileController;
use App\Http\Controllers\Api\V1\Auth\ApplicationStatusController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\SelfRegistrationController;
use App\Http\Controllers\Api\V1\AvailableDriversController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeliveryAssignmentController;
use App\Http\Controllers\Api\V1\DeliveryCodController;
use App\Http\Controllers\Api\V1\DeliveryCompletionController;
use App\Http\Controllers\Api\V1\DeliveryController;
use App\Http\Controllers\Api\V1\DeliveryPricingController;
use App\Http\Controllers\Api\V1\DeliveryTrackingController;
use App\Http\Controllers\Api\V1\DeliveryTransitionController;
use App\Http\Controllers\Api\V1\DispatchPresenceController;
use App\Http\Controllers\Api\V1\DriverAssignmentController;
use App\Http\Controllers\Api\V1\DriverDeliveryController;
use App\Http\Controllers\Api\V1\DriverLocationController;
use App\Http\Controllers\Api\V1\DriverProfileController;
use App\Http\Controllers\Api\V1\MerchantAddressController;
use App\Http\Controllers\Api\V1\MerchantDeliveryCancellationController;
use App\Http\Controllers\Api\V1\MerchantDeliveryPriceApprovalController;
use App\Http\Controllers\Api\V1\MerchantFinanceController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProofOfDeliveryController;
use App\Http\Controllers\Api\V1\ProofOfDeliveryViewController;
use App\Http\Controllers\Api\V1\PushDeviceController;
use App\Http\Controllers\Api\V1\RecurringDeliveryController;
use App\Http\Controllers\Api\V1\StripeBillingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/driver/dispatch-presence', DispatchPresenceController::class)
        ->middleware(['auth:sanctum', 'account.active', 'throttle:10,1']);
    Route::get('/merchant/available-drivers', AvailableDriversController::class)
        ->middleware(['auth:sanctum', 'account.active', 'throttle:60,1']);

    Route::middleware(['auth:sanctum', 'account.active', 'throttle:60,1'])->group(function () {
        Route::get('/deliveries/{delivery}/cod', [DeliveryCodController::class, 'show'])->whereNumber('delivery');
        Route::post('/deliveries/{delivery}/cod/collect', [DeliveryCodController::class, 'collect'])->whereNumber('delivery');
        Route::post('/deliveries/{delivery}/cod/receive', [DeliveryCodController::class, 'receive'])->whereNumber('delivery');
    });

    Route::post('/deliveries/{delivery}/cancel', MerchantDeliveryCancellationController::class)
        ->whereNumber('delivery')->middleware(['auth:sanctum', 'account.active', 'throttle:10,1']);

    Route::get('/merchant/finance', MerchantFinanceController::class)
        ->middleware(['auth:sanctum', 'account.active', 'throttle:60,1']);

    Route::middleware([
        'auth:sanctum',
        'account.active',
    ])->prefix('merchant/billing')->group(function () {
        Route::post('/checkout', [
            StripeBillingController::class,
            'checkout',
        ])->middleware('throttle:billing-write')->name('merchant.billing.checkout');

        Route::post('/portal', [
            StripeBillingController::class,
            'portal',
        ])->middleware('throttle:billing-write')->name('merchant.billing.portal');

        Route::get('/plans', [
            StripeBillingController::class,
            'plans',
        ])->middleware('throttle:billing-read')->name('merchant.billing.plans');

        Route::get('/current', [
            StripeBillingController::class,
            'current',
        ])->middleware('throttle:billing-read')->name('merchant.billing.current');

    });

    Route::middleware([
        'auth:sanctum',
        'account.active',
    ])
        ->get('/dashboard', DashboardController::class)
        ->name('dashboard');

    Route::prefix('auth')->group(function () {

        // Public routes
        Route::post('/login', [AuthController::class, 'login']);

        // Protected routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::get('/account', [AccountProfileController::class, 'show']);
            Route::patch('/account', [AccountProfileController::class, 'update'])->middleware('throttle:10,1');
        });

        Route::post('/register/merchant', [SelfRegistrationController::class, 'merchant'])->middleware('throttle:5,1');
        Route::post('/register/driver', [SelfRegistrationController::class, 'driver'])->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->get(
            '/application-status',
            ApplicationStatusController::class
        );
    });

    Route::middleware('auth:sanctum')
        ->prefix('deliveries')
        ->group(function () {
            Route::get('/', [DeliveryController::class, 'index']);
            Route::post('/', [DeliveryController::class, 'store']);
            Route::get('/{delivery}', [DeliveryController::class, 'show']);
            Route::post(
                '/{delivery}/transition',
                DeliveryTransitionController::class
            );
            Route::post(
                '/{delivery}/price',
                DeliveryPricingController::class
            );
            Route::post(
                '/{delivery}/approve-price',
                MerchantDeliveryPriceApprovalController::class
            );
            Route::post(
                '{delivery}/assign',
                [DeliveryAssignmentController::class, 'store']
            )->name('deliveries.assign');
            Route::post(
                '/{delivery}/complete',
                DeliveryCompletionController::class
            )->name('deliveries.complete');

            Route::get(
                '/{delivery}/proof',
                [ProofOfDeliveryViewController::class, 'show']
            )->name('deliveries.proof.show');

            Route::get(
                '/{delivery}/proof/{type}',
                [ProofOfDeliveryViewController::class, 'file']
            )
                ->where('type', 'photo|signature')
                ->name('deliveries.proof.file');
            Route::get(
                '/{delivery}/tracking',
                DeliveryTrackingController::class
            )->name('deliveries.tracking');
        });
    Route::middleware('auth:sanctum')
        ->prefix('driver')
        ->group(function () {
            Route::get(
                '/assignments',
                [DriverAssignmentController::class, 'index']
            );

            Route::post(
                '/assignments/{assignment}/accept',
                [DriverAssignmentController::class, 'accept']
            );

            Route::post(
                '/assignments/{assignment}/reject',
                [DriverAssignmentController::class, 'reject']
            );

            Route::get('/profile', [
                DriverProfileController::class,
                'show',
            ])->name('driver.profile.show');

            Route::patch('/availability', [
                DriverProfileController::class,
                'updateAvailability',
            ])->name('driver.availability.update');

            Route::patch('/location', [
                DriverLocationController::class,
                'update',
            ])->middleware('throttle:30,1')->name('driver.location.update');
        });

    Route::middleware('auth:sanctum')
        ->prefix('driver/deliveries')
        ->group(function () {
            Route::post(
                '/{delivery}/pickup',
                [DriverDeliveryController::class, 'pickup']
            );

            Route::post(
                '/{delivery}/start-transit',
                [DriverDeliveryController::class, 'startTransit']
            );
        });

    Route::middleware('auth:sanctum')
        ->post(
            'driver/deliveries/{delivery}/proof',
            [ProofOfDeliveryController::class, 'store']
        )
        ->name('driver.deliveries.proof');

    Route::middleware(['auth:sanctum', 'account.active', 'throttle:20,1'])->group(function () {
        Route::post('push/device', [PushDeviceController::class, 'store']);
        Route::delete('push/device', [PushDeviceController::class, 'destroy']);
    });

    Route::middleware('auth:sanctum')
        ->prefix('notifications')
        ->group(function () {
            Route::get('/', [
                NotificationController::class,
                'index',
            ]);

            Route::get('/unread-count', [
                NotificationController::class,
                'unreadCount',
            ]);

            Route::get('/{id}', [NotificationController::class, 'show']);

            Route::patch('/{id}/read', [
                NotificationController::class,
                'markAsRead',
            ]);

            Route::post('/read-all', [
                NotificationController::class,
                'markAllAsRead',
            ]);
        });

    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/merchant/addresses', [
            MerchantAddressController::class,
            'index',
        ]);

        Route::post('/merchant/addresses', [
            MerchantAddressController::class,
            'store',
        ]);
    });
});

// DELIVERYHUB MERCHANT AUTOMATION ROUTES
Route::middleware([
    'auth:sanctum',
    'account.active',
    'throttle:30,1',
])->prefix('v1/merchant/automations')->group(function () {
    Route::get('/', [
        RecurringDeliveryController::class,
        'index',
    ])->name('merchant.automations.index');

    Route::post('/', [
        RecurringDeliveryController::class,
        'store',
    ])->name('merchant.automations.store');

    Route::patch('/{recurringDelivery}/status', [
        RecurringDeliveryController::class,
        'updateStatus',
    ])->name('merchant.automations.status');
});

require __DIR__.'/catalog.php';

// Document onboarding remains available to pending drivers; ownership is checked by profile.
Route::middleware(['auth:sanctum'])->prefix('v1/driver/documents')->controller(\App\Http\Controllers\DriverDocuments\DriverDocumentController::class)->group(function () {
    Route::get('/', 'index')->middleware('throttle:60,1');
    Route::post('/', 'store')->middleware('throttle:10,1');
    Route::get('/{document}/file', 'file')->whereUuid('document')->middleware('throttle:60,1');
});

Route::middleware(['auth:sanctum', 'account.active', 'throttle:20,1'])->controller(\App\Http\Controllers\DeliveryRecoveryController::class)->group(function () {
    Route::post('/v1/deliveries/{delivery}/recovery/fail', 'fail')->whereNumber('delivery');
    Route::post('/v1/deliveries/{delivery}/recovery/reschedule', 'reschedule')->whereNumber('delivery');
    Route::post('/v1/deliveries/{delivery}/recovery/resume', 'resume')->whereNumber('delivery');
    Route::get('/v1/deliveries/{delivery}/recovery', 'show')->whereNumber('delivery');
});
