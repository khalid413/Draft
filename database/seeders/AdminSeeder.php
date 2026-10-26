<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;

/** Creates or updates the administrator from ADMIN_EMAIL / ADMIN_PASSWORD in .env; does nothing when they are unset. */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $credentials = ['email' => config('admin.email'), 'password' => config('admin.password')];
        if (blank($credentials['email']) || blank($credentials['password'])) {
            $this->command?->warn('ADMIN_EMAIL / ADMIN_PASSWORD not set; skipping the admin account.');

            return;
        }
        $validator = Validator::make($credentials, ['email' => 'required|email', 'password' => 'required|string|min:12']);
        if ($validator->fails()) {
            $this->command?->error('Admin not created: '.$validator->errors()->first());

            return;
        }
        $user = User::firstOrNew(['email' => $credentials['email']]);
        $user->name = 'Administrator';
        $user->password = $credentials['password'];
        $user->is_admin = true;
        $user->save();
        $this->command?->info('Admin ready: '.$credentials['email']);
    }
}
