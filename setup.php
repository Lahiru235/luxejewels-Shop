<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Setup — Luxe Jewels</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Arial,sans-serif;background:#f5f5f5;padding:40px 20px}
.box{background:#fff;border-radius:12px;padding:28px;max-width:640px;margin:0 auto 20px;box-shadow:0 2px 8px rgba(0,0,0,.08)}
h1{font-size:1.5rem;margin-bottom:4px}
.ok{background:#d4edda;border:2px solid #27ae60;border-radius:10px;padding:14px 18px;margin:10px 0;color:#155724}
.fail{background:#f8d7da;border:2px solid #dc3545;border-radius:10px;padding:14px 18px;margin:10px 0;color:#721c24}
.fix{background:#fff3cd;border-radius:8px;padding:10px 14px;margin-top:8px;font-size:.88rem}
.btn{display:inline-block;background:#c9a84c;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;border:none;cursor:pointer;font-size:.95rem;margin-top:10px}
.btn-green{background:#27ae60}
code{background:#eee;padding:2px 6px;border-radius:4px;font-size:.88rem}
</style>
</head>
<body>
<div class="box">
  <h1>🔧 Luxe Jewels — Database Setup</h1>
  <p style="color:#666;margin-top:6px">This tool sets up your database automatically.</p>
</div>

<?php
$host='localhost'; $user='root'; $pass=''; $dbname='luxejewels';

// Step 1: PHP
echo '<div class="box"><div class="ok">✅ <strong>Step 1:</strong> PHP '.phpversion().' is working</div>';

// Step 2: PDO
if(extension_loaded('pdo_mysql')) echo '<div class="ok">✅ <strong>Step 2:</strong> PDO MySQL loaded</div>';
else { echo '<div class="fail">❌ <strong>Step 2:</strong> PDO MySQL not available<div class="fix">Enable <code>extension=pdo_mysql</code> in php.ini and restart Apache</div></div></div>'; exit; }

// Step 3: MySQL connect
try {
  $pdo=new PDO("mysql:host=$host;charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  echo '<div class="ok">✅ <strong>Step 3:</strong> MySQL connected!</div>';
} catch(PDOException $e) {
  echo '<div class="fail">❌ <strong>Step 3:</strong> Cannot connect to MySQL<br><em>'.$e->getMessage().'</em><div class="fix">Make sure MySQL is started in XAMPP Control Panel</div></div></div>';
  exit;
}

// Step 4: Database
$dbs=$pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
if(in_array($dbname,$dbs)) {
  echo '<div class="ok">✅ <strong>Step 4:</strong> Database <code>luxejewels</code> exists</div>';
} else {
  if(isset($_GET['create_db'])) {
    $pdo->exec("CREATE DATABASE luxejewels CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo '<div class="ok">✅ Database <code>luxejewels</code> created!</div>';
  } else {
    echo '<div class="fail">❌ <strong>Step 4:</strong> Database <code>luxejewels</code> not found
    <div class="fix"><a href="?create_db=1" class="btn">Create Database Now</a></div></div></div>';
    exit;
  }
}

// Step 5: Tables
$pdo->exec("USE luxejewels");
$tables=$pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$needed=['categories','products','users','orders','order_items','reviews'];
$missing=array_diff($needed,$tables);

if(empty($missing)) {
  echo '<div class="ok">✅ <strong>Step 5:</strong> All tables exist ('.count($tables).' tables)</div>';
} else {
  if(isset($_GET['create_tables'])) {
    $sql=file_get_contents(__DIR__.'/../php/schema.sql');
    // Split and run statements
    $stmts=array_filter(array_map('trim',explode(';',$sql)));
    $done=0;
    foreach($stmts as $s) {
      if(!empty($s)&&strpos($s,'--')!==0) {
        try{$pdo->exec($s);$done++;}catch(Exception $e){}
      }
    }
    echo '<div class="ok">✅ Tables created! ('.$done.' SQL statements executed)</div>';
    $tables=$pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
  } else {
    echo '<div class="fail">❌ <strong>Step 5:</strong> Missing tables: <code>'.implode(', ',$missing).'</code>
    <div class="fix"><a href="?create_tables=1" class="btn">Create All Tables Now</a></div></div></div>';
    exit;
  }
}

// Step 6: Admin user
$s=$pdo->prepare("SELECT id,password_hash,is_admin FROM users WHERE email='admin@luxejewels.com'");
$s->execute(); $admin=$s->fetch();

if($admin && password_verify('admin123',$admin['password_hash']) && $admin['is_admin']) {
  echo '<div class="ok">✅ <strong>Step 6:</strong> Admin user ready! Email: <code>admin@luxejewels.com</code> Password: <code>admin123</code></div>';
} else {
  if(isset($_GET['fix_admin'])) {
    $hash=password_hash('admin123',PASSWORD_BCRYPT,['cost'=>10]);
    if($admin) {
      $pdo->prepare("UPDATE users SET password_hash=?,is_admin=1,is_active=1 WHERE email='admin@luxejewels.com'")->execute([$hash]);
    } else {
      $pdo->prepare("INSERT INTO users (first_name,last_name,email,password_hash,is_admin,is_active) VALUES ('Admin','User','admin@luxejewels.com',?,1,1)")->execute([$hash]);
    }
    echo '<div class="ok">✅ Admin user created/fixed! Email: <code>admin@luxejewels.com</code> Password: <code>admin123</code></div>';
  } else {
    echo '<div class="fail">❌ <strong>Step 6:</strong> Admin user missing or password mismatch
    <div class="fix"><a href="?fix_admin=1" class="btn">Fix Admin User Now</a></div></div></div>';
    exit;
  }
}

// Step 7: Products count
$pcount=$pdo->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn();
if($pcount>0) {
  echo '<div class="ok">✅ <strong>Step 7:</strong> '.$pcount.' products in database</div>';
} else {
  echo '<div class="fail" style="border-color:#e67e22;background:#fff3cd;color:#856404">⚠️ <strong>Step 7:</strong> No products in database yet
  <div class="fix">The schema.sql seeds products automatically. If you see 0, re-run the schema import.</div></div>';
}

echo '</div>';

echo '<div class="box" style="background:#d4edda;border:2px solid #27ae60">
  <h2 style="color:#155724;font-size:1.2rem;margin-bottom:16px">🎉 Setup Complete! Your site is ready.</h2>
  <div style="display:flex;gap:12px;flex-wrap:wrap">
    <a href="../index.html" class="btn">🏠 Go to Homepage</a>
    <a href="../admin/login.html" class="btn btn-green">⚙️ Go to Admin Panel</a>
  </div>
  <p style="margin-top:16px;font-size:.82rem;color:#666">⚠️ Delete this file after setup for security: <code>php/setup.php</code></p>
</div>';
?>
</body>
</html>
