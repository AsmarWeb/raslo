<?php

use App\Http\Controllers\Auth\WebAuthController;
use App\Http\Controllers\Billing\StripeWebhookController;
use App\Http\Controllers\Catalog\CatalogDashboardController;
use App\Http\Controllers\Catalog\PublicCatalogController;
use App\Http\Controllers\Integrations\DeliveryConnectionSettingsController;
use App\Http\Controllers\Integrations\PublicDeliveryTrackingController;
use App\Http\Controllers\Web\Admin\AdminDashboardController;
use App\Http\Controllers\Web\Admin\AdminDeliveryCompletionController;
use App\Http\Controllers\Web\Admin\AdminDeliveryController;
use App\Http\Controllers\Web\Admin\AdminDeliveryDispatchController;
use App\Http\Controllers\Web\Admin\AdminDriverController;
use App\Http\Controllers\Web\Admin\AdminMerchantController;
use App\Http\Controllers\Web\Admin\AdminMerchantSubscriptionController;
use App\Http\Controllers\Web\Admin\AdminPricingSettingsController;
use App\Http\Controllers\Web\Admin\AdminProofOfDeliveryController;
use App\Http\Controllers\Web\Admin\MerchantCrmController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DriverAssignmentController;
use App\Http\Controllers\Web\DriverDeliveryController;
use App\Http\Controllers\Web\DriverProofOfDeliveryController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\MerchantAddressController;
use App\Http\Controllers\Web\MerchantDeliveryController;
use App\Http\Controllers\Web\MerchantDeliveryCreationController;
use App\Http\Controllers\Web\MerchantLeadController;
use App\Http\Controllers\Web\MerchantProofOfDeliveryController;
use Illuminate\Support\Facades\Route;

Route::get('/language/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['ar', 'en'], true), 404);

    session(['locale' => $locale]);

    return redirect()->back(fallback: route('web.dashboard'));
})->middleware('auth')->name('language.switch');

Route::get('/', [HomeController::class, 'index'])->name('home');

// Add these once:
Route::get('/sitemap.xml', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
        .'<url><loc>'.e(url('/')).'</loc></url>'
        .'<url><loc>'.e(url('/?lang=ar')).'</loc></url>'
        .'</urlset>';

    return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');

Route::get('/robots.txt', function () {
    return response("User-agent: *\nAllow: /\nSitemap: ".url('/sitemap.xml')."\n", 200)
        ->header('Content-Type', 'text/plain; charset=UTF-8');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/merchant-crm', [MerchantCrmController::class, 'index'])->name('merchant-crm.index');
    Route::get('/merchant-crm/{lead}', [MerchantCrmController::class, 'show'])->name('merchant-crm.show');
    Route::patch('/merchant-crm/{lead}', [MerchantCrmController::class, 'update'])->name('merchant-crm.update');
    Route::post('/merchant-crm/{lead}/notes', [MerchantCrmController::class, 'note'])->name('merchant-crm.note');
    Route::post('/merchant-crm/{lead}/analyze', [MerchantCrmController::class, 'analyze'])->middleware('throttle:10,1')->name('merchant-crm.analyze');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [
        WebAuthController::class,
        'showLogin',
    ])->name('login');

    Route::post('/login', [
        WebAuthController::class,
        'login',
    ])->name('login.store');
});

Route::get(
    '/admin/dashboard',
    AdminDashboardController::class
)
    ->middleware('auth')
    ->name('admin.dashboard');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [
        WebAuthController::class,
        'logout',
    ])->name('logout');

    Route::get(
        '/dashboard',
        DashboardController::class
    )->name('web.dashboard');

    Route::get(
        '/merchant/deliveries',
        [MerchantDeliveryController::class, 'index']
    )->name('merchant.deliveries.index');

    Route::get(
        '/merchant/deliveries/create',
        [MerchantDeliveryCreationController::class, 'create']
    )->name('merchant.deliveries.create');

    Route::post(
        '/merchant/deliveries',
        [MerchantDeliveryCreationController::class, 'store']
    )->name('merchant.deliveries.store');

    Route::get('/merchant/deliveries/{delivery}', [
        MerchantDeliveryController::class,
        'show',
    ])->name('merchant.deliveries.show');

    Route::prefix('merchant/addresses')
        ->name('merchant.addresses.')
        ->controller(MerchantAddressController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{address}/edit', 'edit')
                ->whereNumber('address')->name('edit');
            Route::put('/{address}', 'update')
                ->whereNumber('address')->name('update');
            Route::delete('/{address}', 'destroy')
                ->whereNumber('address')->name('destroy');
        });

    Route::get('/driver/delivery-history', \App\Http\Controllers\Web\DriverDeliveryHistoryController::class)
        ->name('driver.history.index');

    Route::prefix('driver/assignments')
        ->name('driver.assignments.')
        ->controller(DriverAssignmentController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');

            Route::get('/{assignment}', 'show')
                ->whereNumber('assignment')
                ->name('show');

            Route::post('/{assignment}/accept', 'accept')
                ->whereNumber('assignment')
                ->name('accept');

            Route::post('/{assignment}/reject', 'reject')
                ->whereNumber('assignment')
                ->name('reject');
        });

    Route::prefix('driver/assignments')
        ->name('driver.assignments.')
        ->controller(DriverDeliveryController::class)
        ->group(function () {
            Route::post('/{assignment}/pickup', 'pickup')
                ->whereNumber('assignment')
                ->name('pickup');

            Route::post('/{assignment}/start-transit', 'startTransit')
                ->whereNumber('assignment')
                ->name('start-transit');
        });

    Route::prefix('driver/assignments')
        ->name('driver.assignments.')
        ->controller(DriverProofOfDeliveryController::class)
        ->group(function () {
            Route::get('/{assignment}/proof', 'create')
                ->whereNumber('assignment')
                ->name('proof.create');

            Route::post('/{assignment}/proof', 'store')
                ->whereNumber('assignment')
                ->name('proof.store');
        });

    Route::get(
        '/merchant/deliveries/{delivery}/proof/{type}',
        [MerchantProofOfDeliveryController::class, 'file']
    )
        ->whereNumber('delivery')
        ->where('type', 'photo|signature')
        ->name('merchant.deliveries.proof.file');

    Route::prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::get('/deliveries', [
                AdminDeliveryController::class,
                'index',
            ])->name('deliveries.index');

            Route::get('/deliveries/{delivery}', [
                AdminDeliveryController::class,
                'show',
            ])->whereNumber('delivery')
                ->name('deliveries.show');

            Route::post('/deliveries/{delivery}/complete', [
                AdminDeliveryCompletionController::class,
                '__invoke',
            ])->whereNumber('delivery')
                ->name('deliveries.complete');

            Route::get('/drivers', [
                AdminDriverController::class,
                'index',
            ])->name('drivers.index');

            Route::patch('/drivers/{driver}/status', [
                AdminDriverController::class,
                'updateStatus',
            ])->whereNumber('driver')
                ->name('drivers.status');

            Route::get('/merchants', [
                AdminMerchantController::class,
                'index',
            ])->name('merchants.index');

            Route::get('/merchants/{merchant}', [
                AdminMerchantController::class,
                'show',
            ])->whereNumber('merchant')
                ->name('merchants.show');

            Route::patch('/merchants/{merchant}/status', [
                AdminMerchantController::class,
                'updateStatus',
            ])->whereNumber('merchant')
                ->name('merchants.status');

            Route::post('/deliveries/{delivery}/price', [
                AdminDeliveryDispatchController::class,
                'price',
            ])->whereNumber('delivery')
                ->name('deliveries.price');

            Route::post('/deliveries/{delivery}/assign', [
                AdminDeliveryDispatchController::class,
                'assign',
            ])->whereNumber('delivery')
                ->name('deliveries.assign');

            Route::get(
                '/drivers/{driver}/location',
                [AdminDriverController::class, 'location']
            )->name('drivers.location');

            Route::get('/drivers/map', [
                AdminDriverController::class,
                'map',
            ])->name('drivers.map');

            Route::get('/drivers/locations', [
                AdminDriverController::class,
                'locations',
            ])->name('drivers.locations');
        });

    Route::get(
        '/admin/deliveries/{delivery}/proof/{type}',
        [AdminProofOfDeliveryController::class, 'file']
    )
        ->whereNumber('delivery')
        ->where('type', 'photo|signature')
        ->name('admin.deliveries.proof.file');
});

// Public merchant registration
Route::post('/merchant-leads', [MerchantLeadController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('merchant-leads.store');

// Delivery pricing settings - administrators only.
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/pricing', [
            AdminPricingSettingsController::class,
            'edit',
        ])->name('pricing.edit');

        Route::put('/pricing', [
            AdminPricingSettingsController::class,
            'update',
        ])->name('pricing.update');
    });

// Merchant subscription administration
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin/subscriptions')
    ->name('admin.subscriptions.')
    ->group(function () {
        Route::get('/', [AdminMerchantSubscriptionController::class, 'index'])
            ->name('index');

        Route::post('/', [AdminMerchantSubscriptionController::class, 'store'])
            ->name('store');
    });

// Override Cashier's webhook action while retaining its signature middleware.
Route::post(config('cashier.path', 'stripe').'/webhook', [
    StripeWebhookController::class, 'handleWebhook',
])->name('cashier.webhook');

foreach (['success', 'cancel', 'return'] as $billingOutcome) {
    Route::get('/billing/'.$billingOutcome, function () use ($billingOutcome) {
        return response()->view('billing.return', ['outcome' => $billingOutcome])
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer');
    })->name('billing.'.$billingOutcome);
}

Route::get('/catalog', [PublicCatalogController::class, 'web'])->middleware('throttle:60,1')->name('catalog.index');
Route::middleware(['auth', 'account.active', 'throttle:20,1'])->prefix('merchant/integrations')->name('merchant.catalog.')->controller(CatalogDashboardController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::get('/platforms', 'platforms')->name('platforms');
    Route::get('/platforms/woocommerce/download', 'download')->name('download');
    Route::get('/platforms/api/openapi', 'contract')->name('contract');
    Route::get('/platforms/api/delivery-openapi', [DeliveryConnectionSettingsController::class, 'contract'])->name('delivery-contract');
    Route::get('/platforms/{platform}', 'guide')->whereIn('platform', ['woocommerce', 'api'])->name('guide');
    Route::post('/{connection}/rotate-key', 'rotate')->whereNumber('connection')->name('rotate');
    Route::post('/{connection}/status', 'update')->whereNumber('connection')->name('status');
    Route::post('/{connection}/sync', 'sync')->whereNumber('connection')->name('sync');
    Route::post('/{connection}/check', 'check')->whereNumber('connection')->name('check');
    Route::get('/{connection}/deliveries', [DeliveryConnectionSettingsController::class, 'show'])->whereNumber('connection')->name('deliveries');
    Route::post('/{connection}/delivery-settings', [DeliveryConnectionSettingsController::class, 'update'])->whereNumber('connection')->name('delivery-settings');
});
Route::middleware(['auth', 'role:admin'])->prefix('admin/catalog')->name('admin.catalog.')->controller(CatalogDashboardController::class)->group(function () {
    Route::get('/', 'admin')->name('index');
    Route::post('/{connection}/approval', 'approve')->whereNumber('connection')->name('approval');
});

Route::get('/track/{token}', PublicDeliveryTrackingController::class)
    ->where('token', '[a-f0-9]{64}')->middleware('throttle:60,1')->name('integrations.track');

Route::middleware(['auth'])->controller(\App\Http\Controllers\DriverDocuments\DriverDocumentController::class)->group(function () {
    Route::get('/driver/documents', 'index')->name('driver.documents.index')->middleware('throttle:60,1');
    Route::post('/driver/documents', 'store')->middleware('throttle:10,1')->name('driver.documents.store');
    Route::get('/driver/documents/{document}/file', 'file')->whereUuid('document')->name('driver.documents.file')->middleware('throttle:60,1');
});
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/drivers/{driver}', [\App\Http\Controllers\DriverDocuments\AdminDriverDocumentsController::class, 'show'])->whereNumber('driver')->name('admin.drivers.show');
    Route::post('/admin/driver-documents/{document}/review', [\App\Http\Controllers\DriverDocuments\AdminDriverDocumentsController::class, 'review'])->whereUuid('document')->name('admin.drivers.documents.review')->middleware('throttle:30,1');
});

Route::middleware(['auth', 'throttle:20,1'])->controller(\App\Http\Controllers\DeliveryRecoveryController::class)->group(function () {
    Route::post('/deliveries/{delivery}/recovery/fail', 'fail')->whereNumber('delivery')->name('delivery.recovery.fail');
    Route::post('/deliveries/{delivery}/recovery/reschedule', 'reschedule')->whereNumber('delivery')->name('delivery.recovery.reschedule');
    Route::post('/deliveries/{delivery}/recovery/resume', 'resume')->whereNumber('delivery')->name('delivery.recovery.resume');
});
