<?php

/*
|--------------------------------------------------------------------------
| Authentication                                                           
|--------------------------------------------------------------------------
*/
// (if user is logged in)
function isLoggedIn()
{
    return isset($_SESSION['logged_in'])
        && $_SESSION['logged_in'] === true;
}
// (MFA)
function completeLogin(array $user)
{
    session_regenerate_id(true);

    $_SESSION['logged_in'] = true;
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['username'] = $user['username'] ?? $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['last_activity'] = time();

    unset($_SESSION['mfa_pending']);
    unset($_SESSION['mfa_user_id']);
}
// (if user is admin)
function isAdmin()
{
    return isset($_SESSION['user_role'])
        && $_SESSION['user_role'] === 'admin';
}

/*
|--------------------------------------------------------------------------
| Security 
|--------------------------------------------------------------------------
*/

// (escape text before html read)
function e($value)
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}
// (redirect to another page)
function redirect($url)
{
    header("Location: $url");
    exit;
}

/*
|--------------------------------------------------------------------------
| Products
|--------------------------------------------------------------------------
*/

// (convert image path to url)
function productImageSrc($path, $base = '')
{
    $path = trim($path ?? '');
    if ($path === '') {
        return '';
    }
    if(preg_match('#^https?://#i', $path)) {return $path; }
    return ltrim($path, '/');
}

// (format the price)
function formatPrice($price)
{
    return number_format(
        (float) $price,
        2
    );
}


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/
// (check if the email address is good or valid)
function isValidEmail($email)
{
    return filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    ) !== false;
}

// (check password length)
function isValidPasswordLength($password)
{
    return strlen($password) >= 12;
}

// (check password strength)
function isStrongPassword($password)
{
    return preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);
}

/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
*/

// (create or retrieve csrf token)
function getCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {

        $_SESSION['csrf_token'] = bin2hex(
            random_bytes(32)
        );
    }
    return $_SESSION['csrf_token'];
}

// (generate a hidden csrf input field)
function csrfField()
{
    return '<input type="hidden" name="csrf_token" value="'
        . e(getCsrfToken())
        . '">';
}

// (verify the token)
function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token'])
        && hash_equals(
            $_SESSION['csrf_token'],
            $token ?? ''
        );
}

/*
|--------------------------------------------------------------------------
| Cart
|--------------------------------------------------------------------------
*/

function getCart(PDO $pdo, $userId)
{
    $stmt = $pdo->prepare(
        "SELECT ci.product_id, ci.quantity,
                p.name, p.brand, p.price, p.image_path
         FROM cart_items ci
         JOIN products p ON p.product_id = ci.product_id
         WHERE ci.user_id = ? AND p.status = 'Active'
         ORDER BY ci.cart_item_id ASC"
    );
    $stmt->execute([(int) $userId]);

    $cart = [];
    foreach ($stmt->fetchAll() as $row) {
        $cart[(int) $row['product_id']] = $row;
    }
    return $cart;
}

function getCartItemCount(array $cart)
{
    $count = 0;
    foreach ($cart as $item) { $count += (int) ($item['quantity'] ?? 0); }
    return $count;
}

function getCartSubtotal(array $cart)
{
    $subtotal = 0;
    foreach ($cart as $item) {
        $price = (float) ($item['price'] ?? 0);
        $quantity = (int) ($item['quantity'] ?? 0);
        $subtotal += $price * $quantity;
    }
    return $subtotal;
}

function addToCart(PDO $pdo, $userId, $productId, $quantity = 1)
{
    $stmt = $pdo->prepare(
        "INSERT INTO cart_items (user_id, product_id, quantity)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + ?, 99)"
    );
    $stmt->execute([(int) $userId, (int) $productId, (int) $quantity, (int) $quantity]);
}

function setCartQuantity(PDO $pdo, $userId, $productId, $quantity)
{
    $quantity = (int) $quantity;

    if ($quantity <= 0) {
        removeFromCart($pdo, $userId, $productId);
        return;
    }

    $stmt = $pdo->prepare(
        "UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?"
    );
    $stmt->execute([min($quantity, 99), (int) $userId, (int) $productId]);
}

// (remove product from cart)
function removeFromCart(PDO $pdo, $userId, $productId)
{
    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id = ?");
    $stmt->execute([(int) $userId, (int) $productId]);
}

// (clear the whole cart)
function clearCart(PDO $pdo, $userId)
{
    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
    $stmt->execute([(int) $userId]);
}

/*
|--------------------------------------------------------------------------
| ORDERS
|--------------------------------------------------------------------------
*/

// (getting order status)
function getOrderStatusClass($status)
{
    $statusClasses = [
        'Pending' => 'pending',
        'Processing' => 'processing',
        'Shipped' => 'shipped',
        'Out for Delivery' => 'delivery',
        'Delivered' => 'delivered',
        'Cancelled' => 'cancelled'
    ];
    return $statusClasses[$status] ?? 'processing';
}

function tableHasColumn(PDO $pdo, $table, $column)
{
    static $cache = [];
    $key = $table . '.' . $column;

    if (!isset($cache[$key])) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        $cache[$key] = ((int) $stmt->fetchColumn()) > 0;
    }

    return $cache[$key];
}

function getUserOrders(PDO $pdo, $userId)
{
    $stmt = $pdo->prepare(
        "SELECT o.*, u.phone AS user_phone
         FROM orders o
         LEFT JOIN users u ON u.user_id = o.user_id
         WHERE o.user_id = ?
         ORDER BY o.created_at DESC, o.order_id DESC"
    );
    $stmt->execute([(int) $userId]);
    $rows = $stmt->fetchAll();

    if (!$rows) {
        return [];
    }

    $orders = [];
    foreach ($rows as $row) {
        $orderId = (int) $row['order_id'];

        $orders[$orderId] = [
            'number' => '#ORD-' . $orderId,
            'date' => date('M j, Y · g:i A', strtotime($row['created_at'])),
            'status' => $row['status'],
            'items' => [],
            'shipping' => 0.0,
            'total' => (float) $row['total_amount'],
            'mobile' => $row['contact_number'] ?? $row['user_phone'] ?? '',
            'address' => $row['shipping_address'],
            'request' => $row['order_notes'] ?? ''
        ];
    }

        $marks = implode(',', array_fill(0, count($orders), '?'));
    $stmt = $pdo->prepare(
        "SELECT oi.order_id, oi.quantity, oi.price_at_purchase,
                COALESCE(p.name, 'Unavailable product') AS name, p.image_path
         FROM order_items oi
         LEFT JOIN products p ON p.product_id = oi.product_id
         WHERE oi.order_id IN ($marks)
         ORDER BY oi.order_item_id ASC"
    );
    $stmt->execute(array_keys($orders));

    $subtotals = [];
    foreach ($stmt->fetchAll() as $item) {
        $orderId = (int) $item['order_id'];
        $price = (float) $item['price_at_purchase'];
        $quantity = (int) $item['quantity'];

        $orders[$orderId]['items'][] = [
            'name' => $item['name'],
            'image_path' => $item['image_path'] ?? '',
            'price' => $price,
            'quantity' => $quantity
        ];
        $subtotals[$orderId] = ($subtotals[$orderId] ?? 0) + ($price * $quantity);
    }

    foreach ($orders as $orderId => $order) {
        $orders[$orderId]['shipping'] = max(0, round($order['total'] - ($subtotals[$orderId] ?? 0), 2));
    }

    return array_values($orders);
}

/*
|--------------------------------------------------------------------------
| Session
|--------------------------------------------------------------------------
*/

// (user logged out)
function logoutUser()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

function setFlash($type, $message)
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlash()
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function isValidPhMobile($mobile)
{
    return preg_match('/^(09|\+639)\d{9}$/', $mobile) === 1;
}

function meetsPasswordPolicy($password, ?PDO $pdo = null)
{
    $rules = [
        'min_password_length' => 12,
        'require_uppercase'   => 1,
        'require_lowercase'   => 1,
        'require_number'      => 1,
        'require_special'     => 1,
    ];

    if ($pdo === null && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
    }

    if ($pdo !== null) {
        try {
            $rows = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($rules as $key => $default) {
                if (isset($rows[$key]) && is_numeric($rows[$key])) {
                    $rules[$key] = (int) $rows[$key];
                }
            }
        } catch (PDOException $e) {
        }
    }

    $length = strlen($password);

    return $length >= $rules['min_password_length']
        && $length <= 64
        && preg_match_all('/[A-Z]/', $password) >= $rules['require_uppercase']
        && preg_match_all('/[a-z]/', $password) >= $rules['require_lowercase']
        && preg_match_all('/[0-9]/', $password) >= $rules['require_number']
        && preg_match_all('/[^A-Za-z0-9]/', $password) >= $rules['require_special'];
}

function getAccountStats(PDO $pdo, $userId)
{
    $totalOrders = 0;
    $totalSpent = 0.0;
    $weekSpent = 0.0;
    $weekStart = strtotime('monday this week 00:00:00');

    $stmt = $pdo->prepare(
        "SELECT total_amount, created_at
         FROM orders
         WHERE user_id = ? AND status <> 'Cancelled'"
    );
    $stmt->execute([(int) $userId]);

    foreach ($stmt->fetchAll() as $row) {
        $amount = (float) $row['total_amount'];
        $totalOrders++;
        $totalSpent += $amount;

        if (strtotime($row['created_at']) >= $weekStart) {
            $weekSpent += $amount;
        }
    }

    return [
        'total_orders' => $totalOrders,
        'week_spent' => $weekSpent,
        'total_spent' => $totalSpent
    ];
}