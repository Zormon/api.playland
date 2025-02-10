<?php

use App\Middleware\UserExistMiddleware;

app()->group('user', ['middleware' => 'auth.required', function () {
    // Get all users
    app()->get('/', 'UsersController@all');

    // Get a single user
    app()->get('/(\d+)', ['UsersController@get']);

    // Create a new user
    app()->post('/', 'UsersController@create');

    // Update a user
    app()->put('/(\d+)', 'UsersController@update');

    // Delete a user
    app()->delete('/(\d+)', 'UsersController@delete');
    
}]);