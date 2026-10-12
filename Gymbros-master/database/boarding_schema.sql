-- BoardingHub Database Schema & Seed Data
USE `gym_Bros`;

CREATE TABLE IF NOT EXISTS `boarding_houses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL DEFAULT 'Bayugan City',
  `description` text NOT NULL,
  `room_types` varchar(255) NOT NULL DEFAULT 'Single Room, Bedspace, Studio Pad',
  `gender_policy` enum('All', 'Male Only', 'Female Only', 'Co-ed') NOT NULL DEFAULT 'All',
  `price_range` varchar(100) NOT NULL DEFAULT '₱2,500 - ₱6,500/mo',
  `min_price` decimal(10,2) NOT NULL DEFAULT 2500.00,
  `max_price` decimal(10,2) NOT NULL DEFAULT 6500.00,
  `available_rooms` int(11) NOT NULL DEFAULT 4,
  `total_rooms` int(11) NOT NULL DEFAULT 12,
  `rating` decimal(2,1) NOT NULL DEFAULT 4.9,
  `reviews_count` int(11) NOT NULL DEFAULT 38,
  `landlord_name` varchar(100) NOT NULL DEFAULT 'Maria Santos',
  `landlord_phone` varchar(50) NOT NULL DEFAULT '0917-555-0192',
  `landlord_email` varchar(100) NOT NULL DEFAULT 'landlord@boardinghub.com',
  `proximity` varchar(150) NOT NULL DEFAULT '3 mins walk to Main University Gate',
  `image_path` varchar(255) NOT NULL DEFAULT '../images/boarding_house_exterior.jpg',
  `amenities` text NOT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `room_listings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `boarding_house_id` int(11) NOT NULL,
  `room_number` varchar(50) NOT NULL,
  `room_name` varchar(100) NOT NULL,
  `room_type` enum('Single Room','Bedspace','Studio Pad','Deluxe Room') NOT NULL DEFAULT 'Single Room',
  `monthly_rate` decimal(10,2) NOT NULL DEFAULT 3500.00,
  `gender_policy` enum('All','Male Only','Female Only','Co-ed') NOT NULL DEFAULT 'All',
  `capacity` int(11) NOT NULL DEFAULT 1,
  `available_slots` int(11) NOT NULL DEFAULT 1,
  `amenities` text NOT NULL,
  `description` text NOT NULL,
  `image_path` varchar(255) NOT NULL DEFAULT '../images/boarding_room_single.jpg',
  `status` enum('available','fully_booked','maintenance') NOT NULL DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bh_id` (`boarding_house_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `room_reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) NOT NULL,
  `boarding_house_id` int(11) NOT NULL,
  `room_listing_id` int(11) DEFAULT NULL,
  `target_move_in_date` date NOT NULL,
  `stay_duration_months` int(11) NOT NULL DEFAULT 6,
  `contact_phone` varchar(50) DEFAULT NULL,
  `special_requests` text DEFAULT NULL,
  `status` enum('pending','approved','active','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_res_user` (`user_id`),
  KEY `idx_res_house` (`boarding_house_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `maintenance_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) NOT NULL,
  `boarding_house_name` varchar(150) NOT NULL,
  `room_number` varchar(50) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'Plumbing/Water',
  `urgency` enum('Low','Medium','High','Emergency') NOT NULL DEFAULT 'Medium',
  `description` text NOT NULL,
  `status` enum('pending','in_progress','resolved','cancelled') NOT NULL DEFAULT 'pending',
  `admin_remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_maint_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `saved_boarding_houses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) NOT NULL,
  `boarding_house_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_bh` (`user_id`, `boarding_house_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert Seed Boarding Houses if empty
INSERT INTO `boarding_houses` (`id`, `name`, `address`, `city`, `description`, `room_types`, `gender_policy`, `price_range`, `min_price`, `max_price`, `available_rooms`, `total_rooms`, `rating`, `reviews_count`, `landlord_name`, `landlord_phone`, `landlord_email`, `proximity`, `image_path`, `amenities`, `featured`)
SELECT 1, 'Oakwood Student Residences & Boarding House', '24 Acacia Street, Poblacion', 'Bayugan City', 'Safe, modern 2-story student residence with landscaped garden, secure biometric gate, high-speed fiber internet, and quiet study environment.', 'Single Room, Bedspace, Studio Pad', 'All', '₱2,800 - ₱6,500/mo', 2800.00, 6500.00, 5, 14, 4.9, 42, 'Atty. Roberto Dela Cruz', '0917-882-9011', 'oakwood@boardinghub.com', '3 mins walk to Main University Gate', '../images/boarding_house_exterior.jpg', '["High-Speed WiFi 150Mbps", "24/7 CCTV & Security", "Study Lounge", "Free Drinking Water", "Kitchen Access", "Laundry Area", "Backup Generator"]', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `boarding_houses` WHERE `id` = 1);

INSERT INTO `boarding_houses` (`id`, `name`, `address`, `city`, `description`, `room_types`, `gender_policy`, `price_range`, `min_price`, `max_price`, `available_rooms`, `total_rooms`, `rating`, `reviews_count`, `landlord_name`, `landlord_phone`, `landlord_email`, `proximity`, `image_path`, `amenities`, `featured`)
SELECT 2, 'The Elm’s Haven Luxury Student Living', '118 University Boulevard', 'Bayugan City', 'Charming 3-story boutique boarding residence featuring balcony suites, ambient garden lighting, en-suite bathrooms, and designer study areas.', 'Studio Pad, Deluxe Room', 'Co-ed', '₱5,200 - ₱8,500/mo', 5200.00, 8500.00, 3, 10, 5.0, 31, 'Mrs. Elena Rostova-Tan', '0928-334-1190', 'elms@boardinghub.com', '5 mins walk to Medical Center & Campus', '../images/boarding_house_villa.jpg', '["Fiber Optic WiFi", "Air Conditioned Units", "Private Bathroom (CR)", "Kitchenette with Microwave", "Rooftop Study Deck", "Daily Common Area Cleaning"]', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `boarding_houses` WHERE `id` = 2);

INSERT INTO `boarding_houses` (`id`, `name`, `address`, `city`, `description`, `room_types`, `gender_policy`, `price_range`, `min_price`, `max_price`, `available_rooms`, `total_rooms`, `rating`, `reviews_count`, `landlord_name`, `landlord_phone`, `landlord_email`, `proximity`, `image_path`, `amenities`, `featured`)
SELECT 3, 'Pinecrest Ladies Dormitory & Bedspace', '45 Laurel Street, Barangay Central', 'Bayugan City', 'Exclusive, highly secure boarding house for female students and boarders. Features resident matron, fingerprint entry, individual study lamps and lockers.', 'Bedspace, Single Room', 'Female Only', '₱2,200 - ₱4,000/mo', 2200.00, 4000.00, 6, 20, 4.8, 56, 'Ma’am Corazon Bautista', '0908-112-9844', 'pinecrest@boardinghub.com', '2 mins walk to City College & Market', '../images/boarding_room_bedspace.jpg', '["Female Only Matron", "Lockers & Built-in Storage", "Individual Study Desks", "High Speed WiFi", "Curfew & Night Guard", "Full Kitchen & Fridge Access"]', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `boarding_houses` WHERE `id` = 3);

INSERT INTO `boarding_houses` (`id`, `name`, `address`, `city`, `description`, `room_types`, `gender_policy`, `price_range`, `min_price`, `max_price`, `available_rooms`, `total_rooms`, `rating`, `reviews_count`, `landlord_name`, `landlord_phone`, `landlord_email`, `proximity`, `image_path`, `amenities`, `featured`)
SELECT 4, 'Skyline Executive Studio Pads', '88 Rizal Avenue Downtown', 'Bayugan City', 'Premium studio units designed for working professionals, board exam reviewees, and college scholars wanting utmost privacy and peace.', 'Studio Pad', 'All', '₱6,800 - ₱8,000/mo', 6800.00, 8000.00, 2, 8, 4.9, 28, 'Engr. Marcus Vance', '0919-445-6671', 'skyline@boardinghub.com', 'Direct access to Transit Terminals & Malls', '../images/boarding_room_studio.jpg', '["Inverter Air Conditioning", "Private T&B with Hot Shower", "Granite Kitchenette & Sink", "100Mbps Dedicated Fiber", "Balcony View", "Keycard Door Access"]', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `boarding_houses` WHERE `id` = 4);

INSERT INTO `boarding_houses` (`id`, `name`, `address`, `city`, `description`, `room_types`, `gender_policy`, `price_range`, `min_price`, `max_price`, `available_rooms`, `total_rooms`, `rating`, `reviews_count`, `landlord_name`, `landlord_phone`, `landlord_email`, `proximity`, `image_path`, `amenities`, `featured`)
SELECT 5, 'Sunridge Cozy Private Rooms', '12 Mabini Street, Greenview', 'Bayugan City', 'Peaceful, sunlit single rooms with personal study workstations, big sunny windows, and garden views. Perfect for focused learning.', 'Single Room', 'Co-ed', '₱3,400 - ₱4,200/mo', 3400.00, 4200.00, 4, 12, 4.7, 34, 'Mrs. Teresa Morales', '0933-776-5520', 'sunridge@boardinghub.com', '7 mins walk to City Library & Campus', '../images/boarding_room_single.jpg', '["Single Bed & Foam Mattress", "Personal Desk & Ergonomic Chair", "Spacious Wardrobe", "Free Drinking Water", "Fast WiFi", "Motorcycle Parking Space"]', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `boarding_houses` WHERE `id` = 5);

INSERT INTO `boarding_houses` (`id`, `name`, `address`, `city`, `description`, `room_types`, `gender_policy`, `price_range`, `min_price`, `max_price`, `available_rooms`, `total_rooms`, `rating`, `reviews_count`, `landlord_name`, `landlord_phone`, `landlord_email`, `proximity`, `image_path`, `amenities`, `featured`)
SELECT 6, 'Campus Gateway Student Commons', '7 College Way, North Campus', 'Bayugan City', 'The premier student hub with a 24/7 collaborative study lounge, coffee bar, and diverse room options for active collegiate life.', 'Bedspace, Single Room, Studio Pad', 'All', '₱2,500 - ₱5,800/mo', 2500.00, 5800.00, 7, 24, 4.9, 63, 'Coach David Briggs', '0945-889-1234', 'gateway@boardinghub.com', 'Across the street from Engineering Building', '../images/boarding_lounge.jpg', '["24/7 Co-working & Study Hall", "Pantry with Microwave & Coffee", "High Speed WiFi Mesh", "Laundry Washing Machines", "Bicycle Racks", "Air Conditioned Lounge"]', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `boarding_houses` WHERE `id` = 6);

-- Seed Room Listings
INSERT INTO `room_listings` (`id`, `boarding_house_id`, `room_number`, `room_name`, `room_type`, `monthly_rate`, `gender_policy`, `capacity`, `available_slots`, `amenities`, `description`, `image_path`, `status`)
SELECT 1, 1, 'Room 101', 'Deluxe Private Single', 'Single Room', 3800.00, 'All', 1, 1, '["Single Bed", "Study Desk", "WiFi", "Wardrobe", "Electric Fan"]', 'Bright single room on 1st floor with study desk and garden view.', '../images/boarding_room_single.jpg', 'available'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `room_listings` WHERE `id` = 1);

INSERT INTO `room_listings` (`id`, `boarding_house_id`, `room_number`, `room_name`, `room_type`, `monthly_rate`, `gender_policy`, `capacity`, `available_slots`, `amenities`, `description`, `image_path`, `status`)
SELECT 2, 1, 'Room 204', 'Aircon Studio Suite', 'Studio Pad', 6000.00, 'All', 2, 2, '["Air Conditioning", "Private CR", "Double Bed", "Desk", "Kitchenette"]', 'Second floor studio with air conditioner and private bathroom.', '../images/boarding_room_studio.jpg', 'available'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `room_listings` WHERE `id` = 2);

INSERT INTO `room_listings` (`id`, `boarding_house_id`, `room_number`, `room_name`, `room_type`, `monthly_rate`, `gender_policy`, `capacity`, `available_slots`, `amenities`, `description`, `image_path`, `status`)
SELECT 3, 2, 'Suite 302', 'Elm Penthouse Studio with Balcony', 'Studio Pad', 7800.00, 'Co-ed', 2, 1, '["Private Balcony", "Aircon", "Hot Shower CR", "Pantry", "Fiber WiFi"]', 'Stunning penthouse studio with panoramic trees and skyline view.', '../images/boarding_room_studio.jpg', 'available'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `room_listings` WHERE `id` = 3);

INSERT INTO `room_listings` (`id`, `boarding_house_id`, `room_number`, `room_name`, `room_type`, `monthly_rate`, `gender_policy`, `capacity`, `available_slots`, `amenities`, `description`, `image_path`, `status`)
SELECT 4, 3, 'Bed A-1', 'Pinecrest Lower Bunk Space', 'Bedspace', 2400.00, 'Female Only', 4, 3, '["Lower Bunk Bed", "Personal Locker", "Study Lamp", "Curtain Privacy", "WiFi"]', 'Cozy female bedspace with privacy curtains and sturdy wood bunk.', '../images/boarding_room_bedspace.jpg', 'available'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `room_listings` WHERE `id` = 4);

INSERT INTO `room_listings` (`id`, `boarding_house_id`, `room_number`, `room_name`, `room_type`, `monthly_rate`, `gender_policy`, `capacity`, `available_slots`, `amenities`, `description`, `image_path`, `status`)
SELECT 5, 5, 'Room 208', 'Sunridge Quiet Study Room', 'Single Room', 3600.00, 'All', 1, 1, '["Oak Study Desk", "Single Bed", "Natural Light", "Bookcase", "High-Speed WiFi"]', 'Designed for board exam reviewees and studious university students.', '../images/boarding_room_single.jpg', 'available'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `room_listings` WHERE `id` = 5);

INSERT INTO `room_listings` (`id`, `boarding_house_id`, `room_number`, `room_name`, `room_type`, `monthly_rate`, `gender_policy`, `capacity`, `available_slots`, `amenities`, `description`, `image_path`, `status`)
SELECT 6, 6, 'Dorm 105', 'Commons 4-Person Shared Room', 'Bedspace', 2600.00, 'All', 4, 2, '["Bunk Bed", "Lockers", "Lounge Access", "Fast WiFi", "Drinking Water"]', 'Affordable student bedspace with unlimited 24/7 study lounge access.', '../images/boarding_room_bedspace.jpg', 'available'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `room_listings` WHERE `id` = 6);
