-- AngelPrintShop B2B Portal: MySQL import file
-- Import into an empty database named angelprintshop, or change DB_DATABASE in .env.
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS wallet_transactions, extra_charges, b2c_order_items, b2c_orders, b2c_product_images, b2c_products, b2c_categories, customers, order_items, orders, products, password_reset_tokens, users;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, company_name VARCHAR(255) NULL, email VARCHAR(255) NOT NULL UNIQUE,
 phone VARCHAR(30) NULL, address TEXT NULL, gst_number VARCHAR(30) NULL, password VARCHAR(255) NOT NULL,
 role ENUM('dealer','staff','admin') NOT NULL DEFAULT 'dealer', approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 wallet_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00, remember_token VARCHAR(100) NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE password_reset_tokens (email VARCHAR(255) PRIMARY KEY, token VARCHAR(255) NOT NULL, created_at TIMESTAMP NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE products (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, category VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, print_copy INT UNSIGNED NOT NULL DEFAULT 1000,
 amount DECIMAL(12,2) NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, sort_order INT UNSIGNED NOT NULL DEFAULT 0,
 created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX products_category_sort_index(category, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_number VARCHAR(255) NOT NULL UNIQUE, dealer_id BIGINT UNSIGNED NOT NULL, assigned_staff_id BIGINT UNSIGNED NULL,
 status ENUM('new','in_progress','ready','customer_called','picked_up','cancelled') NOT NULL DEFAULT 'new', deadline_at TIMESTAMP NULL,
 subtotal DECIMAL(12,2) NOT NULL, extra_total DECIMAL(12,2) NOT NULL DEFAULT 0.00, grand_total DECIMAL(12,2) NOT NULL,
 customer_note TEXT NULL, pickup_note TEXT NULL, called_at TIMESTAMP NULL, completed_at TIMESTAMP NULL, picked_up_at TIMESTAMP NULL,
 created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX orders_status_deadline_index(status, deadline_at), CONSTRAINT orders_dealer_fk FOREIGN KEY (dealer_id) REFERENCES users(id), CONSTRAINT orders_staff_fk FOREIGN KEY (assigned_staff_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE order_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NULL, product_name VARCHAR(255) NOT NULL, category VARCHAR(100) NOT NULL,
 print_copy INT UNSIGNED NOT NULL, packs INT UNSIGNED NOT NULL DEFAULT 1, unit_price DECIMAL(12,2) NOT NULL, line_total DECIMAL(12,2) NOT NULL,
 file_path VARCHAR(255) NULL, original_filename VARCHAR(255) NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT order_items_order_fk FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE, CONSTRAINT order_items_product_fk FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE extra_charges (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id BIGINT UNSIGNED NOT NULL, description VARCHAR(255) NOT NULL, amount DECIMAL(12,2) NOT NULL,
 deducted_from_wallet TINYINT(1) NOT NULL DEFAULT 1, added_by BIGINT UNSIGNED NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT extra_charges_order_fk FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE, CONSTRAINT extra_charges_user_fk FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE wallet_transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, order_id BIGINT UNSIGNED NULL, type ENUM('credit','debit') NOT NULL,
 amount DECIMAL(12,2) NOT NULL, balance_after DECIMAL(12,2) NOT NULL, description VARCHAR(255) NOT NULL, created_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX wallet_user_date_index(user_id, created_at), CONSTRAINT wallet_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT wallet_order_fk FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL, CONSTRAINT wallet_creator_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL UNIQUE, phone VARCHAR(30) NOT NULL,
 address TEXT NULL, password VARCHAR(255) NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, remember_token VARCHAR(100) NULL,
 created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE b2c_categories (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL UNIQUE, is_active TINYINT(1) NOT NULL DEFAULT 1, sort_order INT UNSIGNED NOT NULL DEFAULT 0,
 created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE b2c_products (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, b2c_category_id BIGINT UNSIGNED NOT NULL, name VARCHAR(255) NOT NULL, short_description VARCHAR(255) NULL,
 description TEXT NULL, print_copy INT UNSIGNED NOT NULL DEFAULT 100, amount DECIMAL(12,2) NOT NULL, front_back_amount DECIMAL(12,2) NULL,
 sample_pdf_path VARCHAR(255) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, sort_order INT UNSIGNED NOT NULL DEFAULT 0,
 created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX b2c_products_active_sort_index(is_active, sort_order),
 CONSTRAINT b2c_products_category_fk FOREIGN KEY (b2c_category_id) REFERENCES b2c_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE b2c_product_images (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, b2c_product_id BIGINT UNSIGNED NOT NULL, file_path VARCHAR(255) NOT NULL, sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
 created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT b2c_product_images_product_fk FOREIGN KEY (b2c_product_id) REFERENCES b2c_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE b2c_orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_number VARCHAR(255) NOT NULL UNIQUE, customer_id BIGINT UNSIGNED NOT NULL, assigned_staff_id BIGINT UNSIGNED NULL,
 staff_status VARCHAR(255) NOT NULL DEFAULT 'pending', deadline_at TIMESTAMP NULL, contact_name VARCHAR(255) NOT NULL, contact_email VARCHAR(255) NOT NULL,
 contact_phone VARCHAR(30) NOT NULL, status ENUM('new','reviewed','quoted','confirmed','processing','completed','cancelled') NOT NULL DEFAULT 'new',
 subtotal DECIMAL(12,2) NOT NULL, grand_total DECIMAL(12,2) NOT NULL, customer_note TEXT NULL, pickup_note TEXT NULL,
 completed_at TIMESTAMP NULL, picked_up_at TIMESTAMP NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX b2c_orders_status_created_index(status, created_at), CONSTRAINT b2c_orders_customer_fk FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 CONSTRAINT b2c_orders_staff_fk FOREIGN KEY (assigned_staff_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE b2c_order_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, b2c_order_id BIGINT UNSIGNED NOT NULL, b2c_product_id BIGINT UNSIGNED NULL, product_name VARCHAR(255) NOT NULL,
 category_name VARCHAR(255) NOT NULL, quantity INT UNSIGNED NOT NULL, unit_price DECIMAL(12,2) NOT NULL, line_total DECIMAL(12,2) NOT NULL,
 print_side VARCHAR(20) NOT NULL DEFAULT 'single', finish VARCHAR(30) NOT NULL DEFAULT 'none', event_date DATE NULL, custom_text TEXT NULL,
 file_path VARCHAR(255) NULL, original_filename VARCHAR(255) NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT b2c_order_items_order_fk FOREIGN KEY (b2c_order_id) REFERENCES b2c_orders(id) ON DELETE CASCADE,
 CONSTRAINT b2c_order_items_product_fk FOREIGN KEY (b2c_product_id) REFERENCES b2c_products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Demo accounts. Change passwords after first login.
-- admin@angelprintshop.com / Admin@123
-- staff@angelprintshop.com / Staff@123
-- dealer@example.com / Dealer@123
INSERT INTO users (id,name,company_name,email,phone,address,password,role,approval_status,wallet_balance) VALUES
(1,'Portal Admin','Angel Print Shop','admin@angelprintshop.com',NULL,NULL,'$2y$12$c0M0EKq.gWqmgLPsQXJ9uusd02Dqs9dE1RPGf9YgopfqW3P7uQEcu','admin','approved',0.00),
(2,'Printing Operator','Angel Print Shop','staff@angelprintshop.com',NULL,NULL,'$2y$12$cU3vjwhO/JFp9NjVUcFNsu6a5zhCddUYjUTQ8f51KYVuDi9yN4rE.','staff','approved',0.00),
(3,'Demo Dealer','Demo Enterprises','dealer@example.com','9999999999','Vadodara, Gujarat','$2y$12$hmQdiPLDp/Nnt5pyhxSeCud/3LDUnhWDU7P6Ng9VtixjjDswoO9qu','dealer','approved',5000.00);
INSERT INTO wallet_transactions (user_id,type,amount,balance_after,description) VALUES (3,'credit',5000,5000,'Opening demo balance');
INSERT INTO products (category,name,print_copy,amount,sort_order) VALUES
('KANKOTRI','A8 KANKOTRI SPECIAL',1000,1400,1),('KANKOTRI','A4 KANKOTRI SPECIAL',1000,2600,2),
('VISITING CARD','NT SINGLE SIDE',1000,250,3),('VISITING CARD','NT FRONT BACK',1000,290,4),('VISITING CARD','250 GSM BOTH SIDE CARD',1000,230,5),('VISITING CARD','250 GSM ONE SIDE CARD',1000,170,6),('VISITING CARD','250 BOTH SIDE GLOSSY CARD',1000,270,7),('VISITING CARD','400 GSM THERMAL MATT CARD',1000,430,8),('VISITING CARD','400 GSM SINGLE SIDE UV CARD',1000,560,9),('VISITING CARD','400 GSM FRONT BACK UV CARD',1000,650,10),
('SQ. INCH JOB','ART CARD SINGLE SIDE (SQ.INCH)',1000,23,11),('SQ. INCH JOB','ART CARD BOTH SIDE (SQ.INCH)',1000,24,12),('SQ. INCH JOB','ART CARD BOTH SIDE LAMINATION (SQ.INCH)',1000,35,13),
('LETTERHEAD / ENVELOPE','100 ALABASTER (210X297)',1000,1125,14),('LETTERHEAD / ENVELOPE','80 GSM JK FINISH (210X285)',1000,1050,15);
