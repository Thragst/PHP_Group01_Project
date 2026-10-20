<?php
$stmt = $pdo->prepare(
    "SELECT full_name, username, email, created_at
     FROM users
     WHERE user_id = ?
     LIMIT 1"
);
$stmt->execute([$userId]);
$account = $stmt->fetch();

if (!$account) {
    echo '<section class="account-page"><p class="account-notice error">Account not found. Please login again.</p></section>';
    return;
}

$stats = getAccountStats($pdo, $userId);
$flash = getFlash();
$registeredOn = $account['created_at'] ? date('F j, Y', strtotime($account['created_at'])) : '—';
?>
<section class="account-page">
    <div class="account-header">
        <p class="small-title">SPORTMART ACCOUNT</p>
        <h1>My Profile</h1>
        <p>View your order summary and update your personal details.</p>
    </div>

    <?php if ($flash): ?>
        <div class="account-notice <?php echo e($flash['type']); ?>">
            <?php echo e($flash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="stat-boxes">
        <div class="stat-box">
            <span class="stat-label">Total Orders</span>
            <strong class="stat-value"><?php echo (int) $stats['total_orders']; ?></strong>
        </div>
        <div class="stat-box">
            <span class="stat-label">Spent this week</span>
            <strong class="stat-value">₱<?php echo number_format($stats['week_spent'], 2); ?></strong>
        </div>
        <div class="stat-box">
            <span class="stat-label">Total spent</span>
            <strong class="stat-value">₱<?php echo number_format($stats['total_spent'], 2); ?></strong>
        </div>
    </div>

    <div class="account-card">
        <h2>Personal Information</h2>

        <form action="pages/account_actions.php" method="POST" class="account-form" novalidate>
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="update_profile">

            <div class="form-row">
                <label for="fullname">Full name</label>
                <input type="text" id="fullname" name="fullname" value="<?php echo e($account['full_name']); ?>" maxlength="100" required>
            </div>

            <div class="form-row">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo e($account['username']); ?>" maxlength="30" required>
                <small>Letters and numbers only (3–30 characters).</small>
            </div>

            <div class="form-row">
                <label for="email">Email</label>
                <input type="email" id="email" value="<?php echo e($account['email']); ?>" readonly disabled>
                <small>Email cannot be changed.</small>
            </div>

            <div class="form-row">
                <label for="registered">Registration date</label>
                <input type="text" id="registered" value="<?php echo e($registeredOn); ?>" readonly disabled>
            </div>

            <button type="submit" class="account-button">Save Changes</button>
        </form>
    </div>
</section>