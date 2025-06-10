<?php

app()->group('monitor', ['middleware' => 'auth.required', function () {
    // Get summary for current event
    app()->get('/summary', ['middleware' => 'is:admin|taquilla|monitor', 'MonitorController@summary']);

    // Check if an equipo can participate in a specific prueba
    app()->get('/participation/(\d+)/(\d+)', ['middleware' => 'is:admin|taquilla|monitor', 'MonitorController@canParticipate']);

    // Register a new participation
    app()->post('/participation/(\d+)/(\d+)', ['middleware' => 'is:admin|taquilla|monitor', 'MonitorController@registerParticipation']);
}]);
