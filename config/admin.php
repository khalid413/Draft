<?php

// Read through config (not env() in the seeder) so it still works after `php artisan optimize`.
// Set these only in the server's .env, never in the repository.
return [
    'email' => env('ADMIN_EMAIL'),
    'password' => env('ADMIN_PASSWORD'),
];
