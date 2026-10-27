<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'draft:admin';

    protected $description = 'Create or update the Glisa administrator securely';

    public function handle(): int
    {
        $email = $this->ask('Admin email');
        $password = $this->secret('Password (at least 12 characters)');
        $validator = Validator::make(compact('email', 'password'), ['email' => 'required|email', 'password' => 'required|string|min:12']);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        $user = User::firstOrNew(['email' => $email]);
        $user->name = 'Administrator';
        $user->password = $password;
        $user->is_admin = true;
        $user->save();
        $this->info('Admin ready. Open /admin/login, or triple-click the mode screen’s bottom-left corner.');

        return self::SUCCESS;
    }
}
