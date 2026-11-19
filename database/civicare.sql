-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 24, 2026 at 02:20 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `civicare`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attachments`
--

CREATE TABLE `attachments` (
  `id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `id` int(11) NOT NULL,
  `tracking_id` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `landmark` varchar(255) DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('pending','validated','rejected','assigned','in_progress','resolved','escalated','closed') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `rejection_date` timestamp NULL DEFAULT NULL,
  `escalation_reason` text DEFAULT NULL,
  `escalated_by` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `validated_by` int(11) DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `validation_date` timestamp NULL DEFAULT NULL,
  `assignment_date` timestamp NULL DEFAULT NULL,
  `escalation_date` timestamp NULL DEFAULT NULL,
  `resolution_date` timestamp NULL DEFAULT NULL,
  `closing_remarks` text DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `citizen_feedback` text DEFAULT NULL,
  `citizen_rating` int(11) DEFAULT NULL CHECK (`citizen_rating` >= 1 and `citizen_rating` <= 5),
  `parent_complaint_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `assigned_admin_id` int(11) DEFAULT NULL,
  `assigned_staff_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `complaints`
--

INSERT INTO `complaints` (`id`, `tracking_id`, `user_id`, `department_id`, `title`, `description`, `location`, `landmark`, `priority`, `status`, `rejection_reason`, `rejection_date`, `escalation_reason`, `escalated_by`, `assigned_to`, `assigned_by`, `validated_by`, `resolved_by`, `validation_date`, `assignment_date`, `escalation_date`, `resolution_date`, `closing_remarks`, `resolution_notes`, `citizen_feedback`, `citizen_rating`, `parent_complaint_id`, `created_at`, `updated_at`, `assigned_admin_id`, `assigned_staff_id`) VALUES
(1, 'CIV2602545ADF', 9, 3, 'Pothole road', 'the area has so many potholes near by indistry area.', 'Lat: 20.040719, Lng: 73.782694', 'nashik', 'medium', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-03 11:23:25', '2026-02-03 11:23:25', NULL, NULL),
(2, 'CIV2602DA7D35', 9, 3, 'Pothole road', 'the area has so many potholes near by indistry area.', 'Lat: 20.040719, Lng: 73.782694', 'nashik', 'medium', 'closed', NULL, NULL, 'attention needed.', 1, 5, 2, 2, 1, '2026-02-09 04:16:35', '2026-02-09 04:16:55', '2026-02-11 06:38:33', '2026-02-11 06:38:49', 'Complaint resolved successfully.', 'solved.', 'very good service.', 5, NULL, '2026-02-03 11:28:21', '2026-02-13 17:49:27', NULL, NULL),
(3, 'CIV26020BAF85', 9, 2, 'electric pole fall', 'electric pole in our area is disconnected.', 'Lat: 18.585218, Lng: 73.754236', 'Wakad', 'high', 'in_progress', NULL, NULL, 'need attention', 1, 4, 2, 2, NULL, '2026-02-09 06:45:54', '2026-02-09 06:46:11', '2026-02-11 06:47:39', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 06:41:42', '2026-02-11 06:49:06', NULL, NULL),
(4, 'CIV2602A8F469', 21, 1, 'water shortage', 'There is water shortage in the Pandit Colony area from 2 days.', 'Lat: 18.585250, Lng: 73.754204', 'Sindhu Sagar Academy', 'medium', 'escalated', NULL, NULL, 'requires urgent look up.', 1, 3, 1, 2, NULL, '2026-02-11 15:03:10', '2026-02-11 15:09:08', '2026-02-11 15:09:34', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-11 14:54:46', '2026-02-11 15:09:34', NULL, NULL),
(5, 'CIV260214EA82', 22, 5, 'building renovation', 'One of the buildings in our area needs renovation urgently.', 'Lat: 18.585200, Lng: 73.754248', 'Wakad', 'medium', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-13 17:56:37', '2026-02-13 17:56:37', NULL, NULL),
(6, 'CIV26020FFFA5', 23, 4, 'sanitation needed', 'In our area there has been no sanitation for three days now; kindly do it.', 'Lat: 18.585180, Lng: 73.754222', 'Sindhu Sagar Academy', 'medium', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-13 19:07:21', '2026-02-13 19:07:21', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `complaint_attachments`
--

CREATE TABLE `complaint_attachments` (
  `id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_comments`
--

CREATE TABLE `complaint_comments` (
  `id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `is_internal` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_feedback`
--

CREATE TABLE `complaint_feedback` (
  `id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `feedback_text` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_updates`
--

CREATE TABLE `complaint_updates` (
  `id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `remarks` text DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `complaint_updates`
--

INSERT INTO `complaint_updates` (`id`, `complaint_id`, `user_id`, `old_status`, `new_status`, `remarks`, `attachment_path`, `created_at`) VALUES
(1, 2, 9, NULL, 'pending', 'Complaint filed by citizen', NULL, '2026-02-03 11:28:21'),
(2, 2, 2, NULL, 'validated', 'Complaint validated. Priority set to: Medium. ', NULL, '2026-02-09 04:16:35'),
(3, 2, 2, NULL, 'assigned', 'Complaint assigned to staff. Priority: Medium. ', NULL, '2026-02-09 04:16:55'),
(4, 2, 2, NULL, 'escalated', 'Complaint escalated. Reason: needs urgent look up', NULL, '2026-02-09 04:17:14'),
(5, 2, 2, NULL, 'escalated', 'Complaint escalated. Reason: needs urgent look up', NULL, '2026-02-09 04:17:21'),
(6, 3, 9, NULL, 'pending', 'Complaint filed by citizen', NULL, '2026-02-09 06:41:42'),
(7, 3, 2, NULL, 'validated', 'Complaint validated. Priority set to: High. electricity disruption.', NULL, '2026-02-09 06:45:54'),
(8, 3, 2, NULL, 'assigned', 'Complaint assigned to staff. Priority: High. solve it immediately.', NULL, '2026-02-09 06:46:11'),
(9, 3, 2, NULL, 'assigned', 'Complaint assigned to staff. Priority: High. solve it immediately.', NULL, '2026-02-09 06:46:22'),
(10, 2, 1, NULL, 'in_progress', 'Work started on complaint. the compalint is forwarded to superadmin is in progress.', NULL, '2026-02-11 06:22:33'),
(11, 2, 1, NULL, 'in_progress', 'Work started on complaint. still in progress as required to make arrangements', NULL, '2026-02-11 06:37:00'),
(12, 2, 1, NULL, 'escalated', 'Complaint escalated. Reason: attention needed.', NULL, '2026-02-11 06:38:33'),
(13, 2, 1, NULL, 'resolved', 'Escalated complaint resolved. Notes: solved.', NULL, '2026-02-11 06:38:49'),
(14, 2, 1, NULL, 'closed', 'Complaint closed. Complaint resolved successfully.', NULL, '2026-02-11 06:38:56'),
(15, 3, 1, NULL, 'escalated', 'Complaint escalated. Reason: need attention', NULL, '2026-02-11 06:47:39'),
(16, 3, 1, NULL, 'in_progress', 'Work started on complaint. in progress', NULL, '2026-02-11 06:49:06'),
(17, 4, 21, NULL, 'pending', 'Complaint filed by citizen', NULL, '2026-02-11 14:54:46'),
(18, 4, 2, NULL, 'validated', 'Complaint validated. Priority set to: Medium. Eligible complaint.', NULL, '2026-02-11 15:03:10'),
(19, 4, 2, NULL, 'escalated', 'Complaint escalated. Reason: requires urgent lookup', NULL, '2026-02-11 15:05:46'),
(20, 4, 1, NULL, 'assigned', 'Complaint assigned to staff. Priority: Medium. ', NULL, '2026-02-11 15:09:08'),
(21, 4, 1, NULL, 'escalated', 'Complaint escalated. Reason: requires urgent look up.', NULL, '2026-02-11 15:09:34'),
(22, 5, 22, NULL, 'pending', 'Complaint filed by citizen', NULL, '2026-02-13 17:56:37'),
(23, 6, 23, NULL, 'pending', 'Complaint filed by citizen', NULL, '2026-02-13 19:07:21');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `message` longtext DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Water Department', 'Handles water supply, pipeline, and drainage complaints', 1, '2026-02-03 04:07:53', '2026-02-03 04:07:53'),
(2, 'Electricity Department', 'Handles power supply, electrical faults, and street light complaints', 1, '2026-02-03 04:07:53', '2026-02-03 04:07:53'),
(3, 'Road Department', 'Handles road maintenance, potholes, and construction complaints', 1, '2026-02-03 04:07:53', '2026-02-03 04:07:53'),
(4, 'Sanitation Department', 'Handles garbage collection and cleanliness complaints', 1, '2026-02-03 04:07:53', '2026-02-03 04:07:53'),
(5, 'Public Works Department', 'Handles public building maintenance and infrastructure', 1, '2026-02-03 04:07:53', '2026-02-03 04:07:53'),
(6, 'Parks Department', 'Handles parks, gardens, and green spaces maintenance', 1, '2026-02-03 04:07:53', '2026-02-03 04:07:53');

-- --------------------------------------------------------

--
-- Table structure for table `department_announcements`
--

CREATE TABLE `department_announcements` (
  `id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_queue`
--

CREATE TABLE `email_queue` (
  `id` int(11) NOT NULL,
  `recipient_email` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `last_attempt` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sent_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `notification_type` enum('system','complaint','department','announcement') DEFAULT 'system',
  `is_read` tinyint(1) DEFAULT 0,
  `complaint_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `notification_type`, `is_read`, `complaint_id`, `department_id`, `created_at`) VALUES
(1, 9, 'Welcome to Civicare!', 'Thank you for registering with Civicare Municipal Complaint System. You can now file and track complaints.', 'system', 1, NULL, NULL, '2026-02-03 04:28:57'),
(2, 9, 'Complaint Filed Successfully', 'Your complaint #CIV2602DA7D35 has been filed successfully and is pending validation. You can track its status using the tracking ID.', 'system', 1, 2, NULL, '2026-02-03 11:28:21'),
(3, 1, 'New Complaint Filed', 'New complaint #CIV2602DA7D35 filed in Road Department department by user. Title: Pothole road', 'system', 0, 2, NULL, '2026-02-03 11:28:21'),
(4, 2, 'New Complaint Filed', 'New complaint #CIV2602DA7D35 filed in Road Department department by user. Title: Pothole road', 'system', 0, 2, NULL, '2026-02-03 11:28:21'),
(5, 5, 'New Complaint Filed', 'New complaint #CIV2602DA7D35 filed in Road Department department by user. Title: Pothole road', 'system', 0, 2, NULL, '2026-02-03 11:28:21'),
(6, 9, 'Complaint Validated', 'Your complaint #CIV2602DA7D35 has been validated. Priority: Medium', 'system', 1, 2, NULL, '2026-02-09 04:16:35'),
(7, 9, 'Complaint Assigned', 'Your complaint #CIV2602DA7D35 has been assigned to department staff.', 'system', 1, 2, NULL, '2026-02-09 04:16:55'),
(8, 5, 'New Complaint Assigned', 'A new complaint #CIV2602DA7D35 has been assigned to you. Priority: Medium', 'system', 0, 2, NULL, '2026-02-09 04:16:55'),
(9, 1, 'Complaint Escalated', 'Complaint #CIV2602DA7D35 has been escalated. Reason: needs urgent look up', 'system', 0, 2, NULL, '2026-02-09 04:17:14'),
(10, 2, 'Complaint Escalated', 'Complaint #CIV2602DA7D35 has been escalated. Reason: needs urgent look up', 'system', 0, 2, NULL, '2026-02-09 04:17:14'),
(11, 9, 'Complaint Escalated', 'Your complaint #CIV2602DA7D35 has been escalated to higher authorities for review.', 'system', 1, 2, NULL, '2026-02-09 04:17:14'),
(12, 1, 'Complaint Escalated', 'Complaint #CIV2602DA7D35 has been escalated. Reason: needs urgent look up', 'system', 0, 2, NULL, '2026-02-09 04:17:21'),
(13, 2, 'Complaint Escalated', 'Complaint #CIV2602DA7D35 has been escalated. Reason: needs urgent look up', 'system', 0, 2, NULL, '2026-02-09 04:17:21'),
(14, 9, 'Complaint Escalated', 'Your complaint #CIV2602DA7D35 has been escalated to higher authorities for review.', 'system', 1, 2, NULL, '2026-02-09 04:17:21'),
(15, 9, 'Complaint Filed Successfully', 'Your complaint #CIV26020BAF85 has been filed successfully and is pending validation. You can track its status using the tracking ID.', 'system', 1, 3, NULL, '2026-02-09 06:41:42'),
(16, 1, 'New Complaint Filed', 'New complaint #CIV26020BAF85 filed in Electricity Department department by user. Title: electric pole fall', 'system', 0, 3, NULL, '2026-02-09 06:41:42'),
(17, 2, 'New Complaint Filed', 'New complaint #CIV26020BAF85 filed in Electricity Department department by user. Title: electric pole fall', 'system', 0, 3, NULL, '2026-02-09 06:41:42'),
(18, 4, 'New Complaint Filed', 'New complaint #CIV26020BAF85 filed in Electricity Department department by user. Title: electric pole fall', 'system', 0, 3, NULL, '2026-02-09 06:41:42'),
(19, 9, 'Complaint Validated', 'Your complaint #CIV26020BAF85 has been validated. Priority: High', 'system', 1, 3, NULL, '2026-02-09 06:45:54'),
(20, 9, 'Complaint Assigned', 'Your complaint #CIV26020BAF85 has been assigned to department staff.', 'system', 1, 3, NULL, '2026-02-09 06:46:11'),
(21, 4, 'New Complaint Assigned', 'A new complaint #CIV26020BAF85 has been assigned to you. Priority: High', 'system', 0, 3, NULL, '2026-02-09 06:46:11'),
(22, 9, 'Complaint Assigned', 'Your complaint #CIV26020BAF85 has been assigned to department staff.', 'system', 1, 3, NULL, '2026-02-09 06:46:22'),
(23, 4, 'New Complaint Assigned', 'A new complaint #CIV26020BAF85 has been assigned to you. Priority: High', 'system', 0, 3, NULL, '2026-02-09 06:46:22'),
(24, 9, 'Complaint In Progress', 'Work has started on your complaint #CIV2602DA7D35.', 'system', 1, 2, NULL, '2026-02-11 06:22:33'),
(25, 9, 'Complaint In Progress', 'Work has started on your complaint #CIV2602DA7D35.', 'system', 1, 2, NULL, '2026-02-11 06:37:00'),
(26, 1, 'Complaint Escalated', 'Complaint #CIV2602DA7D35 has been escalated. Reason: attention needed.', 'system', 0, 2, NULL, '2026-02-11 06:38:33'),
(27, 2, 'Complaint Escalated', 'Complaint #CIV2602DA7D35 has been escalated. Reason: attention needed.', 'system', 0, 2, NULL, '2026-02-11 06:38:33'),
(28, 9, 'Complaint Escalated', 'Your complaint #CIV2602DA7D35 has been escalated to higher authorities for review.', 'system', 1, 2, NULL, '2026-02-11 06:38:33'),
(29, 9, 'Complaint Resolved', 'Your complaint #CIV2602DA7D35 has been resolved. Please provide your feedback.', 'system', 1, 2, NULL, '2026-02-11 06:38:49'),
(30, 9, 'Complaint Closed', 'Your complaint #CIV2602DA7D35 has been closed.', 'system', 1, 2, NULL, '2026-02-11 06:38:56'),
(31, 1, 'Complaint Escalated', 'Complaint #CIV26020BAF85 has been escalated. Reason: need attention', 'system', 0, 3, NULL, '2026-02-11 06:47:39'),
(32, 2, 'Complaint Escalated', 'Complaint #CIV26020BAF85 has been escalated. Reason: need attention', 'system', 0, 3, NULL, '2026-02-11 06:47:39'),
(33, 9, 'Complaint Escalated', 'Your complaint #CIV26020BAF85 has been escalated to higher authorities for review.', 'system', 1, 3, NULL, '2026-02-11 06:47:39'),
(34, 9, 'Complaint In Progress', 'Work has started on your complaint #CIV26020BAF85.', 'system', 1, 3, NULL, '2026-02-11 06:49:06'),
(35, 21, 'Welcome to Civicare!', 'Thank you for registering with Civicare Municipal Complaint System. You can now file and track complaints.', 'system', 1, NULL, NULL, '2026-02-11 14:52:15'),
(36, 21, 'Complaint Filed Successfully', 'Your complaint #CIV2602A8F469 has been filed successfully and is pending validation. You can track its status using the tracking ID.', 'system', 1, 4, NULL, '2026-02-11 14:54:46'),
(37, 1, 'New Complaint Filed', 'New complaint #CIV2602A8F469 filed in Water Department department by . Title: water shortage', 'system', 0, 4, NULL, '2026-02-11 14:54:46'),
(38, 2, 'New Complaint Filed', 'New complaint #CIV2602A8F469 filed in Water Department department by . Title: water shortage', 'system', 0, 4, NULL, '2026-02-11 14:54:46'),
(39, 3, 'New Complaint Filed', 'New complaint #CIV2602A8F469 filed in Water Department department by . Title: water shortage', 'system', 0, 4, NULL, '2026-02-11 14:54:46'),
(40, 21, 'Complaint Validated', 'Your complaint #CIV2602A8F469 has been validated. Priority: Medium', 'system', 0, 4, NULL, '2026-02-11 15:03:10'),
(41, 1, 'Complaint Escalated', 'Complaint #CIV2602A8F469 has been escalated. Reason: requires urgent lookup', 'system', 0, 4, NULL, '2026-02-11 15:05:46'),
(42, 2, 'Complaint Escalated', 'Complaint #CIV2602A8F469 has been escalated. Reason: requires urgent lookup', 'system', 0, 4, NULL, '2026-02-11 15:05:46'),
(43, 21, 'Complaint Escalated', 'Your complaint #CIV2602A8F469 has been escalated to higher authorities for review.', 'system', 0, 4, NULL, '2026-02-11 15:05:46'),
(44, 21, 'Complaint Assigned', 'Your complaint #CIV2602A8F469 has been assigned to department staff.', 'system', 0, 4, NULL, '2026-02-11 15:09:08'),
(45, 3, 'New Complaint Assigned', 'A new complaint #CIV2602A8F469 has been assigned to you. Priority: Medium', 'system', 0, 4, NULL, '2026-02-11 15:09:08'),
(46, 1, 'Complaint Escalated', 'Complaint #CIV2602A8F469 has been escalated. Reason: requires urgent look up.', 'system', 0, 4, NULL, '2026-02-11 15:09:34'),
(47, 2, 'Complaint Escalated', 'Complaint #CIV2602A8F469 has been escalated. Reason: requires urgent look up.', 'system', 0, 4, NULL, '2026-02-11 15:09:34'),
(48, 21, 'Complaint Escalated', 'Your complaint #CIV2602A8F469 has been escalated to higher authorities for review.', 'system', 0, 4, NULL, '2026-02-11 15:09:34'),
(49, 22, 'Welcome to Civicare!', 'Thank you for registering with Civicare Municipal Complaint System. You can now file and track complaints.', 'system', 0, NULL, NULL, '2026-02-13 17:55:10'),
(50, 22, 'Complaint Filed Successfully', 'Your complaint #CIV260214EA82 has been filed successfully and is pending validation. You can track its status using the tracking ID.', 'system', 1, 5, NULL, '2026-02-13 17:56:37'),
(51, 1, 'New Complaint Filed', 'New complaint #CIV260214EA82 filed in Public Works Department department by Jayesh Nikam. Title: building renovation', 'system', 0, 5, NULL, '2026-02-13 17:56:37'),
(52, 2, 'New Complaint Filed', 'New complaint #CIV260214EA82 filed in Public Works Department department by Jayesh Nikam. Title: building renovation', 'system', 0, 5, NULL, '2026-02-13 17:56:37'),
(53, 7, 'New Complaint Filed', 'New complaint #CIV260214EA82 filed in Public Works Department department by Jayesh Nikam. Title: building renovation', 'system', 0, 5, NULL, '2026-02-13 17:56:37'),
(54, 23, 'Welcome to Civicare!', 'Thank you for registering with Civicare Municipal Complaint System. You can now file and track complaints.', 'system', 1, NULL, NULL, '2026-02-13 19:06:18'),
(55, 23, 'Complaint Filed Successfully', 'Your complaint #CIV26020FFFA5 has been filed successfully and is pending validation. You can track its status using the tracking ID.', 'system', 1, 6, NULL, '2026-02-13 19:07:21'),
(56, 1, 'New Complaint Filed', 'New complaint #CIV26020FFFA5 filed in Sanitation Department department by . Title: sanitation needed', 'system', 0, 6, NULL, '2026-02-13 19:07:21'),
(57, 2, 'New Complaint Filed', 'New complaint #CIV26020FFFA5 filed in Sanitation Department department by . Title: sanitation needed', 'system', 0, 6, NULL, '2026-02-13 19:07:21'),
(58, 6, 'New Complaint Filed', 'New complaint #CIV26020FFFA5 filed in Sanitation Department department by . Title: sanitation needed', 'system', 0, 6, NULL, '2026-02-13 19:07:21');

-- --------------------------------------------------------

--
-- Table structure for table `performance_metrics`
--

CREATE TABLE `performance_metrics` (
  `id` int(11) NOT NULL,
  `metric_date` date NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `total_complaints` int(11) DEFAULT 0,
  `resolved_complaints` int(11) DEFAULT 0,
  `pending_complaints` int(11) DEFAULT 0,
  `avg_resolution_time` int(11) DEFAULT NULL,
  `citizen_satisfaction_rating` decimal(3,2) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `site_name` varchar(255) NOT NULL DEFAULT 'Civicare Municipal Complaint System',
  `site_email` varchar(255) NOT NULL DEFAULT 'admin@civicare.com',
  `site_phone` varchar(50) DEFAULT NULL,
  `site_address` text DEFAULT NULL,
  `site_description` text DEFAULT NULL,
  `enable_registration` tinyint(1) DEFAULT 1,
  `enable_complaints` tinyint(1) DEFAULT 1,
  `maintenance_mode` tinyint(1) DEFAULT 0,
  `notify_new_complaints` tinyint(1) DEFAULT 1,
  `smtp_host` varchar(255) DEFAULT NULL,
  `smtp_port` int(11) DEFAULT 587,
  `smtp_username` varchar(255) DEFAULT NULL,
  `smtp_password` varchar(255) DEFAULT NULL,
  `smtp_from_email` varchar(255) DEFAULT 'admin@civicare.com',
  `smtp_from_name` varchar(255) DEFAULT 'Civicare Admin',
  `smtp_encryption` varchar(10) DEFAULT 'tls',
  `enable_smtp` tinyint(1) DEFAULT 0,
  `notify_on_new_complaint` tinyint(1) DEFAULT 1,
  `notify_on_complaint_status_change` tinyint(1) DEFAULT 1,
  `notify_on_staff_assignment` tinyint(1) DEFAULT 1,
  `notify_on_citizen_registration` tinyint(1) DEFAULT 1,
  `notify_on_staff_registration` tinyint(1) DEFAULT 1,
  `notify_on_escalation` tinyint(1) DEFAULT 1,
  `email_notifications` tinyint(1) DEFAULT 1,
  `sms_notifications` tinyint(1) DEFAULT 0,
  `notification_recipients` text DEFAULT 'admin@civicare.com',
  `default_priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `auto_assign_complaints` tinyint(1) DEFAULT 0,
  `enable_citizen_rating` tinyint(1) DEFAULT 1,
  `require_evidence` tinyint(1) DEFAULT 0,
  `max_evidence_size` int(11) DEFAULT 10,
  `allowed_evidence_types` varchar(255) DEFAULT 'jpg,jpeg,png,pdf,doc,docx',
  `complaint_expiry_days` int(11) DEFAULT 30,
  `auto_close_resolved_days` int(11) DEFAULT 7,
  `enable_escalation` tinyint(1) DEFAULT 1,
  `escalation_days` int(11) DEFAULT 3,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `site_name`, `site_email`, `site_phone`, `site_address`, `site_description`, `enable_registration`, `enable_complaints`, `maintenance_mode`, `notify_new_complaints`, `smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `smtp_from_email`, `smtp_from_name`, `smtp_encryption`, `enable_smtp`, `notify_on_new_complaint`, `notify_on_complaint_status_change`, `notify_on_staff_assignment`, `notify_on_citizen_registration`, `notify_on_staff_registration`, `notify_on_escalation`, `email_notifications`, `sms_notifications`, `notification_recipients`, `default_priority`, `auto_assign_complaints`, `enable_citizen_rating`, `require_evidence`, `max_evidence_size`, `allowed_evidence_types`, `complaint_expiry_days`, `auto_close_resolved_days`, `enable_escalation`, `escalation_days`, `created_at`, `updated_at`) VALUES
(1, 'Civicare Municipal Complaint System', 'admin@civicare.com', NULL, NULL, NULL, 1, 1, 0, 1, 'smtp.gmail.com', 587, 'samadhan000000@gmail.com', 'lnjd zatr wibi iphf', 'samadhan000000@gmail.com', 'Civicare Admin', 'tls', 1, 1, 1, 1, 1, 1, 1, 1, 0, 'admin@civicare.com, othersites141@gmail.com', 'medium', 0, 1, 0, 10, 'jpg,jpeg,png,pdf,doc,docx', 30, 7, 1, 3, '2026-02-03 09:24:32', '2026-02-18 16:12:09');

-- --------------------------------------------------------

--
-- Table structure for table `sms_queue`
--

CREATE TABLE `sms_queue` (
  `id` int(11) NOT NULL,
  `recipient_phone` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `last_attempt` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sent_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `role` enum('citizen','staff','admin','super_admin') DEFAULT 'citizen',
  `department_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `password_reset_required` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `phone`, `address`, `role`, `department_id`, `is_active`, `last_login`, `password_reset_required`, `created_at`, `updated_at`, `deleted_at`, `created_by`, `updated_by`) VALUES
(1, 'superadmin', 'superadmin@civicare.com', '$2y$10$9JdSaqKBHzc3z.hcDBp8b.zW4iIcymlqjnqcD/pTmc7DjXq4cGWR6', 'Super Administrator', NULL, NULL, 'super_admin', NULL, 1, NULL, 0, '2026-02-03 04:07:53', '2026-02-03 04:07:53', NULL, NULL, NULL),
(2, 'admin', 'admin@civicare.com', '$2y$10$4.gMtslcaJi0Od10E641H.Y1G3xwwNC2H4fgA5ZEECmSfdiXQGo7C', 'System Administrator', NULL, NULL, 'admin', NULL, 1, NULL, 0, '2026-02-03 04:07:53', '2026-02-03 04:07:53', NULL, NULL, NULL),
(3, 'water_staff', 'water.staff@civicare.com', '$2y$10$NNEmn6sTsgn4yZ5WkI03e.37Xzy3DD1a/cfs7Dmafngz6iNRMvioq', 'Water Department Staff', NULL, NULL, 'staff', 1, 1, NULL, 0, '2026-02-03 04:07:53', '2026-02-03 04:07:53', NULL, NULL, NULL),
(4, 'electric_staff', 'electric.staff@civicare.com', '$2y$10$DWj0PEJnuMW3py25qaAgFOi9CT3jj5qi3EGNqoA8zAvdrVj3zafGK', 'Electricity Department Staff', NULL, NULL, 'staff', 2, 1, NULL, 0, '2026-02-03 04:07:53', '2026-02-03 04:07:53', NULL, NULL, NULL),
(5, 'road_staff', 'road.staff@civicare.com', '$2y$10$djl2Os5Me7zWWAIwyMXMo.tQR1kS1LF6qe6xGBj/Fp9f43Rsh8XHS', 'Road Department Staff', NULL, NULL, 'staff', 3, 1, NULL, 0, '2026-02-03 04:07:53', '2026-02-03 04:07:53', NULL, NULL, NULL),
(6, 'sanitation_staff', 'sanitation.staff@civicare.com', '$2y$10$fx6h646wMAVJyJpyl2hDuuIv7e8EZ5eJPrIP/I1ew1AeIyGaaC4pa', 'Sanitation Department Staff', NULL, NULL, 'staff', 4, 1, NULL, 0, '2026-02-03 04:07:53', '2026-02-03 04:07:53', NULL, NULL, NULL),
(7, 'publicworks_staff', 'publicworks.staff@civicare.com', '$2y$10$dHqoLyPp35uQsRbc2jumb.KskZ.NW/irA95NbE1Fa7RAZpMXG47ea', 'Public Works Staff', NULL, NULL, 'staff', 5, 1, NULL, 0, '2026-02-03 04:07:53', '2026-02-03 04:07:53', NULL, NULL, NULL),
(8, 'user', 'citizen@civicare.com', 'user123', 'Demo Citizen', NULL, NULL, 'citizen', NULL, 1, NULL, 0, '2026-02-03 04:07:53', '2026-02-03 04:27:46', NULL, NULL, NULL),
(9, 'user1', 'user@gmail.com', '$2y$10$H7Ldxr2MlwX1n4qNyBCQ/uQ5mSy3NR4.yUca5fxHBzbW0yRwCIQDS', 'user', '8785454785', 'nashik', 'citizen', NULL, 1, NULL, 0, '2026-02-03 04:28:57', '2026-02-03 04:28:57', NULL, NULL, NULL),
(20, 'electric2', 'electric2@civicare.com', '$2y$10$W3h.5oKkPv5S5dLwY5x5Qu5L5d5L5d5L5d5L5d5L5d5L5d5L5d5L5', 'Electric Staff 2', NULL, NULL, 'staff', 2, 1, NULL, 0, '2026-02-09 07:13:23', '2026-02-09 07:13:23', NULL, NULL, NULL),
(21, 'yogesh', 'yogesh@gmail.com4', '$2y$10$4DCHb4nnoS2Cn.XiIQXuLOYnFEALWn6glV1K/WQ8pjkUzpKeGOAfW', 'Yogesh pawar', '9654745865', 'nashik', 'citizen', NULL, 1, NULL, 0, '2026-02-11 14:52:15', '2026-02-11 14:52:15', NULL, NULL, NULL),
(22, 'jayesh', 'jn368792@gmail.com', '$2y$10$jUYgH2P6sIDNO.IjitS3ceEe95jF96PZHAh9UBrw0.9JvhZGnmieW', 'Jayesh Nikam', '9511922335', 'Pune', 'citizen', NULL, 0, NULL, 0, '2026-02-13 17:55:10', '2026-02-13 19:04:09', '2026-02-13 19:04:09', NULL, NULL),
(23, 'jayesh1', 'nikamj324@gmail.com', '$2y$10$XARr/9wzjx6XlWU2c7GNBuu2fszI.OMVHHBDTQRqKSLAR/uV45RwK', 'Jayesh Nikam', '9511922335', 'pune', 'citizen', NULL, 1, NULL, 0, '2026-02-13 19:06:18', '2026-02-13 19:06:18', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_logs`
--

CREATE TABLE `user_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `attachments`
--
ALTER TABLE `attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_complaint_id` (`complaint_id`),
  ADD KEY `idx_uploaded_by` (`uploaded_by`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tracking_id` (`tracking_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `validated_by` (`validated_by`),
  ADD KEY `resolved_by` (`resolved_by`),
  ADD KEY `idx_tracking_id` (`tracking_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_department_id` (`department_id`),
  ADD KEY `idx_assigned_to` (`assigned_to`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `assigned_admin_id` (`assigned_admin_id`),
  ADD KEY `assigned_staff_id` (`assigned_staff_id`),
  ADD KEY `escalated_by` (`escalated_by`),
  ADD KEY `idx_parent_complaint_id` (`parent_complaint_id`);

--
-- Indexes for table `complaint_attachments`
--
ALTER TABLE `complaint_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `complaint_id` (`complaint_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `complaint_comments`
--
ALTER TABLE `complaint_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `complaint_id` (`complaint_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `complaint_feedback`
--
ALTER TABLE `complaint_feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_complaint_feedback` (`complaint_id`,`user_id`),
  ADD KEY `idx_complaint_id` (`complaint_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `complaint_updates`
--
ALTER TABLE `complaint_updates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_complaint_id` (`complaint_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_department_name` (`name`);

--
-- Indexes for table `department_announcements`
--
ALTER TABLE `department_announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_department_id` (`department_id`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `email_queue`
--
ALTER TABLE `email_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `complaint_id` (`complaint_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `performance_metrics`
--
ALTER TABLE `performance_metrics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_metric` (`metric_date`,`department_id`),
  ADD KEY `idx_metric_date` (`metric_date`),
  ADD KEY `idx_department_id` (`department_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sms_queue`
--
ALTER TABLE `sms_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_department` (`department_id`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `user_logs`
--
ALTER TABLE `user_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attachments`
--
ALTER TABLE `attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `complaint_attachments`
--
ALTER TABLE `complaint_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_comments`
--
ALTER TABLE `complaint_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_feedback`
--
ALTER TABLE `complaint_feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_updates`
--
ALTER TABLE `complaint_updates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `department_announcements`
--
ALTER TABLE `department_announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_queue`
--
ALTER TABLE `email_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `performance_metrics`
--
ALTER TABLE `performance_metrics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sms_queue`
--
ALTER TABLE `sms_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `user_logs`
--
ALTER TABLE `user_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attachments`
--
ALTER TABLE `attachments`
  ADD CONSTRAINT `attachments_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attachments_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `complaints_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `complaints_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `complaints_ibfk_4` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `complaints_ibfk_5` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `complaints_ibfk_6` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `complaints_ibfk_7` FOREIGN KEY (`escalated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_parent_complaint` FOREIGN KEY (`parent_complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `complaint_attachments`
--
ALTER TABLE `complaint_attachments`
  ADD CONSTRAINT `complaint_attachments_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `complaint_attachments_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `complaint_comments`
--
ALTER TABLE `complaint_comments`
  ADD CONSTRAINT `complaint_comments_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `complaint_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `complaint_feedback`
--
ALTER TABLE `complaint_feedback`
  ADD CONSTRAINT `complaint_feedback_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `complaint_feedback_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `complaint_updates`
--
ALTER TABLE `complaint_updates`
  ADD CONSTRAINT `complaint_updates_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `complaint_updates_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `department_announcements`
--
ALTER TABLE `department_announcements`
  ADD CONSTRAINT `department_announcements_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `department_announcements_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notifications_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notifications_ibfk_4` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notifications_ibfk_5` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `performance_metrics`
--
ALTER TABLE `performance_metrics`
  ADD CONSTRAINT `performance_metrics_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_logs`
--
ALTER TABLE `user_logs`
  ADD CONSTRAINT `user_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
