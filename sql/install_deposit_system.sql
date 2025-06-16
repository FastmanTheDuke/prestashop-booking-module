-- Script SQL pour les nouvelles fonctionnalités de caution Stripe
-- Version 2.1.4 - Gestion avancée des cautions avec empreinte CB

-- Table pour stocker les informations des clients Stripe
CREATE TABLE IF NOT EXISTS `PREFIX_booking_customers` (
    `id_booking_customer` int(11) NOT NULL AUTO_INCREMENT,
    `id_customer` int(11) NOT NULL DEFAULT 0,
    `stripe_customer_id` varchar(255) NOT NULL,
    `date_add` datetime NOT NULL,
    `date_upd` datetime NOT NULL,
    PRIMARY KEY (`id_booking_customer`),
    UNIQUE KEY `idx_customer` (`id_customer`),
    UNIQUE KEY `idx_stripe_customer` (`stripe_customer_id`),
    KEY `idx_date_add` (`date_add`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Table pour la gestion des cautions
CREATE TABLE IF NOT EXISTS `PREFIX_booking_deposits` (
    `id_deposit` int(11) NOT NULL AUTO_INCREMENT,
    `id_reservation` int(11) NOT NULL,
    `setup_intent_id` varchar(255) DEFAULT NULL,
    `payment_method_id` varchar(255) DEFAULT NULL,
    `payment_intent_id` varchar(255) DEFAULT NULL,
    `stripe_transaction_id` varchar(255) DEFAULT NULL,
    `deposit_amount` int(11) NOT NULL DEFAULT 0 COMMENT 'Montant en centimes',
    `captured_amount` int(11) NOT NULL DEFAULT 0 COMMENT 'Montant capturé en centimes',
    `refunded_amount` int(11) NOT NULL DEFAULT 0 COMMENT 'Montant remboursé en centimes',
    `status` varchar(20) NOT NULL DEFAULT 'pending',
    `failure_reason` text DEFAULT NULL,
    `metadata` text DEFAULT NULL COMMENT 'JSON avec métadonnées Stripe',
    `date_authorized` datetime DEFAULT NULL,
    `date_captured` datetime DEFAULT NULL,
    `date_released` datetime DEFAULT NULL,
    `date_refunded` datetime DEFAULT NULL,
    `date_add` datetime NOT NULL,
    `date_upd` datetime NOT NULL,
    PRIMARY KEY (`id_deposit`),
    UNIQUE KEY `idx_reservation` (`id_reservation`),
    KEY `idx_status` (`status`),
    KEY `idx_setup_intent` (`setup_intent_id`),
    KEY `idx_payment_intent` (`payment_intent_id`),
    KEY `idx_date_add` (`date_add`),
    FOREIGN KEY (`id_reservation`) REFERENCES `PREFIX_booker_auth_reserved` (`id_reserved`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Table pour l'historique des actions sur les cautions
CREATE TABLE IF NOT EXISTS `PREFIX_booking_deposit_history` (
    `id_history` int(11) NOT NULL AUTO_INCREMENT,
    `id_deposit` int(11) NOT NULL,
    `id_reservation` int(11) NOT NULL,
    `action_type` varchar(50) NOT NULL COMMENT 'created, authorized, captured, released, refunded, failed',
    `old_status` varchar(20) DEFAULT NULL,
    `new_status` varchar(20) NOT NULL,
    `amount` int(11) DEFAULT NULL COMMENT 'Montant concerné en centimes',
    `stripe_id` varchar(255) DEFAULT NULL COMMENT 'ID Stripe de la transaction',
    `details` text DEFAULT NULL COMMENT 'Détails de l''action',
    `id_employee` int(11) DEFAULT NULL COMMENT 'Employé qui a effectué l''action',
    `date_add` datetime NOT NULL,
    PRIMARY KEY (`id_history`),
    KEY `idx_deposit` (`id_deposit`),
    KEY `idx_reservation` (`id_reservation`),
    KEY `idx_action_type` (`action_type`),
    KEY `idx_date_add` (`date_add`),
    FOREIGN KEY (`id_deposit`) REFERENCES `PREFIX_booking_deposits` (`id_deposit`) ON DELETE CASCADE,
    FOREIGN KEY (`id_reservation`) REFERENCES `PREFIX_booker_auth_reserved` (`id_reserved`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Table pour les webhooks Stripe reçus
CREATE TABLE IF NOT EXISTS `PREFIX_booking_webhooks` (
    `id_webhook` int(11) NOT NULL AUTO_INCREMENT,
    `stripe_event_id` varchar(255) NOT NULL,
    `event_type` varchar(100) NOT NULL,
    `processed` tinyint(1) NOT NULL DEFAULT 0,
    `payload` longtext DEFAULT NULL COMMENT 'JSON du webhook',
    `processing_result` text DEFAULT NULL,
    `date_received` datetime NOT NULL,
    `date_processed` datetime DEFAULT NULL,
    PRIMARY KEY (`id_webhook`),
    UNIQUE KEY `idx_stripe_event` (`stripe_event_id`),
    KEY `idx_event_type` (`event_type`),
    KEY `idx_processed` (`processed`),
    KEY `idx_date_received` (`date_received`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Ajouter nouvelles colonnes à la table principale des réservations si elles n'existent pas
ALTER TABLE `PREFIX_booker_auth_reserved` 
ADD COLUMN IF NOT EXISTS `deposit_required` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Caution requise',
ADD COLUMN IF NOT EXISTS `deposit_rate` decimal(5,2) DEFAULT NULL COMMENT 'Taux de caution personnalisé',
ADD COLUMN IF NOT EXISTS `deposit_status` varchar(20) DEFAULT 'none' COMMENT 'Statut de la caution',
ADD COLUMN IF NOT EXISTS `card_fingerprint` varchar(255) DEFAULT NULL COMMENT 'Empreinte de la carte pour la caution',
ADD COLUMN IF NOT EXISTS `auto_capture_date` datetime DEFAULT NULL COMMENT 'Date de capture automatique de la caution';

-- Index pour optimiser les performances
ALTER TABLE `PREFIX_booker_auth_reserved` 
ADD INDEX IF NOT EXISTS `idx_deposit_status` (`deposit_status`),
ADD INDEX IF NOT EXISTS `idx_auto_capture` (`auto_capture_date`);

-- Table pour la configuration avancée des cautions
CREATE TABLE IF NOT EXISTS `PREFIX_booking_deposit_config` (
    `id_config` int(11) NOT NULL AUTO_INCREMENT,
    `id_booker` int(11) DEFAULT NULL COMMENT 'Configuration spécifique à un booker, NULL = global',
    `deposit_required` tinyint(1) NOT NULL DEFAULT 1,
    `deposit_rate` decimal(5,2) NOT NULL DEFAULT 30.00 COMMENT 'Pourcentage de caution',
    `min_deposit_amount` decimal(10,2) NOT NULL DEFAULT 50.00 COMMENT 'Montant minimum de caution',
    `max_deposit_amount` decimal(10,2) NOT NULL DEFAULT 2000.00 COMMENT 'Montant maximum de caution',
    `auto_capture_delay` int(11) NOT NULL DEFAULT 24 COMMENT 'Délai avant capture auto en heures',
    `auto_release_delay` int(11) NOT NULL DEFAULT 168 COMMENT 'Délai avant libération auto en heures (7 jours)',
    `capture_on_start` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Capturer au début de la réservation',
    `release_on_end` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Libérer à la fin de la réservation',
    `date_add` datetime NOT NULL,
    `date_upd` datetime NOT NULL,
    PRIMARY KEY (`id_config`),
    UNIQUE KEY `idx_booker` (`id_booker`),
    FOREIGN KEY (`id_booker`) REFERENCES `PREFIX_booker` (`id_booker`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Insérer la configuration globale par défaut
INSERT IGNORE INTO `PREFIX_booking_deposit_config` 
(`id_booker`, `deposit_required`, `deposit_rate`, `min_deposit_amount`, `max_deposit_amount`, `auto_capture_delay`, `auto_release_delay`, `date_add`, `date_upd`)
VALUES 
(NULL, 1, 30.00, 50.00, 2000.00, 24, 168, NOW(), NOW());

-- Table pour les templates d'emails liés aux cautions
CREATE TABLE IF NOT EXISTS `PREFIX_booking_deposit_email_templates` (
    `id_template` int(11) NOT NULL AUTO_INCREMENT,
    `id_lang` int(11) NOT NULL,
    `template_key` varchar(50) NOT NULL COMMENT 'deposit_authorized, deposit_captured, deposit_released, deposit_failed',
    `subject` varchar(255) NOT NULL,
    `content_html` longtext NOT NULL,
    `content_text` longtext NOT NULL,
    `active` tinyint(1) NOT NULL DEFAULT 1,
    `date_add` datetime NOT NULL,
    `date_upd` datetime NOT NULL,
    PRIMARY KEY (`id_template`),
    UNIQUE KEY `idx_lang_key` (`id_lang`, `template_key`),
    KEY `idx_template_key` (`template_key`),
    FOREIGN KEY (`id_lang`) REFERENCES `PREFIX_lang` (`id_lang`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Insérer les templates par défaut pour le français
INSERT IGNORE INTO `PREFIX_booking_deposit_email_templates` 
(`id_lang`, `template_key`, `subject`, `content_html`, `content_text`, `date_add`, `date_upd`)
VALUES 
(
    (SELECT id_lang FROM `PREFIX_lang` WHERE iso_code = 'fr' LIMIT 1),
    'deposit_authorized',
    'Caution autorisée pour votre réservation {booking_reference}',
    '<h2>Caution autorisée</h2><p>Bonjour {customer_name},</p><p>Nous vous confirmons que la caution de <strong>{deposit_amount}</strong> pour votre réservation <strong>{booking_reference}</strong> a été autorisée sur votre carte bancaire.</p><p>Cette autorisation sera libérée automatiquement à la fin de votre réservation si tout se passe bien.</p><p>Cordialement,<br>L''équipe</p>',
    'Caution autorisée\n\nBonjour {customer_name},\n\nNous vous confirmons que la caution de {deposit_amount} pour votre réservation {booking_reference} a été autorisée sur votre carte bancaire.\n\nCette autorisation sera libérée automatiquement à la fin de votre réservation si tout se passe bien.\n\nCordialement,\nL''équipe',
    NOW(),
    NOW()
),
(
    (SELECT id_lang FROM `PREFIX_lang` WHERE iso_code = 'fr' LIMIT 1),
    'deposit_captured',
    'Caution prélevée pour votre réservation {booking_reference}',
    '<h2>Caution prélevée</h2><p>Bonjour {customer_name},</p><p>Nous vous informons que la caution de <strong>{deposit_amount}</strong> pour votre réservation <strong>{booking_reference}</strong> a été prélevée sur votre carte bancaire.</p><p>Raison : {capture_reason}</p><p>Si vous avez des questions, n''hésitez pas à nous contacter.</p><p>Cordialement,<br>L''équipe</p>',
    'Caution prélevée\n\nBonjour {customer_name},\n\nNous vous informons que la caution de {deposit_amount} pour votre réservation {booking_reference} a été prélevée sur votre carte bancaire.\n\nRaison : {capture_reason}\n\nSi vous avez des questions, n''hésitez pas à nous contacter.\n\nCordialement,\nL''équipe',
    NOW(),
    NOW()
),
(
    (SELECT id_lang FROM `PREFIX_lang` WHERE iso_code = 'fr' LIMIT 1),
    'deposit_released',
    'Caution libérée pour votre réservation {booking_reference}',
    '<h2>Caution libérée</h2><p>Bonjour {customer_name},</p><p>Excellente nouvelle ! La caution de <strong>{deposit_amount}</strong> pour votre réservation <strong>{booking_reference}</strong> a été libérée.</p><p>Aucun montant ne sera prélevé sur votre carte bancaire.</p><p>Merci d''avoir respecté les conditions de location.</p><p>Cordialement,<br>L''équipe</p>',
    'Caution libérée\n\nBonjour {customer_name},\n\nExcellente nouvelle ! La caution de {deposit_amount} pour votre réservation {booking_reference} a été libérée.\n\nAucun montant ne sera prélevé sur votre carte bancaire.\n\nMerci d''avoir respecté les conditions de location.\n\nCordialement,\nL''équipe',
    NOW(),
    NOW()
),
(
    (SELECT id_lang FROM `PREFIX_lang` WHERE iso_code = 'fr' LIMIT 1),
    'deposit_failed',
    'Problème avec la caution pour votre réservation {booking_reference}',
    '<h2>Problème avec la caution</h2><p>Bonjour {customer_name},</p><p>Nous rencontrons un problème avec l''autorisation de la caution pour votre réservation <strong>{booking_reference}</strong>.</p><p>Erreur : {error_message}</p><p>Veuillez nous contacter rapidement pour régulariser la situation.</p><p>Cordialement,<br>L''équipe</p>',
    'Problème avec la caution\n\nBonjour {customer_name},\n\nNous rencontrons un problème avec l''autorisation de la caution pour votre réservation {booking_reference}.\n\nErreur : {error_message}\n\nVeuillez nous contacter rapidement pour régulariser la situation.\n\nCordialement,\nL''équipe',
    NOW(),
    NOW()
);

-- Ajouter les nouvelles configurations pour Stripe
INSERT IGNORE INTO `PREFIX_configuration` (`name`, `value`, `date_add`, `date_upd`) VALUES
('BOOKING_STRIPE_DEPOSIT_ENABLED', '1', NOW(), NOW()),
('BOOKING_DEPOSIT_RATE', '30', NOW(), NOW()),
('BOOKING_DEPOSIT_MIN_AMOUNT', '50', NOW(), NOW()),
('BOOKING_DEPOSIT_MAX_AMOUNT', '2000', NOW(), NOW()),
('BOOKING_DEPOSIT_AUTO_CAPTURE_DELAY', '24', NOW(), NOW()),
('BOOKING_DEPOSIT_AUTO_RELEASE_DELAY', '168', NOW(), NOW()),
('BOOKING_DEPOSIT_CAPTURE_ON_START', '0', NOW(), NOW()),
('BOOKING_DEPOSIT_RELEASE_ON_END', '1', NOW(), NOW()),
('BOOKING_STRIPE_WEBHOOK_SECRET', '', NOW(), NOW()),
('BOOKING_STRIPE_TEST_MODE', '1', NOW(), NOW()),
('BOOKING_STRIPE_TEST_SECRET_KEY', '', NOW(), NOW()),
('BOOKING_STRIPE_TEST_PUBLIC_KEY', '', NOW(), NOW()),
('BOOKING_STRIPE_LIVE_SECRET_KEY', '', NOW(), NOW()),
('BOOKING_STRIPE_LIVE_PUBLIC_KEY', '', NOW(), NOW());

-- Mise à jour des statuts de commande pour les réservations
INSERT IGNORE INTO `PREFIX_order_state` (`id_order_state`, `invoice`, `send_email`, `module_name`, `color`, `unremovable`, `hidden`, `logable`, `delivery`, `shipped`, `paid`, `pdf_invoice`, `pdf_delivery`, `deleted`, `template`) VALUES
(NULL, 0, 1, 'booking', '#FF6600', 1, 0, 1, 0, 0, 0, 0, 0, 0, 'booking_pending');

-- Récupérer l'ID du statut créé et l'assigner à la configuration
SET @booking_status_id = LAST_INSERT_ID();
INSERT IGNORE INTO `PREFIX_configuration` (`name`, `value`, `date_add`, `date_upd`) VALUES
('BOOKING_STATUS_PENDING_PAYMENT', @booking_status_id, NOW(), NOW());

-- Ajouter les traductions pour le nouveau statut
INSERT IGNORE INTO `PREFIX_order_state_lang` (`id_order_state`, `id_lang`, `name`, `template`) 
SELECT @booking_status_id, id_lang, 'Réservation en attente de paiement', 'booking_pending'
FROM `PREFIX_lang` WHERE iso_code = 'fr';

INSERT IGNORE INTO `PREFIX_order_state_lang` (`id_order_state`, `id_lang`, `name`, `template`) 
SELECT @booking_status_id, id_lang, 'Booking pending payment', 'booking_pending'
FROM `PREFIX_lang` WHERE iso_code = 'en';

-- Vue pour simplifier les requêtes sur les cautions
CREATE OR REPLACE VIEW `PREFIX_booking_deposits_full` AS
SELECT 
    d.*,
    r.booking_reference,
    r.customer_firstname,
    r.customer_lastname,
    r.customer_email,
    r.date_reserved,
    r.total_price,
    r.status as reservation_status,
    b.name as booker_name,
    (d.deposit_amount / 100) as deposit_amount_decimal,
    (d.captured_amount / 100) as captured_amount_decimal,
    (d.refunded_amount / 100) as refunded_amount_decimal
FROM `PREFIX_booking_deposits` d
LEFT JOIN `PREFIX_booker_auth_reserved` r ON d.id_reservation = r.id_reserved
LEFT JOIN `PREFIX_booker` b ON r.id_booker = b.id_booker;

-- Procédure stockée pour nettoyer les anciennes données
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS CleanOldBookingData()
BEGIN
    -- Supprimer les webhooks de plus de 30 jours
    DELETE FROM `PREFIX_booking_webhooks` 
    WHERE date_received < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- Supprimer l'historique des cautions de plus de 1 an
    DELETE FROM `PREFIX_booking_deposit_history` 
    WHERE date_add < DATE_SUB(NOW(), INTERVAL 1 YEAR);
    
    -- Marquer comme expirées les réservations dont la caution a échoué depuis plus de 24h
    UPDATE `PREFIX_booker_auth_reserved` 
    SET status = 4 -- STATUS_EXPIRED
    WHERE status = 0 -- STATUS_PENDING
    AND deposit_status = 'failed'
    AND date_add < DATE_SUB(NOW(), INTERVAL 24 HOUR);
    
END$$
DELIMITER ;

-- Event scheduler pour exécuter le nettoyage automatiquement (si activé)
-- SET GLOBAL event_scheduler = ON;
-- CREATE EVENT IF NOT EXISTS BookingDataCleanup
-- ON SCHEDULE EVERY 1 DAY STARTS '2025-06-17 02:00:00'
-- DO CALL CleanOldBookingData();
