<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderItemModel extends Model
{
    protected $table = 'order_items';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'order_id',
        'menu_item_id',
        'quantity',
        'unit_price',
        'total_price',
        'notes',
        'created_at',
        'updated_at'
    ];
    protected $useTimestamps = true;
    
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at'; 
    
    public function getOrderItemsWithMenuDetails($orderId)
    {
        $builder = $this->db->table($this->table);
        $builder->select('order_items.*, menu_items.name as menu_item_name, menu_items.image as menu_item_image, menu_items.print_kot, menu_items.print_bot, menu_items.category as category_id');
        $builder->join('menu_items', 'menu_items.id = order_items.menu_item_id');
        $builder->where('order_items.order_id', $orderId);
        
        return $builder->get()->getResultArray();
    }
}






