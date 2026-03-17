<?php
require_once __DIR__ . '/../includes/functions.php';

function requireLogin(string $redirectTo = '/auth/login.php'): void {
    if (!isLoggedIn()) {
        setFlash('warning', 'Please log in to continue.');
        redirect(BASE_URL . $redirectTo);
    }
}

function requireRole(string $role, string $redirectTo = '/index.php'): void {
    requireLogin();
    if (!hasRole($role)) {
        setFlash('danger', 'Access denied. Insufficient permissions.');
        redirect(BASE_URL . $redirectTo);
    }
}

function requireStaff(): void {
    requireRole('staff', '/customer/dashboard.php');
}

function requireAdmin(): void {
    requireRole('admin', '/admin/dashboard.php');
}

function requireCustomer(): void {
    requireLogin();
    // customers can also view their pages
}
