<?php
require __DIR__ . '/_init.php';

verify_csrf();
if (current_admin()) {
    log_activity('logout', 'Logged out');
}
logout_admin();
flash('success', 'You have been logged out.');
redirect('admin/login.php');
