<?php

app()->group('evento', ['middleware' => 'auth.required', function () {
    // Get all eventos
    app()->get('/', ['middleware' => 'is:admin|taquilla|adulto', 'EventosController@all']);

    // Get a single evento
    app()->get('/(\d+)', ['middleware' => 'is:admin|taquilla|adulto', 'EventosController@get']);

    // Summary of a single evento
    app()->get('/(\d+)/summary', ['middleware' => 'is:admin|taquilla', 'EventosController@summary']);

    // Create a new evento
    app()->post('/', ['middleware' => 'is:admin', 'EventosController@create']);

    // Update an evento
    app()->put('/(\d+)', ['middleware' => 'is:admin', 'EventosController@put']);

    // Delete an evento
    app()->delete('/(\d+)', ['middleware' => 'is:admin', 'EventosController@delete']);

    // Get the current ongoing event, if any
    app()->get('/current', ['middleware' => 'is:admin|taquilla|monitor', 'EventosController@current']);

}]);