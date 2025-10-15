<?php

namespace App\Models;

use CodeIgniter\Model;

class TableModel extends Model
{
    protected $table = 'dining_tables';
    protected $primaryKey = 'id';
    protected $allowedFields = ['area_id', 'label', 'seats', 'status', 'created_at', 'updated_at'];
    protected $useTimestamps = true;
    protected $returnType = 'array';
}
