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
            
            // Get item-wise sales data with category information
            $query = $db->query("
                SELECT 
                    mi.id as menu_item_id,
                    mi.name as item_name,
                    mi.price as menu_price,
                    mi.category as category_id,
                    c.name as category_name,
                    mi.image as item_image,
                    COALESCE(SUM(CASE WHEN o.status = 'completed' AND o.created_at >= ? AND o.created_at <= ? THEN oi.quantity ELSE 0 END), 0) as total_quantity,
                    COALESCE(SUM(CASE WHEN o.status = 'completed' AND o.created_at >= ? AND o.created_at <= ? THEN oi.total_price ELSE 0 END), 0) as total_revenue,
                    COALESCE(AVG(CASE WHEN o.status = 'completed' AND o.created_at >= ? AND o.created_at <= ? THEN oi.unit_price END), mi.price) as avg_price,
                    COUNT(DISTINCT CASE WHEN o.status = 'completed' AND o.created_at >= ? AND o.created_at <= ? THEN o.id END) as order_count
                FROM menu_items mi
                LEFT JOIN categories c ON LOWER(mi.category) = LOWER(c.name)
                LEFT JOIN order_items oi ON mi.id = oi.menu_item_id  
                LEFT JOIN orders o ON oi.order_id = o.id
                WHERE mi.is_active = 1
                GROUP BY mi.id, mi.name, mi.price, mi.category, c.name, mi.image
                ORDER BY c.name ASC, total_revenue DESC, total_quantity DESC
            ", [$startDateTime, $endDateTime, $startDateTime, $endDateTime, $startDateTime, $endDateTime, $startDateTime, $endDateTime]);

            $items = $query->getResult();

            // Group items by category
            $categories = [];
            $totalSales = 0;
            $totalQuantity = 0;
            $itemsWithSales = 0;
            
            foreach ($items as $item) {
                $categoryId = $item->category_id ?? 'uncategorized';
                $categoryName = $item->category_name ?? 'Uncategorized';
                
                if (!isset($categories[$categoryId])) {
                    $categories[$categoryId] = [
                        'id' => $categoryId,
                        'name' => $categoryName,
                        'items' => [],
                        'total_revenue' => 0,
                        'total_quantity' => 0,
                        'item_count' => 0,
                        'items_with_sales' => 0
                    ];
                }
                
                $revenue = (float)($item->total_revenue ?? 0);
                $quantity = (int)($item->total_quantity ?? 0);
                
                $processedItem = [
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
                
                $categories[$categoryId]['items'][] = $processedItem;
                $categories[$categoryId]['total_revenue'] += $revenue;
                $categories[$categoryId]['total_quantity'] += $quantity;
                $categories[$categoryId]['item_count']++;
                if ($quantity > 0) {
                    $categories[$categoryId]['items_with_sales']++;
                }
                
                $totalSales += $revenue;
                $totalQuantity += $quantity;
                if ($quantity > 0) {
                    $itemsWithSales++;
                }
            }

            // Convert categories to array and sort by total revenue
            $categoryArray = array_values($categories);
            usort($categoryArray, function($a, $b) {
                return $b['total_revenue'] <=> $a['total_revenue'];
            });

            // Flatten items for backward compatibility
            $allItems = [];
            foreach ($categoryArray as $category) {
                $allItems = array_merge($allItems, $category['items']);
            }

            // Get top 5 items for chart
            $topItems = array_slice(array_filter($allItems, function($item) {
                return $item['has_sales'];
            }), 0, 5);

            // Get category performance data
            $categoryPerformance = array_map(function($category) {
                return [
                    'name' => $category['name'],
                    'revenue' => $category['total_revenue'],
                    'quantity' => $category['total_quantity'],
                    'item_count' => $category['item_count'],
                    'avg_revenue_per_item' => $category['item_count'] > 0 ? $category['total_revenue'] / $category['item_count'] : 0
                ];
            }, $categoryArray);

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
                    'total_items' => count($allItems),
                    'items_with_sales' => $itemsWithSales,
                    'total_categories' => count($categoryArray),
                    'categories_with_sales' => count(array_filter($categoryArray, function($cat) { return $cat['total_revenue'] > 0; }))
                ],
                'categories' => $categoryArray,
                'items' => $allItems,
                'top_items' => $topItems,
                'category_performance' => $categoryPerformance
            ]);

        } catch (\Exception $e) {
            log_message('error', 'Error generating item sales report: ' . $e->getMessage());
            return $this->failServerError('Error: ' . $e->getMessage());
        }
    }

    public function itemWiseSales()
    {
        try {
            $startDate = $this->request->getGet('start_date');
            $endDate = $this->request->getGet('end_date');
            $orderType = $this->request->getGet('order_type'); // Optional filter by order type
            $categoryId = $this->request->getGet('category_id'); // Optional filter by category
            
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
            
            // Build the query with optional filters
            $whereClause = "WHERE mi.is_active = 1 AND o.status = 'completed' AND o.created_at >= ? AND o.created_at <= ?";
            $params = [$startDateTime, $endDateTime];
            
            if ($orderType) {
                $whereClause .= " AND o.order_type = ?";
                $params[] = $orderType;
            }
            
            if ($categoryId) {
                $whereClause .= " AND c.id = ?";
                $params[] = $categoryId;
            }
            
            // Get item-wise sales data with order type breakdowns
            $query = $db->query("
                SELECT 
                    mi.id as menu_item_id,
                    mi.name as item_name,
                    mi.price as menu_price,
                    mi.category as category_id,
                    c.name as category_name,
                    mi.image as item_image,
                    COALESCE(SUM(oi.quantity), 0) as total_quantity,
                    COALESCE(SUM(oi.total_price), 0) as total_revenue,
                    COALESCE(SUM(CASE WHEN o.order_type = 'dine_in' THEN oi.quantity ELSE 0 END), 0) as dine_in_quantity,
                    COALESCE(SUM(CASE WHEN o.order_type = 'takeaway' THEN oi.quantity ELSE 0 END), 0) as takeaway_quantity,
                    COALESCE(SUM(CASE WHEN o.order_type = 'dine_in' THEN oi.total_price ELSE 0 END), 0) as dine_in_revenue,
                    COALESCE(SUM(CASE WHEN o.order_type = 'takeaway' THEN oi.total_price ELSE 0 END), 0) as takeaway_revenue
                FROM menu_items mi
                LEFT JOIN categories c ON LOWER(mi.category) = LOWER(c.name)
                LEFT JOIN order_items oi ON mi.id = oi.menu_item_id  
                LEFT JOIN orders o ON oi.order_id = o.id
                $whereClause
                GROUP BY mi.id, mi.name, mi.price, mi.category, c.name, mi.image
                ORDER BY total_revenue DESC, total_quantity DESC
            ", $params);

            $items = $query->getResult();

            // Process items
            $processedItems = [];
            $totalQuantity = 0;
            $totalRevenue = 0;
            $topItem = null;
            $topItemRevenue = 0;
            
            foreach ($items as $item) {
                $quantity = (int)$item->total_quantity;
                $revenue = (float)$item->total_revenue;
                
                if ($quantity > 0) {
                    $processedItem = [
                        'id' => $item->menu_item_id,
                        'name' => $item->item_name,
                        'category' => $item->category_name ?? 'Uncategorized',
                        'qty_sold' => $quantity,
                        'total_sales' => $revenue,
                        'dine_in' => (int)$item->dine_in_quantity,
                        'takeaway' => (int)$item->takeaway_quantity,
                        'delivery' => 0 // Not implemented yet
                    ];
                    
                    $processedItems[] = $processedItem;
                    
                    $totalQuantity += $quantity;
                    $totalRevenue += $revenue;
                    
                    if ($revenue > $topItemRevenue) {
                        $topItemRevenue = $revenue;
                        $topItem = $item->item_name;
                    }
                }
            }

            // Get categories for filter dropdown
            $categoryQuery = $db->query("SELECT id, name FROM categories ORDER BY name");
            $categories = $categoryQuery->getResult();

            return $this->respond([
                'success' => true,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ],
                'summary' => [
                    'date_range_display' => $startDate . ' to ' . $endDate,
                    'total_sales' => $totalRevenue,
                    'total_items_sold' => $totalQuantity,
                    'top_item' => $topItem,
                    'total_quantity' => $totalQuantity,
                    'total_sales_formatted' => number_format($totalRevenue, 2)
                ],
                'items' => $processedItems,
                'categories' => $categories,
                'filters' => [
                    'order_type' => $orderType,
                    'category_id' => $categoryId
                ]
            ]);

        } catch (\Exception $e) {
            log_message('error', 'Error generating item-wise sales report: ' . $e->getMessage());
            return $this->failServerError('Error: ' . $e->getMessage());
        }
    }
}
