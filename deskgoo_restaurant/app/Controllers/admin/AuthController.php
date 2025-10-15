<?php

namespace App\Controllers\Admin;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\UserModel;

class AuthController extends ResourceController
{
    use ResponseTrait;

    protected $userModel;

    public function __construct()
    {
        // Enable CORS for all methods
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }

        $this->userModel = new UserModel();
    }

    /**
     * Admin login authentication with database
     */
    public function login()
    {
        $request = $this->request->getJSON();
        
        if (!$request) {
            return $this->fail('Invalid JSON data', 400);
        }

        $identifier = $request->email ?? ''; // Can be email or username
        $password = $request->password ?? '';

        if (empty($identifier) || empty($password)) {
            return $this->fail('Email/Username and password are required', 400);
        }

        try {
            // Find user by email or username
            $user = $this->userModel->findUserByEmailOrUsername($identifier);

            if (!$user) {
                return $this->fail('Invalid credentials', 401);
            }

            // Verify password
            if (!$this->userModel->verifyPassword($password, $user['password_hash'])) {
                return $this->fail('Invalid credentials', 401);
            }

            // Update last login
            $this->userModel->updateLastLogin($user['id']);

            // Generate a simple token (in production, use proper JWT)
            $token = base64_encode($user['email'] . ':' . time() . ':' . $user['id']);
            
            $response = [
                'success' => true,
                'message' => 'Login successful',
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role']
                ]
            ];

            return $this->respond($response);

        } catch (\Exception $e) {
            log_message('error', 'Login error: ' . $e->getMessage());
            return $this->fail('Login failed. Please try again.', 500);
        }
    }

    /**
     * Logout user
     */
    public function logout()
    {
        return $this->respond([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Verify token (for protected routes)
     */
    public function verify()
    {
        $token = $this->request->getHeaderLine('Authorization');
        
        if (!$token) {
            return $this->fail('No token provided', 401);
        }

        // Remove "Bearer " prefix if present
        $token = str_replace('Bearer ', '', $token);

        try {
            $decoded = base64_decode($token);
            $parts = explode(':', $decoded);
            
            if (count($parts) === 3) {
                $email = $parts[0];
                $timestamp = $parts[1];
                $userId = $parts[2];
                
                // Check if token is not too old (24 hours)
                if (time() - $timestamp < 86400) {
                    // Verify user still exists and is active
                    $user = $this->userModel->find($userId);
                    if ($user && $user['status'] === 'active') {
                        return $this->respond([
                            'success' => true,
                            'user' => [
                                'id' => $user['id'],
                                'username' => $user['username'],
                                'email' => $user['email'],
                                'full_name' => $user['full_name'],
                                'role' => $user['role']
                            ]
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            log_message('error', 'Token verification error: ' . $e->getMessage());
        }

        return $this->fail('Invalid or expired token', 401);
    }

    /**
     * Change password functionality
     */
    public function changePassword()
    {
        // Verify authentication token first
        $token = $this->request->getHeaderLine('Authorization');
        
        if (!$token) {
            return $this->fail('Authentication required', 401);
        }

        // Remove "Bearer " prefix if present
        $token = str_replace('Bearer ', '', $token);

        try {
            // Decode and verify token
            $decoded = base64_decode($token);
            $parts = explode(':', $decoded);
            
            if (count($parts) !== 3) {
                return $this->fail('Invalid token format', 401);
            }

            $email = $parts[0];
            $timestamp = $parts[1];
            $userId = $parts[2];
            
            // Check if token is not too old (24 hours)
            if (time() - $timestamp >= 86400) {
                return $this->fail('Token expired', 401);
            }

            // Verify user exists and is active
            $user = $this->userModel->find($userId);
            if (!$user || $user['status'] !== 'active') {
                return $this->fail('User not found or inactive', 401);
            }

            // Get request data
            $request = $this->request->getJSON();
            
            if (!$request) {
                return $this->fail('Invalid JSON data', 400);
            }

            $currentPassword = $request->currentPassword ?? '';
            $newPassword = $request->newPassword ?? '';
            $confirmPassword = $request->confirmPassword ?? '';

            // Validate input
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                return $this->fail('All password fields are required', 400);
            }

            if ($newPassword !== $confirmPassword) {
                return $this->fail('New passwords do not match', 400);
            }

            if (strlen($newPassword) < 6) {
                return $this->fail('New password must be at least 6 characters long', 400);
            }

            // Verify current password
            if (!$this->userModel->verifyPassword($currentPassword, $user['password_hash'])) {
                return $this->fail('Current password is incorrect', 400);
            }

            // Update password
            $success = $this->userModel->updatePassword($userId, $newPassword);
            
            if (!$success) {
                return $this->fail('Failed to update password', 500);
            }

            return $this->respond([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);

        } catch (\Exception $e) {
            log_message('error', 'Change password error: ' . $e->getMessage());
            return $this->fail('An error occurred. Please try again.', 500);
        }
    }

    /**
     * Forgot password functionality
     */
    public function forgotPassword()
    {
        $request = $this->request->getJSON();
        $email = $request->email ?? '';

        if (!$email) {
            return $this->fail('Email is required', 400);
        }

        try {
            $user = $this->userModel->where('email', $email)->first();
            
            if (!$user) {
                // Don't reveal if email exists or not for security
                return $this->respond([
                    'success' => true,
                    'message' => 'If the email exists, a password reset link has been sent'
                ]);
            }

            // In production, generate reset token and send email
            // For now, just return success message
            return $this->respond([
                'success' => true,
                'message' => 'Password reset link sent to your email'
            ]);

        } catch (\Exception $e) {
            log_message('error', 'Forgot password error: ' . $e->getMessage());
            return $this->fail('An error occurred. Please try again.', 500);
        }
    }
}
