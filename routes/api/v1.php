<?php

use App\Http\Controllers\Api\V1\ProductController;

Route::get('/products', [ProductController::class, 'index']);