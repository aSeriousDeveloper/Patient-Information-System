-- phpMyAdmin SQL Dump
-- version 4.8.4
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 29, 2019 at 05:07 PM
-- Server version: 5.7.24
-- PHP Version: 7.2.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `medapp`
--

-- --------------------------------------------------------

--
-- Table structure for table `allergy_agent`
--

DROP TABLE IF EXISTS `allergy_agent`;
CREATE TABLE IF NOT EXISTS `allergy_agent` (
  `ALLERGY_AGENT_ID` int(11) NOT NULL,
  `ALLERGY_AGENT_DESCRIPTION` varchar(64) NOT NULL,
  PRIMARY KEY (`ALLERGY_AGENT_ID`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `allergy_reaction`
--

DROP TABLE IF EXISTS `allergy_reaction`;
CREATE TABLE IF NOT EXISTS `allergy_reaction` (
  `allergy_reaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `allergy_reaction_description` varchar(64) NOT NULL,
  PRIMARY KEY (`allergy_reaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `authorisation`
--

DROP TABLE IF EXISTS `authorisation`;
CREATE TABLE IF NOT EXISTS `authorisation` (
  `AUTHORISATION_ID` char(32) NOT NULL,
  `AUTHORISATION_USER_ID` int(11) NOT NULL,
  `AUTHORISATION_TIMEOUT` datetime NOT NULL,
  PRIMARY KEY (`AUTHORISATION_ID`),
  UNIQUE KEY `USER_AUTH_INDEX` (`AUTHORISATION_USER_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `medication`
--

DROP TABLE IF EXISTS `medication`;
CREATE TABLE IF NOT EXISTS `medication` (
  `MEDICATION_ID` int(11) NOT NULL AUTO_INCREMENT,
  `MEDICATION_NAME` varchar(64) NOT NULL,
  PRIMARY KEY (`MEDICATION_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `patient`
--

DROP TABLE IF EXISTS `patient`;
CREATE TABLE IF NOT EXISTS `patient` (
  `PATIENT_ID` varchar(12) NOT NULL COMMENT 'NHS ID - store as XXX-XXX-XXXX',
  `PATIENT_USER_ID` int(11) NOT NULL COMMENT 'User Foreign Key',
  `PATIENT_DOCTOR_ID` int(11) NOT NULL COMMENT 'Primary Doctor Assigned to Patient',
  PRIMARY KEY (`PATIENT_ID`),
  UNIQUE KEY `PATIENT_USER_ID_INDEX` (`PATIENT_USER_ID`),
  KEY `PATIENT_ASSIGNED_DOCTOR_INDEX` (`PATIENT_DOCTOR_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `patient_allergy`
--

DROP TABLE IF EXISTS `patient_allergy`;
CREATE TABLE IF NOT EXISTS `patient_allergy` (
  `patient_allergy_id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_allergy_patient_id` varchar(12) NOT NULL COMMENT 'Links to patient table patient_id',
  `patient_allergy_agent_id` int(11) NOT NULL COMMENT 'Links to allergy table allergy_id',
  `patient_allergy_reaction_id` int(11) NOT NULL COMMENT 'Links to Reaction Table',
  `patient_allergy_severity` enum('Mild','Moderate','Severe') NOT NULL COMMENT 'Mild/Moderate/Severe',
  `patient_allergy_source` enum('Reported By Practice','Reported by Patient','Allergy History','Transition of Care','Referral') NOT NULL COMMENT 'Reported by Practice/Reported by Patient/Allergy History/Transition of Care/Referral',
  `patient_allergy_status` enum('Active','Inactive','Resolved') NOT NULL COMMENT 'Active/Inactive/Resolved',
  PRIMARY KEY (`patient_allergy_id`),
  KEY `PATIENT_ALLERGY_PATIENT_INDEX` (`patient_allergy_patient_id`),
  KEY `PATIENT_ALLERGY_AGENT_INDEX` (`patient_allergy_agent_id`),
  KEY `PATIENT_ALLERGY_REACTION_INDEX` (`patient_allergy_reaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `patient_medication`
--

DROP TABLE IF EXISTS `patient_medication`;
CREATE TABLE IF NOT EXISTS `patient_medication` (
  `patient_medication_id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_medication_patient_id` varchar(12) NOT NULL COMMENT 'Links to patient table patient_id',
  `patient_medication_medication_id` int(11) NOT NULL COMMENT 'Links to medication table medication_id',
  `patient_medication_dosage` varchar(16) NOT NULL,
  `patient_medication_date_start` date NOT NULL,
  `patient_medication_date_end` date NOT NULL,
  `patient_medication_usage` varchar(128) NOT NULL COMMENT 'Instructions on usage of medication (what time, quantity, with which meals/fluids, etc)',
  PRIMARY KEY (`patient_medication_id`),
  KEY `PATIENT_MEDICATION_PATIENT_INDEX` (`patient_medication_patient_id`) USING BTREE,
  KEY `PATIENT_MEDICATION_NAME_INDEX` (`patient_medication_medication_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
CREATE TABLE IF NOT EXISTS `user` (
  `USER_ID` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Index ID for all Users',
  `USER_NAME_FIRST` varchar(64) NOT NULL COMMENT 'Firstname',
  `USER_NAME_LAST` varchar(64) NOT NULL COMMENT 'Lastname',
  `USER_EMAIL` varchar(64) NOT NULL COMMENT 'Email Address',
  `USER_ADDRESS_ONE` varchar(64) NOT NULL,
  `USER_ADDRESS_TWO` varchar(64) NOT NULL,
  `USER_ADDRESS_TOWN` varchar(64) NOT NULL,
  `USER_ADDRESS_COUNTY` varchar(32) NOT NULL,
  `USER_ADDRESS_POSTCODE` varchar(8) NOT NULL,
  `USER_DOB` date NOT NULL DEFAULT '1970-01-01' COMMENT 'Date of Birth AS YYYY-MM-DD',
  `USER_PASSWORD` varchar(60) NOT NULL COMMENT 'Password (Hashed in PHP)',
  `USER_TYPE` int(11) NOT NULL COMMENT 'User Type (1 - Patient, 2 - Doctor, 3 - Admin)',
  `USER_IMG` varchar(64) NOT NULL COMMENT 'Store FILE LOCATION of User IMG',
  PRIMARY KEY (`USER_ID`),
  UNIQUE KEY `INDEX_USER_EMAIL` (`USER_EMAIL`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `authorisation`
--
ALTER TABLE `authorisation`
  ADD CONSTRAINT `authorisation_ibfk_1` FOREIGN KEY (`AUTHORISATION_USER_ID`) REFERENCES `user` (`USER_ID`);

--
-- Constraints for table `patient`
--
ALTER TABLE `patient`
  ADD CONSTRAINT `patient_ibfk_1` FOREIGN KEY (`PATIENT_USER_ID`) REFERENCES `user` (`USER_ID`),
  ADD CONSTRAINT `patient_ibfk_2` FOREIGN KEY (`PATIENT_DOCTOR_ID`) REFERENCES `user` (`USER_ID`);

--
-- Constraints for table `patient_allergy`
--
ALTER TABLE `patient_allergy`
  ADD CONSTRAINT `patient_allergy_ibfk_1` FOREIGN KEY (`patient_allergy_patient_id`) REFERENCES `patient` (`PATIENT_ID`),
  ADD CONSTRAINT `patient_allergy_ibfk_2` FOREIGN KEY (`patient_allergy_agent_id`) REFERENCES `allergy_agent` (`ALLERGY_AGENT_ID`),
  ADD CONSTRAINT `patient_allergy_ibfk_3` FOREIGN KEY (`patient_allergy_reaction_id`) REFERENCES `allergy_reaction` (`allergy_reaction_id`);

--
-- Constraints for table `patient_medication`
--
ALTER TABLE `patient_medication`
  ADD CONSTRAINT `patient_medication_ibfk_1` FOREIGN KEY (`patient_medication_patient_id`) REFERENCES `patient` (`PATIENT_ID`),
  ADD CONSTRAINT `patient_medication_ibfk_2` FOREIGN KEY (`patient_medication_medication_id`) REFERENCES `medication` (`MEDICATION_ID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
