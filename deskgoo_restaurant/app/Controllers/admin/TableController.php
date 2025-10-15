<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\TableModel;
use App\Models\OrderModel;
use App\Models\OrderItemModel;

class TableController extends BaseController
{
    protected $model;
    protected $orderModel;
    protected $orderItemModel;

    public function __construct()
    {
        $this->model = new TableModel();
        $this->orderModel = new OrderModel();
        $this->orderItemModel = new OrderItemModel();
    }

    protected function setCorsHeaders()
    {
        $this->response->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
        $this->response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $this->response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        return $this->response;
    }

    // GET /admin/tables
    public function index()
    {
        $this->setCorsHeaders();
        $tables = $this->model->findAll();
        return $this->response->setJSON($tables);
    }

    // POST /admin/tables
    public function create()
    {
        $this->setCorsHeaders();
        
        // Handle preflight OPTIONS request
        if ($this->request->getMethod() === 'OPTIONS') {
            return $this->response;
        }
        
        $data = $this->request->getJSON(true);

        if (!isset($data['area_id']) || !isset($data['label'])) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'Area ID and Label are required']);
        }

        $tableId = $this->model->insert([
            'area_id' => $data['area_id'],
            'label' => $data['label'],
            'seats' => $data['seats'] ?? 4,
            'status' => $data['status'] ?? 'available'
        ]);

        $table = $this->model->find($tableId);
        return $this->response->setJSON($table);
    }

    // PUT /admin/tables/{id}
    public function update($id)
    {
        $this->setCorsHeaders();
        
        // Handle preflight OPTIONS request
        if ($this->request->getMethod() === 'OPTIONS') {
            return $this->response;
        }
        
        $data = $this->request->getJSON(true);

        $this->model->update($id, [
            'area_id' => $data['area_id'] ?? null,
            'label' => $data['label'] ?? null,
            'seats' => $data['seats'] ?? 4,
            'status' => $data['status'] ?? 'available'
        ]);

        $table = $this->model->find($id);
        return $this->response->setJSON($table);
    }

    // DELETE /admin/tables/{id}
    public function delete($id)
    {
        $this->setCorsHeaders();
        
        // Handle preflight OPTIONS request
        if ($this->request->getMethod() === 'OPTIONS') {
            return $this->response;
        }
        
        try {
            // Check if table exists
            $table = $this->model->find($id);
            if (!$table) {
                return $this->response->setStatusCode(404)
                    ->setJSON(['error' => 'Table not found']);
            }
            
            // Check for active/pending orders on this table
            $activeOrders = $this->orderModel->where('table_id', $id)
                ->where('status', 'pending')
                ->findAll();
            
            if (!empty($activeOrders)) {
                return $this->response->setStatusCode(400)
                    ->setJSON([
                        'error' => 'Cannot delete table with active orders',
                        'message' => "Table {$table['label']} has " . count($activeOrders) . " pending order(s). Complete or cancel all orders before deleting the table."
                    ]);
            }
            
            // Get completed/cancelled orders to clean up
            $completedOrders = $this->orderModel->where('table_id', $id)
                ->whereIn('status', ['completed', 'cancelled'])
                ->findAll();
            
            // Delete order items first, then orders (to respect foreign key constraints)
            foreach ($completedOrders as $order) {
                // Delete order items for this order
                $this->orderItemModel->where('order_id', $order['id'])->delete();
            }
            
            // Now delete the completed/cancelled orders
            $this->orderModel->where('table_id', $id)
                ->whereIn('status', ['completed', 'cancelled'])
                ->delete();
            
            // Now safe to delete table
            
            // Safe to delete - no active orders
            $this->model->delete($id);
            return $this->response->setJSON(['message' => 'Table deleted successfully']);
            
        } catch (\Exception $e) {
            log_message('error', 'Error deleting table: ' . $e->getMessage());
            return $this->response->setStatusCode(500)
                ->setJSON([
                    'error' => 'Failed to delete table',
                    'message' => 'An internal error occurred. Please try again.'
                ]);
        }
    }
}
