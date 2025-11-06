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
                ->where('(orders.status IS NULL OR orders.status = "" OR orders.status IN ("pending", "preparing", "ready", "draft", "placed"))')
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
                ->where('(orders.status IS NULL OR orders.status = "" OR orders.status IN ("pending", "preparing", "ready", "draft", "placed"))')
                ->first();

            if (!$order) {
                return $this->response->setStatusCode(404)->setJSON([
                    'success' => false,
                    'error' => 'No active order found for this table'
                ]);
            }

            // Get order items
            $orderItems = $this->orderItemModel
                ->select('order_items.*, menu_items.name as item_name, menu_items.price as item_price, menu_items.print_kot, menu_items.print_bot')
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
            $transferType = $data['transfer_type'] ?? 'full'; // 'full' or 'partial'
            $selectedItems = $data['selected_items'] ?? null; // For partial transfers

            // Verify the order exists and is active
            $order = $this->orderModel->find($orderId);
            if (!$order) {
                return $this->response->setStatusCode(404)->setJSON([
                    'success' => false,
                    'error' => 'Order not found'
                ]);
            }

            if (!in_array($order['status'], ['pending', 'preparing', 'ready', 'draft', 'placed', '', null])) {
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

            // Allow transfers to any table, including those with active orders
            // Check if the new table already has an active order (for logging purposes)
            $existingOrder = $this->orderModel
                ->where('table_id', $newTableId)
                ->where('(status IS NULL OR status = "" OR status IN ("pending", "preparing", "ready", "draft", "placed"))')
                ->first();

            // Start database transaction
            $db = \Config\Database::connect();
            $db->transStart();

            if ($transferType === 'partial' && $selectedItems) {
                // Handle partial transfer - split order items
                $result = $this->handlePartialTransfer($orderId, $newTableId, $selectedItems, $notes, $existingOrder);
                if (!$result['success']) {
                    $db->transRollback();
                    return $this->response->setStatusCode(400)->setJSON([
                        'success' => false,
                        'error' => $result['error']
                    ]);
                }

                $updatedOrder = $result['source_order'];
                $newOrder = $result['target_order'];
            } else {
                // Handle full transfer (existing logic)
                // Update the order with new table ID and add transfer notes
                $updateData = [
                    'table_id' => $newTableId,
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                $transferNotes = $notes;
                if ($existingOrder) {
                    $transferNotes .= " (Note: Destination table already had an active order)";
                }

                if ($transferNotes) {
                    $currentNotes = $order['notes'] ?? '';
                    $transferNote = "\n[TRANSFERRED] " . date('Y-m-d H:i:s') . ": " . $transferNotes;
                    $updateData['notes'] = $currentNotes . $transferNote;
                }

                $this->orderModel->update($orderId, $updateData);

                // Update old table status to available if needed
                $oldTableId = $order['table_id'];
                $this->tableModel->update($oldTableId, ['status' => 'available']);

                // Update new table status to occupied
                $this->tableModel->update($newTableId, ['status' => 'occupied']);

                // Get updated order details
                $updatedOrder = $this->orderModel
                    ->select('orders.*, dining_tables.label as table_label')
                    ->join('dining_tables', 'dining_tables.id = orders.table_id')
                    ->where('orders.id', $orderId)
                    ->first();
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->response->setStatusCode(500)->setJSON([
                    'success' => false,
                    'error' => 'Failed to transfer order'
                ]);
            }

            $message = $transferType === 'partial' ? 'Order items transferred successfully' : 'Order transferred successfully';

            // Trigger kitchen ticket reprint for transferred order
            $this->reprintKitchenTicket($updatedOrder['id'], $newTableId, 'table_transfer');

            return $this->response->setJSON([
                'success' => true,
                'data' => $updatedOrder,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            log_message('error', 'TableTransferController::transferOrder - ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error' => 'Failed to transfer order'
            ]);
        }
    }

    // Get all tables for transfer destination selection
    public function getAvailableTables()
    {
        try {
            // Get all tables with area information
            $allTables = $this->tableModel
                ->select('dining_tables.*, areas.name as area_name')
                ->join('areas', 'areas.id = dining_tables.area_id')
                ->findAll();

            return $this->response->setJSON([
                'success' => true,
                'data' => $allTables
            ]);
        } catch (\Exception $e) {
            log_message('error', 'TableTransferController::getAvailableTables - ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error' => 'Failed to retrieve tables'
            ]);
        }
    }

    // Handle partial transfer - split order items between source and destination
    private function handlePartialTransfer($sourceOrderId, $targetTableId, $selectedItems, $notes, $existingOrder = null)
    {
        try {
            // Get source order details
            $sourceOrder = $this->orderModel->find($sourceOrderId);
            if (!$sourceOrder) {
                return ['success' => false, 'error' => 'Source order not found'];
            }

            // Get all items from source order
            $allItems = $this->orderItemModel->where('order_id', $sourceOrderId)->findAll();

            // Validate selected items
            $itemsToTransfer = [];
            $remainingItems = [];

            foreach ($allItems as $item) {
                $itemId = $item['id'];
                $selectedItem = null;

                // Find if this item is selected for transfer
                foreach ($selectedItems as $selected) {
                    if ($selected['item_id'] == $itemId) {
                        $selectedItem = $selected;
                        break;
                    }
                }

                if ($selectedItem) {
                    $transferQuantity = (int)$selectedItem['quantity'];
                    $originalQuantity = (int)$item['quantity'];

                    if ($transferQuantity <= 0 || $transferQuantity > $originalQuantity) {
                        return ['success' => false, 'error' => 'Invalid transfer quantity for item: ' . $item['menu_item_id']];
                    }

                    // Item to transfer
                    $transferItem = $item;
                    $transferItem['quantity'] = $transferQuantity;
                    $transferItem['total_price'] = $transferQuantity * $item['unit_price'];
                    $itemsToTransfer[] = $transferItem;

                    // Remaining item in source order
                    if ($originalQuantity > $transferQuantity) {
                        $remainingItem = $item;
                        $remainingItem['quantity'] = $originalQuantity - $transferQuantity;
                        $remainingItem['total_price'] = $remainingItem['quantity'] * $item['unit_price'];
                        $remainingItems[] = $remainingItem;
                    }
                } else {
                    // Item stays in source order
                    $remainingItems[] = $item;
                }
            }

            if (empty($itemsToTransfer)) {
                return ['success' => false, 'error' => 'No items selected for transfer'];
            }

            // Create or update target order
            $targetOrderId = null;
            if ($existingOrder) {
                // Add items to existing order
                $targetOrderId = $existingOrder['id'];
                $targetOrder = $existingOrder;
            } else {
                // Create new order for target table
                $targetOrderData = [
                    'table_id' => $targetTableId,
                    'order_type' => $sourceOrder['order_type'],
                    'total_amount' => 0, // Will be calculated
                    'discount_amount' => 0,
                    'discount_type' => null,
                    'vat_amount' => 0,
                    'vat_percentage' => $sourceOrder['vat_percentage'] ?? 13,
                    'status' => $sourceOrder['status'],
                    'payment_method' => null,
                    'notes' => 'Created from partial transfer',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                $targetOrderId = $this->orderModel->insert($targetOrderData);
                $targetOrder = $this->orderModel->find($targetOrderId);

                // Update table status to occupied
                $this->tableModel->update($targetTableId, ['status' => 'occupied']);
            }

            // Add transferred items to target order
            foreach ($itemsToTransfer as $item) {
                $targetItemData = [
                    'order_id' => $targetOrderId,
                    'menu_item_id' => $item['menu_item_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'notes' => $item['notes'] ?? null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                $this->orderItemModel->insert($targetItemData);
            }

            // Update or delete items in source order
            $this->orderItemModel->where('order_id', $sourceOrderId)->delete(); // Delete all existing items

            // Re-insert remaining items
            foreach ($remainingItems as $item) {
                $sourceItemData = [
                    'order_id' => $sourceOrderId,
                    'menu_item_id' => $item['menu_item_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'notes' => $item['notes'] ?? null,
                    'created_at' => $item['created_at'],
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                $this->orderItemModel->insert($sourceItemData);
            }

            // Update order totals
            $this->updateOrderTotals($sourceOrderId);
            $this->updateOrderTotals($targetOrderId);

            // Add transfer notes
            $transferNotes = $notes . " (Partial transfer: " . count($itemsToTransfer) . " items moved)";
            if ($existingOrder) {
                $transferNotes .= " (Added to existing order)";
            }

            // Update source order notes
            $sourceNotes = $sourceOrder['notes'] ?? '';
            $transferNote = "\n[PARTIAL TRANSFER] " . date('Y-m-d H:i:s') . ": " . $transferNotes;
            $this->orderModel->update($sourceOrderId, [
                'notes' => $sourceNotes . $transferNote,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // Update target order notes
            $targetNotes = $targetOrder['notes'] ?? '';
            $targetTransferNote = "\n[RECEIVED ITEMS] " . date('Y-m-d H:i:s') . ": Items transferred from table " . $sourceOrder['table_id'];
            $this->orderModel->update($targetOrderId, [
                'notes' => $targetNotes . $targetTransferNote,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // Trigger kitchen ticket reprints for both source and target orders
            $this->reprintKitchenTicket($sourceOrderId, null, 'partial_transfer_source');
            $this->reprintKitchenTicket($targetOrderId, $targetTableId, 'partial_transfer_target');

            // Get updated source order
            $updatedSourceOrder = $this->orderModel
                ->select('orders.*, dining_tables.label as table_label')
                ->join('dining_tables', 'dining_tables.id = orders.table_id')
                ->where('orders.id', $sourceOrderId)
                ->first();

            // Get updated target order
            $updatedTargetOrder = $this->orderModel
                ->select('orders.*, dining_tables.label as table_label')
                ->join('dining_tables', 'dining_tables.id = orders.table_id')
                ->where('orders.id', $targetOrderId)
                ->first();

            return [
                'success' => true,
                'source_order' => $updatedSourceOrder,
                'target_order' => $updatedTargetOrder
            ];

        } catch (\Exception $e) {
            log_message('error', 'TableTransferController::handlePartialTransfer - ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to process partial transfer: ' . $e->getMessage()];
        }
    }

    // Update order totals after item changes
    private function updateOrderTotals($orderId)
    {
        $orderItems = $this->orderItemModel->where('order_id', $orderId)->findAll();

        $totalAmount = 0;
        foreach ($orderItems as $item) {
            $totalAmount += (float)$item['total_price'];
        }

        $order = $this->orderModel->find($orderId);
        $vatPercentage = $order['vat_percentage'] ?? 13;
        $vatAmount = $totalAmount * ($vatPercentage / 100);

        $this->orderModel->update($orderId, [
            'total_amount' => $totalAmount,
            'vat_amount' => $vatAmount,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    // Merge multiple orders into one
    public function mergeOrders()
    {
        try {
            $data = $this->request->getJSON(true);

            if (!$data || !isset($data['source_order_ids']) || !isset($data['target_table_id'])) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'error' => 'Source order IDs and target table ID are required'
                ]);
            }

            $sourceOrderIds = $data['source_order_ids'];
            $targetTableId = $data['target_table_id'];
            $notes = $data['notes'] ?? '';

            if (!is_array($sourceOrderIds) || count($sourceOrderIds) < 2) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'error' => 'At least 2 source orders are required for merging'
                ]);
            }

            // Verify all source orders exist and are active
            $sourceOrders = [];
            foreach ($sourceOrderIds as $orderId) {
                $order = $this->orderModel->find($orderId);
                if (!$order) {
                    return $this->response->setStatusCode(404)->setJSON([
                        'success' => false,
                        'error' => 'Order not found: ' . $orderId
                    ]);
                }

                if (!in_array($order['status'], ['pending', 'preparing', 'ready', 'draft', 'placed', '', null])) {
                    return $this->response->setStatusCode(400)->setJSON([
                        'success' => false,
                        'error' => 'Cannot merge completed or paid orders'
                    ]);
                }
                $sourceOrders[] = $order;
            }

            // Verify target table exists
            $targetTable = $this->tableModel->find($targetTableId);
            if (!$targetTable) {
                return $this->response->setStatusCode(404)->setJSON([
                    'success' => false,
                    'error' => 'Target table not found'
                ]);
            }

            // Start database transaction
            $db = \Config\Database::connect();
            $db->transStart();

            // Create new merged order
            $firstOrder = $sourceOrders[0];
            $mergedOrderData = [
                'table_id' => $targetTableId,
                'order_type' => $firstOrder['order_type'],
                'total_amount' => 0, // Will be calculated
                'discount_amount' => 0,
                'discount_type' => null,
                'vat_amount' => 0,
                'vat_percentage' => $firstOrder['vat_percentage'] ?? 13,
                'status' => $firstOrder['status'],
                'payment_method' => null,
                'notes' => 'Merged order from tables: ' . implode(', ', array_column($sourceOrders, 'table_id')),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $mergedOrderId = $this->orderModel->insert($mergedOrderData);

            // Collect all items from source orders
            $allItems = [];
            foreach ($sourceOrderIds as $orderId) {
                $orderItems = $this->orderItemModel->where('order_id', $orderId)->findAll();
                foreach ($orderItems as $item) {
                    // Check if we already have this menu item
                    $existingItemKey = null;
                    foreach ($allItems as $key => $existingItem) {
                        if ($existingItem['menu_item_id'] == $item['menu_item_id'] &&
                            $existingItem['unit_price'] == $item['unit_price']) {
                            $existingItemKey = $key;
                            break;
                        }
                    }

                    if ($existingItemKey !== null) {
                        // Merge quantities
                        $allItems[$existingItemKey]['quantity'] += $item['quantity'];
                        $allItems[$existingItemKey]['total_price'] += $item['total_price'];
                    } else {
                        // Add new item
                        $allItems[] = [
                            'menu_item_id' => $item['menu_item_id'],
                            'quantity' => $item['quantity'],
                            'unit_price' => $item['unit_price'],
                            'total_price' => $item['total_price'],
                            'notes' => $item['notes']
                        ];
                    }
                }
            }

            // Add merged items to new order
            foreach ($allItems as $item) {
                $itemData = [
                    'order_id' => $mergedOrderId,
                    'menu_item_id' => $item['menu_item_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'notes' => $item['notes'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                $this->orderItemModel->insert($itemData);
            }

            // Update merged order totals
            $this->updateOrderTotals($mergedOrderId);

            // Add merge notes to merged order
            $mergeNote = "\n[ORDER MERGE] " . date('Y-m-d H:i:s') . ": Merged from " . count($sourceOrders) . " orders";
            if ($notes) {
                $mergeNote .= " - " . $notes;
            }
            $this->orderModel->update($mergedOrderId, [
                'notes' => $mergedOrderData['notes'] . $mergeNote
            ]);

            // Delete source orders and their items
            foreach ($sourceOrderIds as $orderId) {
                $this->orderItemModel->where('order_id', $orderId)->delete();
                $this->orderModel->delete($orderId);

                // Update source table status to available
                $sourceOrder = array_filter($sourceOrders, function($order) use ($orderId) {
                    return $order['id'] == $orderId;
                });
                if (!empty($sourceOrder)) {
                    $sourceOrder = array_values($sourceOrder)[0];
                    $this->tableModel->update($sourceOrder['table_id'], ['status' => 'available']);
                }
            }

            // Update target table status to occupied
            $this->tableModel->update($targetTableId, ['status' => 'occupied']);

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->response->setStatusCode(500)->setJSON([
                    'success' => false,
                    'error' => 'Failed to merge orders'
                ]);
            }

            // Get merged order details
            $mergedOrder = $this->orderModel
                ->select('orders.*, dining_tables.label as table_label')
                ->join('dining_tables', 'dining_tables.id = orders.table_id')
                ->where('orders.id', $mergedOrderId)
                ->first();

            // Trigger kitchen ticket update for merged orders
            $this->updateMergedOrderTickets($mergedOrderId, $sourceOrderIds);

            return $this->response->setJSON([
                'success' => true,
                'data' => $mergedOrder,
                'message' => 'Orders merged successfully'
            ]);

        } catch (\Exception $e) {
            log_message('error', 'TableTransferController::mergeOrders - ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error' => 'Failed to merge orders: ' . $e->getMessage()
            ]);
        }
    }

    // Manual kitchen ticket reprint
    public function reprintTicket($orderId)
    {
        try {
            $data = $this->request->getJSON(true);
            $reason = $data['reason'] ?? 'manual_reprint';

            // Validate order exists
            $order = $this->orderModel->find($orderId);
            if (!$order) {
                return $this->response->setStatusCode(404)->setJSON([
                    'success' => false,
                    'error' => 'Order not found'
                ]);
            }

            // Trigger kitchen ticket reprint
            $success = $this->reprintKitchenTicket($orderId, null, $reason);

            if ($success) {
                return $this->response->setJSON([
                    'success' => true,
                    'message' => 'Kitchen ticket reprinted successfully'
                ]);
            } else {
                return $this->response->setStatusCode(500)->setJSON([
                    'success' => false,
                    'error' => 'Failed to reprint kitchen ticket'
                ]);
            }

        } catch (\Exception $e) {
            log_message('error', 'TableTransferController::reprintTicket - ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error' => 'Failed to reprint ticket: ' . $e->getMessage()
            ]);
        }
    }

    // Handle kitchen ticket reprint for transferred orders
    private function reprintKitchenTicket($orderId, $newTableId = null, $reason = 'transfer')
    {
        try {
            // Get order details with items
            $order = $this->orderModel->getOrderWithItems($orderId);
            if (!$order) {
                log_message('error', 'Order not found for kitchen ticket reprint: ' . $orderId);
                return false;
            }

            // Get table information
            $table = $this->tableModel->find($newTableId ?: $order['table_id']);
            $tableLabel = $table ? $table['label'] : 'Unknown Table';

            // Format items for kitchen ticket (map field names)
            $formattedItems = [];
            if (isset($order['items']) && is_array($order['items'])) {
                foreach ($order['items'] as $item) {
                    $formattedItems[] = [
                        'id' => $item['id'],
                        'menu_item_id' => $item['menu_item_id'],
                        'item_name' => $item['menu_item_name'] ?? 'Unknown Item',
                        'quantity' => $item['quantity'],
                        'item_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                        'notes' => $item['notes'] ?? null
                    ];
                }
            }

            // Prepare kitchen ticket data
            $ticketData = [
                'order_id' => $orderId,
                'table_label' => $tableLabel,
                'order_type' => $order['order_type'],
                'status' => $order['status'],
                'items' => $formattedItems,
                'total_amount' => $order['total_amount'],
                'reprint_reason' => $reason,
                'reprint_timestamp' => date('Y-m-d H:i:s'),
                'notes' => $order['notes']
            ];

            // Log kitchen ticket reprint
            log_message('info', 'Kitchen ticket reprinted for order ' . $orderId . ' - Reason: ' . $reason . ' - Table: ' . $tableLabel);

            // Here you would integrate with your kitchen printer system
            // For now, we'll just log the ticket data
            log_message('info', 'Kitchen ticket data: ' . json_encode($ticketData));

            // You could also store reprint history in a separate table if needed
            // $this->kitchenTicketModel->insert($ticketData);

            return true;

        } catch (\Exception $e) {
            log_message('error', 'Error reprinting kitchen ticket: ' . $e->getMessage());
            return false;
        }
    }

    // Handle kitchen ticket updates for merged orders
    private function updateMergedOrderTickets($mergedOrderId, $originalOrderIds)
    {
        try {
            // Get merged order details
            $mergedOrder = $this->orderModel->getOrderWithItems($mergedOrderId);
            if (!$mergedOrder) {
                log_message('error', 'Merged order not found for ticket update: ' . $mergedOrderId);
                return false;
            }

            // Get table information
            $table = $this->tableModel->find($mergedOrder['table_id']);
            $tableLabel = $table ? $table['label'] : 'Unknown Table';

            // Format items for kitchen ticket (map field names)
            $formattedItems = [];
            if (isset($mergedOrder['items']) && is_array($mergedOrder['items'])) {
                foreach ($mergedOrder['items'] as $item) {
                    $formattedItems[] = [
                        'id' => $item['id'],
                        'menu_item_id' => $item['menu_item_id'],
                        'item_name' => $item['menu_item_name'] ?? 'Unknown Item',
                        'quantity' => $item['quantity'],
                        'item_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                        'notes' => $item['notes'] ?? null
                    ];
                }
            }

            // Prepare merged ticket data
            $ticketData = [
                'order_id' => $mergedOrderId,
                'table_label' => $tableLabel,
                'order_type' => $mergedOrder['order_type'],
                'status' => $mergedOrder['status'],
                'items' => $formattedItems,
                'total_amount' => $mergedOrder['total_amount'],
                'merge_info' => [
                    'original_orders' => $originalOrderIds,
                    'merged_at' => date('Y-m-d H:i:s')
                ],
                'notes' => $mergedOrder['notes']
            ];

            // Log merged order ticket
            log_message('info', 'New kitchen ticket created for merged order ' . $mergedOrderId . ' - Original orders: ' . implode(', ', $originalOrderIds) . ' - Table: ' . $tableLabel);
            log_message('info', 'Merged order ticket data: ' . json_encode($ticketData));

            // Cancel/void original tickets (if your system supports it)
            foreach ($originalOrderIds as $originalOrderId) {
                log_message('info', 'Original order ticket voided: ' . $originalOrderId . ' (merged into ' . $mergedOrderId . ')');
                // Here you would cancel the original tickets in your kitchen system
            }

            return true;

        } catch (\Exception $e) {
            log_message('error', 'Error updating merged order tickets: ' . $e->getMessage());
            return false;
        }
    }
}
