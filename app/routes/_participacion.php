<?php

app()->group('participacion', ['middleware' => 'auth.required', function () {
    // Get all participaciones
    app()->get('/', ['middleware' => 'is:admin|taquilla', 'ParticipacionesController@all']);

    // Get a single participacion
    app()->get('/(\d+)', ['middleware' => 'is:admin|taquilla', 'ParticipacionesController@get']);

    // Create a new participacion
    app()->post('/', ['middleware' => 'is:admin|monitor', 'ParticipacionesController@create']);

    // Update a participacion
    app()->put('/(\d+)', ['middleware' => 'is:admin|taquilla', 'ParticipacionesController@put']);

    // Delete a participacion
    app()->delete('/(\d+)', ['middleware' => 'is:admin|taquilla', 'ParticipacionesController@delete']);
}]);
