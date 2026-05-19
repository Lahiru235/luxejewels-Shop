<?php
require_once 'config.php';
$action = $_GET['action'] ?? 'list';

switch ($action) {

  // ── PUBLIC: list products ─────────────────
  case 'list':
    $db = db();
    $where = ['p.is_active=1']; $params = [];
    if (!empty($_GET['category'])) { $where[]='c.slug=?'; $params[]=$_GET['category']; }
    if (!empty($_GET['search']))   { $where[]='(p.name LIKE ? OR p.description LIKE ?)'; $q='%'.$_GET['search'].'%'; $params[]=$q; $params[]=$q; }
    if (!empty($_GET['featured'])) { $where[]='p.is_featured=1'; }
    if (!empty($_GET['badge']))    { $where[]='p.badge=?'; $params[]=$_GET['badge']; }
    if (!empty($_GET['min_price'])){ $where[]='p.price>=?'; $params[]=$_GET['min_price']; }
    if (!empty($_GET['max_price'])){ $where[]='p.price<=?'; $params[]=$_GET['max_price']; }
    $sort = match($_GET['sort']??'') {
      'price_asc'  => 'p.price ASC',
      'price_desc' => 'p.price DESC',
      'new'        => 'p.id DESC',
      default      => 'p.is_featured DESC, p.id ASC'
    };
    $sql = "SELECT p.*, c.name as cat_name, c.slug as cat_slug,
            (SELECT COALESCE(AVG(rating),0) FROM reviews WHERE product_id=p.id AND approved=1) as avg_rating,
            (SELECT COUNT(*) FROM reviews WHERE product_id=p.id AND approved=1) as review_count
            FROM products p LEFT JOIN categories c ON p.category_id=c.id
            WHERE ".implode(' AND ',$where)." ORDER BY $sort";
    $s = $db->prepare($sql); $s->execute($params);
    json_out(['products'=>$s->fetchAll()]);
    break;

  // ── PUBLIC: get single product ────────────
  case 'get':
    $id = intval($_GET['id']??0);
    if(!$id) json_out(['error'=>'ID required'],400);
    $db = db();
    $s = $db->prepare("SELECT p.*, c.name as cat_name, c.slug as cat_slug,
        (SELECT COALESCE(AVG(rating),0) FROM reviews WHERE product_id=p.id AND approved=1) as avg_rating,
        (SELECT COUNT(*) FROM reviews WHERE product_id=p.id AND approved=1) as review_count
        FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.id=? AND p.is_active=1");
    $s->execute([$id]); $p = $s->fetch();
    if(!$p) json_out(['error'=>'Not found'],404);
    $r = $db->prepare('SELECT * FROM reviews WHERE product_id=? AND approved=1 ORDER BY created_at DESC LIMIT 10');
    $r->execute([$id]); $p['reviews']=$r->fetchAll();
    json_out(['product'=>$p]);
    break;

  // ── PUBLIC: categories ────────────────────
  case 'categories':
    $s = db()->query('SELECT c.*, COUNT(p.id) as cnt FROM categories c LEFT JOIN products p ON p.category_id=c.id AND p.is_active=1 GROUP BY c.id ORDER BY c.id');
    json_out(['categories'=>$s->fetchAll()]);
    break;

  // ── ADMIN: list all (including inactive) ──
  case 'admin_list':
    $db = db(); $where=[]; $params=[];
    if(!empty($_GET['search'])){$where[]='(p.name LIKE ? OR p.description LIKE ?)';$q='%'.$_GET['search'].'%';$params[]=$q;$params[]=$q;}
    if(!empty($_GET['category'])){$where[]='c.slug=?';$params[]=$_GET['category'];}
    $sql="SELECT p.*, c.name as cat_name, c.slug as cat_slug FROM products p LEFT JOIN categories c ON p.category_id=c.id".($where?' WHERE '.implode(' AND ',$where):'')." ORDER BY p.id DESC";
    $s=$db->prepare($sql);$s->execute($params);
    json_out(['products'=>$s->fetchAll()]);
    break;

  // ── ADMIN: create product ─────────────────
  case 'create':
    $db = db();
    $d  = body();
    $name = clean($d['name']??'');
    if(!$name) json_out(['error'=>'Product name is required'],400);
    $db->prepare('INSERT INTO products (name,category_id,description,price,old_price,stock,emoji,badge,is_featured,is_active) VALUES (?,?,?,?,?,?,?,?,?,1)')
       ->execute([
         $name,
         intval($d['category_id']??1),
         clean($d['description']??''),
         floatval($d['price']??0),
         ($d['old_price']&&$d['old_price']>0) ? floatval($d['old_price']) : null,
         intval($d['stock']??0),
         clean($d['emoji']??'💍'),
         clean($d['badge']??''),
         intval($d['is_featured']??0)
       ]);
    json_out(['success'=>true,'id'=>$db->lastInsertId(),'message'=>'Product created successfully']);
    break;

  // ── ADMIN: update product ─────────────────
  case 'update':
    $db = db();
    $d  = body();
    $id = intval($d['id']??0);
    if(!$id) json_out(['error'=>'Product ID required'],400);
    $db->prepare('UPDATE products SET name=?,category_id=?,description=?,price=?,old_price=?,stock=?,emoji=?,badge=?,is_featured=?,is_active=? WHERE id=?')
       ->execute([
         clean($d['name']??''),
         intval($d['category_id']??1),
         clean($d['description']??''),
         floatval($d['price']??0),
         ($d['old_price']&&$d['old_price']>0) ? floatval($d['old_price']) : null,
         intval($d['stock']??0),
         clean($d['emoji']??'💍'),
         clean($d['badge']??''),
         intval($d['is_featured']??0),
         intval($d['is_active']??1),
         $id
       ]);
    json_out(['success'=>true,'message'=>'Product updated successfully']);
    break;

  // ── ADMIN: delete (soft) ──────────────────
  case 'delete':
    $db = db();
    $d  = body();
    $id = intval($d['id']??$_GET['id']??0);
    if(!$id) json_out(['error'=>'ID required'],400);
    $db->prepare('UPDATE products SET is_active=0 WHERE id=?')->execute([$id]);
    json_out(['success'=>true,'message'=>'Product deleted']);
    break;

  // ── ADMIN: upload image ───────────────────
  case 'upload':
    if(empty($_FILES['image'])) json_out(['error'=>'No image file provided'],400);
    $f    = $_FILES['image'];
    $pid  = intval($_POST['product_id']??0);
    // Validate type
    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($fi,$f['tmp_name']);
    finfo_close($fi);
    if(!in_array($mime,$allowed)) json_out(['error'=>'Only JPG, PNG, WebP allowed'],400);
    if($f['size']>3*1024*1024) json_out(['error'=>'Max file size is 3MB'],400);
    // Save file
    $dir = __DIR__.'/../images/products/';
    if(!is_dir($dir)) mkdir($dir,0755,true);
    $ext  = pathinfo($f['name'],PATHINFO_EXTENSION);
    $name = 'p_'.time().'_'.rand(100,999).'.'.strtolower($ext);
    if(!move_uploaded_file($f['tmp_name'],$dir.$name)) json_out(['error'=>'Failed to save image'],500);
    $url  = 'images/products/'.$name;
    // Update product image in DB
    if($pid) db()->prepare('UPDATE products SET image=? WHERE id=?')->execute([$url,$pid]);
    json_out(['success'=>true,'url'=>$url]);
    break;

  default:
    json_out(['error'=>'Unknown action: '.$action],404);
}
