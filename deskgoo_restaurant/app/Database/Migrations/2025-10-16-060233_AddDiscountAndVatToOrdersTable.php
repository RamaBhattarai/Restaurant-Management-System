<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDiscountAndVatToOrdersTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('orders', [
            'discount_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'default' => 0.00,
                'after' => 'total_amount',
            ],
            'discount_type' => [
                'type' => 'ENUM',
                'constraint' => ['percentage', 'fixed'],
                'default' => 'percentage',
                'after' => 'discount_amount',
            ],
            'vat_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'default' => 0.00,
                'after' => 'discount_type',
            ],
            'vat_percentage' => [
                'type' => 'DECIMAL',
                'constraint' => '5,2',
                'default' => 0.00,
                'after' => 'vat_amount',
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('orders', ['discount_amount', 'discount_type', 'vat_amount', 'vat_percentage']);
    }
}
