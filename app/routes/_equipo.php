<?php

app()->group('equipo', ['middleware' => 'auth.required', function () {
    // Get all equipos
    app()->get('/', ['middleware' => 'is:admin|adulto', 'EquiposController@all']);

    // Get a single equipo
    app()->get('/(\d+)', ['middleware' => 'is:admin|adulto', 'EquiposController@get']);

    // Create a new equipo
    app()->post('/', ['middleware' => 'is:admin|adulto', 'EquiposController@create']);

    // Update an equipo
    app()->put('/(\d+)', ['middleware' => 'is:admin|adulto', 'EquiposController@put']);

    // Delete an equipo
    app()->delete('/(\d+)', ['middleware' => 'is:admin', 'EquiposController@delete']);
}]);
