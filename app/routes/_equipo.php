<?php

app()->group('equipo', ['middleware' => 'auth.required', function () {
    // Get all equipos
    app()->get('/',  'EquiposController@all');

    // Get a single equipo
    app()->get('/(\d+)',  'EquiposController@get');

    // Create a new equipo
    app()->post('/', 'EquiposController@create');

    // Update an equipo
    app()->put('/(\d+)', 'EquiposController@put');

    // Delete an equipo
    app()->delete('/(\d+)', 'EquiposController@delete');
}]);