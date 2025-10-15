<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCompanySettingsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 5,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'company_name' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => false,
                'default' => 'Restaurant Name',
            ],
            'company_logo' => [
                'type' => 'VARCHAR',
                'constraint' => '500',
                'null' => true,
                'comment' => 'Path to company logo file',
            ],
            'vat_number' => [
                'type' => 'VARCHAR',
                'constraint' => '50',
                'null' => true,
            ],
            'phone' => [
                'type' => 'VARCHAR',
                'constraint' => '20',
                'null' => true,
            ],
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => true,
            ],
            'address' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'website' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => true,
            ],
            'facebook_url' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => true,
            ],
            'instagram_url' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => true,
            ],
            'tiktok_url' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->createTable('company_settings');

        // Insert default company settings
        $this->db->table('company_settings')->insert([
            'id' => 1,
            'company_name' => 'Deskgoo Restaurant',
            'vat_number' => '',
            'phone' => '',
            'email' => '',
            'address' => '',
            'website' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('company_settings');
    }
}
