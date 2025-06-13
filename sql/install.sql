CREATE TABLE IF NOT EXISTS `PREFIX_booker` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `id_product` int(11) DEFAULT NULL,
    `name` varchar(255) NOT NULL,
    `description` text,
    `price` decimal(10,2) DEFAULT 0.00,
    `duration` int(11) DEFAULT 60,
    `max_bookings` int(11) DEFAULT 1,
    `active` tinyint(1) DEFAULT 1,
    `auto_confirm` tinyint(1) DEFAULT 0,
    `require_deposit` tinyint(1) DEFAULT 0,
    `deposit_amount` decimal(10,2) DEFAULT 0.00,
    `cancellation_hours` int(11) DEFAULT 24,
    `image` varchar(255) DEFAULT NULL,
    `sort_order` int(11) DEFAULT 0,
    `date_add` datetime NOT NULL,
    `date_upd` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_product` (`id_product`),
    KEY `idx_active` (`active`)
);

CREATE TABLE IF NOT EXISTS `PREFIX_booker_auth` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
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
    PRIMARY KEY (`id`),
    KEY `idx_booker` (`id_booker`),
    KEY `idx_date_range` (`date_from`, `date_to`),
    KEY `idx_active` (`active`)
);

CREATE TABLE IF NOT EXISTS `PREFIX_booker_auth_reserved` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `id_auth` int(11) NOT NULL,
    `id_booker` int(11) NOT NULL,
    `id_customer` int(11) DEFAULT NULL,
    `id_order` int(11) DEFAULT NULL,
    `booking_reference` varchar(50) NOT NULL,
    `customer_firstname` varchar(100) NOT NULL,
    `customer_lastname` varchar(100) NOT NULL,
    `customer_email` varchar(150) NOT NULL,
    `customer_phone` varchar(50) DEFAULT NULL,
    `date_start` datetime NOT NULL,
    `date_end` datetime NOT NULL,
    `total_price` decimal(10,2) DEFAULT 0.00,
    `deposit_paid` decimal(10,2) DEFAULT 0.00,
    `status` enum('pending','confirmed','paid','cancelled','completed','refunded') DEFAULT 'pending',
    `payment_status` enum('pending','authorized','captured','cancelled','refunded') DEFAULT 'pending',
    `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
    `stripe_deposit_intent_id` varchar(255) DEFAULT NULL,
    `notes` text,
    `admin_notes` text,
    `date_add` datetime NOT NULL,
    `date_upd` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_reference` (`booking_reference`),
    KEY `idx_auth` (`id_auth`),
    KEY `idx_booker` (`id_booker`),
    KEY `idx_customer` (`id_customer`),
    KEY `idx_order` (`id_order`),
    KEY `idx_status` (`status`),
    KEY `idx_date_range` (`date_start`, `date_end`)
);

CREATE TABLE IF NOT EXISTS `PREFIX_booker_product` (
    `id_booker` int(11) NOT NULL,
    `id_product` int(11) NOT NULL,
    `sync_price` tinyint(1) DEFAULT 1,
    `override_price` decimal(10,2) DEFAULT NULL,
    `date_add` datetime NOT NULL,
    PRIMARY KEY (`id_booker`, `id_product`),
    KEY `idx_product` (`id_product`)
);

CREATE TABLE IF NOT EXISTS `PREFIX_booker_reservation_order` (
    `id_reservation` int(11) NOT NULL,
    `id_order` int(11) NOT NULL,
    `order_type` enum('booking','deposit') DEFAULT 'booking',
    `amount` decimal(10,2) NOT NULL,
    `date_add` datetime NOT NULL,
    PRIMARY KEY (`id_reservation`, `id_order`, `order_type`),
    KEY `idx_order` (`id_order`)
);
