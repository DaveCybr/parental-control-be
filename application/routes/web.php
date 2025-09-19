<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'Parental Control API',
        'status' => 'success',
        'message' => 'Welcome to Parental Control API',
        'version' => '1.0.0'
    ]);
});
