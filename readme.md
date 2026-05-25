# 🍞 Ting Hao Inventory Management System

## 📌 Project Overview

**Ting Hao Inventory Management System** is a web-based application designed for a bakery ingredient shop to manage inventory efficiently.

The system provides a **public-facing website (front page)** to introduce the shop and a **secure admin/staff system** to manage inventory, users, and daily operations.

---

## 🎯 Objectives

* Provide a professional **front page** to introduce the business
* Enable **efficient inventory management**
* Support **role-based access (Admin & Staff)**
* Improve stock tracking and reporting

---

## 🌐 Website Structure

### 🏠 Front Page (Public Access)

The first page of the system focuses on **business introduction and credibility**.

#### Features:

* 🧁 Shop introduction (mission, background)
* 📍 Shop address & location map
* 📞 Contact information (phone, email)
* 🕒 Opening hours
* 🧭 Navigation bar (Home, About, Products, Contact)
* 🔐 Admin Login access

> This page is designed to give users a clear understanding of the business before accessing the system.

---

## 🔐 System Access

* **Admin Login** → Full system control
* **Staff Login** → Limited operational access

---

# 🔑 Features

## 👨‍💼 Admin Features (Full Control)

### 1. User Management

* Add / Edit / Delete staff accounts
* Assign roles (Admin / Staff)

### 2. Inventory Management

* Add new ingredients/products
* Update product details (price, quantity)
* Delete products

### 3. Stock Control

* Monitor stock levels
* Record stock in (from suppliers)
* Record stock out (sales)

### 4. Reports & Analytics

* View sales reports
* View inventory reports
* Track low stock items

### 5. Supplier Management

* Add / Update supplier information
* Manage purchase orders

### 6. System Control

* Backup system data
* Manage system settings
* Ensure system security (authentication & access control)

---

## 👨‍🔧 Staff Features (Limited Access)

### 1. View Inventory

* Check available stock
* Search products

### 2. Stock Update

* Record stock in/out (with permission)

### 3. Sales Entry

* Update items sold

### 4. Basic Reports

* View stock list
* Check low stock alerts

---

## ⚖️ Role Comparison

| Feature              | Admin  | Staff      |
| -------------------- | ------ | ---------- |
| User Management      | ✅      | ❌          |
| Inventory Management | ✅      | ⚠️ Limited |
| Stock Control        | ✅      | ✅          |
| Reports              | ✅ Full | ⚠️ Basic   |
| System Settings      | ✅      | ❌          |

---

## 🧱 System Modules

* Front Page (Public Website)
* Authentication System (Login)
* Inventory Management Module
* User Management Module
* Reporting & Analytics Module
* Supplier Management Module

---

## 🎨 Design Concept

* Warm bakery theme 🍞
* Clean and modern UI
* User-friendly navigation
* Mobile responsive design

---

## 🛠️ Suggested Tech Stack

*(You can modify based on your implementation)*

* **Frontend:** HTML, CSS, JavaScript / React
* **Backend:** Laravel / Flask / Node.js
* **Database:** MySQL / PostgreSQL
* **Design Tool:** Figma

---

## 🚀 Future Improvements

* Online ordering system (e-commerce)
* Smart ingredient recommendation
* Barcode scanning for stock updates
* Real-time dashboard analytics

---

## 📷 Screens (Optional)

*(Add screenshots of your Figma or system here)*

---

## 👤 Author

**Ting Hao System Project**
Developed for academic assignment / project

---

## 📄 License

This project is for educational purposes.

---

## 📚 Documentation

Implementation reference for future maintenance:

* [Product Requirements Document](prd.md)
* [Current Function Inventory](docs/current-function-inventory.md)
* [Core Function Plan](docs/core-function-plan.md)
* [Backend API Documentation](docs/backend-api.md)
* [Implementation Reference](docs/implementation-reference.md)
* [Render Deployment Guide](docs/render-deploy.md)
* [Supabase Setup](docs/supabase-setup.md)





The seeder creates admin/staff users, categories, suppliers, ingredients, stock movements, restock requests, system settings, and a backup snapshot.
