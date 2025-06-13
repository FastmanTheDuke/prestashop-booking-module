<?php
// Création de la table principale avec TOUTES les colonnes d'un coup
$bookerauthreserved = Db::getInstance()->execute(
    "CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."booker_auth_reserved`(
    `id_reserved` int(10) unsigned NOT NULL AUTO_INCREMENT,		
    `id_booker` int(10) unsigned NOT NULL,		
    `date_reserved` DATE NOT NULL,
    `date_to` DATE NULL,
    `hour_from` tinyint(2) unsigned NOT NULL,
    `hour_to` tinyint(2) unsigned NOT NULL,
    `status` tinyint(1) unsigned NOT NULL DEFAULT '0',
    `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    
    -- Colonnes étendues pour le système complet
    `booking_reference` VARCHAR(20) NULL,
    `customer_firstname` VARCHAR(100) NULL,
    `customer_lastname` VARCHAR(100) NULL,
    `customer_email` VARCHAR(150) NULL,
    `customer_phone` VARCHAR(20) NULL,
    `customer_message` TEXT NULL,
    `total_price` DECIMAL(10,2) NULL DEFAULT 0.00,
    `deposit_amount` DECIMAL(10,2) NULL DEFAULT 0.00,
    `id_order` INT(10) unsigned NULL,
    `payment_status` TINYINT(1) unsigned DEFAULT 0,
    `cancellation_reason` TEXT NULL,
    
    PRIMARY KEY (`id_reserved`),
    INDEX `idx_id_booker` (`id_booker`),
    INDEX `idx_date_reserved` (`date_reserved`),
    INDEX `idx_status` (`status`),
    INDEX `idx_active` (`active`),
    INDEX `idx_booking_reference` (`booking_reference`),
    INDEX `idx_customer_email` (`customer_email`),
    INDEX `idx_id_order` (`id_order`),
    INDEX `idx_payment_status` (`payment_status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=UTF8"
);

// Créer la table pour les sessions Stripe seulement si la table principale a été créée
if ($bookerauthreserved) {
    $stripe_sessions = Db::getInstance()->execute(
        "CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."booking_stripe_sessions`(
        `id_session` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `id_reservation` int(10) unsigned NOT NULL,
        `session_id` VARCHAR(255) NOT NULL,
        `payment_intent_id` VARCHAR(255) NULL,
        `status` VARCHAR(50) DEFAULT 'pending',
        `date_add` DATETIME NOT NULL,
        `date_upd` DATETIME NOT NULL,
        
        PRIMARY KEY (`id_session`),
        INDEX `idx_id_reservation` (`id_reservation`),
        INDEX `idx_session_id` (`session_id`),
        INDEX `idx_payment_intent_id` (`payment_intent_id`),
        INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=UTF8"
    );

    // Créer la table des logs d'activité
    $activity_logs = Db::getInstance()->execute(
        "CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."booking_activity_log`(
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
        ) ENGINE=InnoDB DEFAULT CHARSET=UTF8"
    );
}

// IMPORTANT : Ne pas faire de migration de données pendant l'installation !
// La migration se fera dans updateReservationTable() de booking.php après la création

?>