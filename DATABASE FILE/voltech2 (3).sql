-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 30, 2025 at 09:43 AM
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
-- Database: `voltech2`
--

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `position_id` int(11) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `user_id`, `first_name`, `last_name`, `position_id`, `contact_number`, `email`, `created_at`) VALUES
(1, 43, 'Jayson', 'Autor', 1, '09505183527', NULL, '2025-03-26 19:20:09'),
(2, 46, 'example', 'example', 1, '09123456789', NULL, '2025-03-27 13:24:51');

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `id` int(11) NOT NULL,
  `equipment_name` varchar(255) NOT NULL,
  `used_in` varchar(255) NOT NULL,
  `usage_purpose` text NOT NULL,
  `borrow_time` datetime NOT NULL,
  `return_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`id`, `equipment_name`, `used_in`, `usage_purpose`, `borrow_time`, `return_time`) VALUES
(1, 'shovel', 'shoveling dirt', 'de[mgh54uh', '2025-03-22 12:24:00', '2025-03-24 12:25:00');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `expense_id` int(20) NOT NULL,
  `user_id` varchar(15) NOT NULL,
  `expense` int(20) NOT NULL,
  `expensedate` varchar(15) NOT NULL,
  `expensecategory` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`expense_id`, `user_id`, `expense`, `expensedate`, `expensecategory`) VALUES
(77, '43', 50000, '2025-03-26', 'Materials and Supplies'),
(78, '43', 10000, '2025-03-26', 'Labor Costs'),
(79, '43', 20000, '2025-03-26', 'Permits, Licenses, and Compliance'),
(80, '43', 25000, '2025-03-28', 'Labor Costs'),
(82, '43', 40000, '2025-03-29', 'Marketing and Business Development'),
(83, '45', 100000, '2025-03-27', 'Labor Costs'),
(84, '45', 150000, '2025-03-28', 'Materials and Supplies'),
(85, '45', 110000, '2025-03-29', 'Site and Project Costs'),
(86, '45', 250000, '2025-03-30', 'Office and Administrative Costs'),
(87, '46', 40000, '2025-03-27', 'Labor Costs'),
(88, '46', 50000, '2025-03-28', 'Marketing and Business Development'),
(89, '46', 90000, '2025-03-29', 'Equipment and Machinery'),
(90, '47', 53543, '2025-04-05', 'Labor Costs'),
(91, '47', 6686, '2025-04-06', 'Office and Administrative Costs');

-- --------------------------------------------------------

--
-- Table structure for table `materials`
--

CREATE TABLE `materials` (
  `id` int(11) NOT NULL,
  `material_name` varchar(255) NOT NULL,
  `category` enum('Cement','Concrete','Steel','Bricks & Blocks','Wood & Timber','Tiles & Flooring','Paints & Coatings','Glass & Glazing','Plaster & Drywall','Roofing Sheets','Insulation Materials','Sealants & Waterproofing','Electrical Wires & Cables','Switches & Circuit Breakers','Pipes & Fittings','Sanitary Fixtures','Aggregates','Adhesives & Binders','Fasteners','Safety & Protective Materials') NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit` enum('kg','g','t','m³','ft³','L','mL','m','mm','cm','ft','in','pcs','bndl','rl','set','sack/bag','m²','ft²') NOT NULL,
  `status` enum('Available','In Use','Damaged','Low Stock') NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `assigned_to` varchar(255) DEFAULT NULL,
  `supplier_name` varchar(255) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `labor_other` int(11) NOT NULL DEFAULT 0,
  `material_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(10,2) GENERATED ALWAYS AS (`labor_other` + `material_price`) STORED,
  `total_amount` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `materials`
--

INSERT INTO `materials` (`id`, `material_name`, `category`, `quantity`, `unit`, `status`, `location`, `assigned_to`, `supplier_name`, `purchase_date`, `user_id`, `labor_other`, `material_price`, `total_amount`) VALUES
(1, 'wdwdwd', 'Cement', 454, 'kg', 'Available', 'Warehouse', 'Karlo', 'dfaffsfs', '2025-03-06', 0, 0, 0.00, 0),
(2, '343', 'Cement', 886, 'kg', 'Available', 'Warehouse', 'Karlo', 'dfaffsfs', '2025-03-13', 0, 0, 0.00, 0),
(3, 'test1', 'Steel', 68, '', 'Damaged', 'Warehouse', 'Karlo', 'dfaffsfs', '0000-00-00', 9, 0, 0.00, 0),
(4, 'eef', '', 33, '', 'Low Stock', 'Warehouse', 'Karlo', 'dfaffsfs', '2025-03-13', 8, 0, 0.00, 0),
(5, 'qwerty', '', 3, 't', 'Damaged', 'Warehouse', 'Karlo', 'chance', '2025-03-24', 9, 34, 3434.00, 0),
(6, 'qwerty', 'Bricks & Blocks', 1, 'g', 'Available', '', 'Karlo', 'chance', '2025-03-24', 9, 577, 757.00, 0),
(7, 'qwerty', 'Steel', 53, 'm', 'Available', 'Warehouse', '757', '757', '0007-07-05', 9, 6575, 775.00, 0),
(8, 'qwerty', 'Cement', 127, 'kg', 'Available', 'Warehouse', 'Karlo', 'dfaffsfs', '2025-03-23', 9, 0, 0.00, 0),
(9, '43', 'Pipes & Fittings', 533, 'mL', 'In Use', 'Warehouse', '757', 'dfaffsfs', '2025-03-24', 9, 0, 0.00, 0),
(10, 'newq', 'Sanitary Fixtures', 5, 'sack/bag', 'Available', 'Warehouse', 'Karlo', 'chance', '2025-03-24', 9, 50, 100.00, 750),
(11, 'hollowblock', 'Bricks & Blocks', 2, 'pcs', 'Damaged', 'Warehouse', 'Karlo', 'Big Chanz', '2025-03-24', 9, 450, 78.00, 1056),
(12, 'hollowblock', 'Bricks & Blocks', 4, 'pcs', 'Available', 'Warehouse', 'autor', 'timo', '2025-03-24', 8, 40, 13.00, 212),
(13, 'hollowblock', 'Bricks & Blocks', 5, 'pcs', 'Available', 'Warehouse', 'Karlo', 'Big Chanz', '2025-03-24', 9, 10, 20.00, 150);

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `position_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `daily_rate` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `positions`
--

INSERT INTO `positions` (`position_id`, `title`, `daily_rate`, `created_at`) VALUES
(1, 'Mason', 950.00, '2025-03-26 19:16:35');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `project_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `project` varchar(255) NOT NULL,
  `location` varchar(255) NOT NULL,
  `deadline` date NOT NULL,
  `io` int(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`project_id`, `user_id`, `project`, `location`, `deadline`, `io`, `created_at`) VALUES
(1, 1, 'Sample Project 1', 'City Center', '2025-04-30', 1, '2025-03-26 17:29:27'),
(2, 1, 'Sample Project 2', 'North Office', '2025-05-15', 2, '2025-03-26 17:29:27'),
(3, 1, 'Sample Project 3', 'South Branch', '2025-06-20', 3, '2025-03-26 17:29:27'),
(4, 43, 'build', 'laguna', '2025-03-26', 1, '2025-03-26 17:29:41'),
(5, 43, 'build', 'laguna', '2025-03-26', 1, '2025-03-26 17:29:54'),
(6, 46, 'example', 'example', '2025-03-27', 2, '2025-03-27 13:11:00'),
(7, 47, 'house1', 'sta.cruz, laguna', '2025-04-05', 1, '2025-04-05 19:05:45');

-- --------------------------------------------------------

--
-- Table structure for table `projects_forecasting`
--

CREATE TABLE `projects_forecasting` (
  `id` int(11) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `old_size` float NOT NULL,
  `old_cost` float NOT NULL,
  `new_size` float NOT NULL,
  `estimated_cost` float NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects_forecasting`
--

INSERT INTO `projects_forecasting` (`id`, `project_name`, `old_size`, `old_cost`, `new_size`, `estimated_cost`, `notes`, `created_at`) VALUES
(1, 'house 1', 5000, 100000, 2500, 50000, '', '2025-04-05 19:06:12'),
(2, 'house 1', 5000, 100000, 2500, 50000, '', '2025-04-05 19:06:48');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `firstname` varchar(50) NOT NULL,
  `lastname` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `reset_code` varchar(255) DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `verification_code` text DEFAULT NULL,
  `verification_expires` datetime DEFAULT NULL,
  `failed_attempts` int(11) DEFAULT 0,
  `user_level` tinyint(1) DEFAULT NULL
) ;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `firstname`, `lastname`, `email`, `password`, `is_verified`, `reset_code`, `remember_token`, `verification_code`, `verification_expires`, `failed_attempts`, `user_level`) VALUES
(28, 'Jayson', 'Autor', 'jaysonautor.20@gmail.com', '$2y$10$1VcHAA3HqEea645l66EWuuSTPHAhGYNJlmLfadfzOn4dNIWhUTmGS', 0, NULL, NULL, '$2y$10$O8Csp.4NkGHtPnax26s/te2IC89/.H1JHVa/M8T/UjdpECR5F0CFq', '2025-03-25 14:25:32', 0, 3),
(45, 'jayson', 'autor', 'jaysonautor.2003@gmail.com', '$2y$10$EUakkPIcfN7apaWFA8LCme8PVz8dTZUR0BeezO0f1w25ZvOBiGu7W', 1, NULL, NULL, '$2y$10$0NSIRM8U3TvN7gn3xxY4Me03hEE66/T4Gu.8z.3glVLvU6tZlCHGS', '2025-03-27 04:45:03', 0, 3),
(46, 'VOLTECH', 'Electrical Conts.', 'voltechelectricalconstruction0@gmail.com', '$2y$10$dq2PEP0T6ZI7o.hSSR22X.awXPKNiParpMMm/FD6JzRSXh2ivEIZq', 1, '9c7e2c1e94fc3e26107b545da74eabd6', NULL, '$2y$10$6rzP7Nl8eqlgVmdD3o61P.0w8wzjF2M/PkvYiGRmP1rpKczwlDOFG', '2025-03-27 07:01:35', 0, 3),
(47, 'Karlo', 'Lacsam', 'bsitbacapstone@gmail.com', '$2y$10$EF8oYYEPcKpHFKXewuY6Fe1NJrlYQcEIrbyVGiPVfodblEpm1EvqS', 1, NULL, NULL, '$2y$10$GKJDdKpU4284wxXycAPGJ.7IV1PSKDgkYJJvluaCGuejlQaxrMVqK', '2025-04-05 19:52:00', 0, 3);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`);

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`expense_id`);

--
-- Indexes for table `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`position_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`project_id`);

--
-- Indexes for table `projects_forecasting`
--
ALTER TABLE `projects_forecasting`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `expense_id` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;

--
-- AUTO_INCREMENT for table `materials`
--
ALTER TABLE `materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `position_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `project_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `projects_forecasting`
--
ALTER TABLE `projects_forecasting`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
