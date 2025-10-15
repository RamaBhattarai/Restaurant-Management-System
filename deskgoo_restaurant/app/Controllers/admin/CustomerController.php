<?php

namespace App\Controllers\Admin;
use App\Controllers\BaseController;
use App\Models\CustomerModel;

class CustomerController extends BaseController
{
    protected $model;

    public function __construct()
    {
        $this->model = new CustomerModel();
    }

    // List all customers
    public function index()
    {
        try {
            $customers = $this->model->findAll();
            return $this->response->setJSON([
                'success' => true,
                'data' => $customers,
                'message' => 'Customers retrieved successfully'
            ]);
        } catch (\Exception $e) {
            log_message('error', 'CustomerController::index - ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error' => 'Failed to retrieve customers'
            ]);
        }
    }

    // Get single customer
    public function show($id = null)
    {
        $customer = $this->model->find($id);
        if (!$customer) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Customer not found']);
        }
        return $this->response->setJSON($customer);
    }

    // Create a new customer
    public function create()
    {
        try {
            $data = $this->request->getJSON(true);

            if (!$data) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'error' => 'Invalid JSON data'
                ]);
            }

            // Validate required fields
            $requiredFields = ['first_name', 'last_name', 'phone', 'email'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    return $this->response->setStatusCode(400)->setJSON([
                        'success' => false,
                        'error' => "Field '{$field}' is required"
                    ]);
                }
            }

            // Check if email already exists
            $existingCustomer = $this->model->where('email', $data['email'])->first();
            if ($existingCustomer) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'error' => 'Email already exists'
                ]);
            }

            // Check if phone already exists
            $existingPhone = $this->model->where('phone', $data['phone'])->first();
            if ($existingPhone) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'error' => 'Phone number already exists'
                ]);
            }

            $customerId = $this->model->insert($data);
            if (!$customerId) {
                return $this->response->setStatusCode(500)->setJSON([
                    'success' => false,
                    'error' => 'Failed to create customer'
                ]);
            }

            $customer = $this->model->find($customerId);
            return $this->response->setStatusCode(201)->setJSON([
                'success' => true,
                'data' => $customer,
                'message' => 'Customer created successfully'
            ]);
        } catch (\Exception $e) {
            log_message('error', 'CustomerController::create - ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error' => 'Failed to create customer'
            ]);
        }
    }

    // Update customer
    public function update($id = null)
    {
        try {
            if (!$id) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'error' => 'Customer ID is required'
                ]);
            }

            $customer = $this->model->find($id);
            if (!$customer) {
                return $this->response->setStatusCode(404)->setJSON([
                    'success' => false,
                    'error' => 'Customer not found'
                ]);
            }

            $data = $this->request->getJSON(true);
            if (!$data) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'error' => 'Invalid JSON data'
                ]);
            }

            // Check if email already exists (excluding current customer)
            if (!empty($data['email'])) {
                $existingCustomer = $this->model->where('email', $data['email'])
                                                ->where('id !=', $id)
                                                ->first();
                if ($existingCustomer) {
                    return $this->response->setStatusCode(400)->setJSON([
                        'success' => false,
                        'error' => 'Email already exists'
                    ]);
                }
            }

            // Check if phone already exists (excluding current customer)
            if (!empty($data['phone'])) {
                $existingPhone = $this->model->where('phone', $data['phone'])
                                            ->where('id !=', $id)
                                            ->first();
                if ($existingPhone) {
                    return $this->response->setStatusCode(400)->setJSON([
                        'success' => false,
                        'error' => 'Phone number already exists'
                    ]);
                }
            }

            $updated = $this->model->update($id, $data);
            if (!$updated) {
                return $this->response->setStatusCode(500)->setJSON([
                    'success' => false,
                    'error' => 'Failed to update customer'
                ]);
            }

            $updatedCustomer = $this->model->find($id);
            return $this->response->setJSON([
                'success' => true,
                'data' => $updatedCustomer,
                'message' => 'Customer updated successfully'
            ]);
        } catch (\Exception $e) {
            log_message('error', 'CustomerController::update - ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error' => 'Failed to update customer'
            ]);
        }
    }

    // Delete customer
    public function delete($id = null)
    {
        try {
            if (!$id) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'error' => 'Customer ID is required'
                ]);
            }

            $customer = $this->model->find($id);
            if (!$customer) {
                return $this->response->setStatusCode(404)->setJSON([
                    'success' => false,
                    'error' => 'Customer not found'
                ]);
            }

            $deleted = $this->model->delete($id);
            if (!$deleted) {
                return $this->response->setStatusCode(500)->setJSON([
                    'success' => false,
                    'error' => 'Failed to delete customer'
                ]);
            }

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Customer deleted successfully',
                'id' => $id
            ]);
        } catch (\Exception $e) {
            log_message('error', 'CustomerController::delete - ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error' => 'Failed to delete customer'
            ]);
        }
    }
}
