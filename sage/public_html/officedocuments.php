<?php
include_once 'auth.php'; // Ensure session and database connection

// Check if the user is logged in
check_loggedin($con);

// Hardcoded clinic folder
$clinic_folder = 'sage';
$upload_dir = "/home/clinicnotepro/uploads/$clinic_folder/";
$allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'png', 'txt', 'pptx'];
$max_file_size = 100 * 1024 * 1024; // 100MB

// Handle File Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $file = $_FILES['document'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_types)) {
        $error = "Invalid file type. Allowed: " . implode(', ', $allowed_types);
    } elseif ($file['size'] > $max_file_size) {
        $error = "File is too large. Max: 100MB.";
    } else {
        $new_file_name = time() . "_" . basename($file['name']);
        $file_path = $upload_dir . $new_file_name;

        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0775, true);
        }

        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            // Insert into database
            $user_id = $_SESSION['user_id'] ?? ($_SESSION['id'] ?? null);
            if (!$user_id) {
                die("Error: user_id not found in session.");
            }

            $stmt = $con->prepare("INSERT INTO office_documents (user_id, clinic_folder, file_name, file_path, file_type, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
            $stmt->bind_param("issss", $user_id, $clinic_folder, $new_file_name, $file_path, $_POST['file_type']);

            $stmt->execute();
            $stmt->close();

            $success = "File uploaded successfully!";
        } else {
            $error = "File upload failed.";
        }
    }
}

// Handle Review Actions (Only for Admins)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_action'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') {
        $status = $_POST['review_action'];
        $file_id = $_POST['file_id'];

        $stmt = $con->prepare("UPDATE office_documents SET status = ?, reviewed_by = ?, review_date = NOW() WHERE id = ?");
        $stmt->bind_param("ssi", $status, $_SESSION['name'], $file_id);
        $stmt->execute();
        $stmt->close();

        // Auto-refresh to reflect status update
        echo "<script>window.location.href = window.location.href;</script>";
        exit;
    } else {
        die("Error: You do not have permission to approve or reject documents.");
    }
}

// Fetch Files (with Sorting)
$status_filter = $_GET['status'] ?? 'All';
$query = "SELECT * FROM office_documents WHERE clinic_folder = ?";
if ($status_filter !== 'All') {
    $query .= " AND status = ?";
}

$stmt = $con->prepare($query);
if ($status_filter !== 'All') {
    $stmt->bind_param("ss", $clinic_folder, $status_filter);
} else {
    $stmt->bind_param("s", $clinic_folder);
}
$stmt->execute();
$result = $stmt->get_result();
$files = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NotesAO - Document Upload</title>
    <!-- FAVICON LINKS (from index.html) -->
    <link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicons/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="96x96" href="/favicons/favicon-96x96.png">
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg">

    <link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#211c56">

    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
    <link rel="apple-touch-icon" sizes="167x167" href="/favicons/apple-touch-icon-ipad-pro.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/favicons/apple-touch-icon-ipad.png">
    <link rel="apple-touch-icon" sizes="120x120" href="/favicons/apple-touch-icon-120x120.png">

    <link rel="manifest" href="/favicons/site.webmanifest">
    <meta name="apple-mobile-web-app-title" content="NotesAO">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>

<!-- Navbar -->
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <h2 class="text-center">Office Documents - Sage</h2>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php elseif (isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Sorting Options -->
    <div class="mb-3 text-center">
        <label><strong>Filter by Status:</strong></label>
        <a href="?status=All" class="btn btn-sm btn-secondary">All</a>
        <a href="?status=Pending" class="btn btn-sm btn-warning">Pending</a>
        <a href="?status=Approved" class="btn btn-sm btn-success">Approved</a>
        <a href="?status=Rejected" class="btn btn-sm btn-danger">Rejected</a>
    </div>

    <!-- Upload Button (Triggers Modal) -->
    <div class="text-center mb-4">
        <button class="btn btn-primary" data-toggle="modal" data-target="#uploadModal">
            <i class="fas fa-upload"></i> Upload New Document
        </button>
    </div>

    <!-- File List -->
    <div class="card">
        <div class="card-header">Uploaded Documents</div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Uploaded</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $file): ?>
                        <tr>
                        <td>
                            <a href="download2.php?file=<?= urlencode($file['file_name']) ?>">
                                <?= htmlspecialchars($file['file_name']) ?>
                            </a>
                        </td>

                            <td><?= htmlspecialchars($file['file_type']) ?></td>
                            <td>
                                <span class="badge badge-<?= $file['status'] == 'Approved' ? 'success' : ($file['status'] == 'Rejected' ? 'danger' : 'warning') ?>">
                                    <?= htmlspecialchars($file['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($file['upload_date']) ?></td>
                            <td>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin' && $file['status'] == 'Pending'): ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="file_id" value="<?= $file['id'] ?>">
                                        <button name="review_action" value="Approved" class="btn btn-success btn-sm">Approve</button>
                                        <button name="review_action" value="Rejected" class="btn btn-danger btn-sm">Reject</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" role="dialog" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel">Upload New Document</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="file_type">Document Type:</label>
                        <select name="file_type" id="file_type" class="form-control" required>
                            <option value="Client Note Updates">Client Note Updates</option>
                            <option value="Templates">Templates</option>
                            <option value="Client Data">Client Data</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="file" name="document" class="form-control-file" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>

</body>
</html>
