<?php
// document-templates.php – with Admin-only upload
include_once 'auth.php';
check_loggedin($con);
require_once 'helpers.php';

// Optional: tiny escaper fallback if h() absent
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/* ──────────────────────────────────────────────────────────────
   CONFIG
────────────────────────────────────────────────────────────── */
$IS_ADMIN = isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'; // adjust if you use other role names
$UPLOAD_BASE_DIR = __DIR__ . '/uploads/document-templates';
$UPLOAD_BASE_URL = '/uploads/document-templates';
$ALLOWED_EXT = ['pdf','doc','docx','xls','xlsx','ppt','pptx','rtf','txt','odt','png','jpg','jpeg'];
$MAX_BYTES   = 25 * 1024 * 1024; // 25MB server-side cap

// Ensure base upload dir exists
if (!is_dir($UPLOAD_BASE_DIR)) {
  @mkdir($UPLOAD_BASE_DIR, 0755, true);
}

/* ──────────────────────────────────────────────────────────────
   HELPERS
────────────────────────────────────────────────────────────── */
function slugify_segment(string $s): string {
  // safe folder/file name segment (lowercase, dashes)
  $s = trim($s);
  $s = iconv('UTF-8','ASCII//TRANSLIT',$s);
  $s = strtolower($s);
  $s = preg_replace('/[^a-z0-9]+/','-',$s);
  $s = trim($s,'-');
  return $s ?: 'general';
}
function safe_filename(string $name, string $ext): string {
  $base = preg_replace('/\.[^.]+$/','',$name);
  $base = preg_replace('/[^A-Za-z0-9._-]+/','_', $base);
  $base = trim($base,'._-');
  if ($base === '') $base = 'file';
  $ext  = strtolower(preg_replace('/[^A-Za-z0-9]+/','', $ext));
  return $ext ? ($base . '.' . $ext) : $base;
}
function nice_title_from_filename(string $filename): string {
  $name = preg_replace('/\.[^.]+$/','', $filename);
  $name = str_replace(['_','-'],' ', $name);
  $name = preg_replace('/\s+/',' ', $name);
  return ucwords(trim($name));
}
function human_filesize($bytes, $decimals = 1) {
  if ($bytes === null || $bytes === '' || !is_numeric($bytes)) return '';
  $size = ['B','KB','MB','GB','TB','PB'];
  $factor = (int) floor((strlen((string)$bytes) - 1) / 3);
  return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . $size[$factor];
}

// Build absolute URL from a site-relative file_url
function absolute_url_for(string $fileUrl): string {
    if (preg_match('~^https?://~i', $fileUrl)) return $fileUrl;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path   = '/' . ltrim($fileUrl, '/');
    return $scheme . '://' . $host . $path;
}

// Decide the best "view" URL for a file (one hop only)
function build_view_url(string $fileUrl, ?string $fileType): string {
    $abs = absolute_url_for($fileUrl);

    // Prefer extension to route Office docs to Office Online viewer
    $ext = strtolower(pathinfo(parse_url($fileUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
    $office_ext = ['doc','docx','xls','xlsx','ppt','pptx'];

    if (in_array($ext, $office_ext, true)) {
        // Directly open Office viewer (no intermediate tab)
        return 'https://view.officeapps.live.com/op/view.aspx?src=' . rawurlencode($abs);
    }

    // PDFs / images / txt can open directly in browser
    $inline_ext = ['pdf','png','jpg','jpeg','gif','txt'];
    if (in_array($ext, $inline_ext, true)) {
        return $fileUrl; // relative is fine
    }

    // If we got here, just return the direct URL (browser will download if needed)
    return $fileUrl;
}


/* ──────────────────────────────────────────────────────────────
   CSRF
────────────────────────────────────────────────────────────── */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

/* ──────────────────────────────────────────────────────────────
   UPLOAD (Admin only)
────────────────────────────────────────────────────────────── */
$flash = ['ok'=>[], 'err'=>[]];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $IS_ADMIN) {
  $act = $_POST['act'] ?? '';
  if ($act === 'upload') {
    // CSRF
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf'])) {
      $flash['err'][] = 'Security check failed. Please refresh and try again.';
    } else {

      // Gather inputs
      $title       = trim((string)($_POST['title'] ?? ''));
      $description = trim((string)($_POST['description'] ?? ''));
      $categorySel = trim((string)($_POST['category'] ?? ''));
      $categoryNew = trim((string)($_POST['category_new'] ?? ''));
      $file        = $_FILES['file'] ?? null;

      // Decide category (existing dropdown vs new)
      $category = $categorySel !== '__new__' ? $categorySel : $categoryNew;
      if ($category === '') $category = 'General';

      if (!$file || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        $flash['err'][] = 'Please choose a file to upload.';
      } else {
        // Size check (also ensure php.ini upload_max_filesize/post_max_size are sufficient)
        if ((int)$file['size'] > $MAX_BYTES) {
          $flash['err'][] = 'File is too large. Max allowed is ' . human_filesize($MAX_BYTES) . '.';
        } else {
          // Extension check
          $orig_name = $file['name'];
          $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
          if (!in_array($ext, $ALLOWED_EXT, true)) {
            $flash['err'][] = 'File type not allowed. Allowed: ' . implode(', ', $ALLOWED_EXT) . '.';
          } else {
            // Title default
            if ($title === '') $title = nice_title_from_filename($orig_name);

            // Build target path
            $cat_slug = slugify_segment($category);
            $cat_dir  = $GLOBALS['UPLOAD_BASE_DIR'] . '/' . $cat_slug;
            if (!is_dir($cat_dir)) {
              if (!@mkdir($cat_dir, 0755, true)) {
                $flash['err'][] = 'Failed creating category directory.';
              }
            }
            if (empty($flash['err'])) {
              // Sanitize filename & ensure uniqueness
              $target_name = safe_filename($orig_name, $ext);
              $target_path = $cat_dir . '/' . $target_name;
              if (file_exists($target_path)) {
                $base = preg_replace('/\.[^.]+$/','', $target_name);
                $i = 1;
                do {
                  $target_name_try = $base . '-' . $i . '.' . $ext;
                  $target_path     = $cat_dir . '/' . $target_name_try;
                  $i++;
                } while (file_exists($target_path) && $i < 1000);
                $target_name = basename($target_path);
              }

              if (!@move_uploaded_file($file['tmp_name'], $target_path)) {
                $flash['err'][] = 'Server error saving the file.';
              } else {
                @chmod($target_path, 0644);

                // Build public URL and metadata
                $rel_url   = $GLOBALS['UPLOAD_BASE_URL'] . '/' . rawurlencode($cat_slug) . '/' . rawurlencode($target_name);
                $file_size = (int) filesize($target_path);

                // Detect MIME
                $file_type = '';
                if (function_exists('finfo_open')) {
                  $f = finfo_open(FILEINFO_MIME_TYPE);
                  if ($f) {
                    $mime = finfo_file($f, $target_path);
                    if (is_string($mime)) $file_type = $mime;
                    finfo_close($f);
                  }
                }
                if ($file_type === '') {
                  // Fallback by extension
                  $map = [
                    'pdf'=>'application/pdf','doc'=>'application/msword',
                    'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'xls'=>'application/vnd.ms-excel',
                    'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'ppt'=>'application/vnd.ms-powerpoint',
                    'pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'rtf'=>'application/rtf','txt'=>'text/plain','odt'=>'application/vnd.oasis.opendocument.text',
                    'png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg'
                  ];
                  $file_type = $map[$ext] ?? 'application/octet-stream';
                }

                // Insert DB row
                $uploaded_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
                $stmt = $con->prepare("
                  INSERT INTO document_template
                    (title, category, description, file_url, file_name, file_type, file_size, uploaded_by)
                  VALUES (?,?,?,?,?,?,?,?)
                ");
                if ($stmt === false) {
                  $flash['err'][] = 'DB error: failed to prepare statement.';
                } else {
                  $file_name_for_db = $target_name;
                  $stmt->bind_param(
                    'ssssssii',
                    $title,
                    $category,
                    $description,
                    $rel_url,
                    $file_name_for_db,
                    $file_type,
                    $file_size,
                    $uploaded_by
                  );
                  if (!$stmt->execute()) {
                    $flash['err'][] = 'DB insert error: ' . h($stmt->error);
                  } else {
                    $flash['ok'][] = 'Uploaded "' . h($title) . '" successfully.';
                    // reset form values after success
                    $title = $description = '';
                  }
                  $stmt->close();
                }
              }
            }
          }
        }
      } // file checks
    }
  }
}

/* ──────────────────────────────────────────────────────────────
   FILTERS (search/order)
────────────────────────────────────────────────────────────── */
$search   = isset($_GET['search'])   ? trim((string)$_GET['search'])   : '';
$category = isset($_GET['category']) ? trim((string)$_GET['category']) : '';
$order    = isset($_GET['order'])    ? trim((string)$_GET['order'])    : 'uploaded_at';
$sort     = isset($_GET['sort'])     ? strtolower((string)$_GET['sort']) : 'desc';
$sort     = ($sort === 'asc') ? 'asc' : 'desc';

$allowed_order = [
  'title'        => 'dt.title',
  'category'     => 'dt.category',
  'file_type'    => 'dt.file_type',
  'file_size'    => 'dt.file_size',
  'uploaded_by'  => 'a.username',
  'uploaded_at'  => 'dt.uploaded_at'
];
$order_sql = $allowed_order[$order] ?? 'dt.uploaded_at';

$search_sql = '';
if ($search !== '') {
  $search_esc = mysqli_real_escape_string($con, $search);
  $search_sql = " AND CONCAT_WS(' ', dt.title, dt.description, dt.file_name, dt.file_type, dt.category, IFNULL(a.username,'')) LIKE '%$search_esc%'";
}

$category_sql = '';
if ($category !== '') {
  $category_esc = mysqli_real_escape_string($con, $category);
  $category_sql = " AND dt.category = '$category_esc'";
}

$sql = "
  SELECT
    dt.id,
    dt.title,
    dt.category,
    dt.description,
    dt.file_url,
    dt.file_name,
    dt.file_type,
    dt.file_size,
    dt.uploaded_at,
    a.username AS uploaded_by
  FROM document_template dt
  LEFT JOIN accounts a ON a.id = dt.uploaded_by
  WHERE 1=1
  $search_sql
  $category_sql
  ORDER BY $order_sql $sort
";

$categories = [];
$cat_rs = mysqli_query($con, "SELECT DISTINCT category FROM document_template WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC");
if ($cat_rs) {
  while ($r = mysqli_fetch_assoc($cat_rs)) $categories[] = $r['category'];
  mysqli_free_result($cat_rs);
}

$toggle_sort = ($sort === 'asc') ? 'desc' : 'asc';
$base_qs = http_build_query([
  'search'   => $search,
  'category' => $category,
  'sort'     => $toggle_sort
]);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>NotesAO – Document Templates</title>

  <!-- Favicons -->
  <link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicons/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
  <link rel="manifest" href="/favicons/site.webmanifest">
  <meta name="apple-mobile-web-app-title" content="NotesAO">

  <!-- CSS -->
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.1/css/all.min.css" crossorigin="anonymous"/>
  <style>
    .table td:last-child a, .table td:last-child button { margin-right: 8px; }
    .page-header .btn { margin-left: .5rem; }
    .desc-muted { color:#6c757d; font-size: 0.95rem; }
  </style>
</head>

<?php require_once 'navbar.php'; ?>
<body>
<section class="pt-5">
  <div class="container-fluid">
    <div class="row">
      <div class="col">
        <div class="page-header clearfix mb-3">
          <h2 class="float-left">Document Templates</h2>
          <div class="float-right">
            <?php if ($IS_ADMIN): ?>
              <button class="btn btn-primary" data-toggle="modal" data-target="#uploadModal">
                <i class="fas fa-upload"></i> Upload
              </button>
            <?php endif; ?>
            <a href="document-templates.php" class="btn btn-info">Reset View</a>
            <a href="home.php" class="btn btn-secondary">Home</a>
          </div>
        </div>

        <!-- Flash -->
        <?php foreach ($flash['ok'] as $m): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $m ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          </div>
        <?php endforeach; ?>
        <?php foreach ($flash['err'] as $m): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $m ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          </div>
        <?php endforeach; ?>

        <!-- Filters -->
        <form action="document-templates.php" method="get" class="mb-3">
          <input type="hidden" name="order" value="<?= h($order) ?>">
          <input type="hidden" name="sort"  value="<?= h($sort) ?>">

          <div class="form-row">
            <div class="col-md-4 mb-2">
              <small class="text-muted d-block">Quick Search</small>
              <input type="text"
                     name="search"
                     class="form-control"
                     placeholder="Search title, description, file name/type, uploader"
                     value="<?= h($search) ?>">
            </div>
            <div class="col-md-3 mb-2">
              <small class="text-muted d-block">Category</small>
              <select name="category" class="form-control">
                <option value="">— All —</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= h($cat) ?>" <?= ($cat === $category ? 'selected' : '') ?>>
                    <?= h($cat) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2 align-self-end mb-2">
              <button type="submit" class="btn btn-primary btn-block">Search</button>
            </div>
          </div>
        </form>

        <?php if ($rs = mysqli_query($con, $sql)): ?>
          <table class="table table-bordered table-striped">
            <thead>
              <tr>
                <th><a href="?<?= $base_qs ?>&order=title">Title</a></th>
                <th><a href="?<?= $base_qs ?>&order=category">Category</a></th>
                <th><a href="?<?= $base_qs ?>&order=file_type">Type</a></th>
                <th><a href="?<?= $base_qs ?>&order=file_size">Size</a></th>
                <th><a href="?<?= $base_qs ?>&order=uploaded_by">Uploaded By</a></th>
                <th><a href="?<?= $base_qs ?>&order=uploaded_at">Uploaded On</a></th>
                <th style="width:200px;">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php if (mysqli_num_rows($rs) > 0): ?>
              <?php while ($row = mysqli_fetch_assoc($rs)): ?>
                <?php
                  $title       = $row['title']       ?? '';
                  $categoryVal = $row['category']    ?? '';
                  $desc        = $row['description'] ?? '';
                  $fileUrl     = $row['file_url']    ?? '';
                  $fileName    = $row['file_name']   ?? '';
                  $fileType    = $row['file_type']   ?? '';
                  $fileSize    = $row['file_size']   ?? null;
                  $uploaded    = $row['uploaded_at'] ?? '';
                  $uploader    = $row['uploaded_by'] ?? '';
                  $viewHref = $fileUrl ? build_view_url($fileUrl, $fileType) : '';


                  if (!$fileUrl && $fileName) {
                    $fileUrl = "/uploads/document-templates/" . rawurlencode($fileName);
                  }
                ?>
                <tr>
                  <td>
                    <div class="font-weight-bold mb-1">
                        <?php if ($viewHref): ?>
                            <a href="<?= h($viewHref) ?>" target="_blank" rel="noopener">
                            <?= h($title ?: $fileName) ?>
                            </a>
                        <?php else: ?>
                            <?= h($title ?: $fileName) ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($desc): ?>
                      <div class="desc-muted"><?= h($desc) ?></div>
                    <?php endif; ?>
                  </td>
                  <td><?= h($categoryVal) ?></td>
                  <td><?= h($fileType) ?></td>
                  <td><?= h(human_filesize($fileSize)) ?></td>
                  <td><?= h($uploader) ?></td>
                  <td><?= h($uploaded) ?></td>
                  <td>
                    <?php if ($fileUrl): ?>
                        <?php if ($viewHref): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= h($viewHref) ?>" target="_blank" rel="noopener" data-toggle="tooltip" title="View">
                                <i class="far fa-eye"></i>
                            </a>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline-success" href="<?= h($fileUrl) ?>" download data-toggle="tooltip" title="Download">
                            <i class="fas fa-download"></i>
                        </a>
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary copy-link"
                                data-url="<?= h(absolute_url_for($fileUrl)) ?>"
                                data-toggle="tooltip"
                                title="Copy link">
                            <i class="far fa-copy"></i>
                        </button>
                        <?php else: ?>
                            <span class="text-muted">No file</span>
                        <?php endif; ?>

                  </td>
                </tr>
              <?php endwhile; mysqli_free_result($rs); ?>
            <?php else: ?>
              <tr><td colspan="7" class="text-center text-muted">No documents found.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="alert alert-danger">
            ERROR: Could not execute query. <?= h(mysqli_error($con)) ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php if ($IS_ADMIN): ?>
<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" role="dialog" aria-labelledby="uploadModalLbl" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form method="post" enctype="multipart/form-data" class="modal-content">
      <input type="hidden" name="act" value="upload">
      <input type="hidden" name="csrf" value="<?= h($csrf_token) ?>">
      <div class="modal-header">
        <h5 class="modal-title" id="uploadModalLbl"><i class="fas fa-upload"></i> Upload Document Template</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>File <small class="text-muted">(Allowed: <?= h(implode(', ', $ALLOWED_EXT)) ?>; max <?= h(human_filesize($MAX_BYTES)) ?>)</small></label>
            <input type="file" name="file" id="fileInput" class="form-control-file" required
                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.rtf,.txt,.odt,.png,.jpg,.jpeg">
          </div>
          <div class="form-group col-md-6">
            <label>Title</label>
            <input type="text" name="title" id="titleInput" class="form-control" placeholder="Defaults from filename if left blank">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Category</label>
            <select name="category" id="categorySelect" class="form-control">
              <?php if (empty($categories)): ?>
                <option value="General">General</option>
              <?php else: ?>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= h($cat) ?>"><?= h($cat) ?></option>
                <?php endforeach; ?>
                <option value="__new__">— New category… —</option>
              <?php endif; ?>
            </select>
          </div>
          <div class="form-group col-md-6" id="newCatWrap" style="display:none;">
            <label>New Category Name</label>
            <input type="text" name="category_new" class="form-control" placeholder="e.g., BIPP, Intake, Court">
          </div>
        </div>

        <div class="form-group">
          <label>Description <small class="text-muted">(optional)</small></label>
          <textarea name="description" class="form-control" rows="3" placeholder="Short description shown under the title"></textarea>
        </div>

        <div class="alert alert-secondary mb-0">
          Files are stored under <code>/uploads/document-templates/&lt;category&gt;/</code>. PHP will rename if a filename already exists.
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary"><i class="fas fa-cloud-upload-alt"></i> Upload</button>
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.5.1.min.js" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script>
  $(function () {
    $('[data-toggle="tooltip"]').tooltip();

    $('.copy-link').on('click', function () {
      var url = $(this).data('url') || '';
      if (!url) return;
      var $tmp = $('<input>').val(url).appendTo('body').select();
      document.execCommand('copy');
      $tmp.remove();
      var $btn = $(this);
      $btn.tooltip('hide')
          .attr('data-original-title', 'Copied!')
          .tooltip('show');
      setTimeout(function () {
        $btn.tooltip('hide')
            .attr('data-original-title', 'Copy link');
      }, 1200);
    });

    // Auto-title from filename
    $('#fileInput').on('change', function(){
      var f = this.files && this.files[0] ? this.files[0].name : '';
      if (!f) return;
      var title = f.replace(/\.[^.]+$/, '').replace(/[_-]+/g,' ').replace(/\s+/g,' ').trim();
      var $title = $('#titleInput');
      if (!$title.val()) $title.val(title.replace(/\b\w/g, c => c.toUpperCase()));
    });

    // New category toggle
    $('#categorySelect').on('change', function(){
      $('#newCatWrap').toggle(this.value === '__new__');
    });
  });
</script>
</body>
</html>
