<?php
// --------------------------------------------------------------------
// 1) UNIFIED HEADER LOGIC
// --------------------------------------------------------------------

// Include authentication, config, and main functionality once at the top
include_once '../auth.php';
require_once '../../config/config.php';
include 'main.php';
require_once '/home/notesao/lib/mailer.php';

// Check if the user is an Admin
check_loggedin($con, '../index.php');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    error_log("Access Denied: Role is " . ($_SESSION['role'] ?? 'not set'));
    exit('You do not have permission to access this page!');
}

// Decide which tab/section we’re currently viewing
$section = $_GET['section'] ?? 'accounts';  // default to “accounts” tab

// We'll keep a generic $success_msg to display at the top of each tab
$success_msg = '';

// --------------------------------------------------------------------
// 2) USER ACCOUNTS (MAIN “LIST” PAGE) LOGIC
//    (Originally from your accounts.php)
// --------------------------------------------------------------------

// We wrap it in an if-block so it only runs if we’re on the “accounts” tab
if ($section === 'accounts') {
    // The existing logic from your “accounts.php”
    $page = $_GET['page'] ?? 1;
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $activation = $_GET['activation'] ?? '';
    $role = $_GET['role'] ?? '';
    $order = (($_GET['order'] ?? '') === 'DESC') ? 'DESC' : 'ASC';
    $order_by_whitelist = ['id','username','email','activation_code','role','registered','last_seen'];
    $order_by = in_array($_GET['order_by'] ?? '', $order_by_whitelist) ? $_GET['order_by'] : 'id';
    $results_per_page = 20;
    $accounts = [];

    // Query parameters
    $param1 = ($page - 1) * $results_per_page;
    $param2 = $results_per_page;
    $param3 = '%' . $search . '%';
    $where = $search ? 'WHERE (username LIKE ? OR email LIKE ?) ' : '';

    // Filters
    if ($status == 'active') {
        $where .= ($where ? 'AND ' : 'WHERE ') . 'last_seen > date_sub(now(), interval 1 month) ';
    }
    if ($status == 'inactive') {
        $where .= ($where ? 'AND ' : 'WHERE ') . 'last_seen < date_sub(now(), interval 1 month) ';
    }
    if ($activation == 'pending') {
        $where .= ($where ? 'AND ' : 'WHERE ') . 'activation_code != "activated" ';
    }
    if ($role) {
        $where .= ($where ? 'AND ' : 'WHERE ') . 'role = ? ';
    }

    // Get total accounts
    $stmt = $con->prepare('SELECT COUNT(*) AS total FROM accounts ' . $where);
    if ($search && $role) {
        $stmt->bind_param('sss', $param3, $param3, $role);
    } elseif ($search) {
        $stmt->bind_param('ss', $param3, $param3);
    } elseif ($role) {
        $stmt->bind_param('s', $role);
    }
    $stmt->execute();
    $stmt->bind_result($accounts_total);
    $stmt->fetch();
    $stmt->close();

    // Fetch account data
    $stmt = $con->prepare('SELECT id, username, email, activation_code, role, registered, last_seen
                           FROM accounts ' . $where . '
                           ORDER BY ' . $order_by . ' ' . $order . '
                           LIMIT ?,?');
    $types = '';
    $params = [];
    if ($search) {
        $params[] = &$param3;
        $params[] = &$param3;
        $types .= 'ss';
    }
    if ($role) {
        $params[] = &$role;
        $types .= 's';
    }
    $params[] = &$param1;
    $params[] = &$param2;
    $types .= 'ii';
    call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $params));
    $stmt->execute();
    $stmt->bind_result($result_id, $result_username, $result_email, $result_activation_code,
                       $result_role, $result_registered, $result_last_seen);
    while ($stmt->fetch()) {
        $accounts[] = [
            'id' => $result_id,
            'username' => $result_username,
            'email' => $result_email,
            'activation_code' => $result_activation_code,
            'role' => $result_role,
            'registered' => $result_registered,
            'last_seen' => $result_last_seen
        ];
    }
    $stmt->close();

    // Delete account
    if (isset($_GET['delete'])) {
        $stmt = $con->prepare('DELETE FROM accounts WHERE id = ?');
        $stmt->bind_param('i', $_GET['delete']);
        $stmt->execute();
        header('Location: accounts.php?section=accounts&success_msg=3');
        exit;
    }

    // Success messages
    if (isset($_GET['success_msg'])) {
        $messages = [
            1 => 'Account created successfully!',
            2 => 'Account updated successfully!',
            3 => 'Account deleted successfully!'
        ];
        $success_msg = $messages[$_GET['success_msg']] ?? '';
    }

    // Create URL
    $url = 'accounts.php?section=accounts&search=' . urlencode($search)
         . '&status=' . urlencode($status)
         . '&activation=' . urlencode($activation)
         . '&role=' . urlencode($role);
}

// --------------------------------------------------------------------
// 3) CREATE / EDIT ACCOUNT LOGIC
//    (Originally from account.php)
// --------------------------------------------------------------------
if ($section === 'edit') {
    // We'll replicate the logic from account.php here
    $account = [
        'username' => '',
        'password' => '',
        'email' => '',
        'activation_code' => '',
        'rememberme' => '',
        'role' => 'Member',
        'registered' => date('Y-m-d\TH:i'),
        'last_seen' => date('Y-m-d\TH:i')
    ];
    $error_msg = '';

    // Decide if we’re editing or creating
    if (isset($_GET['id'])) {
        // Editing existing
        $pageType = 'Edit';
        $id = (int)$_GET['id'];
        // Load existing account
        $stmt = $con->prepare('SELECT username, password, email, activation_code, rememberme, role, registered, last_seen 
                               FROM accounts 
                               WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($account['username'], $account['password'], $account['email'], $account['activation_code'],
                           $account['rememberme'], $account['role'], $account['registered'], $account['last_seen']);
        $stmt->fetch();
        $stmt->close();

        // On form submit
        if (isset($_POST['submit'])) {
            // Validate unique username
            $stmt = $con->prepare('SELECT id FROM accounts WHERE username = ? AND username != ?');
            $stmt->bind_param('ss', $_POST['username'], $account['username']);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $error_msg = 'Username already exists!';
            }
            // Validate unique email
            $stmt = $con->prepare('SELECT id FROM accounts WHERE email = ? AND email != ?');
            $stmt->bind_param('ss', $_POST['email'], $account['email']);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $error_msg = 'Email already exists!';
            }
            // If no error, update
            if (!$error_msg) {
                $password = !empty($_POST['password'])
                            ? password_hash($_POST['password'], PASSWORD_DEFAULT)
                            : $account['password'];
                $stmt = $con->prepare('UPDATE accounts 
                                       SET username = ?, password = ?, email = ?, activation_code = ?, 
                                           rememberme = ?, role = ?, registered = ?, last_seen = ?
                                       WHERE id = ?');
                $stmt->bind_param('ssssssssi',
                                  $_POST['username'],
                                  $password,
                                  $_POST['email'],
                                  $_POST['activation_code'],
                                  $_POST['rememberme'],
                                  $_POST['role'],
                                  $_POST['registered'],
                                  $_POST['last_seen'],
                                  $id);
                $stmt->execute();
                header('Location: accounts.php?section=accounts&success_msg=2');
                exit;
            } else {
                // Keep the posted data in case of error
                $account['username']       = $_POST['username'];
                $account['password']       = $_POST['password'];
                $account['email']          = $_POST['email'];
                $account['activation_code']= $_POST['activation_code'];
                $account['rememberme']     = $_POST['rememberme'];
                $account['role']           = $_POST['role'];
                $account['registered']     = $_POST['registered'];
                $account['last_seen']      = $_POST['last_seen'];
            }
        }
        // If user clicked “Delete”
        if (isset($_POST['delete'])) {
            header('Location: accounts.php?section=accounts&delete=' . $id);
            exit;
        }
    } else {
        // Creating new
        $pageType = 'Create';
        // ------------------------------------------------------------------
        // Activation & first-login values
        // ------------------------------------------------------------------
        $activation_code      = bin2hex(random_bytes(32));      // 64-char token
        $password_force_reset = 1;                              // force change
        $password_changed_at  = date('Y-m-d H:i:s');            // now

        if (isset($_POST['submit'])) {
            // Validate unique username
            $stmt = $con->prepare('SELECT id FROM accounts WHERE username = ?');
            $stmt->bind_param('s', $_POST['username']);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $error_msg = 'Username already exists!';
            }
            // Validate unique email
            $stmt = $con->prepare('SELECT id FROM accounts WHERE email = ?');
            $stmt->bind_param('s', $_POST['email']);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $error_msg = 'Email already exists!';
            }
            // If no error, insert
            if (!$error_msg) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                // Hash temp password (default “password” if left blank)
                $temp_pass = $_POST['password'] ?: 'password';
                $hashed    = password_hash($temp_pass, PASSWORD_DEFAULT);

                $stmt = $con->prepare('INSERT IGNORE INTO accounts
                  (username,password,email,activation_code,rememberme,role,
                  registered,last_seen,password_force_reset,password_changed_at)
                  VALUES (?,?,?,?,?,?,?,?,?,?)');

                $stmt->bind_param(
                  'ssssssssss',
                  $_POST['username'],
                  $hashed,
                  $_POST['email'],
                  $activation_code,
                  $_POST['rememberme'],
                  $_POST['role'],
                  $_POST['registered'],
                  $_POST['last_seen'],
                  $password_force_reset,
                  $password_changed_at
                );
                $stmt->execute();

                /* ----------------------------------------------------------------
                  Send activation e-mail
                ----------------------------------------------------------------- */
                /* Map current clinic folder (e.g. "lankford") → short code */
                $folder = basename(dirname(dirname(dirname(__FILE__))));   // ← now "lankford"

                $map = [
                    'ffltest'      => 'ffl',
                    'sandbox'      => 'sandbox',
                    'dwag'         => 'dwag',
                    'safatherhood' => 'saf',
                    'ctc'          => 'ctc',
                    'bestoption'   => 'tbo',
                    'transform'    => 'transform',
                    'lankford'     => 'lankford',
                ];
                $clinic_code = $map[$folder] ?? '';


                if ($clinic_code) {
                    $link = "https://notesao.com/activate.php?clinic={$clinic_code}&code={$activation_code}";

                    $html = "
                      <p>Hello {$_POST['username']},</p>
                      <p>An administrator just created an account for you on <strong>NotesAO</strong>.</p>
                      <p>Click the button below to activate your account and choose your password:</p>
                      <p>
                        <a href='{$link}' style='padding:10px 18px;background:#211c56;color:#fff;
                          text-decoration:none;border-radius:6px;'>Activate Account</a>
                      </p>
                      <p>This link works only once.</p>";

                    //send_email($_POST['email'], 'Activate your NotesAO account', $html);
                    $ok = send_email($_POST['email'],
                                    'Activate your NotesAO account',
                                    $html);

                    error_log("Activation mail to {$_POST['email']} – ".($ok ? 'SENT' : 'FAILED'));

                }


                header('Location: accounts.php?section=accounts&success_msg=1');
                exit;
            } else {
                // Keep the posted data in case of error
                $account['username']       = $_POST['username'];
                $account['password']       = $_POST['password'];
                $account['email']          = $_POST['email'];
                $account['activation_code']= $_POST['activation_code'];
                $account['rememberme']     = $_POST['rememberme'];
                $account['role']           = $_POST['role'];
                $account['registered']     = $_POST['registered'];
                $account['last_seen']      = $_POST['last_seen'];
            }
        }
    }
}

// --------------------------------------------------------------------
// 4) ROLES LOGIC
//    (Originally from roles.php)
// --------------------------------------------------------------------
if ($section === 'roles') {
    // We'll replicate the roles logic
    // $roles_list presumably comes from main.php
    $rolesQuery = $con->query('SELECT role, COUNT(*) as total FROM accounts GROUP BY role')
                      ->fetch_all(MYSQLI_ASSOC);
    $roles = array_column($rolesQuery, 'total', 'role');
    foreach ($roles_list as $r) {
        if (!isset($roles[$r])) $roles[$r] = 0;
    }
    $rolesActiveQuery = $con->query('SELECT role, COUNT(*) as total 
                                     FROM accounts 
                                     WHERE last_seen > date_sub(now(), interval 1 month) 
                                     GROUP BY role')->fetch_all(MYSQLI_ASSOC);
    $roles_active = array_column($rolesActiveQuery, 'total', 'role');
    $rolesInactiveQuery = $con->query('SELECT role, COUNT(*) as total 
                                       FROM accounts 
                                       WHERE last_seen < date_sub(now(), interval 1 month) 
                                       GROUP BY role')->fetch_all(MYSQLI_ASSOC);
    $roles_inactive = array_column($rolesInactiveQuery, 'total', 'role');
}

// --------------------------------------------------------------------
// 5) EMAIL TEMPLATES LOGIC
//    (Originally from emailtemplates.php)
// --------------------------------------------------------------------
if ($section === 'email') {
    // On save
    if (isset($_POST['activation_email_template'])) {
        file_put_contents('../activation-email-template.html', $_POST['activation_email_template']);
        header('Location: accounts.php?section=email&success_msg=1');
        exit;
    }
    if (isset($_POST['twofactor_email_template'])) {
        file_put_contents('../twofactor.html', $_POST['twofactor_email_template']);
        header('Location: accounts.php?section=email&success_msg=1');
        exit;
    }
    if (isset($_POST['resetpass_email_template'])) {
        file_put_contents('../resetpass-email-template.html', $_POST['resetpass_email_template']);
        header('Location: accounts.php?section=email&success_msg=1');
        exit;
    }

    // Read existing template files (if they exist)
    $activation_email_template = file_exists('../activation-email-template.html')
                               ? file_get_contents('../activation-email-template.html')
                               : '';
    $twofactor_email_template  = file_exists('../twofactor.html')
                               ? file_get_contents('../twofactor.html')
                               : '';
    $resetpass_email_template  = file_exists('../resetpass-email-template.html')
                               ? file_get_contents('../resetpass-email-template.html')
                               : '';

    // Success message
    if (isset($_GET['success_msg']) && $_GET['success_msg'] == 1) {
        $success_msg = 'Email template updated successfully!';
    }
}

// --------------------------------------------------------------------
// 6) SETTINGS LOGIC
//    (Originally from settings.php)
// --------------------------------------------------------------------
if ($section === 'settings') {
    // Path to config
    $file = '../../config/config.php';
    // Read the entire config
    $contents = file_get_contents($file);

    // Support functions from settings.php
    function format_key($key) {
        $key = str_replace(
            ['_', 'url', 'db ', ' pass', ' user', ' id', ' uri', 'oauth', 'recaptcha'],
            [' ', 'URL', 'Database ', ' Password', ' Username', ' ID', ' URI', 'OAuth', 'reCAPTCHA'],
            strtolower($key)
        );
        return ucwords($key);
    }
    function format_var_html($key, $value, $comment) {
        // Decide input type, etc. (same logic as before).
        $type = 'text';
        $value = htmlspecialchars(trim($value, '\''), ENT_QUOTES);
        if (strpos($key, 'pass') !== false) {
            $type = 'password';
        }
        if (in_array(strtolower($value), ['true', 'false'])) {
            $type = 'checkbox';
        }
        $checked = (strtolower($value) == 'true') ? ' checked' : '';
    
        // Wrap each setting in a row for cleaner spacing
        // Label = col-sm-3, Input = col-sm-9
        $html = '<div class="row mb-3">';
    
        // LABEL column
        $html .= '<label for="' . $key . '" class="col-sm-3 col-form-label fw-bold">'
              .  format_key($key)
              .  '</label>';
    
        // INPUT column
        $html .= '<div class="col-sm-9">';
    
        // If there's a comment, display it as helper text
        // You can move this below the input if you prefer
        if (substr($comment, 0, 2) === '//') {
            $helper_text = ltrim($comment, '//'); // remove leading slashes
            $html .= '<small class="text-muted">' . htmlspecialchars($helper_text) . '</small><br>';
        }
    
        // If it's a checkbox, add hidden field so un-checked boxes send "false"
        if ($type === 'checkbox') {
            $html .= '<input type="hidden" name="' . $key . '" value="false">';
            $html .= '<div class="form-check">';
            $html .= '  <input class="form-check-input" type="checkbox" name="' . $key . '" id="' . $key . '"'
                  .  '       value="' . $value . '"' . $checked . '>';
            $html .= '  <label class="form-check-label" for="' . $key . '">'
                  .       'Enable'
                  .  '</label>';
            $html .= '</div>';
        } else {
            // Normal text/password/etc. input
            $html .= '<input type="' . $type . '" class="form-control" '
                  .  'name="' . $key . '" id="' . $key . '" '
                  .  'placeholder="' . format_key($key) . '" '
                  .  'value="' . $value . '">';
        }
    
        $html .= '</div>'; // end col-sm-9
        $html .= '</div>'; // end row
    
        return $html;
    }
    
    function format_tabs($contents) {
        // You can remove or adapt if you don’t want tabs inside the settings
        $rows = explode("\n", $contents);
        echo '<div class="tabs mb-3" style="display:none;">'; // hide or remove if needed
        echo '<a href="#" class="active">General</a>';
        for ($i = 0; $i < count($rows); $i++) {
            if (preg_match('/\/\*(.*?)\*\//', $rows[$i], $match)) {
                echo '<a href="#">' . $match[1] . '</a>';
            }
        }
        echo '</div>';
    }
    function format_form($contents) {
        $rows = explode("\n", $contents);
        echo '<div class="tab-content active">';
        for ($i = 0; $i < count($rows); $i++) {
            if (preg_match('/\/\*(.*?)\*\//', $rows[$i])) {
                echo '</div><div class="tab-content">';
            }
            if (preg_match('/define\(\'(.*?)\', ?(.*?)\)/', $rows[$i], $match)) {
                // $match[1] => key, $match[2] => value
                // The line above this might have comment
                $comment = $rows[$i-1] ?? '';
                echo format_var_html($match[1], $match[2], $comment);
            }
        }
        echo '</div>';
    }

    // If form posted
    if (!empty($_POST)) {
        foreach ($_POST as $k => $v) {
            // If it’s a bool, we store as lowercase true/false
            $v = in_array(strtolower($v), ['true','false']) ? strtolower($v) : '\'' . $v . '\'';
            $contents = preg_replace('/define\(\'' . $k . '\', ?(.*?)\)/s',
                                     'define(\'' . $k . '\',' . $v . ')',
                                     $contents);
        }
        file_put_contents($file, $contents);
        header('Location: accounts.php?section=settings&success_msg=1');
        exit;
    }

    // Success messages
    if (isset($_GET['success_msg']) && $_GET['success_msg'] == 1) {
        $success_msg = 'Settings updated successfully!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NotesAO - Accounts</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>

    <style>
        body {
            padding-top: 70px;
            background-color: #f8f9fa;
        }
        /* Match the style from index.php */
        .page-header h2 {
            margin-top: 0;
        }
        .admin-btn {
            margin: 10px 5px;
        }
    </style>
</head>

<body>

<!-- Include your admin navbar -->
<?php include 'admin_navbar.php'; ?>

<div class="container-fluid mt-3">
  <!-- Possibly a heading for your entire Admin panel -->
  <h1 class="mb-4">
    <i class="fas fa-tools me-2"></i>User Accounts
  </h1>

  <!-- Nav Tabs for each “sub-page” -->
  <ul class="nav nav-pills mb-3" id="adminTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <a class="nav-link <?=($section === 'accounts') ? 'active' : ''?>" 
         href="?section=accounts" role="tab">
        <i class="fas fa-users-cog"></i> User Accounts
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link <?=($section === 'edit') ? 'active' : ''?>" 
         href="?section=edit" role="tab">
        <i class="fas fa-user-plus"></i> Create/Edit Account
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link <?=($section === 'roles') ? 'active' : ''?>" 
         href="?section=roles" role="tab">
        <i class="fas fa-list"></i> Roles
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link <?=($section === 'email') ? 'active' : ''?>" 
         href="?section=email" role="tab">
        <i class="fas fa-envelope"></i> Email Templates
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link <?=($section === 'settings') ? 'active' : ''?>" 
         href="?section=settings" role="tab">
        <i class="fas fa-cog"></i> Settings
      </a>
    </li>
    <li class="nav-item ms-auto" role="presentation">
        <a class="nav-link" href="index.php">
            <i class="fas fa-arrow-left"></i> Back to Admin Panel
        </a>
    </li>
  </ul>

  <?php if (!empty($success_msg)): ?>
    <div class="alert alert-success d-flex align-items-center" role="alert">
      <i class="fas fa-check-circle me-2"></i>
      <div><?=htmlspecialchars($success_msg)?></div>
    </div>
  <?php endif; ?>

  <!-- Render the content for the active tab/section -->
  <?php if ($section === 'accounts'): ?>
    <!-- ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
         2) USER ACCOUNTS LIST 
         ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ -->
    <h3>Manage User Accounts</h3>
    <form method="get" class="mb-3">
      <!-- Keep the section=accounts param so we stay in this tab -->
      <input type="hidden" name="section" value="accounts">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label for="search" class="form-label fw-bold">Search</label>
          <input type="text" id="search" name="search" class="form-control"
                 placeholder="Username or email..." 
                 value="<?=htmlspecialchars($search)?>">
        </div>
        <div class="col-md-2">
          <label for="status" class="form-label fw-bold">Status</label>
          <select id="status" name="status" class="form-select">
            <option value="">All</option>
            <option value="active" <?=$status=='active'?'selected':''?>>Active</option>
            <option value="inactive" <?=$status=='inactive'?'selected':''?>>Inactive</option>
          </select>
        </div>
        <div class="col-md-2">
          <label for="activation" class="form-label fw-bold">Activation</label>
          <select id="activation" name="activation" class="form-select">
            <option value="">All</option>
            <option value="pending" <?=$activation=='pending'?'selected':''?>>Pending</option>
          </select>
        </div>
        <div class="col-md-2">
          <label for="role" class="form-label fw-bold">Role</label>
          <select id="role" name="role" class="form-select">
            <option value="">All</option>
            <option value="Admin" <?=$role=='Admin'?'selected':''?>>Admin</option>
            <option value="User" <?=$role=='User'?'selected':''?>>User</option>
          </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1">
            <i class="fas fa-check"></i> Apply
          </button>
          <a href="accounts.php?section=accounts" class="btn btn-outline-secondary flex-grow-1">
            <i class="fas fa-undo"></i> Reset
          </a>
        </div>
      </div>
    </form>

    <div class="d-flex justify-content-between align-items-center mb-3">
      <!-- Link now goes to ?section=edit (Create mode) -->
      <a href="?section=edit" class="btn btn-success btn-sm">
        <i class="fas fa-user-plus"></i> Create New Account
      </a>
      <span class="text-muted">Total Accounts: <?=$accounts_total?></span>
    </div>

    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle">
        <thead class="table-dark">
          <tr>
            <th>
              <a class="text-white" 
                 href="<?=$url?>&order=<?=($order=='ASC'?'DESC':'ASC')?>&order_by=id">
                ID <?=($order_by=='id'?($order=='ASC'?'▲':'▼'):'')?>
              </a>
            </th>
            <th>
              <a class="text-white" 
                 href="<?=$url?>&order=<?=($order=='ASC'?'DESC':'ASC')?>&order_by=username">
                Username <?=($order_by=='username'?($order=='ASC'?'▲':'▼'):'')?>
              </a>
            </th>
            <th>Email</th>
            <th>Activation</th>
            <th>Role</th>
            <th>
              <a class="text-white"
                 href="<?=$url?>&order=<?=($order=='ASC'?'DESC':'ASC')?>&order_by=registered">
                Registered <?=($order_by=='registered'?($order=='ASC'?'▲':'▼'):'')?>
              </a>
            </th>
            <th>
              <a class="text-white"
                 href="<?=$url?>&order=<?=($order=='ASC'?'DESC':'ASC')?>&order_by=last_seen">
                Last Seen <?=($order_by=='last_seen'?($order=='ASC'?'▲':'▼'):'')?>
              </a>
            </th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($accounts)): ?>
            <tr><td colspan="8" class="text-center"><em>No accounts found.</em></td></tr>
          <?php else: ?>
            <?php foreach ($accounts as $acc): ?>
              <tr>
                <td><?=$acc['id']?></td>
                <td><?=htmlspecialchars($acc['username'])?></td>
                <td><?=htmlspecialchars($acc['email'])?></td>
                <td>
                  <?php if ($acc['activation_code'] && $acc['activation_code'] !== 'activated'): ?>
                    <?=htmlspecialchars($acc['activation_code'])?>
                  <?php else: ?>
                    <span class="badge bg-success">Activated</span>
                  <?php endif; ?>
                </td>
                <td><?=htmlspecialchars($acc['role'])?></td>
                <td><?=date('Y-m-d H:i', strtotime($acc['registered']))?></td>
                <td title="<?=$acc['last_seen']?>">
                  <?=time_elapsed_string($acc['last_seen'])?>
                </td>
                <td>
                  <!-- Link to edit: ?section=edit&id=... -->
                  <a href="?section=edit&id=<?=$acc['id']?>" class="btn btn-warning btn-sm">
                    <i class="fas fa-edit"></i> Edit
                  </a>
                  <a href="?section=accounts&delete=<?=$acc['id']?>" 
                     class="btn btn-danger btn-sm"
                     onclick="return confirm('Are you sure you want to delete this account?')">
                    <i class="fas fa-trash-alt"></i> Delete
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php 
    $total_pages = max(1, ceil($accounts_total / $results_per_page));
    ?>
    <nav aria-label="Accounts pagination" class="mt-3">
      <ul class="pagination">
        <?php if ($page > 1): ?>
          <li class="page-item">
            <a class="page-link" 
               href="<?=$url?>&page=<?=($page-1)?>&order=<?=$order?>&order_by=<?=$order_by?>">
              &laquo; Previous
            </a>
          </li>
        <?php else: ?>
          <li class="page-item disabled">
            <span class="page-link">&laquo; Previous</span>
          </li>
        <?php endif; ?>

        <li class="page-item active">
          <span class="page-link">
            Page <?=$page?> of <?=$total_pages?>
          </span>
        </li>

        <?php if ($page < $total_pages): ?>
          <li class="page-item">
            <a class="page-link" 
               href="<?=$url?>&page=<?=($page+1)?>&order=<?=$order?>&order_by=<?=$order_by?>">
              Next &raquo;
            </a>
          </li>
        <?php else: ?>
          <li class="page-item disabled">
            <span class="page-link">Next &raquo;</span>
          </li>
        <?php endif; ?>
      </ul>
    </nav>

    <?php elseif ($section === 'edit'): ?>
    <!-- ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
         3) CREATE / EDIT ACCOUNT 
         ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ -->
    <?php
      // We have $pageType, $account, and $error_msg from above
      // (If ?id=XX was set, we’re editing; otherwise creating)
    ?>
    <h3 class="mb-4">
        <i class="fas fa-user-plus me-2"></i>
        <?=$pageType?> Account
    </h3>

    <?php if ($error_msg): ?>
      <div class="alert alert-danger d-flex align-items-center" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <div><?=htmlspecialchars($error_msg)?></div>
      </div>
    <?php endif; ?>

    <form 
      action="?section=edit<?=isset($_GET['id']) ? '&id=' . intval($_GET['id']) : ''?>" 
      method="post" 
      class="mb-5"
    >
      <!-- Username -->
      <div class="row mb-3">
        <label for="username" class="col-sm-3 col-form-label fw-bold">Username</label>
        <div class="col-sm-9">
          <input 
            type="text" 
            id="username" 
            name="username"
            class="form-control"
            placeholder="Username"
            value="<?=htmlspecialchars($account['username'])?>"
            required
          >
        </div>
      </div>

      <!-- Password -->
      <div class="row mb-3">
        <label for="password" class="col-sm-3 col-form-label fw-bold">
          <?=$pageType === 'Edit' ? 'New ' : ''?>Password
        </label>
        <div class="col-sm-9">
          <input 
            type="text" 
            id="password" 
            name="password"
            class="form-control"
            placeholder="<?=$pageType === 'Edit' ? '(Leave blank if unchanged)' : 'Password'?>"
            value=""
            <?=($pageType === 'Create' ? 'required' : '')?>
          >
        </div>
      </div>

      <!-- Email -->
      <div class="row mb-3">
        <label for="email" class="col-sm-3 col-form-label fw-bold">Email</label>
        <div class="col-sm-9">
          <input 
            type="email" 
            id="email" 
            name="email"
            class="form-control"
            placeholder="Email"
            value="<?=htmlspecialchars($account['email'])?>"
            required
          >
        </div>
      </div>

      <!-- Activation Code -->
      <div class="row mb-3">
        <label for="activation_code" class="col-sm-3 col-form-label fw-bold">Activation Code</label>
        <div class="col-sm-9">
          <input 
            type="text" 
            id="activation_code" 
            name="activation_code"
            class="form-control"
            placeholder="Activation Code"
            value="<?=htmlspecialchars($account['activation_code'])?>"
          >
        </div>
      </div>

      <!-- Remember Me Code -->
      <div class="row mb-3">
        <label for="rememberme" class="col-sm-3 col-form-label fw-bold">Remember Me Code</label>
        <div class="col-sm-9">
          <input 
            type="text" 
            id="rememberme" 
            name="rememberme"
            class="form-control"
            placeholder="Remember Me Code"
            value="<?=htmlspecialchars($account['rememberme'])?>"
          >
        </div>
      </div>

      <!-- Role -->
      <div class="row mb-3">
        <label for="role" class="col-sm-3 col-form-label fw-bold">Role</label>
        <div class="col-sm-9">
          <select id="role" name="role" class="form-select">
            <?php foreach ($roles_list as $r): ?>
              <option value="<?=$r?>" <?=($account['role'] === $r ? 'selected' : '')?>>
                <?=$r?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Registered Date -->
      <div class="row mb-3">
        <label for="registered" class="col-sm-3 col-form-label fw-bold">Registered Date</label>
        <div class="col-sm-9">
          <input 
            type="datetime-local" 
            id="registered" 
            name="registered"
            class="form-control"
            value="<?=date('Y-m-d\TH:i', strtotime($account['registered']))?>"
            required
          >
        </div>
      </div>

      <!-- Last Seen Date -->
      <div class="row mb-3">
        <label for="last_seen" class="col-sm-3 col-form-label fw-bold">Last Seen Date</label>
        <div class="col-sm-9">
          <input 
            type="datetime-local" 
            id="last_seen" 
            name="last_seen"
            class="form-control"
            value="<?=date('Y-m-d\TH:i', strtotime($account['last_seen']))?>"
            required
          >
        </div>
      </div>

      <!-- Form Buttons -->
      <div class="d-flex justify-content-between">
        <a href="?section=accounts" class="btn btn-secondary">
          <i class="fas fa-ban"></i> Cancel
        </a>
        <div>
          <?php if ($pageType === 'Edit'): ?>
            <button 
              type="submit" 
              name="delete" 
              class="btn btn-danger me-2"
              onclick="return confirm('Are you sure you want to delete this account?')"
            >
              <i class="fas fa-trash-alt"></i> Delete
            </button>
          <?php endif; ?>
          <button type="submit" name="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Save
          </button>
        </div>
      </div>
    </form>



  <?php elseif ($section === 'roles'): ?>
    <!-- ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
         4) ROLES
         ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ -->
    <h3>Roles Overview</h3>
    <div class="table-responsive">
      <table class="table table-striped table-hover">
        <thead class="table-dark">
          <tr>
            <th>Role</th>
            <th>Total Accounts</th>
            <th>Active Accounts</th>
            <th>Inactive Accounts</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($roles)): ?>
            <tr>
              <td colspan="4" class="text-center">No roles found.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($roles as $k => $v): ?>
              <tr>
                <td><?=$k?></td>
                <td>
                  <a href="?section=accounts&role=<?=$k?>" class="link-primary">
                    <?=number_format($v)?>
                  </a>
                </td>
                <td>
                  <a href="?section=accounts&role=<?=$k?>&status=active" class="link-primary">
                    <?=number_format($roles_active[$k] ?? 0)?>
                  </a>
                </td>
                <td>
                  <a href="?section=accounts&role=<?=$k?>&status=inactive" class="link-primary">
                    <?=number_format($roles_inactive[$k] ?? 0)?>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  <?php elseif ($section === 'email'): ?>
    <!-- ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
         5) EMAIL TEMPLATES
         ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ -->
    <h3>Email Templates</h3>
    <form action="?section=email" method="post">
      <div class="mb-3">
        <label for="activation_email_template" class="form-label fw-bold">
          Activation Email Template
        </label>
        <textarea name="activation_email_template" id="activation_email_template"
                  rows="6" class="form-control"><?=htmlspecialchars($activation_email_template)?></textarea>
      </div>
      <div class="mb-3">
        <label for="twofactor_email_template" class="form-label fw-bold">
          Two-factor Email Template
        </label>
        <textarea name="twofactor_email_template" id="twofactor_email_template"
                  rows="6" class="form-control"><?=htmlspecialchars($twofactor_email_template)?></textarea>
      </div>
      <div class="mb-3">
        <label for="resetpass_email_template" class="form-label fw-bold">
          Reset Password Email Template
        </label>
        <textarea name="resetpass_email_template" id="resetpass_email_template"
                  rows="6" class="form-control"><?=htmlspecialchars($resetpass_email_template)?></textarea>
      </div>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save"></i> Save
      </button>
    </form>

    <?php elseif ($section === 'settings'): ?>
    <!-- ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
         6) SETTINGS
         ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ -->

    <h3 class="mb-4">
        <i class="fas fa-cog me-2"></i> Site/Config Settings
    </h3>

    <form action="?section=settings" method="post" class="mb-5">
        <div class="card">
            <!-- Card Header -->
            <div class="card-header bg-dark text-white">
                <i class="fas fa-sliders-h me-1"></i> Manage Configuration
            </div>

            <!-- Card Body -->
            <div class="card-body">
                <!-- If you still want to keep the "tabs" (though they're hidden by default) -->
                <?php format_tabs($contents); ?>

                <!-- The dynamic settings form -->
                <?php format_form($contents); ?>
            </div>

            <!-- Card Footer: Save button -->
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
        </div>
    </form>

    <script>
    // For checkboxes in the settings form
    document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        checkbox.onclick = () => {
            checkbox.value = checkbox.checked ? 'true' : 'false';
        };
    });
    </script>

<?php endif; ?>

</div>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
