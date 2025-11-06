<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddInvoiceDataToOrdersTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('orders', [
            'customer_invoice_data' => [
                'type' => 'LONGTEXT',
                'null' => true,
                'after' => 'notes'
            ],
            'invoice_generated_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'customer_invoice_data'
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('orders', ['customer_invoice_data', 'invoice_generated_at']);
    }
}