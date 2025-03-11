<?php

app()->group('obstaculo', ['middleware' => 'auth.required', function () {
    // Get all obstaculos
    app()->get('/', 'ObstaculosController@all');

    // Get a single obstaculo
    app()->get('/(\d+)', 'ObstaculosController@get');

    // Create a new obstaculo
    app()->post('/', 'ObstaculosController@create');

    // Update an obstaculo
    app()->put('/(\d+)', 'ObstaculosController@put');

    // Delete an obstaculo
    app()->delete('/(\d+)', 'ObstaculosController@delete');
}]);
