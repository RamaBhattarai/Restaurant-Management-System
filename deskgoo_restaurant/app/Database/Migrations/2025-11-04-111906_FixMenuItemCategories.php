<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixMenuItemCategories extends Migration
{
    public function up()
    {
        // Update menu_items to use proper category IDs instead of string names
        $this->db->query("UPDATE menu_items SET category = 4 WHERE category = 'food'");
        $this->db->query("UPDATE menu_items SET category = 5 WHERE category = 'beverage'");
        $this->db->query("UPDATE menu_items SET category = 6 WHERE category = 'desserts'");
        $this->db->query("UPDATE menu_items SET category = 12 WHERE category = 'soft drinks'");
        $this->db->query("UPDATE menu_items SET category = 2 WHERE category = 'snacks'");
        $this->db->query("UPDATE menu_items SET category = 14 WHERE category = 'main course'");
    }

    public function down()
    {
        // Revert back to string names (though this might not be perfect)
        $this->db->query("UPDATE menu_items SET category = 'food' WHERE category = 4");
        $this->db->query("UPDATE menu_items SET category = 'beverage' WHERE category = 5");
        $this->db->query("UPDATE menu_items SET category = 'desserts' WHERE category = 6");
        $this->db->query("UPDATE menu_items SET category = 'soft drinks' WHERE category = 12");
        $this->db->query("UPDATE menu_items SET category = 'snacks' WHERE category = 2");
        $this->db->query("UPDATE menu_items SET category = 'main course' WHERE category = 14");
    }
}
