<?php

app()->group('evento', ['middleware' => 'auth.required', function () {
    // Get all evento
    app()->get('/', 'EventosController@all');

    // Get a single evento
    app()->get('/(\d+)', 'EventosController@get');

    // Create a new evento
    app()->post('/', 'EventosController@create');

    // Update an evento
    app()->put('/(\d+)', 'EventosController@put');

    // Delete an evento
    app()->delete('/(\d+)', 'EventosController@delete');
}]);