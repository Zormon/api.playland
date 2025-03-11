<?php

app()->group('reserva', ['middleware' => 'auth.required', function () {
    // Get all reservas
    app()->get('/', 'ReservasController@all');

    // Get a single reserva
    app()->get('/(\d+)', 'ReservasController@get');

    // Create a new reserva
    app()->post('/', 'ReservasController@create');

    // Update an reserva
    app()->put('/(\d+)', 'ReservasController@put');

    // Delete an reserva
    app()->delete('/(\d+)', 'ReservasController@delete');
}]);