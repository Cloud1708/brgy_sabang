-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 15, 2025 at 12:20 PM
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
-- Database: `qwerty`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `CreateParentAccount` (IN `p_username` VARCHAR(100), IN `p_email` VARCHAR(255), IN `p_password_hash` VARCHAR(255), IN `p_first_name` VARCHAR(100), IN `p_last_name` VARCHAR(100), IN `p_barangay` VARCHAR(100), IN `p_child_id` INT UNSIGNED, IN `p_relationship_type` ENUM('mother','father','guardian','caregiver'), IN `p_creator_user_id` INT UNSIGNED)   BEGIN
    DECLARE v_parent_user_id INT UNSIGNED;
    DECLARE v_parent_role_id INT UNSIGNED;
    DECLARE v_creator_role   VARCHAR(50);
    DECLARE v_error_msg      VARCHAR(255);

    -- Validate creator
    SELECT r.role_name INTO v_creator_role
      FROM users u JOIN roles r ON u.role_id = r.role_id
     WHERE u.user_id = p_creator_user_id;

    IF v_creator_role NOT IN ('BNS','Admin') THEN
        SET v_error_msg = 'Only BNS or Admin can create parent accounts';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_msg;
    END IF;

    SELECT role_id INTO v_parent_role_id FROM roles WHERE role_name = 'Parent';

    INSERT INTO users (username,email,password_hash,first_name,last_name,role_id,barangay,created_by_user_id)
    VALUES (p_username,p_email,p_password_hash,p_first_name,p_last_name,v_parent_role_id,p_barangay,p_creator_user_id);

    SET v_parent_user_id = LAST_INSERT_ID();

    INSERT INTO parent_child_access (parent_user_id, child_id, relationship_type, access_granted_by)
    VALUES (v_parent_user_id, p_child_id, p_relationship_type, p_creator_user_id);

    -- Link to mothers_caregivers if mother relationship (assuming child has mother_id there)
    IF p_relationship_type = 'mother' THEN
        UPDATE mothers_caregivers mc
           JOIN children c ON c.mother_id = mc.mother_id
          SET mc.user_account_id = v_parent_user_id
        WHERE c.child_id = p_child_id;
    END IF;

    SELECT v_parent_user_id AS new_parent_user_id,
           'Parent account created successfully' AS message;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `CreateStaffAccount` (IN `p_username` VARCHAR(100), IN `p_email` VARCHAR(255), IN `p_password_hash` VARCHAR(255), IN `p_first_name` VARCHAR(100), IN `p_last_name` VARCHAR(100), IN `p_role_name` ENUM('BHW','BNS'), IN `p_barangay` VARCHAR(100), IN `p_admin_user_id` INT UNSIGNED)   BEGIN
    DECLARE v_new_user_id INT UNSIGNED;
    DECLARE v_role_id     INT UNSIGNED;
    DECLARE v_admin_role  VARCHAR(50);
    DECLARE v_error_msg   VARCHAR(255);

    SELECT r.role_name INTO v_admin_role
      FROM users u JOIN roles r ON u.role_id = r.role_id
     WHERE u.user_id = p_admin_user_id;

    IF v_admin_role <> 'Admin' THEN
        SET v_error_msg = 'Only Admin can create BHW/BNS accounts';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_msg;
    END IF;

    SELECT role_id INTO v_role_id FROM roles WHERE role_name = p_role_name;

    INSERT INTO users (username,email,password_hash,first_name,last_name,role_id,barangay,created_by_user_id)
    VALUES (p_username,p_email,p_password_hash,p_first_name,p_last_name,v_role_id,p_barangay,p_admin_user_id);

    SET v_new_user_id = LAST_INSERT_ID();

    SELECT v_new_user_id AS new_user_id,
           CONCAT(p_role_name,' account created successfully') AS message;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `account_creation_log`
--

CREATE TABLE `account_creation_log` (
  `log_id` int(10) UNSIGNED NOT NULL,
  `created_user_id` int(10) UNSIGNED NOT NULL,
  `created_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `account_type` enum('BHW','BNS','Parent') NOT NULL,
  `creation_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Log of account creation events';

--
-- Dumping data for table `account_creation_log`
--

INSERT INTO `account_creation_log` (`log_id`, `created_user_id`, `created_by_user_id`, `account_type`, `creation_reason`, `created_at`) VALUES
(7, 9, 2, 'BHW', 'Account created via admin/BNS interface', '2025-10-06 08:34:30'),
(8, 9, 2, 'BHW', 'New BHW account created', '2025-10-06 08:34:30'),
(9, 10, 2, 'BHW', 'Account created via admin/BNS interface', '2025-10-08 07:36:03'),
(10, 10, 2, 'BHW', 'New BHW account created', '2025-10-08 07:36:03'),
(11, 11, 2, 'BNS', 'Account created via admin/BNS interface', '2025-10-08 07:38:06'),
(12, 11, 2, 'BNS', 'New BNS account created', '2025-10-08 07:38:06'),
(13, 12, 9, 'Parent', 'Account created via admin/BNS interface', '2025-10-08 18:58:06'),
(14, 13, 9, 'Parent', 'Account created via admin/BNS interface', '2025-10-08 19:04:46'),
(15, 14, 9, 'Parent', 'Account created via admin/BNS interface', '2025-10-08 19:06:58'),
(16, 15, 9, 'Parent', 'Account created via admin/BNS interface', '2025-10-14 19:14:35'),
(17, 16, 9, 'Parent', 'Account created via admin/BNS interface', '2025-10-15 10:20:20');

-- --------------------------------------------------------

--
-- Stand-in structure for view `account_management_overview`
-- (See below for the actual view)
--
CREATE TABLE `account_management_overview` (
`user_id` int(10) unsigned
,`username` varchar(100)
,`first_name` varchar(100)
,`last_name` varchar(100)
,`email` varchar(255)
,`role_name` varchar(50)
,`barangay` varchar(100)
,`is_active` tinyint(1)
,`created_at` timestamp
,`created_by_name` varchar(201)
,`created_by_username` varchar(100)
,`children_names` mediumtext
,`records_created` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `bns_created_parents`
-- (See below for the actual view)
--
CREATE TABLE `bns_created_parents` (
`parent_user_id` int(10) unsigned
,`parent_username` varchar(100)
,`parent_first_name` varchar(100)
,`parent_last_name` varchar(100)
,`parent_email` varchar(255)
,`account_created_date` timestamp
,`child_id` int(10) unsigned
,`child_name` varchar(255)
,`child_sex` enum('male','female')
,`child_birth_date` date
,`relationship_type` enum('mother','father','guardian','caregiver')
,`bns_user_id` int(10) unsigned
,`created_by_bns` varchar(201)
);

-- --------------------------------------------------------

--
-- Table structure for table `children`
--

CREATE TABLE `children` (
  `child_id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(120) DEFAULT NULL,
  `middle_name` varchar(120) DEFAULT NULL,
  `last_name` varchar(120) DEFAULT NULL,
  `full_name` varchar(255) GENERATED ALWAYS AS (trim(concat_ws(' ',`first_name`,`middle_name`,`last_name`))) STORED,
  `sex` enum('male','female') NOT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `height_cm` decimal(5,2) DEFAULT NULL,
  `birth_date` date NOT NULL,
  `mother_id` int(10) UNSIGNED NOT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Children registry';

--
-- Dumping data for table `children`
--

INSERT INTO `children` (`child_id`, `first_name`, `middle_name`, `last_name`, `sex`, `weight_kg`, `height_cm`, `birth_date`, `mother_id`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Baby', 'Test', 'Example', 'male', NULL, NULL, '2025-01-01', 1, 9, '2025-10-08 05:37:41', '2025-10-08 05:37:41'),
(2, 'Sepp', 'Bernard', 'Consulta', 'female', 3.50, 45.00, '2025-04-16', 1, 10, '2025-10-08 07:39:27', '2025-10-08 07:39:27'),
(3, 'Brian', 'M', 'Maines', 'male', 3.50, 45.00, '2025-10-09', 2, 9, '2025-10-08 16:34:59', '2025-10-08 16:34:59'),
(4, 'Gabrielle', 'G', 'Resuello', 'female', 3.50, 30.00, '2025-10-09', 31, 9, '2025-10-08 19:06:36', '2025-10-08 19:06:36'),
(5, 'Kaye', 'sda', 'sada', 'female', 3.50, 45.00, '2025-10-14', 41, 9, '2025-10-13 19:23:07', '2025-10-13 19:23:07'),
(6, 'sdsa', 'ds', 'sd', 'male', 2.50, 45.00, '2025-10-14', 47, 9, '2025-10-13 19:46:44', '2025-10-13 19:46:44'),
(7, 'vcvx', 'sda', 'sd', 'male', 3.50, 36.00, '2025-10-14', 47, 9, '2025-10-13 20:07:47', '2025-10-13 20:07:47'),
(8, 'sda', 'dsa', 'das', 'female', 3.50, 45.00, '2025-10-15', 45, 9, '2025-10-14 16:11:19', '2025-10-14 16:11:19'),
(9, 'fdfds', 'dfs', 'fds', 'male', 4.50, 34.00, '2025-10-15', 45, 9, '2025-10-14 16:22:25', '2025-10-14 16:22:25'),
(10, 'fs', 'wq', 'ds', 'male', 3.50, 45.00, '2025-10-15', 55, 9, '2025-10-14 19:13:54', '2025-10-14 19:13:54');

-- --------------------------------------------------------

--
-- Table structure for table `child_immunizations`
--

CREATE TABLE `child_immunizations` (
  `immunization_id` int(10) UNSIGNED NOT NULL,
  `child_id` int(10) UNSIGNED NOT NULL,
  `vaccine_id` int(10) UNSIGNED NOT NULL,
  `dose_number` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `vaccination_date` date NOT NULL,
  `vaccination_site` varchar(100) DEFAULT NULL,
  `batch_lot_number` varchar(50) DEFAULT NULL,
  `vaccine_expiry_date` date DEFAULT NULL,
  `administered_by` int(10) UNSIGNED NOT NULL,
  `next_dose_due_date` date DEFAULT NULL,
  `adverse_reactions` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Child vaccine doses';

--
-- Dumping data for table `child_immunizations`
--

INSERT INTO `child_immunizations` (`immunization_id`, `child_id`, `vaccine_id`, `dose_number`, `vaccination_date`, `vaccination_site`, `batch_lot_number`, `vaccine_expiry_date`, `administered_by`, `next_dose_due_date`, `adverse_reactions`, `notes`, `created_at`, `updated_at`) VALUES
(1, 3, 1, 1, '2025-10-08', 'Right Deltoid', 'BCG-2025-1', '2025-10-10', 9, NULL, 'wala po ate', '', '2025-10-08 16:36:03', '2025-10-08 16:36:03'),
(2, 1, 1, 1, '2025-01-01', 'Left Arm', 'BCG2025001', '2026-01-01', 9, NULL, NULL, 'Birth dose administered', '2025-10-08 17:04:59', '2025-10-08 17:04:59'),
(3, 1, 2, 1, '2025-01-01', 'Right Thigh', 'HEPB2025001', '2026-01-01', 9, NULL, NULL, 'Birth dose administered', '2025-10-08 17:04:59', '2025-10-08 17:04:59'),
(4, 1, 3, 1, '2025-02-01', 'Left Thigh', 'PENTA2025001', '2026-02-01', 9, '2025-03-01', NULL, 'First dose of PENTA', '2025-10-08 17:04:59', '2025-10-08 17:04:59'),
(5, 1, 3, 2, '2025-03-01', 'Left Thigh', 'PENTA2025002', '2026-03-01', 9, '2025-04-01', NULL, 'Second dose of PENTA', '2025-10-08 17:04:59', '2025-10-08 17:04:59'),
(6, 1, 4, 1, '2025-02-01', 'Oral', 'OPV2025001', '2026-02-01', 9, '2025-03-01', NULL, 'First dose of OPV', '2025-10-08 17:04:59', '2025-10-08 17:04:59'),
(7, 1, 4, 2, '2025-03-01', 'Oral', 'OPV2025002', '2026-03-01', 9, '2025-04-01', NULL, 'Second dose of OPV', '2025-10-08 17:04:59', '2025-10-08 17:04:59'),
(8, 2, 1, 1, '2025-04-16', 'Left Arm', 'BCG2025002', '2026-04-16', 10, NULL, NULL, 'Birth dose administered', '2025-10-08 17:04:59', '2025-10-08 17:04:59'),
(9, 2, 2, 1, '2025-04-16', 'Right Thigh', 'HEPB2025002', '2026-04-16', 10, NULL, NULL, 'Birth dose administered', '2025-10-08 17:04:59', '2025-10-08 17:04:59'),
(10, 2, 3, 1, '2025-05-16', 'Left Thigh', 'PENTA2025003', '2026-05-16', 10, '2025-06-16', NULL, 'First dose of PENTA', '2025-10-08 17:04:59', '2025-10-08 17:04:59'),
(11, 2, 4, 1, '2025-05-16', 'Oral', 'OPV2025003', '2026-05-16', 10, '2025-06-16', NULL, 'First dose of OPV', '2025-10-08 17:04:59', '2025-10-08 17:04:59'),
(12, 2, 6, 1, '2025-05-16', 'Right Thigh', 'PCV2025001', '2026-05-16', 10, '2025-06-16', NULL, 'First dose of PCV', '2025-10-08 17:04:59', '2025-10-08 17:04:59'),
(17, 3, 2, 1, '2025-10-09', '', '', NULL, 9, NULL, '', '', '2025-10-08 18:25:53', '2025-10-08 18:25:53');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(10) UNSIGNED NOT NULL,
  `event_title` varchar(255) NOT NULL,
  `event_description` text DEFAULT NULL,
  `event_type` enum('health','nutrition','vaccination','feeding','weighing','general','other') NOT NULL,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `target_audience` text DEFAULT NULL,
  `is_published` tinyint(1) DEFAULT 1,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Community events';

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`event_id`, `event_title`, `event_description`, `event_type`, `event_date`, `event_time`, `location`, `target_audience`, `is_published`, `is_completed`, `completed_at`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'hindi ko alam', '', 'vaccination', '2025-10-13', '12:25:00', 'Center', NULL, 1, 1, '2025-10-13 03:25:53', 9, '2025-10-12 19:25:46', '2025-10-12 19:25:53'),
(2, 'dsdas', '', 'vaccination', '2025-10-14', '03:50:00', 'sadsa', NULL, 1, 1, '2025-10-14 02:54:28', 9, '2025-10-13 18:50:37', '2025-10-13 18:54:28');

-- --------------------------------------------------------

--
-- Table structure for table `health_records`
--

CREATE TABLE `health_records` (
  `health_record_id` int(10) UNSIGNED NOT NULL,
  `mother_id` int(10) UNSIGNED NOT NULL,
  `consultation_date` date NOT NULL,
  `age` int(10) UNSIGNED DEFAULT NULL,
  `height_cm` decimal(5,2) DEFAULT NULL,
  `last_menstruation_date` date DEFAULT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `pregnancy_age_weeks` int(10) UNSIGNED DEFAULT NULL,
  `vaginal_bleeding` tinyint(1) DEFAULT 0,
  `urinary_infection` tinyint(1) DEFAULT 0,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `blood_pressure_systolic` int(10) UNSIGNED DEFAULT NULL,
  `blood_pressure_diastolic` int(10) UNSIGNED DEFAULT NULL,
  `high_blood_pressure` tinyint(1) DEFAULT 0,
  `fever_38_celsius` tinyint(1) DEFAULT 0,
  `pallor` tinyint(1) DEFAULT 0,
  `abnormal_abdominal_size` tinyint(1) DEFAULT 0,
  `abnormal_presentation` tinyint(1) DEFAULT 0,
  `absent_fetal_heartbeat` tinyint(1) DEFAULT 0,
  `swelling` tinyint(1) DEFAULT 0,
  `vaginal_infection` tinyint(1) DEFAULT 0,
  `hgb_result` varchar(50) DEFAULT NULL,
  `urine_result` varchar(100) DEFAULT NULL,
  `vdrl_result` varchar(50) DEFAULT NULL,
  `other_lab_results` text DEFAULT NULL,
  `iron_folate_prescription` tinyint(1) DEFAULT 0,
  `iron_folate_notes` text DEFAULT NULL,
  `additional_iodine` tinyint(1) DEFAULT 0,
  `additional_iodine_notes` text DEFAULT NULL,
  `malaria_prophylaxis` tinyint(1) DEFAULT 0,
  `malaria_prophylaxis_notes` text DEFAULT NULL,
  `breastfeeding_plan` tinyint(1) DEFAULT 0,
  `breastfeeding_plan_notes` text DEFAULT NULL,
  `danger_advice` tinyint(1) DEFAULT 0,
  `danger_advice_notes` text DEFAULT NULL,
  `dental_checkup` tinyint(1) DEFAULT 0,
  `dental_checkup_notes` text DEFAULT NULL,
  `emergency_plan` tinyint(1) DEFAULT 0,
  `emergency_plan_notes` text DEFAULT NULL,
  `general_risk` tinyint(1) DEFAULT 0,
  `general_risk_notes` text DEFAULT NULL,
  `next_visit_date` date DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Prenatal/Postnatal health records';

--
-- Dumping data for table `health_records`
--

INSERT INTO `health_records` (`health_record_id`, `mother_id`, `consultation_date`, `age`, `height_cm`, `last_menstruation_date`, `expected_delivery_date`, `pregnancy_age_weeks`, `vaginal_bleeding`, `urinary_infection`, `weight_kg`, `blood_pressure_systolic`, `blood_pressure_diastolic`, `high_blood_pressure`, `fever_38_celsius`, `pallor`, `abnormal_abdominal_size`, `abnormal_presentation`, `absent_fetal_heartbeat`, `swelling`, `vaginal_infection`, `hgb_result`, `urine_result`, `vdrl_result`, `other_lab_results`, `iron_folate_prescription`, `iron_folate_notes`, `additional_iodine`, `additional_iodine_notes`, `malaria_prophylaxis`, `malaria_prophylaxis_notes`, `breastfeeding_plan`, `breastfeeding_plan_notes`, `danger_advice`, `danger_advice_notes`, `dental_checkup`, `dental_checkup_notes`, `emergency_plan`, `emergency_plan_notes`, `general_risk`, `general_risk_notes`, `next_visit_date`, `recorded_by`, `created_at`, `updated_at`) VALUES
(2, 29, '2025-10-07', 25, 150.00, '2025-10-01', '2025-10-31', 7, 0, 0, 45.00, 110, 80, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, 9, '2025-10-06 17:01:36', '2025-10-06 17:01:36'),
(3, 29, '2025-10-07', 25, 250.00, '2025-10-08', '2025-10-08', NULL, 0, 0, 55.00, 110, 70, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, 9, '2025-10-06 17:11:59', '2025-10-06 17:11:59'),
(4, 31, '2025-10-08', 20, 152.00, '2025-10-09', '2025-10-31', 12, 1, 0, 50.00, 10, 10, 0, 0, 0, 0, 0, 0, 1, 1, 'haha', 'uti', 'hahahah', 'hahah', 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, 10, '2025-10-08 08:48:34', '2025-10-08 08:48:34'),
(5, 32, '2025-10-08', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, 9, '2025-10-08 15:44:39', '2025-10-08 15:44:39'),
(6, 31, '2025-10-12', 20, 145.00, NULL, NULL, 15, 0, 0, 50.00, 110, 80, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, '2025-10-31', 9, '2025-10-12 07:12:28', '2025-10-12 07:12:28'),
(7, 31, '2025-10-12', 20, 150.00, NULL, NULL, 16, 0, 0, 70.00, 110, 70, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 1, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, '2025-11-01', 9, '2025-10-12 07:13:23', '2025-10-12 07:13:23'),
(8, 32, '2025-10-12', NULL, NULL, NULL, NULL, 15, 0, 0, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 1, 'sa teeth', 0, NULL, 0, NULL, NULL, 9, '2025-10-12 07:40:59', '2025-10-12 07:40:59'),
(9, 31, '2025-10-12', 20, NULL, NULL, NULL, 17, 0, 0, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, 9, '2025-10-12 08:22:52', '2025-10-12 08:22:52'),
(10, 33, '2025-10-12', 26, 155.00, NULL, NULL, 4, 0, 0, 56.00, 110, 70, 0, 0, 0, 0, 0, 0, 0, 0, '12.8', 'Normal', 'Non-reactive', 'Negative for infection', 1, NULL, 1, NULL, 0, NULL, 1, NULL, 1, NULL, 0, NULL, 1, NULL, 0, NULL, '2025-11-05', 9, '2025-10-12 08:41:30', '2025-10-12 08:41:30'),
(11, 34, '2025-10-12', 25, 160.00, '2025-10-12', '2026-07-17', 0, 0, 0, 58.00, 110, 80, 0, 0, 0, 1, 0, 0, 1, 0, '11.6', 'Trace protein', 'Non-reactive', 'Normal ultrasound findings', 1, NULL, 1, NULL, 0, NULL, 1, NULL, 1, NULL, 0, NULL, 1, NULL, 1, NULL, '2025-10-28', 9, '2025-10-12 09:25:36', '2025-10-12 09:25:36'),
(12, 35, '2025-10-12', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 1, NULL, 1, NULL, NULL, 9, '2025-10-12 09:27:15', '2025-10-12 09:27:15'),
(13, 36, '2025-10-12', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 1, NULL, 0, NULL, 1, NULL, 1, NULL, NULL, 9, '2025-10-12 09:33:48', '2025-10-12 09:33:48'),
(14, 37, '2025-10-12', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 1, NULL, 1, NULL, 1, NULL, 1, NULL, 1, NULL, 1, NULL, '2025-10-15', 9, '2025-10-12 09:38:41', '2025-10-12 09:38:41'),
(15, 38, '2025-10-12', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 1, NULL, 0, NULL, 1, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, 9, '2025-10-12 09:41:56', '2025-10-12 09:41:56'),
(16, 39, '2025-10-12', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 1, 'sadad', 1, 'sdadd', 1, 'sdad', 0, NULL, 0, NULL, 0, NULL, NULL, 9, '2025-10-12 09:45:24', '2025-10-12 09:45:24'),
(17, 40, '2025-10-12', 25, NULL, '2025-03-10', '2025-12-15', 30, 0, 0, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, 9, '2025-10-12 10:16:36', '2025-10-12 10:16:36'),
(18, 41, '2025-10-12', NULL, NULL, '2025-01-07', '2025-10-12', 39, 0, 0, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, 9, '2025-10-12 10:18:12', '2025-10-12 10:18:12'),
(19, 42, '2025-10-13', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, 9, '2025-10-13 19:35:07', '2025-10-13 19:35:07'),
(20, 43, '2025-10-13', 24, NULL, NULL, NULL, 4, 0, 0, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, 9, '2025-10-13 19:37:00', '2025-10-13 19:37:00'),
(21, 44, '2025-10-13', 25, 145.00, NULL, NULL, 0, 0, 0, 50.00, 110, 80, 0, 0, 0, 0, 0, 0, 0, 0, '12.5', 'Normal', 'Non', 'none', 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, 9, '2025-10-13 19:39:21', '2025-10-13 19:39:21'),
(22, 44, '2025-10-13', 25, NULL, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, 1, 1, 1, 1, 1, 1, 1, 1, NULL, NULL, NULL, NULL, 1, NULL, 1, NULL, 1, NULL, 1, NULL, 1, NULL, 1, NULL, 1, NULL, 1, NULL, '2025-10-15', 9, '2025-10-13 19:39:57', '2025-10-13 19:39:57'),
(23, 45, '2025-10-13', NULL, NULL, '2025-10-13', '2025-10-14', 0, 0, 0, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, 9, '2025-10-13 19:44:35', '2025-10-13 19:44:35'),
(24, 46, '2025-10-13', NULL, NULL, '2025-10-07', '2026-07-13', 0, 0, 0, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, 9, '2025-10-13 19:45:40', '2025-10-13 19:45:40'),
(25, 47, '2025-10-13', NULL, NULL, '2025-10-07', '2025-10-13', 0, 0, 0, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, 9, '2025-10-13 19:46:16', '2025-10-13 19:46:16'),
(26, 48, '2025-10-13', NULL, NULL, '2025-10-01', '2025-10-14', 1, 0, 0, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, 9, '2025-10-13 19:53:55', '2025-10-13 19:53:55'),
(27, 49, '2025-10-14', 24, 150.00, '2025-10-01', '2026-07-07', 1, 0, 0, 60.00, 110, 80, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, '2025-10-30', 9, '2025-10-14 13:24:04', '2025-10-14 13:24:04'),
(28, 50, '2025-10-14', 0, NULL, '2025-10-05', '2026-07-11', 1, 0, 0, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, 9, '2025-10-14 14:05:23', '2025-10-14 14:05:23'),
(29, 51, '2025-10-14', 24, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, 9, '2025-10-14 14:41:48', '2025-10-14 14:41:48'),
(30, 52, '2025-10-14', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, 9, '2025-10-14 14:58:41', '2025-10-14 14:58:41'),
(31, 53, '2025-10-14', 24, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, 9, '2025-10-14 15:12:50', '2025-10-14 15:12:50'),
(32, 5, '2025-10-14', 25, 150.00, '2025-10-05', '2026-07-11', 1, 0, 0, 60.00, 110, 80, 0, 0, 0, 0, 0, 0, 0, 0, '12.5', 'Normal', 'Non', NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, '2025-10-16', 9, '2025-10-14 15:25:29', '2025-10-14 15:25:29'),
(33, 54, '2025-10-14', 20, 150.00, '2025-10-01', '2026-07-07', 1, 0, 0, 80.00, 110, 90, 1, 0, 0, 0, 0, 0, 0, 0, '12.5', 'normal', 'non', NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, '2025-10-30', 9, '2025-10-14 15:26:44', '2025-10-14 15:26:44'),
(34, 54, '2025-10-14', 20, 145.00, '2025-10-01', '2026-07-07', 1, 0, 0, 70.00, 110, 80, 0, 0, 0, 0, 0, 0, 0, 0, '12.5', 'Normal', 'Non-reactive', NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, '2025-10-23', 9, '2025-10-14 18:07:58', '2025-10-14 18:07:58'),
(35, 55, '2025-10-14', 25, 160.00, '2025-09-09', '2025-10-15', 5, 0, 0, 60.00, 110, 90, 1, 0, 0, 0, 0, 0, 0, 0, '12.5', 'sd', 'das', NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, '2025-10-31', 9, '2025-10-14 18:55:30', '2025-10-14 18:55:30');

-- --------------------------------------------------------

--
-- Table structure for table `immunization_schedule`
--

CREATE TABLE `immunization_schedule` (
  `schedule_id` int(10) UNSIGNED NOT NULL,
  `vaccine_id` int(10) UNSIGNED NOT NULL,
  `dose_number` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `recommended_age_months` int(10) UNSIGNED NOT NULL,
  `age_range_min_months` int(10) UNSIGNED DEFAULT NULL,
  `age_range_max_months` int(10) UNSIGNED DEFAULT NULL,
  `is_mandatory` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Recommended immunization schedule';

--
-- Dumping data for table `immunization_schedule`
--

INSERT INTO `immunization_schedule` (`schedule_id`, `vaccine_id`, `dose_number`, `recommended_age_months`, `age_range_min_months`, `age_range_max_months`, `is_mandatory`, `created_at`) VALUES
(1, 1, 1, 0, NULL, NULL, 1, '2025-10-08 16:10:10'),
(2, 2, 1, 0, NULL, NULL, 1, '2025-10-08 16:10:10'),
(3, 4, 1, 1, NULL, NULL, 1, '2025-10-08 16:10:10'),
(4, 3, 1, 1, NULL, NULL, 1, '2025-10-08 16:10:10'),
(5, 6, 1, 1, NULL, NULL, 1, '2025-10-08 16:10:10'),
(6, 4, 2, 2, NULL, NULL, 1, '2025-10-08 16:10:10'),
(7, 3, 2, 2, NULL, NULL, 1, '2025-10-08 16:10:10'),
(8, 5, 1, 3, NULL, NULL, 1, '2025-10-08 16:10:10'),
(9, 4, 3, 3, NULL, NULL, 1, '2025-10-08 16:10:10'),
(10, 3, 3, 3, NULL, NULL, 1, '2025-10-08 16:10:10'),
(11, 6, 2, 6, NULL, NULL, 1, '2025-10-08 16:10:10'),
(12, 7, 1, 9, NULL, NULL, 1, '2025-10-08 16:10:10'),
(13, 5, 2, 9, NULL, NULL, 1, '2025-10-08 16:10:10'),
(14, 6, 3, 12, NULL, NULL, 1, '2025-10-08 16:10:10'),
(15, 7, 2, 12, NULL, NULL, 1, '2025-10-08 16:10:10'),
(16, 8, 1, 24, NULL, NULL, 1, '2025-10-08 16:10:10'),
(17, 10, 1, 132, NULL, NULL, 1, '2025-10-08 16:10:10'),
(18, 9, 1, 132, NULL, NULL, 1, '2025-10-08 16:10:10'),
(19, 10, 2, 138, NULL, NULL, 1, '2025-10-08 16:10:10'),
(20, 9, 2, 144, NULL, NULL, 1, '2025-10-08 16:10:10');

-- --------------------------------------------------------

--
-- Table structure for table `labor_delivery_records`
--

CREATE TABLE `labor_delivery_records` (
  `labor_id` int(10) UNSIGNED NOT NULL,
  `mother_id` int(10) UNSIGNED NOT NULL,
  `child_id` int(10) UNSIGNED DEFAULT NULL,
  `delivery_date` date NOT NULL,
  `delivery_type` varchar(255) DEFAULT NULL,
  `place_of_delivery` varchar(255) DEFAULT NULL,
  `attendant` varchar(255) DEFAULT NULL,
  `immediate_breastfeeding` tinyint(1) NOT NULL DEFAULT 0,
  `birth_weight_grams` int(11) DEFAULT NULL,
  `postpartum_hemorrhage` tinyint(1) NOT NULL DEFAULT 0,
  `baby_alive` tinyint(1) NOT NULL DEFAULT 1,
  `baby_healthy` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `labor_delivery_records`
--

INSERT INTO `labor_delivery_records` (`labor_id`, `mother_id`, `child_id`, `delivery_date`, `delivery_type`, `place_of_delivery`, `attendant`, `immediate_breastfeeding`, `birth_weight_grams`, `postpartum_hemorrhage`, `baby_alive`, `baby_healthy`, `notes`, `recorded_by`, `created_at`) VALUES
(1, 41, NULL, '2025-10-12', 'Normal Spontaneous Vaginal Delivery', 'Hospital', 'Doctor', 1, 2500, 0, 1, 1, NULL, 9, '2025-10-12 11:56:25'),
(2, 41, NULL, '2025-10-12', 'Normal Spontaneous Vaginal Delivery', 'Birthing Center', 'Nurse', 1, 2500, 0, 1, 1, NULL, 9, '2025-10-12 11:57:14');

-- --------------------------------------------------------

--
-- Table structure for table `maternal_patients`
--

CREATE TABLE `maternal_patients` (
  `mother_id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gravida` tinyint(3) UNSIGNED DEFAULT NULL,
  `para` tinyint(3) UNSIGNED DEFAULT NULL,
  `blood_type` varchar(5) DEFAULT NULL,
  `emergency_contact_name` varchar(120) DEFAULT NULL,
  `emergency_contact_number` varchar(40) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_account_id` int(10) UNSIGNED DEFAULT NULL,
  `house_number` varchar(50) DEFAULT NULL,
  `street_name` varchar(150) DEFAULT NULL,
  `purok_name` varchar(100) DEFAULT NULL,
  `subdivision_name` varchar(150) DEFAULT NULL,
  `purok_id` int(10) UNSIGNED DEFAULT NULL,
  `legacy_full_name_backup` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Maternal patients registry';

--
-- Dumping data for table `maternal_patients`
--

INSERT INTO `maternal_patients` (`mother_id`, `first_name`, `middle_name`, `last_name`, `date_of_birth`, `gravida`, `para`, `blood_type`, `emergency_contact_name`, `emergency_contact_number`, `contact_number`, `created_by`, `created_at`, `updated_at`, `user_account_id`, `house_number`, `street_name`, `purok_name`, `subdivision_name`, `purok_id`, `legacy_full_name_backup`) VALUES
(1, 'Althea', 'G', 'Reyes', '2004-11-11', 3, 4, 'O', 'Sepp Bernard', '09622360874', '09958167775', 9, '2025-10-06 08:44:29', '2025-10-08 15:44:11', NULL, '3333', 'rosas', 'Purok 1', 'lynville', 1, NULL),
(2, 'Brian', 'Marvic', 'Maines', '2000-12-31', NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-06 09:17:14', '2025-10-06 09:17:14', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'weq', 'ewq', 'weq', '2000-11-02', NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-06 09:34:22', '2025-10-06 09:34:22', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'wqe', 'q', 'wqe', '2000-01-02', NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-06 16:09:24', '2025-10-06 16:09:24', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'eqwe', 'wq', 'weq', '2000-02-02', 0, 0, 'A+', NULL, NULL, '34234234234', 9, '2025-10-06 16:38:43', '2025-10-14 15:25:29', NULL, '231', 'eqwe', 'Purok 1', NULL, NULL, NULL),
(29, 'qweq', 'wqe', 'weqe', '2000-02-02', NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-06 17:01:36', '2025-10-06 17:01:36', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(30, 'sdasda', 'weq', 'weq', '2004-12-02', NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-06 17:36:54', '2025-10-06 17:36:54', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(31, 'Gabrielle', 'gab', 'Resuello', '2004-11-11', 4, 5, 'A', 'Althea Gabrielle Reyes', '09958167775', '09992223324', 10, '2025-10-08 08:48:34', '2025-10-08 09:27:11', NULL, '2301', 'Sampaguita', NULL, 'Silverlas', 1, NULL),
(32, 'qwe', 'wqe', 'qwe', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-08 15:44:39', '2025-10-08 15:44:39', NULL, NULL, NULL, 'Purok 2', NULL, NULL, NULL),
(33, 'Maria', 'Santos', 'Dela Cruz', '1999-04-18', 2, 1, 'O+', 'Juan Dela Cruz', '09182345678', '09171234567', 9, '2025-10-12 08:41:30', '2025-10-12 08:41:30', NULL, '45', 'Mabini St.', 'Purok 1', 'San Isidro Village', NULL, NULL),
(34, 'Angela', 'Rivera', 'Bautista', '2000-02-07', 1, 0, 'A+', 'Carlo Bautista', '09175678900', '09283456712', 9, '2025-10-12 09:25:36', '2025-10-12 09:25:36', NULL, '128', 'Rizal Avenue', 'Purok 1', 'Green Meadows', NULL, NULL),
(35, 'wq', 'eq', 'ewqe', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-12 09:27:15', '2025-10-12 09:27:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(36, 'wqeq', 'weq', 'eqw', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-12 09:33:48', '2025-10-12 09:33:48', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(37, 'xczxc', 'd', 'asda', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-12 09:38:41', '2025-10-12 09:38:41', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(38, 'sda', 'dsad', 'sada', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-12 09:41:56', '2025-10-12 09:41:56', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(39, 'we', 'wqeqw', 'ewq', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-12 09:45:24', '2025-10-12 09:45:24', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(40, 'we', 'weqw', 'eqeq', '2000-01-02', NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-12 10:16:36', '2025-10-12 10:16:36', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(41, 'cxxcz', 'sda', 'sada', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-12 10:18:12', '2025-10-12 10:18:12', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(42, 'czxc', 'xczx', 'xczxc', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-13 19:35:07', '2025-10-13 19:35:07', NULL, NULL, NULL, 'Purok 1', NULL, NULL, NULL),
(43, 'althea', NULL, 'sdada', '2000-12-03', NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-13 19:37:00', '2025-10-13 19:37:00', NULL, NULL, NULL, 'Purok 4', NULL, NULL, NULL),
(44, 'Kaye', 'dsasd', 'sdad', '2000-02-03', 0, 0, 'A+', 'kaye fernando', '09952604071', '09952604071', 9, '2025-10-13 19:39:21', '2025-10-13 19:39:21', NULL, '123', 'sdad', 'Purok 5', 'lynville', NULL, NULL),
(45, 'kjkhk', 'ds', 'sda', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-13 19:44:35', '2025-10-13 19:44:35', NULL, NULL, NULL, 'Purok 5', NULL, NULL, NULL),
(46, 'bnv', 'dsd', 'sada', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-13 19:45:40', '2025-10-13 19:45:40', NULL, NULL, NULL, 'Purok 3', NULL, NULL, NULL),
(47, 'ghgh', 'dfs', 'fsdf', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-13 19:46:16', '2025-10-13 19:46:16', NULL, NULL, NULL, 'Purok 1', NULL, NULL, NULL),
(48, 'opop', 'er', 'wrwe', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-13 19:53:55', '2025-10-13 19:53:55', NULL, NULL, NULL, 'Purok 6', NULL, NULL, NULL),
(49, 'sad', 'sd', 'asda', '2000-12-02', 0, 0, 'A+', 'df', '12312312312', '34243243242', 9, '2025-10-14 13:24:04', '2025-10-14 13:24:04', NULL, '23', 'weq', 'Purok 2', NULL, NULL, NULL),
(50, 'sd', 'asdasd', 'asd', '2025-10-14', NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-14 14:05:23', '2025-10-14 14:05:23', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(51, 'bbv', 'eqw', 'eq', '2000-12-31', 0, 0, 'A+', 'dffs', '32432424242', '54535453453', 9, '2025-10-14 14:41:48', '2025-10-14 14:41:48', NULL, '43', 'dsad', 'Purok 8', NULL, NULL, NULL),
(52, 'dsad', 'sad', 'asdasd', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-14 14:58:41', '2025-10-14 14:58:41', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(53, 'wsad', NULL, 'dsa', '2000-12-22', 0, 0, 'A+', NULL, NULL, '32', 9, '2025-10-14 15:12:50', '2025-10-14 15:12:50', NULL, '12', 'qwe', 'Purok 8', NULL, NULL, NULL),
(54, 'trtr', NULL, 'qwe', '2005-02-22', 0, 0, 'A-', NULL, NULL, '45534534543', 9, '2025-10-14 15:26:44', '2025-10-14 15:26:44', NULL, '123', 'qwe', 'Purok 1', NULL, NULL, NULL),
(55, 'vfgdfsdf', 'ds', 'dsdas', '2000-01-02', 0, 0, 'A+', NULL, NULL, '45355435353', 9, '2025-10-14 18:55:30', '2025-10-14 18:55:30', NULL, '432', 'dsa', 'Purok 3', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `mothers_caregivers`
--

CREATE TABLE `mothers_caregivers` (
  `mother_id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `emergency_contact_name` varchar(120) DEFAULT NULL,
  `emergency_contact_number` varchar(40) DEFAULT NULL,
  `purok_id` int(10) UNSIGNED DEFAULT NULL,
  `address_details` text DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_account_id` int(10) UNSIGNED DEFAULT NULL,
  `house_number` varchar(50) DEFAULT NULL,
  `street_name` varchar(150) DEFAULT NULL,
  `subdivision_name` varchar(150) DEFAULT NULL,
  `legacy_full_name_backup` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Mothers & caregivers';

--
-- Dumping data for table `mothers_caregivers`
--

INSERT INTO `mothers_caregivers` (`mother_id`, `first_name`, `middle_name`, `last_name`, `full_name`, `date_of_birth`, `emergency_contact_name`, `emergency_contact_number`, `purok_id`, `address_details`, `contact_number`, `created_by`, `created_at`, `updated_at`, `user_account_id`, `house_number`, `street_name`, `subdivision_name`, `legacy_full_name_backup`) VALUES
(1, NULL, NULL, NULL, 'Placeholder Mother', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-08 05:37:41', '2025-10-08 05:37:41', NULL, NULL, NULL, NULL, NULL),
(2, 'Brian', 'Marvic', 'Maines', 'Brian Marvic Maines', '2000-12-31', NULL, NULL, NULL, NULL, NULL, 9, '2025-10-08 16:34:59', '2025-10-08 16:34:59', NULL, NULL, NULL, NULL, NULL),
(31, 'Gabrielle', 'gab', 'Resuello', 'Gabrielle gab Resuello', '2004-11-11', 'Althea Gabrielle Reyes', '09958167775', NULL, NULL, '09992223324', 9, '2025-10-08 19:06:36', '2025-10-08 19:06:36', NULL, '2301', 'Sampaguita', 'Silverlas', NULL),
(41, 'cxxcz', 'sda', 'sada', 'cxxcz sda sada', NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-13 19:23:07', '2025-10-13 19:23:07', NULL, NULL, NULL, NULL, NULL),
(45, 'kjkhk', 'ds', 'sda', 'kjkhk ds sda', NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-14 16:11:19', '2025-10-14 16:11:19', NULL, NULL, NULL, NULL, NULL),
(47, 'ghgh', 'dfs', 'fsdf', 'ghgh dfs fsdf', NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-13 19:46:44', '2025-10-13 19:46:44', NULL, NULL, NULL, NULL, NULL),
(55, 'vfgdfsdf', 'ds', 'dsdas', 'vfgdfsdf ds dsdas', '2000-01-02', NULL, NULL, NULL, NULL, '45355435353', 9, '2025-10-14 19:13:54', '2025-10-14 19:13:54', NULL, '432', 'dsa', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `nutrition_records`
--

CREATE TABLE `nutrition_records` (
  `record_id` int(10) UNSIGNED NOT NULL,
  `child_id` int(10) UNSIGNED NOT NULL,
  `weighing_date` date NOT NULL,
  `age_in_months` int(10) UNSIGNED NOT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `length_height_cm` decimal(5,2) DEFAULT NULL,
  `wfl_ht_status_id` int(10) UNSIGNED DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Nutrition measurement records';

--
-- Dumping data for table `nutrition_records`
--

INSERT INTO `nutrition_records` (`record_id`, `child_id`, `weighing_date`, `age_in_months`, `weight_kg`, `length_height_cm`, `wfl_ht_status_id`, `remarks`, `recorded_by`, `created_at`, `updated_at`) VALUES
(1, 2, '2025-10-08', 5, 5.00, 45.00, NULL, 'abay mataba', 11, '2025-10-08 11:36:23', '2025-10-08 11:36:23'),
(5, 2, '2025-10-09', 5, 3.50, 45.00, 1, '', 11, '2025-10-08 16:00:39', '2025-10-08 16:00:39');

-- --------------------------------------------------------

--
-- Table structure for table `overdue_notifications`
--

CREATE TABLE `overdue_notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `child_id` int(10) UNSIGNED NOT NULL,
  `vaccine_id` int(10) UNSIGNED NOT NULL,
  `dose_number` int(10) UNSIGNED NOT NULL,
  `status` enum('active','dismissed','expired') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `dismissed_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Overdue / due vaccine notifications';

--
-- Dumping data for table `overdue_notifications`
--

INSERT INTO `overdue_notifications` (`id`, `child_id`, `vaccine_id`, `dose_number`, `status`, `created_at`, `dismissed_at`, `expires_at`) VALUES
(1, 1, 1, 1, 'active', '2025-10-08 16:10:39', NULL, NULL),
(2, 1, 2, 1, 'active', '2025-10-08 16:10:39', NULL, NULL),
(3, 1, 5, 1, 'active', '2025-10-08 16:10:39', NULL, NULL),
(4, 1, 4, 1, 'active', '2025-10-08 16:10:39', NULL, NULL),
(5, 1, 4, 2, 'active', '2025-10-08 16:10:39', NULL, NULL),
(6, 1, 4, 3, 'active', '2025-10-08 16:10:39', NULL, NULL),
(7, 1, 3, 1, 'active', '2025-10-08 16:10:39', NULL, NULL),
(8, 1, 3, 2, 'active', '2025-10-08 16:10:39', NULL, NULL),
(9, 1, 3, 3, 'active', '2025-10-08 16:10:39', NULL, NULL),
(10, 1, 6, 1, 'active', '2025-10-08 16:10:39', NULL, NULL),
(11, 1, 6, 2, 'active', '2025-10-08 16:10:39', NULL, NULL),
(12, 2, 1, 1, 'active', '2025-10-08 16:10:39', NULL, NULL),
(13, 2, 2, 1, 'active', '2025-10-08 16:10:39', NULL, NULL),
(14, 2, 5, 1, 'active', '2025-10-08 16:10:39', NULL, NULL),
(15, 2, 4, 1, 'active', '2025-10-08 16:10:39', NULL, NULL),
(16, 2, 4, 2, 'active', '2025-10-08 16:10:39', NULL, NULL),
(17, 2, 4, 3, 'active', '2025-10-08 16:10:39', NULL, NULL),
(18, 2, 3, 1, 'active', '2025-10-08 16:10:39', NULL, NULL),
(19, 2, 3, 2, 'active', '2025-10-08 16:10:39', NULL, NULL),
(20, 2, 3, 3, 'active', '2025-10-08 16:10:39', NULL, NULL),
(21, 2, 6, 1, 'active', '2025-10-08 16:10:39', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `parent_audit_log`
--

CREATE TABLE `parent_audit_log` (
  `log_id` int(10) UNSIGNED NOT NULL,
  `parent_user_id` int(10) UNSIGNED NOT NULL,
  `action_code` varchar(40) NOT NULL,
  `child_id` int(10) UNSIGNED DEFAULT NULL,
  `meta_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_json`)),
  `ip_address` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Audit log of parent portal activity';

--
-- Dumping data for table `parent_audit_log`
--

INSERT INTO `parent_audit_log` (`log_id`, `parent_user_id`, `action_code`, `child_id`, `meta_json`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 12, 'create_account', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-08 18:58:06'),
(2, 13, 'create_account', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-08 19:04:46'),
(3, 14, 'create_account', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-08 19:06:58'),
(4, 15, 'create_account', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-14 19:14:35'),
(5, 16, 'create_account', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 OPR/122.0.0.0', '2025-10-15 10:20:20');

-- --------------------------------------------------------

--
-- Table structure for table `parent_child_access`
--

CREATE TABLE `parent_child_access` (
  `access_id` int(10) UNSIGNED NOT NULL,
  `parent_user_id` int(10) UNSIGNED NOT NULL,
  `child_id` int(10) UNSIGNED NOT NULL,
  `relationship_type` enum('mother','father','guardian','caregiver') NOT NULL,
  `access_granted_by` int(10) UNSIGNED NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `granted_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Link parents to children';

--
-- Dumping data for table `parent_child_access`
--

INSERT INTO `parent_child_access` (`access_id`, `parent_user_id`, `child_id`, `relationship_type`, `access_granted_by`, `is_active`, `granted_date`, `created_at`, `updated_at`) VALUES
(1, 12, 1, 'mother', 9, 1, '2025-10-08 18:58:06', '2025-10-08 18:58:06', '2025-10-08 18:58:06'),
(2, 12, 2, 'mother', 9, 1, '2025-10-08 18:58:06', '2025-10-08 18:58:06', '2025-10-08 18:58:06'),
(3, 13, 3, 'mother', 9, 1, '2025-10-08 19:04:46', '2025-10-08 19:04:46', '2025-10-08 19:04:46'),
(4, 14, 4, 'mother', 9, 1, '2025-10-08 19:06:58', '2025-10-08 19:06:58', '2025-10-08 19:06:58'),
(5, 15, 10, 'mother', 9, 1, '2025-10-14 19:14:35', '2025-10-14 19:14:35', '2025-10-14 19:14:35'),
(6, 16, 5, 'mother', 9, 1, '2025-10-15 10:20:20', '2025-10-15 10:20:20', '2025-10-15 10:20:20');

-- --------------------------------------------------------

--
-- Stand-in structure for view `parent_child_immunization_view`
-- (See below for the actual view)
--
CREATE TABLE `parent_child_immunization_view` (
`parent_user_id` int(10) unsigned
,`relationship_type` enum('mother','father','guardian','caregiver')
,`child_id` int(10) unsigned
,`child_name` varchar(255)
,`child_sex` enum('male','female')
,`child_birth_date` date
,`current_age_months` bigint(21)
,`current_age_years` bigint(21)
,`vaccine_name` varchar(255)
,`vaccine_code` varchar(20)
,`target_age_group` varchar(100)
,`vaccine_category` enum('birth','infant','child','booster','adult')
,`dose_number` int(10) unsigned
,`vaccination_date` date
,`vaccination_site` varchar(100)
,`next_dose_due_date` date
,`adverse_reactions` text
,`notes` text
,`administered_by_name` varchar(201)
,`vaccination_status` varchar(18)
,`days_to_next_dose` int(7)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `parent_child_vaccination_summary`
-- (See below for the actual view)
--
CREATE TABLE `parent_child_vaccination_summary` (
`parent_user_id` int(10) unsigned
,`relationship_type` enum('mother','father','guardian','caregiver')
,`child_id` int(10) unsigned
,`child_name` varchar(255)
,`child_sex` enum('male','female')
,`child_birth_date` date
,`current_age_months` bigint(21)
,`total_vaccines_available` bigint(21)
,`vaccines_received` bigint(21)
,`completion_percentage` decimal(25,1)
,`overdue_doses` decimal(22,0)
,`upcoming_doses` decimal(22,0)
,`last_vaccination_date` date
,`next_due_date` date
);

-- --------------------------------------------------------

--
-- Table structure for table `parent_notifications`
--

CREATE TABLE `parent_notifications` (
  `notification_id` int(10) UNSIGNED NOT NULL,
  `parent_user_id` int(10) UNSIGNED NOT NULL,
  `child_id` int(10) UNSIGNED NOT NULL,
  `notification_type` enum('vaccine_due','vaccine_overdue','appointment_reminder','general') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `original_template` text DEFAULT NULL,
  `related_vaccine_id` int(10) UNSIGNED DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `is_sent` tinyint(1) DEFAULT 0,
  `method_sms` tinyint(1) DEFAULT 0,
  `method_email` tinyint(1) DEFAULT 0,
  `batch_key` varchar(64) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Notifications sent to parents';

--
-- Dumping data for table `parent_notifications`
--

INSERT INTO `parent_notifications` (`notification_id`, `parent_user_id`, `child_id`, `notification_type`, `title`, `message`, `original_template`, `related_vaccine_id`, `due_date`, `is_read`, `is_sent`, `method_sms`, `method_email`, `batch_key`, `created_by`, `created_at`, `read_at`) VALUES
(1, 12, 1, 'vaccine_overdue', 'Overdue Vaccination Alert', 'Reminder: Baby Test Example has overdue vaccination(s).\r\n\r\n- Inactivated Polio Vaccine (IPV) (IPV) Dose 1 (due 2025-04-01)\n- Oral Polio Vaccine (OPV) (OPV) Dose 3 (due 2025-04-01)\n- Pentavalent Vaccine (DPT-Hep B-HIB) (PENTA) Dose 3 (due 2025-04-01)\n- Pneumococcal Conjugate Vaccine (PCV) (PCV) Dose 1 (due 2025-02-01)\n- Pneumococcal Conjugate Vaccine (PCV) (PCV) Dose 2 (due 2025-07-01)\r\n\r\nPlease visit the barangay health center. - BHW', 'Reminder: [[CHILD]] has overdue vaccination(s).\r\n\r\n[[ITEMS]]\r\n\r\nPlease visit the barangay health center. - BHW', NULL, NULL, 0, 0, 0, 1, 'B20251012192426a7fe39', 9, '2025-10-12 17:24:26', NULL),
(2, 12, 2, 'vaccine_overdue', 'Overdue Vaccination Alert', 'Reminder: Sepp Bernard Consulta has overdue vaccination(s).\r\n\r\n- Inactivated Polio Vaccine (IPV) (IPV) Dose 1 (due 2025-07-16)\n- Oral Polio Vaccine (OPV) (OPV) Dose 2 (due 2025-06-16)\n- Oral Polio Vaccine (OPV) (OPV) Dose 3 (due 2025-07-16)\n- Pentavalent Vaccine (DPT-Hep B-HIB) (PENTA) Dose 2 (due 2025-06-16)\n- Pentavalent Vaccine (DPT-Hep B-HIB) (PENTA) Dose 3 (due 2025-07-16)\r\n\r\nPlease visit the barangay health center. - BHW', 'Reminder: [[CHILD]] has overdue vaccination(s).\r\n\r\n[[ITEMS]]\r\n\r\nPlease visit the barangay health center. - BHW', NULL, NULL, 0, 0, 0, 1, 'B20251012192426a7fe39', 9, '2025-10-12 17:24:32', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `postnatal_visits`
--

CREATE TABLE `postnatal_visits` (
  `postnatal_visit_id` int(10) UNSIGNED NOT NULL,
  `mother_id` int(10) UNSIGNED NOT NULL,
  `child_id` int(10) UNSIGNED DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `visit_date` date NOT NULL,
  `postpartum_day` int(10) UNSIGNED DEFAULT NULL,
  `bp_systolic` int(10) UNSIGNED DEFAULT NULL,
  `bp_diastolic` int(10) UNSIGNED DEFAULT NULL,
  `temperature_c` decimal(4,1) DEFAULT NULL,
  `lochia_status` varchar(30) DEFAULT NULL,
  `breastfeeding_status` varchar(30) DEFAULT NULL,
  `danger_signs` text DEFAULT NULL,
  `swelling` tinyint(1) DEFAULT 0,
  `fever` tinyint(1) DEFAULT 0,
  `foul_lochia` tinyint(1) DEFAULT 0,
  `mastitis` tinyint(1) DEFAULT 0,
  `postpartum_depression` tinyint(1) DEFAULT 0,
  `other_findings` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Postnatal visits tracking';

-- --------------------------------------------------------

--
-- Table structure for table `puroks`
--

CREATE TABLE `puroks` (
  `purok_id` int(10) UNSIGNED NOT NULL,
  `purok_name` varchar(100) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Purok reference table';

--
-- Dumping data for table `puroks`
--

INSERT INTO `puroks` (`purok_id`, `purok_name`, `barangay`, `created_at`) VALUES
(1, 'Purok 1', 'Sabang', '2025-10-08 05:37:41'),
(2, 'Purok 2', 'Sabang', '2025-10-08 05:37:41'),
(3, 'Purok 3', 'Sabang', '2025-10-13 13:11:21'),
(4, 'Purok 4', 'Sabang', '2025-10-13 13:11:21'),
(5, 'Purok 5', 'Sabang', '2025-10-13 13:11:21'),
(6, 'Purok 6', 'Sabang', '2025-10-13 13:11:21'),
(7, 'Purok 7', 'Sabang', '2025-10-13 13:11:21'),
(8, 'Purok 8', 'Sabang', '2025-10-13 13:11:21'),
(9, 'Purok 9', 'Sabang', '2025-10-13 13:11:21'),
(10, 'Purok 10', 'Sabang', '2025-10-13 13:11:21'),
(11, 'Purok 11', 'Sabang', '2025-10-13 13:11:21'),
(12, 'Purok 12', 'Sabang', '2025-10-13 13:11:21'),
(13, 'Purok 13', 'Sabang', '2025-10-13 13:11:21'),
(14, 'Purok 14', 'Sabang', '2025-10-13 13:11:21'),
(15, 'Purok 15', 'Sabang', '2025-10-13 13:11:21'),
(16, 'Purok 16', 'Sabang', '2025-10-13 13:11:21'),
(17, 'Purok 17', 'Sabang', '2025-10-13 13:11:21'),
(18, 'Purok 18', 'Sabang', '2025-10-13 13:11:21'),
(19, 'Purok 19', 'Sabang', '2025-10-13 13:11:21'),
(20, 'Purok 20', 'Sabang', '2025-10-13 13:11:21'),
(21, 'Purok 21', 'Sabang', '2025-10-13 13:11:21'),
(22, 'Purok 22', 'Sabang', '2025-10-13 13:11:21'),
(23, 'Purok 23', 'Sabang', '2025-10-13 13:11:21'),
(24, 'Purok 24', 'Sabang', '2025-10-13 13:11:21'),
(25, 'Purok 25', 'Sabang', '2025-10-13 13:11:21'),
(26, 'Purok 26', 'Sabang', '2025-10-13 13:11:21');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(10) UNSIGNED NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `role_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='System roles';

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `role_description`, `created_at`) VALUES
(1, 'Admin', 'Barangay-level administrator with full system access', '2025-10-08 05:37:40'),
(2, 'BHW', 'Barangay Health Worker - focused on health data and services', '2025-10-08 05:37:40'),
(3, 'BNS', 'Barangay Nutrition Scholar - focused on nutrition programs and records', '2025-10-08 05:37:40'),
(4, 'Parent', 'Parent/Guardian with access to view their child information', '2025-10-08 05:37:40');

-- --------------------------------------------------------

--
-- Table structure for table `supplementation_records`
--

CREATE TABLE `supplementation_records` (
  `supplement_id` int(10) UNSIGNED NOT NULL,
  `child_id` int(10) UNSIGNED NOT NULL,
  `supplement_type` varchar(100) NOT NULL,
  `supplement_date` date NOT NULL,
  `dosage` varchar(50) DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `administered_by` int(10) UNSIGNED NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Vitamin/supplementation tracking';

--
-- Dumping data for table `supplementation_records`
--

INSERT INTO `supplementation_records` (`supplement_id`, `child_id`, `supplement_type`, `supplement_date`, `dosage`, `next_due_date`, `administered_by`, `notes`, `created_at`) VALUES
(1, 5, 'Vitamin A', '2025-10-14', '400', '2026-04-14', 11, NULL, '2025-10-13 19:25:25'),
(2, 4, 'Vitamin A', '2025-10-14', '500', '2026-04-14', 11, NULL, '2025-10-13 20:08:56');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL DEFAULT '',
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL DEFAULT '',
  `role_id` int(10) UNSIGNED NOT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_by_user_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Application users';

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `password_hash`, `first_name`, `middle_name`, `last_name`, `role_id`, `barangay`, `birthday`, `is_active`, `created_at`, `updated_at`, `last_login_at`, `created_by_user_id`) VALUES
(2, 'admin', NULL, '', '$2y$10$sHL0FeWDe2/kJaxlFtcvkOXHgPFF7yfadgiO56cfckVIYP/ewdwyG', '', NULL, '', 1, NULL, NULL, 1, '2025-10-08 05:37:41', '2025-10-08 05:37:41', NULL, NULL),
(9, 'cris', 'sdasd@gmail.com', '', '$2y$10$sHL0FeWDe2/kJaxlFtcvkOXHgPFF7yfadgiO56cfckVIYP/ewdwyG', 'Cris', NULL, 'Hernandez', 2, 'Sabang', NULL, 1, '2025-10-08 05:37:41', '2025-10-08 05:37:41', NULL, 2),
(10, 'bnsses', 'bns@gmail.com', 'reyes77488', '$2y$10$BziwBgsgBhk3ZUIm3qS3dutrp0FJn/KuRxOK2Rmp6zgFlhHTVTWjy', 'bnss', NULL, 'reyes', 2, 'Sabang', NULL, 1, '2025-10-08 07:36:03', '2025-10-08 07:36:03', NULL, 2),
(11, 'althea gabriellees', 'raltheagabrielle@gmail.com', 'reyes49341', '$2y$10$p.qXQF4FkaE/4RJstceFE.cjk5GVpHdkRkTuCGCnvRwA6gFwUucH2', 'Althea Gabrielle', NULL, 'Reyes', 3, 'Sabang', NULL, 1, '2025-10-08 07:38:06', '2025-10-14 20:09:05', NULL, 2),
(12, 'althears', 'criscarloh@gmail.com', '', '$2y$10$MpaexbnG1orDEQAR9T2QjuKbdUnzsovwqi5gCG6ISa0nReDsPoqwu', 'Althea', NULL, 'Reyes', 4, 'Sabang', NULL, 1, '2025-10-08 18:58:06', '2025-10-08 18:58:06', NULL, 9),
(13, 'brianms', 'jmbmaines17@gmail.com', '', '$2y$10$9araDtt1HhwtBiIyRTHfhekFzzswSzlAUsVxboL3BjBa6FERQRFNS', 'Brian', NULL, 'Maines', 4, 'Sabang', NULL, 1, '2025-10-08 19:04:46', '2025-10-08 19:04:46', NULL, 9),
(14, 'gabriellero', 'ch512291@gmail.com', '', '$2y$10$u7KVj7ImMfzbFUX3J0S0W.BYJOl4kIy1f1QhBA46M6VfMEu4vScom', 'Gabrielle', NULL, 'Resuello', 4, 'Sabang', NULL, 1, '2025-10-08 19:06:58', '2025-10-08 19:06:58', NULL, 9),
(15, 'vfgdfsdfds', 'criscarloh1@gmail.com', '', '$2y$10$czjtVw/a5bbRBkitnffnUOUDFSVEG90V7fml0KDc1ycBtxDyGSS66', 'vfgdfsdf', 'ds', 'dsdas', 4, 'Sabang', NULL, 1, '2025-10-14 19:14:35', '2025-10-14 19:14:35', NULL, 9),
(16, 'cxxczsa', 'dsd@gmail.com', '', '$2y$10$WdW01BsH3RbO32rTUHaDYuB.uREMD8ngsp/xvkfU7aEUgnsDNTXRO', 'cxxcz', 'sda', 'sada', 4, 'Sabang', '2000-02-15', 1, '2025-10-15 10:20:20', '2025-10-15 10:20:20', NULL, 9);

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `log_account_creation` AFTER INSERT ON `users` FOR EACH ROW BEGIN
    DECLARE v_role_name VARCHAR(50);
    SELECT role_name INTO v_role_name FROM roles WHERE role_id = NEW.role_id;
    IF v_role_name IN ('BHW','BNS','Parent') THEN
        INSERT INTO account_creation_log
          (created_user_id, created_by_user_id, account_type, creation_reason)
        VALUES
          (NEW.user_id, IFNULL(NEW.created_by_user_id, 2), v_role_name, 'Account created via admin/BNS interface');
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `user_login_log`
--

CREATE TABLE `user_login_log` (
  `log_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `login_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_agent` varchar(255) DEFAULT NULL,
  `ip_address` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='User login events';

-- --------------------------------------------------------

--
-- Table structure for table `vaccine_types`
--

CREATE TABLE `vaccine_types` (
  `vaccine_id` int(10) UNSIGNED NOT NULL,
  `vaccine_code` varchar(20) NOT NULL,
  `vaccine_name` varchar(255) NOT NULL,
  `vaccine_description` text DEFAULT NULL,
  `target_age_group` varchar(100) DEFAULT NULL,
  `vaccine_category` enum('birth','infant','child','booster','adult') NOT NULL,
  `doses_required` int(10) UNSIGNED DEFAULT 1,
  `interval_between_doses_days` int(10) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Vaccine type catalog';

--
-- Dumping data for table `vaccine_types`
--

INSERT INTO `vaccine_types` (`vaccine_id`, `vaccine_code`, `vaccine_name`, `vaccine_description`, `target_age_group`, `vaccine_category`, `doses_required`, `interval_between_doses_days`, `is_active`, `created_at`) VALUES
(1, 'BCG', 'BCG Vaccine', NULL, '0', 'birth', 1, NULL, 1, '2025-10-01 11:02:34'),
(2, 'HEPB', 'Hepatitis B Vaccine', NULL, '0', 'birth', 1, NULL, 1, '2025-10-01 11:02:34'),
(3, 'PENTA', 'Pentavalent Vaccine (DPT-Hep B-HIB)', NULL, NULL, 'infant', 3, NULL, 1, '2025-10-01 11:02:34'),
(4, 'OPV', 'Oral Polio Vaccine (OPV)', NULL, '1 1/2, 2 1/2, 3 1/2', 'infant', 3, NULL, 1, '2025-10-01 11:02:34'),
(5, 'IPV', 'Inactivated Polio Vaccine (IPV)', NULL, '3 1/2 & 9', 'infant', 2, NULL, 1, '2025-10-01 11:02:34'),
(6, 'PCV', 'Pneumococcal Conjugate Vaccine (PCV)', NULL, NULL, 'infant', 3, NULL, 1, '2025-10-01 11:02:34'),
(7, 'MMR', 'Measles, Mumps, Rubella Vaccine (MMR)', NULL, NULL, 'child', 2, NULL, 1, '2025-10-01 11:02:34'),
(8, 'MCV', 'Measles Containing Vaccine (MCV) MR/MMR Booster', NULL, NULL, 'child', 1, NULL, 1, '2025-10-01 11:02:34'),
(9, 'TD', 'Tetanus Diphtheria (TD)', NULL, NULL, 'booster', 2, NULL, 1, '2025-10-01 11:02:34'),
(10, 'HPV', 'Human Papillomavirus Vaccine (HPV)', NULL, NULL, 'booster', 2, NULL, 1, '2025-10-01 11:02:34');

-- --------------------------------------------------------

--
-- Table structure for table `wfl_ht_status_types`
--

CREATE TABLE `wfl_ht_status_types` (
  `status_id` int(10) UNSIGNED NOT NULL,
  `status_code` varchar(20) NOT NULL,
  `status_description` varchar(100) NOT NULL,
  `status_category` enum('underweight','normal','overweight','obese','stunted','wasted') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Weight-for-length/height status taxonomy';

--
-- Dumping data for table `wfl_ht_status_types`
--

INSERT INTO `wfl_ht_status_types` (`status_id`, `status_code`, `status_description`, `status_category`, `created_at`) VALUES
(1, 'NOR', 'Normal', 'normal', '2025-10-08 11:40:48'),
(2, 'MAM', 'Moderate Acute Malnutrition', 'wasted', '2025-10-08 11:40:48'),
(3, 'SAM', 'Severe Acute Malnutrition', 'wasted', '2025-10-08 11:40:48'),
(4, 'OW', 'Overweight', 'overweight', '2025-10-08 11:40:48'),
(5, 'OB', 'Obese', 'obese', '2025-10-08 11:40:48'),
(6, 'ST', 'Stunted', 'stunted', '2025-10-08 11:40:48'),
(7, 'UW', 'Underweight', 'underweight', '2025-10-08 11:40:48');

-- --------------------------------------------------------

--
-- Structure for view `account_management_overview`
--
DROP TABLE IF EXISTS `account_management_overview`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `account_management_overview`  AS SELECT `u`.`user_id` AS `user_id`, `u`.`username` AS `username`, `u`.`first_name` AS `first_name`, `u`.`last_name` AS `last_name`, `u`.`email` AS `email`, `r`.`role_name` AS `role_name`, `u`.`barangay` AS `barangay`, `u`.`is_active` AS `is_active`, `u`.`created_at` AS `created_at`, concat(ifnull(`creator`.`first_name`,'System'),' ',ifnull(`creator`.`last_name`,'Admin')) AS `created_by_name`, ifnull(`creator`.`username`,'system') AS `created_by_username`, CASE WHEN `r`.`role_name` = 'Parent' THEN (select group_concat(`c`.`full_name` separator ', ') from (`parent_child_access` `pca2` join `children` `c` on(`pca2`.`child_id` = `c`.`child_id`)) where `pca2`.`parent_user_id` = `u`.`user_id` and `pca2`.`is_active` = 1) ELSE NULL END AS `children_names`, CASE WHEN `r`.`role_name` = 'BHW' THEN (select count(0) from `health_records` `hr` where `hr`.`recorded_by` = `u`.`user_id`) WHEN `r`.`role_name` = 'BNS' THEN (select count(0) from `nutrition_records` `nr` where `nr`.`recorded_by` = `u`.`user_id`) ELSE NULL END AS `records_created` FROM ((`users` `u` join `roles` `r` on(`u`.`role_id` = `r`.`role_id`)) left join `users` `creator` on(`u`.`created_by_user_id` = `creator`.`user_id`)) WHERE `r`.`role_name` in ('BHW','BNS','Parent') ORDER BY `r`.`role_name` ASC, `u`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `bns_created_parents`
--
DROP TABLE IF EXISTS `bns_created_parents`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `bns_created_parents`  AS SELECT `u`.`user_id` AS `parent_user_id`, `u`.`username` AS `parent_username`, `u`.`first_name` AS `parent_first_name`, `u`.`last_name` AS `parent_last_name`, `u`.`email` AS `parent_email`, `u`.`created_at` AS `account_created_date`, `c`.`child_id` AS `child_id`, `c`.`full_name` AS `child_name`, `c`.`sex` AS `child_sex`, `c`.`birth_date` AS `child_birth_date`, `pca`.`relationship_type` AS `relationship_type`, `bns`.`user_id` AS `bns_user_id`, concat(`bns`.`first_name`,' ',`bns`.`last_name`) AS `created_by_bns` FROM (((((`users` `u` join `roles` `r` on(`u`.`role_id` = `r`.`role_id`)) join `parent_child_access` `pca` on(`u`.`user_id` = `pca`.`parent_user_id`)) join `children` `c` on(`pca`.`child_id` = `c`.`child_id`)) join `users` `bns` on(`pca`.`access_granted_by` = `bns`.`user_id`)) join `roles` `bns_role` on(`bns`.`role_id` = `bns_role`.`role_id`)) WHERE `r`.`role_name` = 'Parent' AND `bns_role`.`role_name` = 'BNS' AND `pca`.`is_active` = 1 ORDER BY `u`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `parent_child_immunization_view`
--
DROP TABLE IF EXISTS `parent_child_immunization_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `parent_child_immunization_view`  AS SELECT `pca`.`parent_user_id` AS `parent_user_id`, `pca`.`relationship_type` AS `relationship_type`, `c`.`child_id` AS `child_id`, `c`.`full_name` AS `child_name`, `c`.`sex` AS `child_sex`, `c`.`birth_date` AS `child_birth_date`, timestampdiff(MONTH,`c`.`birth_date`,curdate()) AS `current_age_months`, timestampdiff(YEAR,`c`.`birth_date`,curdate()) AS `current_age_years`, `vt`.`vaccine_name` AS `vaccine_name`, `vt`.`vaccine_code` AS `vaccine_code`, `vt`.`target_age_group` AS `target_age_group`, `vt`.`vaccine_category` AS `vaccine_category`, `ci`.`dose_number` AS `dose_number`, `ci`.`vaccination_date` AS `vaccination_date`, `ci`.`vaccination_site` AS `vaccination_site`, `ci`.`next_dose_due_date` AS `next_dose_due_date`, `ci`.`adverse_reactions` AS `adverse_reactions`, `ci`.`notes` AS `notes`, concat(`bhw`.`first_name`,' ',`bhw`.`last_name`) AS `administered_by_name`, CASE WHEN `ci`.`vaccination_date` is null THEN 'Not Given' WHEN `ci`.`next_dose_due_date` is not null AND `ci`.`next_dose_due_date` < curdate() THEN 'Next Dose Overdue' WHEN `ci`.`next_dose_due_date` is not null AND `ci`.`next_dose_due_date` <= curdate() + interval 30 day THEN 'Next Dose Due Soon' WHEN `ci`.`next_dose_due_date` is null THEN 'Complete' ELSE 'Up to Date' END AS `vaccination_status`, CASE WHEN `ci`.`next_dose_due_date` is not null THEN to_days(`ci`.`next_dose_due_date`) - to_days(curdate()) ELSE NULL END AS `days_to_next_dose` FROM ((((`parent_child_access` `pca` join `children` `c` on(`pca`.`child_id` = `c`.`child_id`)) join `vaccine_types` `vt`) left join `child_immunizations` `ci` on(`c`.`child_id` = `ci`.`child_id` and `vt`.`vaccine_id` = `ci`.`vaccine_id`)) left join `users` `bhw` on(`ci`.`administered_by` = `bhw`.`user_id`)) WHERE `pca`.`is_active` = 1 AND `vt`.`is_active` = 1 ORDER BY `pca`.`parent_user_id` ASC, `c`.`child_id` ASC, `vt`.`vaccine_id` ASC, `ci`.`dose_number` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `parent_child_vaccination_summary`
--
DROP TABLE IF EXISTS `parent_child_vaccination_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `parent_child_vaccination_summary`  AS SELECT `pca`.`parent_user_id` AS `parent_user_id`, `pca`.`relationship_type` AS `relationship_type`, `c`.`child_id` AS `child_id`, `c`.`full_name` AS `child_name`, `c`.`sex` AS `child_sex`, `c`.`birth_date` AS `child_birth_date`, timestampdiff(MONTH,`c`.`birth_date`,curdate()) AS `current_age_months`, count(distinct `vt`.`vaccine_id`) AS `total_vaccines_available`, count(distinct `ci`.`vaccine_id`) AS `vaccines_received`, round(count(distinct `ci`.`vaccine_id`) / nullif(count(distinct `vt`.`vaccine_id`),0) * 100,1) AS `completion_percentage`, sum(case when `ci`.`next_dose_due_date` is not null and `ci`.`next_dose_due_date` < curdate() then 1 else 0 end) AS `overdue_doses`, sum(case when `ci`.`next_dose_due_date` is not null and `ci`.`next_dose_due_date` between curdate() and curdate() + interval 30 day then 1 else 0 end) AS `upcoming_doses`, max(`ci`.`vaccination_date`) AS `last_vaccination_date`, min(case when `ci`.`next_dose_due_date` is not null and `ci`.`next_dose_due_date` >= curdate() then `ci`.`next_dose_due_date` else NULL end) AS `next_due_date` FROM (((`parent_child_access` `pca` join `children` `c` on(`pca`.`child_id` = `c`.`child_id`)) join `vaccine_types` `vt`) left join `child_immunizations` `ci` on(`c`.`child_id` = `ci`.`child_id` and `vt`.`vaccine_id` = `ci`.`vaccine_id`)) WHERE `pca`.`is_active` = 1 AND `vt`.`is_active` = 1 GROUP BY `pca`.`parent_user_id`, `c`.`child_id`, `c`.`full_name`, `c`.`sex`, `c`.`birth_date` ORDER BY `pca`.`parent_user_id` ASC, `c`.`child_id` ASC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_creation_log`
--
ALTER TABLE `account_creation_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_acl_created_by` (`created_by_user_id`),
  ADD KEY `idx_acl_created_user` (`created_user_id`);

--
-- Indexes for table `children`
--
ALTER TABLE `children`
  ADD PRIMARY KEY (`child_id`),
  ADD KEY `idx_children_mother` (`mother_id`),
  ADD KEY `idx_children_created_by` (`created_by`),
  ADD KEY `idx_children_birth_date` (`birth_date`),
  ADD KEY `idx_children_last_first` (`last_name`,`first_name`);

--
-- Indexes for table `child_immunizations`
--
ALTER TABLE `child_immunizations`
  ADD PRIMARY KEY (`immunization_id`),
  ADD UNIQUE KEY `uq_child_vaccine_dose` (`child_id`,`vaccine_id`,`dose_number`),
  ADD KEY `idx_ci_child` (`child_id`),
  ADD KEY `idx_ci_vaccine` (`vaccine_id`),
  ADD KEY `idx_ci_admin` (`administered_by`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `idx_events_creator` (`created_by`),
  ADD KEY `idx_events_date_type` (`event_date`,`event_type`);

--
-- Indexes for table `health_records`
--
ALTER TABLE `health_records`
  ADD PRIMARY KEY (`health_record_id`),
  ADD KEY `idx_hr_mother` (`mother_id`),
  ADD KEY `idx_hr_recorded_by` (`recorded_by`),
  ADD KEY `idx_hr_consultation_date` (`consultation_date`);

--
-- Indexes for table `immunization_schedule`
--
ALTER TABLE `immunization_schedule`
  ADD PRIMARY KEY (`schedule_id`),
  ADD UNIQUE KEY `uq_vaccine_dose_age` (`vaccine_id`,`dose_number`);

--
-- Indexes for table `labor_delivery_records`
--
ALTER TABLE `labor_delivery_records`
  ADD PRIMARY KEY (`labor_id`),
  ADD KEY `idx_mother_date` (`mother_id`,`delivery_date`),
  ADD KEY `idx_child` (`child_id`),
  ADD KEY `idx_recorded_by` (`recorded_by`);

--
-- Indexes for table `maternal_patients`
--
ALTER TABLE `maternal_patients`
  ADD PRIMARY KEY (`mother_id`),
  ADD KEY `idx_mp_created_by` (`created_by`),
  ADD KEY `idx_mp_user_account` (`user_account_id`),
  ADD KEY `idx_mp_purok` (`purok_id`);

--
-- Indexes for table `mothers_caregivers`
--
ALTER TABLE `mothers_caregivers`
  ADD PRIMARY KEY (`mother_id`),
  ADD KEY `idx_mc_purok` (`purok_id`),
  ADD KEY `idx_mc_created_by` (`created_by`),
  ADD KEY `idx_mc_user_account` (`user_account_id`);

--
-- Indexes for table `nutrition_records`
--
ALTER TABLE `nutrition_records`
  ADD PRIMARY KEY (`record_id`),
  ADD UNIQUE KEY `uq_child_weighing_date` (`child_id`,`weighing_date`),
  ADD KEY `idx_nr_status` (`wfl_ht_status_id`),
  ADD KEY `idx_nr_recorded_by` (`recorded_by`),
  ADD KEY `idx_nr_weighing` (`weighing_date`),
  ADD KEY `idx_nr_child_date` (`child_id`,`weighing_date`);

--
-- Indexes for table `overdue_notifications`
--
ALTER TABLE `overdue_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_on_child_vaccine` (`child_id`,`vaccine_id`,`dose_number`),
  ADD KEY `idx_on_status` (`status`),
  ADD KEY `idx_on_expires` (`expires_at`),
  ADD KEY `fk_on_vaccine` (`vaccine_id`);

--
-- Indexes for table `parent_audit_log`
--
ALTER TABLE `parent_audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_pal_parent_created` (`parent_user_id`,`created_at`),
  ADD KEY `idx_pal_action_created` (`action_code`,`created_at`),
  ADD KEY `fk_pal_child` (`child_id`);

--
-- Indexes for table `parent_child_access`
--
ALTER TABLE `parent_child_access`
  ADD PRIMARY KEY (`access_id`),
  ADD UNIQUE KEY `uq_parent_child` (`parent_user_id`,`child_id`),
  ADD KEY `idx_pca_parent` (`parent_user_id`),
  ADD KEY `idx_pca_child` (`child_id`),
  ADD KEY `idx_pca_granted_by` (`access_granted_by`);

--
-- Indexes for table `parent_notifications`
--
ALTER TABLE `parent_notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_pn_child` (`child_id`),
  ADD KEY `idx_pn_related_vaccine` (`related_vaccine_id`),
  ADD KEY `idx_pn_created_by` (`created_by`),
  ADD KEY `idx_pn_parent` (`parent_user_id`),
  ADD KEY `idx_pn_parent_unread` (`parent_user_id`,`is_read`),
  ADD KEY `idx_pn_batch` (`batch_key`);

--
-- Indexes for table `postnatal_visits`
--
ALTER TABLE `postnatal_visits`
  ADD PRIMARY KEY (`postnatal_visit_id`),
  ADD KEY `idx_pnv_mother` (`mother_id`),
  ADD KEY `idx_pnv_child` (`child_id`),
  ADD KEY `idx_pnv_visit_date` (`visit_date`);

--
-- Indexes for table `puroks`
--
ALTER TABLE `puroks`
  ADD PRIMARY KEY (`purok_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `uq_role_name` (`role_name`);

--
-- Indexes for table `supplementation_records`
--
ALTER TABLE `supplementation_records`
  ADD PRIMARY KEY (`supplement_id`),
  ADD KEY `idx_supp_child` (`child_id`),
  ADD KEY `idx_supp_admin` (`administered_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_username` (`username`),
  ADD UNIQUE KEY `uq_email` (`email`),
  ADD KEY `idx_role` (`role_id`),
  ADD KEY `idx_users_created_by` (`created_by_user_id`);

--
-- Indexes for table `user_login_log`
--
ALTER TABLE `user_login_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_ull_user` (`user_id`);

--
-- Indexes for table `vaccine_types`
--
ALTER TABLE `vaccine_types`
  ADD PRIMARY KEY (`vaccine_id`),
  ADD UNIQUE KEY `uq_vaccine_code` (`vaccine_code`);

--
-- Indexes for table `wfl_ht_status_types`
--
ALTER TABLE `wfl_ht_status_types`
  ADD PRIMARY KEY (`status_id`),
  ADD UNIQUE KEY `uq_status_code` (`status_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_creation_log`
--
ALTER TABLE `account_creation_log`
  MODIFY `log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `children`
--
ALTER TABLE `children`
  MODIFY `child_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `child_immunizations`
--
ALTER TABLE `child_immunizations`
  MODIFY `immunization_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `health_records`
--
ALTER TABLE `health_records`
  MODIFY `health_record_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `immunization_schedule`
--
ALTER TABLE `immunization_schedule`
  MODIFY `schedule_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `labor_delivery_records`
--
ALTER TABLE `labor_delivery_records`
  MODIFY `labor_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `maternal_patients`
--
ALTER TABLE `maternal_patients`
  MODIFY `mother_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `mothers_caregivers`
--
ALTER TABLE `mothers_caregivers`
  MODIFY `mother_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `nutrition_records`
--
ALTER TABLE `nutrition_records`
  MODIFY `record_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `overdue_notifications`
--
ALTER TABLE `overdue_notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `parent_audit_log`
--
ALTER TABLE `parent_audit_log`
  MODIFY `log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `parent_child_access`
--
ALTER TABLE `parent_child_access`
  MODIFY `access_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `parent_notifications`
--
ALTER TABLE `parent_notifications`
  MODIFY `notification_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `postnatal_visits`
--
ALTER TABLE `postnatal_visits`
  MODIFY `postnatal_visit_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `puroks`
--
ALTER TABLE `puroks`
  MODIFY `purok_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `supplementation_records`
--
ALTER TABLE `supplementation_records`
  MODIFY `supplement_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `user_login_log`
--
ALTER TABLE `user_login_log`
  MODIFY `log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vaccine_types`
--
ALTER TABLE `vaccine_types`
  MODIFY `vaccine_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `wfl_ht_status_types`
--
ALTER TABLE `wfl_ht_status_types`
  MODIFY `status_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `account_creation_log`
--
ALTER TABLE `account_creation_log`
  ADD CONSTRAINT `fk_acl_created_user` FOREIGN KEY (`created_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_acl_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `children`
--
ALTER TABLE `children`
  ADD CONSTRAINT `fk_children_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_children_mother` FOREIGN KEY (`mother_id`) REFERENCES `mothers_caregivers` (`mother_id`);

--
-- Constraints for table `child_immunizations`
--
ALTER TABLE `child_immunizations`
  ADD CONSTRAINT `fk_ci_admin` FOREIGN KEY (`administered_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_ci_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ci_vaccine` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccine_types` (`vaccine_id`);

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `fk_events_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `health_records`
--
ALTER TABLE `health_records`
  ADD CONSTRAINT `fk_hr_mother` FOREIGN KEY (`mother_id`) REFERENCES `maternal_patients` (`mother_id`),
  ADD CONSTRAINT `fk_hr_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `immunization_schedule`
--
ALTER TABLE `immunization_schedule`
  ADD CONSTRAINT `fk_schedule_vaccine` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccine_types` (`vaccine_id`);

--
-- Constraints for table `labor_delivery_records`
--
ALTER TABLE `labor_delivery_records`
  ADD CONSTRAINT `fk_ld_mother` FOREIGN KEY (`mother_id`) REFERENCES `maternal_patients` (`mother_id`) ON UPDATE CASCADE;

--
-- Constraints for table `maternal_patients`
--
ALTER TABLE `maternal_patients`
  ADD CONSTRAINT `fk_mp_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_mp_purok` FOREIGN KEY (`purok_id`) REFERENCES `puroks` (`purok_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mp_user_account` FOREIGN KEY (`user_account_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `mothers_caregivers`
--
ALTER TABLE `mothers_caregivers`
  ADD CONSTRAINT `fk_mc_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_mc_purok` FOREIGN KEY (`purok_id`) REFERENCES `puroks` (`purok_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_mc_user_account` FOREIGN KEY (`user_account_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `nutrition_records`
--
ALTER TABLE `nutrition_records`
  ADD CONSTRAINT `fk_nr_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`),
  ADD CONSTRAINT `fk_nr_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_nr_status` FOREIGN KEY (`wfl_ht_status_id`) REFERENCES `wfl_ht_status_types` (`status_id`);

--
-- Constraints for table `overdue_notifications`
--
ALTER TABLE `overdue_notifications`
  ADD CONSTRAINT `fk_on_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`),
  ADD CONSTRAINT `fk_on_vaccine` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccine_types` (`vaccine_id`);

--
-- Constraints for table `parent_audit_log`
--
ALTER TABLE `parent_audit_log`
  ADD CONSTRAINT `fk_pal_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pal_parent` FOREIGN KEY (`parent_user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `parent_child_access`
--
ALTER TABLE `parent_child_access`
  ADD CONSTRAINT `fk_pca_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`),
  ADD CONSTRAINT `fk_pca_granted_by` FOREIGN KEY (`access_granted_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_pca_parent` FOREIGN KEY (`parent_user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `parent_notifications`
--
ALTER TABLE `parent_notifications`
  ADD CONSTRAINT `fk_pn_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`),
  ADD CONSTRAINT `fk_pn_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_pn_parent` FOREIGN KEY (`parent_user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_pn_vaccine` FOREIGN KEY (`related_vaccine_id`) REFERENCES `vaccine_types` (`vaccine_id`);

--
-- Constraints for table `postnatal_visits`
--
ALTER TABLE `postnatal_visits`
  ADD CONSTRAINT `fk_pnv_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pnv_mother` FOREIGN KEY (`mother_id`) REFERENCES `maternal_patients` (`mother_id`) ON DELETE CASCADE;

--
-- Constraints for table `supplementation_records`
--
ALTER TABLE `supplementation_records`
  ADD CONSTRAINT `fk_supp_admin` FOREIGN KEY (`administered_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_supp_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`);

--
-- Constraints for table `user_login_log`
--
ALTER TABLE `user_login_log`
  ADD CONSTRAINT `fk_ull_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
