<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/docs/openapi.yaml', function () {
    return response(file_get_contents(base_path('docs/openapi.yaml')), 200, [
        'Content-Type' => 'text/yaml; charset=UTF-8',
        'Content-Disposition' => 'inline; filename="openapi.yaml"',
    ]);
})->name('openapi');
