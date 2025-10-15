<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class DebugController extends BaseController
{
    public function checkTable()
    {
        $db = \Config\Database::connect();
        
        echo "Checking orders for tables...\n\n";
        
        // Get all orders and their statuses
        $orders = $db->query('SELECT id, table_id, status, created_at FROM orders ORDER BY table_id, created_at DESC')->getResultArray();
        
        echo "All orders in database:\n";
        foreach ($orders as $order) {
            echo "Order ID: {$order['id']}, Table ID: {$order['table_id']}, Status: {$order['status']}, Created: {$order['created_at']}\n";
        }
        
        echo "\n\nOrders for table 18 specifically:\n";
        $table18Orders = $db->query('SELECT id, table_id, status, created_at FROM orders WHERE table_id = 18')->getResultArray();
        foreach ($table18Orders as $order) {
            echo "Order ID: {$order['id']}, Table ID: {$order['table_id']}, Status: {$order['status']}, Created: {$order['created_at']}\n";
        }
        
        echo "\n\nTable 18 details:\n";
        $table18 = $db->query('SELECT * FROM dining_tables WHERE id = 18')->getRowArray();
        if ($table18) {
            print_r($table18);
        } else {
            echo "Table 18 not found\n";
        }
    }
}