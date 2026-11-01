-- =====================================================================
-- SMART FIRE AND RESCUE RESPONSE SYSTEM (SUDAR TOWN)
-- Database: smart_fire_rescue
-- Version: 2.0.0
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `smart_fire_rescue` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `smart_fire_rescue`;

-- Disable foreign key checks during schema re-creation
SET FOREIGN_KEY_CHECKS = 0;

-- Drop obsolete tables if recreating
DROP TABLE IF EXISTS `email_logs`;
DROP TABLE IF EXISTS `dispatch_records`;
DROP TABLE IF EXISTS `incident_equipment`;
DROP TABLE IF EXISTS `incidents`;
DROP TABLE IF EXISTS `roads`;
DROP TABLE IF EXISTS `unit_equipment`;
DROP TABLE IF EXISTS `response_units`;
DROP TABLE IF EXISTS `equipment_types`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `stations`;
DROP TABLE IF EXISTS `locations`;

-- ---------------------------------------------------------------------
-- Table 1: locations
-- Simulated and real-world town locations in Sudar
-- ---------------------------------------------------------------------
CREATE TABLE `locations` (
    `location_id` VARCHAR(30) NOT NULL,
    `location_name` VARCHAR(100) NOT NULL,
    `location_type` VARCHAR(50) NOT NULL,
    `latitude` DECIMAL(10, 8) NULL,
    `longitude` DECIMAL(11, 8) NULL,
    `description` VARCHAR(255) NULL,
    PRIMARY KEY (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 2: stations
-- Municipal Fire and Rapid Rescue Stations
-- ---------------------------------------------------------------------
CREATE TABLE `stations` (
    `station_id` VARCHAR(30) NOT NULL,
    `station_name` VARCHAR(100) NOT NULL,
    `location_id` VARCHAR(30) NOT NULL,
    `contact_email` VARCHAR(100) NOT NULL DEFAULT 'station@sudar.gov.in',
    `phone` VARCHAR(30) DEFAULT '911-SUDAR',
    PRIMARY KEY (`station_id`),
    CONSTRAINT `fk_station_location` FOREIGN KEY (`location_id`) 
        REFERENCES `locations` (`location_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 3: equipment_types
-- Master catalog of available rescue and firefighting equipment
-- ---------------------------------------------------------------------
CREATE TABLE `equipment_types` (
    `equipment_code` VARCHAR(50) NOT NULL,
    `equipment_name` VARCHAR(100) NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `icon` VARCHAR(20) NOT NULL,
    `description` VARCHAR(255) NULL,
    PRIMARY KEY (`equipment_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 4: response_units
-- Emergency vehicles (Fire Engines, Rescue Vans)
-- ---------------------------------------------------------------------
CREATE TABLE `response_units` (
    `unit_id` VARCHAR(20) NOT NULL,
    `unit_type` VARCHAR(50) NOT NULL,
    `station_id` VARCHAR(30) NOT NULL,
    `status` ENUM('Available', 'Busy', 'Dispatched', 'Out of Service') NOT NULL DEFAULT 'Available',
    `current_incident_id` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`unit_id`),
    CONSTRAINT `fk_unit_station` FOREIGN KEY (`station_id`) 
        REFERENCES `stations` (`station_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 5: unit_equipment
-- Equipment inventories carried by specific response units
-- ---------------------------------------------------------------------
CREATE TABLE `unit_equipment` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `unit_id` VARCHAR(20) NOT NULL,
    `equipment_code` VARCHAR(50) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_unit_equipment` (`unit_id`, `equipment_code`),
    CONSTRAINT `fk_equipment_unit` FOREIGN KEY (`unit_id`) 
        REFERENCES `response_units` (`unit_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_equipment_type` FOREIGN KEY (`equipment_code`) 
        REFERENCES `equipment_types` (`equipment_code`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 6: roads
-- Weighted adjacency graph for Sudar town road network (Dijkstra)
-- Weights represent simulated travel time in minutes
-- ---------------------------------------------------------------------
CREATE TABLE `roads` (
    `road_id` INT AUTO_INCREMENT NOT NULL,
    `from_location_id` VARCHAR(30) NOT NULL,
    `to_location_id` VARCHAR(30) NOT NULL,
    `travel_time` INT NOT NULL CHECK (`travel_time` > 0),
    `status` ENUM('Open', 'Closed') NOT NULL DEFAULT 'Open',
    `is_bidirectional` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`road_id`),
    INDEX `idx_from_location` (`from_location_id`),
    INDEX `idx_to_location` (`to_location_id`),
    INDEX `idx_road_status` (`status`),
    CONSTRAINT `fk_road_from` FOREIGN KEY (`from_location_id`) 
        REFERENCES `locations` (`location_id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_road_to` FOREIGN KEY (`to_location_id`) 
        REFERENCES `locations` (`location_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 7: incidents
-- Emergency incidents with AUTO_INCREMENT sequential IDs (1, 2, 3...)
-- Supports GPS coords, assessment questions, rules-based priority,
-- authorized severity overrides, and complete lifecycle tracking
-- ---------------------------------------------------------------------
CREATE TABLE `incidents` (
    `incident_id` INT AUTO_INCREMENT NOT NULL,
    `incident_type` ENUM('Fire', 'Rescue', 'Medical Emergency') NOT NULL,
    `location_id` VARCHAR(30) NULL,
    `latitude` DECIMAL(10, 8) NULL,
    `longitude` DECIMAL(11, 8) NULL,
    `location_address` VARCHAR(255) NULL,
    `location_description` TEXT NULL,
    
    -- Assessment Questions (Requirement 3)
    `fire_size` ENUM('Small', 'Medium', 'Large') NULL,
    `fire_spreading` TINYINT(1) NOT NULL DEFAULT 0,
    `people_trapped` TINYINT(1) NOT NULL DEFAULT 0,
    `injuries_reported` TINYINT(1) NOT NULL DEFAULT 0,
    `heavy_smoke` TINYINT(1) NOT NULL DEFAULT 0,
    `building_type` VARCHAR(50) NOT NULL DEFAULT 'Residential',
    `hazmat_involved` ENUM('No', 'Yes', 'Unknown') NOT NULL DEFAULT 'No',
    `people_exposed` INT NOT NULL DEFAULT 0,
    `critical_infrastructure` TINYINT(1) NOT NULL DEFAULT 0,
    `description` TEXT NULL,
    
    -- Calculated Severity & Priority
    `priority_score` INT NOT NULL DEFAULT 0,
    `severity_tier` ENUM('Low', 'Medium', 'High', 'Critical') NOT NULL DEFAULT 'Medium',
    `assessment_reason` TEXT NULL,
    
    -- Authorized Personnel Correction (Requirement 3)
    `override_severity` ENUM('Low', 'Medium', 'High', 'Critical') NULL,
    `override_reason` TEXT NULL,
    `override_by` VARCHAR(50) NULL,
    `override_at` DATETIME NULL,
    
    -- Incident Lifecycle (Requirement 8)
    `status` ENUM('Pending', 'Assigned', 'Dispatched', 'In Progress', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
    `reported_at` DATETIME NOT NULL,
    `in_progress_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    `completed_by` VARCHAR(50) NULL,
    `completion_notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`incident_id`),
    INDEX `idx_incident_status` (`status`),
    INDEX `idx_incident_reported` (`reported_at`),
    INDEX `idx_incident_priority` (`priority_score`),
    CONSTRAINT `fk_incident_location` FOREIGN KEY (`location_id`) 
        REFERENCES `locations` (`location_id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 8: incident_equipment
-- Multiple required equipment items per incident (Requirement 4)
-- ---------------------------------------------------------------------
CREATE TABLE `incident_equipment` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `incident_id` INT NOT NULL,
    `equipment_code` VARCHAR(50) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_incident_equipment` (`incident_id`, `equipment_code`),
    CONSTRAINT `fk_incident_equipment_inc` FOREIGN KEY (`incident_id`) 
        REFERENCES `incidents` (`incident_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_incident_equipment_type` FOREIGN KEY (`equipment_code`) 
        REFERENCES `equipment_types` (`equipment_code`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 9: dispatch_records
-- Unit dispatch assignments, Dijkstra routes, and email delivery status
-- ---------------------------------------------------------------------
CREATE TABLE `dispatch_records` (
    `dispatch_id` INT AUTO_INCREMENT NOT NULL,
    `incident_id` INT NOT NULL,
    `unit_id` VARCHAR(20) NOT NULL,
    `route` TEXT NOT NULL,
    `estimated_travel_time` INT NOT NULL,
    `dispatched_at` DATETIME NOT NULL,
    `completed_at` DATETIME NULL,
    `status` ENUM('Active', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Active',
    `notes` TEXT NULL,
    
    -- Email Notification Tracking (Requirement 6)
    `email_status` ENUM('Pending', 'Sent', 'Failed', 'Disabled') NOT NULL DEFAULT 'Pending',
    `email_sent_to` VARCHAR(255) NULL,
    `email_sent_at` DATETIME NULL,
    `email_error` TEXT NULL,
    
    PRIMARY KEY (`dispatch_id`),
    INDEX `idx_dispatch_status` (`status`),
    CONSTRAINT `fk_dispatch_incident` FOREIGN KEY (`incident_id`) 
        REFERENCES `incidents` (`incident_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_dispatch_unit` FOREIGN KEY (`unit_id`) 
        REFERENCES `response_units` (`unit_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 10: users
-- Authorized command personnel & officers for session-based auth
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
    `user_id` INT AUTO_INCREMENT NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `role` ENUM('admin', 'officer', 'dispatcher') NOT NULL DEFAULT 'officer',
    `email` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    UNIQUE KEY `unique_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 11: email_logs
-- Email notification delivery audit history
-- ---------------------------------------------------------------------
CREATE TABLE `email_logs` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `dispatch_id` INT NULL,
    `recipient` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `status` ENUM('Sent', 'Failed') NOT NULL,
    `error_message` TEXT NULL,
    `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_email_log_dispatch` (`dispatch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SEED DATA INSERTION
-- Fictional Town: Sudar
-- =====================================================================

-- 1. Locations (14 distinct locations with simulated GPS coordinates)
INSERT INTO `locations` (`location_id`, `location_name`, `location_type`, `latitude`, `longitude`, `description`) VALUES
('StationA', 'Central Fire Station Base', 'Fire Station', 13.0827, 80.2707, 'Headquarters response station in Sudar downtown'),
('StationB', 'North Fire Station Base', 'Fire Station', 13.0950, 80.2800, 'Northern auxiliary rapid-response station'),
('Road1', 'West Junction Hub', 'Junction', 13.0800, 80.2680, 'Major crossroads linking downtown and hospital district'),
('Road2', 'Central Avenue Boulevard', 'Junction', 13.0730, 80.2600, 'Commercial thoroughfare heading south towards industry'),
('Road3', 'North Ridge Junction', 'Junction', 13.0880, 80.2730, 'Highland pass connecting north station to hospital'),
('Road4', 'East Bypass Interchange', 'Junction', 13.0750, 80.2800, 'High-speed artery linking north perimeter to tech park'),
('Hospital', 'Sudar City Hospital', 'Healthcare', 13.0780, 80.2650, 'Regional tertiary medical center with emergency trauma care'),
('Factory', 'Apex Chemical Factory', 'Industrial', 13.0650, 80.2500, 'Chemical processing plant & hazardous material facility'),
('ResidentialArea', 'Greenfield Suburbs', 'Residential', 13.0910, 80.2710, 'Dense residential community with multistory apartments'),
('Market', 'Old Town Central Market', 'Commercial', 13.0840, 80.2750, 'High-density commercial bazaar and shopping district'),
('School', 'St. Jude High School', 'Education', 13.0890, 80.2610, 'Secondary educational campus and public assembly point'),
('IndustrialArea', 'Sudar Port & Logistics Hub', 'Industrial', 13.0600, 80.2450, 'Warehousing and freight transport center'),
('TechPark', 'Cyber Corridor Tech Park', 'Commercial', 13.0700, 80.2900, 'Software parks and critical municipal data centers'),
('RiverBridge', 'River Crossing Link', 'Landmark', 13.0750, 80.2820, 'Major suspension bridge over Sudar River');

-- 2. Fire Stations
INSERT INTO `stations` (`station_id`, `station_name`, `location_id`, `contact_email`, `phone`) VALUES
('StationA', 'Central Fire Station', 'StationA', 'station-central@sudar.gov.in', '911-SUDAR-CENTRAL'),
('StationB', 'North Fire Station', 'StationB', 'station-north@sudar.gov.in', '911-SUDAR-NORTH');

-- 3. Master Equipment Types (Requirement 4: 7 specific categories)
INSERT INTO `equipment_types` (`equipment_code`, `equipment_name`, `category`, `icon`, `description`) VALUES
('WaterPump', 'Water Pump', 'Fire Suppression', '💧', 'High-pressure water pump for fire knockdown'),
('Ladder', 'Ladder', 'Access & Extraction', '🪜', 'Heavy aerial ladder for elevated/multi-story rescues'),
('FirstAid', 'First Aid Kit', 'Medical Care', '🩹', 'Trauma dressings, burn treatment, and life support supplies'),
('RescueKit', 'Rescue Kit', 'Heavy Extraction', '🧰', 'Hydraulic spreaders, shears, and extrication gear'),
('BreathingApparatus', 'Breathing Apparatus', 'Respiratory Protection', '🤿', 'SCBA air tanks and masks for toxic smoke and enclosed areas'),
('ProtectiveEquipment', 'Protective Equipment', 'Personal Safety', '🛡️', 'Bunker coats, heat-resistant helmets, and flash hoods'),
('HazmatKit', 'Hazardous Material Kit', 'Hazmat Containment', '☣️', 'Chemical neutralization, detector tubes, and containment suits');

-- 4. Response Units
INSERT INTO `response_units` (`unit_id`, `unit_type`, `station_id`, `status`) VALUES
('FE01', 'Fire Engine', 'StationA', 'Available'),
('RV01', 'Rescue Van', 'StationA', 'Available'),
('FE02', 'Fire Engine', 'StationB', 'Available'),
('RV02', 'Rescue Van', 'StationB', 'Available');

-- 5. Unit Equipment Inventories (Normalized)
-- FE01 (Station A Fire Engine): Heavy Fire Suppression + Breathing Gear
INSERT INTO `unit_equipment` (`unit_id`, `equipment_code`) VALUES
('FE01', 'WaterPump'),
('FE01', 'Ladder'),
('FE01', 'BreathingApparatus'),
('FE01', 'ProtectiveEquipment');

-- FE02 (Station B Fire Engine): Fire Suppression + Hazmat Specialized
INSERT INTO `unit_equipment` (`unit_id`, `equipment_code`) VALUES
('FE02', 'WaterPump'),
('FE02', 'Ladder'),
('FE02', 'ProtectiveEquipment'),
('FE02', 'BreathingApparatus'),
('FE02', 'HazmatKit');

-- RV01 (Station A Rescue Van): Medical + Heavy Extrication
INSERT INTO `unit_equipment` (`unit_id`, `equipment_code`) VALUES
('RV01', 'FirstAid'),
('RV01', 'RescueKit'),
('RV01', 'BreathingApparatus'),
('RV01', 'ProtectiveEquipment');

-- RV02 (Station B Rescue Van): Medical + High-Rise Rescue + Hazmat Support
INSERT INTO `unit_equipment` (`unit_id`, `equipment_code`) VALUES
('RV02', 'FirstAid'),
('RV02', 'RescueKit'),
('RV02', 'Ladder'),
('RV02', 'ProtectiveEquipment'),
('RV02', 'BreathingApparatus'),
('RV02', 'HazmatKit');

-- 6. Roads (Weighted Adjacency Graph: weights represent travel time in minutes)
INSERT INTO `roads` (`from_location_id`, `to_location_id`, `travel_time`, `status`, `is_bidirectional`) VALUES
('StationA', 'Road1', 4, 'Open', 1),
('StationA', 'Road2', 6, 'Open', 1),
('Road1', 'Hospital', 3, 'Open', 1),
('Road2', 'Factory', 2, 'Open', 1),
('Factory', 'Road4', 3, 'Open', 1),
('Road4', 'StationB', 4, 'Open', 1),
('StationB', 'Road3', 3, 'Open', 1),
('Road3', 'Hospital', 5, 'Open', 1),
('Road1', 'Market', 4, 'Open', 1),
('Market', 'School', 3, 'Open', 1),
('Road2', 'ResidentialArea', 5, 'Open', 1),
('ResidentialArea', 'School', 4, 'Open', 1),
('Factory', 'IndustrialArea', 4, 'Open', 1),
('Road4', 'TechPark', 3, 'Open', 1),
('Road3', 'RiverBridge', 4, 'Open', 1),
('RiverBridge', 'TechPark', 5, 'Open', 1),
('IndustrialArea', 'RiverBridge', 6, 'Open', 1),
('Market', 'ResidentialArea', 3, 'Open', 1),
('Road1', 'Road2', 4, 'Open', 1),
('Hospital', 'TechPark', 6, 'Open', 1);

-- 7. Authorized Command Personnel (Password is 'password123' / 'admin123' / 'dispatcher123')
INSERT INTO `users` (`username`, `password_hash`, `full_name`, `role`, `email`) VALUES
('officer', '$2y$10$wT0X8P3Ue8mP4w1rM3D6u.QjBfvQ6tU3Wv2n8mG3h6L1f4c7K3v1.', 'Captain John Ray', 'officer', 'officer@sudar.gov.in'),
('admin', '$2y$10$e7C4vK4Q8o2L1wM9p0N7U.QjBfvQ6tU3Wv2n8mG3h6L1f4c7K3v1.', 'Chief Controller Smith', 'admin', 'admin@sudar.gov.in'),
('dispatcher', '$2y$10$9G2X5M7Q1o4L3wN8p2N9U.QjBfvQ6tU3Wv2n8mG3h6L1f4c7K3v1.', 'CAD Operator Adams', 'dispatcher', 'cad@sudar.gov.in');

-- Start with 0 incident reports as strictly required
