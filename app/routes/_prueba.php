<?php

app()->group('prueba', ['middleware' => 'auth.required', function () {
    // Get all pruebas
    app()->get('/', ['middleware' => 'is:admin|taquilla|monitor', 'PruebasController@all']);

    // Get a single prueba
    app()->get('/(\d+)', ['middleware' => 'is:admin|taquilla|monitor', 'PruebasController@get']);

    // Create a new prueba
    app()->post('/', ['middleware' => 'is:admin', 'PruebasController@create']);

    // Update an prueba
    app()->put('/(\d+)', ['middleware' => 'is:admin', 'PruebasController@put']);

    // Delete an prueba
    app()->delete('/(\d+)', ['middleware' => 'is:admin', 'PruebasController@delete']);
}]);