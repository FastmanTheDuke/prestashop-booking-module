-- Script de mise à jour base de données pour module Booking v2.1
-- Ajout des nouveaux champs et modification de la structure

-- Mise à jour de la table booker_auth_reserved avec les nouveaux champs
ALTER TABLE `PREFIX_booker_auth_reserved` 
ADD COLUMN IF NOT EXISTS `payment_status` ENUM('pending','authorized','captured','cancelled','refunded') DEFAULT 'pending' AFTER `status`;

ALTER TABLE `PREFIX_booker_auth_reserved` 
ADD COLUMN IF NOT EXISTS `stripe_payment_intent_id` VARCHAR(255) DEFAULT NULL AFTER `deposit_paid`;

ALTER TABLE `PREFIX_booker_auth_reserved` 
ADD COLUMN IF NOT EXISTS `stripe_deposit_intent_id` VARCHAR(255) DEFAULT NULL AFTER `stripe_payment_intent_id`;

-- Mise à jour du champ status pour utiliser les valeurs textuelles
ALTER TABLE `PREFIX_booker_auth_reserved` 
MODIFY COLUMN `status` ENUM('pending','confirmed','paid','cancelled','completed','refunded') DEFAULT 'pending';

-- Ajouter un index sur booking_reference pour améliorer les performances
ALTER TABLE `PREFIX_booker_auth_reserved` 
ADD INDEX IF NOT EXISTS `idx_booking_reference` (`booking_reference`);

-- Ajouter un index sur payment_status
ALTER TABLE `PREFIX_booker_auth_reserved` 
ADD INDEX IF NOT EXISTS `idx_payment_status` (`payment_status`);

-- Mise à jour de la table booker avec nouveaux champs
ALTER TABLE `PREFIX_booker` 
ADD COLUMN IF NOT EXISTS `auto_confirm` TINYINT(1) DEFAULT 0 AFTER `active`;

ALTER TABLE `PREFIX_booker` 
ADD COLUMN IF NOT EXISTS `require_deposit` TINYINT(1) DEFAULT 0 AFTER `auto_confirm`;

ALTER TABLE `PREFIX_booker` 
ADD COLUMN IF NOT EXISTS `deposit_amount` DECIMAL(10,2) DEFAULT 0.00 AFTER `require_deposit`;

ALTER TABLE `PREFIX_booker` 
ADD COLUMN IF NOT EXISTS `cancellation_hours` INT(11) DEFAULT 24 AFTER `deposit_amount`;

ALTER TABLE `PREFIX_booker` 
ADD COLUMN IF NOT EXISTS `image` VARCHAR(255) DEFAULT NULL AFTER `cancellation_hours`;

ALTER TABLE `PREFIX_booker` 
ADD COLUMN IF NOT EXISTS `sort_order` INT(11) DEFAULT 0 AFTER `image`;

-- Ajouter un index sur sort_order pour améliorer les performances de tri
ALTER TABLE `PREFIX_booker` 
ADD INDEX IF NOT EXISTS `idx_sort_order` (`sort_order`);

-- Mise à jour de la table booker_auth avec champs de récurrence
ALTER TABLE `PREFIX_booker_auth` 
ADD COLUMN IF NOT EXISTS `recurring` TINYINT(1) DEFAULT 0 AFTER `active`;

ALTER TABLE `PREFIX_booker_auth` 
ADD COLUMN IF NOT EXISTS `recurring_type` ENUM('daily','weekly','monthly') DEFAULT NULL AFTER `recurring`;

ALTER TABLE `PREFIX_booker_auth` 
ADD COLUMN IF NOT EXISTS `recurring_end` DATE DEFAULT NULL AFTER `recurring_type`;

-- Ajouter des index pour améliorer les performances des requêtes récurrentes
ALTER TABLE `PREFIX_booker_auth` 
ADD INDEX IF NOT EXISTS `idx_recurring` (`recurring`);

ALTER TABLE `PREFIX_booker_auth` 
ADD INDEX IF NOT EXISTS `idx_recurring_type` (`recurring_type`);

-- Création de la table de liaisons avec les commandes si elle n'existe pas
CREATE TABLE IF NOT EXISTS `PREFIX_booker_reservation_order` (
    `id_reservation` INT(11) NOT NULL,
    `id_order` INT(11) NOT NULL,
    `order_type` ENUM('booking','deposit') DEFAULT 'booking',
    `amount` DECIMAL(10,2) NOT NULL,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_reservation`, `id_order`, `order_type`),
    KEY `idx_order` (`id_order`),
    CONSTRAINT `fk_reservation_order_reservation` FOREIGN KEY (`id_reservation`) 
        REFERENCES `PREFIX_booker_auth_reserved` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reservation_order_order` FOREIGN KEY (`id_order`) 
        REFERENCES `PREFIX_orders` (`id_order`) ON DELETE CASCADE
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

-- Migration des anciennes données si nécessaire
-- Convertir les anciens statuts numériques vers les statuts textuels
UPDATE `PREFIX_booker_auth_reserved` 
SET `status` = CASE 
    WHEN `status` = '0' THEN 'pending'
    WHEN `status` = '1' THEN 'confirmed' 
    WHEN `status` = '2' THEN 'paid'
    WHEN `status` = '3' THEN 'cancelled'
    WHEN `status` = '4' THEN 'cancelled'  -- expired -> cancelled
    WHEN `status` = '5' THEN 'refunded'
    WHEN `status` = '6' THEN 'confirmed'  -- in_progress -> confirmed
    WHEN `status` = '7' THEN 'completed'
    ELSE 'pending'
END
WHERE `status` IN ('0','1','2','3','4','5','6','7');

-- Nettoyer les données incohérentes
UPDATE `PREFIX_booker_auth_reserved` 
SET `payment_status` = 'captured' 
WHERE `status` = 'paid' AND `payment_status` = 'pending';

UPDATE `PREFIX_booker_auth_reserved` 
SET `payment_status` = 'cancelled' 
WHERE `status` = 'cancelled' AND `payment_status` IN ('pending', 'authorized');

UPDATE `PREFIX_booker_auth_reserved` 
SET `payment_status` = 'refunded' 
WHERE `status` = 'refunded';

-- Supprimer les réservations en double (au cas où)
DELETE r1 FROM `PREFIX_booker_auth_reserved` r1
INNER JOIN `PREFIX_booker_auth_reserved` r2 
WHERE r1.id > r2.id 
AND r1.booking_reference = r2.booking_reference;

-- Optimisation des performances - analyse des tables
ANALYZE TABLE `PREFIX_booker`;
ANALYZE TABLE `PREFIX_booker_auth`;
ANALYZE TABLE `PREFIX_booker_auth_reserved`;
ANALYZE TABLE `PREFIX_booker_product`;
ANALYZE TABLE `PREFIX_booker_reservation_order`;

-- Insertion des configurations par défaut si elles n'existent pas
INSERT IGNORE INTO `PREFIX_configuration` (`name`, `value`) VALUES
('BOOKING_DEFAULT_PRICE', '50.00'),
('BOOKING_DEPOSIT_AMOUNT', '20.00'),
('BOOKING_MIN_BOOKING_TIME', '2'),
('BOOKING_MAX_BOOKING_DAYS', '90'),
('BOOKING_DEFAULT_DURATION', '60'),
('BOOKING_EXPIRY_HOURS', '24'),
('BOOKING_AUTO_CONFIRM', '0'),
('BOOKING_MULTI_SELECT', '1'),
('BOOKING_EMERGENCY_PHONE', ''),
('BOOKING_PAYMENT_ENABLED', '1'),
('BOOKING_STRIPE_ENABLED', '0'),
('BOOKING_SAVE_CARDS', '1'),
('BOOKING_STRIPE_HOLD_DEPOSIT', '1'),
('BOOKING_PAYMENT_EXPIRY_MINUTES', '30'),
('BOOKING_AUTO_CREATE_ORDER', '1'),
('BOOKING_NOTIFICATIONS_ENABLED', '1'),
('BOOKING_AUTO_CONFIRMATION_EMAIL', '1'),
('BOOKING_AUTO_REMINDERS', '1'),
('BOOKING_REMINDER_HOURS', '24'),
('BOOKING_ADMIN_NOTIFICATIONS', '1'),
('BOOKING_CRON_CLEAN_RESERVATIONS', '1'),
('BOOKING_SYNC_PRODUCT_PRICE', '1'),
('BOOKING_BUSINESS_HOURS_START', '08:00'),
('BOOKING_BUSINESS_HOURS_END', '18:00'),
('BOOKING_ALLOWED_DAYS', '1,2,3,4,5,6,7'),
('BOOKING_DEBUG_MODE', '0'),
('BOOKING_CMS_ID', '0');

-- Mise à jour des valeurs de configuration existantes si nécessaire
UPDATE `PREFIX_configuration` 
SET `value` = '1' 
WHERE `name` = 'BOOKING_PAYMENT_ENABLED' AND `value` = '';

UPDATE `PREFIX_configuration` 
SET `value` = '60' 
WHERE `name` = 'BOOKING_DEFAULT_DURATION' AND (`value` = '' OR `value` = '0');

-- Création d'une vue pour faciliter les requêtes de reporting
CREATE OR REPLACE VIEW `PREFIX_booking_stats_view` AS
SELECT 
    DATE(r.date_add) as reservation_date,
    r.status,
    r.payment_status,
    COUNT(*) as count,
    SUM(r.total_price) as total_revenue,
    AVG(r.total_price) as average_price,
    b.name as booker_name,
    b.id as booker_id
FROM `PREFIX_booker_auth_reserved` r
LEFT JOIN `PREFIX_booker` b ON b.id = r.id_booker
GROUP BY DATE(r.date_add), r.status, r.payment_status, b.id;

-- Création d'une vue pour les disponibilités
CREATE OR REPLACE VIEW `PREFIX_booking_availability_view` AS
SELECT 
    ba.*,
    b.name as booker_name,
    b.price as booker_price,
    (ba.max_bookings - ba.current_bookings) as remaining_slots,
    CASE 
        WHEN ba.current_bookings = 0 THEN 'available'
        WHEN ba.current_bookings < ba.max_bookings THEN 'partial'
        ELSE 'full'
    END as availability_status
FROM `PREFIX_booker_auth` ba
LEFT JOIN `PREFIX_booker` b ON b.id = ba.id_booker
WHERE ba.active = 1;

-- Log de la mise à jour
INSERT INTO `PREFIX_log` (`severity`, `error_code`, `message`, `object_type`, `date_add`) 
VALUES (1, 0, 'Module Booking mis à jour vers la version 2.1', 'Booking', NOW());
