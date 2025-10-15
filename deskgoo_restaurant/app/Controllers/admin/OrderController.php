<?php

namespace App\Controllers\Admin;

use CodeIgniter\RESTful\ResourceController;
use App\Models\OrderModel;
use App\Models\OrderItemModel;
use App\Models\MenuItemModel;

class OrderController extends ResourceController
{
    protected $format = 'json';

    protected $orderModel;
    protected $orderItemModel;
    protected $menuItemModel;

    public function __construct()
    {
        $this->orderModel = new OrderModel();
        $this->orderItemModel = new OrderItemModel();
        $this->menuItemModel = new MenuItemModel();
    }

    // Create a new order with items (Complete POS order placement)
    public function createOrder()
    {
        $data = $this->request->getJSON(true);
        log_message('info', 'Order creation request data: ' . json_encode($data));

        // Validate that either table_id or takeaway_id is provided
        if ((!isset($data['table_id']) && !isset($data['takeaway_id'])) || !isset($data['items']) || empty($data['items'])) {
            log_message('error', 'Order creation failed: Missing required fields');
            return $this->fail('Either table_id or takeaway_id is required, along with items');
        }

        // Ensure only one type of ID is provided
        if (isset($data['table_id']) && isset($data['takeaway_id'])) {
            log_message('error', 'Order creation failed: Both table_id and takeaway_id provided');
            return $this->fail('Cannot specify both table_id and takeaway_id');
        }

        try {
            // Start transaction
            $db = \Config\Database::connect();
            $db->transStart();

            // Calculate total amount
            $totalAmount = 0;
            foreach ($data['items'] as $item) {
                $totalAmount += $item['total'];
            }

            // Create order data
            $orderData = [
                'total_amount' => $totalAmount,
                'status' => $data['status'] ?? OrderModel::STATUS_PENDING,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'notes' => $data['notes'] ?? null,
                'order_type' => $data['order_type'] ?? 'dine_in'
            ];

            // Add appropriate ID based on order type
            if (isset($data['table_id'])) {
                $orderData['table_id'] = $data['table_id'];
                $orderData['takeaway_id'] = null;
                $orderData['order_type'] = 'dine_in';
            } else if (isset($data['takeaway_id'])) {
                $orderData['table_id'] = null; // Explicitly set to NULL
                $orderData['takeaway_id'] = $data['takeaway_id'];
                $orderData['order_type'] = 'takeaway';
            }

            log_message('info', 'Final order data: ' . json_encode($orderData));

            $orderId = $this->orderModel->insert($orderData);

            // Add order items
            $addedItems = [];
            foreach ($data['items'] as $item) {
                $orderItemData = [
                    'order_id' => $orderId,
                    'menu_item_id' => $item['id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'total_price' => $item['total'],
                    'notes' => $item['note'] ?? null
                ];

                $orderItemId = $this->orderItemModel->insert($orderItemData);
                $addedItems[] = $this->orderItemModel->find($orderItemId);
            }

            // Complete transaction
            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->failServerError('Failed to create order');
            }

            // Return complete order with items
            $order = $this->orderModel->getOrderWithItems($orderId);
            
            return $this->respondCreated([
                'success' => true,
                'message' => 'Order created successfully',
                'order' => $order,
                'order_id' => $orderId
            ]);

        } catch (\Exception $e) {
            log_message('error', 'Error creating order: ' . $e->getMessage());
            return $this->failServerError('Internal server error: ' . $e->getMessage());
        }
    }

    // Add items to an existing order
    public function addItems($order_id = null)
    {
        if (!$order_id) {
            return $this->fail('Order ID is required');
        }

        $data = $this->request->getJSON(true);
        log_message('info', 'Add items request data: ' . json_encode($data));

        if (!isset($data['items']) || !is_array($data['items'])) {
            return $this->fail('Items array is required');
        }

        try {
            $addedItems = [];
            foreach ($data['items'] as $item) {
                log_message('info', 'Processing item: ' . json_encode($item));
                
                // Validate required fields
                if (!isset($item['menu_item_id']) || !isset($item['unit_price']) || !isset($item['total_price'])) {
                    return $this->fail('Missing required fields: menu_item_id, unit_price, total_price');
                }
                
                $orderItemData = [
                    'order_id' => $order_id,
                    'menu_item_id' => $item['menu_item_id'],
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'notes' => $item['notes'] ?? null
                ];

                log_message('info', 'Inserting order item data: ' . json_encode($orderItemData));
                
                $orderItemId = $this->orderItemModel->insert($orderItemData);
                if (!$orderItemId) {
                    log_message('error', 'Failed to insert order item: ' . json_encode($orderItemData));
                    return $this->failServerError('Failed to insert order item');
                }
                
                $addedItems[] = $this->orderItemModel->find($orderItemId);
            }

            return $this->respondCreated($addedItems);

        } catch (\Exception $e) {
            log_message('error', 'Error adding items to order: ' . $e->getMessage());
            return $this->failServerError('Internal server error: ' . $e->getMessage());
        }
    }

    // Update order item quantity
    public function updateOrderItem($order_id, $item_id)
    {
        $data = $this->request->getJSON(true);
        
        if (!isset($data['quantity']) || !is_numeric($data['quantity'])) {
            return $this->fail('Valid quantity is required');
        }

        $quantity = (int) $data['quantity'];
        
        if ($quantity <= 0) {
            return $this->fail('Quantity must be greater than 0. Use delete endpoint to remove items.');
        }

        try {
            // Get the order item first
            $orderItem = $this->orderItemModel->where(['order_id' => $order_id, 'id' => $item_id])->first();
            
            if (!$orderItem) {
                return $this->failNotFound('Order item not found');
            }

            // Calculate new total
            $newTotal = $quantity * $orderItem['unit_price'];
            
            // Update the order item
            $updateData = [
                'quantity' => $quantity,
                'total_price' => $newTotal
            ];
            
            if (isset($data['notes'])) {
                $updateData['notes'] = $data['notes'];
            }

            $success = $this->orderItemModel->update($item_id, $updateData);
            
            if (!$success) {
                return $this->failServerError('Failed to update order item');
            }

            // Return updated item
            $updatedItem = $this->orderItemModel->find($item_id);
            return $this->respond($updatedItem);

        } catch (\Exception $e) {
            log_message('error', 'Error updating order item: ' . $e->getMessage());
            return $this->failServerError('Internal server error: ' . $e->getMessage());
        }
    }

    // Remove/Cancel order item
    public function removeOrderItem($order_id, $item_id)
    {
        try {
            // Verify the item belongs to this order
            $orderItem = $this->orderItemModel->where(['order_id' => $order_id, 'id' => $item_id])->first();
            
            if (!$orderItem) {
                return $this->failNotFound('Order item not found');
            }

            // Delete the order item
            $success = $this->orderItemModel->delete($item_id);
            
            if (!$success) {
                return $this->failServerError('Failed to remove order item');
            }

            // Check if order has any remaining items
            $remainingItems = $this->orderItemModel->where('order_id', $order_id)->countAllResults();
            
            if ($remainingItems == 0) {
                // No items left in order, delete the order and free the table
                $order = $this->orderModel->find($order_id);
                if ($order) {
                    // Free the table
                    $tableModel = new \App\Models\TableModel();
                    $tableModel->update($order['table_id'], ['status' => 'available']);
                    
                    // Delete the empty order
                    $this->orderModel->delete($order_id);
                    
                    log_message('info', "Order $order_id deleted - no items remaining. Table {$order['table_id']} freed.");
                    
                    return $this->respondDeleted([
                        'message' => 'Order item removed successfully. Order deleted as no items remain.',
                        'order_deleted' => true,
                        'table_freed' => true
                    ]);
                }
            }

            return $this->respondDeleted([
                'message' => 'Order item removed successfully',
                'order_deleted' => false,
                'remaining_items' => $remainingItems
            ]);

        } catch (\Exception $e) {
            log_message('error', 'Error removing order item: ' . $e->getMessage());
            return $this->failServerError('Internal server error: ' . $e->getMessage());
        }
    }

    // Handle OPTIONS requests for CORS preflight
    public function options(...$params)
    {
        // This method handles all OPTIONS requests for CORS preflight
        return $this->response
            ->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->setHeader('Access-Control-Allow-Credentials', 'true')
            ->setStatusCode(200);
    }

    // Get order with items
    public function getOrder($order_id)
    {
        $order = $this->orderModel->getOrderWithItems($order_id);
        if (!$order) {
            return $this->failNotFound('Order not found');
        }

        return $this->respond($order);
    }

    // Get all orders (for order history) - Only dine-in orders for order management
    public function index()
    {
        try {
            // Filter for dine-in orders only (table orders) - Order Management is for dine-in only
            $orders = $this->orderModel->where('order_type', 'dine_in')->orderBy('created_at', 'DESC')->findAll();
            log_message('info', 'Fetched ' . count($orders) . ' dine-in orders from database');
            
            // Enhance each order with context info (table information) and items
            foreach ($orders as &$order) {
                log_message('info', 'Processing dine-in order: ' . json_encode($order));
                
                // Get table information for dine-in orders
                $tableModel = new \App\Models\TableModel();
                $table = $tableModel->find($order['table_id']);
                $order['table_label'] = $table ? $table['label'] : "Table {$order['table_id']}";
                $order['context_label'] = $order['table_label'];
                log_message('info', 'Table order enhanced: table_label = ' . $order['table_label']);
                
                // Get order items with menu item details
                $orderItemModel = new \App\Models\OrderItemModel();
                $items = $orderItemModel->getOrderItemsWithMenuDetails($order['id']);
                $order['items'] = $items;
                $order['item_count'] = count($items);
            }

            log_message('info', 'Returning ' . count($orders) . ' enhanced dine-in orders');
            return $this->respond($orders);

        } catch (\Exception $e) {
            log_message('error', 'Error fetching orders: ' . $e->getMessage());
            return $this->failServerError('Internal server error: ' . $e->getMessage());
        }
    }

    // Get all orders for POS (both dine-in and takeaway) - used by POS for filtering
    public function getAllOrders()
    {
        try {
            // Get all orders (both dine-in and takeaway) for POS filtering
            $orders = $this->orderModel->orderBy('created_at', 'DESC')->findAll();
            log_message('info', 'Fetched ' . count($orders) . ' orders from database (both dine-in and takeaway) for POS');
            
            // Enhance each order with context info and items
            foreach ($orders as &$order) {
                if ($order['order_type'] === 'dine_in') {
                    log_message('info', 'Processing dine-in order for POS: ' . json_encode($order));
                    
                    // Get table information for dine-in orders
                    $tableModel = new \App\Models\TableModel();
                    $table = $tableModel->find($order['table_id']);
                    $order['table_label'] = $table ? $table['label'] : "Table {$order['table_id']}";
                    $order['context_label'] = $order['table_label'];
                } else if ($order['order_type'] === 'takeaway') {
                    log_message('info', 'Processing takeaway order for POS: ' . json_encode($order));
                    
                    // Get takeaway information for takeaway orders
                    $takeawayModel = new \App\Models\TakeawayModel();
                    $takeaway = $takeawayModel->find($order['takeaway_id']);
                    $order['takeaway_number'] = $takeaway ? $takeaway['takeaway_number'] : "T{$order['takeaway_id']}";
                    $order['context_label'] = $order['takeaway_number'];
                }
                
                // Get order items with menu item details
                $orderItemModel = new \App\Models\OrderItemModel();
                $items = $orderItemModel->getOrderItemsWithMenuDetails($order['id']);
                $order['items'] = $items;
                $order['item_count'] = count($items);
            }

            log_message('info', 'Returning ' . count($orders) . ' enhanced orders (both dine-in and takeaway) for POS');
            return $this->respond($orders);

        } catch (\Exception $e) {
            log_message('error', 'Error fetching all orders for POS: ' . $e->getMessage());
            return $this->failServerError('Internal server error: ' . $e->getMessage());
        }
    }

    // Update order status
    public function updateStatus($order_id)
    {
        $data = $this->request->getJSON(true);

        if (!isset($data['status'])) {
            return $this->fail('Status is required');
        }

        try {
            // Update order status
            $updateResult = $this->orderModel->update($order_id, ['status' => $data['status']]);
            
            if (!$updateResult) {
                return $this->failServerError('Failed to update order status');
            }
            
            // Get updated order
            $order = $this->orderModel->find($order_id);

            return $this->respond([
                'success' => true,
                'message' => 'Order status updated successfully',
                'order' => $order
            ]);
 
        } catch (\Exception $e) {
            log_message('error', 'Error updating order status: ' . $e->getMessage());
            return $this->failServerError('Internal server error: ' . $e->getMessage());
        }
    }

    // Update payment method
    public function updatePaymentMethod($order_id)
    {
        $data = $this->request->getJSON(true);

        if (!isset($data['payment_method'])) {
            return $this->fail('Payment method is required');
        }

        try {
            $this->orderModel->update($order_id, ['payment_method' => $data['payment_method']]);
            $order = $this->orderModel->find($order_id);

            return $this->respond([
                'success' => true,
                'message' => 'Payment method updated successfully',
                'order' => $order
            ]);
 
        } catch (\Exception $e) {
            log_message('error', 'Error updating payment method: ' . $e->getMessage());
            return $this->failServerError('Internal server error: ' . $e->getMessage());
        }
    }

    // Complete order checkout - marks order as completed and frees up table
    public function checkout($order_id)
    {
        $data = $this->request->getJSON(true);

        try {
            // Get the order first to validate it exists
            $order = $this->orderModel->find($order_id);
            if (!$order) {
                return $this->failNotFound('Order not found');
            }

            // Prepare update data
            $updateData = [
                'status' => OrderModel::STATUS_COMPLETED
            ];

            // Update payment method if provided
            if (isset($data['payment_method'])) {
                $updateData['payment_method'] = $data['payment_method'];
            }

            // Update the order status to completed
            $updateResult = $this->orderModel->update($order_id, $updateData);
            
            if (!$updateResult) {
                return $this->failServerError('Failed to complete checkout');
            }

            // Free up the table only for table orders, not takeaway orders
            if ($order['order_type'] === 'dine_in' && !empty($order['table_id'])) {
                $tableModel = new \App\Models\TableModel();
                $tableUpdateResult = $tableModel->update($order['table_id'], ['status' => 'available']);
                
                if (!$tableUpdateResult) {
                    log_message('error', 'Failed to update table status to available for table ID: ' . $order['table_id']);
                    // Don't fail the entire checkout for this, just log it
                }
                $message = 'Checkout completed successfully. Table is now available.';
            } else {
                // Takeaway order - no table to free up
                $message = 'Takeaway order completed successfully.';
            }
            
            // Get updated order
            $updatedOrder = $this->orderModel->find($order_id);

            return $this->respond([
                'success' => true,
                'message' => $message,
                'order' => $updatedOrder
            ]);
 
        } catch (\Exception $e) {
            log_message('error', 'Error completing checkout: ' . $e->getMessage());
            return $this->failServerError('Internal server error: ' . $e->getMessage());
        }
    }

    // Day-end report
    public function dayReport()
    {
        $date = $this->request->getGet('date');
        
        if (!$date) {
            $date = date('Y-m-d'); // Default to today
        }

        try {
            // Ensure the date is in the correct format
            $reportDate = date('Y-m-d', strtotime($date));
            $nextDate = date('Y-m-d', strtotime($reportDate . ' +1 day'));

            $db = \Config\Database::connect();
            
            // Get orders for the specific date
            $query = $db->query("
                SELECT 
                    SUM(CASE WHEN status != ? THEN 1 ELSE 0 END) as total_orders,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_orders,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelled_orders,
                    SUM(CASE WHEN status NOT IN (?, ?) THEN 1 ELSE 0 END) as pending_orders,
                    SUM(CASE WHEN status = ? THEN total_amount ELSE 0 END) as total_sales
                FROM orders 
                WHERE DATE(created_at) = ?
            ", [
                'cancelled',  // total_orders (exclude cancelled)
                'completed',  // completed_orders
                'cancelled',  // cancelled_orders  
                'completed',  // for pending calculation
                'cancelled',  // for pending calculation
                'completed',  // total_sales
                $reportDate
            ]);

            $result = $query->getRow();

            // Get payment method breakdown for completed orders
            $paymentQuery = $db->query("
                SELECT 
                    COALESCE(payment_method, 'cash') as payment_method,
                    SUM(total_amount) as amount
                FROM orders 
                WHERE DATE(created_at) = ? AND status = ?
                GROUP BY payment_method
            ", [
                $reportDate,
                'completed'  // Use 'completed' for finished orders
            ]);

            $paymentBreakdown = [];
            $paymentResults = $paymentQuery->getResult();
            
            // Initialize all payment methods with 0
            $paymentBreakdown['cash'] = 0;
            $paymentBreakdown['fonepay'] = 0;
            $paymentBreakdown['card'] = 0;
            $paymentBreakdown['others'] = 0;
            
            // Fill with actual data
            foreach ($paymentResults as $payment) {
                $paymentBreakdown[$payment->payment_method] = (float)$payment->amount;
            }

            return $this->respond([
                'success' => true,
                'date' => $reportDate,
                'total_orders' => (int)$result->total_orders,
                'completed_orders' => (int)$result->completed_orders,
                'cancelled_orders' => (int)$result->cancelled_orders,
                'pending_orders' => (int)$result->pending_orders,
                'total_sales' => (float)$result->total_sales ?? 0,
                'payment_breakdown' => $paymentBreakdown
            ]);

        } catch (\Exception $e) {
            log_message('error', 'Error generating day-end report: ' . $e->getMessage());
            return $this->failServerError('Internal server error: ' . $e->getMessage());
        }
    }
}
