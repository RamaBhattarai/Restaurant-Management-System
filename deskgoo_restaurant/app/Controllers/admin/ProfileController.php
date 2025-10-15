<?php

namespace App\Controllers\Admin;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\UserModel;

class ProfileController extends ResourceController
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
     * Get user profile information
     */
    public function show($id = null)
    {
        try {
            $user = $this->userModel->find($id);
            
            if (!$user) {
                return $this->failNotFound('User not found');
            }

            // Remove sensitive information
            unset($user['password_hash']);

            // Convert profile picture path to full URL if it exists
            if (!empty($user['profile_picture'])) {
                $user['profile_picture'] = base_url($user['profile_picture']);
            }

            return $this->respond([
                'success' => true,
                'user' => $user
            ]);

        } catch (\Exception $e) {
            log_message('error', 'Profile fetch error: ' . $e->getMessage());
            return $this->fail('Failed to fetch profile', 500);
        }
    }

    /**
     * Update user profile information
     */
    public function update($id = null)
    {
        try {
            // Log the incoming request
            log_message('info', 'Profile update request for user ID: ' . $id);
            log_message('info', 'Request method: ' . $this->request->getMethod());
            log_message('info', 'Content type: ' . $this->request->getHeaderLine('Content-Type'));
            
            $request = $this->request->getJSON(true); // Get as array
            log_message('info', 'Request data: ' . json_encode($request));
            
            if (!$request) {
                return $this->fail('Invalid JSON data', 400);
            }

            $user = $this->userModel->find($id);
            if (!$user) {
                return $this->failNotFound('User not found');
            }

            // Prepare update data - only include fields that are allowed
            $updateData = [];
            
            if (isset($request['username']) && !empty($request['username'])) {
                $updateData['username'] = $request['username'];
            }
            
            if (isset($request['email']) && !empty($request['email'])) {
                $updateData['email'] = $request['email'];
            }
            
            if (isset($request['full_name']) && !empty($request['full_name'])) {
                $updateData['full_name'] = $request['full_name'];
            }

            // Only update if we have data to update
            if (empty($updateData)) {
                return $this->fail('No valid data provided for update', 400);
            }

            $updateData['updated_at'] = date('Y-m-d H:i:s');
            log_message('info', 'Update data: ' . json_encode($updateData));

            // Update user - skip validation for profile updates
            $this->userModel->skipValidation(true);
            $updated = $this->userModel->update($id, $updateData);
            $this->userModel->skipValidation(false);

            log_message('info', 'Update result: ' . ($updated ? 'success' : 'failed'));

            if ($updated) {
                // Get updated user data
                $updatedUser = $this->userModel->find($id);
                unset($updatedUser['password_hash']);

                // Convert profile picture path to full URL if it exists
                if (!empty($updatedUser['profile_picture'])) {
                    $updatedUser['profile_picture'] = base_url($updatedUser['profile_picture']);
                }

                return $this->respond([
                    'success' => true,
                    'message' => 'Profile updated successfully',
                    'user' => $updatedUser
                ]);
            } else {
                // Get validation errors if any
                $errors = $this->userModel->errors();
                $errorMsg = !empty($errors) ? implode(', ', $errors) : 'Unknown error';
                log_message('error', 'Update failed with errors: ' . $errorMsg);
                return $this->fail('Failed to update profile: ' . $errorMsg, 500);
            }

        } catch (\Exception $e) {
            log_message('error', 'Profile update error: ' . $e->getMessage());
            log_message('error', 'Stack trace: ' . $e->getTraceAsString());
            return $this->fail('Failed to update profile: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Upload profile picture
     */
    public function uploadPicture($id = null)
    {
        try {
            $user = $this->userModel->find($id);
            if (!$user) {
                return $this->failNotFound('User not found');
            }

            $file = $this->request->getFile('profile_picture');
            
            if (!$file) {
                return $this->fail('No file uploaded', 400);
            }

            if (!$file->isValid()) {
                return $this->fail('Invalid file upload', 400);
            }

            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!in_array($file->getMimeType(), $allowedTypes)) {
                return $this->fail('Invalid file type. Only JPEG, PNG, and GIF are allowed', 400);
            }

            // Validate file size (5MB max)
            if ($file->getSize() > 5 * 1024 * 1024) {
                return $this->fail('File size too large. Maximum 5MB allowed', 400);
            }

            // Create upload directory if it doesn't exist
            $uploadPath = WRITEPATH . 'uploads/profile-pictures/';
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            // Generate unique filename
            $fileName = 'profile_' . $id . '_' . time() . '.' . $file->getExtension();
            
            // Move file to upload directory
            if ($file->move($uploadPath, $fileName)) {
                // Update user record with profile picture path
                $profilePicturePath = 'uploads/profile-pictures/' . $fileName;
                
                $updated = $this->userModel->update($id, [
                    'profile_picture' => $profilePicturePath,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                if ($updated) {
                    return $this->respond([
                        'success' => true,
                        'message' => 'Profile picture uploaded successfully',
                        'profile_picture_url' => base_url($profilePicturePath)
                    ]);
                } else {
                    // Clean up uploaded file if database update fails
                    unlink($uploadPath . $fileName);
                    return $this->fail('Failed to update profile picture in database', 500);
                }
            } else {
                return $this->fail('Failed to upload file', 500);
            }

        } catch (\Exception $e) {
            log_message('error', 'Profile picture upload error: ' . $e->getMessage());
            return $this->fail('Failed to upload profile picture', 500);
        }
    }

    /**
     * Delete profile picture
     */
    public function deletePicture($id = null)
    {
        try {
            $user = $this->userModel->find($id);
            if (!$user) {
                return $this->failNotFound('User not found');
            }

            // Delete file if exists
            if (!empty($user['profile_picture'])) {
                $filePath = WRITEPATH . $user['profile_picture'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            // Update database
            $updated = $this->userModel->update($id, [
                'profile_picture' => null,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            if ($updated) {
                return $this->respond([
                    'success' => true,
                    'message' => 'Profile picture deleted successfully'
                ]);
            } else {
                return $this->fail('Failed to delete profile picture', 500);
            }

        } catch (\Exception $e) {
            log_message('error', 'Profile picture delete error: ' . $e->getMessage());
            return $this->fail('Failed to delete profile picture', 500);
        }
    }
}
