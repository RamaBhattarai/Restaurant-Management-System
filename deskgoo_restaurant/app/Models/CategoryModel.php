<?php

namespace App\Models;

use CodeIgniter\Model;

class CategoryModel extends Model
{
    protected $table = 'categories';
    protected $primaryKey = 'id';
    protected $allowedFields = ['name', 'created_at', 'updated_at'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $returnType = 'array';

    // Validation rules
    protected $validationRules = [
        'name' => 'required|min_length[2]|max_length[100]|is_unique[categories.name,id,{id}]'
    ];

    protected $validationMessages = [
        'name' => [
            'required' => 'Category name is required',
            'min_length' => 'Category name must be at least 2 characters',
            'max_length' => 'Category name cannot exceed 100 characters',
            'is_unique' => 'Category name already exists'
        ]
    ];
}