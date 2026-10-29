<?php
session_set_cookie_params([
    'path' => '/xampp/myproject/WT/',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

require_once __DIR__ . '/config/db.php';

$role = strtolower((string)($_SESSION['role'] ?? ($_SESSION['user']['role'] ?? '')));
$username = (string)($_SESSION['username'] ?? ($_SESSION['user']['username'] ?? 'Unknown'));
$userId = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
$loginUrl = '../pr10/login.php';

if ($userId < 1 || $role !== 'admin') {
    http_response_code($userId < 1 ? 401 : 403);
    if ($userId < 1) {
        header('Location: ' . $loginUrl . '?denied=1');
        exit;
    }
    exit('Access denied. This dashboard is available to administrator accounts only. <a href="../pr10/login.php">Return to login</a>');
}

function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function tableExists($conn, $table) {
    $table = str_replace(
        ['\\', '%', '_'],
        ['\\\\', '\\%', '\\_'],
        $table
    );

    $stmt = $conn->prepare(
        "SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = ?"
    );

    $stmt->bind_param("s", $table);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    return $count > 0;
}
function columnExists($conn, $table, $column) {
    $stmt = $conn->prepare(
        "SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = ?
         AND COLUMN_NAME = ?"
    );

    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    return $count > 0;
}
function audit(mysqli $conn, int $userId, string $username, string $action, string $details = ''): void {
    $stmt = $conn->prepare('INSERT INTO audit_logs (user_id, username, action, details, ip_address) VALUES (?, ?, ?, ?, ?)');
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
    $stmt->bind_param('issss', $userId, $username, $action, $details, $ip);
    $stmt->execute();
}

// Create audit log table if the setup SQL has not yet been imported.
$dashboardConn->query("CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL DEFAULT 0,
    username VARCHAR(100) NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Optional extension: maintain active/inactive status for users.
if (!columnExists($authConn, 'users', 'status')) {
    $authConn->query("ALTER TABLE users ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active'");
}

$flash = '';
$flashType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $flash = 'Security token expired. Refresh the page and try again.';
        $flashType = 'danger';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'toggle_user') {
            $targetId = filter_input(INPUT_POST, 'target_id', FILTER_VALIDATE_INT);
            if (!$targetId || $targetId === $userId) {
                $flash = 'Select a valid user. You cannot change your own status.';
                $flashType = 'warning';
            } else {
                $stmt = $authConn->prepare("SELECT id, username, status FROM users WHERE id = ?");
                $stmt->bind_param('i', $targetId);
                $stmt->execute();
                $target = $stmt->get_result()->fetch_assoc();
                if (!$target) {
                    $flash = 'User not found.';
                    $flashType = 'danger';
                } else {
                    $newStatus = $target['status'] === 'active' ? 'inactive' : 'active';
                    $stmt = $authConn->prepare('UPDATE users SET status = ? WHERE id = ?');
                    $stmt->bind_param('si', $newStatus, $targetId);
                    $stmt->execute();
                    audit($dashboardConn, $userId, $username, 'USER_STATUS_CHANGED', 'User ' . $target['username'] . ' set to ' . $newStatus);
                    $flash = 'User status updated to ' . $newStatus . '.';
                }
            }
        }
        if ($action === 'clear_audit') {
            audit($dashboardConn, $userId, $username, 'AUDIT_VIEWED', 'Admin opened audit log section');
            $flash = 'Audit viewing action recorded.';
        }
    }
}

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
audit($dashboardConn, $userId, $username, 'DASHBOARD_VIEWED', 'Admin dashboard opened');

// Counts are loaded dynamically. Missing optional tables are handled gracefully.
$userCount = 0;
$activeCount = 0;
$eventCount = 0;
$studentCount = 0;
$studentSource = 'No students table found';
$latestUsers = [];
$latestAudit = [];

if (tableExists($authConn, 'users')) {
    $userCount = (int)$authConn->query('SELECT COUNT(*) AS total FROM users')->fetch_assoc()['total'];
    $activeCount = (int)$authConn->query("SELECT COUNT(*) AS total FROM users WHERE status = 'active'")->fetch_assoc()['total'];
    $dateExpr = columnExists($authConn, 'users', 'created_at') ? 'created_at' : "NULL AS created_at";
    $latestUsers = $authConn->query("SELECT id, username, role, status, {$dateExpr} FROM users ORDER BY id DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
}
if (tableExists($dashboardConn, 'events')) {
    $eventCount = (int)$dashboardConn->query('SELECT COUNT(*) AS total FROM events')->fetch_assoc()['total'];
}
if (tableExists($dashboardConn, 'students')) {
    $studentCount = (int)$dashboardConn->query('SELECT COUNT(*) AS total FROM students')->fetch_assoc()['total'];
    $studentSource = 'studenthub.students';
} else {
    // The earlier practical setup may store students in college1 instead.
    try {
        $collegeConn = db_connect('college1');
        if (tableExists($collegeConn, 'students')) {
            $studentCount = (int)$collegeConn->query('SELECT COUNT(*) AS total FROM students')->fetch_assoc()['total'];
            $studentSource = 'college1.students';
        }
        $collegeConn->close();
    } catch (Throwable $e) { /* Optional database */ }
}
$latestAudit = $dashboardConn->query('SELECT username, action, details, created_at FROM audit_logs ORDER BY id DESC LIMIT 8')->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>StudentHub | Admin Dashboard - Practical 14</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{--navy:#172554;--blue:#3157a4;--bg:#f4f7fb}body{background:var(--bg);font-family:system-ui,-apple-system,Segoe UI,sans-serif;color:#1f2937}.navbar{background:var(--navy)}.navbar-brand{font-weight:750;letter-spacing:.2px}.page-title{font-weight:750;color:#172554}.subtle{color:#64748b}.stat-card{border:0;border-radius:16px;box-shadow:0 5px 18px rgba(15,23,42,.06);height:100%}.stat-label{font-size:.9rem;color:#64748b}.stat-number{font-size:2rem;font-weight:750;color:#172554}.section-card{border:0;border-radius:16px;box-shadow:0 5px 18px rgba(15,23,42,.05)}.section-heading{font-weight:700;color:#172554}.table thead th{font-size:.84rem;color:#64748b;white-space:nowrap}.pill{background:#e0e7ff;color:#3730a3;border-radius:999px;padding:.3rem .65rem;font-size:.8rem}.nav-link{color:#dbeafe}.nav-link:hover{color:#fff}.action-cell{min-width:120px}
</style></head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark"><div class="container-fluid px-lg-4"><a class="navbar-brand" href="dashboard.php">StudentHub Admin</a><button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu"><span class="navbar-toggler-icon"></span></button><div class="collapse navbar-collapse" id="navMenu"><ul class="navbar-nav me-auto"><li class="nav-item"><a class="nav-link active" href="#overview">Overview</a></li><li class="nav-item"><a class="nav-link" href="#users">Users</a></li><li class="nav-item"><a class="nav-link" href="#audit">Audit Log</a></li><li class="nav-item"><a class="nav-link" href="../StudentHub-Practical-12/Pages/events_manage.php">Event Management</a></li></ul><span class="navbar-text text-white me-lg-3">Signed in: <?= h($username) ?> <span class="badge text-bg-warning ms-1">ADMIN</span></span><a class="btn btn-outline-light btn-sm mt-2 mt-lg-0" href="../pr10/login.php">Login / Logout</a></div></div></nav>
<main class="container-fluid px-lg-4 py-4">
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4"><div><p class="text-uppercase small fw-bold text-primary mb-1">Administration</p><h1 class="h2 page-title mb-1">Dashboard Overview</h1><p class="subtle mb-0">Monitor users, students, events, and important administrative actions.</p></div><div class="text-secondary small">Updated <?= date('d M Y, h:i A') ?></div></div>
<?php if ($flash !== ''): ?><div class="alert alert-<?= h($flashType) ?> alert-dismissible fade show" role="alert"><?= h($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
<section id="overview" class="row g-3 mb-4">
<div class="col-12 col-sm-6 col-xl-3"><div class="card stat-card"><div class="card-body p-4"><div class="stat-label">Registered Users</div><div class="stat-number"><?= number_format($userCount) ?></div><div class="small subtle">Accounts in secure_login.users</div></div></div></div>
<div class="col-12 col-sm-6 col-xl-3"><div class="card stat-card"><div class="card-body p-4"><div class="stat-label">Students</div><div class="stat-number"><?= number_format($studentCount) ?></div><div class="small subtle"><?= h($studentSource) ?></div></div></div></div>
<div class="col-12 col-sm-6 col-xl-3"><div class="card stat-card"><div class="card-body p-4"><div class="stat-label">Events</div><div class="stat-number"><?= number_format($eventCount) ?></div><div class="small subtle">Records in studenthub.events</div></div></div></div>
<div class="col-12 col-sm-6 col-xl-3"><div class="card stat-card"><div class="card-body p-4"><div class="stat-label">Active Users</div><div class="stat-number"><?= number_format($activeCount) ?></div><div class="small subtle">Active account status</div></div></div></div>
</section>
<section id="users" class="card section-card mb-4"><div class="card-body p-3 p-lg-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 section-heading mb-1">Latest Registrations / Accounts</h2><p class="small subtle mb-0">Latest-created accounts are listed by descending user ID.</p></div><span class="pill">Role-based access enabled</span></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>User ID</th><th>Username</th><th>Role</th><th>Status</th><th>Registered</th><th class="action-cell">Admin Action</th></tr></thead><tbody>
<?php if (!$latestUsers): ?><tr><td colspan="6" class="text-center text-secondary py-4">No user accounts found.</td></tr><?php else: foreach ($latestUsers as $u): ?><tr><td>#<?= (int)$u['id'] ?></td><td class="fw-semibold"><?= h($u['username']) ?><?= (int)$u['id'] === $userId ? ' <span class="badge text-bg-primary">You</span>' : '' ?></td><td><span class="badge text-bg-light border"><?= h($u['role']) ?></span></td><td><span class="badge text-bg-<?= $u['status'] === 'active' ? 'success' : 'secondary' ?>"><?= h(ucfirst($u['status'])) ?></span></td><td><?= !empty($u['created_at']) ? h(date('d M Y, h:i A', strtotime($u['created_at']))) : '<span class="text-secondary">Not recorded</span>' ?></td><td><?php if ((int)$u['id'] !== $userId): ?><form method="post" onsubmit="return confirm('Change this user account status?')"><input type="hidden" name="csrf_token" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="toggle_user"><input type="hidden" name="target_id" value="<?= (int)$u['id'] ?>"><button class="btn btn-sm btn-outline-<?= $u['status'] === 'active' ? 'danger' : 'success' ?>" type="submit"><?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button></form><?php else: ?><span class="small text-secondary">Current admin</span><?php endif; ?></td></tr><?php endforeach; endif; ?>
</tbody></table></div></div></section>
<section id="audit" class="card section-card"><div class="card-body p-3 p-lg-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 section-heading mb-1">Recent Audit Log</h2><p class="small subtle mb-0">Dashboard visits and account status changes are recorded automatically.</p></div><form method="post"><input type="hidden" name="csrf_token" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="clear_audit"><button class="btn btn-sm btn-outline-primary" type="submit">Record Audit View</button></form></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Time</th><th>Admin / User</th><th>Action</th><th>Details</th></tr></thead><tbody><?php if (!$latestAudit): ?><tr><td colspan="4" class="text-center text-secondary py-4">No audit actions yet.</td></tr><?php else: foreach ($latestAudit as $log): ?><tr><td class="text-nowrap"><?= h(date('d M Y, h:i A', strtotime($log['created_at']))) ?></td><td><?= h($log['username']) ?></td><td><span class="badge text-bg-light border"><?= h($log['action']) ?></span></td><td><?= h($log['details'] ?? '') ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div></section>
<footer class="text-center small text-secondary py-4">StudentHub · Practical 14 · Role-Based Admin Dashboard and Audit Logging</footer>
</main><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>
