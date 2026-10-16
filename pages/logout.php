<?php
session_start();

unset($_SESSION['logged_in']);
unset($_SESSION['user_name']);
unset($_SESSION['username']);
unset($_SESSION['user_id']);
unset($_SESSION['user_role']);

header('Location: index.php?logout=success');
exit;
?>