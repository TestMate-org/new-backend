<?php

use App\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::create([
            'name' => 'Administrator',
            'email' => 'admin@TestMate.com',
            'role' => 'admin',
            'password' => bcrypt('criticalpassword'),
        ]);
    }
}
