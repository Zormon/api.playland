<?php

app()->group('auth', function () {
    // Login and get a JWT token
app()->post('/login', ['middleware' => 'auth.guest', 'AuthController@login']);
});