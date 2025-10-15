<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTakeawaysTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'takeaway_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'unique'     => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['active', 'completed'],
                'default'    => 'active',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('takeaway_number');
        $this->forge->createTable('takeaways');
    }

    public function down()
    {
        $this->forge->dropTable('takeaways');
    }
}