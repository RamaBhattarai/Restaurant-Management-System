<?php

namespace App\Models;

use CodeIgniter\Model;

class TakeawayModel extends Model
{
    protected $table = 'takeaways';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'takeaway_number',
        'status'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    // Validation
    protected $validationRules = [
        'takeaway_number' => 'required',
        'status' => 'required|in_list[active,completed]'
    ];
    protected $validationMessages = [];
    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = [];
    protected $afterInsert = [];
    protected $beforeUpdate = [];
    protected $afterUpdate = [];
    protected $beforeFind = [];
    protected $afterFind = [];
    protected $beforeDelete = [];
    protected $afterDelete = [];

    /**
     * Generate next takeaway number
     */
    public function generateTakeawayNumber()
    {
        // Get all takeaway numbers (including soft-deleted) to find the highest
        $db = \Config\Database::connect();
        $query = $db->query("SELECT takeaway_number FROM takeaways ORDER BY id DESC LIMIT 1");
        $result = $query->getRowArray();
        
        if ($result) {
            // Extract number from T001, T002, etc.
            $lastNumber = (int) substr($result['takeaway_number'], 1);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        
        // Keep trying until we find a unique number
        $attempts = 0;
        do {
            $takeawayNumber = 'T' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
            
            // Check if this number already exists (including deleted records)
            $checkQuery = $db->query("SELECT COUNT(*) as count FROM takeaways WHERE takeaway_number = ?", [$takeawayNumber]);
            $exists = $checkQuery->getRowArray()['count'] > 0;
            
            if (!$exists) {
                return $takeawayNumber;
            }
            
            $nextNumber++;
            $attempts++;
            
            // Safety check to prevent infinite loop
            if ($attempts > 1000) {
                // Fallback to timestamp-based number
                return 'T' . date('His'); // THHMMSS format
            }
        } while (true);
    }

    /**
     * Create new takeaway with auto-generated number
     */
    public function createTakeaway()
    {
        try {
            $takeawayNumber = $this->generateTakeawayNumber();
            
            $data = [
                'takeaway_number' => $takeawayNumber,
                'status' => 'active'
            ];
            
            log_message('info', 'Creating takeaway with data: ' . json_encode($data));
            
            $takeawayId = $this->insert($data);
            
            if ($takeawayId) {
                log_message('info', 'Takeaway created successfully with ID: ' . $takeawayId);
                return $this->find($takeawayId);
            }
            
            log_message('error', 'Failed to insert takeaway');
            return false;
        } catch (\Exception $e) {
            log_message('error', 'Exception creating takeaway: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get active takeaways with order counts, optionally filtered by date
     */
    public function getActiveTakeawaysWithOrders($date = null)
    {
        $db = \Config\Database::connect();
        
        $sql = "
            SELECT 
                t.*,
                COUNT(o.id) as order_count,
                COUNT(CASE WHEN o.status NOT IN ('completed', 'cancelled') THEN 1 END) as active_order_count,
                COUNT(CASE WHEN o.status = 'completed' THEN 1 END) as completed_order_count,
                COALESCE(SUM(o.total_amount), 0) as total_amount
            FROM takeaways t
            LEFT JOIN orders o ON t.id = o.takeaway_id
            WHERE t.status = 'active' AND t.deleted_at IS NULL";
        
        $params = [];
        
        // Add date filter if provided
        if ($date) {
            $sql .= " AND DATE(t.created_at) = ?";
            $params[] = $date;
        }
        
        $sql .= " GROUP BY t.id ORDER BY t.created_at DESC";
        
        $query = $db->query($sql, $params);
        
        return $query->getResultArray();
    }

    /**
     * Complete takeaway (mark as completed)
     */
    public function completeTakeaway($id)
    {
        return $this->update($id, ['status' => 'completed']);
    }
}