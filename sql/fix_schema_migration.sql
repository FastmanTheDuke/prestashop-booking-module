-- Script de migration pour corriger le schéma des tables
-- À exécuter manuellement dans phpMyAdmin ou via PrestaShop SQL Manager

-- 1. Renommer les colonnes ID principales
ALTER TABLE `ps_booker` 
CHANGE COLUMN `id` `id_booker` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `ps_booker_auth` 
CHANGE COLUMN `id` `id_auth` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `ps_booker_auth_reserved` 
CHANGE COLUMN `id` `id_reserved` int(11) NOT NULL AUTO_INCREMENT;

-- 2. Ajouter les colonnes manquantes dans ps_booker
ALTER TABLE `ps_booker` 
ADD COLUMN IF NOT EXISTS `location` varchar(255) DEFAULT NULL AFTER `description`,
ADD COLUMN IF NOT EXISTS `capacity` int(11) DEFAULT 1 AFTER `price`,
ADD COLUMN IF NOT EXISTS `booking_duration` int(11) DEFAULT 60 AFTER `capacity`,
ADD COLUMN IF NOT EXISTS `min_booking_time` int(11) DEFAULT 24 AFTER `booking_duration`,
ADD COLUMN IF NOT EXISTS `max_booking_days` int(11) DEFAULT 30 AFTER `min_booking_time`,
ADD COLUMN IF NOT EXISTS `deposit_required` tinyint(1) DEFAULT 0 AFTER `max_booking_days`,
ADD COLUMN IF NOT EXISTS `deposit_amount` decimal(10,2) DEFAULT 0.00 AFTER `deposit_required`,
ADD COLUMN IF NOT EXISTS `google_account` varchar(255) DEFAULT NULL AFTER `auto_confirm`;

-- 3. Corriger ps_booker_auth_reserved
ALTER TABLE `ps_booker_auth_reserved` 
CHANGE COLUMN IF EXISTS `date_start` `date_reserved` date NOT NULL,
CHANGE COLUMN IF EXISTS `date_end` `date_to` date DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `hour_from` int(11) NOT NULL AFTER `date_to`,
ADD COLUMN IF NOT EXISTS `hour_to` int(11) NOT NULL AFTER `hour_from`,
ADD COLUMN IF NOT EXISTS `date_expiry` datetime DEFAULT NULL AFTER `admin_notes`,
MODIFY COLUMN `status` int(11) DEFAULT 0;

-- 4. Supprimer les colonnes obsolètes
ALTER TABLE `ps_booker` 
DROP COLUMN IF EXISTS `duration`,
DROP COLUMN IF EXISTS `max_bookings`,
DROP COLUMN IF EXISTS `require_deposit`,
DROP COLUMN IF EXISTS `cancellation_hours`,
DROP COLUMN IF EXISTS `image`,
DROP COLUMN IF EXISTS `sort_order`;

-- 5. Vérifier et corriger les index
-- Vérifier si les index existent avant de les créer
SET @dbname = DATABASE();
SET @tablename = 'ps_booker';
SET @indexname = 'idx_product';

SET @sqlstmt = IF(
    (SELECT COUNT(*) AS index_count
     FROM information_schema.statistics
     WHERE table_schema = @dbname 
     AND table_name = @tablename 
     AND index_name = @indexname) = 0,
    'CREATE INDEX idx_product ON ps_booker(id_product)',
    'SELECT ''Index already exists'''
);

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Afficher un message de confirmation
SELECT 'Migration completed successfully!' as Status;
