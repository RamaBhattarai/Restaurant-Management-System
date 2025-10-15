<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateOrderStatusEnum extends Migration
{
    public function up()
    {
        // Update the status column to include proper values and set default to 'placed'
        $sql = "ALTER TABLE orders MODIFY COLUMN status ENUM('draft', 'placed', 'preparing', 'ready', 'served', 'cancelled') DEFAULT 'placed'";
        $this->db->query($sql);
    }

    public function down()
    {
        // Revert back to original default
        $sql = "ALTER TABLE orders MODIFY COLUMN status ENUM('draft', 'placed', 'preparing', 'ready', 'served', 'cancelled') DEFAULT 'draft'";
        $this->db->query($sql);
    }
}
