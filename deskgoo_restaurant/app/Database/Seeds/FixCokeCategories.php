<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class FixCokeCategories extends Seeder
{
    public function run()
    {
        // Fix Coke items to beverage category
        $this->db->query("UPDATE menu_items SET category = 'beverage' WHERE name LIKE '%Coke%'");

        echo "✅ Fixed Coke items to beverage category\n";

        // Show final verification
        echo "\n📋 Final verification - All menu items:\n";
        $menuItems = $this->db->query('SELECT name, category FROM menu_items ORDER BY name')->getResultArray();
        foreach ($menuItems as $item) {
            echo "• {$item['name']} → {$item['category']}\n";
        }
    }
}