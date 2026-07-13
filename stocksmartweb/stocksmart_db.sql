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
-- Database: `stocksmart_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `alerts`
--

CREATE TABLE `alerts` (
  `alert_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `alert_type` enum('LOW_STOCK','EXPIRY','RESTOCK') NOT NULL,
  `message` varchar(255) NOT NULL,
  `status` enum('New','Pending','Resolved') DEFAULT 'New',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `alerts`
--

INSERT INTO `alerts` (`alert_id`, `product_id`, `alert_type`, `message`, `status`, `created_at`) VALUES
(1, 1, 'LOW_STOCK', 'Butter 500g is critically low.', 'New', '2026-06-13 05:54:52'),
(2, 2, 'LOW_STOCK', 'Cooking Oil 1L is low in stock.', 'New', '2026-06-13 05:54:52'),
(3, 3, 'LOW_STOCK', 'Cheese Slice is critically low.', 'New', '2026-06-13 05:54:52');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`) VALUES
(4, 'Bakery'),
(3, 'Beverages'),
(5, 'Cooking'),
(1, 'Dairy'),
(2, 'Grocery'),
(6, 'Others');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_locations`
--

CREATE TABLE `inventory_locations` (
  `location_id` int(11) NOT NULL,
  `location_name` varchar(100) NOT NULL,
  `location_type` varchar(80) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_locations`
--

INSERT INTO `inventory_locations` (`location_id`, `location_name`, `location_type`) VALUES
(1, 'Main Warehouse', 'Warehouse'),
(2, 'Cold Storage', 'Storage'),
(3, 'Outlet Store 1', 'Retail Outlet'),
(4, 'Outlet Store 2', 'Retail Outlet');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_movements`
--

CREATE TABLE `inventory_movements` (
  `movement_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `movement_type` enum('Stock In','Stock Out','Adjustment') NOT NULL,
  `quantity` int(11) NOT NULL,
  `movement_note` varchar(255) DEFAULT NULL,
  `movement_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_movements`
--

INSERT INTO `inventory_movements` (`movement_id`, `product_id`, `location_id`, `movement_type`, `quantity`, `movement_note`, `movement_date`) VALUES
(1, 1, 1, 'Stock Out', 6, 'Sold from main warehouse', '2026-06-12 06:56:02'),
(2, 2, 1, 'Stock In', 20, 'New supplier delivery', '2026-06-12 06:56:02'),
(3, 3, 1, 'Stock Out', 4, 'Sold items', '2026-06-12 06:56:02'),
(4, 4, 2, 'Stock In', 25, 'Cold storage restock', '2026-06-12 06:56:02'),
(5, 6, 3, 'Stock In', 100, 'Outlet stock update', '2026-06-12 06:56:02');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_stock`
--

CREATE TABLE `inventory_stock` (
  `stock_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `in_stock` int(11) NOT NULL DEFAULT 0,
  `reserved` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_stock`
--

INSERT INTO `inventory_stock` (`stock_id`, `product_id`, `location_id`, `in_stock`, `reserved`, `last_updated`) VALUES
(1, 1, 1, 4, 1, '2026-06-12 06:56:02'),
(2, 2, 1, 9, 0, '2026-06-12 06:56:02'),
(3, 3, 1, 6, 2, '2026-06-12 06:56:02'),
(4, 4, 2, 14, 3, '2026-06-12 06:56:02'),
(5, 5, 1, 7, 0, '2026-06-12 06:56:02'),
(6, 6, 3, 120, 10, '2026-06-12 06:56:02'),
(7, 7, 1, 30, 4, '2026-06-12 06:56:02'),
(8, 8, 2, 11, 1, '2026-06-12 06:56:02'),
(9, 9, 1, 65, 8, '2026-06-12 06:56:02'),
(10, 10, 4, 45, 5, '2026-06-12 06:56:02');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `product_image` varchar(20) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `unit_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reorder_level` int(11) NOT NULL DEFAULT 10,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `stock_quantity` int(11) DEFAULT 0,
  `batch_no` varchar(50) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `product_name`, `sku`, `product_image`, `category_id`, `supplier_id`, `unit_cost`, `selling_price`, `reorder_level`, `created_at`, `stock_quantity`, `batch_no`, `expiry_date`) VALUES
(1, 'Butter 500g', 'DAIRY-001', 'BTR', 1, 1, 350.00, 450.00, 10, '2026-06-12 06:56:02', 4, 'BC-001', '2026-06-14'),
(2, 'Cooking Oil 1L', 'COOK-002', 'OIL', 5, 2, 220.00, 300.00, 10, '2026-06-12 06:56:02', 9, 'BC-002', '2026-06-15'),
(3, 'Cheese Slice 10 pcs', 'DAIRY-003', 'CHS', 1, 1, 180.00, 250.00, 10, '2026-06-12 06:56:02', 6, 'BC-003', '2026-06-16'),
(4, 'Yoghurt 400g', 'DAIRY-004', 'YOG', 1, 1, 80.00, 120.00, 10, '2026-06-12 06:56:02', 14, 'BC-004', '2026-06-17'),
(5, 'Tomato Paste 200g', 'GROC-005', 'TOM', 2, 2, 90.00, 140.00, 10, '2026-06-12 06:56:02', 7, 'BC-005', '2026-06-18'),
(6, 'Mineral Water 1L', 'BEV-006', 'WTR', 3, 2, 20.00, 35.00, 20, '2026-06-12 06:56:02', 120, 'BC-006', '2026-06-19'),
(7, 'Sliced Bread 400g', 'BAKE-007', 'BRD', 4, 3, 70.00, 120.00, 15, '2026-06-12 06:56:02', 30, 'BC-007', '2026-06-20'),
(8, 'Paneer 250g', 'DAIRY-008', 'PNR', 1, 1, 150.00, 220.00, 10, '2026-06-12 06:56:02', 11, 'BC-008', '2026-06-21'),
(9, 'Rice 5kg', 'GROC-009', 'RCE', 2, 2, 650.00, 850.00, 15, '2026-06-12 06:56:02', 65, 'BC-009', '2026-06-22'),
(10, 'Soft Drink 1L', 'BEV-010', 'DRK', 3, 2, 85.00, 130.00, 20, '2026-06-12 06:56:02', 45, 'BC-010', '2026-06-23');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `sale_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(120) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sale_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`sale_id`, `user_id`, `customer_name`, `total_amount`, `sale_date`) VALUES
(1, 1, 'Walk-in Customer', 1200.00, '2026-06-13 05:54:52'),
(2, 1, 'Walk-in Customer', 3600.00, '2026-06-13 05:54:52'),
(3, 1, 'Walk-in Customer', 2400.00, '2026-06-13 05:54:52');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `sale_item_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`sale_item_id`, `sale_id`, `product_id`, `quantity`, `price`, `subtotal`) VALUES
(1, 1, 1, 3, 400.00, 1200.00),
(2, 2, 2, 12, 300.00, 3600.00),
(3, 3, 3, 8, 300.00, 2400.00);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(120) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `phone`, `email`, `address`) VALUES
(1, 'Fresh Farm Suppliers', '9800000001', 'freshfarm@email.com', 'Kathmandu'),
(2, 'Daily Grocery Traders', '9800000002', 'grocery@email.com', 'Lalitpur'),
(3, 'Bakery House Supply', '9800000003', 'bakery@email.com', 'Bhaktapur');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Super Admin','Admin','Staff') DEFAULT 'Staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password_hash`, `role`, `created_at`) VALUES
(1, 'Admin User', 'admin@stocksmart.com', 'admin123', 'Super Admin', '2026-06-12 06:56:02');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alerts`
--
ALTER TABLE `alerts`
  ADD PRIMARY KEY (`alert_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `inventory_locations`
--
ALTER TABLE `inventory_locations`
  ADD PRIMARY KEY (`location_id`);

--
-- Indexes for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD PRIMARY KEY (`movement_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `location_id` (`location_id`);

--
-- Indexes for table `inventory_stock`
--
ALTER TABLE `inventory_stock`
  ADD PRIMARY KEY (`stock_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `location_id` (`location_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`sale_id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`sale_item_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alerts`
--
ALTER TABLE `alerts`
  MODIFY `alert_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `inventory_locations`
--
ALTER TABLE `inventory_locations`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  MODIFY `movement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventory_stock`
--
ALTER TABLE `inventory_stock`
  MODIFY `stock_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `sale_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `sale_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD CONSTRAINT `inventory_movements_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_movements_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `inventory_locations` (`location_id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_stock`
--
ALTER TABLE `inventory_stock`
  ADD CONSTRAINT `inventory_stock_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_stock_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `inventory_locations` (`location_id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
