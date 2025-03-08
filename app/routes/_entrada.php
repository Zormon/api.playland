<?php

use App\Middleware\Access;

app()->group('entrada', ['middleware' => 'auth.required', function () {
    // Get all entradas
    app()->get('/', 'EntradasController@all');

    // Get a single entrada
    app()->get('/(\d+)', 'EntradasController@get');

    // Create a new entrada
    app()->post('/', ['middleware' => Access::can('entradas:manague'), 'EntradasController@create']);

    // Update an entrada
    app()->put('/(\d+)', ['middleware' => Access::can('entradas:manague'), 'EntradasController@put']);

    // Delete an entrada
    app()->delete('/(\d+)', ['middleware' => Access::can('entradas:manague'), 'EntradasController@delete']);
}]);