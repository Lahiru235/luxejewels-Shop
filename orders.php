<?php
require_once 'config.php';
$action = $_GET['action'] ?? '';

switch ($action) {

  case 'place':
    $uid = auth_required();
    $d = body();
    $db = db();

    // Validate required fields
    if (empty($d['items']) || !is_array($d['items'])) json_out(['error'=>'No items'], 400);
    if (empty($d['customer_name']) || empty($d['customer_email']) || empty($d['address'])) json_out(['error'=>'Missing details'], 400);

    $payment_method = clean($d['payment_method'] ?? 'card');
    $allowed_methods = ['card','cod','bank_transfer','paypal'];
    if (!in_array($payment_method, $allowed_methods)) $payment_method = 'card';

    // Payment status: COD is pending payment; others are paid (simulated)
    $payment_status = ($payment_method === 'cod') ? 'pending' : 'paid';

    // Coupon validation
    $discount = 0;
    $coupon_code = '';
    if (!empty($d['coupon_code'])) {
      $coupon_code = strtoupper(clean($d['coupon_code']));
      $coupons = ['LUXE10'=>10, 'SAVE15'=>15, 'JEWEL20'=>20, 'WELCOME5'=>5];
      if (isset($coupons[$coupon_code])) {
        $discount = $coupons[$coupon_code];
      } else {
        json_out(['error'=>'Invalid coupon code'], 400);
      }
    }

    $subtotal = 0;
    foreach ($d['items'] as $item) {
      $p = $db->prepare('SELECT price FROM products WHERE id=? AND is_active=1');
      $p->execute([$item['id']]);
      $row = $p->fetch();
      if (!$row) json_out(['error'=>'Product not found: '.$item['id']], 400);
      $subtotal += $row['price'] * $item['qty'];
    }

    $shipping  = $subtotal >= 50 ? 0 : 4.99;
    $disc_amt  = round($subtotal * ($discount / 100), 2);
    $total     = max(0, $subtotal - $disc_amt + $shipping);
    $order_no  = 'LJ-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -5));

    $db->prepare('INSERT INTO orders
      (order_no,user_id,customer_name,customer_email,customer_phone,shipping_address,
       subtotal,discount,shipping,total,payment_method,payment_status,coupon_code)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
       ->execute([
         $order_no, $uid,
         clean($d['customer_name']), clean($d['customer_email']),
         clean($d['phone']??''), clean($d['address']),
         $subtotal, $disc_amt, $shipping, $total,
         $payment_method, $payment_status, $coupon_code
       ]);

    $oid = $db->lastInsertId();

    foreach ($d['items'] as $item) {
      $p = $db->prepare('SELECT name, price FROM products WHERE id=?');
      $p->execute([$item['id']]);
      $row = $p->fetch();
      $db->prepare('INSERT INTO order_items (order_id,product_id,product_name,price,qty) VALUES (?,?,?,?,?)')
         ->execute([$oid, $item['id'], $row['name'], $row['price'], $item['qty']]);
      $db->prepare('UPDATE products SET stock=GREATEST(0,stock-?) WHERE id=?')->execute([$item['qty'], $item['id']]);
    }

    json_out(['success'=>true,'order_no'=>$order_no,'order_id'=>$oid,'total'=>$total,
              'discount'=>$disc_amt,'payment_method'=>$payment_method,'payment_status'=>$payment_status]);
    break;

  case 'validate_coupon':
    auth_required();
    $d = body();
    $code = strtoupper(clean($d['code'] ?? ''));
    $coupons = ['LUXE10'=>10, 'SAVE15'=>15, 'JEWEL20'=>20, 'WELCOME5'=>5];
    if (isset($coupons[$code])) {
      json_out(['valid'=>true, 'discount'=>$coupons[$code], 'code'=>$code]);
    } else {
      json_out(['valid'=>false, 'error'=>'Invalid coupon code']);
    }
    break;

  case 'my_orders':
    $uid = auth_required();
    $s = db()->prepare('SELECT o.*, (SELECT COUNT(*) FROM order_items WHERE order_id=o.id) as item_count FROM orders o WHERE o.user_id=? ORDER BY o.created_at DESC');
    $s->execute([$uid]);
    json_out(['orders'=>$s->fetchAll()]);
    break;

  case 'order_detail':
    $uid = auth_required();
    $oid = intval($_GET['id'] ?? 0);
    $db = db();
    $s = $db->prepare('SELECT * FROM orders WHERE id=? AND user_id=?');
    $s->execute([$oid, $uid]);
    $o = $s->fetch();
    if (!$o) json_out(['error'=>'Not found'], 404);
    $items = $db->prepare('SELECT * FROM order_items WHERE order_id=?');
    $items->execute([$oid]);
    $o['items'] = $items->fetchAll();
    json_out(['order'=>$o]);
    break;

  case 'all':
    admin_required();
    $db = db();
    $where = []; $params = [];
    if (!empty($_GET['status'])) { $where[] = 'o.status=?'; $params[] = $_GET['status']; }
    $sql = 'SELECT o.* FROM orders o'.($where ? ' WHERE '.implode(' AND ',$where) : '').' ORDER BY o.created_at DESC LIMIT 100';
    $s = $db->prepare($sql); $s->execute($params);
    json_out(['orders'=>$s->fetchAll()]);
    break;

  case 'update_status':
    admin_required();
    $d = body();
    $allowed = ['pending','processing','shipped','delivered','cancelled'];
    if (!in_array($d['status']??'', $allowed)) json_out(['error'=>'Invalid status'], 400);
    db()->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$d['status'], intval($d['id']??0)]);
    json_out(['success'=>true]);
    break;

  default:
    json_out(['error'=>'Unknown action'], 404);
}
