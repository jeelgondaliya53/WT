<?php
// Share the PHP session across the WT practical folders.
session_set_cookie_params([
    'path' => '/xampp/myproject/WT/',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
require_once __DIR__ . '/../config/db.php';

/*
 * ACCESS CONTROL:
 * This standalone example expects $_SESSION['role'] = 'admin'.
 * If your StudentHub login stores the role elsewhere, change this check
 * to match your existing login/session structure.
 */
$role = $_SESSION['role'] ?? ($_SESSION['user']['role'] ?? '');
if (strtolower((string)$role) !== 'admin') {
    http_response_code(403);
    exit('Access denied. Please log in with an administrator account.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$uploadDir = dirname(__DIR__) . '/uploads/';
$uploadUrl = '../uploads/';
$maxBytes = 2 * 1024 * 1024; // 2 MB
$allowedMime = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp'
];

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectWithMessage(string $message, string $type = 'success'): never {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header('Location: events_manage.php');
    exit;
}

function validateCsrf(): void {
    $submitted = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';
    if (!is_string($submitted) || !is_string($stored) || !hash_equals($stored, $submitted)) {
        redirectWithMessage('Invalid form token. Refresh the page and try again.', 'danger');
    }
}

function savePoster(array $file, string $uploadDir, array $allowedMime, int $maxBytes): ?string {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The poster upload failed. Please try again.');
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('Poster must be 2 MB or smaller.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid upload.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowedMime[$mime])) {
        throw new RuntimeException('Invalid file type. Upload JPG, PNG, or WEBP only.');
    }

    // Verify that the file can be decoded as an image, not just named like one.
    if (@getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('The uploaded file is not a valid image.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowedMime[$mime];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        throw new RuntimeException('Could not save the poster. Check the uploads folder permissions.');
    }
    return $filename;
}

function removePoster(?string $filename, string $uploadDir): void {
    if ($filename && basename($filename) === $filename) {
        $path = $uploadDir . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

$flashMessage = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$editEvent = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'delete') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id || $id < 1) {
                throw new RuntimeException('Invalid event ID.');
            }

            $stmt = $conn->prepare('SELECT poster FROM events WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $event = $stmt->get_result()->fetch_assoc();
            if (!$event) {
                throw new RuntimeException('Event not found.');
            }

            $stmt = $conn->prepare('DELETE FROM events WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            removePoster($event['poster'] ?? null, $uploadDir);
            redirectWithMessage('Event deleted successfully.');
        }

        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $eventDate = trim($_POST['event_date'] ?? '');
            $category = trim($_POST['category'] ?? 'General');
            $status = $_POST['status'] ?? 'Open';

            if ($title === '' || mb_strlen($title) > 150) {
                throw new RuntimeException('Enter an event title (maximum 150 characters).');
            }
            if ($description === '') {
                throw new RuntimeException('Enter an event description.');
            }
            $dateObj = DateTime::createFromFormat('Y-m-d', $eventDate);
            if (!$dateObj || $dateObj->format('Y-m-d') !== $eventDate) {
                throw new RuntimeException('Select a valid event date.');
            }
            if ($category === '' || mb_strlen($category) > 80) {
                throw new RuntimeException('Enter a category (maximum 80 characters).');
            }
            if (!in_array($status, ['Open', 'Closed'], true)) {
                throw new RuntimeException('Select a valid event status.');
            }

            $newPoster = savePoster($_FILES['poster'] ?? [], $uploadDir, $allowedMime, $maxBytes);

            if ($id > 0) {
                $stmt = $conn->prepare('SELECT poster FROM events WHERE id = ?');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $old = $stmt->get_result()->fetch_assoc();
                if (!$old) {
                    if ($newPoster) removePoster($newPoster, $uploadDir);
                    throw new RuntimeException('Event not found.');
                }

                if ($newPoster) {
                    $stmt = $conn->prepare('UPDATE events SET title=?, description=?, event_date=?, category=?, status=?, poster=? WHERE id=?');
                    $stmt->bind_param('ssssssi', $title, $description, $eventDate, $category, $status, $newPoster, $id);
                } else {
                    $stmt = $conn->prepare('UPDATE events SET title=?, description=?, event_date=?, category=?, status=? WHERE id=?');
                    $stmt->bind_param('sssssi', $title, $description, $eventDate, $category, $status, $id);
                }
                $stmt->execute();
                if ($newPoster) removePoster($old['poster'] ?? null, $uploadDir);
                redirectWithMessage('Event updated successfully.');
            } else {
                $stmt = $conn->prepare('INSERT INTO events (title, description, event_date, category, status, poster) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('ssssss', $title, $description, $eventDate, $category, $status, $newPoster);
                $stmt->execute();
                redirectWithMessage('Event added successfully.');
            }
        }
    } catch (Throwable $ex) {
        $flashMessage = $ex->getMessage();
        $flashType = 'danger';
    }
}

if (isset($_GET['edit'])) {
    $id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    if ($id && $id > 0) {
        $stmt = $conn->prepare('SELECT * FROM events WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $editEvent = $stmt->get_result()->fetch_assoc();
        if (!$editEvent) {
            $flashMessage = 'Event not found.';
            $flashType = 'danger';
        }
    }
}

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$sql = 'SELECT * FROM events WHERE (title LIKE ? OR category LIKE ?)';
$like = '%' . $search . '%';
if (in_array($statusFilter, ['Open', 'Closed'], true)) {
    $sql .= ' AND status = ?';
}
$sql .= ' ORDER BY event_date DESC, id DESC';

$stmt = $conn->prepare($sql);
if (in_array($statusFilter, ['Open', 'Closed'], true)) {
    $stmt->bind_param('sss', $like, $like, $statusFilter);
} else {
    $stmt->bind_param('ss', $like, $like);
}
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$form = $editEvent ?? [
    'id' => 0, 'title' => '', 'description' => '',
    'event_date' => '', 'category' => 'General', 'status' => 'Open', 'poster' => null
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>StudentHub | Event Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f4f7fb; }
    .navbar-brand { font-weight:700; letter-spacing:.2px; }
    .page-header { background:linear-gradient(120deg,#172554,#2563eb); color:white; border-radius:1rem; }
    .card { border:0; border-radius:1rem; box-shadow:0 4px 18px rgba(15,23,42,.06); }
    .poster-thumb { width:72px;height:54px;object-fit:cover;border-radius:.5rem; }
    .form-label { font-weight:600; }
  </style>
</head>
<body>
<nav class="navbar navbar-dark" style="background:#172554">
  <div class="container">
    <a class="navbar-brand" href="../index.php">StudentHub</a>
    <span class="navbar-text text-white">Admin · Event Management</span>
  </div>
</nav>

<main class="container py-4">
  <section class="page-header p-4 p-md-5 mb-4">
    <h1 class="h3 mb-2">Event Management</h1>
    <p class="mb-0">Add, view, update and delete events with validated poster uploads.</p>
  </section>

  <?php if ($flashMessage !== ''): ?>
    <div class="alert alert-<?= e($flashType) ?> alert-dismissible fade show" role="alert">
      <?= e($flashMessage) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card p-4">
        <h2 class="h5 mb-3"><?= $editEvent ? 'Update Event' : 'Add New Event' ?></h2>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
          <div class="mb-3">
            <label class="form-label" for="title">Event title</label>
            <input class="form-control" id="title" name="title" maxlength="150" required value="<?= e($form['title']) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label" for="description">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3" required><?= e($form['description']) ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label" for="event_date">Event date</label>
            <input class="form-control" type="date" id="event_date" name="event_date" required value="<?= e($form['event_date']) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label" for="category">Category</label>
            <input class="form-control" id="category" name="category" maxlength="80" required value="<?= e($form['category']) ?>" placeholder="Technology, Cultural, Sports...">
          </div>
          <div class="mb-3">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
              <option value="Open" <?= $form['status'] === 'Open' ? 'selected' : '' ?>>Open</option>
              <option value="Closed" <?= $form['status'] === 'Closed' ? 'selected' : '' ?>>Closed</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="poster">Poster (JPG, PNG, WEBP; max 2 MB)</label>
            <input class="form-control" type="file" id="poster" name="poster" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
            <?php if (!empty($form['poster'])): ?>
              <div class="mt-2 small text-muted">Current poster:</div>
              <img class="poster-thumb mt-1" src="<?= e($uploadUrl . rawurlencode($form['poster'])) ?>" alt="Current event poster">
              <div class="form-text">Leave empty to keep the current poster.</div>
            <?php endif; ?>
          </div>
          <button class="btn btn-primary w-100" type="submit"><?= $editEvent ? 'Save Changes' : 'Add Event' ?></button>
          <?php if ($editEvent): ?>
            <a class="btn btn-outline-secondary w-100 mt-2" href="events_manage.php">Cancel edit</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <h2 class="h5 mb-0">Events (<?= count($events) ?>)</h2>
          <form method="get" class="d-flex flex-wrap gap-2">
            <input class="form-control" name="search" value="<?= e($search) ?>" placeholder="Search title/category" aria-label="Search">
            <select class="form-select" name="status" aria-label="Filter by status">
              <option value="">All statuses</option>
              <option value="Open" <?= $statusFilter === 'Open' ? 'selected' : '' ?>>Open</option>
              <option value="Closed" <?= $statusFilter === 'Closed' ? 'selected' : '' ?>>Closed</option>
            </select>
            <button class="btn btn-outline-primary" type="submit">Filter</button>
          </form>
        </div>

        <?php if (!$events): ?>
          <div class="text-muted py-4 text-center">No events found. Add your first event using the form.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead><tr><th>Poster</th><th>Event</th><th>Date / Status</th><th>Actions</th></tr></thead>
              <tbody>
              <?php foreach ($events as $event): ?>
                <tr>
                  <td>
                    <?php if ($event['poster'] && is_file($uploadDir . basename($event['poster']))): ?>
                      <img class="poster-thumb" src="<?= e($uploadUrl . rawurlencode($event['poster'])) ?>" alt="Event poster">
                    <?php else: ?>
                      <div class="poster-thumb bg-light d-flex align-items-center justify-content-center text-muted small">No image</div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="fw-semibold"><?= e($event['title']) ?></div>
                    <div class="small text-muted"><?= e($event['category']) ?></div>
                    <div class="small"><?= e(mb_strimwidth($event['description'], 0, 85, '…')) ?></div>
                  </td>
                  <td>
                    <div><?= e($event['event_date']) ?></div>
                    <span class="badge text-bg-<?= $event['status'] === 'Open' ? 'success' : 'secondary' ?>"><?= e($event['status']) ?></span>
                  </td>
                  <td>
                    <a class="btn btn-sm btn-outline-primary mb-1" href="events_manage.php?edit=<?= (int)$event['id'] ?>">Edit</a>
                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this event? This cannot be undone.');">
                      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$event['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger mb-1" type="submit">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <p class="text-muted small mt-4">Allowed poster formats: JPG, PNG, WEBP. Maximum file size: 2 MB.</p>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
