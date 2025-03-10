<?php

app()->group('adulto', ['middleware' => 'auth.required', function () {
    // Obtener todos los adultos
    app()->get('/', 'AdultosController@all');

    // Obtener un adulto específico
    app()->get('/(\d+)', ['AdultosController@get']);

    // Crear adulto
    app()->post('/', 'AdultosController@create');

    // Actualizar adulto
    app()->put('/(\d+)', 'AdultosController@put');

    // Eliminar adulto
    app()->delete('/(\d+)', 'AdultosController@delete');
    
}]);