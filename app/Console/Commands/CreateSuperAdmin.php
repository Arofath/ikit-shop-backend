<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class CreateSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'account:create-super-admin {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Promote a user to super_admin role via email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email {$email} not found!");
            return;
        }

        $user->update(['role' => 'admin']); // ឬ 'super_admin' តាមការរៀបចំរបស់អ្នក

        $this->info("User {$email} has been promoted to Admin successfully!");
    }
}

// php artisan account:create-super-admin slesrofath2203@gmail.com