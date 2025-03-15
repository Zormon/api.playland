<?php

app()->group('obstaculo', ['middleware' => 'auth.required', function () {
    // Get all obstaculos
    app()->get('/', ['middleware' => 'is:admin|taquilla|monitor', 'ObstaculosController@all']);

    // Get a single obstaculo
    app()->get('/(\d+)', ['middleware' => 'is:admin|taquilla|monitor', 'ObstaculosController@get']);

    // Create a new obstaculo
    app()->post('/', ['middleware' => 'is:admin', 'ObstaculosController@create']);

    // Update an obstaculo
    app()->put('/(\d+)', ['middleware' => 'is:admin', 'ObstaculosController@put']);

    // Delete an obstaculo
    app()->delete('/(\d+)', ['middleware' => 'is:admin', 'ObstaculosController@delete']);
}]);
