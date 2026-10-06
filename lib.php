<?php
/**
 * Pure helper functions: no database, no session, no output.
 * Kept separate so they can be unit-tested (see tests/run_tests.php).
 */

const ORDER_STATUSES   = ['Pending', 'Preparing', 'Ready', 'Delivered', 'Cancelled'];
const PAYMENT_METHODS  = ['Cash', 'Card'];
const PAYMENT_STATUSES = ['Pending', 'Paid'];
const DELIVERY_TYPES   = ['Delivery', 'Pickup'];
const ALLOWED_IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
const MIN_PASSWORD_LENGTH = 8;

/** HTML-escape for output. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Bootstrap badge classes for an order status. */
function status_badge_class(string $status): string
{
    switch ($status) {
        case 'Pending':   return 'bg-warning text-dark';
        case 'Preparing': return 'bg-info text-dark';
        case 'Ready':     return 'bg-success';
        case 'Delivered': return 'bg-primary';
        case 'Cancelled': return 'bg-danger';
        default:          return 'bg-secondary';
    }
}

function payment_badge_class(string $status): string
{
    return $status === 'Paid' ? 'bg-success' : 'bg-danger';
}

/**
 * Validate the registration form. Returns a list of error messages (empty = valid).
 */
function validate_registration(array $in): array
{
    $errors = [];
    $name  = trim($in['name'] ?? '');
    $email = trim($in['email'] ?? '');
    $phone = trim($in['phone_number'] ?? '');
    $pass  = $in['password'] ?? '';
    $conf  = $in['confirm_password'] ?? '';

    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = 'Name is required (max 100 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($phone !== '' && !is_valid_phone($phone)) {
        $errors[] = 'Phone number may contain digits, spaces, + and - only (8–15 digits).';
    }
    $errors = array_merge($errors, validate_new_password($pass, $conf));
    return $errors;
}

/** Password rules shared by registration and password change. */
function validate_new_password(string $pass, string $confirm): array
{
    $errors = [];
    if (strlen($pass) < MIN_PASSWORD_LENGTH) {
        $errors[] = 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.';
    } elseif (!preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
        $errors[] = 'Password must contain at least one letter and one number.';
    }
    if ($pass !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    return $errors;
}

function is_valid_phone(string $phone): bool
{
    if (!preg_match('/^\+?[0-9\s\-]+$/', $phone)) {
        return false;
    }
    $digits = strlen(preg_replace('/\D/', '', $phone));
    return $digits >= 8 && $digits <= 15;
}

function is_valid_rating($rating): bool
{
    return filter_var($rating, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]) !== false;
}

/** Sum of price * quantity for cart lines. */
function cart_subtotal(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += (float) $item['price'] * (int) $item['quantity'];
    }
    return round($total, 2);
}

/** Total number of units in the cart (not distinct lines). */
function cart_item_count(array $items): int
{
    return array_sum(array_map(fn($i) => (int) $i['quantity'], $items));
}

function delivery_fee(string $delivery_type, float $flat_fee): float
{
    return $delivery_type === 'Delivery' ? $flat_fee : 0.0;
}

/** Turn an uploaded file name into a safe, unique file name, or null if the extension is not allowed. */
function safe_upload_name(string $original, ?string $prefix = null): ?string
{
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMAGE_EXT, true)) {
        return null;
    }
    $base = pathinfo($original, PATHINFO_FILENAME);
    $base = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', $base));
    $base = substr($base, 0, 50) ?: 'image';
    return ($prefix ?? bin2hex(random_bytes(6))) . '_' . $base . '.' . $ext;
}

function truncate(string $text, int $length): string
{
    return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '…' : $text;
}
