<?php

app()->group('user', ['middleware' => 'auth.required', function () {
    // Obtener todos los usuarios
    app()->get('/', ['middleware' => 'is:admin|taquilla', 'UsersController@all']);

    // Obtener un usuario específico
    app()->get('/(\d+)', ['middleware' => 'is:admin|adulto|taquilla', 'UsersController@get']);

    // Crear usuario
    app()->post('/', ['middleware' => 'is:admin|taquilla', 'UsersController@create']);

    // Actualizar usuario
    app()->put('/(\d+)', ['middleware' => 'is:admin|taquilla', 'UsersController@put']);

    // Actualizar adulto (para taquilla)
    app()->patch('/(\d+)', ['middleware' => 'is:admin|taquilla', 'UsersController@patch']);

    // Eliminar usuario
    app()->delete('/(\d+)', ['middleware' => 'is:admin', 'UsersController@delete']);
}]);
