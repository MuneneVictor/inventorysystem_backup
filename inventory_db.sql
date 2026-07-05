-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 05, 2026 at 11:30 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `inventory_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `accessories`
--

CREATE TABLE `accessories` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `quantity` int NOT NULL,
  `added_by` int DEFAULT NULL,
  `place` enum('display','store','warehouse') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'display',
  `branch` enum('MOI','KIMATHI') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'MOI',
  `price` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(10,2) GENERATED ALWAYS AS ((`quantity` * `price`)) STORED,
  `date_added` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('instock','sold') DEFAULT 'instock',
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `accessories`
--

INSERT INTO `accessories` (`id`, `name`, `quantity`, `added_by`, `place`, `branch`, `price`, `date_added`, `status`, `updated_by`, `updated_at`) VALUES
(1, 'DELL optical mouse', 21, 1, 'display', 'MOI', NULL, '2026-06-17 15:07:59', 'instock', 1, '2026-06-22 13:34:24'),
(2, 'JBL essential 2 speaker', 9, 1, 'display', 'KIMATHI', NULL, '2026-06-22 08:38:12', 'instock', 4, '2026-07-06 00:19:00'),
(3, 'power cable', 5, 1, 'display', 'MOI', 500.00, '2026-03-17 09:51:07', 'instock', NULL, NULL),
(4, 'power cable', 22, 1, 'store', 'KIMATHI', 600.00, '2026-03-15 10:29:47', 'instock', 1, '2026-07-04 16:41:13'),
(5, 'HP Keyboard', 5, 1, 'store', 'MOI', NULL, '2026-06-22 10:34:24', 'instock', 1, '2026-06-22 13:34:24'),
(6, 'USB-C Adapter', 3, 1, 'warehouse', 'MOI', NULL, '2026-06-22 10:34:24', 'instock', 1, '2026-06-22 13:34:24'),
(7, 'power cable', 6, 1, 'store', 'KIMATHI', 500.00, '2026-03-19 08:15:54', 'instock', 1, '2026-06-26 11:16:06'),
(8, 'power cable', 1, 8, 'display', 'MOI', NULL, '2026-03-17 09:22:16', 'instock', NULL, NULL),
(9, 'power cable', 10, 1, 'store', 'MOI', 600.00, '2026-07-04 13:41:13', 'instock', 1, '2026-07-04 16:41:13'),
(10, 'JBL essential 2 speaker', 1, 1, 'display', 'MOI', NULL, '2026-07-05 04:47:21', 'instock', 1, '2026-07-05 07:47:21');

-- --------------------------------------------------------

--
-- Table structure for table `accessories_logs`
--

CREATE TABLE `accessories_logs` (
  `id` int NOT NULL,
  `accessory_id` int DEFAULT NULL,
  `accessory_name` varchar(100) DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `given_to` int DEFAULT NULL,
  `given_by` int DEFAULT NULL,
  `branch` enum('MOI','KIMATHI') DEFAULT NULL,
  `status` enum('sold','pending_sale','returned') DEFAULT 'pending_sale',
  `date_given` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `sale_item_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `accessories_logs`
--

INSERT INTO `accessories_logs` (`id`, `accessory_id`, `accessory_name`, `quantity`, `given_to`, `given_by`, `branch`, `status`, `date_given`, `sale_item_id`) VALUES
(1, 4, 'power cable', 2, 7, 8, 'KIMATHI', 'sold', '2026-06-30 10:16:09', 38),
(2, 4, 'power cable', 18, 7, 1, 'KIMATHI', 'sold', '2026-07-02 11:34:23', 36);

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `details`, `created_at`) VALUES
(1, 1, 'Added device', 'Added device SN: 5CG7766H', '2025-12-03 12:28:36'),
(2, NULL, 'Sold device', 'Sold device SN: 5CG7766H', '2025-12-03 12:29:35'),
(3, NULL, 'Sold device', 'Sold device SN: GH677HG72', '2025-12-03 12:50:02'),
(4, NULL, 'Sold device', 'Sold device SN: THG67J100', '2025-12-03 12:51:04'),
(5, 4, 'Maintenance - Update Specs', 'Updated specs for GH677HG7U by Munene vicky. RAM: 16 -> 16, Storage: 512 -> 256, Graphics: none -> none', '2025-12-03 15:20:41'),
(6, 1, 'Added device', 'Added device SN: 5CG77XH4', '2025-12-03 15:29:32'),
(7, 1, 'Added device', 'Added device SN: THG567JKDF', '2025-12-03 15:56:59'),
(8, NULL, 'Added device', 'Added device SN: 5CG890KL9', '2025-12-03 15:59:21'),
(9, NULL, 'Sold device', 'Sold device SN: GH677HG70', '2025-12-03 18:59:48'),
(10, NULL, 'Sold device', 'Sold device SN: 5CG890KL9', '2025-12-03 19:01:10'),
(11, NULL, 'Sold device', 'Sold device SN: THG67J99', '2025-12-04 04:18:46'),
(12, 7, 'Sold device', 'Sold device SN: 5CGHJJ67', '2025-12-04 19:29:24'),
(13, 7, 'Sold device', 'Sold device SN: THG567JKDF', '2025-12-04 19:40:53'),
(14, 1, 'Added device', 'Device 5CGHJJ68 (HP ELITEBOOK 840 G6) added via Excel upload', '2025-12-05 20:28:12'),
(15, 1, 'Added device', 'Device 5CGHJJ69 (HP ELITEBOOK 840 G8) added via Excel upload', '2025-12-05 20:28:12'),
(16, 1, 'Added device', 'Device 5CGHJJ70 (HP ELITEBOOK 840 G8) added via Excel upload', '2025-12-05 20:28:12'),
(17, 1, 'Added device', 'Device 5CGHJJ71 (HP ELITEBOOK 840 G6) added via Excel upload', '2025-12-05 20:28:12'),
(18, 1, 'Added device', 'Device 5CGHJJ72 (HP ELITEBOOK 840 G6) added via Excel upload', '2025-12-05 20:28:12'),
(19, 1, 'Added device', 'Device 5CGHJJ73 (HP ELITEBOOK 840 G6) added via Excel upload', '2025-12-05 20:28:12'),
(20, 1, 'Added device', 'Device 5CGHJJ74 (HP ELITEBOOK 840 G8) added via Excel upload', '2025-12-05 20:28:12'),
(21, 1, 'Added device', 'Device 5CGHJJ75 (DELL OPTILEX 5530) added via Excel upload', '2025-12-05 20:28:12'),
(22, 1, 'Added device', 'Device 5CGHJJ78 (HP ELITEDESK 705 G5) added via Excel upload', '2025-12-05 20:28:12'),
(23, 1, 'Added device', 'Device 5CGHJJ79 (HP ELITEDESK 800 G3) added via Excel upload', '2025-12-05 20:28:12'),
(24, 1, 'Added device', 'Device 5CGHJJ80 (HP ELITEDESK 800 G3) added via Excel upload', '2025-12-05 20:28:12'),
(25, 1, 'Added device', 'Device 5CGHJJ81 (HP PRO ONE 24) added via Excel upload', '2025-12-05 20:28:12'),
(26, 1, 'Added device', 'Device 5CGHJJ82 (HP PRO ONE 24) added via Excel upload', '2025-12-05 20:28:12'),
(27, 1, 'Added device', 'Device 5CGHJJ83 (HP PRO ONE 24) added via Excel upload', '2025-12-05 20:28:12'),
(28, 1, 'Added device', 'Device 5CGHJJ84 (HP ALL IN ONE 25) added via Excel upload', '2025-12-05 20:28:12'),
(29, 1, 'Added device', 'Device 5CGHJJ85 (HP ALL IN ONE 25) added via Excel upload', '2025-12-05 20:28:12'),
(30, 1, 'Added device', 'Device 5CGHJJB33 (HP ELITEBOOK 840 G6) added via Excel upload', '2025-12-05 20:50:03'),
(31, 1, 'Added device', 'Device 5CGHJJB34 (HP ELITEBOOK 840 G6) added via Excel upload', '2025-12-05 20:50:03'),
(32, 1, 'Added device', 'Device 5CGHJJB35 (HP ELITEBOOK 840 G8) added via Excel upload', '2025-12-05 20:50:03'),
(33, 1, 'Added device', 'Device 5CGHJJB36 (HP ELITEBOOK 840 G8) added via Excel upload', '2025-12-05 20:50:03'),
(34, 1, 'Added device', 'Device 5CGHJJB37 (HP ELITEBOOK 840 G6) added via Excel upload', '2025-12-05 20:50:03'),
(35, 1, 'Added device', 'Device 5CGHJJB38 (HP ELITEBOOK 840 G6) added via Excel upload', '2025-12-05 20:50:03'),
(36, 1, 'Added device', 'Device 5CGHJJB39 (HP ELITEBOOK 840 G6) added via Excel upload', '2025-12-05 20:50:03'),
(37, 1, 'Added device', 'Device 5CGHJJB40 (HP ELITEBOOK 840 G8) added via Excel upload', '2025-12-05 20:50:03'),
(38, 1, 'Added device', 'Device 5CGHJJB41 (DELL OPTILEX 5530) added via Excel upload', '2025-12-05 20:50:03'),
(39, 8, 'Added RAM/SSD', 'Added 20 RAM of type NVMe in KIMATHI', '2025-12-05 22:39:06'),
(40, 8, 'Added RAM/SSD', '10 SSD of type SATA (256GB) added in KIMATHI', '2025-12-05 23:34:14'),
(41, 8, 'Updated RAM/SSD', '4 SSD of type SATA (256GB) increased in KIMATHI', '2025-12-05 23:34:49'),
(42, 8, 'Given RAM/SSD', 'Given 1 SSD (256GB) to Peninah Kalundi in KIMATHI', '2025-12-05 23:39:27'),
(43, 7, 'Sold device', 'Sold device SN: 5CGHJJB33', '2025-12-05 23:45:48'),
(44, 8, 'Added RAM/SSD', '5 RAM of type DDR3 (16GB) added in KIMATHI', '2025-12-06 04:01:43'),
(45, 7, 'Sold device', 'Sold device SN: 5CGHJJB36', '2025-12-06 12:50:17'),
(46, 1, 'Added RAM/SSD', '15 SSD(s) of type NVMe (512GB) added in KIMATHI', '2025-12-06 18:05:45'),
(47, 1, 'Added RAM/SSD', '20 SSD(s) of type SATA (256GB) added in MOI', '2025-12-06 18:08:29'),
(48, 1, 'Given RAM/SSD', 'Given 10 SSD (256GB) to Bruce dee in KIMATHI', '2025-12-06 18:29:53'),
(49, 8, 'Added RAM/SSD', '5 SSD(s) of type SATA (512GB) added in KIMATHI', '2025-12-06 21:39:21'),
(50, 8, 'Added RAM/SSD', '7 RAM(s) of type DDR3 (16GB) added in KIMATHI', '2025-12-06 21:39:34'),
(51, 7, 'Sold device', 'Sold device SN: 5CGHJJB34', '2025-12-06 21:41:27'),
(52, 8, 'Edited device', 'Edited device SN: 5CGHJJB36 (Status: Sold → Sold, Branch: MOI)', '2025-12-06 21:51:27'),
(53, 8, 'Given RAM/SSD', 'Given 5 SSD (512GB) to Peninah Kalundi in KIMATHI', '2025-12-06 22:00:25'),
(54, 1, 'Added RAM/SSD', '9 SSD(s) of type SATA (512GB) added in MOI', '2025-12-07 03:56:00'),
(55, 1, 'Edited device', 'Edited device SN: THG567JKDF (Status: Sold → Sold, Branch: KIMATHI)', '2025-12-07 08:11:14'),
(56, 1, 'Edited device', 'Edited device SN: THG567JKDF (Status: Sold → Sold, Branch: KIMATHI)', '2025-12-07 08:23:25'),
(57, NULL, 'device_loaded_for_repair', 'Serial: 5CGHJJB40, Model: HP ELITEBOOK 840 G8', '2025-12-07 11:19:22'),
(58, NULL, 'repair_added', 'Serial: 5CGHJJB40, Problem: broken screen', '2025-12-07 11:20:04'),
(59, NULL, 'device_loaded_for_repair', 'Serial: 5CGHJJB38, Model: HP ELITEBOOK 840 G6', '2025-12-07 11:22:41'),
(60, NULL, 'repair_added', 'Serial: 5CGHJJB38, Problem: camera issue', '2025-12-07 11:30:32'),
(61, NULL, 'Added Repair Record', 'Device: 5CGHJJ81 | Problem: NOT POWERING | Fix: FIXED | Given By ID: 8', '2025-12-07 13:41:20'),
(62, 4, 'Maintenance - Update Specs', 'Updated specs for 5CGHJJ80 by Munene vicky. RAM: From 8 GB to 16 GB, Storage: from 1000 GB to from 256 GB, Graphics: none to none', '2025-12-07 13:59:28'),
(63, 4, 'Maintenance - Update Specs', 'Updated specs for 5CGHJJB41 by Munene vicky. RAM: From 16 GB to 8 GB, Storage: From HDD 2000 GB to SSD 256 GB, Graphics: 4GB NVIDIA QUADRO P2000 to 4GB NVIDIA QUADRO P2000', '2025-12-07 14:23:16'),
(64, 4, 'Maintenance - Update Specs', 'Updated specs for THG67J101 by Munene vicky. RAM: From 16 GB to 8 GB, Storage: From HDD 500 GB to SSD 256 GB, Graphics: 2GB AMD RADEON R7 200 to 2GB AMD RADEON R7 200', '2025-12-07 14:35:10'),
(65, 1, 'Added device', 'Device 5CGHJJR45 (HP ELITEBOOK 840 G6) added via Excel upload to branch: MOI', '2025-12-07 15:33:03'),
(66, 1, 'Added device', 'Device 5CGHJJR46 (HP ELITEBOOK 840 G7) added via Excel upload to branch: MOI', '2025-12-07 15:33:03'),
(67, 1, 'Added device', 'Device 5CGHJJR47 (HP ELITEBOOK 840 G8) added via Excel upload to branch: MOI', '2025-12-07 15:33:03'),
(68, 1, 'Added device', 'Device 5CGHJJR48 (HP ELITEBOOK 840 G9) added via Excel upload to branch: KIMATHI', '2025-12-07 15:33:03'),
(69, 4, 'Maintenance - Update Specs', 'Updated specs for THG67J102 by Munene vicky. RAM: From 16GB to 8GB. Storage: From SSD 256GB to HDD 500GB.', '2025-12-07 15:44:16'),
(70, 1, 'Updated RAM/SSD', '15 SSD(s) of type SATA (512GB) increased in KIMATHI', '2025-12-07 15:49:26'),
(71, 1, 'Given RAM/SSD', 'Given 1 SSD (512GB) to Peninah Kalundi in KIMATHI', '2025-12-07 15:54:41'),
(72, 1, 'Edited device', 'Edited device SN: 5CGHJJR47 (Status: In Stock → In Stock, Branch: MOI)', '2025-12-07 16:30:24'),
(73, 1, 'Given RAM/SSD', 'Given 2 SSD (512GB) to Peninah Kalundi in KIMATHI', '2025-12-09 05:28:43'),
(74, 7, 'Sold device', 'Sold device SN: 5CGHJJ31', '2025-12-09 05:31:18'),
(75, 1, 'Added RAM/SSD', '9 RAM(s) of type PC4 (16GB) added in KIMATHI', '2025-12-09 05:47:19'),
(76, 1, 'Added device', 'Added device SN: 5CGU78X9, Cargo: CX37', '2025-12-13 13:57:43'),
(77, 1, 'Added device', 'Added device SN: 5CGHH67Y8, Cargo: CX37', '2025-12-13 14:53:06'),
(78, 1, 'Added device', 'Added device SN: 5CG11RT3H, Cargo: CX37', '2025-12-13 15:03:14'),
(79, 1, 'Added device', 'Added device SN: 8CC67YHU5, Cargo: CX35', '2025-12-13 18:42:16'),
(80, 1, 'Added device', 'Device 5CGHJJRQW1 (HP ELITEBOOK 840 G5) added via Excel upload to branch: MOI', '2025-12-13 19:20:14'),
(81, 1, 'Added device', 'Device 5CGHJJRQW2 (HP ELITEBOOK 840 G5) added via Excel upload to branch: MOI', '2025-12-13 19:20:14'),
(82, 1, 'Added device', 'Device 5CGHJJRQW3 (HP ELITEBOOK 840 G5) added via Excel upload to branch: MOI', '2025-12-13 19:20:14'),
(83, 1, 'Added device', 'Device 5CGHJJRQW4 (HP ELITEBOOK 840 G5) added via Excel upload to branch: KIMATHI', '2025-12-13 19:20:14'),
(84, 1, 'Added device', 'Device 5CGHJJRQW5 (HP ELITEBOOK 840 G5) added via Excel upload to branch: KIMATHI', '2025-12-13 19:20:14'),
(85, 1, 'Added device', 'Device 5CGHJJRQW6 (HP ELITEBOOK 840 G5) added via Excel upload to branch: MOI', '2025-12-13 19:20:14'),
(86, 1, 'Added device', 'Device TGBHJJRQW1 (HP ELITEDESK 705 G5) added via Excel upload to branch: MOI', '2025-12-13 22:43:46'),
(87, 1, 'Added device', 'Device TGBHJJRQW2 (HP ELITEDESK 705 G5) added via Excel upload to branch: MOI', '2025-12-13 22:43:46'),
(88, 1, 'Added device', 'Device TGBHJJRQW3 (HP ELITEDESK 705 G5) added via Excel upload to branch: MOI', '2025-12-13 22:43:46'),
(89, 1, 'Added device', 'Device TGBHJJRQW4 (HP ELITEDESK 705 G5) added via Excel upload to branch: KIMATHI', '2025-12-13 22:43:46'),
(90, 1, 'Added device', 'Added device SN: 8CCYYX54E, Cargo: CX37', '2025-12-14 07:23:22'),
(91, 1, 'Added device', 'Added device SN: TGH66KK8, Cargo: CX50', '2025-12-14 07:42:22'),
(92, 1, 'Added device', 'Added device SN: 8CCEE56TY, Cargo: CX37', '2025-12-14 07:54:27'),
(93, 7, 'Sold device', 'Sold device SN: 5CGHJJ84 for KES 42,500.00', '2025-12-14 08:38:04'),
(94, 1, 'Added device', 'Added device SN: 5CGKLMN0P', '2025-12-14 11:09:29'),
(95, 7, 'Sold monitor', 'Sold monitor SN: MNJGGDHH8 (DELL MONITOR)', '2025-12-14 20:21:45'),
(96, 7, 'Sold device', 'Sold device SN: 5CGHJJ68 for KES 26,000.00', '2025-12-14 20:58:03'),
(97, 7, 'Sold device', 'Sold device SN: 5CGHJJ81 for KES 0', '2025-12-14 20:58:03'),
(98, 1, 'Added device', 'Added device SN: 8CC56RRZ3, Cargo: NO CARGO', '2025-12-14 21:39:07'),
(99, 7, 'Sold printer', 'Sold printer SN: PTNJGGDHH10 (EPSON PRINTER)', '2025-12-15 05:38:17'),
(100, 1, 'Added device', 'Added device SN: MJ09BKTS, Cargo: NO CARGO', '2025-12-15 11:07:05'),
(101, 1, 'Added device', 'Added device SN: 8CCHJF56R, Cargo: NO CARGO', '2025-12-15 11:21:53'),
(102, 7, 'Sold device', 'Sold device SN: MJ09BKTS for KES 15,000.00', '2025-12-15 11:24:32'),
(103, NULL, 'Added device', 'Added device SN: 1234, Cargo: CX37', '2025-12-15 13:43:30'),
(104, NULL, 'Edited device', 'Edited device SN: 1234 (Status: In Stock → In Stock, Branch: KIMATHI)', '2025-12-15 13:48:55'),
(105, 1, 'Added device', 'Device TGBHJJRQW1 (HP ELITEDESK 705 G5) added via Excel upload to branch: MOI', '2025-12-15 18:26:54'),
(106, 1, 'Added device', 'Device TGBHJJRQW2 (HP ELITEDESK 705 G5) added via Excel upload to branch: MOI', '2025-12-15 18:26:54'),
(107, 1, 'Added device', 'Device TGBHJJRQW3 (HP ELITEDESK 705 G5) added via Excel upload to branch: MOI', '2025-12-15 18:26:54'),
(108, 1, 'Added device', 'Device TGBHJJRQW4 (HP ELITEDESK 705 G5) added via Excel upload to branch: KIMATHI', '2025-12-15 18:26:54'),
(109, 1, 'Added device', 'Device 5CG09OPL34 (HP ELITEDESK 840 G6) added via Excel upload to branch: KIMATHI', '2025-12-15 18:26:54'),
(110, 1, 'Added device', 'Device 5CG09OPL35 (HP ELITEDESK 840 G6) added via Excel upload to branch: KIMATHI', '2025-12-15 18:26:54'),
(111, 1, 'Added device', 'Device 5CG09OPL36 (HP ELITEDESK 840 G6) added via Excel upload to branch: KIMATHI', '2025-12-15 18:26:54'),
(112, 1, 'Added device', 'Device 5CG09OPL37 (HP ELITEDESK 840 G6) added via Excel upload to branch: KIMATHI', '2025-12-15 18:26:54'),
(113, 1, 'Added device', 'Device 5CG09OPL38 (HP ELITEDESK 840 G6) added via Excel upload to branch: MOI', '2025-12-15 18:26:54'),
(114, 1, 'Added device', 'Device 5CG09OPL39 (HP ELITEDESK 840 G6) added via Excel upload to branch: MOI', '2025-12-15 18:26:55'),
(115, 1, 'Added device', 'Device 5CG09OPL40 (HP ELITEDESK 840 G8) added via Excel upload to branch: MOI', '2025-12-15 18:26:55'),
(116, 1, 'Added device', 'Device 5CG09OPL41 (HP ELITEDESK 840 G8) added via Excel upload to branch: MOI', '2025-12-15 18:26:55'),
(117, 1, 'Added device', 'Device 5CG09OPL42 (HP ELITEDESK 840 G8) added via Excel upload to branch: MOI', '2025-12-15 18:26:55'),
(118, 1, 'Added device', 'Device MXHT89YU3 (HP ELITEDESK 705 G4) added via Excel upload to branch: KIMATHI', '2025-12-15 18:26:55'),
(119, 1, 'Added device', 'Device MXHT89YU4 (HP ELITEDESK 705 G4) added via Excel upload to branch: KIMATHI', '2025-12-15 18:26:55'),
(120, 1, 'Added device', 'Device MXHT89YU5 (HP ELITEDESK 705 G4) added via Excel upload to branch: KIMATHI', '2025-12-15 18:26:55'),
(121, 1, 'Added device', 'Device MXHT89YU6 (HP ELITEDESK 705 G4) added via Excel upload to branch: MOI', '2025-12-15 18:26:55'),
(122, 1, 'Added device', 'Device MXHT89YU7 (HP ELITEDESK 705 G4) added via Excel upload to branch: MOI', '2025-12-15 18:26:55'),
(123, 1, 'Given RAM/SSD', 'Given 1 RAM (16GB) to Peninah Kalundi in KIMATHI', '2025-12-15 18:41:23'),
(124, 1, 'Updated RAM/SSD', '10 RAM(s) of type DDR3 (16GB) increased in KIMATHI', '2025-12-15 18:43:02'),
(125, 7, 'Sold device', 'Sold device SN: 5CG09OPL37 for KES 25,000.00', '2025-12-16 12:45:12'),
(126, 4, 'Added Repair', 'Device: TGBHJJRQW4 | Problem: Faulty keyboard | Given By: Victor Munene | Branch: KIMATHI | Technician: Munene vicky', '2025-12-16 21:34:43'),
(127, 1, 'Edited device', 'Device: 5CG09OPL37 | Updated by: Victor Munene | Changes: Model: HP ELITEDESK 840 G6 → HP ELITEDESK 840 G8', '2025-12-17 03:33:45'),
(128, 1, 'Added device', 'Device 5CG09OZL35 (HP ELITEDESK 840 G6) added via Excel upload to branch: Unknown', '2025-12-17 04:31:02'),
(129, 1, 'Added device', 'Device 5CG09OZL36 (HP ELITEDESK 840 G6) added via Excel upload to branch: Unknown', '2025-12-17 04:31:02'),
(130, 1, 'Added device', 'Device 5CG09OZL37 (HP ELITEDESK 840 G6) added via Excel upload to branch: Unknown', '2025-12-17 04:31:02'),
(131, 1, 'Added device', 'Device 5CG09OZL38 (HP ELITEDESK 840 G6) added via Excel upload to branch: Unknown', '2025-12-17 04:31:02'),
(132, 1, 'Added device', 'Device 5CG09OZL39 (HP ELITEDESK 840 G6) added via Excel upload to branch: Unknown', '2025-12-17 04:31:02'),
(133, 1, 'Added device', 'Device 5CG09OZL35 (HP ELITEDESK 840 G6) added via Excel upload to branch: KIMATHI', '2025-12-17 04:42:33'),
(134, 1, 'Added device', 'Device 5CG09OZL36 (HP ELITEDESK 840 G6) added via Excel upload to branch: KIMATHI', '2025-12-17 04:42:33'),
(135, 1, 'Added device', 'Device 5CG09OZL37 (HP ELITEDESK 840 G6) added via Excel upload to branch: KIMATHI', '2025-12-17 04:42:33'),
(136, 1, 'Added device', 'Device 5CG09OZL38 (HP ELITEDESK 840 G6) added via Excel upload to branch: KIMATHI', '2025-12-17 04:42:33'),
(137, 1, 'Added device', 'Device 5CG09OZL39 (HP ELITEDESK 840 G6) added via Excel upload to branch: KIMATHI', '2025-12-17 04:42:33'),
(138, 1, 'Updated RAM/SSD', '5 SSD(s) of type SATA (512GB) increased in KIMATHI', '2025-12-17 16:39:07'),
(139, NULL, 'Added Repair', 'Device: 5CG09OPL34 | Problem: faulty keyboard | Given By: Victor Munene | Branch: KIMATHI | Technician: denis', '2025-12-18 05:16:11'),
(140, 7, 'Sold device', 'Sold device SN: 5CG09OPL34 for KES 25,000.00', '2025-12-18 05:17:46'),
(141, 4, 'Maintenance - Update Specs', 'Updated specs for TGBHJJRQW4 by Munene vicky. RAM: From 16GB to 8GB. Storage: From SSD 256GB to HDD 500GB. Graphics: From none to 2GB AMD RADEON R7 200.', '2025-12-20 18:32:33'),
(142, 1, 'Transfer device', 'Transferred device SN: MXHT89YU3 from KIMATHI to MOI (Delivered by: munene)', '2025-12-28 14:06:33'),
(143, 8, 'Updated RAM/SSD', '5 RAM(s) of type PC4 (16GB) increased in KIMATHI', '2025-12-28 14:12:15'),
(144, 8, 'Added RAM/SSD', '5 RAM(s) of type PC4 (8GB) added in KIMATHI', '2025-12-28 14:12:54'),
(145, 8, 'Given RAM/SSD', 'Given 2 SSD (512GB) to Peninah Kalundi in KIMATHI', '2025-12-28 14:17:01'),
(146, 1, 'Transfer device', 'Transferred device SN: TGBHJJRQW1 from MOI to KIMATHI (Delivered by: Vic)', '2025-12-29 02:36:59'),
(147, 1, 'Transfer RAMs/SSDs', 'Transferred 3 component type(s) (18 total items) from KIMATHI to MOI: DDR3 16GB RAM x7, PC4 16GB RAM x6, SATA 512GB SSD x5 (Delivered by: munene)', '2025-12-29 20:46:01'),
(148, 8, 'Transfer RAMs/SSDs', 'Transferred 1 component type(s) (1 total items) from KIMATHI to MOI: DDR3 16GB RAM x1 (Delivered by: Vic)', '2025-12-30 04:13:03'),
(149, 8, 'Transfer chargers', 'Transferred 1 charger type(s) (1 total items) from KIMATHI to MOI: HP Blue Pin 65W (new) x1 (Delivered by: Vic)', '2025-12-30 04:14:38'),
(150, 1, 'Added device', 'Added device SN: 5TG89JJKV9, Cargo: NO CARGO', '2025-12-30 08:34:15'),
(151, 7, 'Sold device', 'Sold device SN: MXHT89YU5 for KES 20,000.00', '2026-01-02 15:37:57'),
(152, NULL, 'Added Repair', 'Device: MXHT89YU7 | Problem: Faulty keyboard | Given By: Victor Syovata | Branch: MOI | Technician: Vic vdeb', '2026-01-02 16:00:33'),
(153, NULL, 'Added Repair', 'Device: MXHT89YU7 | Problem: Speaker issue | Given By: Victor Syovata | Branch: MOI | Technician: Vic vdeb', '2026-01-02 16:30:36'),
(154, 1, 'Edited device', 'Device: TGBHJJRQW4 | Updated by: Victor Munene | Changes: RAM: 8GB → 16GB, Touch: N/A → ', '2026-01-22 11:28:03'),
(155, 1, 'Transfer RAMs/SSDs', 'Transferred 1 component type(s) (8 total items) from KIMATHI to MOI: DDR3 16GB RAM x8 (Delivered by: Vic)', '2026-01-22 11:32:46'),
(156, 1, 'Transfer chargers', 'Transferred 1 charger type(s) (1 total items) from KIMATHI to MOI: HP Blue Pin 65W (new) x1 (Delivered by: Victor)', '2026-02-02 07:08:12'),
(157, 1, 'Added device', 'Added device SN: MXY77HJKAL, Cargo: AC3', '2026-02-21 18:46:43'),
(158, 1, 'Added device', 'Added device SN: MXD90IOOKLW, Cargo: AC3', '2026-02-21 18:48:48'),
(159, 1, 'Transfer chargers', 'Transferred 1 charger type(s) (5 total items) from MOI to KIMATHI: HP Blue Pin 45W (new) x5 (Delivered by: Victor)', '2026-03-01 06:18:23'),
(160, 1, 'Added device', 'Added device SN: MXFGHGFH, Cargo: CXC30', '2026-03-03 18:09:46'),
(161, 1, 'Edited device', 'Device: MXFGHGFH | Updated by: Victor Munene | Changes: Storage: SSD 16GB → SSD 512GB', '2026-03-03 18:11:38'),
(162, 7, 'Sold device', 'Sold device SN: MXD90IOOKLW for KES 15,000.00', '2026-03-03 18:24:35'),
(163, 1, 'Transfer monitor', 'Transferred monitor SN: MNJGGDHH10 from MOI to KIMATHI (Delivered by: Victor)', '2026-03-03 18:29:32'),
(164, 7, 'Sold monitor', 'Sold monitor SN: MNJGGDHH9 (DELL MONITOR)', '2026-03-03 18:30:17'),
(165, 4, 'Maintenance - Update Specs', 'Updated specs for MXHT89YU7 by Munene vicky. RAM: From 8GB to 16GB. Storage: From SSD 256GB to SSD 512GB.', '2026-03-03 18:36:38'),
(166, 4, 'Added Repair', 'Device: MXFGHGFH | Problem: FAULTY KEYBOARD | Given By: Victor Munene | Branch: KIMATHI | Technician: Munene vicky', '2026-03-03 18:44:11'),
(167, 7, 'Sold device', 'Sold device SN: TGBHJJRQW1 for KES 40,000.00', '2026-03-08 10:01:45'),
(168, NULL, 'Updated RAM/SSD', '2 RAM(s) of type DDR3 (16GB) increased in MOI', '2026-03-17 11:28:24'),
(169, 1, 'Added device', 'Added device SN: 5CG0302X7Y, Cargo: AC26', '2026-03-27 08:32:20'),
(170, 1, 'Transfer RAMs/SSDs', 'Transferred 1 component type(s) (1 total items) from KIMATHI to MOI: PC4 8GB RAM x1 (Delivered by: victor)', '2026-03-28 06:34:30'),
(171, 1, 'Edited device', 'Device: TGBHJJRQW4 | Updated by: Victor Munene | Changes: Storage: HDD 500GB → SSD 256GB', '2026-03-29 17:10:59'),
(172, 1, 'Edited device', 'Device: TGBHJJRQW4 | Updated by: Victor Munene | Changes: Graphics: 2GB AMD RADEON R7 200 → None', '2026-03-29 17:12:09'),
(173, 1, 'Added device', 'Device 5CG09OZXE32 (HP ELITEBOOK 840 G6) added via Excel upload to branch: MOI', '2026-04-02 12:50:27'),
(174, 1, 'Added device', 'Device 5CG09OZXE33 (HP ELITEBOOK 840 G6) added via Excel upload to branch: MOI', '2026-04-02 12:50:27'),
(175, 1, 'Added device', 'Device 5CG09OZXE34 (HP ELITEBOOK 840 G6) added via Excel upload to branch: MOI', '2026-04-02 12:50:27'),
(176, 1, 'Added device', 'Device 5CG09OZXE35 (HP ELITEBOOK 840 G6) added via Excel upload to branch: MOI', '2026-04-02 12:50:27'),
(177, 1, 'Added device', 'Device 5CG09OZXE36 (HP ELITEBOOK 840 G6) added via Excel upload to branch: KIMATHI', '2026-04-02 12:50:27'),
(178, 1, 'Added device', 'Device 5CG09OZXE37 (HP ELITEBOOK 840 G8) added via Excel upload to branch: KIMATHI', '2026-04-02 12:50:27'),
(179, 1, 'Added device', 'Device 5CG09OZXE38 (HP ELITEBOOK 840 G8) added via Excel upload to branch: KIMATHI', '2026-04-02 12:50:27'),
(180, 1, 'Added device', 'Device 5CG09OZXE39 (HP ELITEBOOK 840 G8) added via Excel upload to branch: KIMATHI', '2026-04-02 12:50:27'),
(181, 1, 'Added device', 'Device 5CG09OZXE40 (HP ELITEBOOK 840 G8) added via Excel upload to branch: KIMATHI', '2026-04-02 12:50:27'),
(182, 1, 'Added device', 'Device 5CG09OZXE41 (HP ELITEBOOK 840 G8) added via Excel upload to branch: KIMATHI', '2026-04-02 12:50:27'),
(183, 1, 'Added device', 'Device 5CG09OZXE42 (HP ELITEBOOK 840 G8) added via Excel upload to branch: MOI', '2026-04-02 12:50:27'),
(184, 1, 'Added device', 'Device THK897YYRS4 (HP Z6 WORKSTATION) added via Excel upload to branch: MOI', '2026-04-02 12:50:27'),
(185, 1, 'Added device', 'Device THK897YYRS5 (HP Z6 WORKSTATION) added via Excel upload to branch: MOI', '2026-04-02 12:50:27'),
(186, 1, 'Added device', 'Device THK897YYRS6 (HP Z6 WORKSTATION) added via Excel upload to branch: MOI', '2026-04-02 12:50:27'),
(187, 1, 'Added device', 'Device THK897YYRS7 (HP Z6 WORKSTATION) added via Excel upload to branch: MOI', '2026-04-02 12:50:27'),
(188, 1, 'Added device', 'Device THK897YYRS8 (HP Z6 WORKSTATION) added via Excel upload to branch: KIMATHI', '2026-04-02 12:50:27'),
(189, 1, 'Added device', 'Device THK897YYRS9 (HP Z6 WORKSTATION) added via Excel upload to branch: KIMATHI', '2026-04-02 12:50:27'),
(190, 1, 'Added device', 'Device 8CC6YHMS54 (HP ELITEDESK 705 G4) added via Excel upload to branch: KIMATHI', '2026-04-02 12:50:27'),
(191, 1, 'Added device', 'Device 8CC6YHMS55 (HP ELITEDESK 705 G4) added via Excel upload to branch: KIMATHI', '2026-04-02 12:50:27'),
(192, 1, 'Added device', 'Device 8CC6YHMS56 (HP ELITEDESK 705 G4) added via Excel upload to branch: MOI', '2026-04-02 12:50:27'),
(193, 1, 'Added device', 'Device 8CC6YHMS57 (HP ELITEDESK 705 G5) added via Excel upload to branch: MOI', '2026-04-02 12:50:27'),
(194, 1, 'Added device', 'Device 8CC6YHMS58 (HP ELITEDESK 705 G5) added via Excel upload to branch: MOI', '2026-04-02 12:50:27'),
(195, 1, 'Added device', 'Added device SN: 5CGUYUIJVIUG, Cargo: AC526', '2026-04-22 20:28:16'),
(196, 1, 'Added device', 'Added device SN: 5CGBAJJJUU88, Cargo: AC265', '2026-06-07 10:45:29'),
(197, 7, 'Sold device', 'Sold device SN: 5CG09OZXE40 for KES 60,000.00', '2026-06-07 13:55:36'),
(198, 7, 'Sold monitor', 'Sold monitor SN: ER556TGHM (HP COMPAQ)', '2026-06-07 15:18:22'),
(199, 1, 'Bulk upload', 'Added device 5CG1234XYZ (HP EliteBook 840 G6) via Excel upload to branch: KIMATHI', '2026-06-08 07:02:20'),
(200, 1, 'Bulk upload', 'Added device 8CC5678ABC (HP EliteDesk 705 G4) via Excel upload to branch: MOI', '2026-06-08 07:02:20'),
(201, 1, 'Bulk upload', 'Added device ABC9012DEF (HP ProOne 400 G5) via Excel upload to branch: KIMATHI', '2026-06-08 07:02:20'),
(202, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ9 (HP EliteBook 840 G6) via Excel upload to branch: KIMATHI', '2026-06-08 07:02:20'),
(203, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ10 (HP EliteBook 840 G6) via Excel upload to branch: KIMATHI', '2026-06-08 07:02:20'),
(204, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ11 (HP EliteBook 840 G6) via Excel upload to branch: KIMATHI', '2026-06-08 07:02:20'),
(205, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ12 (HP EliteBook 840 G6) via Excel upload to branch: KIMATHI', '2026-06-08 07:02:20'),
(206, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ13 (HP EliteBook 840 G6) via Excel upload to branch: KIMATHI', '2026-06-08 07:02:20'),
(207, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ14 (HP EliteBook 840 G6) via Excel upload to branch: KIMATHI', '2026-06-08 07:02:20'),
(208, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ15 (HP EliteBook 840 G6) via Excel upload to branch: MOI', '2026-06-08 07:02:20'),
(209, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ16 (HP EliteBook 840 G6) via Excel upload to branch: MOI', '2026-06-08 07:02:20'),
(210, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ17 (HP EliteBook 840 G6) via Excel upload to branch: MOI', '2026-06-08 07:02:20'),
(211, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ18 (HP EliteBook 840 G6) via Excel upload to branch: MOI', '2026-06-08 07:02:20'),
(212, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ19 (HP EliteBook 840 G6) via Excel upload to branch: MOI', '2026-06-08 07:02:20'),
(213, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ20 (HP EliteBook 840 G8) via Excel upload to branch: MOI', '2026-06-08 07:02:20'),
(214, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ21 (HP EliteBook 840 G8) via Excel upload to branch: MOI', '2026-06-08 07:02:20'),
(215, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ22 (HP EliteBook 840 G8) via Excel upload to branch: KIMATHI', '2026-06-08 07:02:20'),
(216, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ23 (HP EliteBook 840 G8) via Excel upload to branch: KIMATHI', '2026-06-08 07:02:20'),
(217, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ24 (HP EliteBook 840 G8) via Excel upload to branch: KIMATHI', '2026-06-08 07:02:20'),
(218, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ25 (HP EliteBook 840 G8) via Excel upload to branch: KIMATHI', '2026-06-08 07:02:20'),
(219, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ26 (HP EliteBook 840 G8) via Excel upload to branch: MOI', '2026-06-08 07:02:20'),
(220, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ27 (HP EliteBook 840 G8) via Excel upload to branch: MOI', '2026-06-08 07:02:20'),
(221, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ28 (HP EliteBook 840 G8) via Excel upload to branch: MOI', '2026-06-08 07:02:20'),
(222, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ29 (HP EliteBook 840 G8) via Excel upload to branch: MOI', '2026-06-08 07:02:20'),
(223, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ30 (LENOVO THINKPAD T480s) via Excel upload to branch: MOI', '2026-06-08 07:02:20'),
(224, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ31 (LENOVO THINKPAD T480s) via Excel upload to branch: KIMATHI', '2026-06-08 07:02:20'),
(225, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ32 (LENOVO THINKPAD T480s) via Excel upload to branch: KIMATHI', '2026-06-08 07:02:20'),
(226, 1, 'Bulk upload', 'Added device 5CG7TYTUGJ33 (LENOVO THINKPAD T480s) via Excel upload to branch: KIMATHI', '2026-06-08 07:02:20'),
(227, 1, 'Bulk upload', 'Added device 5CG7TYTUGl6 (HP EliteBook 840 G6) via Excel upload to branch: KIMATHI', '2026-06-08 07:07:34'),
(228, 1, 'Bulk upload', 'Added device 5CG7TYTUGl7 (HP EliteBook 840 G6) via Excel upload to branch: KIMATHI', '2026-06-08 07:07:34'),
(229, 1, 'Bulk upload', 'Added device 5CG7TYTUGl8 (HP EliteBook 840 G6) via Excel upload to branch: KIMATHI', '2026-06-08 07:07:34'),
(230, 1, 'Bulk upload', 'Added device 5CG7TYTUGl9 (HP EliteBook 840 G6) via Excel upload to branch: KIMATHI', '2026-06-08 07:07:34'),
(231, 1, 'Bulk upload', 'Added device 5CG7TYTUGl10 (HP EliteBook 840 G6) via Excel upload to branch: KIMATHI', '2026-06-08 07:07:34'),
(232, 1, 'Bulk upload', 'Added device 5CG7TYTUGl11 (HP EliteBook 840 G6) via Excel upload to branch: KIMATHI', '2026-06-08 07:07:34'),
(233, 1, 'Bulk upload', 'Added device 5CG7TYTUGl12 (HP EliteBook 840 G6) via Excel upload to branch: MOI', '2026-06-08 07:07:34'),
(234, 1, 'Bulk upload', 'Added device 5CG7TYTUGl13 (HP EliteBook 840 G6) via Excel upload to branch: MOI', '2026-06-08 07:07:34'),
(235, 1, 'Bulk upload', 'Added device 5CG7TYTUGl14 (HP EliteBook 840 G6) via Excel upload to branch: MOI', '2026-06-08 07:07:34'),
(236, 1, 'Bulk upload', 'Added device 5CG7TYTUGl15 (HP EliteBook 840 G6) via Excel upload to branch: MOI', '2026-06-08 07:07:34'),
(237, 1, 'Bulk upload', 'Added device 5CG7TYTUGl16 (HP EliteBook 840 G6) via Excel upload to branch: MOI', '2026-06-08 07:07:34'),
(238, 1, 'Bulk upload', 'Added device 5CG7TYTUGl17 (HP EliteBook 840 G8) via Excel upload to branch: MOI', '2026-06-08 07:07:34'),
(239, 1, 'Bulk upload', 'Added device 5CG7TYTUGl18 (HP EliteBook 840 G8) via Excel upload to branch: MOI', '2026-06-08 07:07:34'),
(240, 1, 'Bulk upload', 'Added device 5CG7TYTUGl19 (HP EliteBook 840 G8) via Excel upload to branch: KIMATHI', '2026-06-08 07:07:34'),
(241, 1, 'Bulk upload', 'Added device 5CG7TYTUGl20 (HP EliteBook 840 G8) via Excel upload to branch: KIMATHI', '2026-06-08 07:07:34'),
(242, 1, 'Bulk upload', 'Added device 5CG7TYTUGl21 (HP EliteBook 840 G8) via Excel upload to branch: KIMATHI', '2026-06-08 07:07:34'),
(243, 1, 'Bulk upload', 'Added device 5CG7TYTUGl22 (HP EliteBook 840 G8) via Excel upload to branch: KIMATHI', '2026-06-08 07:07:34'),
(244, 1, 'Bulk upload', 'Added device 5CG7TYTUGl23 (HP EliteBook 840 G8) via Excel upload to branch: MOI', '2026-06-08 07:07:34'),
(245, 1, 'Bulk upload', 'Added device 5CG7TYTUGl24 (HP EliteBook 840 G8) via Excel upload to branch: MOI', '2026-06-08 07:07:35'),
(246, 1, 'Bulk upload', 'Added device 5CG7TYTUGl25 (HP EliteBook 840 G8) via Excel upload to branch: MOI', '2026-06-08 07:07:35'),
(247, 1, 'Bulk upload', 'Added device 5CG7TYTUGl26 (HP EliteBook 840 G8) via Excel upload to branch: MOI', '2026-06-08 07:07:35'),
(248, 1, 'Bulk upload', 'Added device 5CG7TYTUGl27 (LENOVO THINKPAD T480s) via Excel upload to branch: MOI', '2026-06-08 07:07:35'),
(249, 1, 'Bulk upload', 'Added device 5CG7TYTUGl28 (LENOVO THINKPAD T480s) via Excel upload to branch: KIMATHI', '2026-06-08 07:07:35'),
(250, 1, 'Bulk upload', 'Added device 5CG7TYTUGl29 (LENOVO THINKPAD T480s) via Excel upload to branch: KIMATHI', '2026-06-08 07:07:35'),
(251, 1, 'Bulk upload', 'Added device 5CG7TYTUGl30 (LENOVO THINKPAD T480s) via Excel upload to branch: KIMATHI', '2026-06-08 07:07:35'),
(252, 1, 'User Status Change', 'User ID 4 has been activated by Victor Munene', '2026-06-08 09:36:38'),
(253, 1, 'User Status Change', 'User ID 4 has been activated by Victor Munene', '2026-06-08 09:37:20'),
(254, 1, 'User Status Change', 'User ID 4 has been activated by Victor Munene', '2026-06-08 09:40:19'),
(255, 1, 'User Status Change', 'User ID 4 has been restricted by Victor Munene', '2026-06-08 09:46:56'),
(256, 1, 'User Status Change', 'User ID 4 has been activated by Victor Munene', '2026-06-08 09:47:16'),
(257, 7, 'Sold device', 'Sold device SN: 5CG09OZXE37 for KES 0', '2026-06-08 13:13:30'),
(258, 1, 'Bulk upload monitors', 'Uploaded 28 monitors to MOI branch', '2026-06-08 14:52:36'),
(259, 1, 'Transfer device', 'Transferred device SN: 5CG7TYTUGl28 from KIMATHI to MOI (Delivered by: victor)', '2026-06-08 15:32:30'),
(260, 1, 'Transfer chargers', 'Transferred 1 charger type(s) (1 total items) from KIMATHI to MOI: HP Blue Pin 45W (new) x1 (Delivered by: victor)', '2026-06-08 15:34:24'),
(261, 1, 'User Status Change', 'User ID 4 has been restricted by Victor Munene', '2026-06-09 07:58:14'),
(262, 1, 'User Status Change', 'User ID 4 has been activated by Victor Munene', '2026-06-09 08:19:53'),
(263, 1, 'Give out RAM/SSD', 'Gave 2 RAM (DDR3, 16GB) to Peninah Kalundi in MOI branch', '2026-06-10 15:54:33'),
(264, 1, 'User Status Change', 'User ID 7 has been restricted by Victor Munene', '2026-06-12 09:26:00'),
(265, 1, 'User Status Change', 'User ID 7 has been activated by Victor Munene', '2026-06-12 09:26:11'),
(266, 1, 'Added monitor', 'Added monitor SN: M9OHG56DCGF (HP FRMALESS 24 inch) to MOI branch', '2026-06-16 09:18:51'),
(267, 1, 'User Status Change', 'User ID 4 has been restricted by Munene vicky', '2026-06-17 12:16:50'),
(268, 1, 'User Status Change', 'User ID 4 has been activated by Munene vicky', '2026-06-17 12:22:02'),
(269, 1, 'User Status Change', 'User ID 7 has been restricted by Munene vicky', '2026-06-17 12:22:36'),
(270, 1, 'User Status Change', 'User ID 7 has been activated by Victor Munene', '2026-06-17 12:23:52'),
(271, 1, 'User Status Change', 'User ID 4 has been restricted by Victor Munene', '2026-06-17 12:24:15'),
(272, 1, 'User Status Change', 'User ID 4 has been activated by Victor Munene', '2026-06-17 12:27:30'),
(273, 7, 'Sold device', 'Sold device SN: 5CG7TYTUGl9 for KES 0.00', '2026-06-17 15:30:44'),
(274, 4, 'Maintenance - Update Specs', 'Updated specs for 5CG09OZXE38 by Munene vicky. Storage: SSD 256GB → SSD 512GB.', '2026-06-17 16:00:44'),
(275, 7, 'Sold device', 'Sold device SN: 5CG09OZXE40 for KES 60,000.00', '2026-06-17 16:07:54'),
(276, 1, 'Added device', 'Added device SN: THHGHVCVB, Cargo: CX37', '2026-06-17 16:10:45'),
(277, 7, 'Sold device', 'Sold device SN: THHGHVCVB for KES 25,000.00', '2026-06-17 16:13:06'),
(278, 1, 'Added monitor', 'Added monitor SN: M9JIOHG56DCGF (HP FRAMALESS 24 inch) to KIMATHI branch', '2026-06-18 11:46:40'),
(279, 1, 'Added smartboard', 'Added smartboard SN: SM5CG09OZXE60', '2026-06-19 18:44:40'),
(280, 1, 'Bulk upload smartboard', 'Added smartboard SB001 (SMART 75-inch) via Excel upload', '2026-06-20 08:07:14'),
(281, 1, 'Bulk upload smartboard', 'Added smartboard SB002 (ViewSonic 65-inch) via Excel upload', '2026-06-20 08:07:14'),
(282, 1, 'Bulk upload smartboard', 'Added smartboard SB002S4R (SMART 75-inch) via Excel upload', '2026-06-20 08:11:59'),
(283, 1, 'Bulk upload smartboard', 'Added smartboard SB0025TY (Onescreen 5) via Excel upload', '2026-06-20 08:11:59'),
(284, 1, 'Bulk upload smartboard', 'Added smartboard SMI0OUJ (SMART 6065) via Excel upload', '2026-06-20 08:23:16'),
(285, 1, 'Added accessory', 'Added accessory: JBL essential 2 speaker (Qty: 2) to MOI branch', '2026-06-22 08:38:12'),
(286, 1, 'Updated accessory', 'Added 1 more units to accessory: JBL essential 2 speaker in MOI branch (now total updated)', '2026-06-22 08:45:13'),
(287, 1, 'Updated accessory', 'Added 1 more units to accessory: JBL essential 2 speaker in MOI branch (place: display)', '2026-06-22 09:49:30'),
(288, 1, 'Added accessory', 'Added new accessory: power cable (Qty: 5) to MOI branch (place: display)', '2026-06-22 09:51:07'),
(289, 1, 'Updated accessory', 'Added 1 more units to accessory: dell optical mouse in MOI branch (place: display)', '2026-06-22 09:55:36'),
(290, 1, 'Added accessory', 'Added new accessory: power cable (Qty: 50) to MOI branch (place: store)', '2026-06-22 10:29:47'),
(291, 1, 'Bulk upload accessory', 'Updated accessory \'DELL optical mouse\' (branch MOI, place display) – quantity increased by 10.', '2026-06-22 10:34:24'),
(292, 1, 'Bulk upload accessory', 'Added accessory \'HP Keyboard\' (branch MOI, place store) – quantity 5, price NULL', '2026-06-22 10:34:24'),
(293, 1, 'Bulk upload accessory', 'Added accessory \'USB-C Adapter\' (branch MOI, place warehouse) – quantity 3, price NULL', '2026-06-22 10:34:24'),
(294, 1, 'Added smartboard price', 'Added price for smartboard SN: SMI0OUJ to KES 150000', '2026-06-22 13:21:47'),
(295, 1, 'Updated smartboard price', 'Updated price for smartboard SN: SMI0OUJ to KES 150000.00', '2026-06-22 13:22:34'),
(296, 1, 'Added device', 'Added device SN: MXL8U9UYH09, Cargo: CX37', '2026-06-22 15:37:34'),
(297, 1, 'Added accessory', 'Added new accessory: power cable (Qty: 5) to KIMATHI branch (place: store)', '2026-06-26 08:15:54'),
(298, 1, 'Updated accessory', 'Added 1 more units to accessory: power cable in KIMATHI branch (place: store)', '2026-06-26 08:16:06'),
(299, 1, 'Added HDD', 'Added new HDD: SATA (500GB) Qty: 10 to MOI branch', '2026-06-26 10:39:13'),
(300, 1, 'Give out RAM/SSD', 'Gave 2 SSD (SATA, 512GB) to Victor Syovata in KIMATHI branch', '2026-06-26 10:46:33'),
(301, 1, 'Give out HDD', 'Gave 1 HDD(s) (SATA, 2TB) to Peninah Kalundi in KIMATHI branch', '2026-06-26 11:07:45'),
(302, 7, 'Sold HDD', 'Sold HDD (SATA, 2TB) - Quantity: 1 for KES 15,000.00', '2026-06-26 12:53:56'),
(303, 1, 'Give out HDD', 'Gave 1 HDD(s) (SATA, 2TB) to Peninah Kalundi in KIMATHI branch', '2026-06-26 13:07:49'),
(304, 1, 'Returned HDD (full)', 'Returned 1 HDD(s) (SATA, 2TB) from log ID 2. Status set to returned.', '2026-06-26 13:42:12'),
(305, 1, 'Give out HDD', 'Gave 2 HDD(s) (SATA, 2TB) to Peninah Kalundi in KIMATHI branch', '2026-06-26 13:45:20'),
(306, 1, 'Returned HDD (partial)', 'Returned 1 HDD(s) (SATA, 2TB) from log ID 3. Remaining quantity: 1.', '2026-06-26 13:45:32'),
(307, 1, 'Returned HDD (full)', 'Returned 1 HDD(s) (SATA, 2TB) from log ID 3. Status set to returned.', '2026-06-26 13:45:40'),
(308, 1, 'Give out HDD', 'Gave 1 HDD(s) (SATA, 2TB) to Peninah Kalundi in KIMATHI branch', '2026-06-26 14:08:21'),
(309, 1, 'Returned HDD (full)', 'Returned 1 HDD(s) (SATA, 2TB) from log ID 4. Status set to returned.', '2026-06-26 14:15:57'),
(310, 1, 'Give out HDD', 'Gave 2 HDD(s) (SATA, 2TB) to Peninah Kalundi in KIMATHI branch', '2026-06-26 14:18:50'),
(311, 1, 'Returned HDD (partial)', 'Returned 1 HDD(s) (SATA, 2TB) from log ID 5. Remaining quantity: 1. <a href=\'/uploads/hdd_returns/hdd_return_5_1782483897.png\' target=\'_blank\'>View Photo</a>', '2026-06-26 14:24:57'),
(312, 1, 'Returned HDD (full)', 'Returned 1 HDD(s) (SATA, 2TB) from log ID 5. Status set to returned. <a href=\'/uploads/hdd_returns/hdd_return_5_1782484312.png\' target=\'_blank\'>View Photo</a>', '2026-06-26 14:31:52'),
(313, 1, 'Give out HDD', 'Gave 1 HDD(s) (SATA, 2TB) to Peninah Kalundi in KIMATHI branch', '2026-06-26 14:48:29'),
(314, 1, 'Returned HDD (full)', 'Returned 1 HDD(s) (SATA, 2TB) from log ID 6. Status set to returned. <a href=\'uploads/hdd_returns/hdd_return_6_1782485322.png\' target=\'_blank\'>View Photo</a>', '2026-06-26 14:48:42'),
(315, 1, 'Give out HDD', 'Gave 1 HDD(s) (SATA, 2TB) to Peninah Kalundi in KIMATHI branch', '2026-06-26 14:53:39'),
(316, 1, 'Returned HDD (full)', 'Returned 1 HDD(s) (SATA, 2TB) from log ID 7. Status set to returned. <a href=\'../uploads/hdd_returns/hdd_return_7_1782485644.png\' target=\'_blank\'>View Photo</a>', '2026-06-26 14:54:04'),
(317, 1, 'Give out HDD', 'Gave 3 HDD(s) (SATA, 500GB) to Victor Syovata in MOI branch', '2026-06-26 14:56:19'),
(318, 8, 'Sold HDD', 'Sold HDD (SATA, 500GB) - Quantity: 3 for KES 4,000.00', '2026-06-26 14:58:13'),
(319, 1, 'Returned RAM/SSD (full)', 'Returned 2 SSD(s) (SATA, 512GB) from log ID 7. Status set to returned. <a href=\'../uploads/ram_ssd_returns/ram_ssd_return_7_1782487019.jpg\' target=\'_blank\'>View Photo</a>', '2026-06-26 15:16:59'),
(320, 1, 'Give out RAM/SSD', 'Gave 2 SSD (SATA, 512GB) to Peninah Kalundi in KIMATHI branch', '2026-06-26 15:20:09'),
(321, 1, 'Returned RAM/SSD (full)', 'Returned 5 SSD(s) (SATA, 512GB) from log ID 1. Status set to returned. <a href=\'../uploads/ram_ssd_returns/ram_ssd_return_1_1782488162.jpg\' target=\'_blank\'>View Photo</a>', '2026-06-26 15:36:02'),
(322, 7, 'Sold RAM/SSD', 'Sold SSD (SATA, 512GB) - Quantity: 2 for KES 10,000.00', '2026-06-26 15:36:39'),
(323, 7, 'Sold RAM/SSD', 'Sold RAM (DDR3, 16GB) - Quantity: 2 for KES 10,000.00', '2026-06-26 15:39:17'),
(324, 7, 'Sold RAM/SSD', 'Sold SSD (SATA, 512GB) - Quantity: 2 for KES 10,000.00', '2026-06-26 15:43:00'),
(325, 7, 'Sold RAM/SSD', 'Sold SSD (SATA, 512GB) - Quantity: 2 for KES 10,000.00', '2026-06-26 15:51:59'),
(326, 8, 'Added accessory', 'Added new accessory: power cable (Qty: 1) to MOI branch (place: display)', '2026-06-27 09:22:16'),
(327, 8, 'Give out HDD', 'Gave 2 HDD(s) (SATA, 2TB) to Peninah Kalundi in KIMATHI branch', '2026-06-27 10:44:20'),
(328, 8, 'Give out RAM/SSD', 'Gave 1 SSD (SATA, 512GB) to Peninah Kalundi in KIMATHI branch', '2026-06-27 10:44:52'),
(329, 8, 'Returned RAM/SSD (full)', 'Returned 1 SSD(s) (SATA, 512GB) from log ID 9. Status set to returned. <a href=\'../uploads/ram_ssd_returns/ram_ssd_return_9_1782561394.png\' target=\'_blank\'>View Photo</a>', '2026-06-27 11:56:34'),
(330, 8, 'Add RAM/SSD', 'Added/updated SSD (SATA, 256GB) quantity 10 in KIMATHI branch', '2026-06-27 15:01:31'),
(331, 4, 'Sold device', 'Sold device SN: 5CG1234XYZ for KES 30,000.00 in sale #3', '2026-06-29 11:13:46'),
(332, 4, 'Sold device', 'Sold device SN: 5CG09OZXE36 for KES 40,000.00 in sale #3', '2026-06-29 11:33:25'),
(333, 4, 'Sold device', 'Sold device SN: 5CG09OZXE36 for KES 40,000.00 in sale #3', '2026-06-29 12:15:10'),
(334, 4, 'Sold device', 'Sold device SN: 5CG1234XYZ for KES 30,000.00 in sale #2', '2026-06-29 12:18:09'),
(335, 4, 'Sold device', 'Sold device SN: 5CG1234XYZ for KES 30,000.00 in sale #1', '2026-06-29 12:22:51'),
(336, 7, 'Sold device', 'Sold device SN: 5CG7TYTUGJ9 for KES 35,000.00 in sale #1', '2026-06-29 12:39:56'),
(337, 4, 'Sold HDD', 'Sold HDD (SATA, 2TB) - Quantity: 2 for KES 15,000.00 in sale #1', '2026-06-29 12:50:16'),
(338, 7, 'Sold RAM/SSD', 'Sold SSD (SATA, 512GB) - Quantity: 1 for KES 10,000.00 in sale #1', '2026-06-29 12:59:30'),
(339, 1, 'Give out HDD', 'Gave 2 HDD(s) (SATA, 2TB) to Peninah Kalundi in KIMATHI branch', '2026-06-29 13:08:49'),
(340, 7, 'Sold HDD', 'Sold HDD (SATA, 2TB) - Quantity: 2 for KES 15,000.00 in sale #5', '2026-06-29 13:09:27'),
(341, 1, 'Give out HDD', 'Gave 1 HDD(s) (SATA, 2TB) to Peninah Kalundi in KIMATHI branch', '2026-06-29 13:20:23'),
(342, 7, 'Sold HDD', 'Sold HDD (SATA, 2TB) - Quantity: 1 for KES 15,000.00 in sale #5', '2026-06-29 13:20:46'),
(343, 7, 'Sold RAM/SSD', 'Sold RAM (DDR3, 16GB) - Quantity: 1 for KES 1,000.00 in sale #5', '2026-06-29 13:21:41'),
(344, 4, 'Sold monitor', 'Sold monitor SN: M9JIOHG56DCGF for KES 12,000.00 in sale #9', '2026-06-29 15:36:46'),
(345, 4, 'Sold UPS', 'Sold UPS SN: UPKNM9JHFH for KES 20,000.00 in sale #11', '2026-06-29 16:03:59'),
(346, 4, 'Sold device', 'Sold device SN: 5CG1234XYZ for KES 35,000.00 in sale #11', '2026-06-29 16:06:34'),
(347, 1, 'Transfer device', 'Transferred device SN: 5CG7TYTUGl29 from KIMATHI to MOI (Delivered by: victor)', '2026-06-30 09:54:50'),
(348, 1, 'Edited device', 'Device: 5CG09OZXE36 | Updated by: Victor Munene | Changes: Condition: Refurbished → New', '2026-06-30 11:04:46'),
(349, 8, 'Give out HDD', 'Gave 1 HDD(s) (SATA, 2TB) to Peninah Kalundi in KIMATHI branch', '2026-06-30 11:26:46'),
(350, 7, 'Sold HDD', 'Sold HDD (SATA, 2TB) - Quantity: 1 for KES 15,000.00 in sale #10', '2026-06-30 11:29:10'),
(351, 1, 'Returned HDD (full)', 'Returned 1 HDD(s) (SATA, 2TB) from log ID 12. Status set to returned. <a href=\'../uploads/hdd_returns/hdd_return_12_1782819157.png\' target=\'_blank\'>View Photo</a>', '2026-06-30 11:32:37'),
(352, 1, 'Give out RAM/SSD', 'Gave 1 SSD (SATA, 256GB) to Peninah Kalundi in KIMATHI branch', '2026-06-30 11:39:59'),
(353, 7, 'Sold RAM/SSD', 'Sold SSD (SATA, 256GB) - Quantity: 1 for KES 5,000.00 in sale #10', '2026-06-30 11:40:37'),
(354, 7, 'Sold RAM/SSD', 'Sold SSD (SATA, 256GB) - Quantity: 1 for KES 5,000.00 in sale #10', '2026-06-30 11:55:05'),
(355, 1, 'Give out HDD', 'Gave 1 HDD(s) (SATA, 2TB) to Peninah Kalundi in KIMATHI branch', '2026-06-30 12:00:59'),
(356, 1, 'Returned HDD (full)', 'Returned 1 HDD(s) (SATA, 2TB) from log ID 13. Status set to returned. <a href=\'../uploads/hdd_returns/hdd_return_13_1782820868.png\' target=\'_blank\'>View Photo</a>', '2026-06-30 12:01:08'),
(357, 4, 'Sold HDD', 'Sold HDD (SATA, 2TB) - Quantity: 1 for KES 15,000.00 in sale #10', '2026-06-30 12:11:24'),
(358, 4, 'Sold HDD', 'Sold HDD (SATA, 2TB) - Quantity: 1 for KES 15,000.00 in sale #10', '2026-06-30 12:11:46'),
(359, 4, 'Sold Charger', 'Sold Charger (HP Blue Pin 45W, new) - Quantity: 1 for KES 2,000.00 in sale #10', '2026-06-30 12:24:45'),
(360, 4, 'Sold Charger', 'Sold Charger (HP Blue Pin 45W, new) - Quantity: 1 for KES 2,000.00 in sale #10', '2026-06-30 12:30:04'),
(361, 4, 'Sold Charger', 'Sold Charger (HP Blue Pin 65W, new) - Quantity: 3 for KES 3,500.00 in sale #10', '2026-06-30 12:30:14'),
(362, 1, 'Returned Charger (full)', 'Returned 1 charger(s) (HP Blue Pin 45W, new) from log ID 14. Status set to returned. <a href=\'../uploads/charger_returns/charger_return_14_1782822922.png\' target=\'_blank\'>View Photo</a>', '2026-06-30 12:35:22'),
(363, 1, 'Added charger price', 'Added price for charger ID: 9 (Type: HP Blue Pin 45W) to KES 2000', '2026-06-30 12:47:00'),
(364, 1, 'Added charger price', 'Added price for charger ID: 11 (Type: HP Blue Pin 65W) to KES 3500', '2026-06-30 12:47:11'),
(365, 1, 'Updated charger price', 'Updated price for charger ID: 9 (Type: HP Blue Pin 45W) to KES 1500.00', '2026-06-30 12:47:27'),
(366, 1, 'Returned Charger (full)', 'Returned 1 charger(s) (HP Blue Pin 65W, new) from log ID 13. Status set to returned. <a href=\'../uploads/charger_returns/charger_return_13_1782823673.jpg\' target=\'_blank\'>View Photo</a>', '2026-06-30 12:47:53'),
(367, 4, 'Sold Charger', 'Sold Charger (HP Blue Pin 65W, new) - Quantity: 1 for KES 3,500.00 in sale #10', '2026-06-30 12:58:26'),
(368, 1, 'Bulk upload HDD', 'Added HDD \'SATA 2TB\' (branch MOI) – quantity 10, price NULL', '2026-06-30 13:39:08'),
(369, 1, 'Bulk upload HDD', 'Added HDD \'SATA 500GB\' (branch KIMATHI) – quantity 5, price NULL', '2026-06-30 13:39:08'),
(370, 1, 'Bulk upload HDD', 'Updated HDD \'SATA 2TB\' (branch MOI) – quantity increased by 10.', '2026-06-30 13:40:07'),
(371, 1, 'Bulk upload HDD', 'Updated HDD \'SATA 500GB\' (branch KIMATHI) – quantity increased by 5.', '2026-06-30 13:40:07'),
(372, 1, 'Bulk upload HDD', 'Updated HDD \'SATA 2TB\' (branch MOI) – quantity increased by 10.', '2026-06-30 13:41:03'),
(373, 1, 'Bulk upload HDD', 'Updated HDD \'SATA 500GB\' (branch KIMATHI) – quantity increased by 5.', '2026-06-30 13:41:03'),
(374, 1, 'Bulk upload HDD', 'Added HDD \'NVMe 1TB\' (branch MOI) – quantity 2, price NULL', '2026-06-30 13:41:03'),
(375, 8, 'Added RAM/SSD price', 'Added price for RAM/SSD ID: 7 (RAM DDR3 16GB) to KES 10000', '2026-06-30 14:41:32'),
(376, 4, 'Sold Graphics Card', 'Sold Graphics Card (NVIDIA QUADRO P2000, 5GB) - Quantity: 2 for KES 10,000.00 in sale #16', '2026-07-01 15:11:16'),
(377, 4, 'Sold Graphics Card', 'Sold Graphics Card (NVIDIA QUADRO P2000, 5GB) - Quantity: 2 for KES 10,000.00 in sale #16', '2026-07-01 15:11:36'),
(378, 4, 'Sold Accessory (Store)', 'Sold Accessory (power cable) - Quantity: 2 for KES 600.00 in sale #16', '2026-07-01 15:46:47'),
(379, 4, 'Sold Accessory (Display)', 'Sold Accessory (JBL essential 2 speaker) - Quantity: 2 for KES 20,000.00 in sale #16', '2026-07-01 16:04:54'),
(380, 4, 'Sold Accessory (Store)', 'Sold Accessory (power cable) - Quantity: 2 for KES 600.00 in sale #16', '2026-07-01 16:05:11'),
(381, 1, 'Updated Charger', 'Added 3 more units to charger: HP Blue Pin 65W (new) in KIMATHI branch', '2026-07-02 06:11:29'),
(382, 8, 'Added device', 'Added device SN: 5CG9IOK80B, Cargo: AC26.7', '2026-07-02 07:30:16'),
(383, 7, 'Sold Accessory (Display)', 'Sold Accessory (JBL essential 2 speaker) - Quantity: 1 for KES 20,000.00 in sale #16', '2026-07-02 08:16:37'),
(384, 1, 'Updated accessory', 'Added 10 more units to accessory: jbl essential 2 speaker in KIMATHI branch (place: display)', '2026-07-02 08:51:11'),
(385, 1, 'Give out Accessory', 'Gave 20 accessory unit(s) (power cable) to Peninah Kalundi in KIMATHI branch (Store)', '2026-07-02 11:34:23'),
(386, 1, 'Returned Accessory (partial)', 'Returned 2 accessory unit(s) (power cable) from log ID 2. Remaining quantity: 18. <a href=\'../uploads/accessory_returns/accessory_return_2_1782992525.jpg\' target=\'_blank\'>View Photo</a>', '2026-07-02 11:42:05'),
(387, 4, 'Sold Accessory (Store)', 'Sold Accessory (power cable) - Quantity: 18 for KES 600.00 in sale #20', '2026-07-02 11:47:02'),
(388, 1, 'Give out RAM/SSD', 'Gave 2 SSD (SATA, 512GB) to Peninah Kalundi in KIMATHI branch', '2026-07-03 06:01:51'),
(389, 8, 'Give out HDD', 'Gave 1 HDD(s) (SATA, 500GB) to Peninah Kalundi in KIMATHI branch', '2026-07-03 08:59:23'),
(390, 8, 'Returned RAM/SSD (partial)', 'Returned 1 SSD(s) (SATA, 512GB) from log ID 11. Remaining quantity: 1. <a href=\'../uploads/ram_ssd_returns/ram_ssd_return_11_1783071118.png\' target=\'_blank\'>View Photo</a>', '2026-07-03 09:31:58'),
(391, 8, 'Returned Graphic Card (full)', 'Returned 2 graphic card(s) (NVIDIA QUADRO P2000, 5GB) from log ID 1. Status set to returned. <a href=\'../uploads/graphic_returns/graphic_return_1_1783072123.jpg\' target=\'_blank\'>View Photo</a>', '2026-07-03 09:48:43'),
(392, 8, 'Added Graphic Card', 'Added new graphic card: AMD RYZEN 2 PRO 2600 (2 GB) Qty: 20 to MOI branch', '2026-07-03 09:51:47'),
(393, 1, 'Added charger price', 'Added price for charger ID: 10 (Type: HP Blue Pin 65W) to KES 3500', '2026-07-03 10:10:31'),
(394, 1, 'Updated charger price', 'Updated price for charger ID: 9 (Type: HP Blue Pin 45W) to KES 2500.00', '2026-07-03 10:10:57'),
(395, 1, 'Added Graphic Card', 'Added new graphic card: NVIDIA QUADRO P1000 (5 GB) Qty: 10 to KIMATHI branch', '2026-07-03 11:30:00'),
(396, 1, 'Added graphic card price', 'Added price for graphic card ID: 3 (Type: NVIDIA QUADRO P1000) to KES 10000', '2026-07-03 11:30:31'),
(397, 1, 'Added HDD price', 'Added price for HDD ID: 5 (Type: NVMe, Storage: 1TB) to KES 8000', '2026-07-03 11:43:23'),
(398, 1, 'Added RAM/SSD price', 'Added price for RAM/SSD ID: 9 (RAM PC4 16GB) to KES 1000', '2026-07-03 12:16:50'),
(399, 1, 'Added RAM/SSD price', 'Added price for RAM/SSD ID: 10 (RAM PC4 8GB) to KES 5000', '2026-07-03 12:17:00'),
(400, 1, 'Give out RAM/SSD', 'Gave 1 SSD (SATA, 512GB) to Peninah Kalundi in KIMATHI branch', '2026-07-03 12:19:09'),
(401, 1, 'Added UPS', 'Added UPS SN: UP68GH5 (Model: MECCER, Capacity: 1200VA, Branch: KIMATHI)', '2026-07-03 12:46:07'),
(402, 1, 'Bulk upload UPS', 'Added UPS SN: APC-001 (Model: APC Back-UPS, Capacity: 1500VA) via Excel upload to branch: MOI', '2026-07-03 13:16:24'),
(403, 1, 'Bulk upload UPS', 'Added UPS SN: UPS-003 (Model: DELTA UPS, Capacity: 3000VA) via Excel upload to branch: KIMATHI', '2026-07-03 13:16:24'),
(404, 1, 'Bulk upload Phone', 'Added Phone SN: PH001 (Brand: Apple, Model: iPhone 15 Pro) via Excel upload to branch: KIMATHI', '2026-07-03 13:43:44'),
(405, 1, 'Bulk upload Phone', 'Added Phone SN: PH002 (Brand: Samsung, Model: Galaxy S24) via Excel upload to branch: MOI', '2026-07-03 13:43:44');
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `details`, `created_at`) VALUES
(406, 1, 'Bulk upload Phone', 'Added Phone SN: PH003 (Brand: Nokia, Model: G22) via Excel upload to branch: KIMATHI', '2026-07-03 13:43:44'),
(407, 1, 'Bulk upload Phone', 'Added Phone SN: PH000876 (Brand: Apple, Model: iPhone 15 Pro) via Excel upload to branch: KIMATHI', '2026-07-03 13:44:39'),
(408, 1, 'Bulk upload Phone', 'Added Phone SN: PH000877 (Brand: Samsung, Model: Galaxy S24) via Excel upload to branch: KIMATHI', '2026-07-03 13:44:39'),
(409, 1, 'Bulk upload Phone', 'Added Phone SN: PH000878 (Brand: Nokia, Model: G22) via Excel upload to branch: KIMATHI', '2026-07-03 13:44:39'),
(410, 1, 'Updated phone group price', 'Updated price from KES 120000 to KES 150000 for phone group: Apple iPhone 15 Pro (8 GB RAM, 256GB storage, New) – 2 phones updated', '2026-07-03 14:21:37'),
(411, 1, 'Added phone group price', 'Added price KES 150000 for phone group: Apple iPhone 15 Pro (8 GB RAM, 256GB storage, New) – 1 phones updated', '2026-07-03 14:22:19'),
(412, 1, 'Added UPS group price', 'Added price KES 15000 for UPS group: MECCER (1200 VA, New) – 1 units updated', '2026-07-03 14:31:59'),
(413, 1, 'Updated UPS group price', 'Updated price from KES 12000 to KES 20000 for UPS group: APC Back-UPS (1500 VA, Ex-UK) – 1 units updated', '2026-07-03 14:32:30'),
(414, 1, 'Added smartboard group price', 'Added price KES 150000 for smartboard group: Onescreen 5 (65 inch) – 1 smartboards updated', '2026-07-03 15:10:19'),
(415, 1, 'Added smartboard group price', 'Added price KES 200000 for smartboard group: SMART 75-inch (75 inch) – 1 smartboards updated', '2026-07-03 15:14:55'),
(416, 1, 'Added monitor group price', 'Added price KES 10000 for monitor group: HP FRAMALESS 24 inch (24 inch) – 1 monitors updated', '2026-07-03 15:29:37'),
(417, 1, 'Updated monitor group price', 'Updated price from KES 10000 to KES 11000 for monitor group: HP FRAMALESS 24 inch (24 inch) – 1 monitors updated', '2026-07-03 15:31:26'),
(418, 1, 'Updated graphic card price', 'Updated price for graphic card ID: 3 (Type: NVIDIA QUADRO P1000) to KES 10000.00', '2026-07-04 07:15:16'),
(419, 1, 'Added smartboard', 'Added smartboard SN: SMJJ8Y6UH', '2026-07-04 07:24:04'),
(420, 1, 'Added smartboard', 'Added smartboard SN: SRTF556DCGF', '2026-07-04 07:24:46'),
(421, 1, 'Added smartboard group price', 'Added price KES 150000 for smartboard group: Onescreen 5 (65 inch) – 2 smartboards updated', '2026-07-04 07:26:37'),
(422, 1, 'Added device', 'Added device SN: 5CH78UYBO12, Cargo: AC26.7', '2026-07-04 07:46:57'),
(423, 1, 'Took to display', 'Took device SN: 5CG0302X7Y to display', '2026-07-04 08:48:57'),
(424, 1, 'Returned Device', 'Returned device SN: 5CG0302X7Y from log ID 1. Action was: take_to_display. Status set to returned. <a href=\'../uploads/device_returns/device_return_1_1783156853.jpg\' target=\'_blank\'>View Photo</a>', '2026-07-04 09:20:53'),
(425, 1, 'Give out device', 'Gave device SN: 5CG0302X7Y to salesperson ID 7 (Branch: KIMATHI)', '2026-07-04 09:37:24'),
(426, 1, 'Give out device', 'Gave device SN: 5CG0302X7Y to salesperson ID 7 (Branch: KIMATHI)', '2026-07-04 09:38:21'),
(427, 1, 'Give out device', 'Gave device SN: 5CG0302X7Y to salesperson ID 7 (Branch: KIMATHI)', '2026-07-04 09:46:06'),
(428, 8, 'Give out device', 'Gave device SN: 5CH78UYBO12 to salesperson Peninah Kalundi (Branch: KIMATHI)', '2026-07-04 10:00:22'),
(429, 1, 'Returned Device', 'Returned device SN: 5CH78UYBO12 from log ID 5. Action was: give_out. Status set to returned. <a href=\'../uploads/device_returns/device_return_5_1783159273.jpeg\' target=\'_blank\'>View Photo</a>', '2026-07-04 10:01:13'),
(430, 1, 'Added device', 'Added device SN: MXLI98HDD', '2026-07-04 10:10:36'),
(431, 1, 'Bulk upload', 'Added device 5CG12465TG8 (HP EliteBook 840 G6) via Excel upload to branch: KIMATHI', '2026-07-04 10:30:27'),
(432, 1, 'Bulk upload', 'Added device 5CG12465TG9 (HP EliteDesk 705 G4) via Excel upload to branch: MOI', '2026-07-04 10:30:27'),
(433, 1, 'Bulk upload', 'Added device 5CG12465TG10 (HP ProOne 400 G5) via Excel upload to branch: KIMATHI', '2026-07-04 10:30:27'),
(434, 1, 'Bulk upload', 'Added device 5CG12465TG09 (HP EliteBook 840 G6) via Excel upload to branch: KIMATHI, place: store', '2026-07-04 10:49:24'),
(435, 1, 'Bulk upload', 'Added device 5CG12465TG11 (HP ProOne 400 G5) via Excel upload to branch: KIMATHI, place: display', '2026-07-04 10:49:24'),
(436, 1, 'Bulk upload monitors', 'Uploaded 3 monitors to KIMATHI branch', '2026-07-04 11:20:46'),
(437, 1, 'Bulk upload monitors', 'Uploaded 3 monitors to MOI branch', '2026-07-04 11:32:12'),
(438, 1, 'Give out device', 'Gave device SN: 5CG12465TG09 to salesperson Peninah Kalundi (Branch: KIMATHI)', '2026-07-04 12:53:54'),
(439, 8, 'Transfer smartboard', 'Transferred smartboard SN: SRTF556DCGF from KIMATHI to MOI (Delivered by: victor)', '2026-07-04 13:17:47'),
(440, 1, 'Transfer accessories', 'Transferred 1 accessory type(s) (10 total items) from KIMATHI (store) to MOI (store): power cable x10 (Delivered by: victor)', '2026-07-04 13:41:13'),
(441, 1, 'Transfer HDDs', 'Transferred 1 HDD type(s) (1 total items) from KIMATHI to MOI: SATA 2TB x1 (Delivered by: victor)', '2026-07-04 13:49:40'),
(442, 1, 'Transfer accessories', 'Transferred 1 accessory type(s) (1 total items) from KIMATHI (display) to MOI (display): JBL essential 2 speaker x1 (Delivered by: munene)', '2026-07-05 04:47:21'),
(443, 4, 'Add Repair', 'Added repair for device: 5CG12465TG09 | Problem: Battery issue | Given By: Peninah Kalundi | Branch: KIMATHI | Mode: In Stock', '2026-07-05 15:03:26'),
(444, 4, 'Add Repair', 'Added repair for returned device: 5CG09OZXE36 | Problem: broken screnn | Branch: KIMATHI | Mode: Return', '2026-07-05 15:39:58'),
(445, 4, 'Add Repair', 'Added repair for client: Victor | Device: HP ELITEBOOK 840 G6 | Problem: Faulty battery | Branch: KIMATHI | Source: Client', '2026-07-05 15:59:10'),
(446, 4, 'Add Repair', 'Added repair for client: munene | Device: HP ELITEDESK 705 G5 | Problem: Not powering | Branch: KIMATHI | Source: Client | Status: Pending', '2026-07-05 16:06:35'),
(447, 4, 'Add Repair', 'Added repair for client: Victor | Device: HP ELITEDESK 705 G5 | Problem: Not powering | Branch: KIMATHI | Source: Client | Status: Pending', '2026-07-05 16:20:05'),
(448, 4, 'Repair Completed', 'Repair completed for N/A (Victor) - Email notification sent to victormunene207@gmail.com', '2026-07-05 16:41:27'),
(449, 4, 'Add Repair', 'Added repair for device: 5CG0302X7Y | Problem: faulty keyboard | Given By: Victor Syovata | Branch: KIMATHI | Source: In Stock | Status: Pending', '2026-07-05 18:05:20'),
(450, 4, 'Repair Completed', 'Repair completed for HP ELITEBOOK 745 G6 (N/A) - No email sent', '2026-07-05 18:16:08'),
(451, 4, 'Add Repair', 'Added repair for device: 5CG0302X7Y | Problem: not powering | Given By: Victor Syovata | Branch: KIMATHI | Source: In Stock | Status: Pending', '2026-07-05 18:19:37'),
(452, 4, 'Repair Completed', 'Repair completed for HP ELITEBOOK 745 G6 (N/A) - No email sent', '2026-07-05 18:35:31'),
(453, 4, 'Repair Completed', 'Repair completed for HP EliteBook 840 G6 (N/A) - No email sent', '2026-07-05 18:37:47'),
(454, 4, 'Repair Completed', 'Repair completed for N/A (Victor) - Email notification sent to victormunene207@gmail.com - Cost: KES 4,000.00', '2026-07-05 18:38:41'),
(455, 4, 'Repair Completed', 'Repair completed for N/A (munene) - No email sent - Cost: KES 2,000.00', '2026-07-05 18:39:41'),
(456, 4, 'Add Repair', 'Added repair for client: munene | Device: HP ELITEBOOK 840 G6 | Problem: Broken screen | Branch: KIMATHI | Source: Client | Status: Pending', '2026-07-05 19:04:38'),
(457, 4, 'Add Repair', 'Added repair for client: Victor | Device: HP PRO ONE ALL IN ONE | Problem: Casing replacement | Branch: KIMATHI | Source: Client | Status: Pending', '2026-07-05 19:06:20'),
(458, 4, 'Maintenance - Update Specs', 'Updated specs for 5CG0302X7Y by Munene vicky. RAM: 8GB → 16GB. Price set to NULL.', '2026-07-05 19:37:25'),
(459, 4, 'Maintenance - Update Specs', 'Updated specs for 5CG0302X7Y by Munene vicky. Price set to NULL.', '2026-07-05 19:40:24'),
(460, 4, 'Add Expense', 'Added expense: New crimping tool | Amount: KES 1,000.00 | Method: cash | Given To: Victor | Branch: KIMATHI', '2026-07-05 20:42:40'),
(461, 4, 'Sold Accessory (Store)', 'Sold Accessory (power cable) - Quantity: 18 for KES 600.00 in sale #21', '2026-07-05 20:43:39'),
(462, 4, 'Sold Accessory (Display)', 'Sold Accessory (JBL essential 2 speaker) - Quantity: 4 for KES 20,000.00 in sale #17', '2026-07-05 21:19:00'),
(463, 7, 'Sold Accessory (Store)', 'Sold Accessory (power cable) - Quantity: 2 for KES 600.00 in sale #22', '2026-07-05 21:32:19'),
(464, 7, 'Sold smartboard', 'Sold smartboard SN: SB001 for KES 200,000.00 in sale #23', '2026-07-05 21:35:20'),
(465, 4, 'Sold device', 'Sold device SN: 5CG0302X7Y for KES 35,000.00 in sale #24', '2026-07-05 21:38:55'),
(466, 4, 'Add Expense', 'Added expense: Mop | Amount: KES 2,000.00 | Method: cash | Given To: Sarah | Branch: KIMATHI', '2026-07-05 21:41:06'),
(467, 1, 'User Unlocked', 'User ID 8 has been unlocked by Victor Munene - Failed attempts reset and lock removed', '2026-07-05 22:58:24');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int NOT NULL,
  `category_name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `category_name`) VALUES
(3, 'AIO'),
(2, 'Desktop'),
(1, 'Laptop'),
(5, 'Mini Pc'),
(6, 'POS'),
(4, 'Workstation');

-- --------------------------------------------------------

--
-- Table structure for table `chargers`
--

CREATE TABLE `chargers` (
  `id` int NOT NULL,
  `charger_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `charger_condition` enum('new','ex-uk') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `quantity` int NOT NULL,
  `branch` enum('KIMATHI','MOI') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `updated_by` int NOT NULL,
  `date_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `added_by` int DEFAULT NULL,
  `date_added` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `price` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(10,2) GENERATED ALWAYS AS ((`quantity` * `price`)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chargers`
--

INSERT INTO `chargers` (`id`, `charger_type`, `charger_condition`, `quantity`, `branch`, `updated_by`, `date_updated`, `added_by`, `date_added`, `price`) VALUES
(9, 'HP Blue Pin 45W', 'new', 6, 'MOI', 1, '2026-06-08 15:34:24', 8, '2026-06-30 10:02:15', 2500.00),
(10, 'HP Blue Pin 65W', 'new', 3, 'KIMATHI', 1, '2026-07-02 06:11:29', 8, '2026-06-30 10:02:15', 3500.00),
(11, 'HP Blue Pin 65W', 'new', 2, 'MOI', 1, '2026-02-02 07:08:12', NULL, '2026-06-30 10:02:15', 3500.00),
(12, 'HP Blue Pin 45W', 'new', 4, 'KIMATHI', 1, '2026-06-30 12:35:22', NULL, '2026-06-30 10:02:15', 2000.00);

-- --------------------------------------------------------

--
-- Table structure for table `charger_logs`
--

CREATE TABLE `charger_logs` (
  `id` int NOT NULL,
  `charger_id` int NOT NULL,
  `charger_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `charger_condition` enum('new','ex_uk') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `quantity` int NOT NULL,
  `given_by` int NOT NULL,
  `given_to` int NOT NULL,
  `branch` enum('KIMATHI','MOI') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `date_given` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('sold','pending_sale','returned') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'pending_sale',
  `sale_item_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `charger_logs`
--

INSERT INTO `charger_logs` (`id`, `charger_id`, `charger_type`, `charger_condition`, `quantity`, `given_by`, `given_to`, `branch`, `date_given`, `status`, `sale_item_id`) VALUES
(11, 10, 'HP Blue Pin 65W', 'new', 3, 8, 7, 'KIMATHI', '2025-12-19 19:35:30', 'sold', 27),
(12, 10, 'HP Blue Pin 65W', 'new', 1, 4, 7, 'KIMATHI', '2025-12-20 18:28:20', 'sold', 28),
(13, 10, 'HP Blue Pin 65W', 'new', 1, 4, 7, 'KIMATHI', '2026-02-21 10:05:22', 'returned', NULL),
(14, 12, 'HP Blue Pin 45W', 'new', 1, 4, 7, 'KIMATHI', '2026-03-03 18:32:40', 'returned', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int NOT NULL,
  `client_name` varchar(100) DEFAULT NULL,
  `client_phone` varchar(50) DEFAULT NULL,
  `client_box` varchar(100) DEFAULT NULL,
  `client_email` varchar(100) DEFAULT NULL,
  `sales_person` int DEFAULT NULL,
  `date_added` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `branch` enum('MOI','KIMATHI') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `client_name`, `client_phone`, `client_box`, `client_email`, `sales_person`, `date_added`, `branch`) VALUES
(1, 'Munene', '0711529618', '0711529618', 'victormunene207@gmail.com', 7, '2026-06-29 09:41:42', 'KIMATHI'),
(2, 'peninah', '0703646909', NULL, 'vdebmunene207@gmail.com', 7, '2026-06-29 10:33:17', 'KIMATHI'),
(7, 'Munene victor', '0711529618', 'P.O. BOX 12-95400', 'victormunene207@gmail.com', 8, '2026-06-29 14:55:01', NULL),
(8, 'Musili Homes Limited', '0711529618', '', 'victormunene207@gmail.com', 7, '2026-07-02 08:18:58', 'KIMATHI'),
(9, 'munene', '0711529618', '0711529618', 'victormunene207@gmail.com', 7, '2026-07-04 15:32:01', 'KIMATHI'),
(10, 'munene', '0711529618', '0711529618', 'victormunene207@gmail.com', 7, '2026-07-04 15:32:04', 'KIMATHI'),
(11, 'munene', '0711529618', '0711529618', 'victormunene207@gmail.com', 7, '2026-07-04 15:32:09', 'KIMATHI'),
(12, 'munene', '0711529618', '0711529618', 'victormunene207@gmail.com', 7, '2026-07-04 15:32:09', 'KIMATHI'),
(13, 'munene', '0711529618', '0711529618', 'victormunene207@gmail.com', 7, '2026-07-04 15:32:09', 'KIMATHI'),
(14, 'munene', '', '', '', 7, '2026-07-04 21:09:11', 'KIMATHI'),
(15, 'munene Limited', '0711529618', '0711529618', 'victormunene207@gmail.com', 7, '2026-07-04 21:20:30', 'KIMATHI'),
(16, 'Victor', '0703646909', '', '', 7, '2026-07-05 06:11:15', 'KIMATHI'),
(17, 'peninah kalundi', '0727 733 795', '', '', 7, '2026-07-05 07:54:30', 'KIMATHI');

-- --------------------------------------------------------

--
-- Table structure for table `devices`
--

CREATE TABLE `devices` (
  `serial_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `category_id` int NOT NULL,
  `model_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `processor` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `graphics` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'None',
  `ram` int DEFAULT NULL,
  `storage_type` enum('SSD','HDD') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'SSD',
  `storage_capacity` int DEFAULT NULL,
  `touch` enum('Touch','Non-touch','N/A') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'N/A',
  `status` enum('In Stock','Faulty','Under Repair','Disposed','Sold') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'In Stock',
  `device_condition` enum('New','Refurbished','Ex-Uk') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Ex-Uk',
  `date_added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `added_by` int DEFAULT NULL,
  `branch` enum('KIMATHI','MOI') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `cargo_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'NO CARGO',
  `price` decimal(10,2) DEFAULT NULL,
  `price_updated_at` timestamp NULL DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `sold_at` datetime DEFAULT NULL,
  `sold_by` int DEFAULT NULL,
  `place` enum('store','display','warehouse','under_repair') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `devices`
--

INSERT INTO `devices` (`serial_number`, `category_id`, `model_name`, `processor`, `graphics`, `ram`, `storage_type`, `storage_capacity`, `touch`, `status`, `device_condition`, `date_added`, `added_by`, `branch`, `cargo_number`, `price`, `price_updated_at`, `selling_price`, `sold_at`, `sold_by`, `place`) VALUES
('5CG0302X7Y', 1, 'HP ELITEBOOK 745 G6', 'AMD RYZEN 7 PRO 3700u', '2GB AMD RADEON VEGA 11', 16, 'SSD', 256, 'Non-touch', 'Sold', 'Ex-Uk', '2026-03-27 08:32:20', 1, 'KIMATHI', 'AC26', NULL, NULL, 35000.00, '2026-07-06 00:38:55', 7, NULL),
('5CG09OZXE32', 1, 'HP ELITEBOOK 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 8, 'SSD', 256, 'Touch', 'In Stock', 'Refurbished', '2026-04-02 12:50:27', 1, 'MOI', 'AC16', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG09OZXE33', 1, 'HP ELITEBOOK 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 8, 'SSD', 256, 'Touch', 'In Stock', 'Refurbished', '2026-04-02 12:50:27', 1, 'MOI', 'AC16', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG09OZXE34', 1, 'HP ELITEBOOK 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 8, 'SSD', 256, 'Touch', 'In Stock', 'Refurbished', '2026-04-02 12:50:27', 1, 'MOI', 'AC16', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG09OZXE35', 1, 'HP ELITEBOOK 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 16, 'SSD', 512, 'Non-touch', 'Sold', 'Refurbished', '2026-04-02 12:50:27', 1, 'MOI', 'AC16', 40000.00, NULL, 40000.00, '2026-06-22 15:57:47', 8, NULL),
('5CG09OZXE36', 1, 'HP ELITEBOOK 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 16, 'SSD', 512, 'Non-touch', 'Sold', 'New', '2026-04-02 12:50:27', 1, 'KIMATHI', 'AC16', 40000.00, NULL, 40000.00, '2026-06-29 15:15:10', 7, 'under_repair'),
('5CG09OZXE37', 1, 'HP ELITEBOOK 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 16, 'SSD', 256, 'Touch', 'Sold', 'Refurbished', '2026-04-02 12:50:27', 1, 'KIMATHI', 'AC16', 35000.00, NULL, NULL, NULL, NULL, NULL),
('5CG09OZXE38', 1, 'HP ELITEBOOK 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 16, 'SSD', 512, 'Touch', 'Sold', 'Refurbished', '2026-04-02 12:50:27', 1, 'KIMATHI', 'AC16', NULL, NULL, 65000.00, '2026-06-27 17:14:07', 7, NULL),
('5CG09OZXE39', 1, 'HP ELITEBOOK 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 16, 'SSD', 256, 'Touch', 'Sold', 'Refurbished', '2026-04-02 12:50:27', 1, 'KIMATHI', 'AC16', NULL, NULL, 60000.00, '2026-07-01 13:03:15', 7, NULL),
('5CG09OZXE40', 1, 'HP ELITEBOOK 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 8, 'SSD', 512, 'Non-touch', 'Sold', 'Refurbished', '2026-04-02 12:50:27', 1, 'KIMATHI', 'AC16', 60000.00, '2026-04-24 11:26:11', 60000.00, '2026-06-17 19:07:54', 7, NULL),
('5CG09OZXE41', 1, 'HP ELITEBOOK 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 8, 'SSD', 512, 'Non-touch', 'In Stock', 'Refurbished', '2026-04-02 12:50:27', 1, 'KIMATHI', 'AC16', 60000.00, '2026-04-24 11:26:11', NULL, NULL, NULL, NULL),
('5CG09OZXE42', 1, 'HP ELITEBOOK 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 8, 'SSD', 512, 'Non-touch', 'In Stock', 'Refurbished', '2026-04-02 12:50:27', 1, 'MOI', 'AC16', 60000.00, '2026-04-24 11:26:11', NULL, NULL, NULL, NULL),
('5CG1234XYZ', 1, 'HP EliteBook 840 G6', 'Intel Core i5-8250U', 'Intel UHD Graphics', 8, 'SSD', 256, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'KIMATHI', 'AC16', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG12465TG09', 1, 'HP EliteBook 840 G6', 'Intel Core i5-8250U', 'Intel UHD Graphics', 8, 'SSD', 256, 'Non-touch', 'In Stock', 'Refurbished', '2026-07-04 10:49:24', 1, 'KIMATHI', 'AC16', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG12465TG10', 3, 'HP ProOne 400 G5', 'Intel Core i7-8700T', 'Intel UHD', 32, 'SSD', 1000, 'Touch', 'In Stock', 'Refurbished', '2026-07-04 10:30:27', 1, 'KIMATHI', 'AC20', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG12465TG11', 3, 'HP ProOne 400 G5', 'Intel Core i7-8700T', 'Intel UHD', 32, 'SSD', 1000, 'Touch', 'In Stock', 'Refurbished', '2026-07-04 10:49:24', 1, 'KIMATHI', 'AC20', NULL, NULL, NULL, NULL, NULL, 'display'),
('5CG12465TG8', 1, 'HP EliteBook 840 G6', 'Intel Core i5-8250U', 'Intel UHD Graphics', 8, 'SSD', 256, 'Non-touch', 'In Stock', 'Refurbished', '2026-07-04 10:30:27', 1, 'KIMATHI', 'AC16', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG12465TG9', 2, 'HP EliteDesk 705 G4', 'AMD Ryzen 5 PRO 2600', 'AMD Radeon', 16, 'SSD', 512, 'N/A', 'In Stock', 'Refurbished', '2026-07-04 10:30:27', 1, 'MOI', 'CX37', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGJ10', 1, 'HP EliteBook 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 8, 'SSD', 256, 'Touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGJ11', 1, 'HP EliteBook 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 8, 'SSD', 256, 'Touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGJ12', 1, 'HP EliteBook 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 8, 'SSD', 256, 'Touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGJ13', 1, 'HP EliteBook 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 8, 'SSD', 256, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGJ14', 1, 'HP EliteBook 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 16, 'SSD', 256, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGJ15', 1, 'HP EliteBook 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 16, 'SSD', 512, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'MOI', 'AC266', 40000.00, '2026-06-08 11:22:30', NULL, NULL, NULL, NULL),
('5CG7TYTUGJ16', 1, 'HP EliteBook 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 16, 'SSD', 512, 'Non-touch', 'Sold', 'Refurbished', '2026-06-08 07:02:20', 1, 'MOI', 'AC266', 40000.00, '2026-06-08 11:22:30', 40000.00, '2026-06-20 18:31:25', 8, NULL),
('5CG7TYTUGJ17', 1, 'HP EliteBook 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 16, 'SSD', 512, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'MOI', 'AC266', 40000.00, '2026-06-08 11:22:30', NULL, NULL, NULL, NULL),
('5CG7TYTUGJ18', 1, 'HP EliteBook 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 16, 'SSD', 512, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'MOI', 'AC266', 40000.00, '2026-06-08 11:22:30', NULL, NULL, NULL, NULL),
('5CG7TYTUGJ19', 1, 'HP EliteBook 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 16, 'SSD', 512, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'MOI', 'AC266', 40000.00, '2026-06-08 11:22:30', NULL, NULL, NULL, NULL),
('5CG7TYTUGJ20', 1, 'HP EliteBook 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 16, 'SSD', 512, 'Non-touch', 'Sold', 'Refurbished', '2026-06-08 07:02:20', 1, 'KIMATHI', 'AC266', NULL, NULL, 60000.00, '2026-07-01 16:41:20', 7, NULL),
('5CG7TYTUGJ21', 1, 'HP EliteBook 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 16, 'SSD', 512, 'Non-touch', 'Sold', 'Refurbished', '2026-06-08 07:02:20', 1, 'KIMATHI', 'AC266', NULL, NULL, 65000.00, '2026-07-01 16:23:32', 7, NULL),
('5CG7TYTUGJ22', 1, 'HP EliteBook 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 16, 'SSD', 512, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGJ23', 1, 'HP EliteBook 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 16, 'SSD', 256, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGJ24', 1, 'HP EliteBook 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 16, 'SSD', 256, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGJ25', 1, 'HP EliteBook 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 8, 'SSD', 256, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGJ26', 1, 'HP EliteBook 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 8, 'SSD', 256, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'MOI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGJ27', 1, 'HP EliteBook 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 8, 'SSD', 256, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'MOI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGJ28', 1, 'HP EliteBook 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 8, 'SSD', 256, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'MOI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGJ29', 1, 'HP EliteBook 840 G8', 'INTEL CORE I7-8TH GEN', 'None', 8, 'SSD', 256, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'MOI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGJ30', 1, 'LENOVO THINKPAD T480s', 'INTEL CORE I7-8TH GEN', 'None', 8, 'SSD', 256, 'Touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'MOI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGJ31', 1, 'LENOVO THINKPAD T480s', 'INTEL CORE I7-8TH GEN', 'None', 8, 'SSD', 256, 'Touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGJ32', 1, 'LENOVO THINKPAD T480s', 'INTEL CORE I7-8TH GEN', 'None', 8, 'SSD', 256, 'Touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGJ33', 1, 'LENOVO THINKPAD T480s', 'INTEL CORE I7-8TH GEN', 'None', 8, 'SSD', 256, 'Touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGJ9', 1, 'HP EliteBook 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 8, 'SSD', 256, 'Touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGl10', 1, 'HP EliteBook 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 8, 'SSD', 256, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:34', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGl11', 1, 'HP EliteBook 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 16, 'SSD', 256, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:34', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGl12', 1, 'HP EliteBook 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 16, 'SSD', 512, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:34', 1, 'MOI', 'AC266', 40000.00, '2026-06-08 11:22:30', NULL, NULL, NULL, NULL),
('5CG7TYTUGl13', 1, 'HP EliteBook 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 16, 'SSD', 512, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:34', 1, 'MOI', 'AC266', 40000.00, '2026-06-08 11:22:30', NULL, NULL, NULL, NULL),
('5CG7TYTUGl14', 1, 'HP EliteBook 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 16, 'SSD', 512, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:34', 1, 'MOI', 'AC266', 40000.00, '2026-06-08 11:22:30', NULL, NULL, NULL, NULL),
('5CG7TYTUGl15', 1, 'HP EliteBook 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 16, 'SSD', 512, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:34', 1, 'MOI', 'AC266', 40000.00, '2026-06-08 11:22:30', NULL, NULL, NULL, NULL),
('5CG7TYTUGl16', 1, 'HP EliteBook 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 16, 'SSD', 512, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:34', 1, 'MOI', 'AC266', 40000.00, '2026-06-08 11:22:30', NULL, NULL, NULL, NULL),
('5CG7TYTUGl17', 1, 'HP EliteBook 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 16, 'SSD', 512, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:34', 1, 'MOI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGl18', 1, 'HP EliteBook 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 16, 'SSD', 512, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:34', 1, 'MOI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGl19', 1, 'HP EliteBook 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 16, 'SSD', 512, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:34', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGl20', 1, 'HP EliteBook 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 16, 'SSD', 256, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:34', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGl21', 1, 'HP EliteBook 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 16, 'SSD', 256, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:34', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGl22', 1, 'HP EliteBook 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 8, 'SSD', 256, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:34', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGl23', 1, 'HP EliteBook 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 8, 'SSD', 256, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:34', 1, 'MOI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGl24', 1, 'HP EliteBook 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 8, 'SSD', 256, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:35', 1, 'MOI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGl25', 1, 'HP EliteBook 840 G8', 'INTEL CORE I7-11TH GEN', 'None', 8, 'SSD', 256, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:35', 1, 'MOI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGl26', 1, 'HP EliteBook 840 G8', 'INTEL CORE I7-8TH GEN', 'None', 8, 'SSD', 256, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:35', 1, 'MOI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGl27', 1, 'LENOVO THINKPAD T480s', 'INTEL CORE I7-8TH GEN', 'None', 8, 'SSD', 256, 'Touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:35', 1, 'MOI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGl28', 1, 'LENOVO THINKPAD T480s', 'INTEL CORE I7-8TH GEN', 'None', 8, 'SSD', 256, 'Touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:35', 1, 'MOI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGl29', 1, 'LENOVO THINKPAD T480s', 'INTEL CORE I7-8TH GEN', 'None', 8, 'SSD', 256, 'Touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:35', 1, 'MOI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGl30', 1, 'LENOVO THINKPAD T480s', 'INTEL CORE I7-8TH GEN', 'None', 8, 'SSD', 256, 'Touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:35', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGl6', 1, 'HP EliteBook 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 8, 'SSD', 256, 'Touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:34', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGl7', 1, 'HP EliteBook 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 8, 'SSD', 256, 'Touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:34', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGl8', 1, 'HP EliteBook 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 8, 'SSD', 256, 'Touch', 'In Stock', 'Refurbished', '2026-06-08 07:07:34', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG7TYTUGl9', 1, 'HP EliteBook 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 8, 'SSD', 256, 'Touch', 'Sold', 'Refurbished', '2026-06-08 07:07:34', 1, 'KIMATHI', 'AC266', NULL, NULL, NULL, NULL, NULL, NULL),
('5CG9IOK80B', 1, 'HO ELITEBOOK 840 G9', 'INTEL CORE I7-12TH GEN', 'None', 16, 'SSD', 512, 'Non-touch', 'In Stock', 'Ex-Uk', '2026-07-02 07:30:16', 8, 'MOI', 'AC26.7', NULL, NULL, NULL, NULL, NULL, NULL),
('5CGBAJJJUU88', 1, 'HP ELITEBOOK 840 G9', 'INTEL CORE I7-12TH GEN', 'None', 16, 'SSD', 509, 'Non-touch', 'In Stock', 'Refurbished', '2026-06-07 10:45:29', 1, 'KIMATHI', 'AC265', NULL, NULL, NULL, NULL, NULL, NULL),
('5CGUYUIJVIUG', 1, 'HP ELITEBOOK 840 G6', 'INTEL CORE I5-8TH GEN', 'None', 16, 'SSD', 256, 'Non-touch', 'In Stock', 'Refurbished', '2026-04-22 20:28:16', 1, 'MOI', 'AC526', 30000.00, '2026-04-22 20:30:56', NULL, NULL, NULL, NULL),
('5CH78UYBO12', 1, 'HP ELITEBOOK 840 G9', 'INTEL CORE I7-12TH GEN', 'None', 16, 'SSD', 512, 'Non-touch', 'In Stock', 'Ex-Uk', '2026-07-04 07:46:57', 1, 'KIMATHI', 'AC26.7', 70000.00, '2026-07-04 10:02:06', NULL, NULL, NULL, 'store'),
('8CC5678ABC', 2, 'HP EliteDesk 705 G4', 'AMD Ryzen 5 PRO 2600', 'AMD Radeon', 16, 'SSD', 512, 'N/A', 'In Stock', 'New', '2026-06-08 07:02:20', 1, 'MOI', 'CX37', 40000.00, '2026-07-04 07:35:07', NULL, NULL, NULL, NULL),
('8CC6YHMS54', 5, 'HP ELITEDESK 705 G4', 'INTEL CORE I5-8TH GEN', '1GB AMD RADEON R7 200', 8, 'SSD', 256, 'N/A', 'In Stock', 'Refurbished', '2026-04-02 12:50:27', 1, 'KIMATHI', 'AC16', NULL, NULL, NULL, NULL, NULL, NULL),
('8CC6YHMS55', 5, 'HP ELITEDESK 705 G4', 'INTEL CORE I5-8TH GEN', '1GB AMD RADEON R7 200', 8, 'SSD', 256, 'N/A', 'In Stock', 'Refurbished', '2026-04-02 12:50:27', 1, 'KIMATHI', 'AC16', NULL, NULL, NULL, NULL, NULL, NULL),
('8CC6YHMS56', 5, 'HP ELITEDESK 705 G4', 'INTEL CORE I5-8TH GEN', '2GB AMD RADEON R7 200', 8, 'SSD', 256, 'N/A', 'In Stock', 'Refurbished', '2026-04-02 12:50:27', 1, 'MOI', 'AC16', NULL, NULL, NULL, NULL, NULL, NULL),
('8CC6YHMS57', 5, 'HP ELITEDESK 705 G5', 'INTEL CORE I5-8TH GEN', '2GB AMD RADEON R7 200', 16, 'SSD', 128, 'N/A', 'In Stock', 'Refurbished', '2026-04-02 12:50:27', 1, 'MOI', 'AC16', NULL, NULL, NULL, NULL, NULL, NULL),
('8CC6YHMS58', 5, 'HP ELITEDESK 705 G5', 'INTEL CORE I5-8TH GEN', '2GB AMD RADEON R7 200', 16, 'SSD', 128, 'N/A', 'In Stock', 'Refurbished', '2026-04-02 12:50:27', 1, 'MOI', 'AC16', NULL, NULL, NULL, NULL, NULL, NULL),
('ABC9012DEF', 3, 'HP ProOne 400 G5', 'Intel Core i7-8700T', 'Intel UHD', 32, 'SSD', 1000, 'Touch', 'In Stock', 'Refurbished', '2026-06-08 07:02:20', 1, 'KIMATHI', 'AC20', NULL, NULL, NULL, NULL, NULL, NULL),
('MXD90IOOKLW', 2, 'HP ELITEDESK 705 G4', 'AMD RYZEN 5 PRO 2600', '2GB AMD RADEON R7 430', 8, 'HDD', 500, 'N/A', 'Sold', 'Refurbished', '2026-02-21 18:48:48', 1, 'KIMATHI', 'AC3', 15000.00, '2026-03-03 18:24:20', NULL, NULL, NULL, NULL),
('MXHT89YU3', 5, 'HP ELITEDESK 705 G4', 'INTEL CORE I5-7TH GEN', '1GB AMD RADEON VEGA 11', 8, 'SSD', 128, 'N/A', 'In Stock', 'Refurbished', '2025-12-15 18:26:55', 1, 'MOI', 'CX37', 25000.00, '2026-07-04 07:36:45', NULL, NULL, NULL, NULL),
('MXHT89YU4', 5, 'HP ELITEDESK 705 G4', 'INTEL CORE I5-7TH GEN', '1GB AMD RADEON VEGA 11', 8, 'SSD', 128, 'N/A', 'In Stock', 'Refurbished', '2025-12-15 18:26:55', 1, 'KIMATHI', 'CX37', 25000.00, '2026-07-04 07:36:45', NULL, NULL, NULL, NULL),
('MXHT89YU5', 5, 'HP ELITEDESK 705 G4', 'INTEL CORE I5-7TH GEN', '1GB AMD RADEON VEGA 11', 8, 'SSD', 128, 'N/A', 'Sold', 'Refurbished', '2025-12-15 18:26:55', 1, 'KIMATHI', 'CX37', 25000.00, '2026-07-04 07:36:45', NULL, NULL, NULL, NULL),
('MXHT89YU6', 5, 'HP ELITEDESK 705 G4', 'INTEL CORE I5-7TH GEN', '1GB AMD RADEON VEGA 11', 8, 'SSD', 256, 'N/A', 'In Stock', 'Refurbished', '2025-12-15 18:26:55', 1, 'MOI', 'CX37', 20000.00, '2026-01-06 12:21:00', NULL, NULL, NULL, NULL),
('MXHT89YU7', 5, 'HP ELITEDESK 705 G4', 'INTEL CORE I5-7TH GEN', '1GB AMD RADEON VEGA 11', 16, 'SSD', 512, 'N/A', 'In Stock', 'Refurbished', '2025-12-15 18:26:55', 1, 'MOI', 'CX37', 20000.00, '2026-01-06 12:21:00', NULL, NULL, NULL, NULL),
('MXL8U9UYH09', 2, 'HP ELITEDESK 705 G4', 'AMD RYZEN 5 PRO 2600', 'None', 8, 'SSD', 256, 'N/A', 'In Stock', 'Refurbished', '2026-06-22 15:37:34', 1, 'MOI', 'CX37', NULL, NULL, NULL, NULL, NULL, NULL),
('MXLI98HDD', 2, 'HP ELITEDESK 705 G4', 'AMD RYZEN 5 PRO 2600', '2GB AMD RADEON 2600', 8, 'SSD', 256, 'N/A', 'In Stock', 'Ex-Uk', '2026-07-04 10:10:36', 1, 'KIMATHI', 'NO CARGO', NULL, NULL, NULL, NULL, NULL, 'display'),
('MXY77HJKAL', 2, 'HP ELITEDESK 705 G4', 'AMD RYZEN 5 PRO 2600', '2GB AMD RADEON R7 430', 8, 'HDD', 500, 'N/A', 'In Stock', 'Refurbished', '2026-02-21 18:46:43', 1, 'KIMATHI', 'AC3', 15000.00, '2026-03-03 18:24:20', NULL, NULL, NULL, NULL),
('TGBHJJRQW1', 2, 'HP ELITEDESK 705 G5', 'INTEL CORE I5-9TH GEN', 'none', 16, 'SSD', 256, 'N/A', 'Sold', 'Refurbished', '2025-12-15 18:26:54', 1, 'KIMATHI', 'CX50', 55000.00, '2026-03-25 21:32:17', NULL, NULL, NULL, NULL),
('TGBHJJRQW2', 2, 'HP ELITEDESK 705 G5', 'INTEL CORE I5-9TH GEN', 'none', 16, 'SSD', 256, 'N/A', 'In Stock', 'Refurbished', '2025-12-15 18:26:54', 1, 'MOI', 'CX50', 55000.00, '2026-03-25 21:32:17', NULL, NULL, NULL, NULL),
('TGBHJJRQW3', 2, 'HP ELITEDESK 705 G5', 'INTEL CORE I5-9TH GEN', 'none', 16, 'SSD', 256, 'N/A', 'In Stock', 'Refurbished', '2025-12-15 18:26:54', 1, 'MOI', 'CX50', 55000.00, '2026-03-25 21:32:17', NULL, NULL, NULL, NULL),
('TGBHJJRQW4', 2, 'HP ELITEDESK 705 G5', 'INTEL CORE I5-9TH GEN', 'None', 16, 'SSD', 256, '', 'In Stock', 'Refurbished', '2025-12-15 18:26:54', 1, 'KIMATHI', 'CX50', 20000.00, '2026-06-10 15:10:30', NULL, NULL, NULL, 'under_repair'),
('THHGHVCVB', 2, 'HP ELITEDESK 705 G4', 'AMD RYZEN 5 PRO 2600', 'None', 8, 'SSD', 256, 'N/A', 'Sold', 'Refurbished', '2026-06-17 16:10:45', 1, 'KIMATHI', 'CX37', 25000.00, '2026-06-17 16:12:27', 25000.00, '2026-06-17 19:13:06', 7, NULL),
('THK897YYRS4', 4, 'HP Z6 WORKSTATION', 'INTEL XEON E-CPU12 V6', '5GB NVIDIA QUADRO P2000', 32, 'HDD', 2000, 'N/A', 'In Stock', 'Refurbished', '2026-04-02 12:50:27', 1, 'MOI', 'AC16', NULL, NULL, NULL, NULL, NULL, NULL),
('THK897YYRS5', 4, 'HP Z6 WORKSTATION', 'INTEL XEON E-CPU12 V6', '5GB NVIDIA QUADRO P2000', 32, 'HDD', 2000, 'N/A', 'In Stock', 'Refurbished', '2026-04-02 12:50:27', 1, 'MOI', 'AC16', NULL, NULL, NULL, NULL, NULL, NULL),
('THK897YYRS6', 4, 'HP Z6 WORKSTATION', 'INTEL XEON E-CPU12 V6', '5GB NVIDIA QUADRO P2000', 16, 'HDD', 2000, 'N/A', 'In Stock', 'Refurbished', '2026-04-02 12:50:27', 1, 'MOI', 'AC16', NULL, NULL, NULL, NULL, NULL, NULL),
('THK897YYRS7', 4, 'HP Z6 WORKSTATION', 'INTEL XEON E-CPU12 V2', '2GB NVIDIA QUADRO P1000', 32, 'HDD', 2000, 'N/A', 'In Stock', 'Refurbished', '2026-04-02 12:50:27', 1, 'MOI', 'AC16', NULL, NULL, NULL, NULL, NULL, NULL),
('THK897YYRS8', 4, 'HP Z6 WORKSTATION', 'INTEL XEON E-CPU12 V2', '2GB NVIDIA QUADRO P1000', 32, 'HDD', 1000, 'N/A', 'In Stock', 'Refurbished', '2026-04-02 12:50:27', 1, 'KIMATHI', 'AC16', NULL, NULL, NULL, NULL, NULL, NULL),
('THK897YYRS9', 4, 'HP Z6 WORKSTATION', 'INTEL XEON E-CPU12 V2', '2GB NVIDIA QUADRO P1000', 16, 'HDD', 1000, 'N/A', 'In Stock', 'Refurbished', '2026-04-02 12:50:27', 1, 'KIMATHI', 'AC16', NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `devices_logs`
--

CREATE TABLE `devices_logs` (
  `serial_number` varchar(50) NOT NULL,
  `category_id` int DEFAULT NULL,
  `model_name` varchar(100) DEFAULT NULL,
  `processor` varchar(100) DEFAULT NULL,
  `graphics` varchar(100) DEFAULT NULL,
  `ram` int DEFAULT NULL,
  `storage_type` varchar(50) DEFAULT NULL,
  `storage_capacity` int DEFAULT NULL,
  `touch` enum('Touch','Non-touch','N/A') DEFAULT 'N/A',
  `device_condition` enum('Ex-Uk','Refurbished','New') DEFAULT NULL,
  `branch` enum('MOI','KIMATHI') DEFAULT NULL,
  `cargo_number` varchar(50) DEFAULT 'NO CARGO',
  `action` varchar(100) DEFAULT NULL,
  `given_by` int DEFAULT NULL,
  `given_to` int DEFAULT NULL,
  `date_given` datetime DEFAULT NULL,
  `taken_by` int DEFAULT NULL,
  `date_taken` datetime DEFAULT NULL,
  `id` int NOT NULL,
  `status` enum('instock','returned','sold') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'instock'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `devices_logs`
--

INSERT INTO `devices_logs` (`serial_number`, `category_id`, `model_name`, `processor`, `graphics`, `ram`, `storage_type`, `storage_capacity`, `touch`, `device_condition`, `branch`, `cargo_number`, `action`, `given_by`, `given_to`, `date_given`, `taken_by`, `date_taken`, `id`, `status`) VALUES
('5CG0302X7Y', 1, 'HP ELITEBOOK 745 G6', 'AMD RYZEN 7 PRO 3700u', '2GB AMD RADEON VEGA 11', 8, 'SSD', 256, 'Non-touch', 'Ex-Uk', 'KIMATHI', 'AC26', 'take_to_display', NULL, NULL, NULL, 1, '2026-07-04 11:48:57', 1, 'returned'),
('5CG0302X7Y', 1, 'HP ELITEBOOK 745 G6', 'AMD RYZEN 7 PRO 3700u', '2GB AMD RADEON VEGA 11', 8, 'SSD', 256, 'Non-touch', 'Ex-Uk', 'KIMATHI', 'AC26', 'give_out', 1, 7, '2026-07-04 12:37:24', NULL, NULL, 2, 'instock'),
('5CG0302X7Y', 1, 'HP ELITEBOOK 745 G6', 'AMD RYZEN 7 PRO 3700u', '2GB AMD RADEON VEGA 11', 8, 'SSD', 256, 'Non-touch', 'Ex-Uk', 'KIMATHI', 'AC26', 'give_out', 1, 7, '2026-07-04 12:38:21', NULL, NULL, 3, 'instock'),
('5CG0302X7Y', 1, 'HP ELITEBOOK 745 G6', 'AMD RYZEN 7 PRO 3700u', '2GB AMD RADEON VEGA 11', 8, 'SSD', 256, 'Non-touch', 'Ex-Uk', 'KIMATHI', 'AC26', 'give_out', 1, 7, '2026-07-04 12:46:06', NULL, NULL, 4, 'instock'),
('5CH78UYBO12', 1, 'HP ELITEBOOK 840 G9', 'INTEL CORE I7-12TH GEN', 'None', 16, 'SSD', 512, 'Non-touch', 'Ex-Uk', 'KIMATHI', 'AC26.7', 'give_out', 8, 7, '2026-07-04 13:00:22', NULL, NULL, 5, 'returned'),
('5CG12465TG09', 1, 'HP EliteBook 840 G6', 'Intel Core i5-8250U', 'Intel UHD Graphics', 8, 'SSD', 256, 'Non-touch', 'Refurbished', 'KIMATHI', 'AC16', 'give_out', 1, 7, '2026-07-04 15:53:54', NULL, NULL, 6, 'instock');

-- --------------------------------------------------------

--
-- Table structure for table `device_updates`
--

CREATE TABLE `device_updates` (
  `id` int NOT NULL,
  `serial_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `updated_by` int DEFAULT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `old_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `new_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `device_updates`
--

INSERT INTO `device_updates` (`id`, `serial_number`, `updated_by`, `action`, `old_value`, `new_value`, `updated_at`) VALUES
(1, '5CGHJJB36', 8, 'edit', 'Sold', 'Sold', '2025-12-06 21:51:27'),
(4, '5CGHJJR47', 1, 'edit', 'In Stock', 'In Stock', '2025-12-07 16:30:24'),
(5, '1234', NULL, 'edit', 'In Stock', 'In Stock', '2025-12-15 13:48:55');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int NOT NULL,
  `expense_name` varchar(100) DEFAULT NULL,
  `description` varchar(100) DEFAULT NULL,
  `payment_method` enum('cash','Mpesa') DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `expense_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `branch` enum('MOI','KIMATHI') DEFAULT NULL,
  `given_to` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `expense_name`, `description`, `payment_method`, `total_amount`, `expense_date`, `created_by`, `branch`, `given_to`) VALUES
(1, 'Transport', 'Transport for delivery of victor\'s clients product at parklands', 'cash', 500.00, '2026-07-01 10:37:19', 4, 'KIMATHI', NULL),
(2, 'New crimping tool', 'victor went to buy crimping toll', 'cash', 1000.00, '2026-07-05 20:42:40', 4, 'KIMATHI', 'Victor'),
(3, 'Mop', 'Sarah asked money to go and buy 2 pcs of mops', 'cash', 2000.00, '2026-07-05 21:41:06', 4, 'KIMATHI', 'Sarah');

-- --------------------------------------------------------

--
-- Table structure for table `graphic_cards`
--

CREATE TABLE `graphic_cards` (
  `id` int NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `storage_capacity` int DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `added_by` int DEFAULT NULL,
  `date_added` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `price` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(10,2) GENERATED ALWAYS AS ((`quantity` * `price`)) STORED,
  `branch` enum('MOI','KIMATHI') DEFAULT NULL,
  `status` enum('instock','sold') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'instock'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `graphic_cards`
--

INSERT INTO `graphic_cards` (`id`, `type`, `storage_capacity`, `quantity`, `added_by`, `date_added`, `price`, `branch`, `status`) VALUES
(1, 'NVIDIA QUADRO P2000', 5, 8, 1, '2026-06-20 11:24:51', 11000.00, 'MOI', 'instock'),
(2, 'AMD RYZEN 2 PRO 2600', 2, 20, 8, '2026-07-03 09:51:47', 3000.00, 'MOI', 'instock'),
(3, 'NVIDIA QUADRO P1000', 5, 10, 1, '2026-07-03 11:30:00', 10000.00, 'KIMATHI', 'instock');

-- --------------------------------------------------------

--
-- Table structure for table `graphic_cards_logs`
--

CREATE TABLE `graphic_cards_logs` (
  `id` int NOT NULL,
  `graphic_card_id` int DEFAULT NULL,
  `type` varchar(100) DEFAULT NULL,
  `storage_capacity` int DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `given_by` int DEFAULT NULL,
  `given_to` int DEFAULT NULL,
  `date_given` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('sold','pending_sale','returned') DEFAULT 'pending_sale',
  `branch` enum('MOI','KIMATHI') DEFAULT NULL,
  `sale_item_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `graphic_cards_logs`
--

INSERT INTO `graphic_cards_logs` (`id`, `graphic_card_id`, `type`, `storage_capacity`, `quantity`, `given_by`, `given_to`, `date_given`, `status`, `branch`, `sale_item_id`) VALUES
(1, 1, 'NVIDIA QUADRO P2000', 5, 2, 8, 7, '2026-06-30 10:26:03', 'returned', 'KIMATHI', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `hdds`
--

CREATE TABLE `hdds` (
  `id` int NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `storage` varchar(50) DEFAULT NULL,
  `branch` enum('MOI','KIMATHI') DEFAULT NULL,
  `added_by` int DEFAULT NULL,
  `date_added` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `date_updated` datetime DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(10,2) GENERATED ALWAYS AS ((`quantity` * `price`)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `hdds`
--

INSERT INTO `hdds` (`id`, `type`, `quantity`, `storage`, `branch`, `added_by`, `date_added`, `updated_by`, `date_updated`, `price`) VALUES
(1, 'SATA', 3, '2TB', 'KIMATHI', 1, '2026-06-20 10:22:45', 1, '2026-07-04 16:49:40', 15000.00),
(2, 'SATA', 7, '500GB', 'MOI', 1, '2026-06-26 10:39:13', 1, '2026-06-26 17:56:19', NULL),
(3, 'SATA', 31, '2TB', 'MOI', 1, '2026-06-30 13:39:08', 1, '2026-07-04 16:49:40', NULL),
(4, 'SATA', 14, '500GB', 'KIMATHI', 1, '2026-06-30 13:39:08', 8, '2026-07-03 11:59:22', NULL),
(5, 'NVMe', 2, '1TB', 'MOI', 1, '2026-06-30 13:41:03', NULL, NULL, 8000.00);

-- --------------------------------------------------------

--
-- Table structure for table `hdd_logs`
--

CREATE TABLE `hdd_logs` (
  `id` int NOT NULL,
  `hdd_id` int DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `quantity_given` int DEFAULT NULL,
  `given_to` int DEFAULT NULL,
  `given_by` int DEFAULT NULL,
  `branch` enum('MOI','KIMATHI') DEFAULT NULL,
  `date_given` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `storage` varchar(50) DEFAULT NULL,
  `status` enum('sold','pending_sale','returned') DEFAULT 'pending_sale',
  `sale_item_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `hdd_logs`
--

INSERT INTO `hdd_logs` (`id`, `hdd_id`, `type`, `quantity_given`, `given_to`, `given_by`, `branch`, `date_given`, `storage`, `status`, `sale_item_id`) VALUES
(1, 1, 'SATA', 1, 7, 1, 'KIMATHI', '2026-06-26 11:07:45', '2TB', 'sold', 24),
(2, 1, 'SATA', 1, 7, 1, 'KIMATHI', '2026-06-26 13:07:49', '2TB', 'returned', NULL),
(3, 1, 'SATA', 1, 7, 1, 'KIMATHI', '2026-06-26 13:45:20', '2TB', 'returned', NULL),
(4, 1, 'SATA', 1, 7, 1, 'KIMATHI', '2026-06-26 14:08:21', '2TB', 'returned', NULL),
(5, 1, 'SATA', 1, 7, 1, 'KIMATHI', '2026-06-26 14:18:50', '2TB', 'returned', NULL),
(6, 1, 'SATA', 1, 7, 1, 'KIMATHI', '2026-06-26 14:48:29', '2TB', 'returned', NULL),
(7, 1, 'SATA', 1, 7, 1, 'KIMATHI', '2026-06-26 14:53:39', '2TB', 'returned', NULL),
(8, 2, 'SATA', 3, 8, 1, 'MOI', '2026-06-26 14:56:19', '500GB', 'sold', NULL),
(9, 1, 'SATA', 2, 7, 8, 'KIMATHI', '2026-06-27 10:44:20', '2TB', 'sold', NULL),
(10, 1, 'SATA', 2, 7, 1, 'KIMATHI', '2026-06-29 13:08:49', '2TB', 'sold', NULL),
(11, 1, 'SATA', 1, 7, 1, 'KIMATHI', '2026-06-29 13:20:23', '2TB', 'sold', NULL),
(12, 1, 'SATA', 1, 7, 8, 'KIMATHI', '2026-06-30 11:26:46', '2TB', 'returned', NULL),
(13, 1, 'SATA', 1, 7, 1, 'KIMATHI', '2026-06-30 12:00:59', '2TB', 'returned', NULL),
(14, 4, 'SATA', 1, 7, 8, 'KIMATHI', '2026-07-03 08:59:23', '500GB', 'pending_sale', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int NOT NULL,
  `invoice_number` varchar(20) NOT NULL,
  `quotation_id` int DEFAULT NULL,
  `client_name` varchar(100) NOT NULL,
  `client_phone` varchar(20) DEFAULT NULL,
  `client_box` varchar(100) DEFAULT NULL,
  `client_email` varchar(100) DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `payment_due_date` date NOT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `vat` decimal(10,2) NOT NULL DEFAULT '0.00',
  `grand_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `amount_paid` decimal(10,2) NOT NULL DEFAULT '0.00',
  `balance_due` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_status` enum('paid','partial','unpaid') DEFAULT 'unpaid',
  `payment_method` enum('cash','mpesa-till','mpesa-pochi','bank-transfer') DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `status` enum('draft','sent','paid','cancelled') DEFAULT 'draft',
  `notes` text,
  `user_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `invoice_number`, `quotation_id`, `client_name`, `client_phone`, `client_box`, `client_email`, `invoice_date`, `payment_due_date`, `subtotal`, `vat`, `grand_total`, `amount_paid`, `balance_due`, `payment_status`, `payment_method`, `payment_date`, `status`, `notes`, `user_id`, `created_at`, `updated_at`) VALUES
(1, 'INV00001', 12, 'Munene victor', '0711529618', 'P.O. BOX 12-95400', 'victormunene207@gmail.com', '2026-07-05', '2026-08-04', 25000.00, 4000.00, 29000.00, 29000.00, 0.00, 'paid', NULL, NULL, 'sent', '', 7, '2026-07-05 08:27:18', '2026-07-05 08:52:33'),
(2, 'INV00002', NULL, 'munene', '', '', '', '2026-07-05', '2026-08-04', 175000.00, 28000.00, 203000.00, 0.00, 203000.00, 'unpaid', NULL, NULL, 'cancelled', '', 7, '2026-07-05 09:02:42', '2026-07-05 09:58:27'),
(3, 'INV00003', NULL, 'Musili Homes Limited', '0711529618', '', 'victormunene207@gmail.com', '2026-07-05', '2026-08-04', 3500.00, 560.00, 4060.00, 0.00, 4060.00, 'unpaid', NULL, NULL, 'cancelled', '', 4, '2026-07-05 10:54:32', '2026-07-05 10:56:16');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` int NOT NULL,
  `invoice_id` int NOT NULL,
  `item_type` varchar(50) DEFAULT NULL,
  `item_id` varchar(50) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `specs` text,
  `quantity` int NOT NULL DEFAULT '1',
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `vat_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `vat_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_with_vat` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `invoice_items`
--

INSERT INTO `invoice_items` (`id`, `invoice_id`, `item_type`, `item_id`, `description`, `specs`, `quantity`, `unit_price`, `total_price`, `vat_rate`, `vat_amount`, `total_with_vat`) VALUES
(1, 1, 'manual', '', 'EPSON PRINTER', '', 1, 25000.00, 25000.00, 16.00, 4000.00, 29000.00),
(2, 2, 'manual', '', 'HP ELITEBOOK 745 G6', 'AMD RYZEN 7 PRO 3700u | 8GB RAM | SSD 256GB', 5, 30000.00, 150000.00, 16.00, 24000.00, 174000.00),
(4, 2, 'manual', '', 'DELL optical mouse', '', 5, 1000.00, 5000.00, 16.00, 800.00, 5800.00),
(5, 2, 'manual', '', 'JBL essential 2 speaker', '', 1, 20000.00, 20000.00, 16.00, 3200.00, 23200.00),
(6, 3, 'manual', '', 'Hp Elitebook 840 g6 Keyboard', '', 1, 3500.00, 3500.00, 16.00, 560.00, 4060.00);

-- --------------------------------------------------------

--
-- Table structure for table `invoice_payments`
--

CREATE TABLE `invoice_payments` (
  `id` int NOT NULL,
  `invoice_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','mpesa-till','mpesa-pochi','bank-transfer') NOT NULL,
  `payment_date` datetime NOT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `notes` text,
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `invoice_payments`
--

INSERT INTO `invoice_payments` (`id`, `invoice_id`, `amount`, `payment_method`, `payment_date`, `reference_number`, `notes`, `created_by`, `created_at`) VALUES
(1, 1, 29000.00, 'mpesa-till', '2026-07-05 11:51:36', '', '', 7, '2026-07-05 08:51:36');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance`
--

CREATE TABLE `maintenance` (
  `id` int NOT NULL,
  `device_serial` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `old_ram` int DEFAULT NULL,
  `new_ram` int DEFAULT NULL,
  `old_storage` int DEFAULT NULL,
  `new_storage` int DEFAULT NULL,
  `performed_by` int DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `date_performed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `old_graphics` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `new_graphics` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance`
--

INSERT INTO `maintenance` (`id`, `device_serial`, `old_ram`, `new_ram`, `old_storage`, `new_storage`, `performed_by`, `notes`, `date_performed`, `old_graphics`, `new_graphics`) VALUES
(1, 'GH677HG7U', 16, 16, 512, 256, 4, NULL, '2025-12-03 15:20:41', 'none', 'none'),
(2, '5CGHJJ80', 8, 16, 1000, 256, 4, NULL, '2025-12-07 13:59:28', 'none', 'none'),
(5, 'THG67J102', 16, 8, 256, 500, 4, NULL, '2025-12-07 15:44:16', '2GB AMD RADEON R7 200', '2GB AMD RADEON R7 200'),
(6, 'TGBHJJRQW4', 16, 8, 256, 500, 4, NULL, '2025-12-20 18:32:33', 'none', '2GB AMD RADEON R7 200'),
(7, 'MXHT89YU7', 8, 16, 256, 512, 4, 'UPGRADED RAM AND SSD FOR SHUNZA', '2026-03-03 18:36:38', '1GB AMD RADEON VEGA 11', '1GB AMD RADEON VEGA 11'),
(8, '5CG09OZXE38', 16, 16, 256, 512, 4, NULL, '2026-06-17 16:00:44', 'None', 'None'),
(9, '5CG0302X7Y', 8, 16, 256, 256, 4, NULL, '2026-07-05 19:37:25', '2GB AMD RADEON VEGA 11', '2GB AMD RADEON VEGA 11'),
(10, '5CG0302X7Y', 16, 16, 256, 256, 4, NULL, '2026-07-05 19:40:24', '2GB AMD RADEON VEGA 11', '2GB AMD RADEON VEGA 11');

-- --------------------------------------------------------

--
-- Table structure for table `monitors`
--

CREATE TABLE `monitors` (
  `serial_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `model_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `size_inches` int NOT NULL,
  `status` enum('In Stock','Sold') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'In Stock',
  `branch` enum('KIMATHI','MOI') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `added_by` int DEFAULT NULL,
  `date_added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sold_by` int DEFAULT NULL,
  `sold_at` timestamp NULL DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `monitor_condition` enum('Ex-Uk','New','Refurbished') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `monitors`
--

INSERT INTO `monitors` (`serial_number`, `model_name`, `size_inches`, `status`, `branch`, `added_by`, `date_added`, `sold_by`, `sold_at`, `price`, `selling_price`, `monitor_condition`) VALUES
('M9JIOHG56DCGF', 'HP FRAMALESS 24 inch', 24, 'In Stock', 'KIMATHI', 1, '2026-06-18 11:46:40', NULL, NULL, 11000.00, NULL, NULL),
('M9OHG56DCGF', 'HP FRMALESS 24 inch', 24, 'Sold', 'MOI', 1, '2026-06-16 09:18:51', 7, '2026-06-18 11:48:46', 10000.00, 10000.00, NULL),
('SN001', 'Dell P2419H', 24, 'In Stock', 'MOI', 1, '2026-07-04 11:32:12', NULL, NULL, NULL, NULL, NULL),
('SN002', 'HP E243', 23, 'In Stock', 'MOI', 1, '2026-07-04 11:32:12', NULL, NULL, NULL, NULL, NULL),
('SN003', 'Lenovo ThinkVision', 27, 'In Stock', 'MOI', 1, '2026-07-04 11:32:12', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `phones`
--

CREATE TABLE `phones` (
  `serial_number` varchar(50) NOT NULL,
  `brand` varchar(50) DEFAULT NULL,
  `model` varchar(50) DEFAULT NULL,
  `ram` int DEFAULT NULL,
  `storage_capacity` int DEFAULT NULL,
  `status` enum('instock','sold') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'instock',
  `branch` enum('MOI','KIMATHI') DEFAULT NULL,
  `date_added` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `price` decimal(10,2) DEFAULT NULL,
  `date_sold` datetime DEFAULT NULL,
  `sold_by` int DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `price_updated_at` datetime DEFAULT NULL,
  `added_by` int DEFAULT NULL,
  `phone_condition` enum('New','Ex-Uk') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `phones`
--

INSERT INTO `phones` (`serial_number`, `brand`, `model`, `ram`, `storage_capacity`, `status`, `branch`, `date_added`, `price`, `date_sold`, `sold_by`, `selling_price`, `price_updated_at`, `added_by`, `phone_condition`) VALUES
('1', 'Apple', 'Iphone 15 pro', 4, 256, 'sold', 'MOI', '2026-06-20 11:06:25', 100000.00, NULL, NULL, NULL, NULL, NULL, NULL),
('PH000876', 'Apple', 'iPhone 15 Pro', 8, 256, 'instock', 'KIMATHI', '2026-07-03 13:44:39', NULL, NULL, NULL, NULL, '2026-07-03 17:22:19', 1, 'New'),
('PH000877', 'Samsung', 'Galaxy S24', 12, 512, 'instock', 'KIMATHI', '2026-07-03 13:44:39', 150000.00, NULL, NULL, NULL, NULL, 1, 'New'),
('PH000878', 'Nokia', 'G22', 4, 128, 'instock', 'KIMATHI', '2026-07-03 13:44:39', 25000.00, NULL, NULL, NULL, NULL, 1, 'Ex-Uk'),
('PH001', 'Apple', 'iPhone 15 Pro', 8, 256, 'instock', 'KIMATHI', '2026-07-03 13:43:44', 150000.00, NULL, NULL, NULL, '2026-07-03 17:21:37', 1, 'New'),
('PH002', 'Samsung', 'Galaxy S24', 12, 512, 'instock', 'MOI', '2026-07-03 13:43:44', 150000.00, NULL, NULL, NULL, NULL, 1, 'New'),
('PH003', 'Nokia', 'G22', 4, 128, 'instock', 'KIMATHI', '2026-07-03 13:43:44', 25000.00, NULL, NULL, NULL, NULL, 1, 'Ex-Uk');

-- --------------------------------------------------------

--
-- Table structure for table `printers`
--

CREATE TABLE `printers` (
  `serial_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `model_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `branch` enum('KIMATHI','MOI') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('In Stock','Sold') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'In Stock',
  `added_by` int DEFAULT NULL,
  `sold_by` int DEFAULT NULL,
  `date_added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_sold` timestamp NULL DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `printer_condition` enum('New','Ex-Uk','Refurbished') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `printers`
--

INSERT INTO `printers` (`serial_number`, `model_name`, `branch`, `status`, `added_by`, `sold_by`, `date_added`, `date_sold`, `price`, `selling_price`, `printer_condition`) VALUES
('PH78IIOG', 'EPSON', 'KIMATHI', 'In Stock', 1, NULL, '2025-12-14 14:05:41', NULL, NULL, NULL, NULL),
('PTNJGGDHH10', 'EPSON PRINTER', 'KIMATHI', 'Sold', 1, 7, '2025-12-14 18:46:38', '2026-06-18 05:38:17', 26000.00, 26000.00, NULL),
('PTNJGGDHH11', 'EPSON PRINTER', 'KIMATHI', 'In Stock', 1, NULL, '2025-12-14 18:46:38', NULL, NULL, NULL, NULL),
('PTNJGGDHH8', 'EPSON PRINTER', 'KIMATHI', 'In Stock', 1, NULL, '2025-12-14 18:46:38', NULL, NULL, NULL, NULL),
('PTNJGGDHH9', 'EPSON PRINTER', 'KIMATHI', 'In Stock', 1, NULL, '2025-12-14 18:46:38', NULL, NULL, NULL, NULL),
('TH5JJ897D', 'MECER PRINTER', 'KIMATHI', 'In Stock', 1, NULL, '2025-12-16 13:39:53', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `quotations`
--

CREATE TABLE `quotations` (
  `id` int NOT NULL,
  `quotation_number` varchar(20) NOT NULL,
  `client_name` varchar(100) NOT NULL,
  `client_phone` varchar(20) DEFAULT NULL,
  `client_box` varchar(100) DEFAULT NULL,
  `client_email` varchar(100) DEFAULT NULL,
  `quotation_date` date NOT NULL,
  `payment_due_date` date NOT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `vat` decimal(10,2) NOT NULL DEFAULT '0.00',
  `grand_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','sent','cancelled') DEFAULT 'draft',
  `user_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `quotations`
--

INSERT INTO `quotations` (`id`, `quotation_number`, `client_name`, `client_phone`, `client_box`, `client_email`, `quotation_date`, `payment_due_date`, `subtotal`, `vat`, `grand_total`, `status`, `user_id`, `created_at`, `updated_at`, `notes`) VALUES
(1, 'MC01', 'Vimark Tech', '', '', '', '2026-07-04', '2026-07-11', 210000.00, 11200.00, 221200.00, 'sent', 7, '2026-07-04 15:36:38', '2026-07-05 05:46:49', NULL),
(2, 'MC02', 'munene', '0711529618', '0711529618', 'victormunene207@gmail.com', '2026-07-04', '2026-07-11', 142500.00, 22800.00, 165300.00, 'sent', 7, '2026-07-04 21:03:10', '2026-07-05 06:57:03', NULL),
(3, 'MC03', 'munene', '0711529618', '0711529618', 'victormunene207@gmail.com', '2026-07-05', '2026-07-05', 10500.00, 1680.00, 12180.00, 'sent', 7, '2026-07-05 05:12:14', '2026-07-05 07:36:42', ''),
(4, 'MC04', 'munene', '', '', '', '2026-07-05', '2026-07-12', 15000.00, 1600.00, 16600.00, 'sent', 7, '2026-07-05 05:42:02', '2026-07-05 05:43:35', ''),
(5, 'MC05', 'Victor', '0703646909', '', '', '2026-07-05', '2026-07-12', 140000.00, 22400.00, 162400.00, 'sent', 7, '2026-07-05 06:11:15', '2026-07-05 06:16:35', 'The prices are exclusive VAT'),
(6, 'MC06', 'munene', '', '', '', '2026-07-05', '2026-07-08', 0.00, 0.00, 0.00, 'sent', 7, '2026-07-05 06:41:40', '2026-07-05 07:16:47', ''),
(7, 'MC07', 'Musili homes limited', '0711529618', '0711529618', 'victormunene207@gmail.com', '2026-07-05', '2026-07-12', 26000.00, 4160.00, 30160.00, 'sent', 7, '2026-07-05 06:58:20', '2026-07-05 07:00:24', 'The prices are exclusive VAT'),
(8, 'MC08', 'Munene victor', '', '', '', '2026-07-05', '2026-07-08', 26000.00, 0.00, 26000.00, 'sent', 7, '2026-07-05 07:02:14', '2026-07-05 07:03:16', ''),
(9, 'MC09', 'Musili Homes Limited', '0711529618', '', 'victormunene207@gmail.com', '2026-07-05', '2026-07-12', 265000.00, 42400.00, 307400.00, 'sent', 7, '2026-07-05 07:48:21', '2026-07-05 09:46:50', ''),
(10, 'MC10', 'Munene', '0711529618', '0711529618', 'victormunene207@gmail.com', '2026-07-05', '2026-07-12', 0.00, 0.00, 0.00, 'cancelled', 7, '2026-07-05 07:53:21', '2026-07-05 09:51:52', ''),
(11, 'MC11', 'peninah kalundi', '0727 733 795', '', '', '2026-07-05', '2026-07-08', 200000.00, 32000.00, 232000.00, 'cancelled', 7, '2026-07-05 07:54:30', '2026-07-05 09:55:23', ''),
(12, 'MC12', 'Munene victor', '0711529618', 'P.O. BOX 12-95400', 'victormunene207@gmail.com', '2026-07-05', '2026-07-08', 25000.00, 4000.00, 29000.00, 'sent', 7, '2026-07-05 07:57:15', '2026-07-05 08:09:50', 'The prices are exclusive VAT'),
(13, 'MC13', 'munene', '0711529618', '0711529618', 'victormunene207@gmail.com', '2026-07-05', '2026-07-08', 200000.00, 32000.00, 232000.00, 'cancelled', 7, '2026-07-05 10:00:18', '2026-07-05 10:14:01', '');

-- --------------------------------------------------------

--
-- Table structure for table `quotation_items`
--

CREATE TABLE `quotation_items` (
  `id` int NOT NULL,
  `quotation_id` int NOT NULL,
  `item_type` enum('device','monitor','printer','smartboard','ups','phone','accessory','graphic','hdd','ram_ssd','charger','manual') NOT NULL,
  `item_id` varchar(50) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `specs` text,
  `quantity` int NOT NULL DEFAULT '1',
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `vat_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `vat_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_with_vat` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `quotation_items`
--

INSERT INTO `quotation_items` (`id`, `quotation_id`, `item_type`, `item_id`, `description`, `specs`, `quantity`, `unit_price`, `total_price`, `vat_rate`, `vat_amount`, `total_with_vat`) VALUES
(1, 1, 'manual', '', 'Hp elitebook 840 g9', '8gb ram 256gb ssd i7 12th gen', 1, 70000.00, 70000.00, 0.00, 0.00, 70000.00),
(2, 1, 'manual', '', 'Hp elitebook 840 g9', '8gb ram 256gb ssd i7 12th gen', 1, 70000.00, 70000.00, 0.00, 0.00, 70000.00),
(3, 1, 'manual', '', 'Hp elitebook 840 g9', '8gb ram 256gb ssd i7 12th gen', 1, 70000.00, 70000.00, 16.00, 11200.00, 81200.00),
(4, 2, 'manual', '', 'Hp elitebook 840 g9', 'i7 12th gen 16gb Ram 512gb ssd', 1, 70000.00, 70000.00, 16.00, 11200.00, 81200.00),
(5, 2, 'manual', '', 'Hp elitebook 840 g9', 'i7 12th gen 16gb Ram 512gb ssd', 1, 70000.00, 70000.00, 16.00, 11200.00, 81200.00),
(6, 2, 'manual', '', 'power cable', '', 1, 500.00, 500.00, 16.00, 80.00, 580.00),
(7, 2, 'manual', '', 'Power Cable', '', 1, 500.00, 500.00, 16.00, 80.00, 580.00),
(8, 2, 'manual', '', 'Power Cable', '', 1, 500.00, 500.00, 16.00, 80.00, 580.00),
(9, 2, 'manual', '', 'Power Cable', '', 1, 500.00, 500.00, 16.00, 80.00, 580.00),
(10, 2, 'manual', '', 'Power Cable', '', 1, 500.00, 500.00, 16.00, 80.00, 580.00),
(13, 3, 'manual', '', 'power cable', '', 1, 500.00, 500.00, 16.00, 80.00, 580.00),
(14, 4, 'manual', '', 'Ram', '16GB', 1, 10000.00, 10000.00, 16.00, 1600.00, 11600.00),
(15, 4, 'manual', '', 'Ram', '8GB', 1, 5000.00, 5000.00, 0.00, 0.00, 5000.00),
(16, 5, 'manual', '', 'Hp Elitebook 840 G9 New', 'i7 12th Gen 16GB Ram 1TB', 1, 140000.00, 140000.00, 16.00, 22400.00, 162400.00),
(19, 7, 'manual', '', 'Epson printer L3250', '', 1, 26000.00, 26000.00, 16.00, 4160.00, 30160.00),
(20, 8, 'manual', '', 'Epson printer L3250', '', 1, 26000.00, 26000.00, 0.00, 0.00, 26000.00),
(21, 3, 'manual', '', 'Ram', '16GB', 1, 10000.00, 10000.00, 16.00, 1600.00, 11600.00),
(23, 9, 'manual', '', 'HP ELITEBOOK 840 G6', 'INTEL CORE I5-8TH GEN | 8GB RAM | SSD 256GB', 1, 35000.00, 35000.00, 16.00, 5600.00, 40600.00),
(25, 12, 'manual', '', 'EPSON PRINTER', '', 1, 25000.00, 25000.00, 16.00, 4000.00, 29000.00),
(26, 9, 'manual', '', 'SMART 75-inch', '75 inch', 1, 200000.00, 200000.00, 16.00, 32000.00, 232000.00),
(27, 9, 'manual', '', 'SSD SATA 512GB', '512GB', 1, 10000.00, 10000.00, 16.00, 1600.00, 11600.00),
(28, 9, 'manual', '', 'JBL essential 2 speaker', 'Qty available', 1, 20000.00, 20000.00, 16.00, 3200.00, 23200.00),
(29, 11, 'manual', '', 'SMART 75-inch', '75 inch', 1, 200000.00, 200000.00, 16.00, 32000.00, 232000.00),
(30, 13, 'manual', '', 'SMART 75-inch', '75 inch', 1, 200000.00, 200000.00, 16.00, 32000.00, 232000.00);

-- --------------------------------------------------------

--
-- Table structure for table `rams_ssds`
--

CREATE TABLE `rams_ssds` (
  `id` int NOT NULL,
  `category` enum('RAM','SSD') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `quantity` int NOT NULL,
  `branch` enum('KIMATHI','MOI') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `updated_by` int NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `storage` int NOT NULL,
  `added_by` int DEFAULT NULL,
  `date_added` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `price` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(10,2) GENERATED ALWAYS AS ((`quantity` * `price`)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rams_ssds`
--

INSERT INTO `rams_ssds` (`id`, `category`, `type`, `quantity`, `branch`, `updated_by`, `date_updated`, `storage`, `added_by`, `date_added`, `price`) VALUES
(6, 'SSD', 'SATA', 9, 'KIMATHI', 1, '2026-07-03 15:19:09', 512, 8, '2026-06-26 09:39:37', 10000.00),
(7, 'RAM', 'DDR3', 1, 'KIMATHI', 1, '2026-01-22 14:32:46', 16, 8, '2026-06-26 09:39:37', 10000.00),
(8, 'SSD', 'SATA', 14, 'MOI', 1, '2025-12-29 23:46:01', 512, NULL, '2026-06-26 09:39:37', NULL),
(9, 'RAM', 'PC4', 8, 'KIMATHI', 1, '2025-12-29 23:46:01', 16, NULL, '2026-06-26 09:39:37', 1000.00),
(10, 'RAM', 'PC4', 4, 'KIMATHI', 1, '2026-03-28 09:34:30', 8, NULL, '2026-06-26 09:39:37', 5000.00),
(11, 'RAM', 'DDR3', 16, 'MOI', 1, '2026-06-10 18:54:33', 16, NULL, '2026-06-26 09:39:37', NULL),
(12, 'RAM', 'PC4', 6, 'MOI', 1, '2025-12-29 23:46:01', 16, NULL, '2026-06-26 09:39:37', NULL),
(13, 'RAM', 'PC4', 1, 'MOI', 1, '2026-03-28 09:34:30', 8, NULL, '2026-06-26 09:39:37', NULL),
(14, 'SSD', 'SATA', 9, 'KIMATHI', 1, '2026-06-30 14:39:59', 256, NULL, '2026-06-27 15:01:31', 5000.00);

-- --------------------------------------------------------

--
-- Table structure for table `rams_ssds_logs`
--

CREATE TABLE `rams_ssds_logs` (
  `id` int NOT NULL,
  `ram_ssd_id` int NOT NULL,
  `category` enum('RAM','SSD') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `quantity_given` int NOT NULL,
  `given_to` int NOT NULL,
  `given_by` int NOT NULL,
  `branch` enum('KIMATHI','MOI') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `date_given` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `storage` int NOT NULL,
  `status` enum('pending_sale','sold','returned') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'pending_sale',
  `sale_item_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rams_ssds_logs`
--

INSERT INTO `rams_ssds_logs` (`id`, `ram_ssd_id`, `category`, `type`, `quantity_given`, `given_to`, `given_by`, `branch`, `date_given`, `storage`, `status`, `sale_item_id`) VALUES
(1, 6, 'SSD', 'SATA', 5, 7, 8, 'KIMATHI', '2025-12-06 22:00:25', 512, 'returned', NULL),
(2, 6, 'SSD', 'SATA', 1, 7, 1, 'KIMATHI', '2025-12-07 15:54:41', 512, 'sold', NULL),
(3, 6, 'SSD', 'SATA', 2, 7, 1, 'KIMATHI', '2025-12-09 05:28:43', 512, 'sold', NULL),
(4, 7, 'RAM', 'DDR3', 1, 7, 1, 'KIMATHI', '2025-12-15 18:41:23', 16, 'sold', NULL),
(5, 6, 'SSD', 'SATA', 2, 7, 8, 'KIMATHI', '2025-12-28 14:17:01', 512, 'sold', NULL),
(6, 11, 'RAM', 'DDR3', 2, 7, 1, 'MOI', '2026-06-10 15:54:33', 16, 'sold', NULL),
(7, 6, 'SSD', 'SATA', 2, 8, 1, 'KIMATHI', '2026-06-26 10:46:33', 512, 'returned', NULL),
(8, 6, 'SSD', 'SATA', 2, 7, 1, 'KIMATHI', '2026-06-26 15:20:09', 512, 'sold', NULL),
(9, 6, 'SSD', 'SATA', 1, 7, 8, 'KIMATHI', '2026-06-27 10:44:52', 512, 'returned', NULL),
(10, 14, 'SSD', 'SATA', 1, 7, 1, 'KIMATHI', '2026-06-30 11:39:59', 256, 'sold', 22),
(11, 6, 'SSD', 'SATA', 1, 7, 1, 'KIMATHI', '2026-07-03 06:01:51', 512, 'pending_sale', NULL),
(12, 6, 'SSD', 'SATA', 1, 7, 1, 'KIMATHI', '2026-07-03 12:19:09', 512, 'pending_sale', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `registration_codes`
--

CREATE TABLE `registration_codes` (
  `id` int NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('super_admin','inventory_admin','technician','software','sales','manager') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_used` tinyint(1) DEFAULT '0',
  `used_by` int DEFAULT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registration_codes`
--

INSERT INTO `registration_codes` (`id`, `code`, `email`, `role`, `created_by`, `created_at`, `is_used`, `used_by`, `used_at`, `expiry`) VALUES
(1, '167835', 'victormunene207@gmail.com', 'manager', 1, '2026-04-14 20:06:17', 0, NULL, NULL, NULL),
(4, '297144', 'vdebmunene207@gmail.com', 'manager', 1, '2026-04-22 19:52:46', 1, NULL, NULL, '2026-04-22 23:11:43'),
(5, '922516', 'vdebmunene207@gmail.com', 'manager', 1, '2026-06-08 10:17:40', 0, NULL, NULL, '2026-06-08 13:37:36');

-- --------------------------------------------------------

--
-- Table structure for table `repairs`
--

CREATE TABLE `repairs` (
  `id` int NOT NULL,
  `serial_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'Not provided',
  `problem_description` text NOT NULL,
  `added_by` int NOT NULL,
  `given_by` int DEFAULT NULL,
  `branch` enum('KIMATHI','MOI') NOT NULL,
  `fix_status` enum('Not Fixed','Fixed','pending') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'pending',
  `date_added` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `date_fixed` timestamp NULL DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `model_name` varchar(100) DEFAULT NULL,
  `client_name` varchar(50) DEFAULT NULL,
  `client_phone` varchar(50) DEFAULT NULL,
  `client_email` varchar(50) DEFAULT NULL,
  `sales_person` int DEFAULT NULL,
  `parts_used` varchar(100) DEFAULT NULL,
  `repair_cost` decimal(10,2) DEFAULT NULL,
  `source_device` enum('instock','return','client') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `repairs`
--

INSERT INTO `repairs` (`id`, `serial_number`, `problem_description`, `added_by`, `given_by`, `branch`, `fix_status`, `date_added`, `date_fixed`, `category_id`, `model_name`, `client_name`, `client_phone`, `client_email`, `sales_person`, `parts_used`, `repair_cost`, `source_device`) VALUES
(1, 'TGBHJJRQW4', 'Faulty keyboard', 4, 1, 'KIMATHI', 'Fixed', '2025-12-16 18:34:43', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, '5CG09OPL34', 'faulty keyboard', 16, 1, 'KIMATHI', 'Fixed', '2025-12-18 02:16:11', '2025-12-18 02:16:32', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'MXHT89YU7', 'Faulty keyboard', 14, 8, 'MOI', 'Fixed', '2026-01-02 13:00:33', '2026-01-02 13:00:54', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'MXHT89YU7', 'Speaker issue', 14, 8, 'MOI', 'Fixed', '2026-01-02 13:30:36', '2026-01-02 13:31:10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'MXFGHGFH', 'FAULTY KEYBOARD', 4, 1, 'KIMATHI', 'Fixed', '2026-03-03 15:44:11', '2026-03-03 15:45:10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, '5CG12465TG09', 'Battery issue', 4, 7, 'KIMATHI', 'Fixed', '2026-07-05 15:03:26', '2026-07-05 18:37:47', 1, 'HP EliteBook 840 G6', NULL, NULL, NULL, NULL, 'New battery', NULL, NULL),
(7, '5CG09OZXE36', 'broken screnn', 4, NULL, 'KIMATHI', 'Not Fixed', '2026-07-05 15:39:58', NULL, 1, 'HP ELITEBOOK 840 G6', 'Munene victor', '0703646909', NULL, 7, NULL, NULL, NULL),
(8, NULL, 'Faulty battery', 4, NULL, 'KIMATHI', 'Fixed', '2026-07-05 15:59:10', '2026-07-05 18:38:37', NULL, 'HP ELITEBOOK 840 G6', 'Victor', '0711529618', 'victormunene207@gmail.com', NULL, 'New battery', 4000.00, 'client'),
(9, NULL, 'Not powering', 4, NULL, 'KIMATHI', 'Fixed', '2026-07-05 16:06:35', '2026-07-05 18:39:41', NULL, 'HP ELITEDESK 705 G5', 'munene', '0711529618', NULL, NULL, 'None', 2000.00, 'client'),
(10, NULL, 'Not powering', 4, NULL, 'KIMATHI', 'Fixed', '2026-07-05 16:20:05', '2026-07-05 16:41:22', NULL, 'HP ELITEDESK 705 G5', 'Victor', '0711529618', 'victormunene207@gmail.com', NULL, 'new battery', 3500.00, 'client'),
(11, '5CG0302X7Y', 'faulty keyboard', 4, 8, 'KIMATHI', 'Fixed', '2026-07-05 18:05:20', '2026-07-05 18:16:08', 1, 'HP ELITEBOOK 745 G6', NULL, NULL, NULL, NULL, 'new keyboard', NULL, 'instock'),
(12, '5CG0302X7Y', 'not powering', 4, 8, 'KIMATHI', 'Fixed', '2026-07-05 18:19:37', '2026-07-05 18:35:31', 1, 'HP ELITEBOOK 745 G6', NULL, NULL, NULL, NULL, 'None', NULL, 'instock'),
(13, 'TGBHJJRQW4', 'Broken screen', 4, NULL, 'KIMATHI', 'pending', '2026-07-05 19:04:38', NULL, 2, 'HP ELITEBOOK 840 G6', 'munene', '0703646909', 'victormunene207@gmail.com', NULL, NULL, NULL, 'client'),
(14, '5CGHJJB3623W', 'Casing replacement', 4, NULL, 'KIMATHI', 'pending', '2026-07-05 19:06:20', NULL, NULL, 'HP PRO ONE ALL IN ONE', 'Victor', '0711529618', 'vdebmunene207@gmail.com', NULL, NULL, NULL, 'client');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int NOT NULL,
  `client_name` varchar(100) DEFAULT NULL,
  `client_phone` varchar(20) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `sale_status` enum('active','completed','cancelled') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  `sold_by` int DEFAULT NULL,
  `payment_method` enum('cash','mpesa-till','mpesa-pochi','bank-transfer') DEFAULT NULL,
  `payment_status` enum('paid','unpaid') DEFAULT NULL,
  `completion_status` enum('pending','Completed') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `client_name`, `client_phone`, `total_amount`, `sale_status`, `created_at`, `completed_at`, `sold_by`, `payment_method`, `payment_status`, `completion_status`) VALUES
(1, 'victor', '0711529618', 105000.00, 'cancelled', '2026-06-29 08:07:44', '2026-06-29 16:07:42', 7, NULL, NULL, 'pending'),
(2, 'Munene', '0711529618', 0.00, 'cancelled', '2026-06-29 10:26:24', '2026-06-29 15:19:11', 7, NULL, NULL, 'pending'),
(3, 'Munene victor', '0703646909', 0.00, 'completed', '2026-06-29 10:33:53', '2026-06-29 15:16:18', 7, 'mpesa-till', 'paid', 'Completed'),
(4, NULL, NULL, NULL, 'cancelled', '2026-06-29 10:44:43', '2026-06-29 15:21:50', 7, NULL, NULL, NULL),
(5, 'Munene victor', '0703646909', 30000.00, 'cancelled', '2026-06-29 13:09:01', '2026-06-29 16:31:40', 7, NULL, NULL, 'pending'),
(6, 'Munene victor', '0703646909', NULL, 'completed', '2026-06-29 13:50:15', '2026-06-29 16:51:55', 8, 'mpesa-till', 'paid', 'Completed'),
(7, NULL, NULL, NULL, 'cancelled', '2026-06-29 13:55:45', '2026-06-29 17:05:36', 8, NULL, NULL, NULL),
(8, NULL, NULL, NULL, 'cancelled', '2026-06-29 14:20:14', '2026-06-29 18:02:08', 8, NULL, NULL, NULL),
(9, 'Munene victor', '0711529618', 0.00, 'cancelled', '2026-06-29 15:22:10', '2026-06-29 18:37:29', 8, NULL, NULL, 'pending'),
(10, NULL, NULL, 34000.00, 'completed', '2026-06-29 15:37:39', '2026-06-30 18:02:12', 7, 'mpesa-till', 'paid', 'Completed'),
(11, 'Munene victor', '0711529618', 223000.00, 'cancelled', '2026-06-29 15:38:53', '2026-06-30 13:59:18', 8, NULL, NULL, 'pending'),
(12, NULL, NULL, NULL, 'cancelled', '2026-06-30 10:57:43', '2026-06-30 14:00:47', 7, NULL, NULL, NULL),
(13, NULL, NULL, NULL, 'cancelled', '2026-06-30 10:57:43', '2026-06-30 18:01:55', 8, NULL, NULL, NULL),
(14, NULL, NULL, NULL, 'cancelled', '2026-06-30 10:58:02', '2026-06-30 14:00:19', 7, NULL, NULL, NULL),
(15, NULL, NULL, NULL, 'cancelled', '2026-06-30 10:58:02', '2026-06-30 18:01:30', 8, NULL, NULL, NULL),
(16, 'Munene', '0711529618', 81200.00, 'cancelled', '2026-07-01 11:33:45', '2026-07-02 11:17:52', 7, NULL, NULL, 'pending'),
(17, 'Munene victor', '0711529618', 80000.00, 'completed', '2026-07-01 15:22:45', '2026-07-06 00:19:12', 8, 'mpesa-till', 'paid', 'Completed'),
(18, 'Munene', '0711529618', NULL, 'cancelled', '2026-07-02 08:18:16', '2026-07-02 11:19:12', 7, NULL, NULL, NULL),
(19, 'Musili Homes', '0711529618', NULL, 'cancelled', '2026-07-02 08:20:11', '2026-07-02 11:32:56', 7, NULL, NULL, NULL),
(20, NULL, NULL, 10800.00, 'cancelled', '2026-07-02 08:47:55', '2026-07-02 14:48:16', 7, NULL, NULL, 'pending'),
(21, 'Musili Homes Limited', '0711529618', 10800.00, 'completed', '2026-07-02 11:48:57', '2026-07-05 23:43:52', 7, 'mpesa-till', 'paid', 'Completed'),
(22, 'Munene', '0711529618', 1200.00, 'completed', '2026-07-05 21:32:12', '2026-07-06 00:32:49', 7, 'cash', 'paid', 'Completed'),
(23, 'Musili Homes Limited', '0711529618', 200000.00, 'completed', '2026-07-05 21:35:11', '2026-07-06 00:35:43', 7, 'mpesa-till', 'paid', 'Completed'),
(24, 'Victor', '0703646909', 35000.00, 'completed', '2026-07-05 21:38:42', '2026-07-06 00:39:02', 7, 'mpesa-pochi', 'paid', 'Completed');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int NOT NULL,
  `sale_id` int DEFAULT NULL,
  `item_type` enum('device','monitors','printers','smartboards','phones','ups','ram','ssd','charger','accessory','hdd','graphic') DEFAULT NULL,
  `item_id` varchar(50) DEFAULT NULL,
  `description` varchar(200) DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(10,2) GENERATED ALWAYS AS ((`quantity` * `unit_price`)) STORED,
  `sales_person` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `item_type`, `item_id`, `description`, `quantity`, `unit_price`, `sales_person`, `created_at`) VALUES
(3, 3, 'device', '5CG09OZXE36', 'HP ELITEBOOK 840 G6 | INTEL CORE I5-8TH GEN | 16GB RAM | SSD 512GB | None | Non-touch', 1, 40000.00, 7, '2026-07-05 21:23:44'),
(22, 10, 'ssd', '14', 'SSD | SATA | 256GB', 1, 5000.00, 7, '2026-07-05 21:23:44'),
(24, 10, 'hdd', '1', 'SATA | 2TB', 1, 15000.00, 7, '2026-07-05 21:23:44'),
(27, 10, 'charger', '10', 'HP Blue Pin 65W | New', 3, 3500.00, 7, '2026-07-05 21:23:44'),
(28, 10, 'charger', '10', 'HP Blue Pin 65W | New', 1, 3500.00, 7, '2026-07-05 21:23:44'),
(36, 21, 'accessory', '4', 'power cable | KIMATHI | Qty: 18', 18, 600.00, 7, '2026-07-05 21:23:44'),
(37, 17, 'accessory', '2', 'JBL essential 2 speaker | KIMATHI | display', 4, 20000.00, 8, '2026-07-05 21:23:44'),
(38, 22, 'accessory', '4', 'power cable | KIMATHI | Qty: 2', 2, 600.00, 7, '2026-07-05 21:32:19'),
(39, 23, 'smartboards', 'SB001', 'SMART 75-inch | 75 inch', 1, 200000.00, 7, '2026-07-05 21:35:20'),
(40, 24, 'device', '5CG0302X7Y', 'HP ELITEBOOK 745 G6 | AMD RYZEN 7 PRO 3700u | 16GB RAM | SSD 256GB | 2GB AMD RADEON VEGA 11 | Non-touch', 1, 35000.00, 7, '2026-07-05 21:38:55');

-- --------------------------------------------------------

--
-- Table structure for table `smartboards`
--

CREATE TABLE `smartboards` (
  `serial_number` varchar(50) NOT NULL,
  `model` varchar(50) NOT NULL,
  `size_inches` int NOT NULL,
  `date_added` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('instock','sold') DEFAULT 'instock',
  `added_by` int DEFAULT NULL,
  `branch` enum('KIMATHI','MOI') DEFAULT NULL,
  `place` enum('store','warehouse','sold','display') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `sold_at` datetime DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `sold_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `smartboards`
--

INSERT INTO `smartboards` (`serial_number`, `model`, `size_inches`, `date_added`, `status`, `added_by`, `branch`, `place`, `price`, `sold_at`, `selling_price`, `sold_by`) VALUES
('SB001', 'SMART 75-inch', 75, '2026-05-30 08:07:14', 'sold', 1, 'KIMATHI', 'store', 200000.00, '2026-07-06 00:35:20', 200000.00, 7),
('SB002', 'ViewSonic 65-inch', 65, '2026-06-20 08:07:14', 'instock', 1, 'MOI', 'warehouse', NULL, NULL, NULL, NULL),
('SB0025TY', 'Onescreen 5', 65, '2026-03-16 08:11:59', 'instock', 1, 'MOI', 'warehouse', 150000.00, NULL, NULL, NULL),
('SB002S4R', 'SMART 75-inch', 75, '2026-06-20 08:11:59', 'sold', 1, 'KIMATHI', 'store', 170000.00, '2026-06-29 09:33:43', 170000.00, 7),
('SM5CG09OZXE60', 'Onescreen 5', 65, '2026-03-24 18:44:40', 'sold', 1, 'KIMATHI', 'store', 80000.00, '2026-06-27 17:11:18', 80000.00, 7),
('SM7UYYG3VV', 'SMART', 75, '2026-06-17 13:05:45', 'sold', 1, 'KIMATHI', NULL, 200000.00, '2026-06-18 12:34:13', 2050000.00, 7),
('SMI0OUJ', 'SMART 6065', 65, '2026-06-20 08:23:16', 'sold', 1, 'MOI', 'sold', 150000.00, '2026-07-02 10:05:34', 150000.00, NULL),
('SMJJ8Y6UH', 'Onescreen 5', 65, '2026-07-04 07:24:04', 'instock', 1, 'KIMATHI', 'store', 150000.00, NULL, NULL, NULL),
('SRTF556DCGF', 'Onescreen 5', 65, '2026-07-04 07:24:46', 'instock', 1, 'MOI', 'store', 150000.00, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sold_accessories`
--

CREATE TABLE `sold_accessories` (
  `id` int NOT NULL,
  `accessory_id` int NOT NULL,
  `accessory_name` varchar(100) NOT NULL,
  `quantity` int NOT NULL,
  `selling_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) GENERATED ALWAYS AS ((`quantity` * `selling_price`)) STORED,
  `branch` enum('MOI','KIMATHI') DEFAULT NULL,
  `sold_by` int NOT NULL,
  `date_sold` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `sale_item_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sold_accessories`
--

INSERT INTO `sold_accessories` (`id`, `accessory_id`, `accessory_name`, `quantity`, `selling_price`, `branch`, `sold_by`, `date_sold`, `sale_item_id`) VALUES
(1, 1, 'DELL optical mouse', 1, 1500.00, 'KIMATHI', 7, '2026-06-18 11:43:29', NULL),
(2, 1, 'Dell optical mouce', 3, 1500.00, 'MOI', 7, '2026-06-18 13:11:01', NULL),
(9, 4, 'power cable', 18, 600.00, 'KIMATHI', 4, '2026-07-05 20:43:39', 36),
(10, 2, 'JBL essential 2 speaker', 4, 20000.00, 'KIMATHI', 4, '2026-07-05 21:19:00', 37),
(11, 4, 'power cable', 2, 600.00, 'KIMATHI', 7, '2026-07-05 21:32:19', 38);

-- --------------------------------------------------------

--
-- Table structure for table `sold_chargers`
--

CREATE TABLE `sold_chargers` (
  `id` int NOT NULL,
  `charger_id` int NOT NULL,
  `charger_type` varchar(100) NOT NULL,
  `charger_condition` enum('New','ex-uk') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `quantity` int NOT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `branch` enum('MOI','KIMATHI') DEFAULT NULL,
  `sold_by` int DEFAULT NULL,
  `date_sold` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `total_price` decimal(10,2) GENERATED ALWAYS AS ((`quantity` * `selling_price`)) STORED,
  `sale_item_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sold_chargers`
--

INSERT INTO `sold_chargers` (`id`, `charger_id`, `charger_type`, `charger_condition`, `quantity`, `selling_price`, `branch`, `sold_by`, `date_sold`, `sale_item_id`) VALUES
(1, 9, 'Hp Blue Pin', 'New', 2, 3000.00, 'KIMATHI', 7, '2026-06-18 11:02:17', NULL),
(2, 10, 'Hp bluepin 65W', 'New', 20, 3500.00, 'KIMATHI', 7, '2026-06-22 05:51:19', NULL),
(5, 10, 'HP Blue Pin 65W', 'New', 3, 3500.00, 'KIMATHI', 4, '2026-06-30 12:30:14', 27),
(6, 10, 'HP Blue Pin 65W', 'New', 1, 3500.00, 'KIMATHI', 4, '2026-06-30 12:58:26', 28);

-- --------------------------------------------------------

--
-- Table structure for table `sold_graphics_cards`
--

CREATE TABLE `sold_graphics_cards` (
  `id` int NOT NULL,
  `graphic_card_id` int DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `storage_capacity` int DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(10,2) GENERATED ALWAYS AS ((`quantity` * `selling_price`)) STORED,
  `date_sold` datetime DEFAULT NULL,
  `sold_by` int DEFAULT NULL,
  `branch` enum('MOI','KIMATHI') DEFAULT NULL,
  `sale_item_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sold_graphics_cards`
--

INSERT INTO `sold_graphics_cards` (`id`, `graphic_card_id`, `type`, `storage_capacity`, `quantity`, `selling_price`, `date_sold`, `sold_by`, `branch`, `sale_item_id`) VALUES
(1, 1, 'NVIDIA QUADRO P2000', 5, 3, 15000.00, '2026-06-20 14:38:43', 7, 'KIMATHI', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sold_hdds`
--

CREATE TABLE `sold_hdds` (
  `id` int NOT NULL,
  `hdd_id` int DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `storage` varchar(50) DEFAULT NULL,
  `branch` enum('MOI','KIMATHI') DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(10,2) GENERATED ALWAYS AS ((`quantity` * `selling_price`)) STORED,
  `date_sold` datetime DEFAULT NULL,
  `sold_by` int DEFAULT NULL,
  `sale_item_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sold_hdds`
--

INSERT INTO `sold_hdds` (`id`, `hdd_id`, `type`, `storage`, `branch`, `quantity`, `selling_price`, `date_sold`, `sold_by`, `sale_item_id`) VALUES
(1, 1, 'SATA', '2TB', 'KIMATHI', 2, 15000.00, '2026-06-20 13:39:49', 7, NULL),
(2, 1, 'SATA', '2TB', 'KIMATHI', 1, 15000.00, '2026-06-26 15:53:56', 7, NULL),
(3, 2, 'SATA', '500GB', 'MOI', 3, 4000.00, '2026-06-29 17:58:13', 8, NULL),
(7, 1, 'SATA', '2TB', 'KIMATHI', 1, 15000.00, '2026-06-30 15:11:46', 4, 24);

-- --------------------------------------------------------

--
-- Table structure for table `sold_rams_ssds`
--

CREATE TABLE `sold_rams_ssds` (
  `id` int NOT NULL,
  `ram_ssd_id` int DEFAULT NULL,
  `category` enum('RAM','SSD') DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `storage` int DEFAULT NULL,
  `branch` enum('MOI','KIMATHI') DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(10,2) GENERATED ALWAYS AS ((`quantity` * `selling_price`)) STORED,
  `date_sold` datetime DEFAULT NULL,
  `sold_by` int DEFAULT NULL,
  `sale_item_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sold_rams_ssds`
--

INSERT INTO `sold_rams_ssds` (`id`, `ram_ssd_id`, `category`, `type`, `storage`, `branch`, `quantity`, `selling_price`, `date_sold`, `sold_by`, `sale_item_id`) VALUES
(1, 6, 'SSD', 'SATA', 512, 'KIMATHI', 2, 10000.00, '2026-06-20 18:34:12', 7, NULL),
(2, 6, 'SSD', 'SATA', 512, 'KIMATHI', 2, 10000.00, '2026-06-26 18:36:39', 7, NULL),
(3, 11, 'RAM', 'DDR3', 16, 'MOI', 2, 10000.00, '2026-06-26 18:39:17', 7, NULL),
(4, 6, 'SSD', 'SATA', 512, 'KIMATHI', 2, 10000.00, '2026-06-26 18:43:00', 7, NULL),
(5, 6, 'SSD', 'SATA', 512, 'KIMATHI', 2, 10000.00, '2026-06-26 18:51:59', 7, NULL),
(8, 14, 'SSD', 'SATA', 256, 'KIMATHI', 1, 5000.00, '2026-06-30 14:55:05', 7, 22);

-- --------------------------------------------------------

--
-- Table structure for table `ups`
--

CREATE TABLE `ups` (
  `serial_number` varchar(100) NOT NULL,
  `model` varchar(50) NOT NULL,
  `capacity` int NOT NULL,
  `status` enum('instock','sold') DEFAULT 'instock',
  `added_by` int DEFAULT NULL,
  `date_added` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `price` decimal(10,2) DEFAULT NULL,
  `sold_by` int DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `date_sold` datetime DEFAULT NULL,
  `branch` enum('MOI','KIMATHI') DEFAULT NULL,
  `ups_condition` enum('New','Ex-UK','Refurbished') DEFAULT 'New',
  `price_updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `ups`
--

INSERT INTO `ups` (`serial_number`, `model`, `capacity`, `status`, `added_by`, `date_added`, `price`, `sold_by`, `selling_price`, `date_sold`, `branch`, `ups_condition`, `price_updated_at`) VALUES
('APC-001', 'APC Back-UPS', 1500, 'instock', 1, '2026-07-03 13:16:24', 20000.00, NULL, NULL, NULL, 'MOI', 'Ex-UK', '2026-07-03 17:32:30'),
('UP68GH5', 'MECCER', 1200, 'instock', 1, '2026-07-03 12:46:07', 15000.00, NULL, NULL, NULL, 'KIMATHI', 'New', '2026-07-03 17:31:59'),
('UPKNM9JHFH', 'MECCER UPS', 2600, 'instock', 1, '2026-06-27 10:02:37', NULL, NULL, NULL, NULL, 'KIMATHI', 'New', NULL),
('UPS-003', 'DELTA UPS', 3000, 'instock', 1, '2026-07-03 13:16:24', 35000.00, NULL, NULL, NULL, 'KIMATHI', 'Refurbished', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('super_admin','inventory_admin','technician','maintenance','sales','manager','cashier','software') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `full_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `branch` enum('KIMATHI','MOI') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `failed_attempts` int DEFAULT '0',
  `account_locked_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `username`, `password`, `role`, `is_active`, `full_name`, `created_at`, `last_login`, `branch`, `failed_attempts`, `account_locked_until`) VALUES
(1, 'victormunene207@gmail.com', 'vic', '$2a$12$q5sPMKAfYhS0AMXRm6BpI.W7flz8n0wmcUbBJ45BAnOa8BsxgYKSK', 'super_admin', 1, 'Victor Munene', '2025-12-03 11:42:31', '2026-07-05 23:26:40', 'KIMATHI', 0, NULL),
(4, 'munene@gmail.com', 'Vdeb', '$2y$10$m9PnQbD9v1ZY50s14T45MOV2n4bdaty74/2Y8rBlOuRY56.4OdlGi', 'cashier', 1, 'Munene vicky', '2025-12-03 12:39:40', '2026-07-05 19:50:14', 'KIMATHI', 0, NULL),
(7, 'peninahkalundi@gmail.com', 'pesh', '$2y$10$cS2ZivexM3srJGrkihxPnumRp0RNDgdHDTX8bkGuUXphAbd9ZJSc.', 'sales', 1, 'Peninah Kalundi', '2025-12-04 18:01:17', '2026-07-05 21:32:00', 'KIMATHI', 0, NULL),
(8, 'munene23.v@student.cuk.ac.ke', 'syovata', '$2a$12$/VBwYZBkXo6VuCCFIPFWDeLBJ5Ewbo.Z.lALJgWmckCUeT5PFmJ5.', 'inventory_admin', 1, 'Victor Syovata', '2025-12-05 22:19:21', '2026-07-05 23:05:10', 'KIMATHI', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_devices`
--

CREATE TABLE `user_devices` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `device_id` varchar(255) NOT NULL,
  `device_name` varchar(255) DEFAULT NULL,
  `browser_info` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `last_seen` datetime DEFAULT NULL,
  `first_seen` datetime DEFAULT NULL,
  `times_seen` int DEFAULT '1',
  `is_verified` tinyint(1) DEFAULT '0',
  `verification_code` varchar(10) DEFAULT NULL,
  `code_expires_at` datetime DEFAULT NULL,
  `failed_attempts` int DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user_devices`
--

INSERT INTO `user_devices` (`id`, `user_id`, `device_id`, `device_name`, `browser_info`, `ip_address`, `last_seen`, `first_seen`, `times_seen`, `is_verified`, `verification_code`, `code_expires_at`, `failed_attempts`, `locked_until`, `created_at`) VALUES
(1, 1, '9434825922fdab2488a47e149a22fbf60d1d4c55e549294e745f9529fc04e74b', 'Windows PC - Google Chrome', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '::1', '2026-07-06 02:26:40', '2026-07-06 01:27:11', 9, 1, NULL, NULL, 0, NULL, '2026-07-05 22:27:11'),
(2, 1, '7bf794ddcdd385a20dcfe4191602f784e955a98a8d503d652715ca10e7c188e2', 'Android Device - Google Chrome', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '192.168.0.127', '2026-07-06 01:44:12', '2026-07-06 01:31:15', 2, 1, NULL, NULL, 0, NULL, '2026-07-05 22:31:15'),
(3, 8, '7bf794ddcdd385a20dcfe4191602f784e955a98a8d503d652715ca10e7c188e2', 'Android Device - Google Chrome', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '192.168.0.127', '2026-07-06 01:58:37', '2026-07-06 01:56:14', 2, 1, NULL, NULL, 0, NULL, '2026-07-05 22:56:14'),
(4, 8, '9434825922fdab2488a47e149a22fbf60d1d4c55e549294e745f9529fc04e74b', 'Windows PC - Google Chrome', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '::1', '2026-07-06 02:05:10', '2026-07-06 02:03:33', 2, 1, NULL, NULL, 0, NULL, '2026-07-05 23:03:33');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accessories`
--
ALTER TABLE `accessories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_accessories_users` (`added_by`),
  ADD KEY `fk_updated_accessories` (`updated_by`);

--
-- Indexes for table `accessories_logs`
--
ALTER TABLE `accessories_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_accessories_id` (`accessory_id`),
  ADD KEY `fk_given_by_id` (`given_by`),
  ADD KEY `fk_given_to_id` (`given_to`),
  ADD KEY `fk_accessories_logs_sale_items` (`sale_item_id`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `chargers`
--
ALTER TABLE `chargers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `fk_chargers_added_by` (`added_by`);

--
-- Indexes for table `charger_logs`
--
ALTER TABLE `charger_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `given_by` (`given_by`),
  ADD KEY `given_to` (`given_to`),
  ADD KEY `fk_charger_logs_chargers` (`charger_id`),
  ADD KEY `fk_charger_logs_sale_items` (`sale_item_id`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sales_person_clients` (`sales_person`);

--
-- Indexes for table `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`serial_number`),
  ADD UNIQUE KEY `serial_number` (`serial_number`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `added_by` (`added_by`),
  ADD KEY `sold_by` (`sold_by`);

--
-- Indexes for table `devices_logs`
--
ALTER TABLE `devices_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_category_id_devices_logs` (`category_id`),
  ADD KEY `fk_taken_by_devices_logs` (`given_by`),
  ADD KEY `fk_given_to_device_devices_logs` (`given_to`),
  ADD KEY `fk_taken_by_devices` (`taken_by`);

--
-- Indexes for table `device_updates`
--
ALTER TABLE `device_updates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `serial_number` (`serial_number`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_created_by_expenses` (`created_by`);

--
-- Indexes for table `graphic_cards`
--
ALTER TABLE `graphic_cards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_added_by_user` (`added_by`);

--
-- Indexes for table `graphic_cards_logs`
--
ALTER TABLE `graphic_cards_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_given_by_graphics_logs` (`given_by`),
  ADD KEY `fk_given_to_graphics_logs` (`given_to`),
  ADD KEY `fk_grahpic_card_id_logs` (`graphic_card_id`),
  ADD KEY `fk_graphic_cards_logs_sale_items` (`sale_item_id`);

--
-- Indexes for table `hdds`
--
ALTER TABLE `hdds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_added_by` (`added_by`),
  ADD KEY `fk_updated_by` (`updated_by`);

--
-- Indexes for table `hdd_logs`
--
ALTER TABLE `hdd_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_hdd_logs` (`hdd_id`),
  ADD KEY `fk_logs_given_to` (`given_to`),
  ADD KEY `fk_logs_given_by` (`given_by`),
  ADD KEY `fk_hdd_logs_sale_items` (`sale_item_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `quotation_id` (`quotation_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `invoice_payments`
--
ALTER TABLE `invoice_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `maintenance`
--
ALTER TABLE `maintenance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_serial` (`device_serial`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `monitors`
--
ALTER TABLE `monitors`
  ADD PRIMARY KEY (`serial_number`),
  ADD KEY `added_by` (`added_by`),
  ADD KEY `sold_by` (`sold_by`);

--
-- Indexes for table `phones`
--
ALTER TABLE `phones`
  ADD PRIMARY KEY (`serial_number`),
  ADD KEY `fk_phones_users` (`sold_by`),
  ADD KEY `fk_phones_added_by` (`added_by`);

--
-- Indexes for table `printers`
--
ALTER TABLE `printers`
  ADD PRIMARY KEY (`serial_number`),
  ADD KEY `added_by` (`added_by`),
  ADD KEY `sold_by` (`sold_by`);

--
-- Indexes for table `quotations`
--
ALTER TABLE `quotations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `quotation_number` (`quotation_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `quotation_items`
--
ALTER TABLE `quotation_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quotation_id` (`quotation_id`);

--
-- Indexes for table `rams_ssds`
--
ALTER TABLE `rams_ssds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `fk_rams_ssds_added_by` (`added_by`);

--
-- Indexes for table `rams_ssds_logs`
--
ALTER TABLE `rams_ssds_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ram_ssd_id` (`ram_ssd_id`),
  ADD KEY `given_to` (`given_to`),
  ADD KEY `given_by` (`given_by`),
  ADD KEY `fk_rams_ssds_logs_sale_items` (`sale_item_id`);

--
-- Indexes for table `registration_codes`
--
ALTER TABLE `registration_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `used_by` (`used_by`);

--
-- Indexes for table `repairs`
--
ALTER TABLE `repairs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `serial_number` (`serial_number`),
  ADD KEY `added_by` (`added_by`),
  ADD KEY `given_by` (`given_by`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `sales_person` (`sales_person`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_solb_by_sales` (`sold_by`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sales_saleitems` (`sale_id`),
  ADD KEY `saleitems_saleperson` (`sales_person`);

--
-- Indexes for table `smartboards`
--
ALTER TABLE `smartboards`
  ADD PRIMARY KEY (`serial_number`),
  ADD KEY `fk_users_smartboards` (`added_by`),
  ADD KEY `fk_sales_smartboards` (`sold_by`);

--
-- Indexes for table `sold_accessories`
--
ALTER TABLE `sold_accessories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_accessories_sold` (`accessory_id`),
  ADD KEY `fk_sold_accessories_users` (`sold_by`),
  ADD KEY `fk_sold_accessories_sale_items` (`sale_item_id`);

--
-- Indexes for table `sold_chargers`
--
ALTER TABLE `sold_chargers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sold_chargers` (`charger_id`),
  ADD KEY `fk_user_sold_chargers` (`sold_by`),
  ADD KEY `fk_sold_chargers_sale_items` (`sale_item_id`);

--
-- Indexes for table `sold_graphics_cards`
--
ALTER TABLE `sold_graphics_cards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_graphics_card` (`graphic_card_id`),
  ADD KEY `fk_graphics_sold` (`sold_by`),
  ADD KEY `fk_sold_graphics_sale_items` (`sale_item_id`);

--
-- Indexes for table `sold_hdds`
--
ALTER TABLE `sold_hdds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_hdd` (`hdd_id`),
  ADD KEY `fk_sold_hdds` (`sold_by`),
  ADD KEY `fk_sold_hdds_sale_items` (`sale_item_id`);

--
-- Indexes for table `sold_rams_ssds`
--
ALTER TABLE `sold_rams_ssds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sold_rams_ssds` (`ram_ssd_id`),
  ADD KEY `fk_rams_ssds_users` (`sold_by`),
  ADD KEY `fk_sold_rams_ssds_sale_items` (`sale_item_id`);

--
-- Indexes for table `ups`
--
ALTER TABLE `ups`
  ADD PRIMARY KEY (`serial_number`),
  ADD KEY `fk_ups_users` (`added_by`),
  ADD KEY `fk_ups_users_sales` (`sold_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_devices`
--
ALTER TABLE `user_devices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `device_id` (`device_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accessories`
--
ALTER TABLE `accessories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `accessories_logs`
--
ALTER TABLE `accessories_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=468;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `chargers`
--
ALTER TABLE `chargers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `charger_logs`
--
ALTER TABLE `charger_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `devices_logs`
--
ALTER TABLE `devices_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `device_updates`
--
ALTER TABLE `device_updates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `graphic_cards`
--
ALTER TABLE `graphic_cards`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `graphic_cards_logs`
--
ALTER TABLE `graphic_cards_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `hdds`
--
ALTER TABLE `hdds`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `hdd_logs`
--
ALTER TABLE `hdd_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `invoice_payments`
--
ALTER TABLE `invoice_payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `maintenance`
--
ALTER TABLE `maintenance`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `quotations`
--
ALTER TABLE `quotations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `quotation_items`
--
ALTER TABLE `quotation_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `rams_ssds`
--
ALTER TABLE `rams_ssds`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `rams_ssds_logs`
--
ALTER TABLE `rams_ssds_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `registration_codes`
--
ALTER TABLE `registration_codes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `repairs`
--
ALTER TABLE `repairs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `sold_accessories`
--
ALTER TABLE `sold_accessories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `sold_chargers`
--
ALTER TABLE `sold_chargers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sold_graphics_cards`
--
ALTER TABLE `sold_graphics_cards`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sold_hdds`
--
ALTER TABLE `sold_hdds`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `sold_rams_ssds`
--
ALTER TABLE `sold_rams_ssds`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `user_devices`
--
ALTER TABLE `user_devices`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accessories`
--
ALTER TABLE `accessories`
  ADD CONSTRAINT `fk_accessories_users` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_updated_accessories` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `accessories_logs`
--
ALTER TABLE `accessories_logs`
  ADD CONSTRAINT `fk_accessories_id` FOREIGN KEY (`accessory_id`) REFERENCES `accessories` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_accessories_logs_sale_items` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_given_by_id` FOREIGN KEY (`given_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_given_to_id` FOREIGN KEY (`given_to`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `chargers`
--
ALTER TABLE `chargers`
  ADD CONSTRAINT `chargers_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_chargers_added_by` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `charger_logs`
--
ALTER TABLE `charger_logs`
  ADD CONSTRAINT `charger_logs_ibfk_1` FOREIGN KEY (`given_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `charger_logs_ibfk_2` FOREIGN KEY (`given_to`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_charger_logs_chargers` FOREIGN KEY (`charger_id`) REFERENCES `chargers` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_charger_logs_sale_items` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `clients`
--
ALTER TABLE `clients`
  ADD CONSTRAINT `fk_sales_person_clients` FOREIGN KEY (`sales_person`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `devices`
--
ALTER TABLE `devices`
  ADD CONSTRAINT `devices_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `devices_ibfk_2` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_serial_number_devices_logs` FOREIGN KEY (`serial_number`) REFERENCES `devices` (`serial_number`) ON UPDATE CASCADE,
  ADD CONSTRAINT `sold_by` FOREIGN KEY (`sold_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `devices_logs`
--
ALTER TABLE `devices_logs`
  ADD CONSTRAINT `fk_category_id_devices_logs` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_given_by_devices_logs` FOREIGN KEY (`given_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_given_to_device_devices_logs` FOREIGN KEY (`given_to`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_given_to_devices_logs` FOREIGN KEY (`given_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_taken_by_devices` FOREIGN KEY (`taken_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_taken_by_devices_logs` FOREIGN KEY (`given_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `fk_created_by_expenses` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `graphic_cards`
--
ALTER TABLE `graphic_cards`
  ADD CONSTRAINT `fk_added_by_user` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `graphic_cards_logs`
--
ALTER TABLE `graphic_cards_logs`
  ADD CONSTRAINT `fk_given_by_graphics_logs` FOREIGN KEY (`given_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_given_to_graphics_logs` FOREIGN KEY (`given_to`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_grahpic_card_id_logs` FOREIGN KEY (`graphic_card_id`) REFERENCES `graphic_cards` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_graphic_cards_logs_sale_items` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `hdds`
--
ALTER TABLE `hdds`
  ADD CONSTRAINT `fk_added_by` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `hdd_logs`
--
ALTER TABLE `hdd_logs`
  ADD CONSTRAINT `fk_hdd_logs` FOREIGN KEY (`hdd_id`) REFERENCES `hdds` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hdd_logs_sale_items` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_logs_given_by` FOREIGN KEY (`given_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_logs_given_to` FOREIGN KEY (`given_to`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoice_payments`
--
ALTER TABLE `invoice_payments`
  ADD CONSTRAINT `invoice_payments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoice_payments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `maintenance`
--
ALTER TABLE `maintenance`
  ADD CONSTRAINT `fk_perfomed_by_software` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `monitors`
--
ALTER TABLE `monitors`
  ADD CONSTRAINT `fk_monitors_users` FOREIGN KEY (`sold_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `phones`
--
ALTER TABLE `phones`
  ADD CONSTRAINT `fk_phones_added_by` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_phones_users` FOREIGN KEY (`sold_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `printers`
--
ALTER TABLE `printers`
  ADD CONSTRAINT `fk_printer_users` FOREIGN KEY (`sold_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `quotations`
--
ALTER TABLE `quotations`
  ADD CONSTRAINT `quotations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `quotation_items`
--
ALTER TABLE `quotation_items`
  ADD CONSTRAINT `quotation_items_ibfk_1` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rams_ssds`
--
ALTER TABLE `rams_ssds`
  ADD CONSTRAINT `fk_rams_ssds_added_by` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `rams_ssds_logs`
--
ALTER TABLE `rams_ssds_logs`
  ADD CONSTRAINT `fk_given_by` FOREIGN KEY (`given_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_given_to` FOREIGN KEY (`given_to`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ram_ssd_id` FOREIGN KEY (`ram_ssd_id`) REFERENCES `rams_ssds` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rams_ssds_logs_sale_items` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `repairs`
--
ALTER TABLE `repairs`
  ADD CONSTRAINT `fk_added_by_repairs` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_category_repairs` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_given_by_repair` FOREIGN KEY (`given_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sales_person_repairs` FOREIGN KEY (`sales_person`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `fk_solb_by_sales` FOREIGN KEY (`sold_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `fk_sales_saleitems` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `saleitems_saleperson` FOREIGN KEY (`sales_person`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `smartboards`
--
ALTER TABLE `smartboards`
  ADD CONSTRAINT `fk_sales_smartboards` FOREIGN KEY (`sold_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_users_smartboards` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `sold_accessories`
--
ALTER TABLE `sold_accessories`
  ADD CONSTRAINT `fk_accessories_sold` FOREIGN KEY (`accessory_id`) REFERENCES `accessories` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sold_accessories_sale_items` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sold_accessories_users` FOREIGN KEY (`sold_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `sold_chargers`
--
ALTER TABLE `sold_chargers`
  ADD CONSTRAINT `fk_sold_chargers` FOREIGN KEY (`charger_id`) REFERENCES `chargers` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sold_chargers_sale_items` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_sold_chargers` FOREIGN KEY (`sold_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `sold_graphics_cards`
--
ALTER TABLE `sold_graphics_cards`
  ADD CONSTRAINT `fk_graphics_card` FOREIGN KEY (`graphic_card_id`) REFERENCES `graphic_cards` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_graphics_sold` FOREIGN KEY (`sold_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sold_graphics_sale_items` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `sold_hdds`
--
ALTER TABLE `sold_hdds`
  ADD CONSTRAINT `fk_hdd` FOREIGN KEY (`hdd_id`) REFERENCES `hdds` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sold_hdds` FOREIGN KEY (`sold_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sold_hdds_sale_items` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `sold_rams_ssds`
--
ALTER TABLE `sold_rams_ssds`
  ADD CONSTRAINT `fk_rams_ssds_users` FOREIGN KEY (`sold_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sold_rams_ssds` FOREIGN KEY (`ram_ssd_id`) REFERENCES `rams_ssds` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sold_rams_ssds_sale_items` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `ups`
--
ALTER TABLE `ups`
  ADD CONSTRAINT `fk_ups_users` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ups_users_sales` FOREIGN KEY (`sold_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `user_devices`
--
ALTER TABLE `user_devices`
  ADD CONSTRAINT `user_devices_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
