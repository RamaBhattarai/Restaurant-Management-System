<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPaymentBreakdownToOrdersTable extends Migration
{
    public function up()
    {
        $fields = [
            'cash_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'null' => true,
                'default' => null,
                'comment' => 'Amount paid by cash in partial payments'
            ],
            'card_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'null' => true,
                'default' => null,
                'comment' => 'Amount paid by card in partial payments'
            ],
            'fonepay_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'null' => true,
                'default' => null,
                'comment' => 'Amount paid by fonepay in partial payments'
            ],
            'qr_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'null' => true,
                'default' => null,
                'comment' => 'Amount paid by QR in partial payments'
            ],
            'others_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'null' => true,
                'default' => null,
                'comment' => 'Amount paid by other methods in partial payments'
            ],
            'payment_breakdown' => [
                'type' => 'JSON',
                'null' => true,
                'comment' => 'JSON object storing detailed payment breakdown for complex partial payments'
            ]
        ];

        $this->forge->addColumn('orders', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('orders', [
            'cash_amount',
            'card_amount', 
            'fonepay_amount',
            'qr_amount',
            'others_amount',
            'payment_breakdown'
        ]);
    }
}