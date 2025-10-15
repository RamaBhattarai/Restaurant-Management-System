<?php

namespace App\Controllers\Admin;

use CodeIgniter\RESTful\ResourceController;

class ReportController extends ResourceController
{
    protected $format = 'json';

    public function itemSales()
    {
        try {
            $startDate = $this->request->getGet('start_date');
            $endDate = $this->request->getGet('end_date');
            
            // Default to today if no dates provided
            if (!$startDate) {
                $startDate = date('Y-m-d');
            }
            if (!$endDate) {
                $endDate = date('Y-m-d');
            }

            // Ensure proper date format and add time boundaries
            $startDateTime = date('Y-m-d 00:00:00', strtotime($startDate));
            $endDateTime = date('Y-m-d 23:59:59', strtotime($endDate));

            $db = \Config\Database::connect();
            
            // Get item-wise sales data with proper datetime filtering
            $query = $db->query("
                SELECT 
                    mi.id as menu_item_id,
                    mi.name as item_name,
                    mi.price as menu_price,
                    mi.image as item_image,
                    COALESCE(SUM(CASE WHEN o.status = 'completed' AND o.created_at >= ? AND o.created_at <= ? THEN oi.quantity ELSE 0 END), 0) as total_quantity,
                    COALESCE(SUM(CASE WHEN o.status = 'completed' AND o.created_at >= ? AND o.created_at <= ? THEN oi.total_price ELSE 0 END), 0) as total_revenue,
                    COALESCE(AVG(CASE WHEN o.status = 'completed' AND o.created_at >= ? AND o.created_at <= ? THEN oi.unit_price END), mi.price) as avg_price,
                    COUNT(DISTINCT CASE WHEN o.status = 'completed' AND o.created_at >= ? AND o.created_at <= ? THEN o.id END) as order_count
                FROM menu_items mi
                LEFT JOIN order_items oi ON mi.id = oi.menu_item_id  
                LEFT JOIN orders o ON oi.order_id = o.id
                WHERE mi.is_active = 1
                GROUP BY mi.id, mi.name, mi.price, mi.image
                ORDER BY total_revenue DESC, total_quantity DESC
            ", [$startDateTime, $endDateTime, $startDateTime, $endDateTime, $startDateTime, $endDateTime, $startDateTime, $endDateTime]);

            $items = $query->getResult();

            // Calculate totals
            $totalSales = 0;
            $totalQuantity = 0;
            $itemsWithSales = 0;
            foreach ($items as $item) {
                $totalSales += (float)($item->total_revenue ?? 0);
                $quantity = (int)($item->total_quantity ?? 0);
                $totalQuantity += $quantity;
                if ($quantity > 0) {
                    $itemsWithSales++;
                }
            }

            // Process results
            $processedItems = [];
            foreach ($items as $item) {
                $revenue = (float)($item->total_revenue ?? 0);
                $quantity = (int)($item->total_quantity ?? 0);
                
                $processedItems[] = [
                    'id' => $item->menu_item_id,
                    'name' => $item->item_name,
                    'price' => (float)$item->menu_price,
                    'image' => $item->item_image,
                    'quantity_sold' => $quantity,
                    'total_revenue' => $revenue,
                    'avg_price' => (float)$item->avg_price,
                    'order_count' => (int)$item->order_count,
                    'has_sales' => $quantity > 0
                ];
            }

            // Get top 5 items for chart
            $topItems = array_slice(array_filter($processedItems, function($item) {
                return $item['has_sales'];
            }), 0, 5);

            return $this->respond([
                'success' => true,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ],
                'summary' => [
                    'total_revenue' => $totalSales,
                    'total_quantity' => $totalQuantity,
                    'items_sold' => $itemsWithSales,
                    'total_items' => count($processedItems),
                    'items_with_sales' => $itemsWithSales
                ],
                'items' => $processedItems,
                'top_items' => $topItems
            ]);

        } catch (\Exception $e) {
            log_message('error', 'Error generating item sales report: ' . $e->getMessage());
            return $this->failServerError('Error: ' . $e->getMessage());
        }
    }
}
