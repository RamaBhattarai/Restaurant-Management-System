<?php

namespace App\Models;

use CodeIgniter\Model;

class MenuItemModel extends Model
{
    protected $table = 'menu_items';
    protected $primaryKey = 'id';
    protected $allowedFields = ['name', 'price', 'description', 'category', 'image', 'is_active', 'print_kot', 'print_bot', 'created_at', 'updated_at'];
    protected $useTimestamps = true;
    
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
