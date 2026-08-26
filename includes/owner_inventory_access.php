<?php
/**
 * Access helper for the independent Iman Inventory / Iman's Hustle modules.
 *
 * Access rule:
 *   - Super Admin always has access.
 *   - Any other logged-in user must have an email listed in
 *     login_access_settings.owner_inventory_allowed_emails.
 *
 * The email list is managed from auth/settings.php.
 */

if (!function_exists('ownerInventoryAccessContext')) {
    function ownerInventoryAccessContext(PDO $conn): array
    {
        $role = (string)($_SESSION['role'] ?? '');
        $uid = (int)($_SESSION['user_id'] ?? 0);

        $stmt = $conn->prepare("SELECT email, branch FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$uid]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $email = strtolower(trim((string)($user['email'] ?? $_SESSION['email'] ?? '')));
        $branch = strtoupper(trim((string)($user['branch'] ?? '')));

        $allowedEmails = [];
        try {
            $q = $conn->query("SELECT owner_inventory_allowed_emails FROM login_access_settings WHERE id = 1 LIMIT 1");
            $raw = (string)($q->fetchColumn() ?: '');
            $parts = preg_split('/[\s,;]+/', strtolower($raw)) ?: [];
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part !== '' && filter_var($part, FILTER_VALIDATE_EMAIL)) {
                    $allowedEmails[] = $part;
                }
            }
            $allowedEmails = array_values(array_unique($allowedEmails));
        } catch (Throwable $e) {
            // If migration was not run yet, Super Admin can still enter.
            $allowedEmails = [];
        }

        $hasAccess =
            $role === 'super_admin' ||
            ($email !== '' && in_array($email, $allowedEmails, true));

        return [
            'role' => $role,
            'user_id' => $uid,
            'email' => $email,
            'branch' => $branch,
            'allowed_emails' => $allowedEmails,
            'has_access' => $hasAccess,
        ];
    }

    function requireOwnerInventoryAccess(PDO $conn): array
    {
        $ctx = ownerInventoryAccessContext($conn);
        if (!$ctx['has_access']) {
            http_response_code(403);
            die("You don't have permission to view this inventory.");
        }
        return $ctx;
    }
}
