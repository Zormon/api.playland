<?php

app()->group('user', ['middleware' => 'auth.required', function () {
    // Obtener todos los usuarios
    app()->get('/', 'UsersController@all');

    // Obtener un usuario específico
    app()->get('/(\d+)', ['UsersController@get']);

    // Crear usuario
    app()->post('/', 'UsersController@create');

    // Actualizar usuario
    app()->put('/(\d+)', 'UsersController@update');

    // Actualizar adulto (para taquilla)
    app()->patch('/(\d+)', 'UsersController@patch');

    // Eliminar usuario
    app()->delete('/(\d+)', 'UsersController@delete');
    
}]);