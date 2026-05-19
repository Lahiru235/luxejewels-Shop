<?php
require_once 'config.php';
$action = $_GET['action'] ?? '';

switch ($action) {

  case 'register':
    $d = body();
    $fn = clean($d['first_name'] ?? '');
    $ln = clean($d['last_name']  ?? '');
    $em = filter_var($d['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $pw = $d['password'] ?? '';
    if (!$fn || !$ln || !$em || !$pw) json_out(['error'=>'All fields required'], 400);
    if (!filter_var($em, FILTER_VALIDATE_EMAIL)) json_out(['error'=>'Invalid email'], 400);
    if (strlen($pw) < 6) json_out(['error'=>'Password must be at least 6 characters'], 400);
    $db = db();
    $s = $db->prepare('SELECT id FROM users WHERE email=?');
    $s->execute([$em]);
    if ($s->fetch()) json_out(['error'=>'Email already registered'], 409);
    $hash = password_hash($pw, PASSWORD_BCRYPT);
    $db->prepare('INSERT INTO users (first_name,last_name,email,password_hash) VALUES (?,?,?,?)')->execute([$fn,$ln,$em,$hash]);
    $uid = $db->lastInsertId();
    $_SESSION['user_id'] = $uid;
    $_SESSION['is_admin'] = false;
    json_out(['success'=>true,'user'=>['id'=>$uid,'first_name'=>$fn,'last_name'=>$ln,'email'=>$em,'is_admin'=>false]]);
    break;

  case 'login':
    $d = body();
    $em = filter_var($d['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $pw = $d['password'] ?? '';
    if (!$em || !$pw) json_out(['error'=>'Email and password required'], 400);
    $db = db();
    $s = $db->prepare('SELECT * FROM users WHERE email=?');
    $s->execute([$em]);
    $u = $s->fetch();
    if (!$u || !password_verify($pw, $u['password_hash'])) json_out(['error'=>'Invalid email or password'], 401);
    if (!$u['is_active']) json_out(['error'=>'Account suspended'], 403);
    $_SESSION['user_id'] = $u['id'];
    $_SESSION['is_admin'] = (bool)$u['is_admin'];
    json_out(['success'=>true,'user'=>['id'=>$u['id'],'first_name'=>$u['first_name'],'last_name'=>$u['last_name'],'email'=>$u['email'],'is_admin'=>(bool)$u['is_admin']]]);
    break;

  case 'logout':
    session_destroy();
    json_out(['success'=>true]);
    break;

  case 'me':
    if (empty($_SESSION['user_id'])) json_out(['authenticated'=>false]);
    $s = db()->prepare('SELECT id,first_name,last_name,email,is_admin FROM users WHERE id=?');
    $s->execute([$_SESSION['user_id']]);
    json_out(['authenticated'=>true,'user'=>$s->fetch()]);
    break;

  case 'update_profile':
    $uid = auth_required();
    $d = body();
    $fn = clean($d['first_name'] ?? '');
    $ln = clean($d['last_name']  ?? '');
    $ph = clean($d['phone']      ?? '');
    if (!$fn || !$ln) json_out(['error'=>'Name required'], 400);
    db()->prepare('UPDATE users SET first_name=?, last_name=?, phone=? WHERE id=?')
       ->execute([$fn, $ln, $ph, $uid]);
    $s = db()->prepare('SELECT id,first_name,last_name,email,phone,is_admin FROM users WHERE id=?');
    $s->execute([$uid]);
    $u = $s->fetch();
    json_out(['success'=>true, 'user'=>$u]);
    break;

  case 'change_password':
    $uid = auth_required();
    $d   = body();
    $old = $d['old_password'] ?? '';
    $new = $d['new_password'] ?? '';
    if (!$old || !$new) json_out(['error'=>'Both passwords required'], 400);
    if (strlen($new) < 6) json_out(['error'=>'New password min 6 characters'], 400);
    $s = db()->prepare('SELECT password_hash FROM users WHERE id=?');
    $s->execute([$uid]);
    $row = $s->fetch();
    if (!password_verify($old, $row['password_hash'])) json_out(['error'=>'Current password is incorrect'], 401);
    $hash = password_hash($new, PASSWORD_BCRYPT);
    db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash, $uid]);
    json_out(['success'=>true, 'message'=>'Password changed successfully']);
    break;

  case 'reset_password':
    $d = body();
    $em = filter_var($d['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $np = $d['new_password'] ?? '';
    if (!$em || !$np) json_out(['error'=>'Email and new password required'], 400);
    if (strlen($np) < 6) json_out(['error'=>'Password min 6 characters'], 400);
    $s = db()->prepare('SELECT id FROM users WHERE email=?');
    $s->execute([$em]);
    $u = $s->fetch();
    if (!$u) json_out(['error'=>'No account found with this email'], 404);
    $hash = password_hash($np, PASSWORD_BCRYPT);
    db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash, $u['id']]);
    json_out(['success'=>true, 'message'=>'Password reset successfully']);
    break;

  default:
    json_out(['error'=>'Unknown action'], 404);
}
