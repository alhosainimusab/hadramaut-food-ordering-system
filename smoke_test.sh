#!/usr/bin/env bash
# End-to-end smoke test: imports the demo database, starts PHP's built-in server
# and walks through the main customer and admin flows with curl.
#
# Usage:   DB_USER=root DB_PASS='' bash tests/smoke_test.sh
# Needs:   php (mysqli, mbstring), mysql client, curl, a running MySQL/MariaDB server.
# WARNING: drops and recreates the `hadramaut_food_system` database.

set -u
cd "$(dirname "$0")/.."

PORT="${PORT:-8089}"
URL="http://127.0.0.1:$PORT"
export DB_HOST="${DB_HOST:-127.0.0.1}" DB_USER="${DB_USER:-root}" DB_PASS="${DB_PASS:-}"
MYSQL=(mysql -h "$DB_HOST" -u "$DB_USER")
[ -n "$DB_PASS" ] && MYSQL+=("-p$DB_PASS")
SQL() { "${MYSQL[@]}" -N hadramaut_food_system -e "$1"; }

PASS=0; FAIL=0
ok()   { PASS=$((PASS+1)); echo "  ok    $1"; }
bad()  { FAIL=$((FAIL+1)); echo "  FAIL  $1"; }
expect() { if [ "$2" == "$3" ]; then ok "$1"; else bad "$1 (expected '$3', got '$2')"; fi; }
contains() { if grep -q -- "$3" <<<"$2"; then ok "$1"; else bad "$1 (missing '$3')"; fi; }

TMP="$(mktemp -d)"
cleanup() { [ -n "${SRV:-}" ] && kill "$SRV" 2>/dev/null; rm -rf "$TMP"; }
trap cleanup EXIT

# ---- setup ----
"${MYSQL[@]}" < database/hadramaut_food_system.sql || { echo "Could not import database"; exit 1; }
php -S "127.0.0.1:$PORT" -t public -d display_errors=1 -d error_reporting=E_ALL -d output_buffering=0 >"$TMP/server.log" 2>&1 &
SRV=$!
for _ in $(seq 1 20); do curl -s -o /dev/null "$URL/index.php" && break; sleep 0.2; done

# jar-aware helpers
get()   { curl -s --max-time 10 -c "$TMP/$1" -b "$TMP/$1" "$URL/$2"; }
code()  { curl -s --max-time 10 -c "$TMP/$1" -b "$TMP/$1" -o /dev/null -w '%{http_code} %{redirect_url}' "$URL/$2"; }
token() { get "$1" "$2" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | cut -d'"' -f4; }
post()  { local jar=$1 page=$2; shift 2; curl -s --max-time 10 -c "$TMP/$jar" -b "$TMP/$jar" -o /dev/null -w '%{http_code} %{redirect_url}' "$@" "$URL/$page"; }
login() { local t; t=$(token "$1" login.php); post "$1" login.php -d "csrf_token=$t" --data-urlencode "email=$2" --data-urlencode "password=$3"; }

echo "Public pages"
contains "home page renders featured dishes" "$(get guest index.php)" "Today's picks"
contains "menu groups dishes by category"    "$(get guest menu.php)" 'category-heading mt-4 mb-3">Mains'
contains "menu search filters"               "$(get guest 'menu.php?search=mandi')" "Lamb Mandi"
expect   "cart requires login"               "$(code guest cart.php)" "302 $URL/login.php"
expect   "admin area requires login"         "$(code guest admin/dashboard.php)" "302 $URL/login.php"

echo "Registration and login"
T=$(token new register.php)
R=$(curl -s -c "$TMP/new" -b "$TMP/new" -d "csrf_token=$T&name=Weak&email=weak@example.com&password=abc&confirm_password=abc" "$URL/register.php")
contains "weak password rejected" "$R" "at least 8 characters"
T=$(token new register.php)
expect "valid registration redirects to login" \
  "$(post new register.php -d "csrf_token=$T&name=Test+User&email=test@example.com&phone_number=0123456789&password=Passw0rd1&confirm_password=Passw0rd1")" "302 $URL/login.php"
expect "password is stored hashed" "$(SQL "SELECT LEFT(password,4) FROM users WHERE email='test@example.com'")" '$2y$'
T=$(token new register.php)
contains "duplicate email rejected" \
  "$(curl -s -c "$TMP/new" -b "$TMP/new" -d "csrf_token=$T&name=X&email=test@example.com&password=Passw0rd1&confirm_password=Passw0rd1" "$URL/register.php")" "already registered"
T=$(token cust login.php)
contains "wrong password rejected" "$(curl -s -c "$TMP/cust" -b "$TMP/cust" -d "csrf_token=$T&email=test@example.com&password=nope" "$URL/login.php")" "Invalid email or password"
expect "POST without CSRF token is refused" "$(post cust login.php -d 'email=test@example.com&password=Passw0rd1')" "400 "
expect "customer login redirects home" "$(login cust test@example.com Passw0rd1)" "302 $URL/index.php"
expect "customer blocked from admin" "$(code cust admin/dashboard.php)" "302 $URL/login.php"

echo "Cart and checkout"
T=$(token cust menu.php)
expect "add to cart redirects (no 'headers already sent')" "$(post cust menu.php -d "csrf_token=$T&menu_item_id=1&quantity=2&add_to_cart=1")" "302 $URL/menu.php"
T=$(token cust menu.php); post cust menu.php -d "csrf_token=$T&menu_item_id=12&quantity=1&add_to_cart=1" >/dev/null
contains "cart shows subtotal 2x12.50 + 7.50" "$(get cust cart.php)" "RM 32.50"
contains "cart offers continue shopping" "$(get cust cart.php)" "Continue shopping"
T=$(token cust menu.php)
post cust menu.php -d "csrf_token=$T&menu_item_id=1&quantity=0&add_to_cart=1" >/dev/null
contains "quantity 0 rejected" "$(get cust menu.php)" "between 1 and 99"
T=$(token cust checkout.php)
contains "short delivery address rejected" "$(curl -s -b "$TMP/cust" -c "$TMP/cust" -d "csrf_token=$T&place_order=1&delivery_type=Delivery&payment_method=Cash&delivery_address=short" "$URL/checkout.php")" "full delivery address"
T=$(token cust checkout.php)
R=$(post cust checkout.php -d "csrf_token=$T&place_order=1&delivery_type=Delivery&payment_method=Cash" --data-urlencode "delivery_address=1, Jalan Ujian, 86400 Batu Pahat")
OID=$(SQL "SELECT MAX(id) FROM orders")
expect "delivery order redirects to its page" "$R" "302 $URL/view_order.php?order_id=$OID"
expect "delivery total = items + RM5 fee" "$(SQL "SELECT CONCAT(total_amount,'|',delivery_fee,'|',payment_status) FROM orders WHERE id=$OID")" "37.50|5.00|Pending"
expect "order items saved" "$(SQL "SELECT COUNT(*) FROM order_items WHERE order_id=$OID")" "2"
expect "cart emptied after checkout" "$(code cust checkout.php)" "302 $URL/menu.php"

T=$(token cust menu.php); post cust menu.php -d "csrf_token=$T&menu_item_id=11&quantity=2&add_to_cart=1" >/dev/null
SQL "UPDATE menu_items SET price = 6.00 WHERE id = 11"
T=$(token cust checkout.php)
post cust checkout.php -d "csrf_token=$T&place_order=1&delivery_type=Pickup&payment_method=Cash" >/dev/null
expect "checkout uses current DB price, not the stale cart price" "$(SQL "SELECT CONCAT(total_amount,'|',price_at_order) FROM orders o JOIN order_items i ON i.order_id=o.id WHERE o.id=(SELECT MAX(id) FROM orders)")" "12.00|6.00"
T=$(token cust menu.php); post cust menu.php -d "csrf_token=$T&menu_item_id=2&quantity=1&add_to_cart=1" >/dev/null
T=$(token cust checkout.php)
post cust checkout.php -d "csrf_token=$T&place_order=1&delivery_type=Pickup&payment_method=Card" >/dev/null
PID=$(SQL "SELECT MAX(id) FROM orders")
expect "pickup has no fee, simulated card marks Paid" "$(SQL "SELECT CONCAT(total_amount,'|',delivery_fee,'|',payment_status,'|',delivery_address) FROM orders WHERE id=$PID")" "14.00|0.00|Paid|Self pickup"
T=$(token cust menu.php); post cust menu.php -d "csrf_token=$T&menu_item_id=1&quantity=1&add_to_cart=1" >/dev/null
T=$(token cust checkout.php)
post cust checkout.php -d "csrf_token=$T&place_order=1&delivery_type=Teleport&payment_method=Bitcoin" >/dev/null
expect "tampered delivery/payment values rejected" "$(SQL "SELECT MAX(id) FROM orders")" "$PID"

echo "Order access and cancellation"
contains "order history lists the new order" "$(get cust order_history.php)" "#$OID"
expect "cannot view another customer's order" "$(code cust 'view_order.php?order_id=1')" "302 $URL/order_history.php"
T=$(token cust "view_order.php?order_id=$OID")
post cust view_order.php -d "csrf_token=$T&order_id=$OID&cancel_order=1" >/dev/null
expect "customer can cancel own pending order" "$(SQL "SELECT order_status FROM orders WHERE id=$OID")" "Cancelled"
T=$(token cust "view_order.php?order_id=$OID")
post cust view_order.php -d "csrf_token=$T&order_id=7&cancel_order=1" >/dev/null
expect "cannot cancel someone else's order" "$(SQL "SELECT order_status FROM orders WHERE id=7")" "Pending"

login demo customer@example.com Customer@123 >/dev/null
T=$(token demo "view_order.php?order_id=3")
post demo view_order.php -d "csrf_token=$T&order_id=3&cancel_order=1" >/dev/null
expect "cannot cancel own order once it is past Pending" "$(SQL "SELECT order_status FROM orders WHERE id=3")" "Delivered"

echo "Feedback and profile"
T=$(token cust feedback.php)
post cust feedback.php -d "csrf_token=$T&comment=Great+food" >/dev/null
expect "feedback without rating saved as NULL" "$(SQL "SELECT IFNULL(rating,'NULL') FROM feedback ORDER BY id DESC LIMIT 1")" "NULL"
T=$(token cust feedback.php)
post cust feedback.php -d "csrf_token=$T&comment=Bad+rating&rating=9" >/dev/null
expect "out-of-range rating rejected" "$(SQL "SELECT COUNT(*) FROM feedback WHERE comment='Bad rating'")" "0"
T=$(token cust update_profile.php)
post cust update_profile.php -d "csrf_token=$T&update_profile=1&phone_number=0123456789" --data-urlencode 'name=<script>alert(1)</script>' >/dev/null
expect "profile accepts any text as name (stored raw, escaped on output)" "$(SQL "SELECT name FROM users WHERE email='test@example.com'")" "<script>alert(1)</script>"
T=$(token cust change_password.php)
post cust change_password.php -d "csrf_token=$T&current_password=Passw0rd1&new_password=NewPass99&confirm_new_password=NewPass99" >/dev/null
expect "changed password works for login" "$(login fresh test@example.com NewPass99)" "302 $URL/index.php"

echo "Admin"
expect "admin login redirects to dashboard" "$(login adm admin@example.com Admin@123)" "302 $URL/admin/dashboard.php"
D=$(get adm admin/dashboard.php)
contains "dashboard shows best sellers" "$D" "Best sellers"
contains "dashboard shows feedback count" "$D" "stat-label\">Feedback"
contains "user search finds by email"   "$(get adm 'admin/manage_users.php?q=sara@')" "sara@example.com"
if grep -q "customer@example.com" <<<"$(get adm 'admin/manage_users.php?q=sara@')"; then bad "user search excludes non-matches"; else ok "user search excludes non-matches"; fi
if grep -q '<script>alert(1)</script>' <<<"$D$(get adm admin/manage_users.php)"; then bad "user-supplied name is escaped (stored XSS)"; else ok "user-supplied name is escaped (stored XSS)"; fi

T=$(token adm admin/manage_menu.php)
curl -s -o /dev/null -c "$TMP/adm" -b "$TMP/adm" -F "csrf_token=$T" -F save_item=1 -F item_id=0 -F name="Adeni Tea" -F description="Spiced milk tea" -F price=3.50 -F category=Drinks "$URL/admin/manage_menu.php"
expect "new item keeps its text category" "$(SQL "SELECT category FROM menu_items WHERE name='Adeni Tea'")" "Drinks"
NEW=$(SQL "SELECT id FROM menu_items WHERE name='Adeni Tea'")
T=$(token adm admin/manage_menu.php)
printf '<?php echo 1;' > "$TMP/shell.php"
curl -s -o /dev/null -c "$TMP/adm" -b "$TMP/adm" -F "csrf_token=$T" -F save_item=1 -F item_id=0 -F name="Bad Upload" -F description=x -F price=1 -F category=Drinks -F "image=@$TMP/shell.php" "$URL/admin/manage_menu.php"
expect "non-image upload rejected" "$(SQL "SELECT COUNT(*) FROM menu_items WHERE name='Bad Upload'")" "0"
T=$(token adm admin/manage_menu.php)
post adm admin/manage_menu.php -d "csrf_token=$T&delete_item=1&item_id=1" >/dev/null
expect "item used in past orders cannot be deleted" "$(SQL "SELECT COUNT(*) FROM menu_items WHERE id=1")" "1"
T=$(token adm admin/manage_menu.php)
post adm admin/manage_menu.php -d "csrf_token=$T&delete_item=1&item_id=$NEW" >/dev/null
expect "unused item can be deleted" "$(SQL "SELECT COUNT(*) FROM menu_items WHERE id=$NEW")" "0"

T=$(token adm admin/manage_orders.php)
post adm admin/manage_orders.php -d "csrf_token=$T&update_order_status=1&order_id=7&new_status=Preparing&payment_status=Paid" >/dev/null
expect "admin updates order status" "$(SQL "SELECT CONCAT(order_status,'|',payment_status) FROM orders WHERE id=7")" "Preparing|Paid"
T=$(token adm admin/manage_orders.php)
post adm admin/manage_orders.php -d "csrf_token=$T&update_order_status=1&order_id=7&new_status=Shipped&payment_status=Paid" >/dev/null
expect "invalid status rejected" "$(SQL "SELECT order_status FROM orders WHERE id=7")" "Preparing"
contains "order search by number" "$(get adm 'admin/manage_orders.php?q=4')" "#4"
contains "admin order detail page" "$(get adm 'admin/view_order_details.php?order_id=1')" "Customer:"

UID_T=$(SQL "SELECT id FROM users WHERE email='test@example.com'")
T=$(token adm admin/manage_users.php)
post adm admin/manage_users.php -d "csrf_token=$T&delete_user=1&user_id=$UID_T" >/dev/null
expect "deleting a customer cascades to orders + feedback" \
  "$(SQL "SELECT (SELECT COUNT(*) FROM users WHERE id=$UID_T)+(SELECT COUNT(*) FROM orders WHERE user_id=$UID_T)+(SELECT COUNT(*) FROM feedback WHERE user_id=$UID_T)")" "0"
T=$(token adm admin/manage_users.php)
post adm admin/manage_users.php -d "csrf_token=$T&delete_user=1&user_id=1" >/dev/null
expect "admin cannot delete own account" "$(SQL "SELECT COUNT(*) FROM users WHERE id=1")" "1"

T=$(token adm admin/dashboard.php)
expect "logout (POST) redirects to login" "$(post adm logout.php -d "csrf_token=$T")" "302 $URL/login.php"
expect "session ends after logout" "$(code adm admin/dashboard.php)" "302 $URL/login.php"

echo "Server log"
if grep -Eiq "warning|notice|deprecated|fatal" "$TMP/server.log"; then
    bad "no PHP warnings/notices/errors"; grep -Ei "warning|notice|deprecated|fatal" "$TMP/server.log" | head -5
else
    ok "no PHP warnings/notices/errors"
fi

echo
echo "$PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ]
