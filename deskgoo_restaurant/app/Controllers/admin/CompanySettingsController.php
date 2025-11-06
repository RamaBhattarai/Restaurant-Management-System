<?php

namespace App\Controllers\Admin;

use CodeIgniter\RESTful\ResourceController;
use App\Models\CompanySettingsModel;

class CompanySettingsController extends ResourceController
{
    protected $format = 'json';
    protected $companySettingsModel;

    public function __construct()
    {
        $this->companySettingsModel = new CompanySettingsModel();
    }

    /**
     * Get company settings
     */
    public function index()
    {
        try {
            $settings = $this->companySettingsModel->getSettings();
            
            return $this->respond([
                'success' => true,
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Error fetching company settings: ' . $e->getMessage());
            return $this->failServerError('Failed to fetch company settings');
        }
    }

    /**
     * Update company settings
     */
    public function update($id = null)
    {
        $data = $this->request->getJSON(true);

        try {
            // Validate required fields
            $validation = \Config\Services::validation();
            $validation->setRules([
                'company_name' => 'required|min_length[2]|max_length[255]',
                'email' => 'permit_empty|valid_email',
                'phone' => 'permit_empty|max_length[20]',
                'vat_number' => 'permit_empty|max_length[50]',
                'pan_number' => 'permit_empty|max_length[50]',
                'website' => 'permit_empty|valid_url_strict',
            ]);

            if (!$validation->run($data)) {
                return $this->fail($validation->getErrors());
            }

            // Update settings
            $result = $this->companySettingsModel->updateSettings($data);
            
            if ($result) {
                $updatedSettings = $this->companySettingsModel->getSettings();
                
                return $this->respond([
                    'success' => true,
                    'message' => 'Company settings updated successfully',
                    'data' => $updatedSettings
                ]);
            } else {
                return $this->failServerError('Failed to update company settings');
            }
        } catch (\Exception $e) {
            log_message('error', 'Error updating company settings: ' . $e->getMessage());
            return $this->failServerError('Internal server error: ' . $e->getMessage());
        }
    }

    /**
     * Upload company logo
     */
    public function uploadLogo()
    {
        try {
            $file = $this->request->getFile('logo');
            
            if (!$file || !$file->isValid()) {
                return $this->fail('No valid file uploaded');
            }

            // Validate file type
            if (!$file->hasMoved() && in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                
                // Create uploads directory if it doesn't exist
                $uploadPath = WRITEPATH . 'uploads/company-logos/';
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }

                // Generate unique filename
                $fileName = 'company-logo-' . time() . '.' . $file->getExtension();
                
                // Move file
                if ($file->move($uploadPath, $fileName)) {
                    
                    // Delete old logo if exists
                    $currentSettings = $this->companySettingsModel->getSettings();
                    if ($currentSettings && $currentSettings['company_logo']) {
                        $oldLogoPath = WRITEPATH . $currentSettings['company_logo'];
                        if (file_exists($oldLogoPath)) {
                            unlink($oldLogoPath);
                        }
                    }
                    
                    // Update database with new logo path
                    $logoPath = 'uploads/company-logos/' . $fileName;
                    $this->companySettingsModel->updateSettings(['company_logo' => $logoPath]);
                    
                    return $this->respond([
                        'success' => true,
                        'message' => 'Logo uploaded successfully',
                        'logo_url' => base_url($logoPath),
                        'logo_path' => $logoPath
                    ]);
                } else {
                    return $this->failServerError('Failed to move uploaded file');
                }
            } else {
                return $this->fail('Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.');
            }
        } catch (\Exception $e) {
            log_message('error', 'Error uploading logo: ' . $e->getMessage());
            return $this->failServerError('Internal server error: ' . $e->getMessage());
        }
    }

    /**
     * Delete company logo
     */
    public function deleteLogo()
    {
        try {
            $currentSettings = $this->companySettingsModel->getSettings();
            
            if ($currentSettings && $currentSettings['company_logo']) {
                // Delete file from disk
                $logoPath = WRITEPATH . $currentSettings['company_logo'];
                if (file_exists($logoPath)) {
                    unlink($logoPath);
                }
                
                // Remove from database
                $this->companySettingsModel->updateSettings(['company_logo' => null]);
                
                return $this->respond([
                    'success' => true,
                    'message' => 'Logo deleted successfully'
                ]);
            } else {
                return $this->fail('No logo to delete');
            }
        } catch (\Exception $e) {
            log_message('error', 'Error deleting logo: ' . $e->getMessage());
            return $this->failServerError('Internal server error: ' . $e->getMessage());
        }
    }
}
