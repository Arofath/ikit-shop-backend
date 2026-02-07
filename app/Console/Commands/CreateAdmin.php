<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'account:create-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new admin user via CLI';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->ask('Admin Name');
        $email = $this->ask('Admin Email');
        $phone = $this->ask('Admin Phone Number');
        $password = $this->secret('Admin Password');

        if ($this->confirm("Do you want to create admin: {$email}?", true)) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'phone_number' => $phone,
                'password' => $password, // Password នឹង Hash ស្វ័យប្រវត្តិដោយ Casts ក្នុង Model
                'role' => 'admin',
                'is_active' => true,
                'provider' => 'local',
            ]);

            $this->info("Admin created successfully with ID: {$user->id}");
        }
    }
}


//php artisan account:create-admin