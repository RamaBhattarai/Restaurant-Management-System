<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerModel extends Model
{
    protected $table = 'customers';
    protected $primaryKey = 'id';
    protected $allowedFields = ['first_name', 'last_name', 'phone', 'email'];
    protected $useTimestamps = true;  // automatically handles created_at & updated_at
}
