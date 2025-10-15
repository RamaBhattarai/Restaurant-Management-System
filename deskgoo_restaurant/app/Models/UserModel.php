<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'username',
        'email', 
        'password_hash',
        'full_name',
        'role',
        'status',
        'profile_picture',
        'last_login'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    // Validation
    protected $validationRules = [
        'username' => 'required|alpha_numeric_punct|min_length[3]|max_length[50]|is_unique[users.username,id,{id}]',
        'email' => 'required|valid_email|max_length[100]|is_unique[users.email,id,{id}]',
        'password_hash' => 'required',
        'full_name' => 'required|max_length[100]',
        'role' => 'required|in_list[admin]',
        'status' => 'required|in_list[active,inactive]'
    ];

    protected $validationMessages = [
        'username' => [
            'required' => 'Username is required',
            'is_unique' => 'Username already exists'
        ],
        'email' => [
            'required' => 'Email is required',
            'valid_email' => 'Please provide a valid email',
            'is_unique' => 'Email already exists'
        ]
    ];

    protected $skipValidation = false;

    /**
     * Find user by email or username
     */
    public function findUserByEmailOrUsername($identifier)
    {
        return $this->where('email', $identifier)
                   ->orWhere('username', $identifier)
                   ->where('status', 'active')
                   ->first();
    }

    /**
     * Verify user password
     */
    public function verifyPassword($inputPassword, $hashedPassword)
    {
        return password_verify($inputPassword, $hashedPassword);
    }

    /**
     * Hash password
     */
    public function hashPassword($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Update last login time
     */
    public function updateLastLogin($userId)
    {
        return $this->update($userId, ['last_login' => date('Y-m-d H:i:s')]);
    }

    /**
     * Update user password
     */
    public function updatePassword($userId, $newPassword)
    {
        $hashedPassword = $this->hashPassword($newPassword);
        return $this->update($userId, ['password_hash' => $hashedPassword]);
    }

    /**
     * Create admin user with hashed password
     */
    public function createAdmin($data)
    {
        $data['password_hash'] = $this->hashPassword($data['password']);
        unset($data['password']); // Remove plain password
        $data['role'] = 'admin';
        $data['status'] = 'active';
        
        return $this->insert($data);
    }
}
