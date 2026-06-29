<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if (!in_array($_SESSION['role'], ['sales', 'cashier', 'manager', 'super_admin'])) {
    die("ACCESS DENIED.");
}

$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Get user's branch (for cashier)
$user_branch = null;
if ($user_role === 'cashier') {
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
}

$search = trim($_GET['search'] ?? '');
$filter_branch = $_GET['filter_branch'] ?? '';
$filter_salesperson = $_GET['filter_salesperson'] ?? '';

$sql = "SELECT c.*, u.full_name AS salesperson_name 
        FROM clients c
        LEFT JOIN users u ON c.sales_person = u.id
        WHERE 1=1";
$params = [];

if ($user_role === 'sales') {
    $sql .= " AND c.sales_person = ?";
    $params[] = $user_id;
} elseif ($user_role === 'cashier') {
    $sql .= " AND c.branch = ?";
    $params[] = $user_branch;
}

if ($user_role === 'manager' || $user_role === 'super_admin') {
    if (!empty($filter_branch)) {
        $sql .= " AND c.branch = ?";
        $params[] = $filter_branch;
    }
    if (!empty($filter_salesperson)) {
        $sql .= " AND c.sales_person = ?";
        $params[] = $filter_salesperson;
    }
}

if (!empty($search)) {
    $sql .= " AND (c.client_name LIKE ? OR c.client_phone LIKE ? OR c.client_email LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY c.client_name ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Output only the table HTML (no wrapper)
if (empty($clients)): ?>
    <div class="empty-state">
        <i class="fas fa-users"></i>
        <p>No clients found matching your criteria.</p>
    </div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Client Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>P.O. Box</th>
                <th>Salesperson</th>
                <th>Branch</th>
                <th>Added</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($clients as $client): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($client['client_name']) ?></strong></td>
                    <td><?= htmlspecialchars($client['client_phone'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($client['client_email'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($client['client_box'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($client['salesperson_name'] ?? 'Unassigned') ?></td>
                    <td><span class="badge"><?= htmlspecialchars($client['branch'] ?? '—') ?></span></td>
                    <td><?= date('M j, Y', strtotime($client['date_added'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>