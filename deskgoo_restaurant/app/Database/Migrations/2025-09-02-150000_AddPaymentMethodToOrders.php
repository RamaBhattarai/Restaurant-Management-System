<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPaymentMethodToOrders extends Migration
{
    public function up()
    {
        $this->forge->addColumn('orders', [
            'payment_method' => [
                'type' => 'ENUM',
                'constraint' => ['cash', 'fonepay', 'card', 'others'],
                'default' => 'cash',
                'after' => 'status'
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('orders', 'payment_method');
    }
}
