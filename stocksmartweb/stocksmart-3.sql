-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jul 13, 2026 at 04:21 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `stocksmart`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `activity_type` enum('add','update','delete','checkout','alert','transfer','login','logout','security') NOT NULL,
  `entity_type` varchar(40) DEFAULT NULL,
  `entity_id` int(10) UNSIGNED DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`log_id`, `user_id`, `activity_type`, `entity_type`, `entity_id`, `description`, `ip_address`, `created_at`) VALUES
(1, 2, 'add', 'products', 13, 'Organic Basmati Rice 5kg was added to inventory', NULL, '2026-06-21 07:48:00'),
(2, 3, 'update', 'inventory', 14, 'Stock count updated for Whole Milk 1L', NULL, '2026-06-21 07:12:00'),
(3, 4, 'checkout', 'orders', 2, 'Checkout completed — order #SC-10482', NULL, '2026-06-19 16:05:00'),
(4, 1, 'alert', 'products', 9, 'Low stock alert generated for Olive Oil 500ml', NULL, '2026-06-18 12:00:00'),
(5, 4, 'checkout', 'orders', 1, 'Checkout completed — order #SC-10479', NULL, '2026-06-18 11:20:00'),
(6, 2, 'update', 'products', 16, 'Reorder level adjusted for AA Batteries (4pk)', NULL, '2026-06-18 06:00:00'),
(7, 2, 'transfer', 'stock_transfers', 1, '40 units of Organic Basmati Rice 5kg transferred from Warehouse A to Store Front', NULL, '2026-06-19 10:00:00'),
(8, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-06-21 08:15:00'),
(9, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-07-11 06:33:44'),
(10, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-07-11 06:34:21'),
(11, 2, 'login', 'users', 2, 'Rajesh Karki logged in', NULL, '2026-07-11 06:35:18'),
(12, 3, 'login', 'users', 3, 'Sita Gurung logged in', NULL, '2026-07-11 06:35:18'),
(13, 4, 'login', 'users', 4, 'Bibek Thapa logged in', NULL, '2026-07-11 06:35:18'),
(14, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-07-11 06:36:18'),
(15, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-07-11 12:42:40'),
(16, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-07-11 15:08:30'),
(17, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-07-11 15:15:07'),
(18, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-07-11 15:21:52'),
(19, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-07-11 15:34:18'),
(21, 1, 'logout', 'users', 1, 'Annie Shrestha logged out', NULL, '2026-07-11 15:35:46'),
(22, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-07-11 15:36:04'),
(23, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-07-11 15:59:35'),
(24, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-07-11 16:43:05'),
(25, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-07-11 17:00:05'),
(26, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-07-11 17:06:37'),
(27, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-07-11 18:07:13'),
(28, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-07-12 02:14:01'),
(29, 1, 'logout', 'users', 1, 'Annie Shrestha logged out', NULL, '2026-07-12 02:14:55'),
(30, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-07-12 02:20:37'),
(31, 1, 'logout', 'users', 1, 'Annie Shrestha logged out', NULL, '2026-07-12 02:45:14'),
(32, 6, 'add', 'users', 6, 'Diya Manandhar registered a new account (pending email verification)', NULL, '2026-07-12 02:47:21'),
(33, 7, 'add', 'users', 7, 'Avash Shrestha registered a new account (pending email verification)', NULL, '2026-07-12 03:51:35'),
(34, 8, 'add', 'users', 8, 'Anya registered a new account (pending email verification)', NULL, '2026-07-12 03:54:22'),
(35, NULL, 'add', 'users', 9, 'Test Diag User registered a new account (pending email verification)', NULL, '2026-07-12 05:10:08'),
(36, NULL, 'add', 'users', 10, 'Test Diag User registered a new account (pending email verification)', NULL, '2026-07-12 05:10:26'),
(37, NULL, 'add', 'users', 11, 'Test Diag User3 registered a new account (pending email verification)', NULL, '2026-07-12 05:10:38'),
(38, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-07-12 05:12:35'),
(39, 1, 'update', 'users', 8, 'User Anya updated', NULL, '2026-07-12 05:16:35'),
(40, 1, 'logout', 'users', 1, 'Annie Shrestha logged out', NULL, '2026-07-12 05:18:51'),
(41, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-07-12 05:19:41'),
(42, 1, 'logout', 'users', 1, 'Annie Shrestha logged out', NULL, '2026-07-12 05:20:37'),
(43, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-07-12 05:21:44'),
(44, 1, 'update', 'users', 2, 'User Rajesh Karki updated (password reset)', NULL, '2026-07-12 05:22:12'),
(45, 1, 'update', 'users', 2, 'User Rajesh Karki updated', NULL, '2026-07-12 05:22:25'),
(46, 1, 'logout', 'users', 1, 'Annie Shrestha logged out', NULL, '2026-07-12 05:22:29'),
(47, 2, 'login', 'users', 2, 'Rajesh Karki logged in', NULL, '2026-07-12 05:22:45'),
(48, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-07-12 05:25:13'),
(49, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-07-12 15:14:31'),
(50, NULL, 'add', 'users', 12, 'Flow Test User registered a new account', NULL, '2026-07-12 15:52:52'),
(51, NULL, 'login', 'users', 12, 'Flow Test User logged in', NULL, '2026-07-12 15:52:52'),
(52, 1, 'logout', 'users', 1, 'Annie Shrestha logged out', NULL, '2026-07-12 16:14:13'),
(53, 13, 'add', 'users', 13, 'aaaaa registered a new account', NULL, '2026-07-12 16:14:41'),
(54, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-07-13 00:21:02'),
(55, 1, 'logout', 'users', 1, 'Annie Shrestha logged out', NULL, '2026-07-13 00:52:53'),
(56, 1, 'login', 'users', 1, 'Annie Shrestha logged in', NULL, '2026-07-13 00:53:20');

-- --------------------------------------------------------

--
-- Table structure for table `alerts`
--

CREATE TABLE `alerts` (
  `alert_id` int(10) UNSIGNED NOT NULL,
  `alert_type` enum('low_stock','out_of_stock','expiry') NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `warehouse_id` smallint(5) UNSIGNED DEFAULT NULL,
  `batch_id` int(10) UNSIGNED DEFAULT NULL,
  `message` varchar(255) NOT NULL,
  `severity` enum('info','warning','critical') NOT NULL DEFAULT 'warning',
  `is_acknowledged` tinyint(1) NOT NULL DEFAULT 0,
  `acknowledged_by` int(10) UNSIGNED DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `alerts`
--

INSERT INTO `alerts` (`alert_id`, `alert_type`, `product_id`, `warehouse_id`, `batch_id`, `message`, `severity`, `is_acknowledged`, `acknowledged_by`, `acknowledged_at`, `created_at`) VALUES
(1, 'out_of_stock', 9, 1, NULL, 'Olive Oil 500ml is out of stock at Warehouse A', 'critical', 0, NULL, NULL, '2026-06-18 12:00:00'),
(2, 'out_of_stock', 16, 2, NULL, 'AA Alkaline Batteries (4pk) is out of stock at Warehouse B', 'critical', 0, NULL, NULL, '2026-06-19 09:30:00'),
(3, 'low_stock', 1, 1, NULL, 'Butter 500g stock (4) is below reorder level (10)', 'warning', 0, NULL, NULL, '2026-06-18 09:05:00'),
(4, 'low_stock', 13, 1, NULL, 'Organic Basmati Rice 5kg stock is approaching reorder level', 'info', 1, NULL, NULL, '2026-06-19 09:05:00'),
(5, 'expiry', 19, 3, 1, 'Greek Yogurt 400g (batch BT-2291) expires today', 'critical', 0, NULL, NULL, '2026-06-21 06:00:00'),
(6, 'expiry', 20, 3, 2, 'Fresh Paneer 200g (batch BT-2304) expires in 2 days', 'warning', 0, NULL, NULL, '2026-06-21 06:00:00'),
(7, 'low_stock', 2, NULL, NULL, 'Cooking Oil 1L is low on stock (9 left, reorder at 15).', 'warning', 0, NULL, NULL, '2026-07-11 06:33:44'),
(8, 'low_stock', 3, NULL, NULL, 'Cheese Slice (10) is low on stock (6 left, reorder at 12).', 'warning', 0, NULL, NULL, '2026-07-11 06:33:44'),
(9, 'low_stock', 4, NULL, NULL, 'Yoghurt 400g is low on stock (14 left, reorder at 20).', 'warning', 0, NULL, NULL, '2026-07-11 06:33:44'),
(10, 'low_stock', 5, NULL, NULL, 'Tomato Paste 200g is low on stock (7 left, reorder at 15).', 'warning', 0, NULL, NULL, '2026-07-11 06:33:44'),
(11, 'low_stock', 6, NULL, NULL, 'Mineral Water 1L is low on stock (18 left, reorder at 25).', 'warning', 0, NULL, NULL, '2026-07-11 06:33:44'),
(12, 'low_stock', 7, NULL, NULL, 'Brown Bread 400g is low on stock (25 left, reorder at 30).', 'warning', 0, NULL, NULL, '2026-07-11 06:33:44'),
(13, 'low_stock', 8, NULL, NULL, 'Basmati Rice 1kg is low on stock (32 left, reorder at 35).', 'warning', 0, NULL, NULL, '2026-07-11 06:33:44'),
(14, 'out_of_stock', 10, NULL, NULL, 'Green Tea 100g is out of stock.', 'critical', 0, NULL, NULL, '2026-07-11 06:33:44'),
(15, 'low_stock', 14, NULL, NULL, 'Whole Milk 1L is low on stock (8 left, reorder at 20).', 'warning', 0, NULL, NULL, '2026-07-11 06:33:44'),
(16, 'low_stock', 18, NULL, NULL, 'Whole Wheat Bread is low on stock (14 left, reorder at 14).', 'warning', 0, NULL, NULL, '2026-07-11 06:33:44'),
(17, 'low_stock', 20, NULL, NULL, 'Fresh Paneer 200g is low on stock (9 left, reorder at 10).', 'warning', 0, NULL, NULL, '2026-07-11 06:33:44'),
(18, 'out_of_stock', 22, NULL, NULL, 'Mayonnaise 500ml is out of stock.', 'critical', 0, NULL, NULL, '2026-07-11 06:33:44'),
(19, 'expiry', 21, NULL, 3, 'Chicken Sausages 500g batch has expired.', 'critical', 0, NULL, NULL, '2026-07-11 06:33:44'),
(20, 'expiry', 22, NULL, 4, 'Mayonnaise 500ml batch has expired.', 'critical', 0, NULL, NULL, '2026-07-11 06:33:44'),
(21, 'expiry', 23, NULL, 5, 'Mixed Fruit Juice 1L batch has expired.', 'critical', 0, NULL, NULL, '2026-07-11 06:33:44'),
(22, 'expiry', 14, NULL, 6, 'Whole Milk 1L batch has expired.', 'critical', 0, NULL, NULL, '2026-07-11 06:33:44'),
(23, 'expiry', 18, NULL, 7, 'Whole Wheat Bread batch has expired.', 'critical', 0, NULL, NULL, '2026-07-11 06:33:44');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` smallint(5) UNSIGNED NOT NULL,
  `category_name` varchar(60) NOT NULL,
  `description` varchar(200) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `description`, `created_at`) VALUES
(1, 'Dairy', 'Milk, cheese, butter, yoghurt and related products', '2026-07-11 06:33:26'),
(2, 'Cooking', 'Cooking oils, ghee and culinary essentials', '2026-07-11 06:33:26'),
(3, 'Grocery', 'Staple grocery items: rice, flour, lentils', '2026-07-11 06:33:26'),
(4, 'Beverages', 'Water, soft drinks, juices, tea, coffee', '2026-07-11 06:33:26'),
(5, 'Bakery', 'Bread and baked goods', '2026-07-11 06:33:26'),
(6, 'Personal Care', 'Hygiene and personal care products', '2026-07-11 06:33:26'),
(7, 'Household', 'Cleaning and household consumables', '2026-07-11 06:33:26'),
(8, 'Frozen Foods', 'Frozen and chilled ready-to-cook items', '2026-07-11 06:33:26'),
(9, 'Snacks', 'Chips, chocolates and packaged snacks', '2026-07-11 06:33:26'),
(10, 'Grains & Staples', 'Rice, wheat and other bulk grains', '2026-07-11 06:33:26'),
(11, 'Pantry', 'Pantry staples: oils, sauces, condiments', '2026-07-11 06:33:26');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(10) UNSIGNED NOT NULL,
  `customer_name` varchar(100) NOT NULL DEFAULT 'Walk-in Customer',
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `loyalty_points` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `customer_name`, `phone`, `email`, `loyalty_points`, `created_at`) VALUES
(1, 'Walk-in Customer', NULL, NULL, 0.00, '2026-07-11 06:33:26'),
(2, 'Sujata Maharjan', '+977-9841000011', 'sujata.m@example.com', 120.50, '2026-07-11 06:33:26'),
(3, 'Nabin Pradhan', '+977-9841000012', 'nabin.p@example.com', 45.00, '2026-07-11 06:33:26'),
(4, 'Ramesh Bhattarai', '+977-9841000013', 'ramesh.b@example.com', 0.00, '2026-07-11 06:33:26');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `warehouse_id` smallint(5) UNSIGNED NOT NULL,
  `quantity_on_hand` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `quantity_reserved` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `quantity_available` int(11) GENERATED ALWAYS AS (cast(`quantity_on_hand` as signed) - cast(`quantity_reserved` as signed)) STORED,
  `last_counted_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`inventory_id`, `product_id`, `warehouse_id`, `quantity_on_hand`, `quantity_reserved`, `last_counted_at`, `updated_at`) VALUES
(1, 1, 1, 4, 0, '2026-06-18 09:00:00', '2026-07-11 06:33:26'),
(2, 2, 1, 9, 1, '2026-06-18 09:00:00', '2026-07-11 06:33:26'),
(3, 3, 3, 6, 0, '2026-06-18 09:00:00', '2026-07-11 06:33:26'),
(4, 4, 3, 14, 2, '2026-06-18 09:00:00', '2026-07-11 06:33:26'),
(5, 5, 1, 7, 0, '2026-06-18 09:00:00', '2026-07-11 06:33:26'),
(6, 6, 4, 18, 3, '2026-06-18 09:00:00', '2026-07-11 06:33:26'),
(7, 7, 4, 25, 0, '2026-06-18 09:00:00', '2026-07-11 06:33:26'),
(8, 8, 1, 32, 4, '2026-06-18 09:00:00', '2026-07-11 06:33:26'),
(9, 9, 1, 0, 0, '2026-06-18 09:00:00', '2026-07-11 06:33:26'),
(10, 10, 4, 0, 0, '2026-06-18 09:00:00', '2026-07-11 06:33:26'),
(11, 11, 3, 40, 5, '2026-06-18 09:00:00', '2026-07-11 06:33:26'),
(12, 12, 1, 55, 6, '2026-06-18 09:00:00', '2026-07-11 06:33:26'),
(13, 13, 1, 120, 14, '2026-06-19 09:00:00', '2026-07-11 06:33:26'),
(14, 14, 3, 8, 3, '2026-06-19 09:00:00', '2026-07-11 06:33:26'),
(15, 15, 1, 64, 6, '2026-06-19 09:00:00', '2026-07-11 06:33:26'),
(16, 16, 2, 0, 0, '2026-06-19 09:00:00', '2026-07-11 06:33:26'),
(17, 17, 2, 31, 5, '2026-06-19 09:00:00', '2026-07-11 06:33:26'),
(18, 18, 4, 14, 2, '2026-06-19 09:00:00', '2026-07-11 06:33:26'),
(19, 19, 3, 42, 8, '2026-06-19 09:00:00', '2026-07-11 06:33:26'),
(20, 20, 3, 9, 1, '2026-06-19 09:00:00', '2026-07-11 06:33:26'),
(21, 21, 3, 58, 11, '2026-06-19 09:00:00', '2026-07-11 06:33:26'),
(22, 22, 1, 0, 0, '2026-06-19 09:00:00', '2026-07-11 06:33:26'),
(23, 23, 2, 76, 9, '2026-06-19 09:00:00', '2026-07-11 06:33:26'),
(24, 24, 4, 140, 18, '2026-06-19 09:00:00', '2026-07-11 06:33:26'),
(25, 25, 2, 22, 4, '2026-06-19 09:00:00', '2026-07-11 06:33:26');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `notification_type` varchar(40) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` varchar(500) NOT NULL,
  `entity_type` varchar(40) DEFAULT NULL,
  `entity_id` int(10) UNSIGNED DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `notification_type`, `title`, `message`, `entity_type`, `entity_id`, `read_at`, `created_at`) VALUES
(1, NULL, 'low_stock', 'Low Stock', 'Cooking Oil 1L is low on stock (9 left, reorder at 15).', 'products', 2, NULL, '2026-07-11 06:33:44'),
(2, NULL, 'low_stock', 'Low Stock', 'Cheese Slice (10) is low on stock (6 left, reorder at 12).', 'products', 3, NULL, '2026-07-11 06:33:44'),
(3, NULL, 'low_stock', 'Low Stock', 'Yoghurt 400g is low on stock (14 left, reorder at 20).', 'products', 4, NULL, '2026-07-11 06:33:44'),
(4, NULL, 'low_stock', 'Low Stock', 'Tomato Paste 200g is low on stock (7 left, reorder at 15).', 'products', 5, NULL, '2026-07-11 06:33:44'),
(5, NULL, 'low_stock', 'Low Stock', 'Mineral Water 1L is low on stock (18 left, reorder at 25).', 'products', 6, NULL, '2026-07-11 06:33:44'),
(6, NULL, 'low_stock', 'Low Stock', 'Brown Bread 400g is low on stock (25 left, reorder at 30).', 'products', 7, NULL, '2026-07-11 06:33:44'),
(7, NULL, 'low_stock', 'Low Stock', 'Basmati Rice 1kg is low on stock (32 left, reorder at 35).', 'products', 8, NULL, '2026-07-11 06:33:44'),
(8, NULL, 'out_of_stock', 'Out of Stock', 'Green Tea 100g is out of stock.', 'products', 10, NULL, '2026-07-11 06:33:44'),
(9, NULL, 'low_stock', 'Low Stock', 'Whole Milk 1L is low on stock (8 left, reorder at 20).', 'products', 14, NULL, '2026-07-11 06:33:44'),
(10, NULL, 'low_stock', 'Low Stock', 'Whole Wheat Bread is low on stock (14 left, reorder at 14).', 'products', 18, NULL, '2026-07-11 06:33:44'),
(11, NULL, 'low_stock', 'Low Stock', 'Fresh Paneer 200g is low on stock (9 left, reorder at 10).', 'products', 20, NULL, '2026-07-11 06:33:44'),
(12, NULL, 'out_of_stock', 'Out of Stock', 'Mayonnaise 500ml is out of stock.', 'products', 22, NULL, '2026-07-11 06:33:44'),
(13, NULL, 'expiry', 'Expiry Alert', 'Chicken Sausages 500g batch has expired.', 'products', 21, NULL, '2026-07-11 06:33:44'),
(14, NULL, 'expiry', 'Expiry Alert', 'Mayonnaise 500ml batch has expired.', 'products', 22, NULL, '2026-07-11 06:33:44'),
(15, NULL, 'expiry', 'Expiry Alert', 'Mixed Fruit Juice 1L batch has expired.', 'products', 23, NULL, '2026-07-11 06:33:44'),
(16, NULL, 'expiry', 'Expiry Alert', 'Whole Milk 1L batch has expired.', 'products', 14, NULL, '2026-07-11 06:33:44'),
(17, NULL, 'expiry', 'Expiry Alert', 'Whole Wheat Bread batch has expired.', 'products', 18, NULL, '2026-07-11 06:33:44');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(10) UNSIGNED NOT NULL,
  `order_number` varchar(30) NOT NULL,
  `customer_id` int(10) UNSIGNED DEFAULT NULL,
  `cashier_id` int(10) UNSIGNED NOT NULL,
  `warehouse_id` smallint(5) UNSIGNED NOT NULL,
  `items_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `loyalty_discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('cash','card','mobile_wallet','bank_transfer','other') NOT NULL DEFAULT 'cash',
  `order_status` enum('completed','pending','refunded','partially_refunded','cancelled') NOT NULL DEFAULT 'completed',
  `order_date` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `order_number`, `customer_id`, `cashier_id`, `warehouse_id`, `items_total`, `discount_amount`, `loyalty_discount`, `tax_amount`, `grand_total`, `payment_method`, `order_status`, `order_date`) VALUES
(1, 'SC-10479', 2, 4, 4, 3767.00, 0.00, 0.00, 0.00, 3767.00, 'cash', 'completed', '2026-06-18 11:20:00'),
(2, 'SC-10482', 1, 4, 4, 1064.00, 0.00, 0.00, 0.00, 1064.00, 'card', 'completed', '2026-06-19 16:05:00'),
(3, 'SC-10483', 3, 5, 4, 708.00, 0.00, 0.00, 0.00, 708.00, 'cash', 'completed', '2026-06-19 17:42:00'),
(4, 'SC-10484', 1, 4, 4, 532.00, 0.00, 0.00, 0.00, 532.00, 'mobile_wallet', 'completed', '2026-06-20 09:10:00'),
(5, 'SC-10485', 4, 4, 4, 429.00, 0.00, 0.00, 0.00, 429.00, 'cash', 'completed', '2026-06-20 13:55:00');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `batch_id` int(10) UNSIGNED DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(12,2) NOT NULL,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(12,2) GENERATED ALWAYS AS (round(`quantity` * `unit_price` - `discount_amount`,2)) STORED
) ;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `batch_id`, `quantity`, `unit_price`, `discount_amount`) VALUES
(1, 1, 8, NULL, 2.00, 150.00, 0.00),
(2, 1, 11, NULL, 1.00, 480.00, 0.00),
(3, 1, 6, NULL, 5.00, 30.00, 0.00),
(4, 1, 9, NULL, 5.00, 480.00, 0.00),
(5, 2, 21, 3, 2.00, 320.00, 0.00),
(6, 2, 4, NULL, 5.00, 85.00, 0.00),
(7, 3, 6, NULL, 2.00, 30.00, 0.00),
(8, 3, 19, 1, 4.00, 140.00, 0.00),
(9, 4, 23, 5, 2.00, 180.00, 0.00),
(10, 4, 25, NULL, 1.00, 190.00, 18.00),
(11, 5, 6, NULL, 1.00, 30.00, 0.00),
(12, 5, 24, NULL, 3.00, 110.00, 1.00);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `token_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `permission_id` smallint(5) UNSIGNED NOT NULL,
  `permission_key` varchar(80) NOT NULL,
  `permission_name` varchar(120) NOT NULL,
  `module_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`permission_id`, `permission_key`, `permission_name`, `module_name`) VALUES
(1, 'dashboard.view', 'View dashboard', 'dashboard'),
(2, 'products.view', 'View products', 'products'),
(3, 'products.manage', 'Manage products', 'products'),
(4, 'inventory.view', 'View inventory', 'inventory'),
(5, 'inventory.manage', 'Manage inventory', 'inventory'),
(6, 'checkout.use', 'Use checkout', 'checkout'),
(7, 'sales.view', 'View sales', 'sales'),
(8, 'sales.manage', 'Manage sales and refunds', 'sales'),
(9, 'purchases.view', 'View purchases', 'purchases'),
(10, 'purchases.manage', 'Manage purchase orders', 'purchases'),
(11, 'suppliers.view', 'View suppliers', 'suppliers'),
(12, 'suppliers.manage', 'Manage suppliers', 'suppliers'),
(13, 'customers.view', 'View customers', 'customers'),
(14, 'customers.manage', 'Manage customers', 'customers'),
(15, 'reports.view', 'View reports', 'reports'),
(16, 'alerts.view', 'View alerts', 'alerts'),
(17, 'notifications.view', 'View notifications', 'notifications'),
(18, 'users.manage', 'Manage users', 'users'),
(19, 'roles.manage', 'Manage roles and permissions', 'roles'),
(20, 'settings.manage', 'Manage settings', 'settings'),
(21, 'logs.view', 'View audit logs', 'logs');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(10) UNSIGNED NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `sku` varchar(40) NOT NULL,
  `barcode` varchar(64) DEFAULT NULL,
  `category_id` smallint(5) UNSIGNED NOT NULL,
  `supplier_id` int(10) UNSIGNED DEFAULT NULL,
  `icon_emoji` varchar(10) DEFAULT '?',
  `unit` varchar(20) NOT NULL DEFAULT 'pcs',
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cost_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reorder_level` int(10) UNSIGNED NOT NULL DEFAULT 10,
  `has_expiry` tinyint(1) NOT NULL DEFAULT 0,
  `default_expiry_date` date DEFAULT NULL,
  `status` enum('active','inactive','discontinued') NOT NULL DEFAULT 'active',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `product_name`, `sku`, `barcode`, `category_id`, `supplier_id`, `icon_emoji`, `unit`, `price`, `cost_price`, `reorder_level`, `has_expiry`, `default_expiry_date`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Butter 500g', 'DAIRY-001', NULL, 1, 1, NULL, 'pcs', 120.00, 90.00, 10, 1, '2026-08-15', 'active', 1, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(2, 'Cooking Oil 1L', 'COOK-002', NULL, 2, 4, NULL, 'pcs', 220.00, 170.00, 15, 0, NULL, 'active', 1, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(3, 'Cheese Slice (10)', 'DAIRY-003', NULL, 1, 1, NULL, 'pack', 180.00, 140.00, 12, 1, '2026-09-01', 'active', 1, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(4, 'Yoghurt 400g', 'DAIRY-004', NULL, 1, 1, NULL, 'pcs', 85.00, 60.00, 20, 1, '2026-07-05', 'active', 1, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(5, 'Tomato Paste 200g', 'GROC-005', NULL, 3, 4, NULL, 'pcs', 60.00, 42.00, 15, 0, NULL, 'active', 1, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(6, 'Mineral Water 1L', 'BEV-006', NULL, 4, 3, NULL, 'pcs', 30.00, 18.00, 25, 0, NULL, 'active', 1, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(7, 'Brown Bread 400g', 'BAK-007', NULL, 5, 5, NULL, 'pcs', 70.00, 48.00, 30, 1, '2026-06-25', 'active', 1, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(8, 'Basmati Rice 1kg', 'GROC-008', NULL, 3, 4, NULL, 'kg', 150.00, 110.00, 35, 0, NULL, 'active', 1, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(9, 'Olive Oil 500ml', 'COOK-009', NULL, 2, 4, NULL, 'pcs', 480.00, 380.00, 10, 0, NULL, 'active', 1, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(10, 'Green Tea 100g', 'BEV-010', NULL, 4, 3, NULL, 'pcs', 210.00, 160.00, 10, 1, '2027-01-10', 'active', 1, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(11, 'Eggs (Tray 30)', 'DAIRY-011', NULL, 1, 1, NULL, 'tray', 480.00, 400.00, 20, 1, '2026-06-29', 'active', 1, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(12, 'Wheat Flour 5kg', 'GROC-012', NULL, 3, 4, NULL, 'kg', 560.00, 460.00, 25, 0, NULL, 'active', 1, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(13, 'Organic Basmati Rice 5kg', 'STK-1042', NULL, 10, 4, NULL, 'kg', 850.00, 700.00, 30, 0, NULL, 'active', 2, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(14, 'Whole Milk 1L', 'STK-2031', NULL, 1, 1, NULL, 'pcs', 110.00, 85.00, 20, 1, '2026-06-24', 'active', 2, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(15, 'Extra Virgin Olive Oil 500ml', 'STK-3087', NULL, 11, 4, NULL, 'pcs', 520.00, 410.00, 12, 0, NULL, 'active', 2, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(16, 'AA Alkaline Batteries (4pk)', 'STK-4410', NULL, 7, 2, NULL, 'pack', 250.00, 190.00, 18, 0, NULL, 'active', 2, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(17, 'Hand Sanitizer 250ml', 'STK-5126', NULL, 6, 2, NULL, 'pcs', 150.00, 110.00, 16, 0, NULL, 'active', 2, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(18, 'Whole Wheat Bread', 'STK-6203', NULL, 5, 5, NULL, 'pcs', 75.00, 52.00, 14, 1, '2026-06-23', 'active', 2, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(19, 'Greek Yogurt 400g', 'STK-7089', NULL, 1, 1, NULL, 'pcs', 140.00, 105.00, 15, 1, '2026-06-21', 'active', 2, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(20, 'Fresh Paneer 200g', 'STK-7144', NULL, 1, 1, NULL, 'pcs', 160.00, 120.00, 10, 1, '2026-06-23', 'active', 2, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(21, 'Chicken Sausages 500g', 'STK-8021', NULL, 8, 2, NULL, 'pack', 320.00, 250.00, 20, 1, '2026-06-25', 'active', 2, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(22, 'Mayonnaise 500ml', 'STK-3098', NULL, 11, 4, NULL, 'pcs', 290.00, 220.00, 12, 1, '2026-06-27', 'active', 2, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(23, 'Mixed Fruit Juice 1L', 'STK-9012', NULL, 4, 3, NULL, 'pcs', 180.00, 130.00, 22, 1, '2026-06-30', 'active', 2, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(24, 'Potato Chips 150g', 'STK-9145', NULL, 9, 2, NULL, 'pack', 110.00, 80.00, 30, 0, NULL, 'active', 2, '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(25, 'Dish Washing Liquid 1L', 'STK-4477', NULL, 7, 2, NULL, 'pcs', 190.00, 145.00, 18, 0, NULL, 'active', 2, '2026-07-11 06:33:26', '2026-07-11 06:33:26');

-- --------------------------------------------------------

--
-- Table structure for table `product_batches`
--

CREATE TABLE `product_batches` (
  `batch_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `warehouse_id` smallint(5) UNSIGNED NOT NULL,
  `batch_number` varchar(40) NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `manufacture_date` date DEFAULT NULL,
  `expiry_date` date NOT NULL,
  `received_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_batches`
--

INSERT INTO `product_batches` (`batch_id`, `product_id`, `warehouse_id`, `batch_number`, `quantity`, `manufacture_date`, `expiry_date`, `received_at`) VALUES
(1, 19, 3, 'BT-2291', 14, '2026-06-07', '2026-06-21', '2026-07-11 06:33:26'),
(2, 20, 3, 'BT-2304', 9, '2026-06-09', '2026-06-23', '2026-07-11 06:33:26'),
(3, 21, 3, 'BT-2287', 21, '2026-06-11', '2026-06-25', '2026-07-11 06:33:26'),
(4, 22, 1, 'BT-2266', 7, '2026-06-08', '2026-06-27', '2026-07-11 06:33:26'),
(5, 23, 2, 'BT-2310', 16, '2026-06-12', '2026-06-30', '2026-07-11 06:33:26'),
(6, 14, 3, 'BT-2318', 8, '2026-06-10', '2026-06-24', '2026-07-11 06:33:26'),
(7, 18, 4, 'BT-2299', 14, '2026-06-09', '2026-06-23', '2026-07-11 06:33:26');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `purchase_order_id` int(10) UNSIGNED NOT NULL,
  `po_number` varchar(30) NOT NULL,
  `supplier_id` int(10) UNSIGNED NOT NULL,
  `warehouse_id` smallint(5) UNSIGNED NOT NULL,
  `status` enum('draft','ordered','partial','received','cancelled') NOT NULL DEFAULT 'draft',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `due_date` date DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `ordered_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `purchase_order_item_id` int(10) UNSIGNED NOT NULL,
  `purchase_order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `ordered_quantity` decimal(10,2) NOT NULL,
  `received_quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit_cost` decimal(12,2) NOT NULL,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `returns`
--

CREATE TABLE `returns` (
  `return_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `processed_by` int(10) UNSIGNED DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `refund_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `return_items`
--

CREATE TABLE `return_items` (
  `return_item_id` int(10) UNSIGNED NOT NULL,
  `return_id` int(10) UNSIGNED NOT NULL,
  `order_item_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `line_refund` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` tinyint(3) UNSIGNED NOT NULL,
  `role_name` varchar(30) NOT NULL,
  `role_description` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `role_description`) VALUES
(1, 'Admin', 'Full system access: manage users, products, inventory, settings'),
(2, 'Manager', 'Manage products, inventory, view reports, approve transfers'),
(3, 'Staff', 'Manage inventory counts and stock movements'),
(4, 'Cashier', 'Process checkout / sales transactions only'),
(5, 'Super Admin', 'Unrestricted system administration'),
(6, 'Inventory Staff', 'Inventory, purchasing and warehouse operations'),
(7, 'Viewer', 'Read-only business visibility');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` tinyint(3) UNSIGNED NOT NULL,
  `permission_id` smallint(5) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(1, 15),
(1, 16),
(1, 17),
(1, 18),
(1, 19),
(1, 20),
(1, 21),
(2, 1),
(2, 2),
(2, 3),
(2, 4),
(2, 5),
(2, 7),
(2, 9),
(2, 10),
(2, 11),
(2, 12),
(2, 13),
(2, 14),
(2, 15),
(2, 16),
(2, 17),
(3, 1),
(3, 2),
(3, 4),
(3, 5),
(3, 9),
(3, 10),
(3, 11),
(3, 16),
(3, 17),
(4, 1),
(4, 2),
(4, 4),
(4, 6),
(4, 7),
(4, 13),
(4, 14),
(4, 17),
(5, 1),
(5, 2),
(5, 3),
(5, 4),
(5, 5),
(5, 6),
(5, 7),
(5, 8),
(5, 9),
(5, 10),
(5, 11),
(5, 12),
(5, 13),
(5, 14),
(5, 15),
(5, 16),
(5, 17),
(5, 18),
(5, 19),
(5, 20),
(5, 21),
(6, 1),
(6, 2),
(6, 4),
(6, 5),
(6, 9),
(6, 10),
(6, 11),
(6, 16),
(6, 17),
(7, 1),
(7, 2),
(7, 4),
(7, 7),
(7, 9),
(7, 11),
(7, 13),
(7, 15),
(7, 16),
(7, 17);

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `movement_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `warehouse_id` smallint(5) UNSIGNED NOT NULL,
  `movement_type` enum('purchase_in','sale_out','adjustment_in','adjustment_out','transfer_in','transfer_out','return_in','damage_out') NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `reference_table` varchar(30) DEFAULT NULL,
  `reference_id` int(10) UNSIGNED DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `performed_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`movement_id`, `product_id`, `warehouse_id`, `movement_type`, `quantity`, `reference_table`, `reference_id`, `notes`, `performed_by`, `created_at`) VALUES
(1, 8, 1, 'purchase_in', 60, 'suppliers', 4, 'Initial stock receipt from Sagarmatha Grocers', 2, '2026-06-01 08:00:00'),
(2, 8, 1, 'sale_out', 2, 'orders', 1, 'Sold via order SC-10479', 4, '2026-06-18 11:20:00'),
(3, 11, 3, 'purchase_in', 50, 'suppliers', 1, 'Egg tray restock', 3, '2026-06-02 08:00:00'),
(4, 11, 3, 'sale_out', 1, 'orders', 1, 'Sold via order SC-10479', 4, '2026-06-18 11:20:00'),
(5, 9, 1, 'sale_out', 5, 'orders', 1, 'Sold via order SC-10479 — depleted remaining stock', 4, '2026-06-18 11:20:00'),
(6, 6, 4, 'sale_out', 5, 'orders', 1, 'Sold via order SC-10479', 4, '2026-06-18 11:20:00'),
(7, 21, 3, 'sale_out', 2, 'orders', 2, 'Sold via order SC-10482', 4, '2026-06-19 16:05:00'),
(8, 4, 3, 'sale_out', 5, 'orders', 2, 'Sold via order SC-10482', 4, '2026-06-19 16:05:00'),
(9, 6, 4, 'sale_out', 2, 'orders', 3, 'Sold via order SC-10483', 5, '2026-06-19 17:42:00'),
(10, 19, 3, 'sale_out', 4, 'orders', 3, 'Sold via order SC-10483', 5, '2026-06-19 17:42:00'),
(11, 23, 2, 'sale_out', 2, 'orders', 4, 'Sold via order SC-10484', 4, '2026-06-20 09:10:00'),
(12, 25, 2, 'sale_out', 1, 'orders', 4, 'Sold via order SC-10484', 4, '2026-06-20 09:10:00'),
(13, 6, 4, 'sale_out', 1, 'orders', 5, 'Sold via order SC-10485', 4, '2026-06-20 13:55:00'),
(14, 24, 4, 'sale_out', 3, 'orders', 5, 'Sold via order SC-10485', 4, '2026-06-20 13:55:00'),
(15, 13, 1, 'transfer_out', 40, 'stock_transfers', 1, 'Transferred to Store Front', 2, '2026-06-19 10:00:00'),
(16, 13, 4, 'transfer_in', 40, 'stock_transfers', 1, 'Received from Warehouse A', 2, '2026-06-19 10:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `stock_transfers`
--

CREATE TABLE `stock_transfers` (
  `transfer_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `from_warehouse_id` smallint(5) UNSIGNED NOT NULL,
  `to_warehouse_id` smallint(5) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','in_transit','completed','cancelled') NOT NULL DEFAULT 'completed',
  `requested_by` int(10) UNSIGNED DEFAULT NULL,
  `transferred_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `stock_transfers`
--

INSERT INTO `stock_transfers` (`transfer_id`, `product_id`, `from_warehouse_id`, `to_warehouse_id`, `quantity`, `status`, `requested_by`, `transferred_at`) VALUES
(1, 13, 1, 4, 40, 'completed', 2, '2026-06-19 10:00:00');

--
-- Triggers `stock_transfers`
--
DELIMITER $$
CREATE TRIGGER `trg_stock_transfers_diff_warehouse_ins` BEFORE INSERT ON `stock_transfers` FOR EACH ROW BEGIN
    IF NEW.from_warehouse_id = NEW.to_warehouse_id THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'from_warehouse_id and to_warehouse_id must differ';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_stock_transfers_diff_warehouse_upd` BEFORE UPDATE ON `stock_transfers` FOR EACH ROW BEGIN
    IF NEW.from_warehouse_id = NEW.to_warehouse_id THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'from_warehouse_id and to_warehouse_id must differ';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(10) UNSIGNED NOT NULL,
  `supplier_name` varchar(120) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `contact_person`, `phone`, `email`, `address`, `status`, `created_at`) VALUES
(1, 'Himalayan Dairy Distributors', 'Suresh Maharjan', '+977-9851000001', 'sales@himalayandairy.com', 'Balaju, Kathmandu', 'active', '2026-07-11 06:33:26'),
(2, 'Everest FMCG Traders', 'Anita Shakya', '+977-9851000002', 'orders@everestfmcg.com', 'Teku, Kathmandu', 'active', '2026-07-11 06:33:26'),
(3, 'Kathmandu Beverages Pvt. Ltd.', 'Bishnu Lama', '+977-9851000003', 'info@ktmbeverages.com', 'Balkhu, Kathmandu', 'active', '2026-07-11 06:33:26'),
(4, 'Sagarmatha Grocers Supply', 'Prakash Adhikari', '+977-9851000004', 'contact@sagarmathagrocers.com', 'Kalanki, Kathmandu', 'active', '2026-07-11 06:33:26'),
(5, 'Annapurna Bakery Supplies', 'Kabita Basnet', '+977-9851000005', 'hello@annapurnabakery.com', 'Patan, Lalitpur', 'inactive', '2026-07-11 06:33:26');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(80) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_by`, `updated_at`) VALUES
('company_name', 'StockSmart', NULL, '2026-07-11 06:33:26'),
('currency', 'NPR', NULL, '2026-07-11 06:33:26'),
('default_tax_rate', '0', NULL, '2026-07-11 06:33:26'),
('invoice_prefix', 'INV-', NULL, '2026-07-11 06:33:26'),
('receipt_footer', 'Thank you for your business.', NULL, '2026-07-11 06:33:26'),
('timezone', 'Asia/Kathmandu', NULL, '2026-07-11 06:33:26');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL COMMENT 'bcrypt / password_hash() output',
  `role_id` tinyint(3) UNSIGNED NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `avatar_emoji` varchar(10) DEFAULT '?',
  `status` enum('active','inactive','suspended','pending') NOT NULL DEFAULT 'active',
  `failed_login_attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `username`, `email`, `password_hash`, `role_id`, `phone`, `avatar_emoji`, `status`, `failed_login_attempts`, `locked_until`, `email_verified_at`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'Annie Shrestha', 'annie.admin', 'annie@stocksmart.com', '$2y$10$O5eIoi8XCXcThzhJI0.nleTMIDKI4UK5DMj5kgKiHT5PsaEgPgfni', 1, '+977-9800000001', NULL, 'active', 0, NULL, NULL, '2026-07-13 06:38:20', '2026-07-11 06:33:26', '2026-07-13 00:53:20'),
(2, 'Rajesh Karki', 'rajesh.mgr', 'rajesh@stocksmart.com', '$2y$10$QVbbXHQw9zt1nt2qGRPt/e1BAF3Yg4X6ZT5shfTNcSayvWwXjRQsa', 7, '+977-9800000002', NULL, 'active', 0, NULL, NULL, '2026-07-12 11:07:45', '2026-07-11 06:33:26', '2026-07-12 05:22:45'),
(3, 'Sita Gurung', 'sita.staff', 'sita@stocksmart.com', '$2y$10$8yZTNIrYeHor64RnwjYYuOXAHgnnUeUSHBdlilPCeX9fy8S1ekkY.', 3, '+977-9800000003', NULL, 'active', 0, NULL, NULL, '2026-07-11 12:20:18', '2026-07-11 06:33:26', '2026-07-11 06:35:18'),
(4, 'Bibek Thapa', 'bibek.cash', 'bibek@stocksmart.com', '$2y$10$hswxzVCcFhVY0LpPpdia3ugIapdaSK2Wf8/71oyfcM./NvyB1atqa', 4, '+977-9800000004', NULL, 'active', 0, NULL, NULL, '2026-07-11 12:20:18', '2026-07-11 06:33:26', '2026-07-11 06:35:18'),
(5, 'Manisha Rai', 'manisha.cash', 'manisha@stocksmart.com', '$2y$10$NRN6bBcOKLarRqrSbFVKfuU604lkJAjdqJMLbqzyashwBqlzDlSDC', 4, '+977-9800000005', NULL, 'inactive', 0, NULL, NULL, '2026-06-10 10:00:00', '2026-07-11 06:33:26', '2026-07-11 06:33:26'),
(6, 'Diya Manandhar', 'Diya', 'diamanandhar1@gmail.com', '$2y$10$m9DT4RSEHtNBsrUcaszkjeo8NsnRP/Pnz/LQIHspuY2dHciCqgd0m', 3, '9860171183', 'D', 'active', 0, NULL, '2026-07-12 21:35:52', NULL, '2026-07-12 02:47:21', '2026-07-12 15:50:52'),
(7, 'Avash Shrestha', 'Avash', 'avashdhakre@gmail.com', '$2y$10$ktiNhcW/hffms9M6qIHOpeWEcQazseDzsfSYg4.JE2c3PhQKmtDGS', 3, '9765411626', 'A', 'active', 0, NULL, '2026-07-12 21:35:52', NULL, '2026-07-12 03:51:35', '2026-07-12 15:50:52'),
(8, 'Anya', 'Anya', 'avashphoto1@gmail.com', '$2y$10$DTwTVhMnMs0t48HKqJ6V1.omKhhwfO1PVhq0Haf4GLg7mUsKLxL/G', 7, '9848579854', 'A', 'active', 1, NULL, NULL, NULL, '2026-07-12 03:54:22', '2026-07-12 05:20:59'),
(13, 'aaaaa', 'aaaaa', 'a@gmail.com', '$2y$10$JMaVSyVaxnvMXV27qw5alu/VyiRqbaO.93fV53gDyRefKq7qJnA32', 3, '988888888888', 'A', 'active', 0, NULL, '2026-07-12 21:59:41', NULL, '2026-07-12 16:14:41', '2026-07-12 16:14:41');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `session_id` varchar(128) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `last_seen_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_sessions`
--

INSERT INTO `user_sessions` (`session_id`, `user_id`, `ip_address`, `user_agent`, `last_seen_at`, `expires_at`, `created_at`) VALUES
('2561e3e6fb3ac88f76c39812e3799e85', 1, '127.0.0.1', 'curl/8.7.1', '2026-07-11 22:28:23', '2026-07-11 22:58:23', '2026-07-11 16:43:05'),
('30760553d6bc8a9c05f832019ea67c4a', 4, '127.0.0.1', 'curl/8.7.1', '2026-07-11 12:20:18', '2026-07-11 12:50:18', '2026-07-11 06:35:18'),
('30cf061ee78f1ef2334dc75cef5596e2', 1, '127.0.0.1', 'curl/8.7.1', '2026-07-11 21:06:53', '2026-07-11 21:36:53', '2026-07-11 15:21:52'),
('365e61dbbd19223461596969852eda39', 1, '127.0.0.1', 'curl/8.7.1', '2026-07-11 21:19:18', '2026-07-11 21:49:18', '2026-07-11 15:34:18'),
('6lhr2qia4psn83v4njgiogmcve', 1, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.5 Safari/605.1.15', '2026-07-13 08:05:35', '2026-07-13 08:35:35', '2026-07-13 00:53:20'),
('70cb43a22f56140ae52b2271ceae9ddb', 1, '127.0.0.1', 'curl/8.7.1', '2026-07-11 21:53:08', '2026-07-11 22:23:08', '2026-07-11 15:59:35'),
('71449ef0c9050db951c787f9cba2e6f8', 1, '127.0.0.1', 'curl/8.7.1', '2026-07-11 22:51:37', '2026-07-11 23:21:37', '2026-07-11 17:06:37'),
('74f605f2b72b162c0859412b935485d0', 3, '127.0.0.1', 'curl/8.7.1', '2026-07-11 12:20:18', '2026-07-11 12:50:18', '2026-07-11 06:35:18'),
('7agjul2qg404c99dl89pappoja', 1, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 11:11:42', '2026-07-12 11:41:42', '2026-07-12 05:25:13'),
('a2hin0ovlulamokeu5nfg0mfkh', 2, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.5 Safari/605.1.15', '2026-07-12 11:08:58', '2026-07-12 11:38:58', '2026-07-12 05:22:45'),
('d17e97cae96f89a84098953a9fd449e3', 1, '127.0.0.1', 'curl/8.7.1', '2026-07-11 12:19:21', '2026-07-11 12:49:21', '2026-07-11 06:34:21'),
('e442fe22c31c49aae5ba6f89e385bea6', 2, '127.0.0.1', 'curl/8.7.1', '2026-07-11 12:20:18', '2026-07-11 12:50:18', '2026-07-11 06:35:18'),
('e7c982ebb35df361c398d56675811de5', 1, '127.0.0.1', 'curl/8.7.1', '2026-07-11 12:18:44', '2026-07-11 12:48:44', '2026-07-11 06:33:44'),
('f9487b3f6d7649e4429f14853c80c0ba', 1, '127.0.0.1', 'curl/8.7.1', '2026-07-11 20:53:30', '2026-07-11 21:23:30', '2026-07-11 15:08:30'),
('fd991bf4e8661878623509529b12a82c', 1, '127.0.0.1', 'curl/8.7.1', '2026-07-11 22:45:05', '2026-07-11 23:15:05', '2026-07-11 17:00:05'),
('hidj0febe9s22di4tm0nobqo3h', 1, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.5 Safari/605.1.15', '2026-07-11 12:51:59', '2026-07-11 13:21:59', '2026-07-11 06:36:18'),
('jghbmsljc1mvfb5643npsvpsbg', 1, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.5 Safari/605.1.15', '2026-07-11 23:52:34', '2026-07-12 00:22:34', '2026-07-11 18:07:13'),
('p274bacmj8r79dvm6tdhdovcu0', 1, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.5 Safari/605.1.15', '2026-07-11 22:51:04', '2026-07-11 23:21:04', '2026-07-11 15:36:04'),
('pma8lvcrhm9pt8qn653qa6sfb3', 1, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.5 Safari/605.1.15', '2026-07-11 18:27:41', '2026-07-11 18:57:41', '2026-07-11 12:42:40');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_expiry_alerts`
-- (See below for the actual view)
--
CREATE TABLE `vw_expiry_alerts` (
`batch_id` int(10) unsigned
,`product_id` int(10) unsigned
,`product_name` varchar(150)
,`batch_number` varchar(40)
,`warehouse_name` varchar(80)
,`quantity` int(10) unsigned
,`expiry_date` date
,`days_left` int(7)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_inventory_detail`
-- (See below for the actual view)
--
CREATE TABLE `vw_inventory_detail` (
`inventory_id` int(10) unsigned
,`product_id` int(10) unsigned
,`product_name` varchar(150)
,`sku` varchar(40)
,`icon_emoji` varchar(10)
,`category_name` varchar(60)
,`warehouse_id` smallint(5) unsigned
,`warehouse_name` varchar(80)
,`quantity_on_hand` int(10) unsigned
,`quantity_reserved` int(10) unsigned
,`quantity_available` int(11)
,`reorder_level` int(10) unsigned
,`stock_status` varchar(12)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_product_stock_summary`
-- (See below for the actual view)
--
CREATE TABLE `vw_product_stock_summary` (
`product_id` int(10) unsigned
,`product_name` varchar(150)
,`sku` varchar(40)
,`category_name` varchar(60)
,`price` decimal(12,2)
,`reorder_level` int(10) unsigned
,`product_status` enum('active','inactive','discontinued')
,`total_on_hand` decimal(32,0)
,`total_reserved` decimal(32,0)
,`total_available` decimal(32,0)
,`stock_status` varchar(12)
);

-- --------------------------------------------------------

--
-- Table structure for table `warehouses`
--

CREATE TABLE `warehouses` (
  `warehouse_id` smallint(5) UNSIGNED NOT NULL,
  `warehouse_name` varchar(80) NOT NULL,
  `warehouse_type` enum('warehouse','cold_storage','store_front','other') NOT NULL DEFAULT 'warehouse',
  `address` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `warehouses`
--

INSERT INTO `warehouses` (`warehouse_id`, `warehouse_name`, `warehouse_type`, `address`, `is_active`, `created_at`) VALUES
(1, 'Warehouse A', 'warehouse', 'Balaju Industrial Area, Kathmandu', 1, '2026-07-11 06:33:26'),
(2, 'Warehouse B', 'warehouse', 'Sinamangal, Kathmandu', 1, '2026-07-11 06:33:26'),
(3, 'Cold Storage', 'cold_storage', 'Balkhu, Kathmandu', 1, '2026-07-11 06:33:26'),
(4, 'Store Front', 'store_front', 'New Road, Kathmandu', 1, '2026-07-11 06:33:26');

-- --------------------------------------------------------

--
-- Structure for view `vw_expiry_alerts`
--
DROP TABLE IF EXISTS `vw_expiry_alerts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_expiry_alerts`  AS SELECT `b`.`batch_id` AS `batch_id`, `p`.`product_id` AS `product_id`, `p`.`product_name` AS `product_name`, `b`.`batch_number` AS `batch_number`, `w`.`warehouse_name` AS `warehouse_name`, `b`.`quantity` AS `quantity`, `b`.`expiry_date` AS `expiry_date`, to_days(`b`.`expiry_date`) - to_days(curdate()) AS `days_left` FROM ((`product_batches` `b` join `products` `p` on(`p`.`product_id` = `b`.`product_id`)) join `warehouses` `w` on(`w`.`warehouse_id` = `b`.`warehouse_id`)) WHERE `b`.`quantity` > 0 ORDER BY to_days(`b`.`expiry_date`) - to_days(curdate()) ASC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_inventory_detail`
--
DROP TABLE IF EXISTS `vw_inventory_detail`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_inventory_detail`  AS SELECT `i`.`inventory_id` AS `inventory_id`, `p`.`product_id` AS `product_id`, `p`.`product_name` AS `product_name`, `p`.`sku` AS `sku`, `p`.`icon_emoji` AS `icon_emoji`, `cat`.`category_name` AS `category_name`, `w`.`warehouse_id` AS `warehouse_id`, `w`.`warehouse_name` AS `warehouse_name`, `i`.`quantity_on_hand` AS `quantity_on_hand`, `i`.`quantity_reserved` AS `quantity_reserved`, `i`.`quantity_available` AS `quantity_available`, `p`.`reorder_level` AS `reorder_level`, CASE WHEN `i`.`quantity_available` <= 0 THEN 'Out of Stock' WHEN `i`.`quantity_available` <= `p`.`reorder_level` THEN 'Low Stock' ELSE 'In Stock' END AS `stock_status` FROM (((`inventory` `i` join `products` `p` on(`p`.`product_id` = `i`.`product_id`)) join `warehouses` `w` on(`w`.`warehouse_id` = `i`.`warehouse_id`)) join `categories` `cat` on(`cat`.`category_id` = `p`.`category_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `vw_product_stock_summary`
--
DROP TABLE IF EXISTS `vw_product_stock_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_product_stock_summary`  AS SELECT `p`.`product_id` AS `product_id`, `p`.`product_name` AS `product_name`, `p`.`sku` AS `sku`, `c`.`category_name` AS `category_name`, `p`.`price` AS `price`, `p`.`reorder_level` AS `reorder_level`, `p`.`status` AS `product_status`, coalesce(sum(`i`.`quantity_on_hand`),0) AS `total_on_hand`, coalesce(sum(`i`.`quantity_reserved`),0) AS `total_reserved`, coalesce(sum(`i`.`quantity_available`),0) AS `total_available`, CASE WHEN coalesce(sum(`i`.`quantity_available`),0) <= 0 THEN 'Out of Stock' WHEN coalesce(sum(`i`.`quantity_available`),0) <= `p`.`reorder_level` THEN 'Low Stock' ELSE 'In Stock' END AS `stock_status` FROM ((`products` `p` left join `inventory` `i` on(`i`.`product_id` = `p`.`product_id`)) left join `categories` `c` on(`c`.`category_id` = `p`.`category_id`)) GROUP BY `p`.`product_id`, `p`.`product_name`, `p`.`sku`, `c`.`category_name`, `p`.`price`, `p`.`reorder_level`, `p`.`status` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_activity_user` (`user_id`),
  ADD KEY `idx_activity_type` (`activity_type`),
  ADD KEY `idx_activity_created` (`created_at`);

--
-- Indexes for table `alerts`
--
ALTER TABLE `alerts`
  ADD PRIMARY KEY (`alert_id`),
  ADD KEY `idx_alerts_type` (`alert_type`),
  ADD KEY `idx_alerts_product` (`product_id`),
  ADD KEY `idx_alerts_ack` (`is_acknowledged`),
  ADD KEY `fk_alerts_warehouse` (`warehouse_id`),
  ADD KEY `fk_alerts_batch` (`batch_id`),
  ADD KEY `fk_alerts_ack_by` (`acknowledged_by`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `uq_categories_name` (`category_name`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD UNIQUE KEY `uq_inventory_product_warehouse` (`product_id`,`warehouse_id`),
  ADD KEY `idx_inventory_warehouse` (`warehouse_id`),
  ADD KEY `idx_inventory_available` (`quantity_available`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_notifications_user_read` (`user_id`,`read_at`),
  ADD KEY `idx_notifications_created` (`created_at`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD UNIQUE KEY `uq_orders_order_number` (`order_number`),
  ADD KEY `idx_orders_customer` (`customer_id`),
  ADD KEY `idx_orders_cashier` (`cashier_id`),
  ADD KEY `idx_orders_warehouse` (`warehouse_id`),
  ADD KEY `idx_orders_date` (`order_date`),
  ADD KEY `idx_orders_status` (`order_status`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `idx_order_items_order` (`order_id`),
  ADD KEY `idx_order_items_product` (`product_id`),
  ADD KEY `idx_order_items_batch` (`batch_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`token_id`),
  ADD UNIQUE KEY `uq_reset_token_hash` (`token_hash`),
  ADD KEY `idx_reset_user` (`user_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`permission_id`),
  ADD UNIQUE KEY `uq_permissions_key` (`permission_key`),
  ADD KEY `idx_permissions_module` (`module_name`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD UNIQUE KEY `uq_products_sku` (`sku`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `idx_products_category` (`category_id`),
  ADD KEY `idx_products_supplier` (`supplier_id`),
  ADD KEY `idx_products_status` (`status`),
  ADD KEY `idx_products_name` (`product_name`),
  ADD KEY `fk_products_created_by` (`created_by`);

--
-- Indexes for table `product_batches`
--
ALTER TABLE `product_batches`
  ADD PRIMARY KEY (`batch_id`),
  ADD UNIQUE KEY `uq_batches_number` (`batch_number`),
  ADD KEY `idx_batches_product` (`product_id`),
  ADD KEY `idx_batches_warehouse` (`warehouse_id`),
  ADD KEY `idx_batches_expiry` (`expiry_date`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`purchase_order_id`),
  ADD UNIQUE KEY `uq_purchase_orders_number` (`po_number`),
  ADD KEY `idx_purchase_supplier_status` (`supplier_id`,`status`),
  ADD KEY `fk_purchase_warehouse` (`warehouse_id`),
  ADD KEY `fk_purchase_creator` (`created_by`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`purchase_order_item_id`),
  ADD UNIQUE KEY `uq_po_item_product` (`purchase_order_id`,`product_id`),
  ADD KEY `fk_purchase_item_product` (`product_id`);

--
-- Indexes for table `returns`
--
ALTER TABLE `returns`
  ADD PRIMARY KEY (`return_id`),
  ADD KEY `idx_returns_order` (`order_id`),
  ADD KEY `fk_returns_user` (`processed_by`);

--
-- Indexes for table `return_items`
--
ALTER TABLE `return_items`
  ADD PRIMARY KEY (`return_item_id`),
  ADD KEY `idx_return_items_return` (`return_id`),
  ADD KEY `fk_return_items_order_item` (`order_item_id`),
  ADD KEY `fk_return_items_product` (`product_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `uq_roles_role_name` (`role_name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `fk_role_permissions_permission` (`permission_id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`movement_id`),
  ADD KEY `idx_movements_product` (`product_id`),
  ADD KEY `idx_movements_warehouse` (`warehouse_id`),
  ADD KEY `idx_movements_type` (`movement_type`),
  ADD KEY `idx_movements_created` (`created_at`),
  ADD KEY `fk_movements_user` (`performed_by`);

--
-- Indexes for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  ADD PRIMARY KEY (`transfer_id`),
  ADD KEY `idx_transfers_product` (`product_id`),
  ADD KEY `idx_transfers_from` (`from_warehouse_id`),
  ADD KEY `idx_transfers_to` (`to_warehouse_id`),
  ADD KEY `fk_transfers_requested_by` (`requested_by`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`),
  ADD KEY `fk_settings_user` (`updated_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_role` (`role_id`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `idx_user_sessions_user` (`user_id`),
  ADD KEY `idx_user_sessions_expires` (`expires_at`);

--
-- Indexes for table `warehouses`
--
ALTER TABLE `warehouses`
  ADD PRIMARY KEY (`warehouse_id`),
  ADD UNIQUE KEY `uq_warehouses_name` (`warehouse_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `alerts`
--
ALTER TABLE `alerts`
  MODIFY `alert_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `token_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `permission_id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_batches`
--
ALTER TABLE `product_batches`
  MODIFY `batch_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `purchase_order_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `purchase_order_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `returns`
--
ALTER TABLE `returns`
  MODIFY `return_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `return_items`
--
ALTER TABLE `return_items`
  MODIFY `return_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `movement_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  MODIFY `transfer_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `warehouses`
--
ALTER TABLE `warehouses`
  MODIFY `warehouse_id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `alerts`
--
ALTER TABLE `alerts`
  ADD CONSTRAINT `fk_alerts_ack_by` FOREIGN KEY (`acknowledged_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_alerts_batch` FOREIGN KEY (`batch_id`) REFERENCES `product_batches` (`batch_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_alerts_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_alerts_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`warehouse_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `fk_inventory_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inventory_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`warehouse_id`) ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`warehouse_id`) ON UPDATE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_batch` FOREIGN KEY (`batch_id`) REFERENCES `product_batches` (`batch_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON UPDATE CASCADE;

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_products_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `product_batches`
--
ALTER TABLE `product_batches`
  ADD CONSTRAINT `fk_batches_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_batches_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`warehouse_id`) ON UPDATE CASCADE;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `fk_purchase_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_purchase_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`),
  ADD CONSTRAINT `fk_purchase_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`warehouse_id`);

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `fk_purchase_item_order` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`purchase_order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_purchase_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `returns`
--
ALTER TABLE `returns`
  ADD CONSTRAINT `fk_returns_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_returns_user` FOREIGN KEY (`processed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `return_items`
--
ALTER TABLE `return_items`
  ADD CONSTRAINT `fk_return_items_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`order_item_id`),
  ADD CONSTRAINT `fk_return_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  ADD CONSTRAINT `fk_return_items_return` FOREIGN KEY (`return_id`) REFERENCES `returns` (`return_id`) ON DELETE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`permission_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_movements_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_movements_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_movements_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`warehouse_id`) ON UPDATE CASCADE;

--
-- Constraints for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  ADD CONSTRAINT `fk_transfers_from_wh` FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses` (`warehouse_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transfers_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transfers_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transfers_to_wh` FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses` (`warehouse_id`) ON UPDATE CASCADE;

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `fk_settings_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON UPDATE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
