<?php

app()->group('user', ['middleware' => 'auth.required', function () {
    // Obtener todos los usuarios
    app()->get('/', ['middleware' => 'is:admin', 'UsersController@all']);

    // Obtener un usuario específico
    app()->get('/(\d+)', ['middleware' => 'is:admin|adulto', 'UsersController@get']);

    // Crear usuario
    app()->post('/', ['middleware' => 'is:admin|adulto', 'UsersController@create']);

    // Actualizar usuario
    app()->put('/(\d+)', ['middleware' => 'is:admin|adulto', 'UsersController@put']);

    // Actualizar adulto (para taquilla)
    app()->patch('/(\d+)', ['middleware' => 'is:admin|adulto', 'UsersController@patch']);

    // Eliminar usuario
    app()->delete('/(\d+)', ['middleware' => 'is:admin', 'UsersController@delete']);
}]);
