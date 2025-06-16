-- Migration de correction pour le module Booking v2.1.3
-- Corrige les problèmes de structure de base de données

-- ====================================
-- 1. VÉRIFICATION ET AJOUT DE LA COLONNE ACTIVE
-- ====================================

-- Ajouter la colonne active si elle n'existe pas
ALTER TABLE `PREFIX_booker_auth_reserved` 
ADD COLUMN IF NOT EXISTS `active` TINYINT(1) DEFAULT 1 AFTER `date_upd`;

-- Créer l'index pour la colonne active
ALTER TABLE `PREFIX_booker_auth_reserved` 
ADD INDEX IF NOT EXISTS `idx_active` (`active`);

-- ====================================
-- 2. CORRECTION DE LA STRUCTURE DES STATUTS
-- ====================================

-- Mettre à jour la colonne status pour supporter les nouveaux statuts
ALTER TABLE `PREFIX_booker_auth_reserved` 
MODIFY COLUMN `status` INT(11) DEFAULT 0 COMMENT '0=pending, 1=accepted, 2=paid, 3=cancelled, 4=expired, 5=completed, 6=refunded';

-- ====================================
-- 3. VÉRIFICATION DES COLONNES MANQUANTES
-- ====================================

-- Ajouter les colonnes de gestion des commandes si elles n'existent pas
ALTER TABLE `PREFIX_booker_auth_reserved` 
ADD COLUMN IF NOT EXISTS `id_customer` INT(11) DEFAULT NULL AFTER `id_booker`;

ALTER TABLE `PREFIX_booker_auth_reserved` 
ADD COLUMN IF NOT EXISTS `id_order` INT(11) DEFAULT NULL AFTER `id_customer`;

-- Ajouter les colonnes de dates si elles n'existent pas
ALTER TABLE `PREFIX_booker_auth_reserved` 
ADD COLUMN IF NOT EXISTS `date_start` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `date_reserved`;

ALTER TABLE `PREFIX_booker_auth_reserved` 
ADD COLUMN IF NOT EXISTS `date_end` DATETIME NULL AFTER `date_start`;

-- ====================================
-- 4. MIGRATION DES DONNÉES EXISTANTES
-- ====================================

-- Activer toutes les réservations existantes
UPDATE `PREFIX_booker_auth_reserved` SET `active` = 1 WHERE `active` IS NULL;

-- Convertir date_reserved + heures en datetime pour date_start
UPDATE `PREFIX_booker_auth_reserved` 
SET `date_start` = CONCAT(date_reserved, ' ', LPAD(hour_from, 2, '0'), ':00:00')
WHERE `date_start` = '0000-00-00 00:00:00' OR `date_start` IS NULL;

-- Calculer date_end basée sur date_start + durée (suppose 1h par défaut)
UPDATE `PREFIX_booker_auth_reserved` 
SET `date_end` = DATE_ADD(date_start, INTERVAL 1 HOUR)
WHERE `date_end` IS NULL;

-- ====================================
-- 5. AJOUT DES INDEX POUR PERFORMANCES
-- ====================================

-- Index pour les requêtes courantes
ALTER TABLE `PREFIX_booker_auth_reserved` 
ADD INDEX IF NOT EXISTS `idx_status_active` (`status`, `active`);

ALTER TABLE `PREFIX_booker_auth_reserved` 
ADD INDEX IF NOT EXISTS `idx_date_status` (`date_reserved`, `status`);

ALTER TABLE `PREFIX_booker_auth_reserved` 
ADD INDEX IF NOT EXISTS `idx_customer_order` (`id_customer`, `id_order`);

-- ====================================
-- 6. CRÉATION TABLE DE LIAISON PRODUITS SI MANQUANTE
-- ====================================

CREATE TABLE IF NOT EXISTS `PREFIX_booker_product` (
    `id_booker` INT(11) NOT NULL,
    `id_product` INT(11) NOT NULL,
    `sync_price` TINYINT(1) DEFAULT 1,
    `override_price` DECIMAL(10,2) DEFAULT NULL,
    `date_add` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_booker`, `id_product`),
    KEY `idx_product` (`id_product`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ====================================
-- 7. CRÉATION TABLE DE LIAISON RÉSERVATIONS-COMMANDES SI MANQUANTE
-- ====================================

CREATE TABLE IF NOT EXISTS `PREFIX_booker_reservation_order` (
    `id_reservation` INT(11) NOT NULL,
    `id_order` INT(11) NOT NULL,
    `order_type` ENUM('booking','deposit') DEFAULT 'booking',
    `amount` DECIMAL(10,2) NOT NULL,
    `date_add` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_reservation`, `id_order`, `order_type`),
    KEY `idx_order` (`id_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ====================================
-- 8. TABLE DES LOGS D'ACTIVITÉ SI MANQUANTE
-- ====================================

CREATE TABLE IF NOT EXISTS `PREFIX_booking_activity_log` (
    `id_log` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_reservation` INT(10) UNSIGNED NULL,
    `id_booker` INT(10) UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT NULL,
    `id_employee` INT(10) UNSIGNED NULL,
    `date_add` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_log`),
    INDEX `idx_reservation` (`id_reservation`),
    INDEX `idx_booker` (`id_booker`),
    INDEX `idx_action` (`action`),
    INDEX `idx_employee` (`id_employee`),
    INDEX `idx_date_add` (`date_add`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ====================================
-- 9. MISE À JOUR DE LA TABLE BOOKER
-- ====================================

-- S'assurer que la table booker a toutes les colonnes nécessaires
ALTER TABLE `PREFIX_booker` 
ADD COLUMN IF NOT EXISTS `id_product` INT(11) DEFAULT NULL AFTER `id_booker`;

ALTER TABLE `PREFIX_booker` 
ADD COLUMN IF NOT EXISTS `auto_confirm` TINYINT(1) DEFAULT 0 AFTER `active`;

ALTER TABLE `PREFIX_booker` 
ADD COLUMN IF NOT EXISTS `require_deposit` TINYINT(1) DEFAULT 0 AFTER `auto_confirm`;

ALTER TABLE `PREFIX_booker` 
ADD COLUMN IF NOT EXISTS `deposit_amount` DECIMAL(10,2) DEFAULT 0.00 AFTER `require_deposit`;

ALTER TABLE `PREFIX_booker` 
ADD COLUMN IF NOT EXISTS `cancellation_hours` INT(11) DEFAULT 24 AFTER `deposit_amount`;

-- Index pour les performances
ALTER TABLE `PREFIX_booker` 
ADD INDEX IF NOT EXISTS `idx_product` (`id_product`);

-- ====================================
-- 10. AJOUT COLONNE BOOKING_REFERENCE SI MANQUANTE
-- ====================================

ALTER TABLE `PREFIX_booker_auth_reserved` 
ADD COLUMN IF NOT EXISTS `booking_reference` VARCHAR(50) DEFAULT NULL AFTER `id_reserved`;

-- Générer des références pour les réservations existantes sans référence
UPDATE `PREFIX_booker_auth_reserved` 
SET `booking_reference` = CONCAT('BOOK-', YEAR(date_add), MONTH(date_add), '-', id_reserved)
WHERE `booking_reference` IS NULL OR `booking_reference` = '';

-- Index unique sur booking_reference
ALTER TABLE `PREFIX_booker_auth_reserved` 
ADD UNIQUE INDEX IF NOT EXISTS `idx_booking_reference` (`booking_reference`);

-- ====================================
-- 11. CORRECTION DES TYPES DE DONNÉES
-- ====================================

-- S'assurer que les colonnes de prix sont correctes
ALTER TABLE `PREFIX_booker_auth_reserved` 
MODIFY COLUMN `total_price` DECIMAL(10,2) DEFAULT 0.00;

ALTER TABLE `PREFIX_booker_auth_reserved` 
MODIFY COLUMN `deposit_paid` DECIMAL(10,2) DEFAULT 0.00;

-- S'assurer que les colonnes de texte sont correctes
ALTER TABLE `PREFIX_booker_auth_reserved` 
MODIFY COLUMN `customer_firstname` VARCHAR(100) NOT NULL;

ALTER TABLE `PREFIX_booker_auth_reserved` 
MODIFY COLUMN `customer_lastname` VARCHAR(100) NOT NULL;

ALTER TABLE `PREFIX_booker_auth_reserved` 
MODIFY COLUMN `customer_email` VARCHAR(150) NOT NULL;

-- ====================================
-- 12. NETTOYAGE ET OPTIMISATION
-- ====================================

-- Supprimer les réservations orphelines (sans booker associé)
DELETE r FROM `PREFIX_booker_auth_reserved` r 
LEFT JOIN `PREFIX_booker` b ON r.id_booker = b.id_booker 
WHERE b.id_booker IS NULL;

-- Optimiser les tables
OPTIMIZE TABLE `PREFIX_booker`;
OPTIMIZE TABLE `PREFIX_booker_auth`;
OPTIMIZE TABLE `PREFIX_booker_auth_reserved`;

-- ====================================
-- 13. LOGS DE MIGRATION
-- ====================================

-- Créer une entrée de log pour cette migration
INSERT INTO `PREFIX_booking_activity_log` 
(`action`, `details`, `date_add`) 
VALUES 
('database_migration', 'Migration v2.1.3 - Correction structure base de données', NOW());

-- ====================================
-- FIN DE LA MIGRATION
-- ====================================