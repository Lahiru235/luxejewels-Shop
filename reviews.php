<?php
require_once 'config.php';
$action = $_GET['action'] ?? '';

switch ($action) {

  case 'add':
    $uid = auth_required();
    $d = body();
    $pid = intval($d['product_id'] ?? 0);
    $rating = intval($d['rating'] ?? 0);
    if (!$pid || $rating < 1 || $rating > 5) json_out(['error'=>'Invalid data'], 400);
    // Get user name
    $u = db()->prepare('SELECT first_name, last_name FROM users WHERE id=?');
    $u->execute([$uid]); $user = $u->fetch();
    $name = $user ? $user['first_name'].' '.$user['last_name'] : 'Customer';
    db()->prepare('INSERT INTO reviews (product_id,user_id,customer_name,rating,comment) VALUES (?,?,?,?,?)')
       ->execute([$pid, $uid, $name, $rating, clean($d['comment']??'')]);
    json_out(['success'=>true,'message'=>'Review submitted for approval']);
    break;

  // Admin
  case 'all':
    admin_required();
    $s = db()->query('SELECT r.*, p.name as product_name FROM reviews r JOIN products p ON r.product_id=p.id ORDER BY r.created_at DESC');
    json_out(['reviews'=>$s->fetchAll()]);
    break;

  case 'approve':
    admin_required();
    db()->prepare('UPDATE reviews SET approved=1 WHERE id=?')->execute([intval(body()['id']??0)]);
    json_out(['success'=>true]);
    break;

  case 'delete':
    admin_required();
    db()->prepare('DELETE FROM reviews WHERE id=?')->execute([intval(body()['id']??0)]);
    json_out(['success'=>true]);
    break;

  default:
    json_out(['error'=>'Unknown action'], 404);
}
