<?php

namespace App\Models;

use CodeIgniter\Model;

class CompanySettingsModel extends Model
{
    protected $table = 'company_settings';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'company_name',
        'company_logo', 
        'vat_number',
        'phone',
        'email',
        'address',
        'website',
        'facebook_url',
        'instagram_url',
        'tiktok_url',
    ];
    protected $useTimestamps = true;
    protected $returnType = 'array';
    
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Get company settings (always returns the first/only record)
     */
    public function getSettings()
    {
        $settings = $this->first();
        
        // If no settings exist, create default ones
        if (!$settings) {
            $defaultSettings = [
                'company_name' => 'Deskgoo Restaurant',
                'vat_number' => '',
                'phone' => '',
                'email' => '',
                'address' => '',
                'website' => '',
            ];
            
            $this->insert($defaultSettings);
            $settings = $this->first();
        }
        
        return $settings;
    }

    /**
     * Update company settings
     */
    public function updateSettings($data)
    {
        $settings = $this->getSettings();
        
        if ($settings) {
            return $this->update($settings['id'], $data);
        } else {
            return $this->insert($data);
        }
    }

    /**
     * Get company logo URL
     */
    public function getLogoUrl()
    {
        $settings = $this->getSettings();
        
        if ($settings && $settings['company_logo']) {
            return base_url($settings['company_logo']);
        }
        
        // Return default logo if no company logo is set
        return base_url('uploads/default-logo.png');
    }
}
