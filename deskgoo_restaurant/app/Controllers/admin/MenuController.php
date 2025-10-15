<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\MenuItemModel;

class MenuController extends BaseController
{
    protected $model;

    public function __construct()
    {
        $this->model = new MenuItemModel();
    }

    protected function setCorsHeaders()
    {
        $this->response->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
        $this->response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $this->response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        return $this->response;
    }

    // GET /admin/menu-items
    public function index()
    {
        $this->setCorsHeaders();
        $items = $this->model->findAll();
        
        // Process image URLs for frontend
        foreach ($items as &$item) {
            // Create image_url field from image field
            $item['image_url'] = $item['image'];
        }
        
        return $this->response->setJSON($items);
    }

    // POST /admin/menu-items
    public function create()
    {
        $this->setCorsHeaders();
        
        // Handle preflight OPTIONS request
        if ($this->request->getMethod() === 'OPTIONS') {
            return $this->response;
        }
        
        $data = $this->request->getJSON(true);

        if (!isset($data['name']) || !isset($data['price'])) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'Name and Price are required']);
        }

        $id = $this->model->insert([
            'name' => $data['name'],
            'price' => $data['price'],
            'category' => !empty(trim($data['category'] ?? '')) ? trim($data['category']) : 'food',
            'image' => $data['image'] ?? null,
            'is_active' => $data['is_active'] ?? 1
        ]);

        $item = $this->model->find($id);
        // Add image_url field for frontend
        $item['image_url'] = $item['image'];
        return $this->response->setJSON($item);
    }

    // PUT /admin/menu-items/{id}
    public function update($id)
    {
        $this->setCorsHeaders();
        
        // Handle preflight OPTIONS request
        if ($this->request->getMethod() === 'OPTIONS') {
            return $this->response;
        }
        
        $data = $this->request->getJSON(true);

        $this->model->update($id, [
            'name' => $data['name'] ?? null,
            'price' => $data['price'] ?? null,
            'category' => !empty(trim($data['category'] ?? '')) ? trim($data['category']) : null,
            'image' => $data['image'] ?? null,
            'is_active' => $data['is_active'] ?? 1
        ]);

        $item = $this->model->find($id);
        // Add image_url field for frontend
        $item['image_url'] = $item['image'];
        return $this->response->setJSON($item);
    }

    // DELETE /admin/menu-items/{id}
    public function delete($id)
    {
        $this->setCorsHeaders();
        
        // Handle preflight OPTIONS request
        if ($this->request->getMethod() === 'OPTIONS') {
            return $this->response;
        }
        
        $this->model->delete($id);
        return $this->response->setJSON(['message' => 'Menu item deleted']);
    }

    // POST /admin/menu-items/upload-image
    public function uploadImage()
    {
        $this->setCorsHeaders();
        
        // Handle preflight OPTIONS request
        if ($this->request->getMethod() === 'OPTIONS') {
            return $this->response;
        }

        $file = $this->request->getFile('image');
        
        if (!$file || !$file->isValid()) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'No valid image file uploaded']);
        }

        // Validate image type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedTypes)) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'Invalid image type. Only JPEG, PNG, GIF, and WebP are allowed']);
        }

        // Validate file size (5MB max)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file->getSize() > $maxSize) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'File size too large. Maximum 5MB allowed']);
        }

        // Create uploads directory if it doesn't exist
        $uploadPath = WRITEPATH . 'uploads/menu-images/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        // Generate unique filename
        $newName = $file->getRandomName();
        
        // Move file to uploads directory
        if ($file->move($uploadPath, $newName)) {
            // Return the image URL
            $imageUrl = base_url('uploads/menu-images/' . $newName);
            return $this->response->setJSON(['image_url' => $imageUrl]);
        } else {
            return $this->response->setStatusCode(500)
                ->setJSON(['error' => 'Failed to upload image']);
        }
    }
}
