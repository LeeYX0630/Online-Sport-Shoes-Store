-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 10, 2026 at 09:49 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `homestay_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('superadmin','admin') NOT NULL DEFAULT 'admin',
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `email`, `phone`, `password`, `full_name`, `created_at`, `role`, `status`) VALUES
(1, 'superadmin', 'admin@homestay.com', '0189814519', '$2y$10$Es01TFUm9zbseL182kvKYOA1DJg8EUss.g5tz/Ma/4aSbMFSplr16', 'Super Administrator', '2026-01-31 05:53:58', 'superadmin', 'active'),
(4, 'Johnny', 'Johnny123@homestay.com', '0161234567', '$2y$10$GEXPd8mcmBUPXYdyzlte0.OANnK.TzXI5COU0s./a0kJYSQuUUUhO', 'Sales Manager', '2026-02-02 08:43:14', 'admin', 'active'),
(5, 'Tung Khai Jun', 'tung.kj@homestay.com', '0167886554', '$2y$10$EZdfPvKfYClwfc1jePFgVuXTeJ8nbC1uRmq/3y4qMsFwUuVopbESS', 'HR Manager', '2026-02-03 16:34:20', 'admin', 'active'),
(6, 'Bobby Boe', 'bobby.boe@homestay.com', '01277889099', '$2y$10$1KuLt.S53r1ACrC5JDcg4eK7FVlnym3OwdgcFxuzh/NvFO3pHIhYu', 'Sales Staff (Resign)', '2026-02-05 14:36:04', 'admin', 'inactive');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `check_in_date` date NOT NULL,
  `check_out_date` date NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `booking_status` enum('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  `payment_status` enum('unpaid','paid') NOT NULL DEFAULT 'unpaid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`booking_id`, `user_id`, `room_id`, `check_in_date`, `check_out_date`, `total_price`, `booking_status`, `payment_status`) VALUES
(4, 2, 1, '2026-02-04', '2026-02-06', 500.00, 'confirmed', 'paid'),
(6, 2, 2, '2026-02-26', '2026-03-01', 310.00, 'confirmed', 'paid'),
(8, 3, 1, '2026-02-20', '2026-02-24', 850.00, 'confirmed', 'paid'),
(11, 5, 8, '2026-02-15', '2026-02-20', 2025.00, 'confirmed', 'paid'),
(12, 7, 9, '2026-02-14', '2026-02-18', 1280.00, 'confirmed', 'paid'),
(13, 8, 8, '2026-02-06', '2026-02-07', 520.00, 'cancelled', 'paid'),
(14, 7, 8, '2026-02-06', '2026-02-07', 468.00, 'cancelled', 'paid'),
(15, 7, 2, '2026-02-15', '2026-02-21', 550.00, 'confirmed', 'paid'),
(16, 9, 8, '2026-02-07', '2026-02-15', 4160.00, 'cancelled', 'paid'),
(17, 9, 9, '2026-02-20', '2026-03-01', 2520.00, 'confirmed', 'paid');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `price_per_night` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_pax` int(11) NOT NULL DEFAULT 2,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `room_id`, `category_name`, `price_per_night`, `max_pax`, `description`) VALUES
(1, 1, 'Deluxe Suite', 300.00, 2, 'Ocean view.'),
(2, 2, 'Standard Room', 100.00, 2, 'Standard room for 2 person'),
(3, 3, 'Family Room', 295.00, 4, 'Family room for 4 persons'),
(4, 2, 'Deluxe Suite', 122.50, 4, 'Nice homestay'),
(6, 8, 'Luxury Seafront Suite', 520.00, 2, 'Exclusive suite for couples featuring high-end amenities and unblocked sunrise views.'),
(7, 8, 'Standard Room', 450.00, 4, 'A standard room for family featuring basic amenities.'),
(8, 9, 'Bedroom Family Suite', 280.00, 5, 'Comfortably accommodates a family of five with a fully equipped mini-kitchen and living room.'),
(9, 10, 'Standard Room', 180.00, 3, 'A cozy wooden cabin that offers a unique glamping experience in the heart of the forest.'),
(10, 11, 'Family Room', 520.00, 6, 'Spacious unit designed for large families, including a master bedroom and two twin rooms'),
(11, 12, 'Couple’s Retreat', 220.00, 2, 'An intimate setting focused on privacy and comfort, perfect for honeymooners'),
(12, 10, 'Backpacker Dorm', 150.00, 1, 'Single bunk bed in a shared ventilation-cooled room for budget travelers.'),
(13, 13, 'Backpacker Dorm', 80.00, 2, ''),
(14, 13, 'Bedroom Family Suite', 200.00, 2, '');

-- --------------------------------------------------------

--
-- Table structure for table `contact_us`
--

CREATE TABLE `contact_us` (
  `contact_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_us`
--

INSERT INTO `contact_us` (`contact_id`, `name`, `email`, `message`, `created_at`) VALUES
(3, 'Lim Yun Zhen', 'lim.yun.zhen@gmail.com', 'Hello!', '2026-02-05 02:27:09');

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `coupon_id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `discount_type` enum('fixed','percent') DEFAULT 'fixed',
  `min_spend` decimal(10,2) DEFAULT 0.00,
  `expiry_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `coupons`
--

INSERT INTO `coupons` (`coupon_id`, `code`, `discount_value`, `discount_type`, `min_spend`, `expiry_date`) VALUES
(1, 'WELCOME10', 10.00, 'percent', 0.00, '2030-12-31'),
(2, 'RM50OFF', 50.00, 'fixed', 200.00, '2030-12-31');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL,
  `room_name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `price_per_night` decimal(10,2) NOT NULL,
  `min_price` decimal(10,2) DEFAULT 0.00,
  `max_price` decimal(10,2) DEFAULT 0.00,
  `facilities` varchar(255) DEFAULT NULL,
  `room_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`room_id`, `room_name`, `description`, `price_per_night`, `min_price`, `max_price`, `facilities`, `room_image`) VALUES
(1, 'Ocean View Deluxe', 'A beautiful suite with a direct view of the ocean. King size bed included.', 250.00, 200.00, 300.00, 'WiFi, AC, TV, Bathtub', 'room1.jpg'),
(2, 'Cozy Standard Stay', 'Perfect for solo travelers or couples on a budget.', 120.00, 100.00, 122.50, 'WiFi, AC, Shower', 'room2.jpg'),
(8, 'Azure Horizon Villa', 'A premium stay offering panoramic views of the ocean. Features a private infinity balcony.', 0.00, 450.00, 580.00, 'WiFi, Private Pool, AC, Sea View Balcony, King Size Bed', '1770299060_room5.jpg'),
(9, 'Maple Family Loft', 'Spacious double-story loft designed for family bonding. Located near the city center.', 0.00, 280.00, 350.00, 'WiFi, Kitchenette, Smart TV, Kids Play Area, Free Parking', '1770299240_1770208492_OIP.webp'),
(10, 'Rainforest Bamboo Cabin', 'An eco-friendly retreat surrounded by tropical greenery. Perfect for nature lovers.', 0.00, 150.00, 220.00, 'Outdoor Shower, Ceiling Fan, BBQ Pit, Hammock', '1770524842_1770362896_room6.jpg'),
(11, 'Sunset Villa', 'A serene stay featuring stunning panoramic views of the evening horizon.', 0.00, 500.00, 600.00, 'WiFi, Infinity Pool, Private Balcony, BBQ Pit', '1770299495_room4.jpg'),
(12, 'Heritage Melaka House', 'A traditional wooden house restored to offer an authentic cultural experience in the heart of town', 0.00, 150.00, 220.00, 'Parking, Ceiling Fans, Kitchenette, Garden', '1770299612_1770125548_xanadu lead homestay.jpg'),
(13, 'Monday Homestay', 'Very good homestay', 0.00, 80.00, 200.00, 'Wifi', '1770362896_room6.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `role` enum('admin','customer') NOT NULL DEFAULT 'customer',
  `profile_image` varchar(255) DEFAULT 'default.png',
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Active','Blocked') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `phone`, `role`, `profile_image`, `reset_token`, `reset_token_expiry`, `created_at`, `status`) VALUES
(2, 'John Doe', 'johndoe@gmail.com', '$2y$10$NTNXGtvKPcTCvCkEyZTd0OZYqGIuwEZp9a5N8OfXkYH6B5lMgx86W', '0198765432', 'customer', '1770025646_1769267153_67.jpg', '9462b351374966b40c46e0245b5b496a2efb3a5d3e7e8cac6e1f4b4e16ccea9c', '2026-02-01 14:58:20', '2026-02-03 06:48:02', 'Active'),
(3, 'LIM YUN ZHEN', 'lim.yun.zhen@gmail.com', '$2y$10$oz.KEg5U54ITFhotb1FgY.2WB8IPRB2fT/4zZCufZX2.xJbkFy75u', '0189814519', 'customer', 'default.png', NULL, NULL, '2026-02-03 06:48:02', 'Active'),
(4, 'Tung kj', 'Tung.kj@gmail.com', '$2y$10$8hCudEUp3B6OtdAuNXD.e.vz6e6Jg4idrI/mFBZNKEOJjLThNRCni', '0117788888', 'customer', '1770199003_1769793134_khaijunblur.jpg', NULL, NULL, '2026-02-04 09:54:55', 'Active'),
(5, 'Ahmad Daniel', 'ahmad.d@gmail.com', '$2y$10$AhgyM86a7UoY/bxPTGFB9euVcmOLmbkPmrGWZYynpyXWEHDjglofG', '60115587888', 'customer', '1770304362_1770270441_epstein pfp.jpg', NULL, NULL, '2026-02-05 13:59:43', 'Active'),
(6, 'Bad User', 'imbaduser@yahoo.com', '$2y$10$4R.6YLJ08X15MHcbMNCueukJ.U/G7kHXX9jGgDwitUVcLiQ2REgT.', '0166767667', 'customer', '1770302451_1770137595_baduser pfp.jpg', NULL, NULL, '2026-02-05 14:12:56', 'Blocked'),
(7, 'Jayson', 'jayson123@gmail.com', '$2y$10$ZX2Mb.qVpJF.0TpXB91rYeab7Tvh/kMPJG0mz.fZHdGHXG8D98hzK', '01610611869', 'customer', '1770359149_1770123992_chicken on tree.webp', NULL, NULL, '2026-02-05 14:15:25', 'Active'),
(8, 'Choi Xhong Bao', 'choi.zhong.bao@gmail.com', '$2y$10$1AXFeVuVimtcwCYaC7YfP..Z.bqHbTL81ozgULvqOOx4ha6ZVxjtG', '0152345678', 'customer', '1770310888_1770266081_caydence pfp.jpg', NULL, NULL, '2026-02-05 17:00:22', 'Active'),
(9, 'jason123', 'jason@gmail.com', '$2y$10$wU2wZsp852Dkj/pX5e1GXedb/u.k8Yiys/qlXF4DUUZxpC3KFNZre', '0126547895', 'customer', '1770361626_1770266448_Tung Prof Pic.png', NULL, NULL, '2026-02-06 07:05:50', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `user_coupons`
--

CREATE TABLE `user_coupons` (
  `uc_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `coupon_id` int(11) NOT NULL,
  `status` enum('active','used') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_coupons`
--

INSERT INTO `user_coupons` (`uc_id`, `user_id`, `coupon_id`, `status`) VALUES
(1, 2, 1, 'used'),
(2, 2, 2, 'used'),
(3, 3, 1, 'active'),
(6, 4, 1, 'used'),
(7, 3, 2, 'active'),
(8, 4, 2, 'active'),
(9, 5, 1, 'used'),
(10, 6, 1, 'active'),
(11, 7, 1, 'used'),
(13, 5, 2, 'active'),
(14, 6, 2, 'active'),
(15, 7, 2, 'used');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `contact_us`
--
ALTER TABLE `contact_us`
  ADD PRIMARY KEY (`contact_id`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`coupon_id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_coupons`
--
ALTER TABLE `user_coupons`
  ADD PRIMARY KEY (`uc_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `coupon_id` (`coupon_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `contact_us`
--
ALTER TABLE `contact_us`
  MODIFY `contact_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `coupon_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `user_coupons`
--
ALTER TABLE `user_coupons`
  MODIFY `uc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_coupons`
--
ALTER TABLE `user_coupons`
  ADD CONSTRAINT `user_coupons_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_coupons_ibfk_2` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`coupon_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
