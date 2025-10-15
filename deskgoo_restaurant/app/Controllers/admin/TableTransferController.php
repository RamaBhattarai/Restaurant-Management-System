<?php

namespace App\Controllers\Admin;
use App\Controllers\BaseController;
use App\Models\OrderModel;
use App\Models\TableModel;
use App\Models\OrderItemModel;

class TableTransferController extends BaseController
{
    protected $orderModel;
    protected $tableModel;
    protected $orderItemModel;

    public function __construct()
    {
        $this->orderModel = new OrderModel();
        $this->tableModel = new TableModel();
        $this->orderItemModel = new OrderItemModel();
    }

    // Get active orders for tables that have orders
    public function getActiveOrders()
    {
        try {
            // Get all active orders (not paid/completed) with table info
            $orders = $this->orderModel
                ->select('orders.*, dining_tables.label as table_label, dining_tables.area_id')
                ->join('dining_tables', 'dining_tables.id = orders.table_id')
                ->where('(orders.status IS NULL OR orders.status = "" OR orders.status IN ("pending", "preparing", "ready", "draft"))')
                ->findAll();

            return $this->response->setJSON([
                'success' => true,
                'data' => $orders
            ]);
        } catch (\Exception $e) {
            log_message('error', 'TableTransferController::getActiveOrders - ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error' => 'Failed to retrieve active orders'
            ]);
        }
    }

    // Get order details with items for a specific table
    public function getOrderByTable($tableId)
    {
        try {
            // Get active order for the table
            $order = $this->orderModel
                ->select('orders.*, dining_tables.label as table_label')
                ->join('dining_tables', 'dining_tables.id = orders.table_id')
                ->where('orders.table_id', $tableId)
                ->where('(orders.status IS NULL OR orders.status = "" OR orders.status IN ("pending", "preparing", "ready", "draft"))')
                ->first();

            if (!$order) {
                return $this->response->setStatusCode(404)->setJSON([
                    'success' => false,
                    'error' => 'No active order found for this table'
                ]);
            }

            // Get order items
            $orderItems = $this->orderItemModel
                ->select('order_items.*, menu_items.name as item_name, menu_items.price as item_price')
                ->join('menu_items', 'menu_items.id = order_items.menu_item_id')
                ->where('order_items.order_id', $order['id'])
                ->findAll();

            $order['items'] = $orderItems;

            return $this->response->setJSON([
                'success' => true,
                'data' => $order
            ]);
        } catch (\Exception $e) {
            log_message('error', 'TableTransferController::getOrderByTable - ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error' => 'Failed to retrieve order details'
            ]);
        }
    }

    // Transfer order from one table to another
    public function transferOrder()
    {
        try {
            $data = $this->request->getJSON(true);

            if (!$data || !isset($data['order_id']) || !isset($data['new_table_id'])) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'error' => 'Order ID and new table ID are required'
                ]);
            }

            $orderId = $data['order_id'];
            $newTableId = $data['new_table_id'];
            $notes = $data['notes'] ?? '';

            // Verify the order exists and is active
            $order = $this->orderModel->find($orderId);
            if (!$order) {
                return $this->response->setStatusCode(404)->setJSON([
                    'success' => false,
                    'error' => 'Order not found'
                ]);
            }

            if (!in_array($order['status'], ['pending', 'preparing', 'ready', 'draft', '', null])) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'error' => 'Cannot transfer completed or paid orders'
                ]);
            }

            // Verify the new table exists and is available
            $newTable = $this->tableModel->find($newTableId);
            if (!$newTable) {
                return $this->response->setStatusCode(404)->setJSON([
                    'success' => false,
                    'error' => 'Destination table not found'
                ]);
            }

            // Check if the new table already has an active order
            $existingOrder = $this->orderModel
                ->where('table_id', $newTableId)
                ->where('(status IS NULL OR status = "" OR status IN ("pending", "preparing", "ready", "draft"))')
                ->first();

            if ($existingOrder) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'error' => 'Destination table already has an active order'
                ]);
            }

            // Start database transaction
            $db = \Config\Database::connect();
            $db->transStart();

            // Update the order with new table ID and add transfer notes
            $updateData = [
                'table_id' => $newTableId,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            if ($notes) {
                $currentNotes = $order['notes'] ?? '';
                $transferNote = "\n[TRANSFERRED] " . date('Y-m-d H:i:s') . ": " . $notes;
                $updateData['notes'] = $currentNotes . $transferNote;
            }

            $this->orderModel->update($orderId, $updateData);

            // Update old table status to available if needed
            $oldTableId = $order['table_id'];
            $this->tableModel->update($oldTableId, ['status' => 'available']);

            // Update new table status to occupied
            $this->tableModel->update($newTableId, ['status' => 'occupied']);

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->response->setStatusCode(500)->setJSON([
                    'success' => false,
                    'error' => 'Failed to transfer order'
                ]);
            }

            // Get updated order details
            $updatedOrder = $this->orderModel
                ->select('orders.*, dining_tables.label as table_label')
                ->join('dining_tables', 'dining_tables.id = orders.table_id')
                ->where('orders.id', $orderId)
                ->first();

            return $this->response->setJSON([
                'success' => true,
                'data' => $updatedOrder,
                'message' => 'Order transferred successfully'
            ]);
        } catch (\Exception $e) {
            log_message('error', 'TableTransferController::transferOrder - ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error' => 'Failed to transfer order'
            ]);
        }
    }

    // Get available tables for transfer (excluding tables with active orders)
    public function getAvailableTables()
    {
        try {
            // Get all tables that don't have active orders
            $tablesWithOrders = $this->orderModel
                ->select('table_id')
                ->where('(status IS NULL OR status = "" OR status IN ("pending", "preparing", "ready", "draft"))')
                ->findAll();

            $excludeTableIds = array_column($tablesWithOrders, 'table_id');

            $query = $this->tableModel
                ->select('dining_tables.*, areas.name as area_name')
                ->join('areas', 'areas.id = dining_tables.area_id');

            if (!empty($excludeTableIds)) {
                $query = $query->whereNotIn('dining_tables.id', $excludeTableIds);
            }

            $availableTables = $query->findAll();

            return $this->response->setJSON([
                'success' => true,
                'data' => $availableTables
            ]);
        } catch (\Exception $e) {
            log_message('error', 'TableTransferController::getAvailableTables - ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error' => 'Failed to retrieve available tables'
            ]);
        }
    }
}
