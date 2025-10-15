-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 16, 2023 at 11:27 PM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `therapy_track`
--

-- --------------------------------------------------------

--
-- Table structure for table `absence`
--

CREATE TABLE `absence` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `excused` tinyint(1) NOT NULL DEFAULT 0,
  `note` varchar(1024) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `activation_code` varchar(50) NOT NULL DEFAULT '',
  `rememberme` varchar(255) NOT NULL DEFAULT '',
  `role` enum('Member','Admin') NOT NULL DEFAULT 'Member',
  `registered` datetime NOT NULL,
  `last_seen` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`id`, `username`, `password`, `email`, `activation_code`, `rememberme`, `role`, `registered`, `last_seen`) VALUES
(1, 'admin', '$2y$10$ZU7Jq5yZ1U/ifeJoJzvLbenjRyJVkSzmQKQc.X0KDPkfR3qs/iA7O', 'admin@example.com', 'activated', '', 'Admin', '2023-01-01 00:00:00', '2023-04-05 02:54:12'),
(2, 'member', '$2y$10$yWKu95tLTnqdNhR/XfHtEekrjKJg2iVa8p65Da/EoijSPaFkRnmRG', 'member@example.com', 'activated', '', 'Member', '2023-01-01 00:00:00', '2023-04-02 02:05:47'),
(3, 'denton', '$2y$10$qb1uw6ClAUC.GYyosmy1q.gCFJ.WtJ94Fxd3PdKMgoVc3E5Brhvgu', 'denton@texasfells.com', '', '', 'Admin', '2023-03-30 04:58:00', '2023-07-06 04:20:37');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_record`
--

CREATE TABLE `attendance_record` (
  `client_id` int(11) NOT NULL,
  `therapy_session_id` int(11) NOT NULL,
  `note` varchar(512) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `case_manager`
--

CREATE TABLE `case_manager` (
  `id` int(11) NOT NULL,
  `first_name` varchar(45) NOT NULL,
  `last_name` varchar(45) NOT NULL,
  `office` varchar(45) DEFAULT NULL,
  `email` varchar(45) DEFAULT NULL,
  `phone_number` varchar(45) DEFAULT NULL,
  `fax` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client`
--

CREATE TABLE `client` (
  `id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `first_name` varchar(45) NOT NULL,
  `last_name` varchar(45) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender_id` int(11) NOT NULL DEFAULT 1,
  `email` varchar(64) DEFAULT NULL,
  `phone_number` varchar(45) DEFAULT NULL,
  `cause_number` varchar(15) DEFAULT NULL,
  `referral_type_id` int(11) NOT NULL,
  `ethnicity_id` int(11) DEFAULT NULL,
  `required_sessions` int(11) DEFAULT NULL,
  `fee` decimal(2,0) NOT NULL DEFAULT 30,
  `case_manager_id` int(11) DEFAULT NULL,
  `therapy_group_id` int(11) DEFAULT NULL,
  `client_stage_id` int(11) NOT NULL,
  `note` varchar(2048) DEFAULT NULL,
  `emergency_contact` varchar(512) DEFAULT NULL,
  `orientation_date` date DEFAULT NULL,
  `exit_date` date DEFAULT NULL,
  `exit_reason_id` int(11) DEFAULT NULL,
  `exit_note` varchar(512) DEFAULT NULL,
  `documents_url` varchar(128) DEFAULT NULL,
  `speaksSignificantlyInGroup` tinyint(4) NOT NULL DEFAULT 0,
  `respectfulTowardsGroup` tinyint(4) NOT NULL DEFAULT 1,
  `takesResponsibilityForPastBehavior` tinyint(4) NOT NULL DEFAULT 1,
  `disruptiveOrArgumentitive` tinyint(4) NOT NULL DEFAULT 0,
  `inappropriateHumor` tinyint(4) NOT NULL DEFAULT 0,
  `blamesVictim` tinyint(4) NOT NULL DEFAULT 0,
  `drug_alcohol` tinyint(1) NOT NULL DEFAULT 0,
  `inappropriate_behavior_to_staff` tinyint(1) NOT NULL DEFAULT 0,
  `other_concerns` varchar(2048) DEFAULT NULL,
  `weekly_attendance` int(1) NOT NULL DEFAULT 0,
  `attends_sunday` tinyint(1) NOT NULL DEFAULT 0,
  `attends_monday` tinyint(1) NOT NULL DEFAULT 0,
  `attends_tuesday` tinyint(1) NOT NULL DEFAULT 0,
  `attends_wednesday` tinyint(1) NOT NULL DEFAULT 0,
  `attends_thursday` tinyint(1) NOT NULL DEFAULT 0,
  `attends_friday` tinyint(1) NOT NULL DEFAULT 0,
  `attends_saturday` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_stage`
--

CREATE TABLE `client_stage` (
  `id` int(11) NOT NULL,
  `stage` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client_stage`
--

INSERT INTO `client_stage` (`id`, `stage`) VALUES
(1, 'Precontemplation'),
(2, 'Contemplation'),
(3, 'Preparation – action'),
(4, 'Maintenance'),
(5, 'Relapse');

-- --------------------------------------------------------

--
-- Table structure for table `conversion`
--

CREATE TABLE `conversion` (
  `id` int(11) NOT NULL,
  `sheet` varchar(45) NOT NULL,
  `tab` varchar(45) NOT NULL,
  `date` date NOT NULL,
  `name` varchar(45) NOT NULL,
  `dob` date NOT NULL,
  `age` int(11) DEFAULT NULL,
  `ethnicity` varchar(16) DEFAULT NULL,
  `18_27_wks` int(11) DEFAULT NULL,
  `cause_number` varchar(45) DEFAULT NULL,
  `parole_officer` varchar(128) DEFAULT NULL,
  `parole_officer_first` varchar(45) DEFAULT NULL,
  `parole_officer_last` varchar(45) DEFAULT NULL,
  `po_office` varchar(45) DEFAULT NULL,
  `paid` int(11) DEFAULT NULL,
  `owes` int(11) DEFAULT NULL,
  `fee_prob` varchar(45) DEFAULT NULL,
  `pay_fail` int(11) DEFAULT NULL,
  `attended` int(11) DEFAULT NULL,
  `missed` int(11) DEFAULT NULL,
  `note` varchar(1024) DEFAULT NULL,
  `phone` varchar(45) DEFAULT NULL,
  `speaks_sig` varchar(16) DEFAULT NULL,
  `respect` varchar(5) DEFAULT NULL,
  `respons_past` varchar(5) DEFAULT NULL,
  `disrup_arg` varchar(5) DEFAULT NULL,
  `humor_inap` varchar(5) DEFAULT NULL,
  `blames` varchar(5) DEFAULT NULL,
  `drug_alc` varchar(5) DEFAULT NULL,
  `inapp` varchar(5) DEFAULT NULL,
  `other_conc` varchar(1024) DEFAULT NULL,
  `intake_orientation` date DEFAULT NULL,
  `P1` date DEFAULT NULL,
  `P2` date DEFAULT NULL,
  `P3` date DEFAULT NULL,
  `P4` date DEFAULT NULL,
  `P5` date DEFAULT NULL,
  `P6` date DEFAULT NULL,
  `P7` date DEFAULT NULL,
  `P8` date DEFAULT NULL,
  `P9` date DEFAULT NULL,
  `P10` date DEFAULT NULL,
  `P11` date DEFAULT NULL,
  `P12` date DEFAULT NULL,
  `P13` date DEFAULT NULL,
  `P14` date DEFAULT NULL,
  `P15` date DEFAULT NULL,
  `P16` date DEFAULT NULL,
  `P17` date DEFAULT NULL,
  `P18` date DEFAULT NULL,
  `P19` date DEFAULT NULL,
  `P20` date DEFAULT NULL,
  `P21` date DEFAULT NULL,
  `P22` date DEFAULT NULL,
  `P23` date DEFAULT NULL,
  `P24` date DEFAULT NULL,
  `P25` date DEFAULT NULL,
  `P26` date DEFAULT NULL,
  `P27` date DEFAULT NULL,
  `A1` date DEFAULT NULL,
  `A2` date DEFAULT NULL,
  `A3` date DEFAULT NULL,
  `A4` date DEFAULT NULL,
  `A5` date DEFAULT NULL,
  `A6` date DEFAULT NULL,
  `A7` date DEFAULT NULL,
  `A8` date DEFAULT NULL,
  `A9` date DEFAULT NULL,
  `A10` date DEFAULT NULL,
  `A11` date DEFAULT NULL,
  `A12` date DEFAULT NULL,
  `A13` date DEFAULT NULL,
  `A14` date DEFAULT NULL,
  `A15` date DEFAULT NULL,
  `A16` date DEFAULT NULL,
  `A17` date DEFAULT NULL,
  `A18` date DEFAULT NULL,
  `intake_assessment` varchar(3) DEFAULT NULL,
  `evaluating_risk` varchar(3) DEFAULT NULL,
  `victim_impact` varchar(3) DEFAULT NULL,
  `stipulation_adherence` varchar(3) DEFAULT NULL,
  `time_out` varchar(3) DEFAULT NULL,
  `red_flags` varchar(3) DEFAULT NULL,
  `addiction_recovery` varchar(3) DEFAULT NULL,
  `assertiveness` varchar(3) DEFAULT NULL,
  `aggression_triggers` varchar(3) DEFAULT NULL,
  `rebt_abcs` varchar(3) DEFAULT NULL,
  `community_resources` varchar(3) DEFAULT NULL,
  `mental_health_symptoms` varchar(3) DEFAULT NULL,
  `parenting_skills` varchar(3) DEFAULT NULL,
  `validation` varchar(3) DEFAULT NULL,
  `breaking_violence_cycle` varchar(3) DEFAULT NULL,
  `mood_regulation` varchar(3) DEFAULT NULL,
  `marriage_skills` varchar(3) DEFAULT NULL,
  `emotional_self_care` varchar(3) DEFAULT NULL,
  `medical_self_care` varchar(3) DEFAULT NULL,
  `brain_health` varchar(3) DEFAULT NULL,
  `impulse_control` varchar(3) DEFAULT NULL,
  `nutrition` varchar(3) DEFAULT NULL,
  `spiritual_support` varchar(3) DEFAULT NULL,
  `progressive_relaxation` varchar(3) DEFAULT NULL,
  `healthy_dating` varchar(3) DEFAULT NULL,
  `safety_planning` varchar(3) DEFAULT NULL,
  `changing_core_beliefs` varchar(3) DEFAULT NULL,
  `cognitive_coping_skills` varchar(3) DEFAULT NULL,
  `stress_management` varchar(3) DEFAULT NULL,
  `conflict_resolution` varchar(3) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `curriculum`
--

CREATE TABLE `curriculum` (
  `id` int(11) NOT NULL,
  `short_description` varchar(64) NOT NULL,
  `long_description` varchar(256) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ethnicity`
--

CREATE TABLE `ethnicity` (
  `id` int(11) NOT NULL,
  `code` varchar(1) NOT NULL,
  `name` varchar(16) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ethnicity`
--

INSERT INTO `ethnicity` (`id`, `code`, `name`) VALUES
(1, 'A', 'Asian'),
(2, 'B', 'Black'),
(3, 'H', 'Hispanic'),
(4, 'W', 'White'),
(5, 'O', 'Other');

-- --------------------------------------------------------

--
-- Table structure for table `exit_reason`
--

CREATE TABLE `exit_reason` (
  `id` int(11) NOT NULL,
  `reason` varchar(45) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `exit_reason`
--

INSERT INTO `exit_reason` (`id`, `reason`) VALUES
(1, 'Not Exited'),
(2, 'Other'),
(3, 'Completion of Program'),
(4, 'Violation of Requirements'),
(5, 'Unable to Participate'),
(6, 'Death'),
(7, 'Moved');

-- --------------------------------------------------------

--
-- Table structure for table `facilitator`
--

CREATE TABLE `facilitator` (
  `id` int(11) NOT NULL,
  `first_name` varchar(45) NOT NULL,
  `last_name` varchar(45) NOT NULL,
  `email` varchar(45) DEFAULT NULL,
  `phone` varchar(45) NOT NULL,
  `licensure` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gender`
--

CREATE TABLE `gender` (
  `id` int(11) NOT NULL,
  `gender` varchar(16) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `gender`
--

INSERT INTO `gender` (`id`, `gender`) VALUES
(1, 'not specified'),
(2, 'male'),
(3, 'female');

-- --------------------------------------------------------

--
-- Table structure for table `image`
--

CREATE TABLE `image` (
  `id` int(11) NOT NULL,
  `hash` varchar(128) DEFAULT NULL,
  `image_data` longblob NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ledger`
--

CREATE TABLE `ledger` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `amount` decimal(3,0) NOT NULL,
  `create_date` datetime NOT NULL DEFAULT current_timestamp(),
  `note` varchar(45) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `program`
--

CREATE TABLE `program` (
  `id` int(11) NOT NULL,
  `name` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `program`
--

INSERT INTO `program` (`id`, `name`) VALUES
(1, 'Thinking for a Change'),
(2, 'BIPP');

-- --------------------------------------------------------

--
-- Table structure for table `referral_type`
--

CREATE TABLE `referral_type` (
  `id` int(11) NOT NULL,
  `referral_type` varchar(45) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `referral_type`
--

INSERT INTO `referral_type` (`id`, `referral_type`) VALUES
(0, 'other'),
(1, 'Probation'),
(2, 'Parole'),
(3, 'Pretrial');

-- --------------------------------------------------------

--
-- Table structure for table `report`
--

CREATE TABLE `report` (
  `client_id` int(11) NOT NULL,
  `report_date` datetime NOT NULL DEFAULT current_timestamp(),
  `first_name` varchar(45) NOT NULL,
  `last_name` varchar(45) NOT NULL,
  `dob` date DEFAULT NULL,
  `age` int(2) NOT NULL,
  `ethnicity_code` varchar(1) NOT NULL,
  `ethnicity_name` varchar(16) NOT NULL,
  `required_sessions` int(11) NOT NULL,
  `cause_number` varchar(15) DEFAULT NULL,
  `referral_type` varchar(45) NOT NULL,
  `po_first` varchar(45) NOT NULL,
  `po_last` varchar(45) NOT NULL,
  `po_office` varchar(45) NOT NULL,
  `paid` decimal(4,0) NOT NULL DEFAULT 0,
  `owes` decimal(4,0) NOT NULL DEFAULT 0,
  `fee_prob` tinyint(1) NOT NULL DEFAULT 0,
  `pay_fail` int(2) NOT NULL DEFAULT 0,
  `attended` int(3) NOT NULL DEFAULT 0,
  `missed` int(3) NOT NULL DEFAULT 0,
  `client_note` varchar(512) NOT NULL,
  `speaks_sig` varchar(1) NOT NULL,
  `respect` varchar(1) NOT NULL,
  `respons_past` varchar(1) NOT NULL,
  `disrup_arg` varchar(1) NOT NULL,
  `humor_inap` varchar(1) NOT NULL,
  `blames` varchar(1) NOT NULL,
  `drug_alc` varchar(1) NOT NULL,
  `inapp` varchar(1) NOT NULL,
  `other_conc` varchar(512) NOT NULL,
  `intake_orientation` date DEFAULT NULL,
  `P1` date DEFAULT NULL,
  `P2` date DEFAULT NULL,
  `P3` date DEFAULT NULL,
  `P4` date DEFAULT NULL,
  `P5` date DEFAULT NULL,
  `P6` date DEFAULT NULL,
  `P7` date DEFAULT NULL,
  `P8` date DEFAULT NULL,
  `P9` date DEFAULT NULL,
  `P10` date DEFAULT NULL,
  `P11` date DEFAULT NULL,
  `P12` date DEFAULT NULL,
  `P13` date DEFAULT NULL,
  `P14` date DEFAULT NULL,
  `P15` date DEFAULT NULL,
  `P16` date DEFAULT NULL,
  `P17` date DEFAULT NULL,
  `P18` date DEFAULT NULL,
  `P19` date DEFAULT NULL,
  `P20` date DEFAULT NULL,
  `P21` date DEFAULT NULL,
  `P22` date DEFAULT NULL,
  `P23` date DEFAULT NULL,
  `P24` date DEFAULT NULL,
  `P25` date DEFAULT NULL,
  `P26` date DEFAULT NULL,
  `P27` date DEFAULT NULL,
  `A1` date DEFAULT NULL,
  `A2` date DEFAULT NULL,
  `A3` date DEFAULT NULL,
  `A4` date DEFAULT NULL,
  `A5` date DEFAULT NULL,
  `A6` date DEFAULT NULL,
  `A7` date DEFAULT NULL,
  `A8` date DEFAULT NULL,
  `A9` date DEFAULT NULL,
  `A10` date DEFAULT NULL,
  `A11` date DEFAULT NULL,
  `A12` date DEFAULT NULL,
  `A13` date DEFAULT NULL,
  `A14` date DEFAULT NULL,
  `A15` date DEFAULT NULL,
  `A16` date DEFAULT NULL,
  `A17` date DEFAULT NULL,
  `A18` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report2`
--

CREATE TABLE `report2` (
  `client_id` int(11) NOT NULL,
  `report_date` datetime NOT NULL DEFAULT current_timestamp(),
  `program_name` varchar(64) NOT NULL,
  `first_name` varchar(45) NOT NULL,
  `last_name` varchar(45) NOT NULL,
  `image_url` varchar(256) NOT NULL,
  `dob` date DEFAULT NULL,
  `age` int(2) NOT NULL,
  `gender` varchar(16) NOT NULL,
  `phone_number` varchar(45) DEFAULT NULL,
  `ethnicity_code` varchar(1) NOT NULL,
  `ethnicity_name` varchar(16) NOT NULL,
  `required_sessions` int(11) NOT NULL,
  `cause_number` varchar(15) DEFAULT NULL,
  `referral_type` varchar(45) NOT NULL,
  `case_manager_first_name` varchar(45) NOT NULL,
  `case_manager_last_name` varchar(45) NOT NULL,
  `case_manager_office` varchar(45) NOT NULL,
  `group_name` varchar(45) DEFAULT NULL,
  `fee` decimal(4,0) NOT NULL DEFAULT 0,
  `balance` decimal(4,0) NOT NULL DEFAULT 0,
  `attended` int(3) NOT NULL DEFAULT 0,
  `absence_excused` int(3) NOT NULL DEFAULT 0,
  `absence_unexcused` int(3) NOT NULL DEFAULT 0,
  `client_stage` varchar(128) NOT NULL,
  `client_note` varchar(512) NOT NULL,
  `speaks_significantly_in_group` varchar(1) NOT NULL,
  `respectful_to_group` varchar(1) NOT NULL,
  `takes_responsibility_for_past` varchar(1) NOT NULL,
  `disruptive_argumentitive` varchar(1) NOT NULL,
  `humor_inappropriate` varchar(1) NOT NULL,
  `blames_victim` varchar(1) NOT NULL,
  `appears_drug_alcohol` varchar(1) NOT NULL,
  `inappropriate_to_staff` varchar(1) NOT NULL,
  `other_concerns` varchar(512) DEFAULT NULL,
  `orientation_date` date DEFAULT NULL,
  `exit_date` date DEFAULT NULL,
  `exit_reason` varchar(45) DEFAULT NULL,
  `exit_note` varchar(512) DEFAULT NULL,
  `P1` date DEFAULT NULL,
  `P1_cur` varchar(64) DEFAULT NULL,
  `P2` date DEFAULT NULL,
  `P2_cur` varchar(64) DEFAULT NULL,
  `P3` date DEFAULT NULL,
  `P3_cur` varchar(64) DEFAULT NULL,
  `P4` date DEFAULT NULL,
  `P4_cur` varchar(64) DEFAULT NULL,
  `P5` date DEFAULT NULL,
  `P5_cur` varchar(64) DEFAULT NULL,
  `P6` date DEFAULT NULL,
  `P6_cur` varchar(64) DEFAULT NULL,
  `P7` date DEFAULT NULL,
  `P7_cur` varchar(64) DEFAULT NULL,
  `P8` date DEFAULT NULL,
  `P8_cur` varchar(64) DEFAULT NULL,
  `P9` date DEFAULT NULL,
  `P9_cur` varchar(64) DEFAULT NULL,
  `P10` date DEFAULT NULL,
  `P10_cur` varchar(64) DEFAULT NULL,
  `P11` date DEFAULT NULL,
  `P11_cur` varchar(64) DEFAULT NULL,
  `P12` date DEFAULT NULL,
  `P12_cur` varchar(64) DEFAULT NULL,
  `P13` date DEFAULT NULL,
  `P13_cur` varchar(64) DEFAULT NULL,
  `P14` date DEFAULT NULL,
  `P14_cur` varchar(64) DEFAULT NULL,
  `P15` date DEFAULT NULL,
  `P15_cur` varchar(64) DEFAULT NULL,
  `P16` date DEFAULT NULL,
  `P16_cur` varchar(64) DEFAULT NULL,
  `P17` date DEFAULT NULL,
  `P17_cur` varchar(64) DEFAULT NULL,
  `P18` date DEFAULT NULL,
  `P18_cur` varchar(64) DEFAULT NULL,
  `P19` date DEFAULT NULL,
  `P19_cur` varchar(64) DEFAULT NULL,
  `P20` date DEFAULT NULL,
  `P20_cur` varchar(64) DEFAULT NULL,
  `P21` date DEFAULT NULL,
  `P21_cur` varchar(64) DEFAULT NULL,
  `P22` date DEFAULT NULL,
  `P22_cur` varchar(64) DEFAULT NULL,
  `P23` date DEFAULT NULL,
  `P23_cur` varchar(64) DEFAULT NULL,
  `P24` date DEFAULT NULL,
  `P24_cur` varchar(64) DEFAULT NULL,
  `P25` date DEFAULT NULL,
  `P25_cur` varchar(64) DEFAULT NULL,
  `P26` date DEFAULT NULL,
  `P26_cur` varchar(64) DEFAULT NULL,
  `P27` date DEFAULT NULL,
  `P27_cur` varchar(64) DEFAULT NULL,
  `P28` date DEFAULT NULL,
  `P28_cur` varchar(64) DEFAULT NULL,
  `P29` date DEFAULT NULL,
  `P29_cur` varchar(64) DEFAULT NULL,
  `P30` date DEFAULT NULL,
  `P30_cur` varchar(64) DEFAULT NULL,
  `P31` date DEFAULT NULL,
  `P31_cur` varchar(64) DEFAULT NULL,
  `P32` date DEFAULT NULL,
  `P32_cur` varchar(64) DEFAULT NULL,
  `P33` date DEFAULT NULL,
  `P33_cur` varchar(64) DEFAULT NULL,
  `P34` date DEFAULT NULL,
  `P34_cur` varchar(64) DEFAULT NULL,
  `P35` date DEFAULT NULL,
  `P35_cur` varchar(64) DEFAULT NULL,
  `A1` date DEFAULT NULL,
  `A2` date DEFAULT NULL,
  `A3` date DEFAULT NULL,
  `A4` date DEFAULT NULL,
  `A5` date DEFAULT NULL,
  `A6` date DEFAULT NULL,
  `A7` date DEFAULT NULL,
  `A8` date DEFAULT NULL,
  `A9` date DEFAULT NULL,
  `A10` date DEFAULT NULL,
  `A11` date DEFAULT NULL,
  `A12` date DEFAULT NULL,
  `A13` date DEFAULT NULL,
  `A14` date DEFAULT NULL,
  `A15` date DEFAULT NULL,
  `A16` date DEFAULT NULL,
  `A17` date DEFAULT NULL,
  `A18` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report3`
--

CREATE TABLE `report3` (
  `client_id` int(11) NOT NULL,
  `report_date` datetime NOT NULL DEFAULT current_timestamp(),
  `program_name` varchar(64) NOT NULL,
  `first_name` varchar(45) NOT NULL,
  `last_name` varchar(45) NOT NULL,
  `image_url` varchar(256) NOT NULL,
  `dob` date DEFAULT NULL,
  `age` int(2) NOT NULL,
  `gender` varchar(16) NOT NULL,
  `phone_number` varchar(45) DEFAULT NULL,
  `ethnicity_code` varchar(1) NOT NULL,
  `ethnicity_name` varchar(16) NOT NULL,
  `required_sessions` int(11) NOT NULL,
  `cause_number` varchar(15) DEFAULT NULL,
  `referral_type` varchar(45) NOT NULL,
  `case_manager_first_name` varchar(45) NOT NULL,
  `case_manager_last_name` varchar(45) NOT NULL,
  `case_manager_office` varchar(45) NOT NULL,
  `group_name` varchar(45) DEFAULT NULL,
  `fee` decimal(4,0) NOT NULL DEFAULT 0,
  `balance` decimal(4,0) NOT NULL DEFAULT 0,
  `attended` int(3) NOT NULL DEFAULT 0,
  `absence_excused` int(3) NOT NULL DEFAULT 0,
  `absence_unexcused` int(3) NOT NULL DEFAULT 0,
  `client_stage` varchar(128) NOT NULL,
  `client_note` varchar(512) NOT NULL,
  `speaks_significantly_in_group` varchar(1) NOT NULL,
  `respectful_to_group` varchar(1) NOT NULL,
  `takes_responsibility_for_past` varchar(1) NOT NULL,
  `disruptive_argumentitive` varchar(1) NOT NULL,
  `humor_inappropriate` varchar(1) NOT NULL,
  `blames_victim` varchar(1) NOT NULL,
  `appears_drug_alcohol` varchar(1) NOT NULL,
  `inappropriate_to_staff` varchar(1) NOT NULL,
  `other_concerns` varchar(512) DEFAULT NULL,
  `orientation_date` date DEFAULT NULL,
  `exit_date` date DEFAULT NULL,
  `exit_reason` varchar(45) DEFAULT NULL,
  `exit_note` varchar(512) DEFAULT NULL,
  `c1_header` varchar(16) DEFAULT NULL,
  `c1_11` varchar(8) DEFAULT NULL,
  `c1_12` varchar(8) DEFAULT NULL,
  `c1_13` varchar(8) DEFAULT NULL,
  `c1_14` varchar(8) DEFAULT NULL,
  `c1_15` varchar(8) DEFAULT NULL,
  `c1_16` varchar(8) DEFAULT NULL,
  `c1_17` varchar(8) DEFAULT NULL,
  `c1_21` varchar(8) DEFAULT NULL,
  `c1_22` varchar(8) DEFAULT NULL,
  `c1_23` varchar(8) DEFAULT NULL,
  `c1_24` varchar(8) DEFAULT NULL,
  `c1_25` varchar(8) DEFAULT NULL,
  `c1_26` varchar(8) DEFAULT NULL,
  `c1_27` varchar(8) DEFAULT NULL,
  `c1_31` varchar(8) DEFAULT NULL,
  `c1_32` varchar(8) DEFAULT NULL,
  `c1_33` varchar(8) DEFAULT NULL,
  `c1_34` varchar(8) DEFAULT NULL,
  `c1_35` varchar(8) DEFAULT NULL,
  `c1_36` varchar(8) DEFAULT NULL,
  `c1_37` varchar(8) DEFAULT NULL,
  `c1_41` varchar(8) DEFAULT NULL,
  `c1_42` varchar(8) DEFAULT NULL,
  `c1_43` varchar(8) DEFAULT NULL,
  `c1_44` varchar(8) DEFAULT NULL,
  `c1_45` varchar(8) DEFAULT NULL,
  `c1_46` varchar(8) DEFAULT NULL,
  `c1_47` varchar(8) DEFAULT NULL,
  `c1_51` varchar(8) DEFAULT NULL,
  `c1_52` varchar(8) DEFAULT NULL,
  `c1_53` varchar(8) DEFAULT NULL,
  `c1_54` varchar(8) DEFAULT NULL,
  `c1_55` varchar(8) DEFAULT NULL,
  `c1_56` varchar(8) DEFAULT NULL,
  `c1_57` varchar(8) DEFAULT NULL,
  `c1_61` varchar(8) DEFAULT NULL,
  `c1_62` varchar(8) DEFAULT NULL,
  `c1_63` varchar(8) DEFAULT NULL,
  `c1_64` varchar(8) DEFAULT NULL,
  `c1_65` varchar(8) DEFAULT NULL,
  `c1_66` varchar(8) DEFAULT NULL,
  `c1_67` varchar(8) DEFAULT NULL,
  `c2_header` varchar(16) DEFAULT NULL,
  `c2_11` varchar(8) DEFAULT NULL,
  `c2_12` varchar(8) DEFAULT NULL,
  `c2_13` varchar(8) DEFAULT NULL,
  `c2_14` varchar(8) DEFAULT NULL,
  `c2_15` varchar(8) DEFAULT NULL,
  `c2_16` varchar(8) DEFAULT NULL,
  `c2_17` varchar(8) DEFAULT NULL,
  `c2_21` varchar(8) DEFAULT NULL,
  `c2_22` varchar(8) DEFAULT NULL,
  `c2_23` varchar(8) DEFAULT NULL,
  `c2_24` varchar(8) DEFAULT NULL,
  `c2_25` varchar(8) DEFAULT NULL,
  `c2_26` varchar(8) DEFAULT NULL,
  `c2_27` varchar(8) DEFAULT NULL,
  `c2_31` varchar(8) DEFAULT NULL,
  `c2_32` varchar(8) DEFAULT NULL,
  `c2_33` varchar(8) DEFAULT NULL,
  `c2_34` varchar(8) DEFAULT NULL,
  `c2_35` varchar(8) DEFAULT NULL,
  `c2_36` varchar(8) DEFAULT NULL,
  `c2_37` varchar(8) DEFAULT NULL,
  `c2_41` varchar(8) DEFAULT NULL,
  `c2_42` varchar(8) DEFAULT NULL,
  `c2_43` varchar(8) DEFAULT NULL,
  `c2_44` varchar(8) DEFAULT NULL,
  `c2_45` varchar(8) DEFAULT NULL,
  `c2_46` varchar(8) DEFAULT NULL,
  `c2_47` varchar(8) DEFAULT NULL,
  `c2_51` varchar(8) DEFAULT NULL,
  `c2_52` varchar(8) DEFAULT NULL,
  `c2_53` varchar(8) DEFAULT NULL,
  `c2_54` varchar(8) DEFAULT NULL,
  `c2_55` varchar(8) DEFAULT NULL,
  `c2_56` varchar(8) DEFAULT NULL,
  `c2_57` varchar(8) DEFAULT NULL,
  `c2_61` varchar(8) DEFAULT NULL,
  `c2_62` varchar(8) DEFAULT NULL,
  `c2_63` varchar(8) DEFAULT NULL,
  `c2_64` varchar(8) DEFAULT NULL,
  `c2_65` varchar(8) DEFAULT NULL,
  `c2_66` varchar(8) DEFAULT NULL,
  `c2_67` varchar(8) DEFAULT NULL,
  `c3_header` varchar(16) DEFAULT NULL,
  `c3_11` varchar(8) DEFAULT NULL,
  `c3_12` varchar(8) DEFAULT NULL,
  `c3_13` varchar(8) DEFAULT NULL,
  `c3_14` varchar(8) DEFAULT NULL,
  `c3_15` varchar(8) DEFAULT NULL,
  `c3_16` varchar(8) DEFAULT NULL,
  `c3_17` varchar(8) DEFAULT NULL,
  `c3_21` varchar(8) DEFAULT NULL,
  `c3_22` varchar(8) DEFAULT NULL,
  `c3_23` varchar(8) DEFAULT NULL,
  `c3_24` varchar(8) DEFAULT NULL,
  `c3_25` varchar(8) DEFAULT NULL,
  `c3_26` varchar(8) DEFAULT NULL,
  `c3_27` varchar(8) DEFAULT NULL,
  `c3_31` varchar(8) DEFAULT NULL,
  `c3_32` varchar(8) DEFAULT NULL,
  `c3_33` varchar(8) DEFAULT NULL,
  `c3_34` varchar(8) DEFAULT NULL,
  `c3_35` varchar(8) DEFAULT NULL,
  `c3_36` varchar(8) DEFAULT NULL,
  `c3_37` varchar(8) DEFAULT NULL,
  `c3_41` varchar(8) DEFAULT NULL,
  `c3_42` varchar(8) DEFAULT NULL,
  `c3_43` varchar(8) DEFAULT NULL,
  `c3_44` varchar(8) DEFAULT NULL,
  `c3_45` varchar(8) DEFAULT NULL,
  `c3_46` varchar(8) DEFAULT NULL,
  `c3_47` varchar(8) DEFAULT NULL,
  `c3_51` varchar(8) DEFAULT NULL,
  `c3_52` varchar(8) DEFAULT NULL,
  `c3_53` varchar(8) DEFAULT NULL,
  `c3_54` varchar(8) DEFAULT NULL,
  `c3_55` varchar(8) DEFAULT NULL,
  `c3_56` varchar(8) DEFAULT NULL,
  `c3_57` varchar(8) DEFAULT NULL,
  `c3_61` varchar(8) DEFAULT NULL,
  `c3_62` varchar(8) DEFAULT NULL,
  `c3_63` varchar(8) DEFAULT NULL,
  `c3_64` varchar(8) DEFAULT NULL,
  `c3_65` varchar(8) DEFAULT NULL,
  `c3_66` varchar(8) DEFAULT NULL,
  `c3_67` varchar(8) DEFAULT NULL,
  `c4_header` varchar(16) DEFAULT NULL,
  `c4_11` varchar(8) DEFAULT NULL,
  `c4_12` varchar(8) DEFAULT NULL,
  `c4_13` varchar(8) DEFAULT NULL,
  `c4_14` varchar(8) DEFAULT NULL,
  `c4_15` varchar(8) DEFAULT NULL,
  `c4_16` varchar(8) DEFAULT NULL,
  `c4_17` varchar(8) DEFAULT NULL,
  `c4_21` varchar(8) DEFAULT NULL,
  `c4_22` varchar(8) DEFAULT NULL,
  `c4_23` varchar(8) DEFAULT NULL,
  `c4_24` varchar(8) DEFAULT NULL,
  `c4_25` varchar(8) DEFAULT NULL,
  `c4_26` varchar(8) DEFAULT NULL,
  `c4_27` varchar(8) DEFAULT NULL,
  `c4_31` varchar(8) DEFAULT NULL,
  `c4_32` varchar(8) DEFAULT NULL,
  `c4_33` varchar(8) DEFAULT NULL,
  `c4_34` varchar(8) DEFAULT NULL,
  `c4_35` varchar(8) DEFAULT NULL,
  `c4_36` varchar(8) DEFAULT NULL,
  `c4_37` varchar(8) DEFAULT NULL,
  `c4_41` varchar(8) DEFAULT NULL,
  `c4_42` varchar(8) DEFAULT NULL,
  `c4_43` varchar(8) DEFAULT NULL,
  `c4_44` varchar(8) DEFAULT NULL,
  `c4_45` varchar(8) DEFAULT NULL,
  `c4_46` varchar(8) DEFAULT NULL,
  `c4_47` varchar(8) DEFAULT NULL,
  `c4_51` varchar(8) DEFAULT NULL,
  `c4_52` varchar(8) DEFAULT NULL,
  `c4_53` varchar(8) DEFAULT NULL,
  `c4_54` varchar(8) DEFAULT NULL,
  `c4_55` varchar(8) DEFAULT NULL,
  `c4_56` varchar(8) DEFAULT NULL,
  `c4_57` varchar(8) DEFAULT NULL,
  `c4_61` varchar(8) DEFAULT NULL,
  `c4_62` varchar(8) DEFAULT NULL,
  `c4_63` varchar(8) DEFAULT NULL,
  `c4_64` varchar(8) DEFAULT NULL,
  `c4_65` varchar(8) DEFAULT NULL,
  `c4_66` varchar(8) DEFAULT NULL,
  `c4_67` varchar(8) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `therapy_group`
--

CREATE TABLE `therapy_group` (
  `id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `name` varchar(45) NOT NULL,
  `address` varchar(45) DEFAULT NULL,
  `city` varchar(45) DEFAULT NULL,
  `state` varchar(45) DEFAULT NULL,
  `zip` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `therapy_session`
--

CREATE TABLE `therapy_session` (
  `id` int(11) NOT NULL,
  `therapy_group_id` int(11) NOT NULL,
  `date` datetime NOT NULL,
  `duration_minutes` int(3) NOT NULL,
  `curriculum_id` int(11) DEFAULT NULL,
  `facilitator_id` int(11) DEFAULT NULL,
  `note` varchar(512) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `absence`
--
ALTER TABLE `absence`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id_fk` (`client_id`);

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `attendance_record`
--
ALTER TABLE `attendance_record`
  ADD PRIMARY KEY (`client_id`,`therapy_session_id`),
  ADD KEY `attendance_record_therapy_session_fk_idx` (`therapy_session_id`);

--
-- Indexes for table `case_manager`
--
ALTER TABLE `case_manager`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `client`
--
ALTER TABLE `client`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ethnicity_id_fk_idx` (`ethnicity_id`),
  ADD KEY `case_manager_id_fk_idx` (`case_manager_id`),
  ADD KEY `fk_client_therapy_group` (`therapy_group_id`),
  ADD KEY `fk_client_referral_type` (`referral_type_id`),
  ADD KEY `client_gender_fk` (`gender_id`),
  ADD KEY `client_program_fk` (`program_id`),
  ADD KEY `client_client_stage_fk` (`client_stage_id`);

--
-- Indexes for table `client_stage`
--
ALTER TABLE `client_stage`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `conversion`
--
ALTER TABLE `conversion`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `curriculum`
--
ALTER TABLE `curriculum`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ethnicity`
--
ALTER TABLE `ethnicity`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `exit_reason`
--
ALTER TABLE `exit_reason`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `facilitator`
--
ALTER TABLE `facilitator`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gender`
--
ALTER TABLE `gender`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `image`
--
ALTER TABLE `image`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ledger`
--
ALTER TABLE `ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ledger_client_fk` (`client_id`);

--
-- Indexes for table `program`
--
ALTER TABLE `program`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `referral_type`
--
ALTER TABLE `referral_type`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `report`
--
ALTER TABLE `report`
  ADD PRIMARY KEY (`client_id`);

--
-- Indexes for table `therapy_group`
--
ALTER TABLE `therapy_group`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UQ_therapy_group_name` (`name`) USING BTREE,
  ADD KEY `therapy_group_program_fk` (`program_id`);

--
-- Indexes for table `therapy_session`
--
ALTER TABLE `therapy_session`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_therapy_session_curriculum` (`curriculum_id`),
  ADD KEY `fk_therapy_session_therapy_group` (`therapy_group_id`),
  ADD KEY `fk_therapy_session_facilitator` (`facilitator_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `absence`
--
ALTER TABLE `absence`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `case_manager`
--
ALTER TABLE `case_manager`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client`
--
ALTER TABLE `client`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_stage`
--
ALTER TABLE `client_stage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `conversion`
--
ALTER TABLE `conversion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `curriculum`
--
ALTER TABLE `curriculum`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exit_reason`
--
ALTER TABLE `exit_reason`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `facilitator`
--
ALTER TABLE `facilitator`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gender`
--
ALTER TABLE `gender`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `ledger`
--
ALTER TABLE `ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `program`
--
ALTER TABLE `program`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `referral_type`
--
ALTER TABLE `referral_type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `therapy_group`
--
ALTER TABLE `therapy_group`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `therapy_session`
--
ALTER TABLE `therapy_session`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `absence`
--
ALTER TABLE `absence`
  ADD CONSTRAINT `client_id_fk` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`);

--
-- Constraints for table `attendance_record`
--
ALTER TABLE `attendance_record`
  ADD CONSTRAINT `attendance_record_client_fk` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`),
  ADD CONSTRAINT `attendance_record_therapy_session_fk` FOREIGN KEY (`therapy_session_id`) REFERENCES `therapy_session` (`id`);

--
-- Constraints for table `client`
--
ALTER TABLE `client`
  ADD CONSTRAINT `case_manager_id_fk` FOREIGN KEY (`case_manager_id`) REFERENCES `case_manager` (`id`),
  ADD CONSTRAINT `client_client_stage_fk` FOREIGN KEY (`client_stage_id`) REFERENCES `client_stage` (`id`),
  ADD CONSTRAINT `client_ibfk_1` FOREIGN KEY (`gender_id`) REFERENCES `gender` (`id`),
  ADD CONSTRAINT `client_ibfk_2` FOREIGN KEY (`program_id`) REFERENCES `program` (`id`),
  ADD CONSTRAINT `client_program_fk` FOREIGN KEY (`program_id`) REFERENCES `program` (`id`),
  ADD CONSTRAINT `ethnicity_id_fk` FOREIGN KEY (`ethnicity_id`) REFERENCES `ethnicity` (`id`),
  ADD CONSTRAINT `fk_client_referral_type` FOREIGN KEY (`referral_type_id`) REFERENCES `referral_type` (`id`),
  ADD CONSTRAINT `fk_client_therapy_group` FOREIGN KEY (`therapy_group_id`) REFERENCES `therapy_group` (`id`);

--
-- Constraints for table `ledger`
--
ALTER TABLE `ledger`
  ADD CONSTRAINT `ledger_client_fk` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`);

--
-- Constraints for table `therapy_group`
--
ALTER TABLE `therapy_group`
  ADD CONSTRAINT `therapy_group_program_fk` FOREIGN KEY (`program_id`) REFERENCES `program` (`id`);

--
-- Constraints for table `therapy_session`
--
ALTER TABLE `therapy_session`
  ADD CONSTRAINT `fk_therapy_session_curriculum` FOREIGN KEY (`curriculum_id`) REFERENCES `curriculum` (`id`),
  ADD CONSTRAINT `fk_therapy_session_facilitator` FOREIGN KEY (`facilitator_id`) REFERENCES `facilitator` (`id`),
  ADD CONSTRAINT `fk_therapy_session_therapy_group` FOREIGN KEY (`therapy_group_id`) REFERENCES `therapy_group` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
