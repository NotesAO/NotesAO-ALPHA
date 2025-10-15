<?php
ob_start(); // Start output buffering

// Enable error reporting and logging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/home/notesao/public_html/login.error.log');

/**
 * Map a short clinic name (like "sandbox" or "ffltest")
 * to the clinic's folder AND domain name.
 */
function parse_clinic_folder($clinic_short) {
    // Only short keys allowed now:
    $clinic_map = [
        'sandbox' => [
            'folder' => 'sandbox',
            'domain' => 'sandbox.notesao.com'
        ],
        'ffl' => [
            'folder' => 'ffltest',
            'domain' => 'ffl.notesao.com'
        ],
        'dwag' => [
            'folder' => 'dwag',
            'domain' => 'dwag.notesao.com'
        ],
        'transform' => [
            'folder' => 'transform',
            'domain' => 'transform.notesao.com'
        ],
        'saf' => [
            'folder' => 'safatherhood',
            'domain' => 'safatherhood.notesao.com'
        ],
        'ctc' => [
            'folder' => 'ctc',
            'domain' => 'ctc.notesao.com'
        ],
        'tbo' => [
            'folder' => 'bestoption',
            'domain' => 'tbo.notesao.com'
        ],
        'admin' => [
            'folder' => 'adminclinic',
            'domain' => 'admin.notesao.com'
        ],
        'sage' => [
            'folder' => 'sage',
            'domain' => 'sage.notesao.com'
        ],
        'lal' => [
            'folder' => 'lankford',
            'domain' => 'lal.notesao.com'
        ],
        'saferpath' => [
            'folder' => 'saferpath',
            'domain' => 'saferpath.notesao.com'
        ],
        'lakeview' => [
            'folder' => 'lakeview',
            'domain' => 'lakeview.notesao.com'
        ]
    ];

    // Return the array (folder + domain) if found, otherwise null
    return $clinic_map[$clinic_short] ?? null;
}

$error = ''; // Holds any error messages for display above the form

$timeout_msg = '';
if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $timeout_msg = 'Your session expired due to inactivity. Please log in again.';
}

$activation_msg = '';
if (isset($_GET['activated']) && $_GET['activated'] == '1') {
    $activation_msg = 'Your account has been activated – please log in below.';
}


// ----------------------------------------------
// Auth flow control
// - POST: credential login
// - GET + rememberme (no timeout): auto-login
// ----------------------------------------------
$skip_remember_for_timeout = (isset($_GET['timeout']) && $_GET['timeout'] === '1');

// Common holders
$clinic_folder = '';
$clinic_domain = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ---------- POST LOGIN ----------
    $username       = $_POST['username'] ?? '';
    $password       = $_POST['password'] ?? '';
    $entered_clinic = $_POST['clinic']   ?? '';

    if (!$entered_clinic) {
        $error = 'Please enter a clinic.';
    } else {
        $clinic_info = parse_clinic_folder($entered_clinic);
        if (!$clinic_info) {
            error_log("Invalid clinic entered: $entered_clinic");
            $error = 'Invalid clinic. Please enter a valid short name (e.g. "sandbox").';
        } else {
            $clinic_folder = $clinic_info['folder'];
            $clinic_domain = $clinic_info['domain'];
            $_SESSION['clinic_folder'] = $clinic_folder;
            $_SESSION['clinic_domain'] = $clinic_domain;

            // Persist last clinic for future cookie-based logins
            setcookie('last_clinic', $entered_clinic, [
                'expires'  => time() + 86400 * 30,
                'path'     => '/',
                'domain'   => '.notesao.com',
                'secure'   => true,
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
    }

    if (!$error && $clinic_folder) {
        $auth_file_path = "/home/notesao/$clinic_folder/public_html/auth.php";
        if (file_exists($auth_file_path) && is_readable($auth_file_path)) {
            include_once $auth_file_path;
            error_log("Auth file included successfully from: $auth_file_path");
        } else {
            error_log("Configuration for this clinic is not available at path: $auth_file_path");
            $error = "Configuration for this clinic is not available.";
        }

        if (!$error && (!isset($con) || !($con instanceof mysqli))) {
            $inc = $GLOBALS['__auth_db_included'] ?? 'none';
            error_log("Clinic DB handle \$con missing after including $auth_file_path; auth included='$inc'");
            $error = "Clinic database configuration is invalid. Please contact support.";
        }

        // ----- Credential check -----
        if (!$error && $username && $password) {
            $stmt = $con->prepare('SELECT id, password, role FROM accounts WHERE username = ?');

            $stmt->bind_param('s', $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->bind_result($id, $hashed_password, $role);
                $stmt->fetch();


                if (password_verify($password, $hashed_password)) {
                    // Successful login
                    $_SESSION = [];
                    session_regenerate_id(true);
                    $_SESSION['loggedin']  = true;
                    $_SESSION['user_id']   = $id;
                    $_SESSION['username']  = $username;

                    $_SESSION['name']          = $username;   // navbar expects 'name'
                    $_SESSION['role']          = $role;       // fix Admin check in navbar
                    $_SESSION['last_activity'] = time();      // good hygiene for timeout logic


                    // Re-seed clinic identity for check_loggedin()
                    $_SESSION['clinic_folder'] = $clinic_folder;
                    $_SESSION['appname']       = defined('APPNAME') ? APPNAME : ('NotesAO-' . $clinic_folder);
                    $_SESSION['last_activity'] = time();


                    // Remember-me on user request (hidden input always 1 in your form)
                    if (isset($_POST['rememberme'])) {
                        $cookiehash = password_hash($id . $username . 'your_secret_key', PASSWORD_DEFAULT);
                        setcookie('rememberme', $cookiehash, [
                            'expires'  => time() + 86400 * 30,
                            'path'     => '/',
                            'domain'   => '.notesao.com',
                            'secure'   => true,
                            'httponly' => true,
                            'samesite' => 'Lax',
                        ]);
                        $u = $con->prepare('UPDATE accounts SET rememberme = ? WHERE id = ?');
                        $u->bind_param('si', $cookiehash, $id);
                        $u->execute();
                        $u->close();
                    }

                    error_log("Login successful. Session set with user ID: " . $_SESSION['user_id']);
                    header("Location: https://$clinic_domain/home.php", true, 303);

                    exit;
                } else {
                    error_log('Incorrect username or password.');
                    $error = 'Incorrect username or password.';
                }
            } else {
                error_log("No such user found.");
                $error = 'Incorrect username or password.';
            }
            $stmt->close();
        }
    }

} elseif (!$skip_remember_for_timeout && isset($_COOKIE['rememberme']) && isset($_COOKIE['last_clinic'])) {
    // ---------- GET + REMEMBERME (AUTO-LOGIN) ----------
    error_log("Remember me + last_clinic cookie detected.");
    $clinic_info = parse_clinic_folder($_COOKIE['last_clinic']);
    if ($clinic_info) {
        $clinic_folder = $clinic_info['folder'];
        $clinic_domain = $clinic_info['domain'];
        $_SESSION['clinic_folder'] = $clinic_folder;
        $_SESSION['clinic_domain'] = $clinic_domain;

        $auth_file_path = "/home/notesao/$clinic_folder/public_html/auth.php";
        if (file_exists($auth_file_path) && is_readable($auth_file_path)) {
            include_once $auth_file_path;
            error_log("Auth file included successfully from: $auth_file_path");
        } else {
            error_log("Configuration for this clinic is not available at path: $auth_file_path");
            $error = "Configuration for this clinic is not available.";
        }

        if (!$error && (!isset($con) || !($con instanceof mysqli))) {
            $inc = $GLOBALS['__auth_db_included'] ?? 'none';
            error_log("Clinic DB handle \$con missing after including $auth_file_path; auth included='$inc'");
            $error = "Clinic database configuration is invalid. Please contact support.";
        }

        if (!$error) {
            $stmt = $con->prepare('SELECT id, username, role FROM accounts WHERE rememberme = ?');
            $stmt->bind_param('s', $_COOKIE['rememberme']);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $stmt->bind_result($id, $username, $role);
                $stmt->fetch();
                session_regenerate_id(true);
                $_SESSION['loggedin']  = true;
                $_SESSION['user_id']   = $id;
                $_SESSION['username']  = $username;
                $_SESSION['role']      = $role;

                $_SESSION['name'] = $username; // navbar uses this


                $_SESSION['clinic_folder'] = $clinic_folder; // ensure clinic isolation is satisfied
                $_SESSION['appname']       = defined('APPNAME') ? APPNAME : ('NotesAO-' . $clinic_folder);
                $_SESSION['last_activity'] = time();


                error_log("Remembered user logged in. Session ID: " . $_SESSION['user_id']);
                header("Location: https://$clinic_domain/home.php");
                exit;
            }
            $stmt->close();
        }
    } else {
        $error = 'Invalid clinic in cookie. Please re-enter clinic.';
    }
}
// (else: plain GET → show form)


// ====================
// HTML Login Form
// ====================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NotesAO Login</title>
    <!-- Favicon and App Icons -->
    <link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicons/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="96x96" href="/favicons/favicon-96x96.png">
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg">

    <!-- Safari (macOS pinned tabs) -->
    <link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#211c56">

    <!-- iOS Home Screen Icons -->
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
    <link rel="apple-touch-icon" sizes="167x167" href="/favicons/apple-touch-icon-ipad-pro.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/favicons/apple-touch-icon-ipad.png">
    <link rel="apple-touch-icon" sizes="120x120" href="/favicons/apple-touch-icon-120x120.png">

    <!-- Manifest (Android/Chrome) -->
    <link rel="manifest" href="/favicons/site.webmanifest">
    <meta name="apple-mobile-web-app-title" content="NotesAO">

    <style type="text/css">
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        body { 
            min-height:100vh;
            display:flex;                 /* column layout:  card | footer */
            flex-direction:column;
            align-items:center;           /* centres card horizontally      */
            font-family:Arial,Helvetica,sans-serif;
            background:linear-gradient(to bottom right,#f0f4f8,#d9e4ea);
            text-align:center;
        }

        .content{
            flex:1;                       /* takes remaining height         */
            display:flex;
            align-items:center;           /* vertical centring              */
            justify-content:center;       /* horizontal centring            */
            width:100%;
        }

        .login-card { 
            background:#fff;
            padding:30px;
            border-radius:15px;
            box-shadow:0 8px 16px rgba(0,0,0,.2);
            align-items:center;
            display:flex;
            flex-direction:column;
            width:100%;
            max-width:350px;
            text-align:center;
        }
        img { 
            max-width: 150px; 
            margin-bottom: 20px; 
            cursor: pointer; 
        }
        button { 
            padding:15px 25px;
            font-size:18px;
            font-weight:bold;
            color:#fff;
            background:#211c56;
            border:none;
            border-radius:8px;
            cursor:pointer;
            transition:background .3s,transform .2s;


        }
        button:hover { 
            background-color: rgb(56, 48, 143); 
            transform: scale(1.05); 
        }
        .error-message {
            color: red;
            margin-bottom: 10px;
            font-weight: bold;
        }

        /* ========== COOKIE-CONSENT STYLES ========== */
        #cookie-bar{
        position:fixed;left:0;right:0;bottom:0;z-index:1000;
        background:#211c56;color:#fff;box-shadow:0 -4px 12px rgba(0,0,0,.15);
        transform:translateY(100%);transition:transform .4s ease;
        padding:1rem 1.5rem;display:flex;flex-wrap:wrap;gap:1rem;font-size:.95rem;line-height:1.5;
        }
        #cookie-bar.show{transform:translateY(0);}
        #cookie-bar button{border:none;border-radius:4px;padding:.5rem 1.25rem;font-weight:600;cursor:pointer;}
        #btn-accept{background:#00d08b;color:#000;}
        #btn-reject{background:#f8f9fa;color:#211c56;}
        #btn-settings{background:#ffc107;color:#211c56;}
        a.cookie-policy{color:#fff;text-decoration:underline;}
        #cookie-fab{
        position:fixed;bottom:1rem;right:1rem;z-index:1000;display:none;
        width:44px;height:44px;border-radius:50%;background:#211c56;color:#fff;font-size:20px;
        align-items:center;justify-content:center;cursor:pointer;
        box-shadow:0 2px 6px rgba(0,0,0,.2);
        }
        #cookie-fab.show{display:flex;}


        /* Footer (full-width & sticky) */
        footer{
            background:#1c1c1c;
            color:#fff;
            padding:2rem;
            text-align:center;
            width:100%;                 /* spans viewport, padding included */
            margin-top:auto;            /* sticks to bottom of column       */
        }

        footer .footer-links{margin-bottom:1rem;}
        footer .footer-links a{margin:0 1rem;color:#ccc;font-size:.9rem;transition:color .3s;text-decoration:none;}
        footer .footer-links a:hover{color:#fff;}
        footer p{font-size:.9rem;color:#ccc;}

    </style>
    <!-- Bootstrap 5 core CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet" crossorigin="anonymous">

    <!-- Font Awesome (icon for the floating cookie button) -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        integrity="sha512-pjxj7YJ7fYJODva4u10m2uUtSQxEp9QeJk+zYsO19N7pskZsdf0WqW4M5a8a5YybmUR2Pt3rX+sFye83IGn3Eg=="
        crossorigin="anonymous" referrerpolicy="no-referrer">

</head>
<body>
    <main class="content">
        <div class="login-card">
            <a href="https://notesao.com">
                <img alt="NotesAO Logo" src="logo.png" />
            </a>
            <h2>Login</h2>
            <?php if ($timeout_msg): ?>
                <div class="alert alert-warning w-100" role="alert">
                    <?= htmlspecialchars($timeout_msg) ?>
                </div>
            <?php endif; ?>
            <?php if ($activation_msg): ?>
                <div class="alert alert-success w-100" role="alert">
                    <?= htmlspecialchars($activation_msg) ?>
                </div>
            <?php endif; ?>


            <?php if (!empty($error)): ?>
                <p class="error-message"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            
            <form action="" method="post">
                <label for="username">Username:</label><br>
                <input id="username" type="text" name="username" required><br><br>

                <label for="password">Password:</label><br>
                <input id="password" type="password" name="password" required><br><br>

                <!-- Only short name allowed: "sandbox", "ffl", etc. -->
                <label for="clinic">Clinic:</label><br>
                <input
                    id="clinic"
                    type="text"
                    name="clinic"
                    placeholder="e.g. sandbox"
                    required
                >
                <br><br>

                <!-- Hidden "remember me" always set to 1 -->
                <input type="hidden" name="rememberme" id="rememberme" value="1">

                <button type="submit">Login</button>

                <p class="mt-3"><a href="/forgot_password.php">Forgot password?</a></p>

            </form>

        </div>
    </main>
    
    <!-- Footer -->
    <footer>
    <div class="footer-links">
      <a href="/">Home</a>
      <a href="/login.php">Login</a>
      <a href="/signup.php">Sign Up</a>
      <a href="/legal/privacy.html">Privacy Policy</a>
      <a href="/legal/terms.html">Terms of Service</a>
      <a href="/legal/accessibility.html">Accessibility</a>
      <a href="/legal/security.html">Security</a>
    </div>
    <p>2025 NotesAO. All rights reserved.</p>
  </footer>
    
    <!-- Cookie consent bar -->
    <div id="cookie-bar" role="dialog" aria-label="Cookie consent">
    <span>We use cookies to keep you signed in and to improve your experience. Choose an option:</span>
    <div class="ms-auto d-flex gap-2 flex-wrap">
        <button id="btn-reject">Reject non-essential</button>
        <button id="btn-settings">Customise</button>
        <button id="btn-accept">Accept all</button>
    </div>
    <a href="/legal/privacy.html" class="cookie-policy ms-3">Privacy policy</a>
    </div>

    <!-- Manage-cookies floating button -->
    <div id="cookie-fab" title="Cookie settings"><i class="fas fa-cookie-bite"></i></div>

    <!-- Settings modal (Bootstrap 5) -->
    <div class="modal fade" id="cookieModal" tabindex="-1" aria-labelledby="cookieModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
        <div class="modal-header bg-light">
            <h5 class="modal-title" id="cookieModalLabel">Customise cookies</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <p class="small">Strictly-necessary cookies are always on (they keep you logged-in and secure).</p>
            <div class="form-check">
            <input class="form-check-input" type="checkbox" id="ck-analytics">
            <label class="form-check-label fw-semibold" for="ck-analytics">Analytics cookies</label>
            </div>
            <div class="form-check">
            <input class="form-check-input" type="checkbox" id="ck-marketing">
            <label class="form-check-label fw-semibold" for="ck-marketing">Marketing / remarketing cookies</label>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-brand" id="btn-save-pref">Save preferences</button>
        </div>
        </div>
    </div>
    </div>

</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

<script>
(()=>{                           // self-contained IIFE
  const key='notesaoConsentV1';
  const bar=document.getElementById('cookie-bar');
  const fab=document.getElementById('cookie-fab');
  const modal=new bootstrap.Modal(document.getElementById('cookieModal'));

  const save=p=>localStorage.setItem(key,JSON.stringify(p));
  const load=()=>JSON.parse(localStorage.getItem(key)||'{}');

  const pref=load();
  if(!pref.choice){bar.classList.add('show');}
  else{fab.classList.add('show');applyConsent(pref);}

  // Accept all
  document.getElementById('btn-accept').onclick=()=>{
    const p={choice:'all',analytics:true,marketing:true};
    save(p);bar.classList.remove('show');fab.classList.add('show');applyConsent(p);
  };

  // Reject
  document.getElementById('btn-reject').onclick=()=>{
    const p={choice:'necessary',analytics:false,marketing:false};
    save(p);bar.classList.remove('show');fab.classList.add('show');applyConsent(p);
  };

  // Settings open
  document.getElementById('btn-settings').onclick=()=>{
    document.getElementById('ck-analytics').checked=!!pref.analytics;
    document.getElementById('ck-marketing').checked=!!pref.marketing;
    modal.show();
  };

  // Save custom
  document.getElementById('btn-save-pref').onclick=()=>{
    const p={
      choice:'custom',
      analytics:document.getElementById('ck-analytics').checked,
      marketing:document.getElementById('ck-marketing').checked
    };
    save(p);modal.hide();bar.classList.remove('show');fab.classList.add('show');applyConsent(p);
  };

  fab.onclick=()=>modal.show();

  function applyConsent(p){
    /* hook your GA / FB loaders here
       if(p.analytics){ loadGoogleAnalytics(); } */
  }
})();
</script>

</html>
