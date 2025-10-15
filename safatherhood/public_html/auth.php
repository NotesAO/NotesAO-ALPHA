<?php
declare(strict_types=1);

/**
 * SAFatherhood auth guard
 * Path: /home/notesao/safatherhood/public_html/auth.php
 * Responsibilities:
 *  - Find & include the clinic DB config, normalize handle to $con (mysqli)
 *  - Start session safely (no cookie-param warnings)
 *  - Enforce idle timeout + redirect to central login on expiry
 *  - Provide check_loggedin($con) for protected pages/APIs
 */

/* ===== Back-compat & clinic constants ===== */
$appname = 'SAFatherhood'; // some pages expect $appname
if (!defined('APPNAME'))       define('APPNAME', $appname);
if (!defined('CLINIC_FOLDER')) define('CLINIC_FOLDER', 'safatherhood');

/* ===== Locate & include the clinic DB config =====
   Different clinics sometimes place config in different paths.
   We try a small set of candidates and record which one we loaded.
*/
$__auth_db_candidates = [
    __DIR__ . '/../config/config.php',                 // e.g., /home/notesao/safatherhood/config/config.php
    __DIR__ . '/../includes/config.php',               // includes-style
    __DIR__ . '/../includes/db.php',
    '/home/notesao/safatherhood/config/config.php',    // absolute safety nets
    '/home/notesao/safatherhood/public_html/config/config.php',
];

$GLOBALS['__auth_db_included'] = null;
foreach ($__auth_db_candidates as $cand) {
    if (is_readable($cand)) {
        require_once $cand;
        $GLOBALS['__auth_db_included'] = $cand;
        break;
    }
}

/* ===== Normalize / discover DB handle as $con (mysqli) ===== */

// 1) Common variable names from included config
if (!isset($con) || !($con instanceof mysqli)) {
    if (isset($conn)   && $conn   instanceof mysqli) { $con = $conn;   $GLOBALS['__auth_db_found'] = 'var:$conn'; }
    elseif (isset($mysqli) && $mysqli instanceof mysqli) { $con = $mysqli; $GLOBALS['__auth_db_found'] = 'var:$mysqli'; }
}

// 2) Scan all defined variables for a mysqli instance (defensive)
if (!isset($con) || !($con instanceof mysqli)) {
    foreach (get_defined_vars() as $__k => $__v) {
        if ($__v instanceof mysqli) { $con = $__v; $GLOBALS['__auth_db_found'] = "scan:\${$__k}"; break; }
    }
}

// 3) If still missing, try functions commonly used to return a handle
if (!isset($con) || !($con instanceof mysqli)) {
    $funcs = ['db_connect', 'get_db_connection', 'getMysqli', 'connect_db'];
    foreach ($funcs as $fn) {
        if (function_exists($fn)) {
            try { $tmp = @$fn(); if ($tmp instanceof mysqli) { $con = $tmp; $GLOBALS['__auth_db_found'] = "fn:$fn()"; break; } } catch (Throwable $e) {}
        }
    }
}

// 4) If still missing, try to build mysqli from common constants or arrays
if (!isset($con) || !($con instanceof mysqli)) {
    // Constants (very common)
    $host = defined('DB_HOST')      ? DB_HOST      : (defined('MYSQL_HOST')      ? MYSQL_HOST      : null);
    $user = defined('DB_USER')      ? DB_USER      : (defined('MYSQL_USER')      ? MYSQL_USER      : null);
    $pass = defined('DB_PASSWORD')  ? DB_PASSWORD  : (defined('DB_PASS')         ? DB_PASS         : (defined('MYSQL_PASSWORD') ? MYSQL_PASSWORD : (defined('MYSQL_PASS') ? MYSQL_PASS : null)));
    $name = defined('DB_NAME')      ? DB_NAME      : (defined('MYSQL_DB')        ? MYSQL_DB        : (defined('MYSQL_DATABASE') ? MYSQL_DATABASE : null));

    // Arrays (other common shapes)
    $candidates = [];
    foreach (['db', 'database', 'db_config', 'config', 'mysql', 'dbParams'] as $arrName) {
        if (isset(${$arrName}) && is_array(${$arrName})) $candidates[] = ${$arrName};
        if (isset($GLOBALS[$arrName]) && is_array($GLOBALS[$arrName])) $candidates[] = $GLOBALS[$arrName];
    }
    foreach ($candidates as $arr) {
        $host = $host ?? ($arr['host'] ?? $arr['hostname'] ?? $arr['server'] ?? null);
        $user = $user ?? ($arr['user'] ?? $arr['username'] ?? null);
        $pass = $pass ?? ($arr['pass'] ?? $arr['password'] ?? null);
        $name = $name ?? ($arr['name'] ?? $arr['dbname'] ?? $arr['database'] ?? null);
    }

    if ($host && $user && $name) {
        try {
            $tmp = @new mysqli($host, $user, (string)$pass, $name);
            if ($tmp instanceof mysqli && !$tmp->connect_errno) {
                $con = $tmp;
                $GLOBALS['__auth_db_found'] = 'fallback:constructed_from_config';
            } else {
                if ($tmp instanceof mysqli && $tmp->connect_errno) {
                    error_log('SAFatherhood auth.php: mysqli connect error ' . $tmp->connect_errno . ' ' . $tmp->connect_error);
                }
            }
        } catch (Throwable $e) {
            error_log('SAFatherhood auth.php: mysqli throw on connect: ' . $e->getMessage());
        }
    }
}

// 5) Final log if still missing (no redirect here; login.php will surface the error)
if (!isset($con) || !($con instanceof mysqli)) {
    $inc = var_export($GLOBALS['__auth_db_included'], true);
    $found = $GLOBALS['__auth_db_found'] ?? 'none';
    error_log('SAFatherhood auth.php: DB handle still missing. Included=' . $inc . ' found=' . $found
        . ' Candidates tried: ' . implode(', ', $__auth_db_candidates));
}


/* ===== Session bootstrap (guarded) ===== */
if (!defined('SESSION_TTL')) {
    define('SESSION_TTL', 3600); // 1 hour
}
if (!defined('COOKIE_DOMAIN')) {
    define('COOKIE_DOMAIN', '.notesao.com');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    // If you want a custom session name, set it here BEFORE start (optional):
    // ini_set('session.name', 'NOTESAOSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => COOKIE_DOMAIN,
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'None',
    ]);
    session_start();
}

// ---- Passive heartbeat: do not refresh last_activity here ----
if (isset($_GET['__ping'])) {
    header('Cache-Control: no-store');
    $now  = time();
    $last = (int)($_SESSION['last_activity'] ?? 0);
    $expired = ($last && ($now - $last) > SESSION_TTL);

    if (empty($_SESSION['loggedin']) || $expired) {
        http_response_code(401);
    } else {
        http_response_code(204); // No Content = still logged in
    }
    exit;
}



/* ===== Helpers ===== */
function is_ajax_request(): bool {
    return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}

function force_logout_and_redirect(bool $is_timeout = false): void {
    // We may need DB access to clear rememberme; pull the global if available
    global $con;

    // capture before wiping session
    $remember = $_COOKIE['rememberme'] ?? null;
    $uid      = $_SESSION['user_id']   ?? null;

    // On timeout, clear remember-me so credentials are required
    if ($is_timeout && $remember) {
        // Clear cookie in browser
        setcookie('rememberme', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => '.notesao.com',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // Best effort: clear token in DB
        if (isset($con) && $con instanceof mysqli) {
            if ($uid) {
                if ($stmt = $con->prepare('UPDATE accounts SET rememberme = NULL WHERE id = ?')) {
                    $stmt->bind_param('i', $uid);
                    $stmt->execute();
                    $stmt->close();
                }
            } elseif ($remember) {
                if ($stmt = $con->prepare('UPDATE accounts SET rememberme = NULL WHERE rememberme = ?')) {
                    $stmt->bind_param('s', $remember);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }

    // wipe session state (don’t change cookie params here; session is already active)
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    @session_destroy();

    if (is_ajax_request()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'reason' => $is_timeout ? 'timeout' : 'not_authenticated']);
        exit;
    }

    $login_url = 'https://notesao.com/login.php' . ($is_timeout ? '?timeout=1' : '');
    header('Location: ' . $login_url);
    exit;
}


/**
 * check_loggedin
 * Call at the top of every protected page/API in this clinic.
 *   include_once __DIR__.'/auth.php';
 *   check_loggedin($con);
 */
function check_loggedin(mysqli $con, string $redirect_file = 'index.php'): void
{
    $now = time();

    /* If DB handle is missing at runtime, fail safe (prevents fatals on $con->prepare) */
    if (!($con instanceof mysqli)) {
        error_log('check_loggedin: $con missing or invalid; forcing re-auth.');
        force_logout_and_redirect(false);
    }

    /* Guard against sessions from other clinics/apps */
    if (isset($_SESSION['loggedin']) && (!isset($_SESSION['appname']) || $_SESSION['appname'] !== APPNAME)) {
        force_logout_and_redirect(false);
    }

    /* Restore from remember-me cookie if present and not logged in */
    if (empty($_SESSION['loggedin']) && !empty($_COOKIE['rememberme'])) {
        if ($stmt = $con->prepare('SELECT id, username, role FROM accounts WHERE rememberme = ? LIMIT 1')) {
            $stmt->bind_param('s', $_COOKIE['rememberme']);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $stmt->bind_result($id, $username, $role);
                    $stmt->fetch();
                    if (!headers_sent()) {
                        session_regenerate_id(true);
                    }
                    $_SESSION['loggedin']      = true;
                    $_SESSION['name']          = (string)$username;
                    $_SESSION['user_id']       = (int)$id;
                    $_SESSION['role']          = (string)$role;
                    $_SESSION['appname']       = APPNAME;
                    $_SESSION['clinic_folder'] = CLINIC_FOLDER;
                    $_SESSION['last_activity'] = $now;
                }
            }
            $stmt->close();
        }
    }

    /* If still not logged in → central login */
    if (empty($_SESSION['loggedin'])) {
        force_logout_and_redirect(false);
    }

    /* Enforce clinic isolation */
    if (empty($_SESSION['clinic_folder'])) {
        $_SESSION['clinic_folder'] = CLINIC_FOLDER;
    } elseif ($_SESSION['clinic_folder'] !== CLINIC_FOLDER) {
        force_logout_and_redirect(false);
    }

    /* Idle-timeout enforcement */
    $last = isset($_SESSION['last_activity']) ? (int)$_SESSION['last_activity'] : 0;
    if ($last && ($now - $last) > SESSION_TTL) {
        force_logout_and_redirect(true);
    }
    $_SESSION['last_activity'] = $now;

    /* Update last_seen */
    if (isset($_SESSION['user_id'])) {
        $seen = date('Y-m-d\TH:i:s');
        if ($stmt = $con->prepare('UPDATE accounts SET last_seen = ? WHERE id = ?')) {
            $uid = (int)$_SESSION['user_id'];
            $stmt->bind_param('si', $seen, $uid);
            $stmt->execute();
            $stmt->close();
        }
    }

    /* Ensure program_id and program_name exist in session */
    if (empty($_SESSION['program_id'])) {
        $res = $con->query('SELECT id, name FROM program ORDER BY id ASC LIMIT 1');
        if ($res && ($row = $res->fetch_assoc())) {
            $_SESSION['program_id']   = (int)$row['id'];
            $_SESSION['program_name'] = (string)$row['name'];
        } else {
            $_SESSION['program_id']   = 1;
            $_SESSION['program_name'] = 'Program #1';
        }
        if ($res) { $res->free(); }
    }

    if (empty($_SESSION['program_name'])) {
        if ($stmt = $con->prepare('SELECT name FROM program WHERE id = ? LIMIT 1')) {
            $pid = (int)$_SESSION['program_id'];
            $stmt->bind_param('i', $pid);
            if ($stmt->execute()) {
                $stmt->bind_result($pname);
                if ($stmt->fetch()) {
                    $_SESSION['program_name'] = (string)$pname;
                }
            }
            $stmt->close();
        }
        if (empty($_SESSION['program_name'])) {
            $_SESSION['program_name'] = 'Unknown Program id=' . (int)$_SESSION['program_id'];
        }
    }
}

/* ===== Suggested usage in JSON endpoints =====
require_once __DIR__.'/auth.php';
check_loggedin($con); // will send 401 JSON if AJAX + expired
================================================ */
