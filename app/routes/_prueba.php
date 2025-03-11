<?php

app()->group('prueba', ['middleware' => 'auth.required', function () {
    // Get all pruebas
    app()->get('/', 'PruebasController@all');

    // Get a single prueba
    app()->get('/(\d+)', 'PruebasController@get');

    // Create a new prueba
    app()->post('/', 'PruebasController@create');

    // Update an prueba
    app()->put('/(\d+)', 'PruebasController@put');

    // Delete an prueba
    app()->delete('/(\d+)', 'PruebasController@delete');
}]);