# Fashion Studio GH – Setup Guide

## Prerequisites
- XAMPP installed (Apache + MySQL)
- PHP 7.4+

## Quick Setup (5 Steps)

### 1. Copy Files
Copy the entire `fashion mgt system` folder to:
```
C:\xampp\htdocs\fashion-management\
```

### 2. Start XAMPP
Open XAMPP Control Panel → Start **Apache** and **MySQL**

### 3. Import Database
- Open **http://localhost/phpmyadmin**
- Click **New** → Name: `fashion_db` → Create
- Click `fashion_db` → **Import** tab
- Choose file: `database/fashion_db.sql` → Click **Go**

### 4. Verify Config (optional)
Edit `config/config.php` if your XAMPP uses a different port or MySQL password.

### 5. Open the Website
- **Homepage:** http://localhost/fashion-management/index.php
- **Login:** http://localhost/fashion-management/auth/login.php

---

## Default Login Credentials
| Role     | Email                       | Password   |
|----------|-----------------------------|------------|
| Admin    | admin@fashionstudio.gh      | Admin@1234 |
| Staff    | staff@fashionstudio.gh      | Admin@1234 |
| Customer | ama@example.com             | Admin@1234 |
| Customer | akua@example.com            | Admin@1234 |

---

## Key Pages
| Page        | URL                                    |
|-------------|----------------------------------------|
| Home        | /index.php                             |
| Login       | /auth/login.php                        |
| Register    | /auth/register.php                     |
| Admin Panel | /admin/dashboard.php                   |
| Customer    | /customer/dashboard.php                |

---

## Features Implemented
- ✅ User Registration & Login (role-based)
- ✅ Customer Dashboard & Order Placement
- ✅ Order Tracking with Status Timeline
- ✅ Staff: Customer Management + Measurements
- ✅ Staff: Full Order Management (approve, assign, update)
- ✅ Inventory Management with Low-Stock Alerts
- ✅ Sales Recording + Financial Summary Charts
- ✅ CSV Report Exports (Sales, Orders, Inventory)
- ✅ User Management (Admin only)
- ✅ Audit Log (Admin only)
- ✅ Notifications System
- ✅ Responsive Design (Bootstrap 5)
- ✅ Pink Fashion Aesthetic Design
