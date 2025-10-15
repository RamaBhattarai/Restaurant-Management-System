<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AreaModel;
use App\Models\TableModel;
use App\Models\OrderModel;

class AreaController extends BaseController
{
    protected $model;
    protected $tableModel;
    protected $orderModel;

    public function __construct()
    {
        $this->model = new AreaModel();
        $this->tableModel = new TableModel();
        $this->orderModel = new OrderModel();
    }

    protected function setCorsHeaders()
    {
        $this->response->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
        $this->response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $this->response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        return $this->response;
    }

    // GET /admin/areas
    public function index()
    {
        $this->setCorsHeaders();
        $areas = $this->model->findAll();
        return $this->response->setJSON($areas);
    }

    // POST /admin/areas
    public function create()
    {
        $this->setCorsHeaders();
        
        // Handle preflight OPTIONS request
        if ($this->request->getMethod() === 'OPTIONS') {
            return $this->response;
        }
        
        $data = $this->request->getJSON(true);
        if (!isset($data['name'])) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'Name is required']);
        }

        $areaId = $this->model->insert([
            'name' => $data['name'],
            'status' => $data['status'] ?? 'active'
        ]);

        $area = $this->model->find($areaId);
        return $this->response->setJSON($area);
    }

    // PUT /admin/areas/{id}
    public function update($id)
    {
        $this->setCorsHeaders();
        
        // Handle preflight OPTIONS request
        if ($this->request->getMethod() === 'OPTIONS') {
            return $this->response;
        }
        
        $data = $this->request->getJSON(true);
        if (!isset($data['name'])) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'Name is required']);
        }

        $this->model->update($id, [
            'name' => $data['name'],
            'status' => $data['status'] ?? 'active'
        ]);

        $area = $this->model->find($id);
        return $this->response->setJSON($area);
    }

    // DELETE /admin/areas/{id}
    public function delete($id)
    {
        $this->setCorsHeaders();
        
        // Handle preflight OPTIONS request
        if ($this->request->getMethod() === 'OPTIONS') {
            return $this->response;
        }
        
        try {
            // Check if area exists
            $area = $this->model->find($id);
            if (!$area) {
                return $this->response->setStatusCode(404)
                    ->setJSON(['error' => 'Area not found']);
            }
            
            // Check for tables in this area
            $tables = $this->tableModel->where('area_id', $id)->findAll();
            
            if (!empty($tables)) {
                // Check if any tables have active orders
                $activeOrdersCount = 0;
                foreach ($tables as $table) {
                    $activeOrders = $this->orderModel->where('table_id', $table['id'])
                        ->where('status', 'pending')
                        ->countAllResults();
                    $activeOrdersCount += $activeOrders;
                }
                
                if ($activeOrdersCount > 0) {
                    return $this->response->setStatusCode(400)
                        ->setJSON([
                            'error' => 'Cannot delete area with active orders',
                            'message' => "This area has {$activeOrdersCount} active order(s). Complete or cancel all orders before deleting the area."
                        ]);
                }
                
                return $this->response->setStatusCode(400)
                    ->setJSON([
                        'error' => 'Cannot delete area with tables',
                        'message' => "This area contains " . count($tables) . " table(s). Delete all tables first before deleting the area."
                    ]);
            }
            
            // Safe to delete - no tables associated
            $this->model->delete($id);
            return $this->response->setJSON(['message' => 'Area deleted successfully']);
            
        } catch (\Exception $e) {
            log_message('error', 'Error deleting area: ' . $e->getMessage());
            return $this->response->setStatusCode(500)
                ->setJSON([
                    'error' => 'Failed to delete area',
                    'message' => 'An internal error occurred. Please try again.'
                ]);
        }
    }
}
