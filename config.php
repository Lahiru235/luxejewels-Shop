<?php
// =============================================
// LUXE JEWELS — Database Config
// =============================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'luxejewels');

function db() {
    static $pdo = null;
    if (!$pdo) {
        try {
            $pdo = new PDO(
                'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
            );
        } catch (PDOException $e) {
            json_out(['error'=>'Database connection failed: '.$e->getMessage()], 500);
        }
    }
    return $pdo;
}

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Admin-Token, X-Admin-Email');
    echo json_encode($data);
    exit;
}

function body() {
    $raw = json_decode(file_get_contents('php://input'), true);
    return $raw ?? $_POST;
}

function clean($s) {
    return htmlspecialchars(strip_tags(trim((string)$s)));
}

function session_start_if() {
    if (session_status() === PHP_SESSION_NONE) session_start();
}

function auth_required() {
    session_start_if();
    if (!empty($_SESSION['user_id'])) return $_SESSION['user_id'];

    // Check email from any source
    $email = get_admin_email();
    if ($email) {
        $s = db()->prepare('SELECT id FROM users WHERE email=? AND is_active=1');
        $s->execute([$email]);
        $u = $s->fetch();
        if ($u) return $u['id'];
    }
    json_out(['error'=>'Login required'], 401);
}

function admin_required() {
    session_start_if();
    if (!empty($_SESSION['user_id']) && !empty($_SESSION['is_admin'])) return;

    // Get email from ANY possible source
    $email = get_admin_email();
    if ($email) {
        $s = db()->prepare('SELECT id FROM users WHERE email=? AND is_admin=1 AND is_active=1');
        $s->execute([$email]);
        if ($s->fetch()) return;
    }
    json_out(['error'=>'Admin access required'], 403);
}

// Gets admin email from header, GET param, POST body, or cookie
function get_admin_email() {
    $data = body();
    $email = $_SERVER['HTTP_X_ADMIN_EMAIL']
          ?? $_GET['_email']
          ?? $_POST['_email']
          ?? $data['_email']
          ?? $_COOKIE['admin_email']
          ?? '';
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { json_out(['ok'=>true]); }
session_start_if();
