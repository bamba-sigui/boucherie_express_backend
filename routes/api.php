<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\OrderTrackingController;
use App\Http\Controllers\Api\V1\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Public — vérification numéro
    Route::get('auth/check-phone', [AuthController::class, 'checkPhone']);

    // Public — auth Sanctum (admin / partenaire via web/email)
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    // Public — catalogue
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{id}', [ProductController::class, 'show']);
    Route::get('categories', [CategoryController::class, 'index']);

    // Webhooks paiement (signature vérifiée à l'intérieur)
    Route::post('webhooks/cinetpay', [PaymentController::class, 'webhook']);
    Route::post('webhooks/genius-pay', [PaymentController::class, 'webhook']);

    // Routes protégées par Firebase Auth (app Flutter)
    Route::middleware('firebase.auth')->group(function () {

        // Profil
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::post('profile/fcm-token', [ProfileController::class, 'updateFcmToken']);
        Route::post('users/me/avatar', [ProfileController::class, 'uploadAvatar']);

        // Adresses
        Route::apiResource('addresses', AddressController::class);
        Route::put('addresses/{id}/default', [AddressController::class, 'setDefault']);

        // Favoris
        Route::get('favorites', [FavoriteController::class, 'index']);
        Route::post('favorites/{productId}', [FavoriteController::class, 'add']);
        Route::delete('favorites/{productId}', [FavoriteController::class, 'remove']);

        // Checkout
        Route::post('checkout', [CheckoutController::class, 'create']);

        // Commandes
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{id}', [OrderController::class, 'show']);
        Route::get('orders/{id}/tracking', [OrderTrackingController::class, 'show']);
        Route::get('orders/{id}/courier-location', [OrderController::class, 'courierLocation']);

        // Paiements
        Route::post('payments/initialize', [PaymentController::class, 'initialize']);
        Route::get('payments/{ref}/status', [PaymentController::class, 'status']);
    });

    // Routes protégées par Sanctum (admin dashboard)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::apiResource('products', ProductController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('categories', CategoryController::class)->only(['store', 'update', 'destroy']);
        Route::put('orders/{id}/status', [OrderController::class, 'updateStatus']);
        Route::apiResource('orders', OrderController::class)->only(['update', 'destroy']);
    });
});
