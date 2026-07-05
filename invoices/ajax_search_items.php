<?php
// Start session and set JSON header FIRST
session_start();
header('Content-Type: application/json');

// Simple auth check - no includes
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// Check role
$allowed_roles = ['sales', 'super_admin', 'manager', 'technician'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Get search query
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

if (strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

try {
    // Database connection
    require_once "../config/db.php";
    
    $results = [];
    $like = "%$q%";
    
    switch ($category) {
        case 'Device':
            $stmt = $conn->prepare("SELECT serial_number as id, model_name as name, CONCAT(processor, ' | ', ram, 'GB RAM | ', storage_type, ' ', storage_capacity, 'GB') as specs, price FROM devices WHERE status = 'In Stock' AND (serial_number LIKE ? OR model_name LIKE ?) LIMIT 20");
            $stmt->execute([$like, $like]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['type'] = 'Device';
                $row['description'] = $row['name'];
                $results[] = $row;
            }
            break;
            
        case 'Monitor':
            $stmt = $conn->prepare("SELECT serial_number as id, model_name as name, CONCAT(size_inches, ' inch') as specs, price FROM monitors WHERE status = 'In Stock' AND (serial_number LIKE ? OR model_name LIKE ?) LIMIT 20");
            $stmt->execute([$like, $like]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['type'] = 'Monitor';
                $row['description'] = $row['name'];
                $results[] = $row;
            }
            break;
            
        case 'Printer':
            $stmt = $conn->prepare("SELECT serial_number as id, model_name as name, 'N/A' as specs, price FROM printers WHERE status = 'In Stock' AND (serial_number LIKE ? OR model_name LIKE ?) LIMIT 20");
            $stmt->execute([$like, $like]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['type'] = 'Printer';
                $row['description'] = $row['name'];
                $results[] = $row;
            }
            break;
            
        case 'Smartboard':
            // Smartboards uses 'model' not 'model_name', and status = 'instock'
            $stmt = $conn->prepare("SELECT serial_number as id, model as name, CONCAT(size_inches, ' inch') as specs, price FROM smartboards WHERE status = 'instock' AND (serial_number LIKE ? OR model LIKE ?) LIMIT 20");
            $stmt->execute([$like, $like]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['type'] = 'Smartboard';
                $row['description'] = $row['name'];
                $results[] = $row;
            }
            break;
            
        case 'UPS':
            $stmt = $conn->prepare("SELECT serial_number as id, model as name, CONCAT(capacity, ' VA') as specs, price FROM ups WHERE status = 'instock' AND (serial_number LIKE ? OR model LIKE ?) LIMIT 20");
            $stmt->execute([$like, $like]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['type'] = 'UPS';
                $row['description'] = $row['name'];
                $results[] = $row;
            }
            break;
            
        case 'Phone':
            $stmt = $conn->prepare("SELECT serial_number as id, CONCAT(brand, ' ', model) as name, CONCAT(ram, 'GB RAM | ', storage_capacity, 'GB') as specs, price FROM phones WHERE status = 'instock' AND (serial_number LIKE ? OR brand LIKE ? OR model LIKE ?) LIMIT 20");
            $stmt->execute([$like, $like, $like]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['type'] = 'Phone';
                $row['description'] = $row['name'];
                $results[] = $row;
            }
            break;
            
        case 'Accessory':
            $stmt = $conn->prepare("SELECT id, name, 'Qty available' as specs, price FROM accessories WHERE status = 'instock' AND (id LIKE ? OR name LIKE ?) LIMIT 20");
            $stmt->execute([$like, $like]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['type'] = 'Accessory';
                $row['description'] = $row['name'];
                $results[] = $row;
            }
            break;
            
        case 'Graphics Card':
            $stmt = $conn->prepare("SELECT id, type as name, CONCAT(storage_capacity, 'GB') as specs, price FROM graphic_cards WHERE status = 'instock' AND (id LIKE ? OR type LIKE ?) LIMIT 20");
            $stmt->execute([$like, $like]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['type'] = 'Graphics Card';
                $row['description'] = $row['name'];
                $results[] = $row;
            }
            break;
            
        case 'HDD':
            // HDDs uses quantity > 0 (no status column)
            $stmt = $conn->prepare("SELECT id, CONCAT(type, ' ', storage) as name, storage as specs, price FROM hdds WHERE quantity > 0 AND (id LIKE ? OR type LIKE ? OR storage LIKE ?) LIMIT 20");
            $stmt->execute([$like, $like, $like]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['type'] = 'HDD';
                $row['description'] = $row['name'];
                $results[] = $row;
            }
            break;
            
        case 'RAM/SSD':
            // RAM/SSD uses quantity > 0 (no status column)
            $stmt = $conn->prepare("SELECT id, CONCAT(category, ' ', type, ' ', storage, 'GB') as name, CONCAT(storage, 'GB') as specs, price FROM rams_ssds WHERE quantity > 0 AND (id LIKE ? OR category LIKE ? OR type LIKE ?) LIMIT 20");
            $stmt->execute([$like, $like, $like]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['type'] = 'RAM/SSD';
                $row['description'] = $row['name'];
                $results[] = $row;
            }
            break;
            
        case 'Charger':
            // Chargers uses quantity > 0 (no status column)
            $stmt = $conn->prepare("SELECT id, charger_type as name, charger_condition as specs, price FROM chargers WHERE quantity > 0 AND (id LIKE ? OR charger_type LIKE ?) LIMIT 20");
            $stmt->execute([$like, $like]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['type'] = 'Charger';
                $row['description'] = $row['name'];
                $results[] = $row;
            }
            break;
            
        default:
            // Search all categories - limit to 5 each
            // Devices
            $stmt = $conn->prepare("SELECT serial_number as id, model_name as name, CONCAT(processor, ' | ', ram, 'GB RAM | ', storage_type, ' ', storage_capacity, 'GB') as specs, price, 'Device' as type FROM devices WHERE status = 'In Stock' AND (serial_number LIKE ? OR model_name LIKE ?) LIMIT 5");
            $stmt->execute([$like, $like]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['description'] = $row['name'];
                $results[] = $row;
            }
            
            // Monitors
            $stmt = $conn->prepare("SELECT serial_number as id, model_name as name, CONCAT(size_inches, ' inch') as specs, price, 'Monitor' as type FROM monitors WHERE status = 'In Stock' AND (serial_number LIKE ? OR model_name LIKE ?) LIMIT 5");
            $stmt->execute([$like, $like]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['description'] = $row['name'];
                $results[] = $row;
            }
            
            // Printers
            $stmt = $conn->prepare("SELECT serial_number as id, model_name as name, 'N/A' as specs, price, 'Printer' as type FROM printers WHERE status = 'In Stock' AND (serial_number LIKE ? OR model_name LIKE ?) LIMIT 5");
            $stmt->execute([$like, $like]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['description'] = $row['name'];
                $results[] = $row;
            }
            
            // Smartboards
            $stmt = $conn->prepare("SELECT serial_number as id, model as name, CONCAT(size_inches, ' inch') as specs, price, 'Smartboard' as type FROM smartboards WHERE status = 'instock' AND (serial_number LIKE ? OR model LIKE ?) LIMIT 5");
            $stmt->execute([$like, $like]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['description'] = $row['name'];
                $results[] = $row;
            }
            
            // UPS
            $stmt = $conn->prepare("SELECT serial_number as id, model as name, CONCAT(capacity, ' VA') as specs, price, 'UPS' as type FROM ups WHERE status = 'instock' AND (serial_number LIKE ? OR model LIKE ?) LIMIT 5");
            $stmt->execute([$like, $like]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['description'] = $row['name'];
                $results[] = $row;
            }
            
            // Phones
            $stmt = $conn->prepare("SELECT serial_number as id, CONCAT(brand, ' ', model) as name, CONCAT(ram, 'GB RAM | ', storage_capacity, 'GB') as specs, price, 'Phone' as type FROM phones WHERE status = 'instock' AND (serial_number LIKE ? OR brand LIKE ? OR model LIKE ?) LIMIT 5");
            $stmt->execute([$like, $like, $like]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['description'] = $row['name'];
                $results[] = $row;
            }
            
            // Accessories
            $stmt = $conn->prepare("SELECT id, name, 'Qty available' as specs, price, 'Accessory' as type FROM accessories WHERE status = 'instock' AND (id LIKE ? OR name LIKE ?) LIMIT 5");
            $stmt->execute([$like, $like]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['description'] = $row['name'];
                $results[] = $row;
            }
            
            // Graphics Cards
            $stmt = $conn->prepare("SELECT id, type as name, CONCAT(storage_capacity, 'GB') as specs, price, 'Graphics Card' as type FROM graphic_cards WHERE status = 'instock' AND (id LIKE ? OR type LIKE ?) LIMIT 5");
            $stmt->execute([$like, $like]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['description'] = $row['name'];
                $results[] = $row;
            }
            
            // HDDs
            $stmt = $conn->prepare("SELECT id, CONCAT(type, ' ', storage) as name, storage as specs, price, 'HDD' as type FROM hdds WHERE quantity > 0 AND (id LIKE ? OR type LIKE ? OR storage LIKE ?) LIMIT 5");
            $stmt->execute([$like, $like, $like]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['description'] = $row['name'];
                $results[] = $row;
            }
            
            // RAM/SSD
            $stmt = $conn->prepare("SELECT id, CONCAT(category, ' ', type, ' ', storage, 'GB') as name, CONCAT(storage, 'GB') as specs, price, 'RAM/SSD' as type FROM rams_ssds WHERE quantity > 0 AND (id LIKE ? OR category LIKE ? OR type LIKE ?) LIMIT 5");
            $stmt->execute([$like, $like, $like]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['description'] = $row['name'];
                $results[] = $row;
            }
            
            // Chargers
            $stmt = $conn->prepare("SELECT id, charger_type as name, charger_condition as specs, price, 'Charger' as type FROM chargers WHERE quantity > 0 AND (id LIKE ? OR charger_type LIKE ?) LIMIT 5");
            $stmt->execute([$like, $like]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['description'] = $row['name'];
                $results[] = $row;
            }
    }
    
    echo json_encode($results);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
exit;
?>