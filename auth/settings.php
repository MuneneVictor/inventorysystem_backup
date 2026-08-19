<?php
date_default_timezone_set('Africa/Nairobi');
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once "../includes/header.php";

$role = $_SESSION['role'] ?? '';
if ($role !== 'super_admin') {
    http_response_code(403);
    die("Access denied! Only Super Administrators can manage login access settings.");
}

$defaults = [
    'restrictions_enabled' => 1,
    'blocked_days' => 'sunday',
    'enforce_working_hours' => 1,
    'work_start_time' => '08:00:00',
    'work_end_time' => '18:00:00',
    'timezone' => 'Africa/Nairobi',
    'blocked_day_message' => 'The system is closed today. Please log in on the next working day.',
    'outside_hours_message' => 'The system is currently outside working hours. Please try again during the allowed login period.'
];

function loadLoginSettings(PDO $conn, array $defaults): array {
    try {
        $stmt = $conn->query("SELECT * FROM login_access_settings WHERE id = 1 LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? array_merge($defaults, $row) : $defaults;
    } catch (Throwable $e) {
        return $defaults;
    }
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Security validation failed. Please refresh and try again.";
    } else {
        $restrictionsEnabled = isset($_POST['restrictions_enabled']) ? 1 : 0;
        $enforceHours = isset($_POST['enforce_working_hours']) ? 1 : 0;

        $allowedDays = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
        $blockedDaysInput = $_POST['blocked_days'] ?? [];
        if (!is_array($blockedDaysInput)) {
            $blockedDaysInput = [];
        }
        $blockedDays = array_values(array_intersect($allowedDays, array_map('strtolower', $blockedDaysInput)));
        $blockedDaysCsv = implode(',', $blockedDays);

        $workStart = trim($_POST['work_start_time'] ?? '08:00');
        $workEnd = trim($_POST['work_end_time'] ?? '18:00');
        $timezone = trim($_POST['timezone'] ?? 'Africa/Nairobi');
        $blockedDayMessage = trim($_POST['blocked_day_message'] ?? '');
        $outsideHoursMessage = trim($_POST['outside_hours_message'] ?? '');

        $validTime = static fn($v) => preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $v);

        if (!$validTime($workStart) || !$validTime($workEnd)) {
            $error = "Please enter valid working hours.";
        } elseif (!in_array($timezone, timezone_identifiers_list(), true)) {
            $error = "Please select a valid timezone.";
        } else {
            try {
                $sql = "INSERT INTO login_access_settings
                        (id, restrictions_enabled, blocked_days, enforce_working_hours,
                         work_start_time, work_end_time, timezone, blocked_day_message,
                         outside_hours_message, updated_by, updated_at)
                        VALUES
                        (1, :enabled, :blocked_days, :enforce_hours,
                         :start_time, :end_time, :timezone, :blocked_message,
                         :hours_message, :updated_by, NOW())
                        ON DUPLICATE KEY UPDATE
                         restrictions_enabled = VALUES(restrictions_enabled),
                         blocked_days = VALUES(blocked_days),
                         enforce_working_hours = VALUES(enforce_working_hours),
                         work_start_time = VALUES(work_start_time),
                         work_end_time = VALUES(work_end_time),
                         timezone = VALUES(timezone),
                         blocked_day_message = VALUES(blocked_day_message),
                         outside_hours_message = VALUES(outside_hours_message),
                         updated_by = VALUES(updated_by),
                         updated_at = NOW()";

                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    'enabled' => $restrictionsEnabled,
                    'blocked_days' => $blockedDaysCsv,
                    'enforce_hours' => $enforceHours,
                    'start_time' => $workStart . ':00',
                    'end_time' => $workEnd . ':00',
                    'timezone' => $timezone,
                    'blocked_message' => $blockedDayMessage,
                    'hours_message' => $outsideHoursMessage,
                    'updated_by' => (int)($_SESSION['user_id'] ?? 0)
                ]);

                try {
                    $logStmt = $conn->prepare(
                        "INSERT INTO activity_logs (user_id, action, details)
                         VALUES (:uid, :action, :details)"
                    );
                    $logStmt->execute([
                        'uid' => (int)($_SESSION['user_id'] ?? 0),
                        'action' => 'Login Access Settings Updated',
                        'details' => "Login restrictions updated. Blocked days: " .
                                     ($blockedDaysCsv ?: 'none') .
                                     "; Working hours: " .
                                     ($enforceHours ? "{$workStart}-{$workEnd}" : 'not enforced')
                    ]);
                } catch (Throwable $e) {
                    // Do not fail the setting save if activity logging fails.
                }

                $success = "Login access settings saved successfully.";
            } catch (Throwable $e) {
                $error = "Unable to save settings. Run the supplied database migration first.";
            }
        }
    }
}

$settings = loadLoginSettings($conn, $defaults);
$blocked = array_filter(array_map('trim', explode(',', strtolower((string)$settings['blocked_days']))));

require_once "../includes/sidebar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Login Access Settings | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary:#1a4b2a; --primary-light:#2a6b3a; --border:#e5e7eb;
            --muted:#6b7280; --bg:#f3f4f6; --danger:#991b1b;
        }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif; background:var(--bg); color:#1f2937; }
        .main-content { margin-left:260px; width:calc(100% - 260px); min-height:100vh; padding:2rem; }
        .page-header,.card { background:#fff; border:1px solid var(--border); border-radius:16px; box-shadow:0 1px 2px rgba(0,0,0,.05); }
        .page-header { padding:1.5rem 2rem; margin-bottom:1.5rem; }
        .page-header h1 { margin:0 0 .4rem; font-size:1.7rem; }
        .page-header h1 i { color:var(--primary); margin-right:.5rem; }
        .breadcrumb { color:var(--muted); font-size:.9rem; }
        .breadcrumb a { color:var(--primary); text-decoration:none; }
        .card { padding:1.5rem; margin-bottom:1.25rem; }
        .card h2 { margin:0 0 .4rem; font-size:1.1rem; }
        .help { color:var(--muted); font-size:.9rem; margin:0 0 1rem; }
        .notice { padding:1rem 1.1rem; border-radius:10px; margin-bottom:1rem; }
        .success { background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; }
        .error { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
        .super-admin-note { background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; }
        .switch-row { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; padding:1rem 0; border-bottom:1px solid var(--border); }
        .switch-row:last-child { border-bottom:0; }
        .switch-row input[type=checkbox] { width:20px; height:20px; accent-color:var(--primary); }
        .days { display:grid; grid-template-columns:repeat(4,minmax(120px,1fr)); gap:.75rem; margin-top:1rem; }
        .day { display:flex; align-items:center; gap:.55rem; border:1px solid var(--border); border-radius:10px; padding:.75rem; background:#fafafa; }
        .day input { accent-color:var(--primary); }
        .grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; }
        label.field { display:block; font-size:.85rem; font-weight:600; color:#374151; }
        input[type=time],input[type=text],textarea {
            width:100%; margin-top:.45rem; border:1px solid #d1d5db; border-radius:9px;
            padding:.7rem .8rem; font:inherit; background:#fff;
        }
        textarea { min-height:85px; resize:vertical; }
        .actions { display:flex; gap:.75rem; flex-wrap:wrap; }
        .btn { border:0; border-radius:9px; padding:.7rem 1rem; font-weight:600; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:.45rem; }
        .btn-primary { background:var(--primary); color:#fff; }
        .btn-primary:hover { background:var(--primary-light); }
        .btn-secondary { background:#fff; color:#374151; border:1px solid #d1d5db; }
        @media (max-width:1200px) {
            .main-content { margin-left:0!important; width:100%!important; padding:5rem 1rem 1rem!important; }
        }
        @media (max-width:700px) {
            .grid,.days { grid-template-columns:1fr; }
            .main-content { padding:4.5rem .75rem .75rem!important; }
            .page-header,.card { padding:1rem; }
        }
    </style>
</head>
<body>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-user-clock"></i> Login Access Settings</h1>
        <div class="breadcrumb">
            <a href="../dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <span> / </span>
            <a href="view_users.php">Users</a>
            <span> / Login Access Settings</span>
        </div>
    </div>

    <?php if ($success): ?><div class="notice success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="notice error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="notice super-admin-note">
        <strong><i class="fas fa-shield-alt"></i> Super Admin safety rule:</strong>
        Super Admin accounts are never blocked by Sunday or working-hours restrictions.
    </div>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="card">
            <h2>Master Control</h2>
            <p class="help">Turn scheduled login restrictions on or off for every non-Super-Admin account.</p>
            <div class="switch-row">
                <div>
                    <strong>Enable Login Restrictions</strong>
                    <div class="help">When disabled, active users can log in at any time.</div>
                </div>
                <input type="checkbox" name="restrictions_enabled" value="1" <?= (int)$settings['restrictions_enabled'] === 1 ? 'checked' : '' ?>>
            </div>
        </div>

        <div class="card">
            <h2>Blocked Days</h2>
            <p class="help">Select any day when non-Super-Admin users must not log in. Sunday is selected by default.</p>
            <div class="days">
                <?php foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day): ?>
                    <label class="day">
                        <input type="checkbox" name="blocked_days[]" value="<?= $day ?>" <?= in_array($day, $blocked, true) ? 'checked' : '' ?>>
                        <span><?= ucfirst($day) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <label class="field" style="margin-top:1rem;">
                Message shown on blocked days
                <textarea name="blocked_day_message"><?= htmlspecialchars($settings['blocked_day_message']) ?></textarea>
            </label>
        </div>

        <div class="card">
            <h2>Working Hours</h2>
            <div class="switch-row">
                <div>
                    <strong>Enforce Working Hours</strong>
                    <div class="help">Users will only be allowed to start or complete login inside the configured time window.</div>
                </div>
                <input type="checkbox" name="enforce_working_hours" value="1" <?= (int)$settings['enforce_working_hours'] === 1 ? 'checked' : '' ?>>
            </div>

            <div class="grid" style="margin-top:1rem;">
                <label class="field">
                    Login allowed from
                    <input type="time" name="work_start_time" value="<?= htmlspecialchars(substr((string)$settings['work_start_time'],0,5)) ?>" required>
                </label>
                <label class="field">
                    Login allowed until
                    <input type="time" name="work_end_time" value="<?= htmlspecialchars(substr((string)$settings['work_end_time'],0,5)) ?>" required>
                </label>
            </div>

            <label class="field" style="margin-top:1rem;">
                Timezone
                <input type="text" name="timezone" value="<?= htmlspecialchars($settings['timezone']) ?>" required>
            </label>

            <label class="field" style="margin-top:1rem;">
                Message shown outside working hours
                <textarea name="outside_hours_message"><?= htmlspecialchars($settings['outside_hours_message']) ?></textarea>
            </label>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Login Settings</button>
            <a href="view_users.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Users</a>
        </div>
    </form>
</div>
</body>
</html>
