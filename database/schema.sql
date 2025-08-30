-- HC Store Purchase Order Management System Database Schema
-- Created: August 30, 2025
-- Version: 1.0.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Database: hc_store_stock
CREATE DATABASE IF NOT EXISTS `hc_store_stock` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `hc_store_stock`;

-- --------------------------------------------------------
-- Table structure for table `categories`
-- --------------------------------------------------------

CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `products`
-- --------------------------------------------------------

CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `min_stock_level` int(11) DEFAULT 10,
  `price` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `suppliers`
-- --------------------------------------------------------

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Sri Lanka',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `purchase_orders`
-- --------------------------------------------------------

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_number` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `status` enum('draft','sent','confirmed','partially_received','received','cancelled') DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_number` (`po_number`),
  KEY `supplier_id` (`supplier_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `purchase_order_items`
-- --------------------------------------------------------

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity_ordered` int(11) NOT NULL,
  `quantity_received` int(11) DEFAULT 0,
  `unit_cost` decimal(10,2) NOT NULL,
  `total_cost` decimal(12,2) GENERATED ALWAYS AS (`quantity_ordered` * `unit_cost`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `po_id` (`po_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `stock_movements`
-- --------------------------------------------------------

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `movement_type` enum('in','out','adjustment') NOT NULL,
  `quantity_change` int(11) NOT NULL,
  `previous_quantity` int(11) NOT NULL,
  `new_quantity` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `reference_type` enum('purchase_order','manual','adjustment','sale') DEFAULT 'manual',
  `reference_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Insert sample data
-- --------------------------------------------------------

-- Insert default admin user (password: admin123)
INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `role`, `is_active`) VALUES
(1, 'admin', '$2y$10$YourHashedPasswordHere', 'System Administrator', 'admin@hcstore.com', 'admin', 1);

-- Insert sample categories
INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, 'Electronics', 'Electronic devices and components'),
(2, 'Stationery', 'Office and school supplies'),
(3, 'Furniture', 'Office and home furniture'),
(4, 'Medical Supplies', 'Healthcare and medical equipment');

-- Insert sample suppliers
INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `email`, `phone`, `address`, `city`, `country`) VALUES
(1, 'ABC Electronics Ltd', 'John Silva', 'john@abcelectronics.lk', '+94112345678', '123 Galle Road', 'Colombo', 'Sri Lanka'),
(2, 'Lanka Stationery Mart', 'Priya Fernando', 'info@lankastationery.com', '+94771234567', '456 Kandy Road', 'Kandy', 'Sri Lanka'),
(3, 'Modern Furniture Co.', 'Raj Patel', 'raj@modernfurniture.lk', '+94812345678', '789 Negombo Road', 'Negombo', 'Sri Lanka');

-- Insert sample products
INSERT INTO `products` (`id`, `name`, `sku`, `description`, `category_id`, `quantity`, `min_stock_level`, `price`) VALUES
(1, 'Laptop Computer', 'LAP001', 'High-performance business laptop', 1, 15, 5, 125000.00),
(2, 'Office Chair', 'CHR001', 'Ergonomic office chair with lumbar support', 3, 8, 3, 25000.00),
(3, 'A4 Paper Ream', 'PPR001', 'Premium quality A4 printing paper', 2, 50, 10, 750.00),
(4, 'Blood Pressure Monitor', 'MED001', 'Digital blood pressure monitoring device', 4, 5, 2, 15000.00);

-- Create triggers for automatic stock movement logging
DELIMITER //

CREATE TRIGGER `update_stock_on_quantity_change` 
AFTER UPDATE ON `products` 
FOR EACH ROW
BEGIN
    IF OLD.quantity != NEW.quantity THEN
        INSERT INTO `stock_movements` (
            `product_id`, 
            `movement_type`, 
            `quantity_change`, 
            `previous_quantity`, 
            `new_quantity`, 
            `reason`, 
            `reference_type`, 
            `user_id`
        ) VALUES (
            NEW.id,
            CASE 
                WHEN NEW.quantity > OLD.quantity THEN 'in'
                WHEN NEW.quantity < OLD.quantity THEN 'out'
                ELSE 'adjustment'
            END,
            NEW.quantity - OLD.quantity,
            OLD.quantity,
            NEW.quantity,
            'Automatic stock update',
            'adjustment',
            1
        );
    END IF;
END//

DELIMITER ;

COMMIT;

-- --------------------------------------------------------
-- Additional indexes for performance
-- --------------------------------------------------------

CREATE INDEX `idx_stock_movements_created_at` ON `stock_movements` (`created_at`);
CREATE INDEX `idx_stock_movements_movement_type` ON `stock_movements` (`movement_type`);
CREATE INDEX `idx_products_quantity` ON `products` (`quantity`);
CREATE INDEX `idx_products_min_stock` ON `products` (`min_stock_level`);
CREATE INDEX `idx_purchase_orders_status` ON `purchase_orders` (`status`);
CREATE INDEX `idx_purchase_orders_order_date` ON `purchase_orders` (`order_date`);

-- --------------------------------------------------------
-- Views for common queries
-- --------------------------------------------------------

CREATE VIEW `low_stock_products` AS
SELECT 
    p.id,
    p.name,
    p.sku,
    p.quantity,
    p.min_stock_level,
    c.name as category_name,
    (p.min_stock_level - p.quantity) as shortage
FROM products p
LEFT JOIN categories c ON p.category_id = c.id
WHERE p.quantity <= p.min_stock_level;

CREATE VIEW `inventory_value` AS
SELECT 
    p.id,
    p.name,
    p.sku,
    p.quantity,
    p.price,
    (p.quantity * p.price) as total_value,
    c.name as category_name
FROM products p
LEFT JOIN categories c ON p.category_id = c.id;

-- --------------------------------------------------------
-- End of schema
-- --------------------------------------------------------
