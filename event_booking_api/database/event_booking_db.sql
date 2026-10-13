-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 09, 2026 at 07:12 PM
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
-- Database: `event_booking_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `booking_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `booking_status` enum('pending','confirmed','cancelled') DEFAULT 'pending',
  `booking_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`booking_id`, `customer_id`, `event_id`, `quantity`, `total_amount`, `booking_status`, `booking_date`) VALUES
(1, 4, 1, 2, 100.00, 'confirmed', '2026-10-04 04:01:11'),
(2, 5, 2, 1, 120.00, 'confirmed', '2026-10-04 04:01:11'),
(3, 4, 3, 2, 60.00, 'cancelled', '2026-10-04 04:01:11'),
(4, 5, 4, 1, 40.00, 'confirmed', '2026-10-04 04:01:11'),
(5, 4, 5, 3, 75.00, 'pending', '2026-10-04 04:01:11'),
(7, 4, 1, 2, 100.00, 'cancelled', '2026-10-08 20:49:23'),
(8, 7, 1, 1, 50.00, 'cancelled', '2026-10-09 04:30:58'),
(9, 7, 1, 2, 100.00, 'cancelled', '2026-10-09 04:41:29'),
(10, 7, 5, 2, 50.00, 'cancelled', '2026-10-09 15:23:26'),
(11, 7, 2, 1, 120.00, 'cancelled', '2026-10-09 16:57:29');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `organiser_id` int(11) NOT NULL,
  `venue_id` int(11) NOT NULL,
  `event_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `event_time` time NOT NULL,
  `ticket_price` decimal(10,2) NOT NULL,
  `total_tickets` int(11) NOT NULL,
  `available_tickets` int(11) NOT NULL,
  `status` enum('upcoming','ongoing','completed','cancelled') DEFAULT 'upcoming',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`event_id`, `organiser_id`, `venue_id`, `event_name`, `description`, `event_date`, `event_time`, `ticket_price`, `total_tickets`, `available_tickets`, `status`, `created_at`) VALUES
(1, 2, 1, 'UPTM Technology Conference', 'Technology and innovation conference for university students.', '2026-11-15', '09:00:00', 50.00, 300, 300, 'upcoming', '2026-10-04 03:59:24'),
(2, 3, 2, 'Malaysia Music Festival', 'Live music festival featuring local artists.', '2026-12-05', '18:00:00', 120.00, 800, 800, 'upcoming', '2026-10-04 03:59:24'),
(3, 2, 3, 'Cyber Security Workshop', 'Cyber security awareness and practical workshop.', '2026-11-22', '10:00:00', 30.00, 200, 202, 'upcoming', '2026-10-04 03:59:24'),
(4, 3, 4, 'Entrepreneurship Seminar', 'Seminar for young entrepreneurs and business owners.', '2026-12-10', '09:30:00', 40.00, 250, 250, 'upcoming', '2026-10-04 03:59:24'),
(5, 2, 5, 'Digital Innovation Expo', 'Exhibition showcasing modern digital technologies.', '2027-01-20', '10:00:00', 25.00, 600, 600, 'upcoming', '2026-10-04 03:59:24'),
(9, 2, 1, 'AI & Cybersecurity Conference 2027', 'Conference about AI, cybersecurity, cloud security and future technology.', '2027-03-15', '11:00:00', 55.00, 250, 250, 'upcoming', '2026-10-09 15:51:12');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','organiser','customer') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'System Administrator', 'admin@event.com', '$2y$10$oInRSqJ4zgTNlFw8Wbh1ZuaZWThG9VdslS6lLdK/hahnEMTZm.iu6', 'admin', '2026-10-04 03:55:46'),
(2, 'Aiman Hakim', 'aiman@event.com', '$2y$10$ZT2JcZdmZr82ESwqRJZ9s.9FYluU7NjOOdgOK0TpTZwsCwj8fWGWC', 'organiser', '2026-10-04 03:55:46'),
(3, 'Siti Nurul', 'siti@event.com', '$2y$10$deNbpxQXaws2PweDD/M7n.0yDoYtZs6kHBE7d/9D/73ggwIZmvXsO', 'organiser', '2026-10-04 03:55:46'),
(4, 'Muhammad Amir', 'amir@gmail.com', '$2y$10$WRtjgThve76oRUuyvwte9OQt6Qsso63fTkUyVzm3RSXevynoFafuy', 'customer', '2026-10-04 03:55:46'),
(5, 'Nur Aisyah', 'aisyah@gmail.com', 'aisyah123', 'customer', '2026-10-04 03:55:46'),
(7, 'Test Login', 'testlogin@gmail.com', '$2y$10$jZ.ZVYXgI5Cprog1F0dfeus.VAUmHU0kiNiqfmvz/./87KQyFCkwa', 'customer', '2026-10-08 20:12:07');

-- --------------------------------------------------------

--
-- Table structure for table `venues`
--

CREATE TABLE `venues` (
  `venue_id` int(11) NOT NULL,
  `venue_name` varchar(150) NOT NULL,
  `location` varchar(255) NOT NULL,
  `capacity` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `venues`
--

INSERT INTO `venues` (`venue_id`, `venue_name`, `location`, `capacity`, `created_at`) VALUES
(1, 'UPTM Grand Hall', 'Kuala Lumpur', 500, '2026-10-04 03:57:01'),
(2, 'KL Convention Centre', 'Kuala Lumpur', 1000, '2026-10-04 03:57:01'),
(3, 'Shah Alam Convention Centre', 'Shah Alam', 800, '2026-10-04 03:57:01'),
(4, 'Petaling Jaya Performing Arts Centre', 'Petaling Jaya', 400, '2026-10-04 03:57:01'),
(5, 'Putrajaya International Convention Centre', 'Putrajaya', 1200, '2026-10-04 03:57:01');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `fk_booking_customer` (`customer_id`),
  ADD KEY `fk_booking_event` (`event_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `fk_event_organiser` (`organiser_id`),
  ADD KEY `fk_event_venue` (`venue_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `venues`
--
ALTER TABLE `venues`
  ADD PRIMARY KEY (`venue_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `venues`
--
ALTER TABLE `venues`
  MODIFY `venue_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_booking_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_booking_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON UPDATE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `fk_event_organiser` FOREIGN KEY (`organiser_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_event_venue` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`venue_id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
