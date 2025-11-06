<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderModel extends Model
{
    protected $table = 'orders';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'table_id', 
        'takeaway_id',
        'order_type',
        'total_amount',
        'discount_amount',
        'discount_type',
        'vat_amount',
        'vat_percentage',
        'status',
        'payment_method',
        'notes',
        'customer_invoice_data',
        'invoice_generated_at',
        'cash_amount',
        'card_amount',
        'online_amount',
        'payment_breakdown',
        'created_at', 
        'updated_at'
    ];
    protected $useTimestamps = true;
    
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    
    // Order statuses (simple system)
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    
    public function getOrderWithItems($orderId)
    {
        $order = $this->find($orderId);
        if (!$order) {
            return null;
        }
        
        $orderItemModel = new \App\Models\OrderItemModel();
        $order['items'] = $orderItemModel->getOrderItemsWithMenuDetails($orderId);
        
        return $order;
    }
}
