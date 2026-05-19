-- =============================================
-- LUXE JEWELS — Database Schema (Improved)
-- Run in phpMyAdmin or MySQL command line
-- =============================================

CREATE DATABASE IF NOT EXISTS luxejewels CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE luxejewels;

-- CATEGORIES
CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) UNIQUE NOT NULL,
  icon VARCHAR(10) DEFAULT '💍'
);

-- PRODUCTS
CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  category_id INT,
  description TEXT,
  price DECIMAL(10,2) NOT NULL,
  old_price DECIMAL(10,2) DEFAULT NULL,
  stock INT DEFAULT 10,
  image VARCHAR(500) DEFAULT '',
  emoji VARCHAR(10) DEFAULT '💍',
  badge ENUM('','hot','new','sale') DEFAULT '',
  is_featured TINYINT DEFAULT 0,
  is_active TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- USERS
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  phone VARCHAR(30) DEFAULT '',
  address TEXT DEFAULT '',
  is_admin TINYINT DEFAULT 0,
  is_active TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ORDERS (improved: added discount, payment_status, coupon_code)
CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(30) UNIQUE NOT NULL,
  user_id INT,
  customer_name VARCHAR(200) NOT NULL,
  customer_email VARCHAR(255) NOT NULL,
  customer_phone VARCHAR(30) DEFAULT '',
  shipping_address TEXT NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  discount DECIMAL(10,2) DEFAULT 0.00,
  shipping DECIMAL(10,2) DEFAULT 0,
  total DECIMAL(10,2) NOT NULL,
  status ENUM('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  payment_method ENUM('card','cod','bank_transfer','paypal') DEFAULT 'card',
  payment_status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
  coupon_code VARCHAR(30) DEFAULT '',
  notes TEXT DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ORDER ITEMS
CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT,
  product_name VARCHAR(255) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- REVIEWS
CREATE TABLE IF NOT EXISTS reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_id INT,
  customer_name VARCHAR(200) NOT NULL,
  rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment TEXT,
  approved TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- SEED CATEGORIES
INSERT IGNORE INTO categories (name, slug, icon) VALUES
('Necklaces',     'necklaces',     '📿'),
('Bracelets',     'bracelets',     '💛'),
('Earrings',      'earrings',      '✨'),
('Rings',         'rings',         '💍'),
('Anklets',       'anklets',       '🌟'),
('Jewellery Sets','sets',          '🎀');

-- SEED PRODUCTS (30 products)
INSERT IGNORE INTO products (name, category_id, description, price, old_price, stock, emoji, badge, is_featured) VALUES
('Pearl Pendant Necklace', 1, 'Elegant pearl pendant on a delicate gold chain. Perfect for everyday wear.', 29.99, 45.00, 24, '📿', 'hot', 1),
('Minimal Cross Necklace', 1, 'Minimalist cross pendant available in gold and silver finish.', 24.99, NULL, 15, '✝️', 'new', 1),
('Opal Charm Necklace', 1, 'Stunning opal stone charm on a fine gold chain.', 34.99, 50.00, 8, '💎', 'sale', 0),
('Heart Pendant Necklace', 1, 'Dainty heart pendant necklace — a timeless classic.', 22.99, NULL, 32, '❤️', '', 1),
('Infinity Love Necklace', 1, 'Classic infinity symbol necklace, symbolising eternal love.', 19.99, 30.00, 45, '♾️', 'sale', 0),
('Heart Charm Bracelet', 2, 'Delicate heart charm bracelet in gold plating.', 19.99, 28.00, 37, '💛', 'hot', 1),
('Zirconia Charm Bracelet', 2, 'Sparkling zirconia stone charm bracelet.', 32.99, NULL, 12, '💎', 'new', 1),
('Luna Bangle', 2, 'Classic luna bangle with crescent moon design.', 24.99, 35.00, 28, '🌙', 'sale', 0),
('Woven Mesh Bracelet', 2, 'Luxurious woven mesh bracelet with secure clasp.', 21.99, NULL, 41, '🔗', '', 0),
('Golden Heart Tassel Bracelet', 2, 'Eye-catching heart tassel bracelet with golden finish.', 29.99, NULL, 9, '✨', 'new', 0),
('Butterfly Stud Earrings', 3, 'Charming butterfly stud earrings in gold finish.', 12.99, 18.00, 65, '🦋', 'hot', 1),
('White Pearl Stud Earrings', 3, 'Timeless white pearl stud earrings for any occasion.', 9.99, NULL, 88, '⚪', '', 1),
('Midnight Heart Studs', 3, 'Elegant black heart stud earrings in gold plating.', 11.99, 16.00, 22, '🖤', 'sale', 0),
('Hollow Hoop Earrings', 3, 'Classic hollow hoop earrings in gold tone.', 14.99, NULL, 35, '⭕', 'new', 0),
('Golden Bloom Earrings', 3, 'Floral bloom charm drop earrings in gold finish.', 13.99, 20.00, 18, '🌸', 'sale', 0),
('Evil Eye Ring', 4, 'Trendy evil eye ring available in gold and silver.', 9.99, 15.00, 47, '👁️', 'hot', 1),
('Gemstone Cubic Ring', 4, 'Sparkling cubic gemstone ring in white and green.', 9.99, NULL, 55, '💚', '', 1),
('Classic Ring Set 4pc', 4, 'Set of 4 stackable decorative rings in gold finish.', 19.99, 28.00, 23, '💍', 'sale', 0),
('Rose Gold Band Ring', 4, 'Minimalist rose gold band ring — elegant and timeless.', 14.99, NULL, 31, '🌹', 'new', 0),
('Minimalist Solid Anklet', 5, 'Minimalist solid gold anklet — perfect for beach and everyday.', 14.99, 20.00, 42, '🌟', 'hot', 1),
('Star Charm Anklet', 5, 'Delicate charm anklet with star and moon pendants.', 17.99, NULL, 14, '⭐', 'new', 0),
('Beaded Anklet', 5, 'Colourful beaded anklet with gold spacers.', 12.99, 18.00, 27, '📿', 'sale', 0),
('Pearl Jewellery Set', 6, 'Complete pearl set: necklace, bracelet, and earrings.', 49.99, 75.00, 11, '🎀', 'hot', 1),
('Gold Layered Set', 6, 'Luxurious layered gold jewellery set for special occasions.', 44.99, 65.00, 8, '✨', 'sale', 1),
('Minimalist Silver Set', 6, 'Clean minimalist silver necklace and earring set.', 39.99, NULL, 13, '🥈', 'new', 0),
('Crystal Bridal Set', 6, 'Stunning crystal set perfect for weddings and special events.', 59.99, 89.00, 6, '💎', 'hot', 1),
('Everyday Classics Set', 6, 'Simple and elegant everyday jewellery set.', 34.99, 50.00, 19, '💛', 'sale', 0),
('Rose Gold Trio Set', 6, 'Beautiful rose gold necklace, ring and earrings set.', 42.99, NULL, 15, '🌹', 'new', 0),
('Birthday Gift Set', 6, 'Perfect birthday gift — necklace, bracelet and earrings.', 38.99, 55.00, 22, '🎁', 'sale', 0),
('Luxury Gold Collection', 6, 'Premium 5-piece gold jewellery collection.', 89.99, 120.00, 5, '👑', 'hot', 1);

-- ADMIN USER (password: admin123)
INSERT IGNORE INTO users (first_name, last_name, email, password_hash, is_admin) VALUES
('Admin', 'User', 'admin@luxejewels.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);
