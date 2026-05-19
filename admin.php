<?php
require_once 'config.php';
$action = $_GET['action'] ?? '';

switch ($action) {

  case 'stats':
    admin_required();
    $db = db();
    $revenue   = $db->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status!='cancelled'")->fetchColumn();
    $orders    = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $customers = $db->query("SELECT COUNT(*) FROM users WHERE is_admin=0")->fetchColumn();
    $products  = $db->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn();
    $low       = $db->query("SELECT COUNT(*) FROM products WHERE stock<5 AND is_active=1")->fetchColumn();
    $recent    = $db->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 8")->fetchAll();
    json_out(compact('revenue','orders','customers','products','low','recent'));
    break;

  case 'customers':
    admin_required();
    $s = db()->query('SELECT u.id,u.first_name,u.last_name,u.email,u.phone,u.is_active,u.created_at,
      COUNT(o.id) as order_count, COALESCE(SUM(o.total),0) as spent
      FROM users u LEFT JOIN orders o ON o.user_id=u.id
      WHERE u.is_admin=0 GROUP BY u.id ORDER BY u.created_at DESC');
    json_out(['customers'=>$s->fetchAll()]);
    break;

  case 'toggle_user':
    admin_required();
    $d = body();
    $id = intval($d['id'] ?? 0);
    $active = intval($d['is_active'] ?? 0);
    db()->prepare('UPDATE users SET is_active=? WHERE id=? AND is_admin=0')->execute([$active, $id]);
    json_out(['success'=>true]);
    break;

  case 'delete_user':
    admin_required();
    $id = intval(body()['id'] ?? 0);
    db()->prepare('DELETE FROM users WHERE id=? AND is_admin=0')->execute([$id]);
    json_out(['success'=>true]);
    break;

  case 'inventory':
    admin_required();
    $s = db()->query('SELECT p.id,p.name,p.stock,p.price,p.emoji,c.name as cat FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_active=1 ORDER BY p.stock ASC');
    json_out(['inventory'=>$s->fetchAll()]);
    break;

  case 'restock':
    admin_required();
    $d = body();
    db()->prepare('UPDATE products SET stock=stock+? WHERE id=?')->execute([intval($d['qty']??0), intval($d['id']??0)]);
    json_out(['success'=>true]);
    break;

  case 'categories':
    admin_required();
    $s = db()->query('SELECT c.*, COUNT(p.id) as cnt FROM categories c LEFT JOIN products p ON p.category_id=c.id GROUP BY c.id');
    json_out(['categories'=>$s->fetchAll()]);
    break;

  case 'add_category':
    admin_required();
    $d = body();
    db()->prepare('INSERT INTO categories (name,slug,icon) VALUES (?,?,?)')->execute([clean($d['name']??''), clean($d['slug']??''), clean($d['icon']??'💍')]);
    json_out(['success'=>true]);
    break;

  default:
    json_out(['error'=>'Unknown action'], 404);
}
