<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UpdateMenuCategories extends Seeder
{
    public function run()
    {
        // Update existing menu items with categories
        $updates = [
            ['Chicken Momo (10 pcs)', 'food'],
            ['Veg Momo (10 pcs)', 'food'],
            ['Margherita Pizza', 'food'],
            ['Farmhouse Pizza', 'food'],
            ['Coke 250ml', 'beverage'],
            ['Coke', 'beverage'],
            ['Veg Roll', 'food'],
            ['Naan', 'food']
        ];

        echo "Updating menu item categories...\n\n";

        foreach ($updates as $update) {
            $this->db->query('UPDATE menu_items SET category = ? WHERE name = ?', $update);
            echo "✅ Updated '{$update[0]}' to category: {$update[1]}\n";
        }

        echo "\n🎉 All menu items updated with categories!\n";

        // Verify the updates
        echo "\n📋 Verification - Current menu items:\n";
        $menuItems = $this->db->query('SELECT name, category FROM menu_items ORDER BY name')->getResultArray();
        foreach ($menuItems as $item) {
            echo "• {$item['name']} → {$item['category']}\n";
        }
    }
}