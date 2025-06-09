<?php

app()->group('monitor', ['middleware' => 'auth.required', function () {
    // Get summary for current event
    app()->get('/summary', ['middleware' => 'is:admin|taquilla|monitor', 'MonitorController@summary']);
    
    // Check if an equipo can participate in a specific prueba
    app()->get('/check/(\d+)/(\d+)', ['middleware' => 'is:admin|taquilla|monitor', 'MonitorController@canParticipate']);
}]);
