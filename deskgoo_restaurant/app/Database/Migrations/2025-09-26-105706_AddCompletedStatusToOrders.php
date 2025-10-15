<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCompletedStatusToOrders extends Migration
{
    public function up()
    {
        // Add 'completed' to the status enum
        $sql = "ALTER TABLE orders MODIFY COLUMN status ENUM('draft', 'placed', 'preparing', 'ready', 'served', 'completed', 'cancelled') DEFAULT 'placed'";
        $this->db->query($sql);
    }

    public function down()
    {
        // Remove 'completed' from the status enum
        $sql = "ALTER TABLE orders MODIFY COLUMN status ENUM('draft', 'placed', 'preparing', 'ready', 'served', 'cancelled') DEFAULT 'placed'";
        $this->db->query($sql);
    }
}
