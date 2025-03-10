<?php

app()->group('entrada', ['middleware' => 'auth.required', function () {
    // Get all entradas
    app()->get('/', 'EntradasController@all');

    // Get a single entrada
    app()->get('/(\d+)', 'EntradasController@get');

    // Create a new entrada
    app()->post('/', 'EntradasController@create');

    // Update an entrada
    app()->put('/(\d+)', 'EntradasController@put');

    // Delete an entrada
    app()->delete('/(\d+)', 'EntradasController@delete');
}]);