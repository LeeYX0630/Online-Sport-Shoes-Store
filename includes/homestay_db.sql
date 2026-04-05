-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 05, 2026 at 04:38 AM
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
  `role` enum('superadmin','admin') NOT NULL DEFAULT 'admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `email`, `phone`, `password`, `full_name`, `created_at`, `role`) VALUES
(1, 'superadmin', 'admin@homestay.com', '0189814519', '$2y$10$Es01TFUm9zbseL182kvKYOA1DJg8EUss.g5tz/Ma/4aSbMFSplr16', 'Super Administrator', '2026-01-31 05:53:58', 'superadmin'),
(4, 'Johnny', 'Johnny123@homestay.com', '0161234567', '$2y$10$GEXPd8mcmBUPXYdyzlte0.OANnK.TzXI5COU0s./a0kJYSQuUUUhO', 'Sales Manager', '2026-02-02 08:43:14', 'admin'),
(5, 'Tung Khai Jun', 'tung.kj@homestay.com', '0167886554', '$2y$10$EZdfPvKfYClwfc1jePFgVuXTeJ8nbC1uRmq/3y4qMsFwUuVopbESS', 'HR Manager', '2026-02-03 16:34:20', 'admin');

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
(5, 3, 3, '2026-02-03', '2026-02-15', 3600.00, 'cancelled', 'paid'),
(6, 2, 2, '2026-02-26', '2026-03-01', 310.00, 'confirmed', 'paid'),
(7, 2, 3, '2026-02-17', '2026-02-20', 810.00, 'cancelled', 'paid'),
(8, 3, 1, '2026-02-20', '2026-02-24', 850.00, 'confirmed', 'paid'),
(9, 4, 3, '2026-03-07', '2026-03-09', 600.00, 'confirmed', 'paid'),
(10, 4, 3, '2026-02-14', '2026-02-16', 540.00, 'confirmed', 'paid');

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
(4, 2, 'Deluxe Suite', 122.50, 4, 'Nice homestay');

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
(3, 'Happy Family Suite', 'Spacious room for 4 people with 2 Queen beds.', 300.00, 250.00, 320.00, 'WiFi, AC, Kitchenette', 'room3.jpg');

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
(2, 'John Doe', 'customer@gmail.com', '$2y$10$NTNXGtvKPcTCvCkEyZTd0OZYqGIuwEZp9a5N8OfXkYH6B5lMgx86W', '0198765432', 'customer', '1770025646_1769267153_67.jpg', '9462b351374966b40c46e0245b5b496a2efb3a5d3e7e8cac6e1f4b4e16ccea9c', '2026-02-01 14:58:20', '2026-02-03 06:48:02', 'Active'),
(3, 'LIM YUN ZHEN', 'lim.yun.zhen@gmail.com', '$2y$10$oz.KEg5U54ITFhotb1FgY.2WB8IPRB2fT/4zZCufZX2.xJbkFy75u', '0189814519', 'customer', 'default.png', NULL, NULL, '2026-02-03 06:48:02', 'Active'),
(4, 'Tung kj', 'Tung.kj@gmail.com', '$2y$10$8hCudEUp3B6OtdAuNXD.e.vz6e6Jg4idrI/mFBZNKEOJjLThNRCni', '0117788888', 'customer', '1770199003_1769793134_khaijunblur.jpg', NULL, NULL, '2026-02-04 09:54:55', 'Active');

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
(8, 4, 2, 'active');

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
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_coupons`
--
ALTER TABLE `user_coupons`
  MODIFY `uc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

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
