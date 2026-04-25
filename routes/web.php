<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['message' => 'Boucherie Express API', 'version' => '1.0'];
});