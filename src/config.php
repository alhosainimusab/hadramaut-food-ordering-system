<?php
/**
 * App configuration. Values can be overridden with environment variables,
 * so no credentials need to be edited into the code.
 * Defaults match a fresh XAMPP install (user "root", empty password).
 */
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'hadramaut_food_system');

define('APP_NAME', 'Hadramaut Food Ordering System');
define('CURRENCY', 'RM');
define('DELIVERY_FEE', 5.00);          // flat fee for Delivery orders; Pickup is free
define('MAX_UPLOAD_BYTES', 2 * 1024 * 1024);

define('ROOT_PATH', dirname(__DIR__) . '/');
define('IMG_DIR', ROOT_PATH . 'public/assets/img/');   // DB stores paths relative to this, e.g. "menu/mandi.jpg"
define('UPLOAD_SUBDIR', 'uploads/');
