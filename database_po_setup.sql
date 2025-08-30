-- Purchase Order Management Database Schema
-- Run this SQL to add PO functionality to your existing HC Store database

USE hc_store_stock;

-- Create suppliers table
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100) DEFAULT 'Sri Lanka',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create purchase_orders table
CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(50) UNIQUE NOT NULL,
    supplier_id INT NOT NULL,
    status ENUM('draft', 'sent', 'confirmed', 'partially_received', 'received', 'cancelled') DEFAULT 'draft',
    order_date DATE NOT NULL,
    expected_delivery DATE,
    notes TEXT,
    total_amount DECIMAL(10,2) DEFAULT 0.00,
    created_by INT NOT NULL,
    received_by INT NULL,
    received_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create purchase_order_items table
CREATE TABLE IF NOT EXISTS purchase_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(10,2) NOT NULL,
    total_cost DECIMAL(10,2) NOT NULL,
    received_quantity INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);

-- Insert some sample suppliers
INSERT INTO suppliers (name, contact_person, email, phone, address, city, postal_code) VALUES
('ABC Trading Company', 'John Silva', 'john@abctrading.lk', '0771234567', '123 Main Street', 'Colombo', '00100'),
('Lanka Suppliers Ltd', 'Priya Fernando', 'priya@lankasuppliers.com', '0777654321', '456 Galle Road', 'Mount Lavinia', '10370'),
('Quick Supply Co.', 'Ravi Perera', 'info@quicksupply.lk', '0712345678', '789 Kandy Road', 'Kandy', '20000');

-- Create indexes for better performance
CREATE INDEX idx_po_number ON purchase_orders(po_number);
CREATE INDEX idx_po_supplier ON purchase_orders(supplier_id);
CREATE INDEX idx_po_status ON purchase_orders(status);
CREATE INDEX idx_poi_po ON purchase_order_items(po_id);
CREATE INDEX idx_poi_product ON purchase_order_items(product_id);

-- Update stock_movements table to include PO reference (if not already exists)
ALTER TABLE stock_movements 
ADD COLUMN po_id INT NULL AFTER id,
ADD FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE SET NULL;

-- Create a view for purchase order summary
CREATE VIEW purchase_order_summary AS
SELECT 
    po.id,
    po.po_number,
    s.name as supplier_name,
    po.status,
    po.order_date,
    po.expected_delivery,
    po.total_amount,
    u1.username as created_by_name,
    u2.username as received_by_name,
    po.received_date,
    COUNT(poi.id) as total_items,
    SUM(poi.quantity) as total_quantity,
    SUM(poi.received_quantity) as total_received_quantity
FROM purchase_orders po
LEFT JOIN suppliers s ON po.supplier_id = s.id
LEFT JOIN users u1 ON po.created_by = u1.id
LEFT JOIN users u2 ON po.received_by = u2.id
LEFT JOIN purchase_order_items poi ON po.id = poi.po_id
GROUP BY po.id;
