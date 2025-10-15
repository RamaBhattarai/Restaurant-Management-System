<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CategoryModel;

class CategoryController extends BaseController
{
    protected $model;

    public function __construct()
    {
        $this->model = new CategoryModel();
    }

    protected function setCorsHeaders()
    {
        $this->response->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
        $this->response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $this->response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        return $this->response;
    }

    // GET /admin/categories
    public function index()
    {
        $this->setCorsHeaders();
        $categories = $this->model->findAll();
        return $this->response->setJSON($categories);
    }

    // POST /admin/categories
    public function create()
    {
        $this->setCorsHeaders();

        // Handle preflight OPTIONS request
        if ($this->request->getMethod() === 'OPTIONS') {
            return $this->response;
        }

        $data = $this->request->getJSON(true);

        if (!$this->validate([
            'name' => 'required|min_length[1]|max_length[100]'
        ])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $this->validator->getErrors()
            ])->setStatusCode(400);
        }

        try {
            $categoryId = $this->model->insert($data);

            if ($categoryId) {
                $category = $this->model->find($categoryId);
                return $this->response->setJSON([
                    'success' => true,
                    'message' => 'Category created successfully',
                    'category' => $category
                ]);
            } else {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Failed to create category'
                ])->setStatusCode(500);
            }
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    // PUT /admin/categories/{id}
    public function update($id = null)
    {
        $this->setCorsHeaders();

        // Handle preflight OPTIONS request
        if ($this->request->getMethod() === 'OPTIONS') {
            return $this->response;
        }

        if (!$id) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Category ID is required'
            ])->setStatusCode(400);
        }

        $data = $this->request->getJSON(true);

        if (!$this->validate([
            'name' => 'required|min_length[1]|max_length[100]'
        ])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $this->validator->getErrors()
            ])->setStatusCode(400);
        }

        try {
            $updated = $this->model->update($id, $data);

            if ($updated) {
                $category = $this->model->find($id);
                return $this->response->setJSON([
                    'success' => true,
                    'message' => 'Category updated successfully',
                    'category' => $category
                ]);
            } else {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Failed to update category'
                ])->setStatusCode(500);
            }
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    // DELETE /admin/categories/{id}
    public function delete($id = null)
    {
        $this->setCorsHeaders();

        // Handle preflight OPTIONS request
        if ($this->request->getMethod() === 'OPTIONS') {
            return $this->response;
        }

        if (!$id) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Category ID is required'
            ])->setStatusCode(400);
        }

        try {
            $deleted = $this->model->delete($id);

            if ($deleted) {
                return $this->response->setJSON([
                    'success' => true,
                    'message' => 'Category deleted successfully'
                ]);
            } else {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Failed to delete category'
                ])->setStatusCode(500);
            }
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ])->setStatusCode(500);
        }
    }
}