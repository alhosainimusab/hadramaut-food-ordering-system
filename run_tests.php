<?php
/**
 * Dependency-free unit tests for src/lib.php.
 * Run:  php tests/run_tests.php      (exit code 0 = all passed)
 */
require __DIR__ . '/../src/lib.php';

$passed = 0;
$failed = [];

function check(string $name, bool $condition): void
{
    global $passed, $failed;
    if ($condition) { $passed++; } else { $failed[] = $name; }
}

// --- escaping ---
check('e() escapes HTML',            e('<script>"x"</script>') === '&lt;script&gt;&quot;x&quot;&lt;/script&gt;');
check('e() escapes single quotes',   e("O'Neil") === 'O&#039;Neil');
check('e() handles null',            e(null) === '');

// --- cart maths ---
$cart = [
    1 => ['price' => 12.50, 'quantity' => 2],
    7 => ['price' => 4.00,  'quantity' => 1],
];
check('cart_subtotal sums lines',     cart_subtotal($cart) === 29.0);
check('cart_subtotal empty is 0',     cart_subtotal([]) === 0.0);
check('cart_subtotal rounds to 2dp',  cart_subtotal([['price' => 0.1, 'quantity' => 3]]) === 0.3);
check('cart_item_count sums units',   cart_item_count($cart) === 3);
check('delivery fee for Delivery',    delivery_fee('Delivery', 5.0) === 5.0);
check('no fee for Pickup',            delivery_fee('Pickup', 5.0) === 0.0);

// --- validation ---
$good = ['name' => 'Ali', 'email' => 'ali@example.com', 'phone_number' => '012-345 6789',
         'password' => 'secret123', 'confirm_password' => 'secret123'];
check('valid registration passes',            validate_registration($good) === []);
check('bad email rejected',                   count(validate_registration(['email' => 'nope'] + $good)) === 1);
check('empty name rejected',                  count(validate_registration(['name' => '  '] + $good)) === 1);
check('short password rejected',              count(validate_registration(['password' => 'a1', 'confirm_password' => 'a1'] + $good)) === 1);
check('password without digit rejected',      count(validate_registration(['password' => 'abcdefgh', 'confirm_password' => 'abcdefgh'] + $good)) === 1);
check('mismatched passwords rejected',        count(validate_registration(['confirm_password' => 'secret124'] + $good)) === 1);
check('phone optional',                       validate_registration(['phone_number' => ''] + $good) === []);
check('phone with letters rejected',          !is_valid_phone('01x-2345678'));
check('phone too short rejected',             !is_valid_phone('12345'));
check('international phone accepted',         is_valid_phone('+60 12-345 6789'));

check('rating 1 valid',    is_valid_rating('1'));
check('rating 5 valid',    is_valid_rating(5));
check('rating 0 invalid',  !is_valid_rating('0'));
check('rating 6 invalid',  !is_valid_rating(6));
check('rating text invalid', !is_valid_rating('five'));

// --- uploads ---
$n = safe_upload_name('My Photo (1).JPG', 'abc');
check('upload name sanitised',          $n === 'abc_My_Photo_1.jpg');
check('php upload rejected',            safe_upload_name('shell.php') === null);
check('double extension rejected',      safe_upload_name('img.jpg.php') === null);
check('random prefix when none given',  (bool) preg_match('/^[0-9a-f]{12}_cat\.png$/', safe_upload_name('cat.png')));

// --- badges ---
check('badge for Pending',   status_badge_class('Pending') === 'bg-warning text-dark');
check('badge for unknown',   status_badge_class('???') === 'bg-secondary');
check('every status has a specific badge', count(array_filter(ORDER_STATUSES, fn($s) => status_badge_class($s) === 'bg-secondary')) === 0);

check('truncate long text',  truncate('abcdef', 3) === 'abc…');
check('truncate short text', truncate('abc', 3) === 'abc');

// --- report ---
$total = $passed + count($failed);
foreach ($failed as $f) {
    echo "FAIL  $f\n";
}
echo "\n$passed/$total tests passed\n";
exit($failed ? 1 : 0);
