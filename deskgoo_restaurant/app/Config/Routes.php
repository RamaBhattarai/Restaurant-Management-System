<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// Route to serve uploaded images
$routes->get('uploads/menu-images/(:any)', function($filename) {
    $path = WRITEPATH . 'uploads/menu-images/' . $filename;
    if (file_exists($path)) {
        $mimeType = mime_content_type($path);
        header('Content-Type: ' . $mimeType);
        readfile($path);
        exit;
    } else {
        throw new \CodeIgniter\Exceptions\PageNotFoundException();
    }
});

// Route to serve profile pictures
$routes->get('uploads/profile-pictures/(:any)', function($filename) {
    $path = WRITEPATH . 'uploads/profile-pictures/' . $filename;
    if (file_exists($path)) {
        $mimeType = mime_content_type($path);
        header('Content-Type: ' . $mimeType);
        readfile($path);
        exit;
    } else {
        throw new \CodeIgniter\Exceptions\PageNotFoundException();
    }
});

// Route to serve default logo and other files from uploads root
$routes->get('uploads/(:any)', function($filename) {
    $path = WRITEPATH . 'uploads/' . $filename;
    if (file_exists($path)) {
        $mimeType = mime_content_type($path);
        header('Content-Type: ' . $mimeType);
        readfile($path);
        exit;
    } else {
        throw new \CodeIgniter\Exceptions\PageNotFoundException();
    }
});

$routes->group('admin', function($routes){
    // General upload route for file uploads
    $routes->options('upload', 'Admin\MenuController::uploadImage');
    $routes->post('upload', 'Admin\MenuController::uploadImage');

    // Authentication routes
    $routes->options('auth/login', 'Admin\AuthController::login');
    $routes->options('auth/logout', 'Admin\AuthController::logout');
    $routes->options('auth/verify', 'Admin\AuthController::verify');
    $routes->options('auth/forgot-password', 'Admin\AuthController::forgotPassword');
    $routes->options('auth/change-password', 'Admin\AuthController::changePassword');

    $routes->post('auth/login', 'Admin\AuthController::login');
    $routes->post('auth/logout', 'Admin\AuthController::logout');
    $routes->get('auth/verify', 'Admin\AuthController::verify');
    $routes->post('auth/verify', 'Admin\AuthController::verify');
    $routes->post('auth/forgot-password', 'Admin\AuthController::forgotPassword');
    $routes->put('auth/change-password', 'Admin\AuthController::changePassword');

    // Handle OPTIONS requests for CORS preflight
    $routes->options('areas', 'Admin\AreaController::create');
    $routes->options('areas/(:num)', 'Admin\AreaController::update/$1');
    $routes->options('tables', 'Admin\TableController::create');
    $routes->options('tables/(:num)', 'Admin\TableController::update/$1');
    $routes->options('menu-items', 'Admin\MenuController::create');
    $routes->options('menu-items/(:num)', 'Admin\MenuController::update/$1');
    $routes->options('menu-items/upload-image', 'Admin\MenuController::uploadImage');
    $routes->options('orders', 'Admin\OrderController::getSalesReportOrders');
    $routes->options('orders/(:num)', 'Admin\OrderController::getOrder/$1');
    $routes->options('orders/(:num)/items', 'Admin\OrderController::addItems/$1');
    $routes->options('orders/(:num)/items/(:num)', 'Admin\OrderController::options/$1/$2'); // For PUT and DELETE operations
    $routes->options('orders/(:num)/status', 'Admin\OrderController::updateStatus/$1');
    $routes->options('orders/(:num)/payment-method', 'Admin\OrderController::updatePaymentMethod/$1');
    $routes->options('orders/(:num)/checkout', 'Admin\OrderController::checkout/$1');
    $routes->options('orders/(:num)/print-invoice', 'Admin\OrderController::printCustomerInvoice/$1');
    $routes->options('orders/day-report', 'Admin\OrderController::dayReport');
    $routes->options('profile/(:num)', 'Admin\ProfileController::show/$1');
    $routes->options('profile/(:num)/upload-picture', 'Admin\ProfileController::uploadPicture/$1');
    $routes->options('profile/(:num)/delete-picture', 'Admin\ProfileController::deletePicture/$1');
    $routes->options('customers', 'Admin\CustomerController::index');
    $routes->options('customers/(:num)', 'Admin\CustomerController::show/$1');

    $routes->get('areas', 'Admin\AreaController::index');
    $routes->post('areas', 'Admin\AreaController::create');
    $routes->put('areas/(:num)', 'Admin\AreaController::update/$1');
    $routes->delete('areas/(:num)', 'Admin\AreaController::delete/$1');

    //tables--
    $routes->get('tables', 'Admin\TableController::index');
    $routes->post('tables', 'Admin\TableController::create');
    $routes->put('tables/(:num)', 'Admin\TableController::update/$1');
    $routes->delete('tables/(:num)', 'Admin\TableController::delete/$1');

    //menu--
    $routes->get('menu-items', 'Admin\MenuController::index');
    $routes->post('menu-items', 'Admin\MenuController::create');
    $routes->put('menu-items/(:num)', 'Admin\MenuController::update/$1');
    $routes->delete('menu-items/(:num)', 'Admin\MenuController::delete/$1');
    $routes->post('menu-items/upload-image', 'Admin\MenuController::uploadImage');

    //categories--
    $routes->options('categories', 'Admin\CategoryController::create');
    $routes->options('categories/(:num)', 'Admin\CategoryController::update/$1');
    $routes->get('categories', 'Admin\CategoryController::index');
    $routes->post('categories', 'Admin\CategoryController::create');
    $routes->put('categories/(:num)', 'Admin\CategoryController::update/$1');
    $routes->delete('categories/(:num)', 'Admin\CategoryController::delete/$1');

    // Orders
    $routes->get('orders', 'Admin\OrderController::getSalesReportOrders');    // Get orders for sales report with filtering
    $routes->get('orders/all', 'Admin\OrderController::index');               // Get all orders (order history)
    $routes->get('orders/pos', 'Admin\OrderController::getAllOrders');        // Get all orders (both dine-in and takeaway) for POS
    $routes->post('orders', 'Admin\OrderController::createOrder');             // Create new order (Place order from POS)
    $routes->get('orders/(:num)', 'Admin\OrderController::getOrder/$1');       // Get specific order with items
    $routes->post('orders/(:num)/items', 'Admin\OrderController::addItems/$1'); // Add items to existing order
    $routes->put('orders/(:num)/items/(:num)', 'Admin\OrderController::updateOrderItem/$1/$2'); // Update order item quantity/notes
    $routes->delete('orders/(:num)/items/(:num)', 'Admin\OrderController::removeOrderItem/$1/$2'); // Remove/cancel order item
    $routes->put('orders/(:num)/status', 'Admin\OrderController::updateStatus/$1'); // Update order status
    $routes->put('orders/(:num)/payment-method', 'Admin\OrderController::updatePaymentMethod/$1'); // Update payment method
    $routes->post('orders/(:num)/checkout', 'Admin\OrderController::checkout/$1'); // Complete checkout and free table
    $routes->get('orders/(:num)/print-invoice', 'Admin\OrderController::printCustomerInvoice/$1'); // Print saved customer invoice
    $routes->get('orders/day-report', 'Admin\OrderController::dayReport');      // Get day-end report

    // Profile Management
    $routes->get('profile/(:num)', 'Admin\ProfileController::show/$1');         // Get user profile
    $routes->put('profile/(:num)', 'Admin\ProfileController::update/$1');       // Update user profile
    $routes->post('profile/(:num)/upload-picture', 'Admin\ProfileController::uploadPicture/$1'); // Upload profile picture
    $routes->delete('profile/(:num)/delete-picture', 'Admin\ProfileController::deletePicture/$1'); // Delete profile picture

    //customers
    $routes->get('customers', 'Admin\CustomerController::index');
    $routes->get('customers/(:num)', 'Admin\CustomerController::show/$1');
    $routes->post('customers', 'Admin\CustomerController::create');
    $routes->put('customers/(:num)', 'Admin\CustomerController::update/$1');
    $routes->delete('customers/(:num)', 'Admin\CustomerController::delete/$1');

    // Takeaways
    $routes->options('takeaways', 'Admin\TakeawayController::index');
    $routes->options('takeaways', 'Admin\TakeawayController::store');
    $routes->options('takeaways/(:num)', 'Admin\TakeawayController::show/$1');
    $routes->options('takeaways/(:num)/complete', 'Admin\TakeawayController::complete/$1');
    $routes->options('takeaways/(:num)', 'Admin\TakeawayController::delete/$1');

    $routes->get('takeaways', 'Admin\TakeawayController::index');
    $routes->post('takeaways', 'Admin\TakeawayController::store');
    $routes->get('takeaways/(:num)', 'Admin\TakeawayController::show/$1');
    $routes->put('takeaways/(:num)/complete', 'Admin\TakeawayController::complete/$1');
    $routes->delete('takeaways/(:num)', 'Admin\TakeawayController::delete/$1');

    // Takeaway Orders (for management)
    $routes->options('takeaway-orders', 'Admin\TakeawayController::getTakeawayOrders');
    $routes->get('takeaway-orders', 'Admin\TakeawayController::getTakeawayOrders');

    // Table Transfer
    $routes->options('table-transfer/active-orders', 'Admin\TableTransferController::getActiveOrders');
    $routes->options('table-transfer/order/(:num)', 'Admin\TableTransferController::getOrderByTable/$1');
    $routes->options('table-transfer/available-tables', 'Admin\TableTransferController::getAvailableTables');
    $routes->options('table-transfer/transfer', 'Admin\TableTransferController::transferOrder');
    $routes->options('table-transfer/merge', 'Admin\TableTransferController::mergeOrders');
    $routes->options('table-transfer/reprint-ticket/(:num)', 'Admin\TableTransferController::reprintTicket/$1');

    $routes->get('table-transfer/active-orders', 'Admin\TableTransferController::getActiveOrders');
    $routes->get('table-transfer/order/(:num)', 'Admin\TableTransferController::getOrderByTable/$1');
    $routes->get('table-transfer/available-tables', 'Admin\TableTransferController::getAvailableTables');
    $routes->post('table-transfer/transfer', 'Admin\TableTransferController::transferOrder');
    $routes->post('table-transfer/merge', 'Admin\TableTransferController::mergeOrders');
    $routes->post('table-transfer/reprint-ticket/(:num)', 'Admin\TableTransferController::reprintTicket/$1');

    // Reports
    $routes->options('reports/item-sales', 'Admin\ReportController::itemSales');
    $routes->get('reports/item-sales', 'Admin\ReportController::itemSales');
    $routes->options('reports/item-wise-sales', 'Admin\ReportController::itemWiseSales');
    $routes->get('reports/item-wise-sales', 'Admin\ReportController::itemWiseSales');

    // Company Settings
    $routes->options('company-settings', 'Admin\CompanySettingsController::index');
    $routes->options('company-settings/(:num)', 'Admin\CompanySettingsController::update/$1');
    $routes->options('company-settings/upload-logo', 'Admin\CompanySettingsController::uploadLogo');
    $routes->options('company-settings/delete-logo', 'Admin\CompanySettingsController::deleteLogo');

    $routes->get('company-settings', 'Admin\CompanySettingsController::index');
    $routes->put('company-settings/(:num)', 'Admin\CompanySettingsController::update/$1');
    $routes->put('company-settings', 'Admin\CompanySettingsController::update'); // For single settings record
    $routes->post('company-settings/upload-logo', 'Admin\CompanySettingsController::uploadLogo');
    $routes->delete('company-settings/delete-logo', 'Admin\CompanySettingsController::deleteLogo');
});

