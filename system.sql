-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Nov 26, 2025 at 01:36 AM
-- Server version: 8.0.36
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `system`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('super_admin','admin') DEFAULT 'admin',
  `is_active` tinyint(1) DEFAULT '1',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `fullname`, `email`, `role`, `is_active`, `last_login`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'المدير الرئيسي', 'admin@system.com', 'super_admin', 1, '2025-11-26 00:28:02', '2025-11-05 22:46:03'),
(6, 'BelalOmar', '$2y$10$KMM6TA0kqXN6Lj15qPuwV..oMvUlloX1dDoamBBx3gCcgVERXE/Ya', 'Belal Medhat', 'medhatomar5555@gmail.com', 'admin', 1, '2025-11-24 23:30:50', '2025-11-24 23:30:40');

-- --------------------------------------------------------

--
-- Table structure for table `balance_transactions`
--

CREATE TABLE `balance_transactions` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `order_id` int DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` enum('credit','debit') NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commission_history`
--

CREATE TABLE `commission_history` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `order_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` enum('order_commission','withdrawal','adjustment') DEFAULT 'order_commission',
  `status` enum('pending','available','withdrawn','cancelled') DEFAULT 'pending',
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `commission_history`
--

INSERT INTO `commission_history` (`id`, `user_id`, `order_id`, `amount`, `type`, `status`, `description`, `created_at`) VALUES
(19, 8, 24, 10.00, 'order_commission', 'available', 'عمولة طلب #24', '2025-11-21 18:34:27'),
(20, 8, 25, 10.00, 'order_commission', 'pending', 'عمولة معلقة - طلب #25', '2025-11-23 18:25:26'),
(21, 8, 25, 10.00, 'order_commission', 'available', 'عمولة طلب #25', '2025-11-23 18:25:37'),
(22, 8, 25, 10.00, 'order_commission', 'pending', 'عمولة معلقة - طلب #25', '2025-11-23 18:25:42'),
(23, 8, 25, 10.00, 'order_commission', 'available', 'عمولة طلب #25', '2025-11-23 18:25:44'),
(24, 8, 26, 10.00, 'order_commission', 'available', 'عمولة طلب #26', '2025-11-23 19:34:10'),
(25, 8, 27, 10.00, 'order_commission', 'available', 'عمولة طلب #27', '2025-11-23 20:30:09'),
(26, 8, 28, 10.00, 'order_commission', 'available', 'عمولة طلب #28', '2025-11-23 20:32:30'),
(27, 8, 29, 10.00, 'order_commission', 'available', 'عمولة طلب #29', '2025-11-23 20:36:36'),
(28, 8, 30, 10.00, 'order_commission', 'available', 'عمولة طلب #30', '2025-11-23 22:46:41'),
(29, 8, 30, 10.00, 'order_commission', 'available', 'عمولة طلب #30', '2025-11-23 22:46:46'),
(30, 8, 30, 10.00, 'order_commission', 'cancelled', 'عمولة ملغاة - طلب #30', '2025-11-23 22:46:55'),
(31, 8, 30, 10.00, 'order_commission', 'available', 'عمولة طلب #30', '2025-11-23 22:47:02'),
(32, 8, 27, 10.00, 'order_commission', 'cancelled', 'عمولة ملغاة - طلب #27', '2025-11-24 18:27:51'),
(33, 18, 31, 10.00, 'order_commission', 'available', 'عمولة طلب #31', '2025-11-24 20:23:44'),
(34, 18, 31, 10.00, 'order_commission', 'cancelled', 'عمولة ملغاة - طلب #31', '2025-11-24 20:25:29'),
(35, 8, 30, 10.00, 'order_commission', 'cancelled', 'عمولة ملغاة - طلب #30', '2025-11-25 00:44:24'),
(36, 8, 30, 10.00, 'order_commission', 'available', 'عمولة طلب #30', '2025-11-25 00:44:29'),
(37, 18, 31, 10.00, 'order_commission', 'cancelled', 'عمولة ملغاة - طلب #31', '2025-11-25 00:45:32'),
(38, 18, 31, 10.00, 'order_commission', 'pending', 'عمولة معلقة - طلب #31', '2025-11-25 00:47:35'),
(39, 18, 31, 10.00, 'order_commission', 'cancelled', 'عمولة ملغاة - طلب #31', '2025-11-25 00:47:38'),
(40, 18, 31, 10.00, 'order_commission', 'cancelled', 'عمولة ملغاة - طلب #31', '2025-11-25 01:42:50'),
(41, 8, 28, 10.00, 'order_commission', 'cancelled', 'عمولة ملغاة - طلب #28', '2025-11-25 01:44:33'),
(42, 8, 28, 10.00, 'order_commission', 'cancelled', 'عمولة ملغاة - طلب #28', '2025-11-25 01:45:52'),
(43, 18, 31, 10.00, 'order_commission', 'pending', 'عمولة معلقة - طلب #31', '2025-11-25 01:46:11'),
(44, 18, 31, 10.00, 'order_commission', 'pending', 'عمولة معلقة - طلب #31', '2025-11-25 01:46:12'),
(45, 18, 31, 10.00, 'order_commission', 'pending', 'عمولة معلقة - طلب #31', '2025-11-25 01:46:14'),
(46, 18, 31, 10.00, 'order_commission', 'pending', 'عمولة معلقة - طلب #31', '2025-11-25 01:46:15'),
(47, 18, 31, 10.00, 'order_commission', 'available', 'عمولة طلب #31', '2025-11-25 01:46:16'),
(48, 18, 31, 10.00, 'order_commission', 'cancelled', 'عمولة ملغاة - طلب #31', '2025-11-25 01:46:29'),
(49, 18, 31, 10.00, 'order_commission', 'available', 'عمولة طلب #31', '2025-11-25 01:46:33'),
(50, 8, 29, 10.00, 'order_commission', 'cancelled', 'عمولة ملغاة - طلب #29', '2025-11-25 02:54:24'),
(51, 8, 26, 10.00, 'order_commission', 'cancelled', 'عمولة ملغاة - طلب #26', '2025-11-25 02:54:34'),
(52, 8, 30, 10.00, 'order_commission', 'cancelled', 'عمولة ملغاة - طلب #30', '2025-11-25 02:55:01'),
(53, 8, 25, 10.00, 'order_commission', 'cancelled', 'عمولة ملغاة - طلب #25', '2025-11-25 02:55:09'),
(54, 8, 25, 10.00, 'order_commission', 'cancelled', 'عمولة ملغاة - طلب #25', '2025-11-25 02:55:12'),
(55, 8, 30, 10.00, 'order_commission', 'available', 'عمولة طلب #30', '2025-11-25 13:03:32'),
(56, 8, 32, 10.00, 'order_commission', 'pending', 'عمولة معلقة - طلب #32', '2025-11-25 13:12:59'),
(57, 8, 32, 10.00, 'order_commission', 'available', 'عمولة طلب #32', '2025-11-25 13:13:05'),
(58, 8, 33, 10.00, 'order_commission', 'available', 'عمولة طلب #33', '2025-11-25 22:55:54'),
(59, 8, 33, 10.00, 'order_commission', 'available', 'عمولة طلب #33', '2025-11-25 23:12:49'),
(60, 8, 33, 10.00, 'order_commission', 'available', 'عمولة طلب #33', '2025-11-25 23:20:04'),
(61, 5, 42, 10.00, 'order_commission', 'available', 'عمولة طلب #42', '2025-11-25 23:57:30'),
(62, 5, 42, 10.00, 'order_commission', 'available', 'عمولة طلب #42', '2025-11-26 00:00:26');

-- --------------------------------------------------------

--
-- Table structure for table `marketer_balance`
--

CREATE TABLE `marketer_balance` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `total_earnings` decimal(10,2) DEFAULT '0.00',
  `available_balance` decimal(10,2) DEFAULT '0.00',
  `pending_balance` decimal(10,2) DEFAULT '0.00',
  `withdrawn_balance` decimal(10,2) DEFAULT '0.00',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `marketer_balance`
--

INSERT INTO `marketer_balance` (`id`, `user_id`, `total_earnings`, `available_balance`, `pending_balance`, `withdrawn_balance`, `last_updated`) VALUES
(32, 5, 20.00, 20.00, -10.00, 0.00, '2025-11-26 00:00:26'),
(33, 5, 0.00, 0.00, -10.00, 0.00, '2025-11-26 00:00:26'),
(34, 5, 10.00, 10.00, -10.00, 0.00, '2025-11-26 00:00:26'),
(35, 5, 0.00, 0.00, -10.00, 0.00, '2025-11-26 00:00:26'),
(36, 6, 10.00, 10.00, 0.00, 0.00, '2025-11-16 20:14:34'),
(37, 8, -200.00, -200.00, -290.00, 0.00, '2025-11-25 23:20:04'),
(38, 8, -190.00, -190.00, -260.00, 0.00, '2025-11-25 23:20:04'),
(39, 8, -180.00, -180.00, -240.00, 0.00, '2025-11-25 23:20:04'),
(40, 8, -120.00, -120.00, -190.00, 0.00, '2025-11-25 23:20:04'),
(41, 8, -120.00, -120.00, -140.00, 0.00, '2025-11-25 23:20:04'),
(42, 8, -70.00, -70.00, -140.00, 0.00, '2025-11-25 23:20:04'),
(43, 8, -100.00, -100.00, -120.00, 0.00, '2025-11-25 23:20:04'),
(44, 8, -110.00, -110.00, -110.00, 0.00, '2025-11-25 23:20:04'),
(45, 8, -120.00, -120.00, -90.00, 0.00, '2025-11-25 23:20:04'),
(46, 8, -110.00, -110.00, -90.00, 0.00, '2025-11-25 23:20:04'),
(47, 8, -110.00, -110.00, -80.00, 0.00, '2025-11-25 23:20:04'),
(48, 8, -100.00, -100.00, -80.00, 0.00, '2025-11-25 23:20:04'),
(49, 8, -100.00, -100.00, -70.00, 0.00, '2025-11-25 23:20:04'),
(50, 8, -100.00, -100.00, -60.00, 0.00, '2025-11-25 23:20:04'),
(51, 8, -100.00, -100.00, -50.00, 0.00, '2025-11-25 23:20:04'),
(52, 8, -100.00, -100.00, -40.00, 0.00, '2025-11-25 23:20:04'),
(53, 8, -100.00, -100.00, -30.00, 0.00, '2025-11-25 23:20:04'),
(54, 8, -90.00, -90.00, -30.00, 0.00, '2025-11-25 23:20:04'),
(55, 8, -80.00, -80.00, -30.00, 0.00, '2025-11-25 23:20:04'),
(56, 18, -10.00, -10.00, -50.00, 0.00, '2025-11-25 01:46:29'),
(57, 8, -50.00, -50.00, -30.00, 0.00, '2025-11-25 23:20:04'),
(58, 18, -10.00, -10.00, -40.00, 0.00, '2025-11-25 01:46:29'),
(59, 18, -10.00, -10.00, -30.00, 0.00, '2025-11-25 01:46:29'),
(60, 18, -10.00, -10.00, -20.00, 0.00, '2025-11-25 01:46:29'),
(61, 18, -10.00, -10.00, -10.00, 0.00, '2025-11-25 01:46:29'),
(62, 18, -10.00, -10.00, 0.00, 0.00, '2025-11-25 01:46:29'),
(63, 18, 0.00, 0.00, 0.00, 0.00, '2025-11-25 01:46:29'),
(64, 18, 10.00, 10.00, 0.00, 0.00, '2025-11-25 01:46:33'),
(65, 8, -10.00, -10.00, -30.00, 0.00, '2025-11-25 23:20:04'),
(66, 8, -20.00, -20.00, -10.00, 0.00, '2025-11-25 23:20:04'),
(67, 8, -10.00, -10.00, -10.00, 0.00, '2025-11-25 23:20:04'),
(68, 8, -10.00, -10.00, 0.00, 0.00, '2025-11-25 23:20:04'),
(69, 8, 0.00, 0.00, 0.00, 0.00, '2025-11-25 23:20:04'),
(70, 8, 10.00, 10.00, 0.00, 0.00, '2025-11-25 23:20:04'),
(71, 5, 0.00, 0.00, 0.00, 0.00, '2025-11-26 00:00:26'),
(72, 5, 10.00, 10.00, 0.00, 0.00, '2025-11-26 00:00:26');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL DEFAULT 'غير معروف',
  `customer_phone` varchar(20) NOT NULL,
  `region` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `shipping_city` varchar(100) DEFAULT NULL,
  `shipping_cost` decimal(10,2) DEFAULT '0.00',
  `merchant_net_price` decimal(10,2) DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL,
  `commission_total` decimal(10,2) NOT NULL,
  `shipping_fee` decimal(10,2) DEFAULT '0.00',
  `status` enum('قيد الانتظار','تم التأكيد','قيد التنفيذ','في الشحن','تم التوصيل','مرتجع','ملغي','محصل','تحت التحضير','مرفوض') DEFAULT 'قيد الانتظار',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `shipping_city_id` int NOT NULL,
  `shipping_city_name` varchar(255) DEFAULT NULL,
  `cancellation_reason` text,
  `return_reason` text,
  `reason_type` enum('cancellation','return') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `customer_name`, `customer_phone`, `region`, `address`, `shipping_city`, `shipping_cost`, `merchant_net_price`, `total`, `commission_total`, `shipping_fee`, `status`, `created_at`, `shipping_city_id`, `shipping_city_name`, `cancellation_reason`, `return_reason`, `reason_type`) VALUES
(24, 8, 'Belal Medhat', '01228721331', 'ِAlexandria', '49 شارع باب الملوك - كرموز', NULL, 30.00, 0.00, 105.00, 10.00, 0.00, 'تم التوصيل', '2025-11-21 18:33:50', 19, 'غدامس', NULL, NULL, NULL),
(25, 8, 'Belal Medhat', '01228721331', 'ِAlexandria', '49 شارع باب الملوك - كرموز', NULL, 30.00, 0.00, 105.00, 10.00, 0.00, 'ملغي', '2025-11-23 18:18:42', 19, 'غدامس', 'عدم القدرة علي الوصول للعميل', '', 'cancellation'),
(26, 8, 'Belal Medhat', '01228721331', 'ِAlexandria', '49 شارع باب الملوك - كرموز', NULL, 30.00, 0.00, 105.00, 10.00, 0.00, 'ملغي', '2025-11-23 19:33:55', 19, 'غدامس', 'عدم القدرة علي الوصول للعميل', '', 'cancellation'),
(27, 8, 'Belal Medhat', '01228721331', 'ِAlexandria', '49 شارع باب الملوك - كرموز', NULL, 30.00, 0.00, 105.00, 10.00, 0.00, 'مرتجع', '2025-11-23 20:29:54', 19, 'غدامس', '', 'عدم القدرة علي الوصول للعميل', 'return'),
(28, 8, 'Belal Medhat', '01228721331', 'ِAlexandria', '49 شارع باب الملوك - كرموز', NULL, 30.00, 0.00, 105.00, 10.00, 0.00, 'ملغي', '2025-11-23 20:31:48', 19, 'غدامس', 'عدم القدرة علي الوصول للعميل', '', 'cancellation'),
(29, 8, 'Belal Medhat', '01228721331', 'ِAlexandria', '49 شارع باب الملوك - كرموز', NULL, 30.00, 0.00, 105.00, 10.00, 0.00, 'ملغي', '2025-11-23 20:36:20', 19, 'غدامس', 'عدم القدرة علي الوصول للعميل', '', 'cancellation'),
(30, 8, 'Belal Medhat', '01228721331', 'ِAlexandria', '49 شارع باب الملوك - كرموز', NULL, 30.00, 0.00, 105.00, 10.00, 0.00, 'تم التوصيل', '2025-11-23 22:46:14', 19, 'غدامس', '', '', NULL),
(31, 18, 'Belal Medhat', '01228721331', 'ِAlexandria', '49 شارع باب الملوك - كرموز', NULL, 30.00, 0.00, 105.00, 10.00, 0.00, 'تم التوصيل', '2025-11-24 20:22:15', 14, 'البيضاء', '', '', NULL),
(32, 8, 'Belal Medhat', '01228721331', 'ِAlexandria', '49 شارع باب الملوك - كرموز', NULL, 30.00, 0.00, 105.00, 10.00, 0.00, 'تم التوصيل', '2025-11-25 13:12:39', 21, 'تازوتبو', '', '', NULL),
(33, 8, 'Belal Medhat', '01228721331', 'ِAlexandria', '49 شارع باب الملوك - كرموز', NULL, 30.00, 0.00, 105.00, 10.00, 0.00, 'تم التوصيل', '2025-11-25 22:34:28', 47, 'أبو عيسي', '', '', NULL),
(34, 8, 'Belal Medhat', '01228721331', 'ِAlexandria', '49 شارع باب الملوك - كرموز', NULL, 30.00, 0.00, 255.00, 30.00, 0.00, 'قيد الانتظار', '2025-11-25 23:11:56', 58, 'أبو كماش', NULL, NULL, NULL),
(35, 8, 'Belal Medhat', '01228721331', 'ِAlexandria', '49 شارع باب الملوك - كرموز', NULL, 30.00, 0.00, 105.00, 10.00, 0.00, 'قيد الانتظار', '2025-11-25 23:14:59', 26, 'اجدابيا', NULL, NULL, NULL),
(37, 5, 'Belal Medhat', '01228721331', 'ِAlexandria', '49 شارع باب الملوك - كرموز', NULL, 30.00, 0.00, 105.00, 10.00, 0.00, 'قيد الانتظار', '2025-11-25 23:27:32', 85, 'قصر ليبيا', NULL, NULL, NULL),
(38, 5, 'Belal Medhat', '01228721331', 'ِAlexandria', '49 شارع باب الملوك - كرموز', NULL, 30.00, 0.00, 105.00, 10.00, 0.00, 'قيد الانتظار', '2025-11-25 23:32:27', 38, 'قصر خيار', NULL, NULL, NULL),
(39, 5, 'Belal Medhat', '01228721331', 'ِAlexandria', '49 شارع باب الملوك - كرموز', NULL, 30.00, 0.00, 180.00, 20.00, 0.00, 'قيد الانتظار', '2025-11-25 23:40:44', 54, '0', NULL, NULL, NULL),
(41, 5, 'Belal Medhat', '01228721331', 'ِAlexandria', '49 شارع باب الملوك - كرموز', NULL, 30.00, 0.00, 105.00, 10.00, 0.00, 'قيد الانتظار', '2025-11-25 23:49:42', 14, 'البيضاء', NULL, NULL, NULL),
(42, 5, 'Belal Medhat', '01228721331', 'ِAlexandria', '49 شارع باب الملوك - كرموز', NULL, 30.00, 0.00, 105.00, 10.00, 0.00, 'تم التوصيل', '2025-11-25 23:50:19', 54, 'قصر بن غشير', '', '', NULL),
(43, 5, 'Belal Medhat', '01228721331', 'ِAlexandria', '49 شارع باب الملوك - كرموز', NULL, 30.00, 0.00, 105.00, 10.00, 0.00, 'قيد الانتظار', '2025-11-26 00:02:45', 54, 'قصر بن غشير', NULL, NULL, NULL),
(44, 5, 'Belal Medhat', '01228721331', 'ِAlexandria', '49 شارع باب الملوك - كرموز', NULL, 30.00, 0.00, 180.00, 20.00, 0.00, 'قيد الانتظار', '2025-11-26 00:11:10', 47, 'أبو عيسي', NULL, NULL, NULL);

--
-- Triggers `orders`
--
DELIMITER $$
CREATE TRIGGER `ultimate_city_fix` BEFORE INSERT ON `orders` FOR EACH ROW BEGIN
    DECLARE city_name_found VARCHAR(255);
    DECLARE city_cost_found DECIMAL(10,2);
    
    -- إذا كان هناك city_id
    IF NEW.shipping_city_id IS NOT NULL AND NEW.shipping_city_id > 0 THEN
        
        -- جلب اسم المدينة وتكلفة الشحن
        SELECT city_name, shipping_cost INTO city_name_found, city_cost_found
        FROM shipping_cities 
        WHERE id = NEW.shipping_city_id;
        
        -- فرض اسم المدينة
        IF city_name_found IS NOT NULL THEN
            SET NEW.shipping_city_name = city_name_found;
        ELSE
            SET NEW.shipping_city_name = 'مدينة غير معروفة';
        END IF;
        
        -- فرض تكلفة الشحن
        IF NEW.shipping_cost IS NULL OR NEW.shipping_cost = 0 THEN
            IF city_cost_found IS NOT NULL THEN
                SET NEW.shipping_cost = city_cost_found;
            ELSE
                SET NEW.shipping_cost = 30.00;
            END IF;
        END IF;
        
    ELSE
        -- إذا لم يكن هناك city_id
        IF NEW.shipping_city_name IS NULL OR NEW.shipping_city_name = '' THEN
            SET NEW.shipping_city_name = 'مدينة غير محددة';
        END IF;
        
        IF NEW.shipping_cost IS NULL OR NEW.shipping_cost = 0 THEN
            SET NEW.shipping_cost = 30.00;
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `ultimate_city_fix_update` BEFORE UPDATE ON `orders` FOR EACH ROW BEGIN
    DECLARE city_name_found VARCHAR(255);
    DECLARE city_cost_found DECIMAL(10,2);
    
    -- إذا كان هناك city_id
    IF NEW.shipping_city_id IS NOT NULL AND NEW.shipping_city_id > 0 THEN
        
        -- جلب اسم المدينة وتكلفة الشحن
        SELECT city_name, shipping_cost INTO city_name_found, city_cost_found
        FROM shipping_cities 
        WHERE id = NEW.shipping_city_id;
        
        -- فرض اسم المدينة
        IF city_name_found IS NOT NULL THEN
            SET NEW.shipping_city_name = city_name_found;
        ELSE
            SET NEW.shipping_city_name = 'مدينة غير معروفة';
        END IF;
        
        -- فرض تكلفة الشحن
        IF NEW.shipping_cost IS NULL OR NEW.shipping_cost = 0 THEN
            IF city_cost_found IS NOT NULL THEN
                SET NEW.shipping_cost = city_cost_found;
            ELSE
                SET NEW.shipping_cost = 30.00;
            END IF;
        END IF;
        
    ELSE
        -- إذا لم يكن هناك city_id
        IF NEW.shipping_city_name IS NULL OR NEW.shipping_city_name = '' THEN
            SET NEW.shipping_city_name = 'مدينة غير محددة';
        END IF;
        
        IF NEW.shipping_cost IS NULL OR NEW.shipping_cost = 0 THEN
            SET NEW.shipping_cost = 30.00;
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int NOT NULL,
  `order_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `quantity` int NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `original_price` decimal(10,2) DEFAULT NULL,
  `commission` decimal(10,2) NOT NULL,
  `shipping_cost` decimal(10,2) DEFAULT '0.00',
  `color` varchar(50) DEFAULT NULL,
  `size` varchar(20) DEFAULT NULL,
  `is_shipping_included` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`, `original_price`, `commission`, `shipping_cost`, `color`, `size`, `is_shipping_included`) VALUES
(25, 24, 4, 1, 65.00, 65.00, 10.00, 30.00, '—', '—', 0),
(26, 25, 4, 1, 65.00, 65.00, 10.00, 30.00, '—', '—', 0),
(27, 26, 4, 1, 65.00, 65.00, 10.00, 30.00, '—', '—', 0),
(28, 27, 4, 1, 65.00, 65.00, 10.00, 30.00, '—', '—', 0),
(29, 28, 4, 1, 65.00, 65.00, 10.00, 30.00, '—', '—', 0),
(30, 29, 4, 1, 65.00, 65.00, 10.00, 30.00, '—', '—', 0),
(31, 30, 3, 1, 65.00, 65.00, 10.00, 0.00, 'ازرق', '2XL', 0),
(32, 31, 4, 1, 65.00, 65.00, 10.00, 0.00, '—', '—', 0),
(33, 32, 4, 1, 65.00, 65.00, 10.00, 0.00, '—', '—', 0),
(34, 33, 4, 1, 65.00, 65.00, 10.00, 0.00, '—', '—', 0),
(35, 34, 4, 2, 65.00, 65.00, 10.00, 0.00, '—', '—', 0),
(36, 34, 3, 1, 65.00, 65.00, 10.00, 0.00, 'ازرق', '3XL', 0),
(37, 35, 4, 1, 65.00, 65.00, 10.00, 0.00, '—', '—', 0),
(38, 37, 4, 1, 65.00, 65.00, 10.00, 0.00, '—', '—', 0),
(39, 38, 4, 1, 65.00, 65.00, 10.00, 0.00, '—', '—', 0),
(40, 39, 4, 2, 65.00, 65.00, 10.00, 0.00, '—', '—', 0),
(41, 41, 3, 1, 65.00, 65.00, 10.00, 0.00, 'ازرق', 'XL', 0),
(42, 42, 3, 1, 65.00, 65.00, 10.00, 0.00, 'ازرق', 'L', 0),
(43, 43, 3, 1, 65.00, 65.00, 10.00, 0.00, 'ازرق', '2XL', 0),
(44, 44, 3, 1, 65.00, 65.00, 10.00, 0.00, 'ازرق', '2XL', 0),
(45, 44, 4, 1, 65.00, 65.00, 10.00, 0.00, '—', '—', 0);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `used` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expires_at`, `created_at`, `used`) VALUES
(11, 8, '89e3b5aad5ea375e1791072545d2fba8265e2f1f11682d7020225f5132f1ebaf', '2025-11-24 22:49:14', '2025-11-24 22:49:14', 0);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `commission` decimal(10,2) NOT NULL,
  `shipping_prices` text,
  `has_custom_shipping` tinyint(1) DEFAULT '0',
  `shipping_fee` decimal(10,2) DEFAULT '0.00',
  `category` varchar(100) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `stock` int DEFAULT '0',
  `shipping_company` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `shipping_cost` decimal(10,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `price`, `commission`, `shipping_prices`, `has_custom_shipping`, `shipping_fee`, `category`, `image`, `stock`, `shipping_company`, `created_at`, `user_id`, `status`, `shipping_cost`) VALUES
(3, 'جاكيت Tommy', 'جاكت رجالي الخامة وترتوڤ مبطن فرو داخلي المقاسات كبيرة من أول L حتى 3XL', 65.00, 10.00, '[{\"city_id\":19,\"price\":60},{\"city_id\":15,\"price\":50},{\"city_id\":18,\"price\":55},{\"city_id\":3,\"price\":35},{\"city_id\":4,\"price\":40},{\"city_id\":20,\"price\":65},{\"city_id\":14,\"price\":40},{\"city_id\":2,\"price\":25},{\"city_id\":8,\"price\":40},{\"city_id\":5,\"price\":40},{\"city_id\":9,\"price\":40},{\"city_id\":12,\"price\":35},{\"city_id\":25,\"price\":40},{\"city_id\":1,\"price\":25},{\"city_id\":10,\"price\":30},{\"city_id\":11,\"price\":35},{\"city_id\":27,\"price\":45},{\"city_id\":21,\"price\":70},{\"city_id\":26,\"price\":40},{\"city_id\":6,\"price\":45},{\"city_id\":24,\"price\":75},{\"city_id\":7,\"price\":45},{\"city_id\":16,\"price\":50},{\"city_id\":23,\"price\":70},{\"city_id\":17,\"price\":55},{\"city_id\":13,\"price\":40},{\"city_id\":22,\"price\":65}]', 1, 0.00, 'ملابس رجالي', 'imgs/1762384416_1762383274_68fc8f3b4dec8.webp', 65, NULL, '2025-11-05 22:54:34', NULL, 'active', 0.00),
(4, 'كريم شعر', 'كريم شعر ينعم و يغذي فروة الرأس', 65.00, 10.00, '[{\"city_id\":19,\"price\":60},{\"city_id\":15,\"price\":50},{\"city_id\":18,\"price\":55},{\"city_id\":3,\"price\":35},{\"city_id\":4,\"price\":40},{\"city_id\":20,\"price\":65},{\"city_id\":14,\"price\":40},{\"city_id\":2,\"price\":25},{\"city_id\":8,\"price\":40},{\"city_id\":5,\"price\":40},{\"city_id\":9,\"price\":40},{\"city_id\":12,\"price\":35},{\"city_id\":25,\"price\":40},{\"city_id\":1,\"price\":25},{\"city_id\":10,\"price\":30},{\"city_id\":11,\"price\":35},{\"city_id\":27,\"price\":45},{\"city_id\":21,\"price\":70},{\"city_id\":26,\"price\":40},{\"city_id\":6,\"price\":45},{\"city_id\":24,\"price\":75},{\"city_id\":7,\"price\":45},{\"city_id\":16,\"price\":50},{\"city_id\":23,\"price\":70},{\"city_id\":17,\"price\":55},{\"city_id\":13,\"price\":40},{\"city_id\":22,\"price\":65}]', 1, 30.00, 'مستحضرات تجميل', NULL, 6, 'Digital', '2025-11-17 00:44:31', 10, 'active', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_main` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `image_path`, `is_main`, `created_at`) VALUES
(1, 3, 'imgs/1762385199_0_1762383274_68fc8f3b4dec8.webp', 1, '2025-11-05 23:26:39'),
(2, 3, 'imgs/1762385215_0_1762384303_zity.webp', 0, '2025-11-05 23:26:55'),
(3, 4, 'imgs/1763340271_0_shopping.webp', 1, '2025-11-17 00:44:31'),
(4, 4, 'imgs/1763340422_0_shopping.webp', 0, '2025-11-17 00:47:02');

-- --------------------------------------------------------

--
-- Table structure for table `product_inventory`
--

CREATE TABLE `product_inventory` (
  `id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `size` varchar(20) DEFAULT NULL,
  `quantity` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `product_inventory`
--

INSERT INTO `product_inventory` (`id`, `product_id`, `color`, `size`, `quantity`) VALUES
(141, 3, 'ازرق', 'L', 20),
(142, 3, 'زيتي', 'L', 20),
(143, 3, 'ازرق', 'XL', 20),
(144, 3, 'ازرق', '2XL', 10),
(145, 3, 'ازرق', '3XL', 10),
(146, 3, 'زيتي', 'XL', 10),
(147, 3, 'زيتي', '2XL', 10);

-- --------------------------------------------------------

--
-- Table structure for table `shipping_cities`
--

CREATE TABLE `shipping_cities` (
  `id` int NOT NULL,
  `city_name` varchar(100) NOT NULL,
  `shipping_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `shipping_cost` decimal(10,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `shipping_cities`
--

INSERT INTO `shipping_cities` (`id`, `city_name`, `shipping_price`, `is_active`, `created_at`, `shipping_cost`) VALUES
(1, 'الكفرة', 25.00, 1, '2025-11-19 18:28:41', 30.00),
(2, 'الخمس', 25.00, 1, '2025-11-19 18:28:41', 30.00),
(3, 'مصراته', 35.00, 1, '2025-11-19 18:28:42', 30.00),
(4, 'الزاوية', 40.00, 1, '2025-11-19 18:28:42', 30.00),
(5, 'سرت', 40.00, 1, '2025-11-19 18:28:42', 30.00),
(6, 'المرج', 45.00, 1, '2025-11-19 18:28:42', 30.00),
(7, 'غات', 45.00, 1, '2025-11-19 18:28:42', 30.00),
(8, 'طبرق', 40.00, 1, '2025-11-19 18:28:42', 30.00),
(9, 'غريان', 40.00, 1, '2025-11-19 18:28:42', 30.00),
(10, 'هون', 30.00, 1, '2025-11-19 18:28:42', 30.00),
(11, 'ودان', 35.00, 1, '2025-11-19 18:28:42', 30.00),
(12, 'أوباري', 35.00, 1, '2025-11-19 18:28:42', 30.00),
(14, 'البيضاء', 40.00, 1, '2025-11-19 18:28:42', 30.00),
(15, 'طرابلس', 50.00, 1, '2025-11-19 18:28:42', 30.00),
(16, 'صبراتة', 50.00, 1, '2025-11-19 18:28:42', 30.00),
(18, 'بنغازي', 55.00, 1, '2025-11-19 18:28:42', 30.00),
(19, 'غدامس', 60.00, 1, '2025-11-19 18:28:42', 30.00),
(20, 'سبها', 65.00, 1, '2025-11-19 18:28:42', 30.00),
(21, 'تازوتبو', 70.00, 1, '2025-11-19 18:28:42', 30.00),
(23, 'لبدة الكبرى', 70.00, 1, '2025-11-19 18:28:42', 30.00),
(24, 'درنة', 75.00, 1, '2025-11-19 18:28:42', 30.00),
(25, 'مرزق', 40.00, 1, '2025-11-19 18:28:42', 30.00),
(26, 'اجدابيا', 40.00, 1, '2025-11-19 18:28:42', 30.00),
(27, 'زلة', 45.00, 1, '2025-11-19 18:28:42', 30.00),
(28, 'زوارة', 0.00, 1, '2025-11-25 21:48:27', 30.00),
(29, 'ورشفانة', 0.00, 1, '2025-11-25 21:49:14', 30.00),
(30, 'زلتين', 0.00, 1, '2025-11-25 21:49:33', 30.00),
(31, 'ترهونة', 0.00, 1, '2025-11-25 21:49:49', 30.00),
(32, 'يفرن', 0.00, 1, '2025-11-25 21:50:14', 30.00),
(33, 'نالوت', 0.00, 1, '2025-11-25 21:50:31', 30.00),
(34, 'صرمان', 0.00, 1, '2025-11-25 21:50:54', 30.00),
(35, 'القبة', 0.00, 1, '2025-11-25 21:51:08', 30.00),
(36, 'بني وليد', 0.00, 1, '2025-11-25 21:51:26', 30.00),
(37, 'سوكنة', 0.00, 1, '2025-11-25 21:52:02', 30.00),
(38, 'قصر خيار', 0.00, 1, '2025-11-25 21:52:27', 30.00),
(39, 'غوط الرمان', 0.00, 1, '2025-11-25 21:52:46', 30.00),
(40, 'وادي كعام', 0.00, 1, '2025-11-25 21:53:03', 30.00),
(41, 'الابيار', 0.00, 1, '2025-11-25 21:53:17', 30.00),
(42, 'امساعد', 0.00, 1, '2025-11-25 21:53:35', 30.00),
(43, 'العلوص', 0.00, 1, '2025-11-25 21:53:48', 30.00),
(44, 'القربولي', 0.00, 1, '2025-11-25 21:54:01', 30.00),
(45, 'القويعة', 0.00, 1, '2025-11-25 21:54:19', 30.00),
(46, 'قماطة', 0.00, 1, '2025-11-25 21:55:15', 30.00),
(47, 'أبو عيسي', 0.00, 1, '2025-11-25 21:55:40', 30.00),
(48, 'الماية', 0.00, 1, '2025-11-25 21:55:53', 30.00),
(49, 'المطرد', 0.00, 1, '2025-11-25 21:56:07', 30.00),
(50, 'تيجي', 0.00, 1, '2025-11-25 21:56:23', 30.00),
(51, 'جدائم', 0.00, 1, '2025-11-25 21:56:37', 30.00),
(52, 'كوبري 27', 0.00, 1, '2025-11-25 21:56:58', 30.00),
(53, 'الطويلة', 0.00, 1, '2025-11-25 21:57:12', 30.00),
(54, 'قصر بن غشير', 0.00, 1, '2025-11-25 21:57:37', 30.00),
(55, 'السايح', 0.00, 1, '2025-11-25 21:57:53', 30.00),
(56, 'سوق السبت', 0.00, 1, '2025-11-25 21:58:08', 30.00),
(57, 'الجميل', 0.00, 1, '2025-11-25 21:58:22', 30.00),
(58, 'أبو كماش', 0.00, 1, '2025-11-25 21:58:38', 30.00),
(59, 'رقدالين', 0.00, 1, '2025-11-25 21:58:54', 30.00),
(60, 'العجيلات', 0.00, 1, '2025-11-25 21:59:11', 30.00),
(61, 'الزنتان', 0.00, 1, '2025-11-25 21:59:36', 30.00),
(62, 'الرجبان', 0.00, 1, '2025-11-25 21:59:49', 30.00),
(63, 'كاباو', 0.00, 1, '2025-11-25 22:00:11', 30.00),
(64, 'طمزين', 0.00, 1, '2025-11-25 22:00:27', 30.00),
(65, 'جادو', 0.00, 1, '2025-11-25 22:00:41', 30.00),
(66, 'العوينية', 0.00, 1, '2025-11-25 22:00:57', 30.00),
(67, 'الاصابعه', 0.00, 1, '2025-11-25 22:01:11', 30.00),
(68, 'راس لانوف', 0.00, 1, '2025-11-25 22:01:28', 30.00),
(69, 'البريقة', 0.00, 1, '2025-11-25 22:01:42', 30.00),
(70, 'بن جواد', 0.00, 1, '2025-11-25 22:02:04', 30.00),
(71, 'هراوة', 0.00, 1, '2025-11-25 22:02:14', 30.00),
(72, 'براك الشاطئ', 0.00, 1, '2025-11-25 22:03:09', 30.00),
(73, 'ام الارانب', 0.00, 1, '2025-11-25 22:03:36', 30.00),
(74, 'القطرون', 0.00, 1, '2025-11-25 22:03:52', 30.00),
(75, 'مسلاتة', 0.00, 1, '2025-11-25 22:04:07', 30.00),
(76, 'جالو', 0.00, 1, '2025-11-25 22:04:24', 30.00),
(77, 'اوجلة', 0.00, 1, '2025-11-25 22:04:38', 30.00),
(78, 'الرجمة', 0.00, 1, '2025-11-25 22:04:59', 30.00),
(79, 'سلوق', 0.00, 1, '2025-11-25 22:05:15', 30.00),
(80, 'قمينس', 0.00, 1, '2025-11-25 22:05:45', 30.00),
(81, 'الابرق', 0.00, 1, '2025-11-25 22:05:57', 30.00),
(82, 'سوسة', 0.00, 1, '2025-11-25 22:06:10', 30.00),
(83, 'التميمي', 0.00, 1, '2025-11-25 22:07:02', 30.00),
(84, 'توكرة', 0.00, 1, '2025-11-25 22:07:15', 30.00),
(85, 'قصر ليبيا', 0.00, 1, '2025-11-25 22:07:32', 30.00),
(86, 'دريانة', 0.00, 1, '2025-11-25 22:07:46', 30.00),
(87, 'سوق الخميس', 0.00, 1, '2025-11-25 22:08:04', 30.00),
(88, 'السبيعة', 0.00, 1, '2025-11-25 22:08:20', 30.00),
(89, 'القواليش', 0.00, 1, '2025-11-25 22:08:33', 30.00),
(90, 'بدر', 0.00, 1, '2025-11-25 22:08:51', 30.00),
(91, 'وازن', 0.00, 1, '2025-11-25 22:09:08', 30.00),
(92, 'بير الغنم', 0.00, 1, '2025-11-25 22:09:22', 30.00),
(93, 'العوينات', 0.00, 1, '2025-11-25 22:09:40', 30.00),
(94, 'أبو قرين', 0.00, 1, '2025-11-25 22:09:56', 30.00),
(95, 'الجوش', 0.00, 1, '2025-11-25 22:10:09', 30.00),
(96, 'القلعة', 0.00, 1, '2025-11-25 22:10:22', 30.00),
(97, 'تراغن', 0.00, 1, '2025-11-25 22:10:34', 30.00),
(98, 'سمنو', 0.00, 1, '2025-11-25 22:10:47', 30.00),
(99, 'وادي عتبة', 0.00, 1, '2025-11-25 22:11:05', 30.00),
(101, 'مزدة', 0.00, 1, '2025-11-25 22:12:12', 30.00),
(102, 'الشويرف', 0.00, 1, '2025-11-25 22:12:36', 30.00),
(103, 'الجفرة', 0.00, 1, '2025-11-25 22:12:54', 30.00),
(104, 'تاورغاء', 0.00, 1, '2025-11-25 22:13:16', 30.00),
(105, 'تاجوراء', 0.00, 1, '2025-11-25 22:13:30', 30.00),
(106, 'جنزور', 0.00, 1, '2025-11-25 22:13:51', 30.00),
(107, 'تاكنس', 0.00, 1, '2025-11-25 22:14:14', 30.00),
(108, 'سيناوين', 0.00, 1, '2025-11-25 22:14:36', 30.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `region` varchar(100) DEFAULT NULL,
  `address` text,
  `national_id` varchar(20) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account` varchar(50) DEFAULT NULL,
  `wallet_balance` decimal(10,2) DEFAULT '0.00',
  `total_earnings` decimal(10,2) DEFAULT '0.00',
  `total_withdrawn` decimal(10,2) DEFAULT '0.00',
  `is_active` tinyint(1) DEFAULT '1',
  `last_login` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `user_type` enum('تاجر','مسوق') DEFAULT 'مسوق',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `balance` decimal(10,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullname`, `email`, `phone`, `region`, `address`, `national_id`, `bank_name`, `bank_account`, `wallet_balance`, `total_earnings`, `total_withdrawn`, `is_active`, `last_login`, `password`, `reset_token`, `reset_token_expiry`, `user_type`, `created_at`, `updated_at`, `balance`) VALUES
(5, 'B', 'b@gmail.com', '01228721331', '', '', '', '', '', 0.00, 0.00, 0.00, 1, NULL, '$2y$10$kqaVPjliKqz.oU2ZQ0bHx.tUm..IEYISTTPsv/4YFiSvRzyaDSpNq', NULL, NULL, 'مسوق', '2025-11-16 19:13:19', '2025-11-16 19:13:19', 0.00),
(6, 'Belal', 'medhatomar555@gmail.com', '01228721331', '', '', '', '', '', 0.00, 0.00, 0.00, 1, NULL, '$2y$10$VycE0vAPGwkBfXeUq5ig1uE4I95VHky8DHUUVmaxoZzcepnjWgYCa', NULL, NULL, 'مسوق', '2025-11-16 19:17:17', '2025-11-16 19:17:17', 0.00),
(7, 'Belal0', 'medhatomar5@gmail.com', '01228721331', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 1, NULL, '123456', NULL, NULL, 'مسوق', '2025-11-16 19:26:14', '2025-11-16 19:26:14', 0.00),
(8, 'Belal Medhat', 'medhatomar5555@gmail.com', '01228721331', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 1, '2025-11-25 22:21:39', '$2y$10$ryiOWA6vrwcVeFGu1lUqL.hv8TVmwvygeZcoPa0/ksaV2G3UkBcuy', NULL, NULL, 'مسوق', '2025-11-16 21:22:57', '2025-11-25 22:21:39', 0.00),
(9, 'Belal Medhat', 'medhatomar@gmail.com', '01228721331', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 1, '2025-11-21 17:39:54', '$2y$10$h1YPOdTEen3vzquQDQzJzOom6gVTfu8bo2t0knKh/29JujaBSgjay', NULL, NULL, 'تاجر', '2025-11-16 23:58:24', '2025-11-21 17:39:54', 0.00),
(10, 'Medhat', 'medhat@gmail.com', '0123456789', '', '', '', '', '', 0.00, 0.00, 0.00, 1, '2025-11-26 00:20:48', '$2y$10$FSTJ6RETOgoV1tQZgxYwv.DfePc.bHZT/EJtI1279nZ1JA/luR.DO', NULL, NULL, 'تاجر', '2025-11-17 00:05:26', '2025-11-26 00:20:48', 0.00),
(11, 'omar', 'omar@gmail.com', '0123654987', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 1, '2025-11-17 01:02:33', '$2y$10$pjlwEz2WNF5.XAIbTdvlfOLe.it6lflPD6NmakCdfhg6IvDvaCH/K', NULL, NULL, 'تاجر', '2025-11-17 01:02:24', '2025-11-17 01:02:33', 0.00),
(12, 'ooo', 'or@gmail.com', '03698745326', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 1, '2025-11-17 01:19:30', '$2y$10$bz7tKF2oWxwLgVRa3Uuute6RH7DKiSCTY/wK8BIVLJkiIGGkYF3VC', NULL, NULL, 'تاجر', '2025-11-17 01:15:32', '2025-11-17 01:19:30', 0.00),
(13, 'om', 'omar5555@gmail.com', '01478965326', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 1, '2025-11-17 01:18:46', '$2y$10$8Gc7zXQaMS9nRHvsva/pAe6e5Hkj6usnV/u4YkrPzBuNWp9AdC1nu', NULL, NULL, 'مسوق', '2025-11-17 01:18:39', '2025-11-17 01:18:46', 0.00),
(14, 'Belal Medhat', 'm4@gmail.com', '01225558721331', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 1, '2025-11-19 18:12:08', '$2y$10$IVyg/2XoibtSvnEk6ys9NeoJ23nAag7l4zLMmnenICovRj9ltJIZO', NULL, NULL, 'مسوق', '2025-11-19 18:04:07', '2025-11-19 18:12:08', 0.00),
(15, 'M', 'M@gmail.com', '0122872133102', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 1, '2025-11-23 22:44:11', '$2y$10$6KLPWco1ZEgHiv.xvSPTMueMTbG3CuSsb6S1qWP2QjVH8rTe4osP2', NULL, NULL, 'تاجر', '2025-11-21 17:07:30', '2025-11-23 22:44:11', 0.00),
(16, 'B', 'Bd@gmail.com', '0122872133177', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 1, '2025-11-21 17:13:54', '$2y$10$3Eahk36vSbGBVZ8X9JJl5.LJrbdjWd7KPZVIZxNuuyxqvzzJJCi6S', NULL, NULL, 'مسوق', '2025-11-21 17:13:35', '2025-11-21 17:13:54', 0.00),
(17, 'S', 'S@gmail.com', '0122872122331', 'Alexandria', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 1, '2025-11-23 22:31:25', '$2y$10$cAiOLLt3gqsYvmYl9WQQJOQRFboi1k5dLmj3Ysp3Jft4LZzknZLo6', NULL, NULL, 'تاجر', '2025-11-23 22:31:07', '2025-11-23 22:31:25', 0.00),
(18, 'Y', 'Y@gmail.com', '0147852399', 'Alexandria', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 1, '2025-11-24 20:20:07', '$2y$10$NN0I/RAjHBtovTzhwgearuFC2AZZ5S3H.uwwRUx8/YMehtKCyLUSK', NULL, NULL, 'مسوق', '2025-11-24 20:19:57', '2025-11-24 20:20:07', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `withdrawals`
--

CREATE TABLE `withdrawals` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('قيد المراجعة','مكتمل','مرفوض') COLLATE utf8mb4_unicode_ci DEFAULT 'قيد المراجعة',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `withdrawals`
--

INSERT INTO `withdrawals` (`id`, `user_id`, `amount`, `phone`, `status`, `created_at`, `processed_at`) VALUES
(1, 6, 10.00, '01228721331', 'قيد المراجعة', '2025-11-16 20:15:05', NULL),
(2, 8, 20.00, '01228721331', 'مكتمل', '2025-11-16 23:35:21', '2025-11-16 23:35:31'),
(3, 10, 100.00, '01228721331', 'مرفوض', '2025-11-17 00:18:23', '2025-11-17 00:18:34'),
(4, 10, 25.00, '01228721331', 'مكتمل', '2025-11-23 18:09:15', '2025-11-23 18:18:05'),
(5, 8, 30.00, '01228721331', 'مرفوض', '2025-11-24 20:40:46', '2025-11-24 20:42:13'),
(6, 8, 30.00, '01228721331', 'قيد المراجعة', '2025-11-24 20:42:20', NULL),
(7, 8, 30.00, '01228721331', 'قيد المراجعة', '2025-11-24 20:42:23', NULL),
(8, 10, 80.00, '01228721331', 'مكتمل', '2025-11-25 12:58:14', '2025-11-25 13:05:06');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `balance_transactions`
--
ALTER TABLE `balance_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `commission_history`
--
ALTER TABLE `commission_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `marketer_balance`
--
ALTER TABLE `marketer_balance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `product_inventory`
--
ALTER TABLE `product_inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `shipping_cities`
--
ALTER TABLE `shipping_cities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `city_name` (`city_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_phone` (`phone`),
  ADD KEY `idx_user_type` (`user_type`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `balance_transactions`
--
ALTER TABLE `balance_transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commission_history`
--
ALTER TABLE `commission_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `marketer_balance`
--
ALTER TABLE `marketer_balance`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `product_inventory`
--
ALTER TABLE `product_inventory`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=148;

--
-- AUTO_INCREMENT for table `shipping_cities`
--
ALTER TABLE `shipping_cities`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `withdrawals`
--
ALTER TABLE `withdrawals`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `balance_transactions`
--
ALTER TABLE `balance_transactions`
  ADD CONSTRAINT `balance_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `balance_transactions_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `commission_history`
--
ALTER TABLE `commission_history`
  ADD CONSTRAINT `commission_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `commission_history_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `marketer_balance`
--
ALTER TABLE `marketer_balance`
  ADD CONSTRAINT `marketer_balance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_inventory`
--
ALTER TABLE `product_inventory`
  ADD CONSTRAINT `product_inventory_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD CONSTRAINT `withdrawals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`root`@`localhost` EVENT `auto_fix_every_minute` ON SCHEDULE EVERY 1 MINUTE STARTS '2025-11-26 01:50:57' ON COMPLETION NOT PRESERVE ENABLE DO UPDATE orders o
    LEFT JOIN shipping_cities sc ON o.shipping_city_id = sc.id
    SET 
        o.shipping_city_name = CASE 
            WHEN o.shipping_city_id IS NULL OR o.shipping_city_id = 0 THEN 'مدينة غير محددة'
            WHEN sc.city_name IS NOT NULL AND sc.city_name != '' THEN sc.city_name
            ELSE 'مدينة غير معروفة'
        END,
        o.shipping_cost = CASE 
            WHEN o.shipping_cost IS NULL OR o.shipping_cost = 0 THEN 
                CASE 
                    WHEN sc.shipping_cost IS NOT NULL AND sc.shipping_cost > 0 THEN sc.shipping_cost
                    ELSE 30.00
                END
            ELSE o.shipping_cost
        END
    WHERE o.shipping_city_name IS NULL OR o.shipping_city_name = '' OR o.shipping_cost IS NULL OR o.shipping_cost = 0$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
