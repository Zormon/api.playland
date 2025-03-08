<?php

use App\Middleware\Access;

app()->group('evento', ['middleware' => 'auth.required', function () {
    // Get all evento
    app()->get('/', 'EventosController@all');

    // Get a single evento
    app()->get('/(\d+)', 'EventosController@get');

    // Create a new evento
    app()->post('/', ['middleware' => Access::can('eventos:manague'), 'EventosController@create']);

    // Update an evento
    app()->put('/(\d+)', ['middleware' => Access::can('eventos:manague'), 'EventosController@put']);

    // Delete an evento
    app()->delete('/(\d+)', ['middleware' => Access::can('eventos:manague'), 'EventosController@delete']);
}]);