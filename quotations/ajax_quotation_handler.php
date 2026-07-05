<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Set JSON header
header('Content-Type: application/json');

// Check authentication
if (!in_array($_SESSION['role'], ['sales', 'super_admin', 'manager', 'technician'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$user_branch = $_SESSION['branch'] ?? 'KIMATHI';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Generate quotation number
function generateQuotationNumber($conn) {
    $stmt = $conn->query("SELECT quotation_number FROM quotations ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetch(PDO::FETCH_COLUMN);
    if ($last) {
        $num = (int) substr($last, 2) + 1;
    } else {
        $num = 1;
    }
    return 'MC' . str_pad($num, 2, '0', STR_PAD_LEFT);
}

try {
    switch ($action) {
        // Search clients
        case 'search_clients':
            $q = trim($_GET['q'] ?? '');
            if (strlen($q) < 1) {
                echo json_encode([]);
                break;
            }
            $stmt = $conn->prepare("SELECT id, client_name, client_phone, client_box, client_email FROM clients WHERE client_name LIKE ? OR client_phone LIKE ? ORDER BY client_name LIMIT 20");
            $like = "%$q%";
            $stmt->execute([$like, $like]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
        
        // Search items
        case 'search_items':
            $q = trim($_GET['q'] ?? '');
            $category = trim($_GET['category'] ?? '');
            if (strlen($q) < 1) {
                echo json_encode([]);
                break;
            }
            
            $results = [];
            $like = "%$q%";
            
            // Search based on category
            switch ($category) {
                case 'Device':
                    $stmt = $conn->prepare("SELECT serial_number as id, model_name as name, CONCAT(processor, ' | ', ram, 'GB RAM | ', storage_type, ' ', storage_capacity, 'GB') as specs, price FROM devices WHERE status = 'In Stock' AND (serial_number LIKE ? OR model_name LIKE ?) LIMIT 20");
                    $stmt->execute([$like, $like]);
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $row['type'] = 'Device';
                        $results[] = $row;
                    }
                    break;
                    
                case 'Monitor':
                    $stmt = $conn->prepare("SELECT serial_number as id, model_name as name, CONCAT(size_inches, ' inch') as specs, price FROM monitors WHERE status = 'In Stock' AND (serial_number LIKE ? OR model_name LIKE ?) LIMIT 20");
                    $stmt->execute([$like, $like]);
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $row['type'] = 'Monitor';
                        $results[] = $row;
                    }
                    break;
                    
                case 'Accessory':
                    $stmt = $conn->prepare("SELECT id, name, 'Qty available' as specs, price FROM accessories WHERE status = 'instock' AND (id LIKE ? OR name LIKE ?) LIMIT 20");
                    $stmt->execute([$like, $like]);
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $row['type'] = 'Accessory';
                        $results[] = $row;
                    }
                    break;
                    
                default:
                    // Search all categories - limit to 5 each
                    // Devices
                    $stmt = $conn->prepare("SELECT serial_number as id, model_name as name, CONCAT(processor, ' | ', ram, 'GB RAM | ', storage_type, ' ', storage_capacity, 'GB') as specs, price, 'Device' as type FROM devices WHERE status = 'In Stock' AND (serial_number LIKE ? OR model_name LIKE ?) LIMIT 5");
                    $stmt->execute([$like, $like]);
                    $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
                    
                    // Monitors
                    $stmt = $conn->prepare("SELECT serial_number as id, model_name as name, CONCAT(size_inches, ' inch') as specs, price, 'Monitor' as type FROM monitors WHERE status = 'In Stock' AND (serial_number LIKE ? OR model_name LIKE ?) LIMIT 5");
                    $stmt->execute([$like, $like]);
                    $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
                    
                    // Accessories
                    $stmt = $conn->prepare("SELECT id, name, 'Qty available' as specs, price, 'Accessory' as type FROM accessories WHERE status = 'instock' AND (id LIKE ? OR name LIKE ?) LIMIT 5");
                    $stmt->execute([$like, $like]);
                    $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
            }
            
            echo json_encode($results);
            break;
        
        // Create client and quotation
        case 'create_quotation':
            $client_name = trim($_POST['client_name'] ?? '');
            $client_phone = trim($_POST['client_phone'] ?? '');
            $client_box = trim($_POST['client_box'] ?? '');
            $client_email = trim($_POST['client_email'] ?? '');
            $payment_due = $_POST['payment_due'] ?? date('Y-m-d', strtotime('+7 days'));
            $notes = trim($_POST['notes'] ?? '');
            
            if (empty($client_name)) {
                echo json_encode(['error' => 'Client name is required']);
                break;
            }
            
            // Check if client exists
            $stmt = $conn->prepare("SELECT id FROM clients WHERE client_name = ?");
            $stmt->execute([$client_name]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $client_id = $existing['id'];
            } else {
                $stmt = $conn->prepare("INSERT INTO clients (client_name, client_phone, client_box, client_email, sales_person, branch) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$client_name, $client_phone, $client_box, $client_email, $user_id, $user_branch]);
                $client_id = $conn->lastInsertId();
            }
            
            // Create quotation
            $qnum = generateQuotationNumber($conn);
            $quotation_date = date('Y-m-d');
            
            $stmt = $conn->prepare("INSERT INTO quotations (quotation_number, client_name, client_phone, client_box, client_email, quotation_date, payment_due_date, notes, user_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')");
            $stmt->execute([$qnum, $client_name, $client_phone, $client_box, $client_email, $quotation_date, $payment_due, $notes, $user_id]);
            $quotation_id = $conn->lastInsertId();
            
            $_SESSION['quotation_id'] = $quotation_id;
            
            echo json_encode(['success' => true, 'quotation_id' => $quotation_id]);
            break;
        
        // Add item to quotation
        case 'add_item':
            $quotation_id = (int)($_POST['quotation_id'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $specs = trim($_POST['specs'] ?? '');
            $quantity = (int)($_POST['quantity'] ?? 1);
            $unit_price = (float)($_POST['unit_price'] ?? 0);
            $vat_rate = (float)($_POST['vat_rate'] ?? 0);
            
            if (!$quotation_id) {
                echo json_encode(['error' => 'No quotation found']);
                break;
            }
            
            if (empty($description) || $quantity < 1 || $unit_price <= 0) {
                echo json_encode(['error' => 'Please fill all required fields']);
                break;
            }
            
            $total_price = $quantity * $unit_price;
            $vat_amount = $total_price * ($vat_rate / 100);
            $total_with_vat = $total_price + $vat_amount;
            
            $stmt = $conn->prepare("INSERT INTO quotation_items (quotation_id, item_type, item_id, description, specs, quantity, unit_price, total_price, vat_rate, vat_amount, total_with_vat) VALUES (?, 'manual', '', ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$quotation_id, $description, $specs, $quantity, $unit_price, $total_price, $vat_rate, $vat_amount, $total_with_vat]);
            
            // Update totals
            $stmt = $conn->prepare("SELECT SUM(total_price) as subtotal, SUM(vat_amount) as vat, SUM(total_with_vat) as grand_total FROM quotation_items WHERE quotation_id = ?");
            $stmt->execute([$quotation_id]);
            $totals = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $conn->prepare("UPDATE quotations SET subtotal = ?, vat = ?, grand_total = ? WHERE id = ?");
            $stmt->execute([$totals['subtotal'] ?? 0, $totals['vat'] ?? 0, $totals['grand_total'] ?? 0, $quotation_id]);
            
            echo json_encode(['success' => true, 'message' => 'Item added successfully']);
            break;
        
        // Get items
        case 'get_items':
            $quotation_id = (int)($_GET['quotation_id'] ?? 0);
            if (!$quotation_id) {
                echo json_encode(['items' => [], 'totals' => ['subtotal' => 0, 'vat' => 0, 'grand_total' => 0]]);
                break;
            }
            
            $stmt = $conn->prepare("SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY id ASC");
            $stmt->execute([$quotation_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $conn->prepare("SELECT subtotal, vat, grand_total FROM quotations WHERE id = ?");
            $stmt->execute([$quotation_id]);
            $totals = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['items' => $items, 'totals' => $totals ?? ['subtotal' => 0, 'vat' => 0, 'grand_total' => 0]]);
            break;
        
        // Remove item
        case 'remove_item':
            $item_id = (int)($_GET['item_id'] ?? 0);
            $quotation_id = (int)($_GET['quotation_id'] ?? 0);
            
            if (!$item_id || !$quotation_id) {
                echo json_encode(['error' => 'Invalid request']);
                break;
            }
            
            $stmt = $conn->prepare("DELETE FROM quotation_items WHERE id = ? AND quotation_id = ?");
            $stmt->execute([$item_id, $quotation_id]);
            
            // Update totals
            $stmt = $conn->prepare("SELECT SUM(total_price) as subtotal, SUM(vat_amount) as vat, SUM(total_with_vat) as grand_total FROM quotation_items WHERE quotation_id = ?");
            $stmt->execute([$quotation_id]);
            $totals = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $conn->prepare("UPDATE quotations SET subtotal = ?, vat = ?, grand_total = ? WHERE id = ?");
            $stmt->execute([$totals['subtotal'] ?? 0, $totals['vat'] ?? 0, $totals['grand_total'] ?? 0, $quotation_id]);
            
            echo json_encode(['success' => true]);
            break;
        
        // Clear all items
        case 'clear_items':
            $quotation_id = (int)($_GET['quotation_id'] ?? 0);
            
            if (!$quotation_id) {
                echo json_encode(['error' => 'Invalid request']);
                break;
            }
            
            $stmt = $conn->prepare("DELETE FROM quotation_items WHERE quotation_id = ?");
            $stmt->execute([$quotation_id]);
            
            $stmt = $conn->prepare("UPDATE quotations SET subtotal = 0, vat = 0, grand_total = 0 WHERE id = ?");
            $stmt->execute([$quotation_id]);
            
            echo json_encode(['success' => true]);
            break;
        
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
exit;
?>