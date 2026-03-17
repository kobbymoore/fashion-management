<?php
require_once __DIR__ . '/../includes/functions.php';
auditLog('logout', 'User logged out');
session_destroy();
redirect(BASE_URL . '/auth/login.php');
