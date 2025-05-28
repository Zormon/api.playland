<?php

app()->group('monitor', ['middleware' => 'auth.required', function () {
    // Get summary for current event
    app()->get('/summary', ['middleware' => 'is:admin|taquilla|monitor', 'MonitorController@summary']);
}]);
