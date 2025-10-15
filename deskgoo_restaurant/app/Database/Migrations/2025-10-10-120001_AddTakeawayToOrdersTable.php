<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTakeawayToOrdersTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('orders', [
            'takeaway_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'table_id',
            ],
            'order_type' => [
                'type'       => 'ENUM',
                'constraint' => ['dine_in', 'takeaway'],
                'default'    => 'dine_in',
                'after'      => 'takeaway_id',
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('orders', ['takeaway_id', 'order_type']);
    }
}