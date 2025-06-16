-- Script de mise à jour des tables du module Booking
-- À exécuter dans phpMyAdmin ou via ligne de commande MySQL

-- 1. SUPPRIMER LES ANCIENNES TABLES SI ELLES EXISTENT
DROP TABLE IF EXISTS `ps_booking_activity_log`;
DROP TABLE IF EXISTS `ps_booker_reservation_order`;
DROP TABLE IF EXISTS `ps_booker_product`;
DROP TABLE IF EXISTS `ps_booker_auth_reserved`;
DROP TABLE IF EXISTS `ps_booker_auth`;
DROP TABLE IF EXISTS `ps_booker_lang`;  -- Supprimer l'ancienne table multilingue si elle existe
DROP TABLE IF EXISTS `ps_booker`;

-- 2. CRÉER LES NOUVELLES TABLES AVEC LE BON SCHÉMA

-- Table des éléments réservables (bookers)
CREATE TABLE IF NOT EXISTS `ps_booker` (
    `id_booker` int(11) NOT NULL AUTO_INCREMENT,
    `id_product` int(11) DEFAULT NULL,
    `name` varchar(255) NOT NULL,
    `description` text,
    `location` varchar(255) DEFAULT NULL,
    `price` decimal(10,2) DEFAULT 0.00,
    `capacity` int(11) DEFAULT 1,
    `booking_duration` int(11) DEFAULT 60,
    `min_booking_time` int(11) DEFAULT 24,
    `max_booking_days` int(11) DEFAULT 30,
    `deposit_required` tinyint(1) DEFAULT 0,
    `deposit_amount` decimal(10,2) DEFAULT 0.00,
    `auto_confirm` tinyint(1) DEFAULT 0,
    `google_account` varchar(255) DEFAULT NULL,
    `active` tinyint(1) DEFAULT 1,
    `date_add` datetime NOT NULL,
    `date_upd` datetime NOT NULL,
    PRIMARY KEY (`id_booker`),
    KEY `idx_product` (`id_product`),
    KEY `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Table des disponibilités
CREATE TABLE IF NOT EXISTS `ps_booker_auth` (
    `id_auth` int(11) NOT NULL AUTO_INCREMENT,
    `id_booker` int(11) NOT NULL,
    `date_from` datetime NOT NULL,
    `date_to` datetime NOT NULL,
    `time_from` time NOT NULL,
    `time_to` time NOT NULL,
    `max_bookings` int(11) DEFAULT 1,
    `current_bookings` int(11) DEFAULT 0,
    `price_override` decimal(10,2) DEFAULT NULL,
    `active` tinyint(1) DEFAULT 1,
    `recurring` tinyint(1) DEFAULT 0,
    `recurring_type` enum('daily','weekly','monthly') DEFAULT NULL,
    `recurring_end` date DEFAULT NULL,
    `notes` text,
    `date_add` datetime NOT NULL,
    `date_upd` datetime NOT NULL,
    PRIMARY KEY (`id_auth`),
    KEY `idx_booker` (`id_booker`),
    KEY `idx_date_range` (`date_from`, `date_to`),
    KEY `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Table des réservations
CREATE TABLE IF NOT EXISTS `ps_booker_auth_reserved` (
    `id_reserved` int(11) NOT NULL AUTO_INCREMENT,
    `id_auth` int(11) NOT NULL,
    `id_booker` int(11) NOT NULL,
    `id_customer` int(11) DEFAULT NULL,
    `id_order` int(11) DEFAULT NULL,
    `booking_reference` varchar(50) NOT NULL,
    `customer_firstname` varchar(100) NOT NULL,
    `customer_lastname` varchar(100) NOT NULL,
    `customer_email` varchar(150) NOT NULL,
    `customer_phone` varchar(50) DEFAULT NULL,
    `date_reserved` date NOT NULL,
    `date_to` date DEFAULT NULL,
    `hour_from` int(11) NOT NULL,
    `hour_to` int(11) NOT NULL,
    `total_price` decimal(10,2) DEFAULT 0.00,
    `deposit_paid` decimal(10,2) DEFAULT 0.00,
    `status` int(11) DEFAULT 0,
    `payment_status` enum('pending','authorized','captured','cancelled','refunded') DEFAULT 'pending',
    `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
    `stripe_deposit_intent_id` varchar(255) DEFAULT NULL,
    `notes` text,
    `admin_notes` text,
    `date_expiry` datetime DEFAULT NULL,
    `date_add` datetime NOT NULL,
    `date_upd` datetime NOT NULL,
    PRIMARY KEY (`id_reserved`),
    UNIQUE KEY `idx_reference` (`booking_reference`),
    KEY `idx_auth` (`id_auth`),
    KEY `idx_booker` (`id_booker`),
    KEY `idx_customer` (`id_customer`),
    KEY `idx_order` (`id_order`),
    KEY `idx_status` (`status`),
    KEY `idx_date_range` (`date_reserved`, `date_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Table de liaison produits
CREATE TABLE IF NOT EXISTS `ps_booker_product` (
    `id_booker` int(11) NOT NULL,
    `id_product` int(11) NOT NULL,
    `sync_price` tinyint(1) DEFAULT 1,
    `override_price` decimal(10,2) DEFAULT NULL,
    `date_add` datetime NOT NULL,
    PRIMARY KEY (`id_booker`, `id_product`),
    KEY `idx_product` (`id_product`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Table de liaison réservations-commandes
CREATE TABLE IF NOT EXISTS `ps_booker_reservation_order` (
    `id_reservation` int(11) NOT NULL,
    `id_order` int(11) NOT NULL,
    `order_type` enum('booking','deposit') DEFAULT 'booking',
    `amount` decimal(10,2) NOT NULL,
    `date_add` datetime NOT NULL,
    PRIMARY KEY (`id_reservation`, `id_order`, `order_type`),
    KEY `idx_order` (`id_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Table de logs d'activité
CREATE TABLE IF NOT EXISTS `ps_booking_activity_log` (
    `id_log` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `id_reservation` int(10) unsigned NULL,
    `id_booker` int(10) unsigned NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT NULL,
    `id_employee` int(10) unsigned NULL,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_log`),
    INDEX `idx_id_reservation` (`id_reservation`),
    INDEX `idx_id_booker` (`id_booker`),
    INDEX `idx_action` (`action`),
    INDEX `idx_id_employee` (`id_employee`),
    INDEX `idx_date_add` (`date_add`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- 3. INSÉRER DES DONNÉES DE TEST (OPTIONNEL)
-- Décommentez les lignes suivantes pour créer un booker de test

/*
INSERT INTO `ps_booker` (`name`, `description`, `location`, `price`, `capacity`, `booking_duration`, `min_booking_time`, `max_booking_days`, `deposit_required`, `deposit_amount`, `auto_confirm`, `active`, `date_add`, `date_upd`) 
VALUES ('Bateau Test', 'Un bateau de test pour vérifier le module', 'Port de Marseille', 100.00, 4, 60, 24, 30, 1, 50.00, 0, 1, NOW(), NOW());
*/
