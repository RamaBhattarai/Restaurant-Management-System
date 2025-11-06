<?php

namespace App\Controllers\Admin;

use CodeIgniter\RESTful\ResourceController;
use App\Models\OrderModel;
use App\Models\OrderItemModel;
use App\Models\MenuItemModel;
use App\Models\CategoryModel;

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
            return $this->fail('Order ID is required')
                ->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
                ->setHeader('Access-Control-Allow-Credentials', 'true');
        }

        $data = $this->request->getJSON(true);
        log_message('info', 'Add items request data: ' . json_encode($data));

        if (!isset($data['items']) || !is_array($data['items'])) {
            return $this->fail('Items array is required')
                ->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
                ->setHeader('Access-Control-Allow-Credentials', 'true');
        }

        try {
            $addedItems = [];
            foreach ($data['items'] as $item) {
                log_message('info', 'Processing item: ' . json_encode($item));
                
                // Validate required fields
                if (!isset($item['menu_item_id']) || !isset($item['unit_price']) || !isset($item['total_price'])) {
                    return $this->fail('Missing required fields: menu_item_id, unit_price, total_price')
                        ->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
                        ->setHeader('Access-Control-Allow-Credentials', 'true');
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
                    return $this->failServerError('Failed to insert order item')
                        ->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
                        ->setHeader('Access-Control-Allow-Credentials', 'true');
                }
                
                $addedItems[] = $this->orderItemModel->find($orderItemId);
            }

            return $this->respondCreated($addedItems)
                ->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
                ->setHeader('Access-Control-Allow-Credentials', 'true');

        } catch (\Exception $e) {
            log_message('error', 'Error adding items to order: ' . $e->getMessage());
            return $this->failServerError('Internal server error: ' . $e->getMessage())
                ->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
                ->setHeader('Access-Control-Allow-Credentials', 'true');
        }
    }

    // Update order item quantity
    public function updateOrderItem($order_id, $item_id)
    {
        $data = $this->request->getJSON(true);
        log_message('info', 'Update order item request data: ' . json_encode($data));

        try {
            // Get the existing order item first
            $orderItem = $this->orderItemModel->where(['order_id' => $order_id, 'id' => $item_id])->first();

            if (!$orderItem) {
                return $this->failNotFound('Order item not found')
                    ->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
                    ->setHeader('Access-Control-Allow-Credentials', 'true');
            }

            // Check if this is a complete item replacement or just quantity/notes update
            if (isset($data['menu_item_id'])) {
                // Complete item replacement - validate all required fields
                if (!isset($data['quantity']) || !isset($data['unit_price']) || !isset($data['total_price'])) {
                    return $this->fail('For item replacement, menu_item_id, quantity, unit_price, and total_price are required')
                        ->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
                        ->setHeader('Access-Control-Allow-Credentials', 'true');
                }

                $updateData = [
                    'menu_item_id' => $data['menu_item_id'],
                    'quantity' => $data['quantity'],
                    'unit_price' => $data['unit_price'],
                    'total_price' => $data['total_price'],
                    'notes' => $data['notes'] ?? null
                ];

                log_message('info', 'Performing complete item replacement: ' . json_encode($updateData));
            } else {
                // Quantity/notes update only
                if (!isset($data['quantity']) || !is_numeric($data['quantity'])) {
                    return $this->fail('Valid quantity is required')
                        ->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
                        ->setHeader('Access-Control-Allow-Credentials', 'true');
                }

                $quantity = (int) $data['quantity'];

                if ($quantity <= 0) {
                    return $this->fail('Quantity must be greater than 0. Use delete endpoint to remove items.')
                        ->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
                        ->setHeader('Access-Control-Allow-Credentials', 'true');
                }

                // Calculate new total based on existing unit price
                $newTotal = $quantity * $orderItem['unit_price'];

                $updateData = [
                    'quantity' => $quantity,
                    'total_price' => $newTotal
                ];

                if (isset($data['notes'])) {
                    $updateData['notes'] = $data['notes'];
                }

                log_message('info', 'Performing quantity/notes update: ' . json_encode($updateData));
            }

            $success = $this->orderItemModel->update($item_id, $updateData);

            if (!$success) {
                return $this->failServerError('Failed to update order item')
                    ->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
                    ->setHeader('Access-Control-Allow-Credentials', 'true');
            }

            // Return updated item
            $updatedItem = $this->orderItemModel->find($item_id);
            log_message('info', 'Order item updated successfully: ' . json_encode($updatedItem));
            
            return $this->respond($updatedItem)
                ->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
                ->setHeader('Access-Control-Allow-Credentials', 'true');

        } catch (\Exception $e) {
            log_message('error', 'Error updating order item: ' . $e->getMessage());
            return $this->failServerError('Internal server error: ' . $e->getMessage())
                ->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
                ->setHeader('Access-Control-Allow-Credentials', 'true');
        }
    }

    // Remove/Cancel order item
    public function removeOrderItem($order_id, $item_id)
    {
        try {
            // Verify the item belongs to this order
            $orderItem = $this->orderItemModel->where(['order_id' => $order_id, 'id' => $item_id])->first();

            if (!$orderItem) {
                return $this->failNotFound('Order item not found')
                    ->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
                    ->setHeader('Access-Control-Allow-Credentials', 'true');
            }

            // Delete the order item
            $success = $this->orderItemModel->delete($item_id);

            if (!$success) {
                return $this->failServerError('Failed to remove order item')
                    ->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
                    ->setHeader('Access-Control-Allow-Credentials', 'true');
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
                    ])
                    ->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
                    ->setHeader('Access-Control-Allow-Credentials', 'true');
                }
            }

            return $this->respondDeleted([
                'message' => 'Order item removed successfully',
                'order_deleted' => false,
                'remaining_items' => $remainingItems
            ])
            ->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->setHeader('Access-Control-Allow-Credentials', 'true');

        } catch (\Exception $e) {
            log_message('error', 'Error removing order item: ' . $e->getMessage());
            return $this->failServerError('Internal server error: ' . $e->getMessage())
                ->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
                ->setHeader('Access-Control-Allow-Credentials', 'true');
        }
    }    // Handle OPTIONS requests for CORS preflight
    public function options(...$params)
    {
        // This method handles all OPTIONS requests for CORS preflight
        $response = $this->response
            ->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin')
            ->setHeader('Access-Control-Allow-Credentials', 'true')
            ->setHeader('Access-Control-Max-Age', '7200')
            ->setStatusCode(200);

        return $response;
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

    // Get orders for sales report with date and order type filtering
    public function getSalesReportOrders()
    {
        try {
            $startDate = $this->request->getGet('start_date');
            $endDate = $this->request->getGet('end_date');
            $orderType = $this->request->getGet('order_type');

            // Build query
            $query = $this->orderModel->orderBy('created_at', 'DESC');

            // Apply date filters if provided
            if ($startDate) {
                $query->where('DATE(created_at) >=', $startDate);
            }
            if ($endDate) {
                $query->where('DATE(created_at) <=', $endDate);
            }

            // Apply order type filter if provided
            if ($orderType && $orderType !== '') {
                $query->where('order_type', $orderType);
            }

            $orders = $query->findAll();

            // Enhance orders with item details for summary calculations
            foreach ($orders as &$order) {
                $orderItemModel = new \App\Models\OrderItemModel();
                $items = $orderItemModel->getOrderItemsWithMenuDetails($order['id']);
                $order['items'] = $items;
                $order['item_count'] = count($items);
            }

            // Get total menu items and categories count for summary
            $menuItemModel = new \App\Models\MenuItemModel();
            $totalMenuItems = $menuItemModel->countAll();
            
            $categoryModel = new \App\Models\CategoryModel();
            $totalCategories = $categoryModel->countAll();

            log_message('info', 'Fetched ' . count($orders) . ' orders for sales report with filters: start_date=' . $startDate . ', end_date=' . $endDate . ', order_type=' . $orderType);

            return $this->respond([
                'orders' => $orders,
                'summary' => [
                    'total_menu_items' => $totalMenuItems,
                    'total_categories' => $totalCategories
                ]
            ]);

        } catch (\Exception $e) {
            log_message('error', 'Error fetching sales report orders: ' . $e->getMessage());
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

            // Update discount data if provided
            if (isset($data['discount'])) {
                $updateData['discount_amount'] = $data['discount'];
            }
            if (isset($data['discount_type'])) {
                $updateData['discount_type'] = $data['discount_type'];
            }
            if (isset($data['discount_amount'])) {
                $updateData['discount_amount'] = $data['discount_amount'];
            }

            // Update VAT data if provided
            if (isset($data['vat'])) {
                $updateData['vat_amount'] = $data['vat'];
            }
            if (isset($data['vat_percentage'])) {
                $updateData['vat_percentage'] = $data['vat_percentage'];
            }

            // Save customer invoice data if provided
            if (isset($data['customer_invoice_data'])) {
                $updateData['customer_invoice_data'] = $data['customer_invoice_data'];
                $updateData['invoice_generated_at'] = date('Y-m-d H:i:s');
            }

            // Handle partial payment breakdown for "others" payment method
            if (isset($data['payment_method']) && $data['payment_method'] === 'others') {
                // Save individual payment amounts
                if (isset($data['cash_amount'])) {
                    $updateData['cash_amount'] = $data['cash_amount'];
                }
                if (isset($data['card_amount'])) {
                    $updateData['card_amount'] = $data['card_amount'];
                }
                if (isset($data['online_amount'])) {
                    $updateData['online_amount'] = $data['online_amount'];
                }
                if (isset($data['payment_breakdown'])) {
                    $updateData['payment_breakdown'] = $data['payment_breakdown'];
                }
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

    // Print saved customer invoice
    public function printCustomerInvoice($order_id)
    {
        try {
            // Get the order with saved invoice data
            $order = $this->orderModel->getOrderWithItems($order_id);
            
            if (!$order) {
                return $this->failNotFound('Order not found');
            }

            // Check if we have saved invoice data
            if (!empty($order['customer_invoice_data'])) {
                // Return saved invoice
                return $this->respond([
                    'success' => true,
                    'invoice_html' => $order['customer_invoice_data'],
                    'generated_at' => $order['invoice_generated_at'],
                    'order_id' => $order_id
                ]);
            }

            // For older orders without saved invoice data, generate it on-the-fly
            if ($order['status'] !== 'completed') {
                return $this->fail('Invoice not available for pending orders. Complete checkout first.');
            }

            // Get company settings
            $companySettingsModel = new \App\Models\CompanySettingsModel();
            $companySettings = $companySettingsModel->first();
            $companyName = $companySettings['company_name'] ?? 'Deskgoo Consulting';

            // Generate invoice HTML
            $invoiceHtml = $this->generateCustomerInvoiceHTML($order, $companyName);

            return $this->respond([
                'success' => true,
                'invoice_html' => $invoiceHtml,
                'generated_at' => date('Y-m-d H:i:s'),
                'order_id' => $order_id,
                'note' => 'Generated on-the-fly for older order'
            ]);

        } catch (\Exception $e) {
            log_message('error', 'Error retrieving customer invoice: ' . $e->getMessage());
            return $this->failServerError('Internal server error: ' . $e->getMessage());
        }
    }

    // Generate customer invoice HTML for older orders
    private function generateCustomerInvoiceHTML($order, $companyName)
    {
        $orderDate = date('d M Y', strtotime($order['created_at']));
        
        // Determine context label
        $contextLabel = '';
        if ($order['order_type'] === 'takeaway') {
            $contextLabel = $order['takeaway_number'] ?? "Takeaway {$order['takeaway_id']}";
        } else {
            $contextLabel = $order['table_label'] ?? "Table {$order['table_id']}";
        }

        // Determine payment method display
        $paymentDisplay = ucfirst($order['payment_method'] ?? 'cash');
        if ($order['payment_method'] === 'others' && !empty($order['payment_breakdown'])) {
            $breakdown = json_decode($order['payment_breakdown'], true);
            if ($breakdown && isset($breakdown['type'])) {
                $paymentDisplay = ucfirst(str_replace('-', ' + ', $breakdown['type']));
                
                // Add amounts breakdown
                $amountDetails = [];
                if (!empty($order['cash_amount'])) {
                    $amountDetails[] = "Cash: " . number_format($order['cash_amount'], 2);
                }
                if (!empty($order['card_amount'])) {
                    $amountDetails[] = "Card: " . number_format($order['card_amount'], 2);
                }
                if (!empty($order['online_amount'])) {
                    $amountDetails[] = "Online: " . number_format($order['online_amount'], 2);
                }
                
                if (!empty($amountDetails)) {
                    $paymentDisplay .= " (" . implode(', ', $amountDetails) . ")";
                }
            }
        }

        // Calculate totals
        $subtotal = floatval($order['total_amount']);
        $discount = floatval($order['discount_amount'] ?? 0);
        $vat = floatval($order['vat_amount'] ?? 0);
        $total = $subtotal - $discount + $vat;

        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Customer Invoice - Order #{$order['id']}</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    font-size: 12px; 
                    margin: 0; 
                    padding: 20px; 
                    max-width: 300px;
                    margin: 0 auto;
                    line-height: 1.4;
                }
                .header { 
                    text-align: center; 
                    border-bottom: 2px solid #000; 
                    padding-bottom: 10px; 
                    margin-bottom: 15px; 
                }
                .company-name { 
                    font-size: 16px; 
                    font-weight: bold; 
                    margin-bottom: 5px; 
                }
                .invoice-title {
                    font-size: 14px;
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                .order-info { 
                    margin-bottom: 15px; 
                    font-size: 11px;
                }
                .info-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 3px;
                }
                .items-table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin-bottom: 15px;
                    font-size: 11px;
                }
                .items-table th, .items-table td { 
                    padding: 4px 2px; 
                    text-align: left; 
                    border-bottom: 1px solid #ddd;
                }
                .items-table th { 
                    background-color: #f0f0f0; 
                    font-weight: bold;
                    font-size: 10px;
                }
                .qty-col { width: 30px; text-align: center; }
                .price-col { width: 60px; text-align: right; }
                .total-section { 
                    border-top: 2px solid #000; 
                    padding-top: 10px; 
                    margin-top: 15px;
                    font-size: 11px;
                }
                .total-line { 
                    display: flex; 
                    justify-content: space-between; 
                    margin-bottom: 3px;
                }
                .grand-total { 
                    font-weight: bold; 
                    font-size: 13px;
                    border-top: 1px solid #000;
                    padding-top: 5px;
                    margin-top: 5px;
                }
                .footer { 
                    text-align: center; 
                    margin-top: 20px; 
                    border-top: 1px solid #000; 
                    padding-top: 10px; 
                    font-size: 11px;
                }
                @media print {
                    body { margin: 0; padding: 10px; }
                }
            </style>
        </head>
        <body>
            <div class='header'>
                <div class='company-name'>{$companyName}</div>
                <div class='invoice-title'>CUSTOMER INVOICE</div>
            </div>

            <div class='order-info'>
                <div class='info-row'>
                    <span><strong>Invoice No:</strong></span>
                    <span>INV-{$order['id']}</span>
                </div>
                <div class='info-row'>
                    <span><strong>" . ($order['order_type'] === 'takeaway' ? 'Takeaway:' : 'Table:') . "</strong></span>
                    <span>{$contextLabel}</span>
                </div>
                <div class='info-row'>
                    <span><strong>Date:</strong></span>
                    <span>{$orderDate}</span>
                </div>
                <div class='info-row'>
                    <span><strong>Payment:</strong></span>
                    <span>{$paymentDisplay}</span>
                </div>
            </div>

            <table class='items-table'>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class='qty-col'>Qty</th>
                        <th class='price-col'>Amount</th>
                    </tr>
                </thead>
                <tbody>";

        // Add items
        if (!empty($order['items'])) {
            foreach ($order['items'] as $item) {
                $itemName = $item['menu_item_name'] ?? $item['name'] ?? 'Unknown Item';
                $quantity = $item['quantity'] ?? 1;
                $itemTotal = floatval($item['total_price'] ?? $item['total'] ?? 0);
                
                $html .= "
                    <tr>
                        <td>{$itemName}</td>
                        <td class='qty-col'>{$quantity}</td>
                        <td class='price-col'>" . number_format($itemTotal, 2) . "</td>
                    </tr>";
            }
        } else {
            $html .= "
                    <tr>
                        <td colspan='3' style='text-align: center;'>No items found</td>
                    </tr>";
        }

        $html .= "
                </tbody>
            </table>

            <div class='total-section'>
                <div class='total-line'>
                    <span>Subtotal:</span>
                    <span>NRS " . number_format($subtotal, 2) . "</span>
                </div>";

        if ($discount > 0) {
            $html .= "
                <div class='total-line'>
                    <span>Discount:</span>
                    <span>- NRS " . number_format($discount, 2) . "</span>
                </div>";
        }

        if ($vat > 0) {
            $html .= "
                <div class='total-line'>
                    <span>VAT (13%):</span>
                    <span>NRS " . number_format($vat, 2) . "</span>
                </div>";
        }

        $html .= "
                <div class='total-line grand-total'>
                    <span>Total:</span>
                    <span>NRS " . number_format($total, 2) . "</span>
                </div>
            </div>

            <div class='footer'>
                <p><strong>Thank you! Visit Again Soon!</strong></p>
                <p>* Customer Copy *</p>
            </div>
        </body>
        </html>";

        return $html;
    }

    // Day-end report
    public function dayReport()
    {
        $date = $this->request->getGet('date');
        $orderType = $this->request->getGet('order_type');
        
        if (!$date) {
            $date = date('Y-m-d'); // Default to today
        }

        try {
            // Ensure the date is in the correct format
            $reportDate = date('Y-m-d', strtotime($date));
            $nextDate = date('Y-m-d', strtotime($reportDate . ' +1 day'));

            $db = \Config\Database::connect();
            
            // Build WHERE clause for order type filtering
            $whereClause = "DATE(created_at) = ?";
            $params = [$reportDate];
            
            if ($orderType && $orderType !== 'all') {
                $whereClause .= " AND order_type = ?";
                $params[] = $orderType;
            }
            
            // Get orders for the specific date and order type
            $query = $db->query("
                SELECT 
                    SUM(CASE WHEN status != ? THEN 1 ELSE 0 END) as total_orders,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_orders,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelled_orders,
                    SUM(CASE WHEN status NOT IN (?, ?) THEN 1 ELSE 0 END) as pending_orders,
                    SUM(CASE WHEN status = ? THEN (total_amount - COALESCE(discount_amount, 0) + COALESCE(vat_amount, 0)) ELSE 0 END) as total_sales
                FROM orders 
                WHERE {$whereClause}
            ", array_merge([
                'cancelled',  // total_orders (exclude cancelled)
                'completed',  // completed_orders
                'cancelled',  // cancelled_orders  
                'completed',  // for pending calculation
                'cancelled',  // for pending calculation
                'completed'   // total_sales
            ], $params));

            $result = $query->getRow();

            // Get payment method breakdown for completed orders
            $paymentWhereClause = $whereClause . " AND status = ?";
            $paymentParams = array_merge($params, ['completed']);
            
            // Get basic payment method breakdown
            $paymentQuery = $db->query("
                SELECT 
                    COALESCE(payment_method, 'cash') as payment_method,
                    SUM(total_amount - COALESCE(discount_amount, 0) + COALESCE(vat_amount, 0)) as amount,
                    SUM(COALESCE(cash_amount, 0)) as cash_amount,
                    SUM(COALESCE(card_amount, 0)) as card_amount,
                    SUM(COALESCE(online_amount, 0)) as online_amount
                FROM orders 
                WHERE {$paymentWhereClause}
                GROUP BY payment_method
            ", $paymentParams);

            $paymentBreakdown = [];
            $paymentResults = $paymentQuery->getResult();
            
            // Initialize all payment methods with 0
            $paymentBreakdown['cash'] = 0;
            $paymentBreakdown['card'] = 0;
            $paymentBreakdown['online'] = 0;
            
            // Process each payment method result
            foreach ($paymentResults as $payment) {
                if ($payment->payment_method === 'others') {
                    // Distribute partial payments to their respective categories
                    $paymentBreakdown['cash'] += (float)$payment->cash_amount;
                    $paymentBreakdown['card'] += (float)$payment->card_amount;
                    $paymentBreakdown['online'] += (float)$payment->online_amount;
                } elseif (in_array($payment->payment_method, ['cash', 'card', 'online'])) {
                    // Add direct payments to their categories
                    $paymentBreakdown[$payment->payment_method] += (float)$payment->amount;
                } elseif ($payment->payment_method === 'fonepay') {
                    // Handle legacy fonepay as online
                    $paymentBreakdown['online'] += (float)$payment->amount;
                }
            }

            // Remove the separate partial payment query since we're now distributing properly
            // No need for partial_payment_breakdown anymore

            // Log the payment queries for debugging
            log_message('info', 'Payment query executed: ' . $db->getLastQuery());
            log_message('info', 'Payment results: ' . json_encode($paymentResults));
            log_message('info', 'Final payment breakdown: ' . json_encode($paymentBreakdown));

            return $this->respond([
                'success' => true,
                'date' => $reportDate,
                'order_type' => $orderType ?? 'all',
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
