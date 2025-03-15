<?php

app()->group('entrada', ['middleware' => 'auth.required', function () {
    // Get all entradas
    app()->get('/', 'EntradasController@all');

    // Get a single entrada
    app()->get('/(\d+)', 'EntradasController@get');

    // Create a new entrada
    app()->post('/', ['middleware' => 'is:admin', 'EntradasController@create']);

    // Update an entrada
    app()->put('/(\d+)', ['middleware' => 'is:admin', 'EntradasController@put']);

    // Delete an entrada
    app()->delete('/(\d+)', ['middleware' => 'is:admin', 'EntradasController@delete']);
}]);