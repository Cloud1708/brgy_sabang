-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 08, 2025 at 06:08 PM
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
(12, 11, 2, 'BNS', 'New BNS account created', '2025-10-08 07:38:06');

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
(2, 'Sepp', 'Bernard', 'Consulta', 'female', 3.50, 45.00, '2025-04-16', 1, 10, '2025-10-08 07:39:27', '2025-10-08 07:39:27');

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
  `recorded_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Prenatal/Postnatal health records';

--
-- Dumping data for table `health_records`
--

INSERT INTO `health_records` (`health_record_id`, `mother_id`, `consultation_date`, `age`, `height_cm`, `last_menstruation_date`, `expected_delivery_date`, `pregnancy_age_weeks`, `vaginal_bleeding`, `urinary_infection`, `weight_kg`, `blood_pressure_systolic`, `blood_pressure_diastolic`, `high_blood_pressure`, `fever_38_celsius`, `pallor`, `abnormal_abdominal_size`, `abnormal_presentation`, `absent_fetal_heartbeat`, `swelling`, `vaginal_infection`, `hgb_result`, `urine_result`, `vdrl_result`, `other_lab_results`, `recorded_by`, `created_at`, `updated_at`) VALUES
(2, 29, '2025-10-07', 25, 150.00, '2025-10-01', '2025-10-31', 7, 0, 0, 45.00, 110, 80, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 9, '2025-10-06 17:01:36', '2025-10-06 17:01:36'),
(3, 29, '2025-10-07', 25, 250.00, '2025-10-08', '2025-10-08', NULL, 0, 0, 55.00, 110, 70, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 9, '2025-10-06 17:11:59', '2025-10-06 17:11:59'),
(4, 31, '2025-10-08', 20, 152.00, '2025-10-09', '2025-10-31', 12, 1, 0, 50.00, 10, 10, 0, 0, 0, 0, 0, 0, 1, 1, 'haha', 'uti', 'hahahah', 'hahah', 10, '2025-10-08 08:48:34', '2025-10-08 08:48:34'),
(5, 32, '2025-10-08', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 9, '2025-10-08 15:44:39', '2025-10-08 15:44:39');

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
(5, 'eqwe', 'wq', 'weq', '2000-02-02', NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-06 16:38:43', '2025-10-06 16:38:43', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(29, 'qweq', 'wqe', 'weqe', '2000-02-02', NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-06 17:01:36', '2025-10-06 17:01:36', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(30, 'sdasda', 'weq', 'weq', '2004-12-02', NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-06 17:36:54', '2025-10-06 17:36:54', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(31, 'Gabrielle', 'gab', 'Resuello', '2004-11-11', 4, 5, 'A', 'Althea Gabrielle Reyes', '09958167775', '09992223324', 10, '2025-10-08 08:48:34', '2025-10-08 09:27:11', NULL, '2301', 'Sampaguita', NULL, 'Silverlas', 1, NULL),
(32, 'qwe', 'wqe', 'qwe', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 9, '2025-10-08 15:44:39', '2025-10-08 15:44:39', NULL, NULL, NULL, 'Purok 2', NULL, NULL, NULL);

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
(1, NULL, NULL, NULL, 'Placeholder Mother', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-08 05:37:41', '2025-10-08 05:37:41', NULL, NULL, NULL, NULL, NULL);

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
(2, 'Purok 2', 'Sabang', '2025-10-08 05:37:41');

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
  `last_name` varchar(100) NOT NULL DEFAULT '',
  `role_id` int(10) UNSIGNED NOT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_by_user_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Application users';

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `password_hash`, `first_name`, `last_name`, `role_id`, `barangay`, `is_active`, `created_at`, `updated_at`, `last_login_at`, `created_by_user_id`) VALUES
(2, 'admin', NULL, '', '$2y$10$sHL0FeWDe2/kJaxlFtcvkOXHgPFF7yfadgiO56cfckVIYP/ewdwyG', '', '', 1, NULL, 1, '2025-10-08 05:37:41', '2025-10-08 05:37:41', NULL, NULL),
(9, 'cris', 'sdasd@gmail.com', '', '$2y$10$sHL0FeWDe2/kJaxlFtcvkOXHgPFF7yfadgiO56cfckVIYP/ewdwyG', 'Cris', 'Hernandez', 2, 'Sabang', 1, '2025-10-08 05:37:41', '2025-10-08 05:37:41', NULL, 2),
(10, 'bnsses', 'bns@gmail.com', 'reyes77488', '$2y$10$BziwBgsgBhk3ZUIm3qS3dutrp0FJn/KuRxOK2Rmp6zgFlhHTVTWjy', 'bnss', 'reyes', 2, 'Sabang', 1, '2025-10-08 07:36:03', '2025-10-08 07:36:03', NULL, 2),
(11, 'althea gabriellees', 'raltheagabrielle@gmail.com', 'reyes49341', '$2y$10$p.qXQF4FkaE/4RJstceFE.cjk5GVpHdkRkTuCGCnvRwA6gFwUucH2', 'Althea Gabrielle', 'Reyes', 3, 'Sabang', 1, '2025-10-08 07:38:06', '2025-10-08 07:38:06', NULL, 2);

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
  MODIFY `log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `children`
--
ALTER TABLE `children`
  MODIFY `child_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `child_immunizations`
--
ALTER TABLE `child_immunizations`
  MODIFY `immunization_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `health_records`
--
ALTER TABLE `health_records`
  MODIFY `health_record_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `immunization_schedule`
--
ALTER TABLE `immunization_schedule`
  MODIFY `schedule_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `maternal_patients`
--
ALTER TABLE `maternal_patients`
  MODIFY `mother_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `mothers_caregivers`
--
ALTER TABLE `mothers_caregivers`
  MODIFY `mother_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `nutrition_records`
--
ALTER TABLE `nutrition_records`
  MODIFY `record_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `overdue_notifications`
--
ALTER TABLE `overdue_notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parent_audit_log`
--
ALTER TABLE `parent_audit_log`
  MODIFY `log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parent_child_access`
--
ALTER TABLE `parent_child_access`
  MODIFY `access_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parent_notifications`
--
ALTER TABLE `parent_notifications`
  MODIFY `notification_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `postnatal_visits`
--
ALTER TABLE `postnatal_visits`
  MODIFY `postnatal_visit_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `puroks`
--
ALTER TABLE `puroks`
  MODIFY `purok_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `supplementation_records`
--
ALTER TABLE `supplementation_records`
  MODIFY `supplement_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

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
