<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use App\Models\UserModel;

class AdminSeeder extends Seeder
{
    public function run()
    {
        $userModel = new UserModel();

        // Create default admin user
        $adminData = [
            'username' => 'admin',
            'email' => 'admin@restaurant.com',
            'password' => 'admin123', // This will be hashed by the model
            'full_name' => 'Restaurant Administrator',
            'role' => 'admin',
            'status' => 'active'
        ];

        // Check if admin already exists
        $existingAdmin = $userModel->where('email', $adminData['email'])->first();
        
        if (!$existingAdmin) {
            $userModel->createUser($adminData);
            echo "Admin user created successfully!\n";
            echo "Email: admin@restaurant.com\n";
            echo "Password: admin123\n";
        } else {
            echo "Admin user already exists!\n";
        }
    }
}
