<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\TakeawayModel;
use App\Models\OrderModel;

class TakeawayController extends BaseController
{
    protected $takeawayModel;
    protected $orderModel;

    public function __construct()
    {
        $this->takeawayModel = new TakeawayModel();
        $this->orderModel = new OrderModel();
    }

    /**
     * GET /admin/takeaways
     * Get all active takeaways, optionally filtered by date
     */
    public function index()
    {
        try {
            log_message('info', 'TakeawayController::index called');
            
            // Get date filter from query parameters
            $date = $this->request->getGet('date');
            
            $takeaways = $this->takeawayModel->getActiveTakeawaysWithOrders($date);
            log_message('info', 'Takeaways found: ' . count($takeaways));
            log_message('info', 'Takeaways data: ' . json_encode($takeaways));
            
            return $this->response->setJSON([
                'status' => 'success',
                'data' => $takeaways
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Error fetching takeaways: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'Failed to fetch takeaways'
            ]);
        }
    }

    /**
     * POST /admin/takeaways
     * Create new takeaway
     */
    public function store()
    {
        try {
            log_message('info', 'TakeawayController::store called');
            
            $takeaway = $this->takeawayModel->createTakeaway();
            
            if ($takeaway) {
                log_message('info', 'Takeaway created: ' . json_encode($takeaway));
                
                // Add order counts for consistency with index response
                $takeaway['order_count'] = 0;
                $takeaway['active_order_count'] = 0;
                $takeaway['completed_order_count'] = 0;
                $takeaway['total_amount'] = '0.00';
                
                return $this->response->setStatusCode(201)->setJSON([
                    'status' => 'success',
                    'message' => 'Takeaway created successfully',
                    'data' => $takeaway
                ]);
            } else {
                log_message('error', 'Failed to create takeaway - model returned false');
                return $this->response->setStatusCode(400)->setJSON([
                    'status' => 'error',
                    'message' => 'Failed to create takeaway'
                ]);
            }
        } catch (\Exception $e) {
            log_message('error', 'Exception in TakeawayController::store: ' . $e->getMessage());
            log_message('error', 'Stack trace: ' . $e->getTraceAsString());
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'Failed to create takeaway: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * GET /admin/takeaways/{id}
     * Get specific takeaway with orders
     */
    public function show($id)
    {
        try {
            $takeaway = $this->takeawayModel->find($id);
            
            if (!$takeaway) {
                return $this->response->setStatusCode(404)->setJSON([
                    'status' => 'error',
                    'message' => 'Takeaway not found'
                ]);
            }

            // Get orders for this takeaway
            $orders = $this->orderModel->where('takeaway_id', $id)
                                    ->where('deleted_at', null)
                                    ->findAll();

            $takeaway['orders'] = $orders;
            
            return $this->response->setJSON([
                'status' => 'success',
                'data' => $takeaway
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Error fetching takeaway: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'Failed to fetch takeaway'
            ]);
        }
    }

    /**
     * PUT /admin/takeaways/{id}/complete
     * Complete takeaway
     */
    public function complete($id)
    {
        try {
            $takeaway = $this->takeawayModel->find($id);
            
            if (!$takeaway) {
                return $this->response->setStatusCode(404)->setJSON([
                    'status' => 'error',
                    'message' => 'Takeaway not found'
                ]);
            }

            // Check if there are any unpaid orders
            $unpaidOrders = $this->orderModel->where('takeaway_id', $id)
                                           ->whereNotIn('status', ['paid', 'completed', 'cancelled'])
                                           ->where('deleted_at', null)
                                           ->findAll();

            if (!empty($unpaidOrders)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'status' => 'error',
                    'message' => 'Cannot complete takeaway with unpaid orders'
                ]);
            }

            $result = $this->takeawayModel->completeTakeaway($id);
            
            if ($result) {
                return $this->response->setJSON([
                    'status' => 'success',
                    'message' => 'Takeaway completed successfully'
                ]);
            } else {
                return $this->response->setStatusCode(400)->setJSON([
                    'status' => 'error',
                    'message' => 'Failed to complete takeaway'
                ]);
            }
        } catch (\Exception $e) {
            log_message('error', 'Error completing takeaway: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'Failed to complete takeaway'
            ]);
        }
    }

    /**
     * DELETE /admin/takeaways/{id}
     * Delete takeaway (soft delete)
     */
    public function delete($id)
    {
        try {
            $takeaway = $this->takeawayModel->find($id);
            
            if (!$takeaway) {
                return $this->response->setStatusCode(404)->setJSON([
                    'status' => 'error',
                    'message' => 'Takeaway not found'
                ]);
            }

            // Check if there are any orders
            $orders = $this->orderModel->where('takeaway_id', $id)
                                     ->where('deleted_at', null)
                                     ->findAll();

            if (!empty($orders)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'status' => 'error',
                    'message' => 'Cannot delete takeaway with existing orders'
                ]);
            }

            $result = $this->takeawayModel->delete($id);
            
            if ($result) {
                return $this->response->setJSON([
                    'status' => 'success',
                    'message' => 'Takeaway deleted successfully'
                ]);
            } else {
                return $this->response->setStatusCode(400)->setJSON([
                    'status' => 'error',
                    'message' => 'Failed to delete takeaway'
                ]);
            }
        } catch (\Exception $e) {
            log_message('error', 'Error deleting takeaway: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'Failed to delete takeaway'
            ]);
        }
    }

    /**
     * GET /admin/takeaway-orders
     * Get all takeaway orders with details, optionally filtered by date
     */
    public function getTakeawayOrders()
    {
        try {
            $db = \Config\Database::connect();
            
            // Get date filter from query parameters
            $date = $this->request->getGet('date');
            
            // First, let's check if there are any orders with takeaway_id
            $debugQuery = "SELECT COUNT(*) as count FROM orders WHERE takeaway_id IS NOT NULL";
            $debugResult = $db->query($debugQuery)->getRowArray();
            log_message('info', 'Total orders with takeaway_id: ' . $debugResult['count']);
            
            // Check recent orders specifically
            $recentQuery = "SELECT id, takeaway_id, order_type, status, created_at FROM orders WHERE takeaway_id IS NOT NULL ORDER BY created_at DESC LIMIT 5";
            $recentResult = $db->query($recentQuery)->getResultArray();
            log_message('info', 'Recent takeaway orders: ' . json_encode($recentResult));
            
            $sql = "
                SELECT 
                    o.*,
                    t.takeaway_number,
                    t.status as takeaway_status,
                    t.deleted_at as takeaway_deleted_at
                FROM orders o
                LEFT JOIN takeaways t ON o.takeaway_id = t.id
                WHERE o.takeaway_id IS NOT NULL 
                AND t.deleted_at IS NULL";
            
            $params = [];
            
            // Add date filter if provided
            if ($date) {
                $sql .= " AND DATE(o.created_at) = ?";
                $params[] = $date;
            }
            
            $sql .= " ORDER BY o.created_at DESC";
            
            log_message('info', 'Executing takeaway orders query: ' . $sql);
            log_message('info', 'Query parameters: ' . json_encode($params));
            
            $query = $db->query($sql, $params);
            $orders = $query->getResultArray();
            
            log_message('info', 'Found ' . count($orders) . ' takeaway orders');
            if (count($orders) > 0) {
                log_message('info', 'Sample order: ' . json_encode($orders[0]));
            }
            
            // Get order items with proper price information for each order
            $orderItemModel = new \App\Models\OrderItemModel();
            $processedOrders = array_map(function($order) use ($orderItemModel) {
                // Get order items with menu item details and pricing
                $items = $orderItemModel->getOrderItemsWithMenuDetails($order['id']);
                $order['items'] = $items;
                return $order;
            }, $orders);
            
            log_message('info', 'Returning ' . count($processedOrders) . ' processed takeaway orders');
            
            return $this->response->setJSON([
                'status' => 'success',
                'data' => $processedOrders
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Error fetching takeaway orders: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'Failed to fetch takeaway orders'
            ]);
        }
    }
}