<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCategoryToMenuItems extends Migration
{
    public function up()
    {
        $this->forge->addColumn('menu_items', [
            'category' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
                'null' => true,
                'after' => 'description',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('menu_items', 'category');
    }
}
