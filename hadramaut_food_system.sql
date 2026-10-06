-- Hadramaut Food Ordering System: schema + demo data
-- Import in phpMyAdmin (Import tab) or:  mysql -u root -p < database/hadramaut_food_system.sql
-- Re-importing DROPS and recreates the database.
--
-- Demo logins (change or delete before any real use):
--   admin@example.com     / Admin@123
--   customer@example.com  / Customer@123
--   sara@example.com     / Customer@123

DROP DATABASE IF EXISTS hadramaut_food_system;
CREATE DATABASE hadramaut_food_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hadramaut_food_system;

-- ------------------------------------------------------------------
-- Tables
-- ------------------------------------------------------------------

CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    phone_number  VARCHAR(30)  DEFAULT NULL,
    address       TEXT         DEFAULT NULL,
    password      VARCHAR(255) NOT NULL COMMENT 'password_hash() output, never plain text',
    user_type     ENUM('user','admin') NOT NULL DEFAULT 'user',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE menu_items (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(150) NOT NULL,
    description  TEXT DEFAULT NULL,
    price        DECIMAL(10,2) NOT NULL,
    category     VARCHAR(100) DEFAULT NULL,
    image        VARCHAR(255) DEFAULT NULL COMMENT 'path relative to assets/img/',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_menu_category (category),
    CONSTRAINT chk_menu_price CHECK (price > 0)
) ENGINE=InnoDB;

CREATE TABLE orders (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id           INT UNSIGNED NOT NULL,
    total_amount      DECIMAL(10,2) NOT NULL COMMENT 'items + delivery_fee',
    delivery_fee      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    delivery_type     ENUM('Delivery','Pickup') NOT NULL DEFAULT 'Delivery',
    delivery_address  TEXT NOT NULL,
    payment_method    ENUM('Cash','Card') NOT NULL,
    payment_status    ENUM('Pending','Paid') NOT NULL DEFAULT 'Pending',
    order_status      ENUM('Pending','Preparing','Ready','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
    order_date        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_orders_user (user_id),
    KEY idx_orders_status (order_status),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id        INT UNSIGNED NOT NULL,
    menu_item_id    INT UNSIGNED NOT NULL,
    quantity        INT UNSIGNED NOT NULL,
    price_at_order  DECIMAL(10,2) NOT NULL COMMENT 'price snapshot so later menu price changes do not alter old orders',
    PRIMARY KEY (id),
    KEY idx_items_order (order_id),
    KEY idx_items_menu (menu_item_id),
    CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_items_menu  FOREIGN KEY (menu_item_id) REFERENCES menu_items (id) ON DELETE RESTRICT,
    CONSTRAINT chk_items_qty CHECK (quantity > 0)
) ENGINE=InnoDB;

CREATE TABLE feedback (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED NOT NULL,
    comment        TEXT NOT NULL,
    rating         TINYINT UNSIGNED DEFAULT NULL COMMENT '1-5, optional',
    feedback_date  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_feedback_user (user_id),
    CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT chk_feedback_rating CHECK (rating IS NULL OR rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;

-- ------------------------------------------------------------------
-- Demo data (fictional people; example.com addresses)
-- ------------------------------------------------------------------

INSERT INTO users (id, name, email, phone_number, address, password, user_type, created_at) VALUES
(1, 'Admin',          'admin@example.com',    NULL,           NULL,
    '$2y$10$g1lO7ckP4EDnTbfbqAw3oOV6s84lNJJXTBRLMWLBwIt14QOkDUGEu', 'admin', '2026-04-01 09:00:00'),
(2, 'Demo Customer',  'customer@example.com', '012-345 6789', '12, Jalan Contoh 3, Taman Demo, 86400 Batu Pahat, Johor',
    '$2y$10$yJxRCuDF3SWpr2ZZJqbFye9.Wz1jaMfq84cXhDRjGujlwgXfKNKDm', 'user', '2026-04-02 10:15:00'),
(3, 'Sara Demo',   'sara@example.com',    '013-222 1100', '7, Lorong Sampel 2, 86400 Batu Pahat, Johor',
    '$2y$10$yJxRCuDF3SWpr2ZZJqbFye9.Wz1jaMfq84cXhDRjGujlwgXfKNKDm', 'user', '2026-04-05 14:40:00');

INSERT INTO menu_items (id, name, description, price, category, image, created_at) VALUES
(1,  'Chicken Mandi',   'Half chicken slow-roasted over spiced basmati rice, served with tomato sahawiq and salad.', 12.50, 'Mains',    'menu/mandi.jpg',          '2026-04-01 09:10:00'),
(2,  'Chicken Hanith',  'Chicken slow-cooked in a sealed pot until tender, on saffron rice with spicy dip.',         14.00, 'Mains',    'menu/chicken_hanith.jpg', '2026-04-01 09:11:00'),
(3,  'Lamb Mandi',      'Lamb shoulder cooked for hours above fragrant rice with fried onions and raisins.',          16.00, 'Mains',    'menu/lamb_mandi.jpg',     '2026-04-01 09:12:00'),
(4,  'Chicken Shawarma Plate', 'Two chicken shawarma wraps with fries, garlic sauce and pickles.',                   11.00, 'Mains',    'menu/shawarma.jpg',       '2026-04-01 09:13:00'),
(5,  'Pizza',           'Stone-baked pizza with tomato, mozzarella, peppers and olives.',                              14.00, 'Mains',    'menu/pizza.jpg',          '2026-04-01 09:14:00'),
(6,  'Falafel Plate',   'Crisp chickpea falafel with tahini, salad and warm flatbread.',                               8.50, 'Sides',    'menu/falafel.jpg',        '2026-04-01 09:15:00'),
(7,  'Yoghurt Salad',   'Cool yoghurt with cucumber, dill and a drizzle of olive oil.',                                4.00, 'Sides',    'menu/yoghurt_salad.jpg',  '2026-04-01 09:16:00'),
(8,  'Loaded Fries',    'Fries topped with garlic sauce and a little chilli.',                                         6.00, 'Sides',    'menu/french_fries.jpg',   '2026-04-01 09:17:00'),
(9,  'Masoub',          'Mashed bread and banana with cream, honey and black seed. A Hadrami breakfast classic.',     9.00, 'Desserts', 'menu/masoub.jpg',         '2026-04-01 09:18:00'),
(10, 'Fatta with Dates','Torn flatbread soaked in ghee and dates, finished with black seed.',                         9.50, 'Desserts', 'menu/fatta_dates.jpg',    '2026-04-01 09:19:00'),
(11, 'Basbousa',        'Semolina cake soaked in syrup.',                                                              5.00, 'Desserts', 'menu/basbousa.jpg',       '2026-04-01 09:20:00'),
(12, 'Almond Date Shake','Milk blended with almonds and dates.',                                                       7.50, 'Drinks',   'menu/almond_shake.jpg',   '2026-04-01 09:21:00'),
(13, 'Fresh Carrot Juice','Freshly pressed carrot juice.',                                                             5.50, 'Drinks',   'menu/carrot_juice.jpg',   '2026-04-01 09:22:00');

-- Every order's total_amount = SUM(quantity * price_at_order) + delivery_fee.
INSERT INTO orders (id, user_id, total_amount, delivery_fee, delivery_type, delivery_address, payment_method, payment_status, order_status, order_date) VALUES
(1, 2, 27.00, 5.00, 'Delivery', '12, Jalan Contoh 3, Taman Demo, 86400 Batu Pahat, Johor', 'Card', 'Paid',    'Delivered', '2026-04-10 12:05:00'),
(2, 3, 25.00, 0.00, 'Pickup',   'Self pickup',                                              'Cash', 'Paid',    'Delivered', '2026-04-11 13:20:00'),
(3, 2, 28.50, 5.00, 'Delivery', '12, Jalan Contoh 3, Taman Demo, 86400 Batu Pahat, Johor', 'Cash', 'Paid',    'Delivered', '2026-04-15 19:45:00'),
(4, 3, 33.00, 5.00, 'Delivery', '7, Lorong Sampel 2, 86400 Batu Pahat, Johor',            'Card', 'Paid',    'Ready',     '2026-04-20 12:30:00'),
(5, 2, 14.00, 0.00, 'Pickup',   'Self pickup',                                              'Card', 'Paid',    'Cancelled', '2026-04-23 18:10:00'),
(6, 3, 37.00, 5.00, 'Delivery', '7, Lorong Sampel 2, 86400 Batu Pahat, Johor',            'Cash', 'Pending', 'Preparing', '2026-05-24 20:00:00'),
(7, 2, 21.00, 0.00, 'Pickup',   'Self pickup',                                              'Cash', 'Pending', 'Pending',   '2026-06-13 11:06:00');

INSERT INTO order_items (order_id, menu_item_id, quantity, price_at_order) VALUES
(1, 1, 1, 12.50), (1, 7, 1, 4.00), (1, 13, 1, 5.50),
(2, 1, 2, 12.50),
(3, 3, 1, 16.00), (3, 12, 1, 7.50),
(4, 2, 2, 14.00),
(5, 2, 1, 14.00),
(6, 3, 2, 16.00),
(7, 6, 1, 8.50), (7, 1, 1, 12.50);

INSERT INTO feedback (user_id, comment, rating, feedback_date) VALUES
(2, 'The lamb mandi was very tender and arrived hot. Delivery took about 40 minutes.', 5, '2026-04-15 21:00:00'),
(3, 'Pickup was quick. Would love a smaller portion option for the mandi.',            4, '2026-04-11 14:00:00'),
(3, 'Please add more drinks to the menu.',                                             NULL, '2026-05-25 10:00:00');
