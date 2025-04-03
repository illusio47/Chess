-- Medicine Orders Table
CREATE TABLE IF NOT EXISTS `medicine_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `delivery_address` text NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `payment_method` enum('cash','card','insurance') NOT NULL DEFAULT 'cash',
  `status` enum('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `prescription_id` (`prescription_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cab Bookings Table
CREATE TABLE IF NOT EXISTS `cab_bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `pickup_address` text NOT NULL,
  `destination` text NOT NULL,
  `pickup_datetime` datetime NOT NULL,
  `cab_type` enum('standard','wheelchair','stretcher') NOT NULL DEFAULT 'standard',
  `special_requirements` text DEFAULT NULL,
  `status` enum('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hospitals Table
CREATE TABLE IF NOT EXISTS `hospitals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `website` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample hospitals
INSERT INTO `hospitals` (`name`, `address`, `phone`, `email`, `website`, `status`, `created_at`) VALUES
('City General Hospital', '123 Main Street, City Center', '555-1234', 'info@citygeneral.com', 'www.citygeneral.com', 'active', NOW()),
('Memorial Medical Center', '456 Park Avenue, Downtown', '555-5678', 'contact@memorialmed.com', 'www.memorialmed.com', 'active', NOW()),
('St. John\'s Hospital', '789 Oak Road, Westside', '555-9012', 'info@stjohns.com', 'www.stjohns.com', 'active', NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`); 