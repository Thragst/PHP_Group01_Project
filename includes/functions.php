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
function productImageSrc($path)
{
    $path = trim($path ?? '');
    if ($path === '') {
        return '';
    }
    if ( str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
    ) {
        return $path;
    }
    $clean = ltrim($path, '/');
    if (str_starts_with(strtolower($clean), 'admin/')) {
        $clean = substr($clean, 6);
    }
    return $clean;
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

// (get the cart)
function getCart()
{
    return $_SESSION['cart'] ?? [];
}

// (count total items)
function getCartItemCount()
{
    $cart = getCart();
    $count = 0;
    foreach ($cart as $item) { $count += (int) ($item['quantity'] ?? 0); }
    return $count;
}

// (calculate the subtotal of cart)
function getCartSubtotal()
{
    $cart = getCart();
    $subtotal = 0;
    foreach ($cart as $item) {
        $price = (float) ($item['price'] ?? 0);
        $quantity = (int) ($item['quantity'] ?? 0);
        $subtotal += $price * $quantity;
    }
    return $subtotal;
}

// (add product to cart)
function addToCart($product)
{
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    $productId = (int) $product['product_id'];
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]['quantity']++;
    } else {
        $_SESSION['cart'][$productId] = [
            'product_id' => $productId,
            'name' => $product['name'],
            'price' => (float) $product['price'],
            'image_path' => $product['image_path'] ?? '',
            'quantity' => 1
        ];
    }
}

// (remove product from cart)
function removeFromCart($productId)
{
    $productId = (int) $productId;
    if (isset($_SESSION['cart'][$productId])) {
        unset($_SESSION['cart'][$productId]);
    }
}

// (clear the whole cart)
function clearCart()
{
    $_SESSION['cart'] = [];
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
        'Processing' => 'processing',
        'Shipped' => 'shipped',
        'Out for Delivery' => 'delivery',
        'Delivered' => 'delivered'
    ];
    return $statusClasses[$status] ?? 'processing';
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
