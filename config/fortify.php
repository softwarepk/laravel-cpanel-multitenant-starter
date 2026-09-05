<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,
    'home' => '/dashboard',
    'prefix' => '',
    'domain' => null,
    'middleware' => ['web'],
    'limiters' => ['login' => 'login'],
    'views' => true,
    'features' => array_values(array_filter([
        filter_var(env('FORTIFY_REGISTRATION', false), FILTER_VALIDATE_BOOL) ? Features::registration() : null,
        Features::resetPasswords(),
        filter_var(env('FORTIFY_EMAIL_VERIFICATION', true), FILTER_VALIDATE_BOOL) ? Features::emailVerification() : null,
    ])),
];
