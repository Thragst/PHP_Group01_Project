<?php
session_start();
$_SESSION['user_name'] = $_SESSION['user_name'] ?? 'Jeam Paul';
$_SESSION['user_role'] = $_SESSION['user_role'] ?? 'Admin';

include 'includes/admin_header.php';
?>

<div class="page-header">
    <div>
        <h2>Super Admin Dashboard</h2>
        <p style="color: #666; font-size: 0.95rem; margin-top: 5px;">Platform-wide sales, site activity, users, and search insights</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <h4>Platform-Wide Sales</h4>
        <div class="stat-value">₱124,500.00</div>
    </div>
    <div class="stat-card">
        <h4>Site Activity</h4>
        <div class="stat-value">1,420 Sessions</div>
    </div>
    <div class="stat-card">
        <h4>Total Users</h4>
        <div class="stat-value">388</div>
    </div>
    <div class="stat-card">
        <h4>Total Orders</h4>
        <div class="stat-value">94</div>
    </div>
</div>

<div class="insights-container">
    <div class="insight-card">
        <h3>Top Searched Keywords</h3>
        <ul class="insight-list">
            <li><span>1. Running Shoes</span> <strong style="color: var(--primary-dark-red);">342 searches</strong></li>
            <li><span>2. Basketball</span> <strong style="color: var(--primary-dark-red);">215 searches</strong></li>
            <li><span>3. Jersey</span> <strong style="color: var(--primary-dark-red);">180 searches</strong></li>
            <li><span>4. Football</span> <strong style="color: var(--primary-dark-red);">95 searches</strong></li>
        </ul>
    </div>

    <div class="insight-card">
        <h3>Queries with 0 Results</h3>
        <ul class="insight-list">
            <li><span>Cleats size 13</span> <span style="color:#999; font-size:0.85rem;">8 attempts</span></li>
            <li><span>Tennis racket bag</span> <span style="color:#999; font-size:0.85rem;">5 attempts</span></li>
            <li><span>Red futsal goal</span> <span style="color:#999; font-size:0.85rem;">3 attempts</span></li>
        </ul>
    </div>
</div>

<?php echo '</main></div></div></body></html>'; ?>